<?php
/**
 * DeoVR Web API Implementation
 *
 * Endpoints:
 *   GET/POST /deovr/          -> Library index (scenes with video_url links to detail endpoints)
 *   GET/POST /deovr/{id}      -> Individual scene detail with encodings/videoSources
 *
 * Reference: XBVR deovr.go (github.com/xbapps/xbvr)
 *
 * DeoVR Library structure (DeoLibrary):
 *   { "scenes": [{ "name": "...", "list": [DeoListItem, ...] }], "authorized": "1" }
 *
 * DeoListItem: { "title", "videoLength", "thumbnailUrl", "video_url" }
 *   - video_url points to the scene detail endpoint, NOT the actual video file
 *
 * DeoScene (scene detail): { "id", "authorized", "title", "encodings": [{ "name", "videoSources": [...] }], ... }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTPS_HOST'] ?? '192.168.1.12';
$baseUrl = $scheme . '://' . $host;

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path, '/');

$dbFile = __DIR__ . '/videodb.json';
$ratingsFile = __DIR__ . '/ratings.json';

$groupedVideos = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
$ratings = file_exists($ratingsFile) ? json_decode(file_get_contents($ratingsFile), true) : [];

foreach ($groupedVideos as $baseName => &$data) {
    if (isset($ratings[$baseName])) {
        $data['metadata']['rating'] = $ratings[$baseName];
    }
}
unset($data);

function makeUrl(string $path, string $baseUrl): string {
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^\./#', '', $path);
    $parts = explode('/', $path);
    $parts = array_map('rawurlencode', $parts);
    return $baseUrl . '/' . implode('/', $parts);
}

function formatBytes(int $bytes): string {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

function resolveThumbnail(array $data, string $baseName, string $baseUrl): string {
    $coversDir = 'covers';
    foreach (['.webp', '.jpg', '.jpeg', '.png'] as $ext) {
        if (file_exists(__DIR__ . '/' . $coversDir . '/' . $baseName . $ext)) {
            return makeUrl($coversDir . '/' . $baseName . $ext, $baseUrl);
        }
    }

    $assetDir = rtrim($data['assetDir'] ?? '', '/\\');
    $assetDir = preg_replace('#^\./#', '', str_replace('\\', '/', $assetDir));
    foreach (['.webp', '.jpg', '.jpeg', '.png'] as $ext) {
        if (file_exists(__DIR__ . '/' . $assetDir . '/' . $baseName . $ext)) {
            return makeUrl($assetDir . '/' . $baseName . $ext, $baseUrl);
        }
    }

    return makeUrl('covers/placeholder.jpg', $baseUrl);
}

function resolvePreview(string $baseName, string $baseUrl): string {
    $previewsDir = 'previews';
    if (file_exists(__DIR__ . '/' . $previewsDir . '/' . $baseName . '.mp4')) {
        return makeUrl($previewsDir . '/' . $baseName . '.mp4', $baseUrl);
    }
    return '';
}

function buildDeoScene(string $baseName, array $data, string $baseUrl): array {
    $metadata = $data['metadata'] ?? [];

    $encodings = [];
    $videos = $data['videos'] ?? [];
    usort($videos, function($a, $b) { return strcmp($a['file'], $b['file']); });

    $totalParts = count($videos);
    $videoLength = 0;

    foreach ($videos as $i => $video) {
        if (preg_match('/(_preview|_p|_P)\.mp4$/i', $video['file'])) {
            continue;
        }

        $fsPath = rtrim(str_replace('\\', '/', $video['dir']), '/') . '/' . $video['file'];
        $height = (int)($metadata['height'] ?? 2048);
        $width = (int)($metadata['width'] ?? 4096);

        $absPath = __DIR__ . '/' . preg_replace('#^\./#', '', $fsPath);
        $fileSize = file_exists($absPath) ? filesize($absPath) : 0;

        $label = sprintf("File %d/%d %dp", $i + 1, $totalParts, $height);
        if ($fileSize > 0) {
            $label .= ' - ' . formatBytes($fileSize);
        }

        $encodings[] = [
            'name' => $label,
            'videoSources' => [
                [
                    'resolution' => $height,
                    'height' => $height,
                    'width' => $width,
                    'size' => $fileSize,
                    'url' => makeUrl($fsPath, $baseUrl),
                ],
            ],
        ];
    }

    $runtimeMinutes = (float)($metadata['runtime'] ?? 0);
    $videoLengthSeconds = (int)($runtimeMinutes * 60);

    $actors = [];
    if (!empty($metadata['actors']) && is_array($metadata['actors'])) {
        foreach ($metadata['actors'] as $idx => $actor) {
            $actors[] = ['id' => $idx, 'name' => $actor];
        }
    }

    $categories = [];
    if (!empty($metadata['genres']) && is_array($metadata['genres'])) {
        foreach ($metadata['genres'] as $idx => $genre) {
            $categories[] = ['tag' => ['id' => $idx, 'name' => $genre]];
        }
    }

    $paysite = [
        'id' => 1,
        'name' => $metadata['studio'] ?? 'Local',
        'is3rdParty' => true,
    ];

    $thumbnailUrl = resolveThumbnail($data, $baseName, $baseUrl);
    $videoPreview = resolvePreview($baseName, $baseUrl);

    $ratingAvg = isset($metadata['userrating']) ? (float)$metadata['userrating'] : 0.0;

    $dateStr = $metadata['premiered'] ?? '';
    $dateUnix = 0;
    if (!empty($dateStr)) {
        $ts = strtotime($dateStr);
        if ($ts !== false && $ts > 0) {
            $dateUnix = $ts;
        }
    }

    $sceneId = (int)($data['id'] ?? 0);

    $stereoMode = 'sbs';
    $screenType = 'dome';

    $scene = [
        'id' => $sceneId,
        'authorized' => 1,
        'title' => $metadata['title'] ?? $baseName,
        'description' => $metadata['plot'] ?? '',
        'date' => $dateUnix,
        'paysite' => $paysite,
        'isFavorite' => false,
        'isScripted' => false,
        'isWatchlist' => false,
        'is3d' => true,
        'thumbnailUrl' => $thumbnailUrl,
        'rating_avg' => $ratingAvg,
        'screenType' => $screenType,
        'stereoMode' => $stereoMode,
        'videoLength' => $videoLengthSeconds,
        'videoThumbnail' => $thumbnailUrl,
        'encodings' => $encodings,
        'timeStamps' => [],
        'actors' => $actors,
        'categories' => $categories,
        'fullVideoReady' => true,
        'fullAccess' => true,
    ];

    if (!empty($videoPreview)) {
        $scene['videoPreview'] = $videoPreview;
    }

    return $scene;
}

if (preg_match('#/deovr/(\d+)$#', $path, $matches)) {
    $videoId = (int)$matches[1];

    $found = false;
    foreach ($groupedVideos as $baseName => $data) {
        if (isset($data['id']) && (int)$data['id'] === $videoId) {
            echo json_encode(buildDeoScene($baseName, $data, $baseUrl));
            $found = true;
            break;
        }
    }

    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Scene not found']);
    }
    exit;
}

if (preg_match('#/deovr/video/(.+)$#', $path, $matches)) {
    $baseName = urldecode($matches[1]);

    if (isset($groupedVideos[$baseName])) {
        echo json_encode(buildDeoScene($baseName, $groupedVideos[$baseName], $baseUrl));
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Scene not found']);
    }
    exit;
}

$list = [];
foreach ($groupedVideos as $baseName => $data) {
    $metadata = $data['metadata'] ?? [];

    $runtimeMinutes = (float)($metadata['runtime'] ?? 0);
    $videoLengthSeconds = (int)($runtimeMinutes * 60);

    $thumbnailUrl = resolveThumbnail($data, $baseName, $baseUrl);

    if (isset($data['id'])) {
        $videoUrl = $baseUrl . '/deovr/' . $data['id'];
    } else {
        $videoUrl = $baseUrl . '/deovr/video/' . rawurlencode($baseName);
    }

    $list[] = [
        'title' => $metadata['title'] ?? $baseName,
        'videoLength' => $videoLengthSeconds,
        'thumbnailUrl' => $thumbnailUrl,
        'video_url' => $videoUrl,
    ];
}

echo json_encode([
    'scenes' => [
        [
            'name' => 'Local VR Library',
            'list' => $list,
        ],
    ],
    'authorized' => '1',
]);
?>
