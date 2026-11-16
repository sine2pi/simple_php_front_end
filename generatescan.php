<?php

function parseNFO(string $nfoFile) {
    $nfoData = simplexml_load_file($nfoFile);
    if ($nfoData === false) {
        return [];
    }
    $metadata = [
        'title' => (string)$nfoData->title,
        'originaltitle' => (string)$nfoData->originaltitle,
        'premiered' => (string)$nfoData->premiered,
        'director' => (string)$nfoData->director,
        'studio' => (string)$nfoData->studio,
        'label' => (string)$nfoData->label,
        'year' => (string)$nfoData->year,
        'plot' => (string)$nfoData->plot,
        'runtime' => (string)$nfoData->runtime,
        'rating' => (string)$nfoData->rating,
        'userrating' => (string)$nfoData->userrating,
        'resolution' => (string)$nfoData->fileinfo->streamdetails->video->height,
        "height" => (string)$nfoData->fileinfo->streamdetails->video->height,
        "width" => (string)$nfoData->fileinfo->streamdetails->video->width,
        'dateadded' => (string)$nfoData->dateadded,
        'genres' => [],
        'actors' => [],
    ];
    foreach ($nfoData->genre as $genre) {
        $metadata['genres'][] = (string)$genre;
    }
    foreach ($nfoData->actor as $actor) {
        $metadata['actors'][] = (string)$actor->name;
    }
    return $metadata;
}

$videoDirs = ['./VR']; #  $videoDirs = ['./VR1', './VR2', './VR3'];
$previewsDir = './previews';
$coversDir = './covers';
$videos = [];
foreach($videoDirs as $baseDir) {
    if (!is_dir($baseDir)) continue;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $info) {
        $file = $info->getFilename();
        $dir = $info->getPath();
        $isMp4 = strtolower($info->getExtension()) === 'mp4';
        $isPreview = preg_match('/(_preview|_p|_P)\.mp4$/i', $file);
        if ($isMp4 && !$isPreview) {
            $videos[] = ['file' => $file, 'dir' => $dir];
        }
    }
}

$groupedVideos = [];
foreach ($videos as $video) {
    $filenameNoExt = pathinfo($video['file'], PATHINFO_FILENAME);
    $filenameNoExt = preg_replace('/(_180_LR|_F180)$/i', '', $filenameNoExt);
    $cleanStem = preg_replace('/-([A-Z0-9]{1,2})$/i', '', $filenameNoExt);
    $baseName = $cleanStem;

    if (!isset($groupedVideos[$baseName])) {
        $assetDir = $video['dir'];
        $nfoFilePath = "$assetDir/$baseName.nfo";
        $metadata = file_exists($nfoFilePath) ? parseNFO($nfoFilePath) : [];

        $srtFilePath = "$assetDir/$baseName.srt";
        $metadata['has_srt'] = file_exists($srtFilePath);

        $coverCandidates = [
            $cleanStem . '.webp',
            $cleanStem . '.jpg',
            $cleanStem . '.jpeg',
            $cleanStem . '.png',
            $baseName . '.webp',
            $baseName . '.jpg',
            $baseName . '.jpeg',
            $baseName . '.png'
        ];

        $foundCover = false;
        $coverRel = null;
        $coversPath = __DIR__ . DIRECTORY_SEPARATOR . trim($coversDir, "./\\/");
        foreach ($coverCandidates as $cand) {
            $coverFs = $coversPath . DIRECTORY_SEPARATOR . $cand;
            if (file_exists($coverFs)) {
                $foundCover = true;
                $coverRel = trim($coversDir, '/\\') . '/' . $cand;
                break;
            }
        }

        $previewCandidates = [
            $cleanStem . '.mp4',
            $baseName . '.mp4',
        ];
        $foundPreview = false;
        $previewRel = null;
        $previewsPath = __DIR__ . DIRECTORY_SEPARATOR . trim($previewsDir, "./\\/");
        foreach ($previewCandidates as $cand) {
            $previewFs = $previewsPath . DIRECTORY_SEPARATOR . $cand;
            if (file_exists($previewFs)) {
                $foundPreview = true;
                $previewRel = trim($previewsDir, '/\\') . '/' . $cand;
                break;
            }
        }

        $groupedVideos[$baseName] = [
            'videos' => [],
            'metadata' => $metadata,
            'assetDir' => $assetDir
        ];

        if ($foundCover) {
            $groupedVideos[$baseName]['cover'] = $coverRel;
        }
        if ($foundPreview) {
            $groupedVideos[$baseName]['preview'] = $previewRel;
        }
    }

    $groupedVideos[$baseName]['videos'][] = [
        'file' => $video['file'],
        'dir' => $video['dir']
    ];
}

uasort($groupedVideos, function($a, $b) {
    $yearA = (int)($a['metadata']['year'] ?? 0);
    $yearB = (int)($b['metadata']['year'] ?? 0);

    if ($yearA !== $yearB) {
        return $yearB - $yearA;
    }

    return strcmp($a['metadata']['title'] ?? '', $b['metadata']['title'] ?? '');
});

