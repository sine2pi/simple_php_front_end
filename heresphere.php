<?php
/**
 * HereSphere Web API Implementation
 *
 * Endpoints:
 *   POST/GET /heresphere/          -> Library index (list of video detail URLs)
 *   POST/GET /heresphere/{id}      -> Individual video detail with media sources
 *   POST/GET /heresphere/scan      -> Scan endpoint (same as library, kept for compat)
 *
 * Reference: XBVR heresphere_api.go (github.com/xbapps/xbvr)
 */

header('Content-Type: application/json');
header('HereSphere-JSON-Version: 1');
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

$rawBody = file_get_contents('php://input');
$postData = json_decode($rawBody, true) ?: [];
$needsMediaSource = $postData['needsMediaSource'] ?? true;

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

function makeUrl(?string $path, ?string $baseUrl): string {
    $path = $path ?? '';
    $baseUrl = $baseUrl ?? '';

    $path = str_replace('\\', '/', $path);

    $path = preg_replace('#^\./#', '', $path);

    $parts = explode('/', $path);
    $parts = array_map('rawurlencode', $parts);

    return rtrim($baseUrl, '/') . '/' . ltrim(implode('/', $parts), '/');
}

function resolveThumbnail(array $data, string $baseName, string $baseUrl): string {
    $coversDir = 'covers';

    $coverCandidates = [
        $baseName . '.webp',
        $baseName . '.jpg',
        $baseName . '.jpeg',
        $baseName . '.png',
    ];

    foreach ($coverCandidates as $cand) {
        $coverFs = __DIR__ . '/' . $coversDir . '/' . $cand;
        if (file_exists($coverFs)) {
            return makeUrl($coversDir . '/' . $cand, $baseUrl);
        }
    }

    $assetDir = rtrim($data['assetDir'] ?? '', '/\\');
    $assetDir = preg_replace('#^\./#', '', $assetDir);
    foreach (['.webp', '.jpg', '.jpeg', '.png'] as $ext) {
        $coverFs = __DIR__ . '/' . $assetDir . '/' . $baseName . $ext;
        if (file_exists($coverFs)) {
            return makeUrl($assetDir . '/' . $baseName . $ext, $baseUrl);
        }
    }

    return makeUrl('covers/placeholder.jpg', $baseUrl);
}

function resolvePreview(string $baseName, string $baseUrl): string {
    $previewsDir = 'previews';
    $candidates = [
        $baseName . '.mp4',
    ];
    foreach ($candidates as $cand) {
        if (file_exists(__DIR__ . '/' . $previewsDir . '/' . $cand)) {
            return makeUrl($previewsDir . '/' . $cand, $baseUrl);
        }
    }
    return '';
}