file_put_contents('videodb.json', json_encode($groupedVideos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Video database generated successfully.";

$baseUrl = 'http://127.0.0.1';
$dbFile = __DIR__ . '/videodb.json';
$outFile = __DIR__ . '/deovr';
$previewsDir = '/previews';
$coversDir = '/covers';

$grouped = json_decode(file_get_contents($dbFile), true);
if (!is_array($grouped)) {
    echo "Error: videodb.json invalid.\n";
    exit(1);
}

$videoList = [];
$i = 0;
foreach ($grouped as $baseName => &$entry) {
    $videos = $entry['videos'] ?? [];
    usort($videos, function($a, $b){ return strcmp($a['file'], $b['file']); });
    $assetDir = rtrim($entry['assetDir'] ?? '', '/\\');
    $first = $videos[0] ?? null;
    if (!$first) continue;

    $makeUrl = function($path) use ($baseUrl) {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^\./#', '', $path);
        $parts = explode('/', $path);
        $parts = array_map('rawurlencode', $parts);
        return $baseUrl . '/' . implode('/', $parts);
    };

    $runtimeStr = $entry['metadata']['runtime'] ?? '';
    $resolutionMeta = $entry['metadata']['resolution'] ?? '';

    $videoLengthSeconds = $runtimeStr !== '' ? $runtimeStr : 60;
    $resolution = $resolutionMeta !== '' ? $resolutionMeta : 2048;

    $videoData = [
        'title' => $baseName,
        'id' => $i,
        'videoLength' => $videoLengthSeconds*60,
        'resolution' => $resolution,
        'is3d' => true,
        'screenType' => 'dome',
        'stereoMode' => 'sbs',
        'skipIntro' => 0
    ];

    if (count($videos) > 1) {
        $sources = [];
        foreach ($videos as $v) {
            $fsPath = rtrim($v['dir'], '/\\') . '/' . $v['file'];
            $sources[] = ['url' => $makeUrl($fsPath)];
        }
        $videoData['videoSources'] = $sources;
    } else {
        $fsPath = rtrim($first['dir'], '/\\') . '/' . $first['file'];
        $videoData['video_url'] = $makeUrl($fsPath);
    }

    $firstFsPath = rtrim($first['dir'], '/\\') . '/' . $first['file'];
    $assetUrl = $makeUrl($firstFsPath);

    $firstFileStem = pathinfo($first['file'], PATHINFO_FILENAME);
    $firstFileStem = preg_replace('/(_180_LR|_F180)$/i', '', $firstFileStem);
    $cleanStem = preg_replace('/-([A-Z0-9]{1,2})$/i', '', $firstFileStem);

    $coverCandidates = [
        $cleanStem . '.webp',
        $cleanStem . '.jpg',
        $cleanStem . '.jpeg',
        $cleanStem . '.png',
        $baseName . '.webp',
        $baseName . '.jpg',
        $baseName . '.jpeg',
        $baseName . '.png'
    ];

    $foundCover = false;
    foreach ($coverCandidates as $cand) {
        $coverRel = trim($coversDir, '/\\') . '/' . $cand;
        $coverFs  = __DIR__ . '/' . $coverRel;
        if (file_exists($coverFs)) {
            $videoData['thumbnailUrl'] = $makeUrl($coverRel);
            $foundCover = true;
            break;
        }
    }

    if (!$foundCover) {
        $absAssetDir = __DIR__ . '/' . preg_replace('#^\./#', '', $assetDir);
        $basePath = $absAssetDir . '/' . $baseName;

        if (file_exists($basePath . '.webp')) {
            $foundExt = '.webp';
        } elseif (file_exists($basePath . '.jpg')) {
            $foundExt = '.jpg';
        } elseif (file_exists($basePath . '.jpeg')) {
            $foundExt = '.jpeg';
        } elseif (file_exists($basePath . '.png')) {
            $foundExt = '.png';
        } else {
            $foundExt = null;
        }
        if ($foundExt) {
            $videoData['thumbnailUrl'] = preg_replace('/\.mp4$/i', $foundExt, $assetUrl);
        } else {
            $safeDir = trim(str_replace('\\', '/', $assetDir), '/');
            $videoData['thumbnailUrl'] = $safeDir ? "{$baseUrl}/{$safeDir}/placeholder.jpg" : "{$baseUrl}/placeholder.jpg";
        }
}

    $firstFileStem = pathinfo($first['file'], PATHINFO_FILENAME);
    $firstFileStem = preg_replace('/(_180_LR|_F180)$/i', '', $firstFileStem);
    $cleanStem = preg_replace('/-([A-Z0-9]{1,2})$/i', '', $firstFileStem);
    
    $candidates = [
        $cleanStem . '.mp4',
        $baseName . '.mp4',
    ];

    $foundPreview = false;
    foreach ($candidates as $cand) {
        $previewRel = trim($previewsDir, '/\\') . '/' . $cand;
        $previewFs  = __DIR__ . '/' . $previewRel;
        if (file_exists($previewFs)) {
            $videoData['videoPreview'] = $makeUrl($previewRel);
            $foundPreview = true;
            break;
        }
    }

    $videoList[] = $videoData;
    $entry['id'] = $i;
    $i++;
}

$out = [
    'scenes' => [
        [
            'name' => 'Local 180 Library',
            'list' => $videoList
        ]
    ],
    'authorized' => '1'
];

file_put_contents($outFile, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($dbFile, json_encode($grouped, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "--- " . count($videoList) . " files in videodb.json.\n";
?>