function buildVideoDetail(string $baseName, array $data, string $baseUrl, bool $needsMediaSource): array {
    $metadata = $data['metadata'] ?? [];

    $media = [];
    if ($needsMediaSource) {
        $videos = $data['videos'] ?? [];
        usort($videos, function($a, $b) { return strcmp($a['file'], $b['file']); });

        foreach ($videos as $i => $video) {
            if (preg_match('/(_preview|_p|_P)\.mp4$/i', $video['file'])) {
                continue;
            }

            $fsPath = rtrim(str_replace('\\', '/', $video['dir']), '/') . '/' . $video['file'];
            $height = (int)($metadata['height'] ?? 2048);
            $width = (int)($metadata['width'] ?? 4096);
            $resolution = (string)$height;

            $absPath = __DIR__ . '/' . preg_replace('#^\./#', '', $fsPath);
            $fileSize = file_exists($absPath) ? filesize($absPath) : 0;

            $partLabel = sprintf("File %d/%d %sp", $i + 1, count($videos), $resolution);
            if ($fileSize > 0) {
                $partLabel .= ' - ' . formatBytes($fileSize);
            }

            $media[] = [
                'name' => $partLabel,
                'sources' => [
                    [
                        'resolution' => $resolution,
                        'height' => $height,
                        'width' => $width,
                        'size' => $fileSize,
                        'url' => makeUrl($fsPath, $baseUrl),
                    ],
                ],
            ];
        }
    }

    $tags = [];
    if (!empty($metadata['studio'])) {
        $tags[] = ['name' => 'Studio:' . $metadata['studio']];
    }
    if (!empty($metadata['director'])) {
        $tags[] = ['name' => 'Director:' . $metadata['director']];
    }
    if (!empty($metadata['genres']) && is_array($metadata['genres'])) {
        foreach ($metadata['genres'] as $genre) {
            $tags[] = ['name' => 'Category:' . $genre];
        }
    }
    if (!empty($metadata['actors']) && is_array($metadata['actors'])) {
        foreach ($metadata['actors'] as $actor) {
            $tags[] = ['name' => 'Talent:' . $actor];
        }
    }

    $subtitles = [];
    if (!empty($metadata['has_srt'])) {
        $assetDir = rtrim(str_replace('\\', '/', $data['assetDir'] ?? ''), '/');
        $assetDir = preg_replace('#^\./#', '', $assetDir);
        $srtPath = $assetDir . '/' . $baseName . '.srt';
        if (file_exists(__DIR__ . '/' . $srtPath)) {
            $subtitles[] = [
                'name' => $baseName . '.srt',
                'language' => 'English',
                'url' => makeUrl($srtPath, $baseUrl),
            ];
        }
    }

    $runtimeMinutes = (float)($metadata['runtime'] ?? 0);
    $durationMs = (int)($runtimeMinutes * 60 * 1000);

    $rating = isset($metadata['userrating']) ? (float)$metadata['userrating'] : 0.0;
    $dateReleased = $metadata['premiered'] ?? ($metadata['year'] ? $metadata['year'] . '-01-01' : '');
    $dateAdded = $metadata['dateAdded'] ?? $dateReleased;

    $thumbnailImage = resolveThumbnail($data, $baseName, $baseUrl);
    $thumbnailVideo = resolvePreview($baseName, $baseUrl);

    $response = [
        'access' => 1,
        'title' => $baseName,
        'description' => $metadata['plot'] ?? '',
        'thumbnailImage' => $thumbnailImage,
        'dateReleased' => $dateReleased,
        'dateAdded' => $dateAdded,
        'duration' => $durationMs,
        'rating' => $rating,
        'isFavorite' => false,
        'projection' => 'equirectangular',
        'stereo' => 'sbs',
        'fov' => 180.0,
        'lens' => 'Linear',
        'tags' => $tags,
        'media' => $media,
        'writeFavorite' => false,
        'writeRating' => true,
        'writeTags' => false,
        'writeHSP' => false,
    ];

    if (!empty($thumbnailVideo)) {
        $response['thumbnailVideo'] = $thumbnailVideo;
    }

    if (!empty($subtitles)) {
        $response['subtitles'] = $subtitles;
    }

    return $response;
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

if (preg_match('#/heresphere/(\d+)$#', $path, $matches)) {
    $videoId = (int)$matches[1];

    $found = false;
    foreach ($groupedVideos as $baseName => $data) {
        if (isset($data['id']) && (int)$data['id'] === $videoId) {
            handleUpdates($baseName, $postData);

            echo json_encode(buildVideoDetail($baseName, $data, $baseUrl, $needsMediaSource));
            $found = true;
            break;
        }
    }

    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Video not found']);
    }
    exit;
}

if (preg_match('#/heresphere/video/(.+)$#', $path, $matches)) {
    $baseName = urldecode($matches[1]);

    if (isset($groupedVideos[$baseName])) {
        handleUpdates($baseName, $postData);
        echo json_encode(buildVideoDetail($baseName, $groupedVideos[$baseName], $baseUrl, $needsMediaSource));
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Video not found']);
    }
    exit;
}

if (preg_match('#/heresphere(/scan)?$#', $path) || $path === '/heresphere') {

    $list = [];
    foreach ($groupedVideos as $baseName => $data) {
        if (isset($data['id'])) {
            $list[] = $baseUrl . '/heresphere/' . $data['id'];
        } else {
            $list[] = $baseUrl . '/heresphere/video/' . rawurlencode($baseName);
        }
    }

    $library = [
        'access' => 1,
        'library' => [
            [
                'name' => 'My Local VR Library',
                'list' => $list,
            ],
        ],
    ];

    echo json_encode($library);
    exit;
}

if (preg_match('#/index\.hsp$#', $path)) {
    header('Location: ' . $baseUrl . '/heresphere');
    exit;
}

$list = [];
foreach ($groupedVideos as $baseName => $data) {
    if (isset($data['id'])) {
        $list[] = $baseUrl . '/heresphere/' . $data['id'];
    } else {
        $list[] = $baseUrl . '/heresphere/video/' . rawurlencode($baseName);
    }
}

echo json_encode([
    'access' => 1,
    'library' => [
        [
            'name' => 'My Local VR Library',
            'list' => $list,
        ],
    ],
]);

function handleUpdates(string $baseName, array $postData): void {
    if (isset($postData['rating'])) {
        $ratingsFile = __DIR__ . '/ratings.json';
        $ratings = file_exists($ratingsFile) ? json_decode(file_get_contents($ratingsFile), true) : [];
        $ratings[$baseName] = (float)$postData['rating'];
        file_put_contents($ratingsFile, json_encode($ratings, JSON_PRETTY_PRINT));
    }
}
?>
