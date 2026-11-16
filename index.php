<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Video180</title>

  <style>

body {
    width: 100%;
    height: 100%;
    margin: 0;
    background-color: rgba(0, 0, 0, 1);
    font-family: Arial, sans-serif;
}

.video-container {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    justify-content: center;

}

.thumbnail-container {
  display: flex;
    flex-wrap: wrap;
    gap: 5px;
    justify-content: center;

  }

.cover-image, video {
    width: 100%;
    height: 100%;
    border-radius: 2px;
    cursor: pointer;
    position: absolute;

}

.cover-image {
    z-index: 1;
}

.video-wrapper {
    width: 20vw;
    height: 25vh;
    background-color: rgba(255, 255, 255, 1);
    margin: 2%;
    border-radius: 1%;
    position: relative;
    padding: 0%;
    overflow: hidden;
      box-shadow:
          0 0px 20px rgba(181, 181, 181, 0.57),
          inset 0 0 20px rgba(0, 0, 0, 1);

}

.trailer-preview:hover::after {
    top: 150%;
    left: 150%;
}

.trailer-preview {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: flex; 

}

.video-wrapper:hover .trailer-preview {
    display: block;
    z-index: 1;
}

  .cover-image {
    z-index: 1;
    overflow: hidden;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    }

    .video-details {
    font-size: 10px;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    }

    .play-button {
      padding: 8px 10px;
      margin: 0.5%;
      background-color: black;
      opacity: 90%;
      color: white;
      cursor: pointer;
      z-index: 3;
      border-radius: 4px;
      }

    .play-button:hover {
      background-color: #000000ff;

    }

    .video-filename {
      font-size: 12px;
      padding: 4px 8px;
      background-color: black;
      color: white;
      border-radius: 0px;
      cursor: pointer;
      position: absolute;
      bottom: 0px;
      left: 0;
      z-index: 3;
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 100;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(9, 9, 9, 0.5);
    }

    .modal-content {
      font-size: 0.9vw;
      color: rgba(0, 0, 0, 1);
      background-color: #fefefe;
      margin: 4% auto;
      padding: 0 2% 2% 2%;
      border: 1px solid #888;
      width: 80%;
      max-height: 80%;
      overflow-y: auto;
      max-width: 80%;
      border-radius: 3px;
      box-sizing: border-box;
      position: relative;
      box-shadow:
          0 30px 60px rgba(181, 181, 181, 0.57),
          inset 0 0 20px rgba(0, 0, 0, 1);

    }

    .modal-close {
      color: #aaa;
      position: absolute;
      top: 10px;
      right: 20px;
      font-size: 28px;
      font-weight: bold;
      z-index: 5;
    }

    .modal-close:hover,
    .modal-close:focus {
      color: black;
      text-decoration: none;
      cursor: pointer;
    }

    #videoContent {
      height: inherit;
      position: relative;
      z-index: 1;
      top: 10%;
    }

    #videoContent video {
      width: 100%;
      height: 100%;
      max-height: 100%;
    }

    #searchBar {
      margin-bottom: 20px;
      padding: 10px;
      width: 100%;
      box-sizing: border-box;
      font-size: 16px;
    }

    #totalCount {
      margin-bottom: 10px;
      font-size: 18px;
      color: white;
    }

    #sortControls {
      margin-bottom: 20px;
      color: white;
    }

    #sortSelect {
      padding: 5px;
      font-size: 16px;
    }

    @media (max-width: 960px) {
      .video-wrapper {
        width: calc(100% / 3 - 20px);
      }
    }

    .star-rating {
      font-size: 24px;
      cursor: pointer;
      color: #ddd;
    }

    .star-rating .star {
      display: inline-block;
      margin-right: 5px;
    }

    .star-rating .star.active {
      color: gold;
    }

    .rating-container {
      margin-top: 10px;
      margin-bottom: 15px;
    }

    .save-rating {
      padding: 5px 10px;
      background-color: #0050c88a;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-left: 10px;
    }

    .save-rating:hover {
      background-color: #0050c8c0;
    }

    .rating-saved {
      color: green;
      margin-left: 10px;
      display: none;
    }

    .srt-indicator {
      position: absolute;
      top: 5px;
      left: 5px;
      background-color: rgba(0, 0, 0, 0.5);
      color: white;
      padding: 2px 5px;
      border-radius: 3px;
      font-size: 10px;
      font-weight: bold;
      z-index: 4;
    }

  </style>
</head>

<body>
  <?php
    $dbFile = './videodb.json';
    $ratingsFile = './ratings.json';
    $coversDir = './covers';
    $trailerDir = './previews';

    $groupedVideos = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
    $ratings = file_exists($ratingsFile) ? json_decode(file_get_contents($ratingsFile), true) : [];

    $allGenres = [];
    $allActors = [];
    $allStudios = [];

    foreach ($groupedVideos as $baseName => &$data) {
        if (isset($ratings[$baseName])) {
            $data['metadata']['rating'] = $ratings[$baseName];
            $data['metadata']['userrating'] = $ratings[$baseName];
        }
        $metadata = $data['metadata'] ?? [];
        if (isset($metadata['genres']) && is_array($metadata['genres'])) {
            foreach ($metadata['genres'] as $genre) {
                $genreTrimmed = trim($genre);
                if (!empty($genreTrimmed) && !in_array($genreTrimmed, $allGenres)) {
                    $allGenres[] = $genreTrimmed;
                }
            }
        }

        if (isset($metadata['actors']) && is_array($metadata['actors'])) {
            foreach ($metadata['actors'] as $actor) {
                $actorTrimmed = trim($actor);
                if (!empty($actorTrimmed) && !in_array($actorTrimmed, $allActors)) {
                    $allActors[] = $actorTrimmed;
                }
            }
        }

        if (isset($metadata['studio'])) {
            $studioTrimmed = trim($metadata['studio']);
            if (!empty($studioTrimmed) && !in_array($studioTrimmed, $allStudios)) {
                $allStudios[] = $studioTrimmed;
            }
        }
    }
    sort($allGenres);
    sort($allActors);
    sort($allStudios);
  ?>

<div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center;">
  <input type="text" id="searchBar" placeholder="Search videos..." style="margin-bottom: 0px; flex-grow: 1;">
  <button id="clearSearch" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background-color: #131023ff; color: white; border: none; border-radius: 4px; display: none; font-weight: bold; white-space: nowrap;">Clear Search</button>
</div>

<details style="margin-bottom: 10px; color: white;">
  <summary style="cursor: pointer; margin-bottom: 5px; font-weight: bold; user-select: none; font-size: 18px; padding: 5px 0;">Genre</summary>
  <div style="display: flex; flex-wrap: wrap; gap: 10px; max-height: 180px; overflow-y: auto; padding: 5px 0; margin-bottom: 10px;">
    <?php foreach ($allGenres as $item): ?>
      <span class="filter-tag" style="background-color: #333; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; cursor: pointer; user-select: none; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#555'" onmouseout="this.style.backgroundColor='#333'"><?php echo htmlspecialchars($item); ?></span>
    <?php endforeach; ?>
  </div>
</details>

<details style="margin-bottom: 10px; color: white;">
  <summary style="cursor: pointer; margin-bottom: 5px; font-weight: bold; user-select: none; font-size: 18px; padding: 5px 0;">Actor</summary>
  <div style="display: flex; flex-wrap: wrap; gap: 10px; max-height: 180px; overflow-y: auto; padding: 5px 0; margin-bottom: 10px;">
    <?php foreach ($allActors as $item): ?>
      <span class="filter-tag" style="background-color: #333; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; cursor: pointer; user-select: none; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#555'" onmouseout="this.style.backgroundColor='#333'"><?php echo htmlspecialchars($item); ?></span>
    <?php endforeach; ?>
  </div>
</details>

<details style="margin-bottom: 20px; color: white;">
  <summary style="cursor: pointer; margin-bottom: 5px; font-weight: bold; user-select: none; font-size: 18px; padding: 5px 0;">Studio</summary>
  <div style="display: flex; flex-wrap: wrap; gap: 10px; max-height: 180px; overflow-y: auto; padding: 5px 0; margin-bottom: 10px;">
    <?php foreach ($allStudios as $item): ?>
      <span class="filter-tag" style="background-color: #333; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; cursor: pointer; user-select: none; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#555'" onmouseout="this.style.backgroundColor='#333'"><?php echo htmlspecialchars($item); ?></span>
    <?php endforeach; ?>
  </div>
</details>

<div id="totalCount">Total Videos: <?php echo count($groupedVideos); ?></div>

<div id="sortControls">
  <label for="sortSelect">Sort by:</label>
  <select id="sortSelect">
    <option value="premiered" selected>Premiered</option>
    <option value="default">ID</option>
    <option value="random">Random</option>
    <option value="dateadded">Added Date</option>
    <option value="rating">Rating</option>
    <option value="actor">Actor</option>
    <option value="genre">Genre</option>
    <option value="studio">Studio</option>
    <option value="title">Title</option>
    <option value="director">Director</option>
    <option value="runtime">Runtime</option>
    <option value="subtitles">Subtitles</option>
  </select>
</div>

  <div class="video-container" id="videoContainer">

    <?php
    uasort($groupedVideos, function($a, $b) {
        $dateA = isset($a['metadata']['dateadded']) ? strtotime($a['metadata']['dateadded']) : 0;
        $dateB = isset($b['metadata']['dateadded']) ? strtotime($b['metadata']['dateadded']) : 0;
        return $dateB <=> $dateA;
    });
    ?>

    <?php foreach ($groupedVideos as $baseName => $data): ?><?php

        $coverImagePath = $data['cover'] ?? null;
        if (!$coverImagePath) {
            $dir = rtrim($data['assetDir'] ?? '', '/\\');
            $fullPathBase = __DIR__ . '/' . ltrim($dir, './') . '/' . $baseName;

            $foundExt = null;
            if (file_exists($fullPathBase . '.webp')) {
                $foundExt = '.webp';
            } elseif (file_exists($fullPathBase . '.jpg')) {
                $foundExt = '.jpg';
            } elseif (file_exists($fullPathBase . '.jpeg')) {
                $foundExt = '.jpeg';
            } elseif (file_exists($fullPathBase . '.png')) {
                $foundExt = '.png';
            }
            if ($foundExt) {
                $coverImagePath = $data['assetDir'] . '/' . $baseName . $foundExt;
            }
        }

        $trailerPath = $trailerDir . '/' . $baseName . ".mp4";
        $mainVideos = $data['videos'];
        $firstVideoPath = !empty($mainVideos) ? $mainVideos[0]['dir'] . '/' . $mainVideos[0]['file'] : '';
    ?>

    <div class="video-wrapper" data-filename="<?php echo htmlspecialchars($baseName); ?>"
         data-metadata="<?php echo htmlspecialchars(json_encode($data['metadata'])); ?>"
         data-video-path="<?php echo htmlspecialchars($firstVideoPath); ?>"
         data-trailer-path="<?php echo file_exists($trailerPath) ? htmlspecialchars($trailerPath) : ''; ?>"
         data-video-parts="<?php echo htmlspecialchars(json_encode($mainVideos)); ?>"
         onclick="showMetadata('<?php echo htmlspecialchars($baseName); ?>')"
         style="cursor: pointer;">

        <?php if (file_exists($coverImagePath)): ?>
            <div class="thumbnail-container">
                <img src="<?php echo htmlspecialchars($coverImagePath); ?>" alt="Cover Image" class="cover-image" loading="lazy">
                <?php if (file_exists($trailerPath)): ?>
                    <video src="<?php echo $trailerPath; ?>" class="trailer-preview" preload="none" loop muted></video>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <img src="covers/placeholder.jpg" alt="cover-image" class="cover-image">
        <?php endif; ?>

        <div class="video-details">
            <?php if (isset($data['metadata']['has_srt']) && $data['metadata']['has_srt']): ?>
                <span class="srt-indicator">CC</span>
            <?php endif; ?>

            <span class="video-filename"><?php echo htmlspecialchars($baseName); ?></span>
        </div>
    </div>

  <?php endforeach; ?>
  </div>

  <div id="metadataModal" class="modal">
    <div class="modal-content">
      <span class="modal-close" onclick="closeModal()">&times;</span>
      <div style="position: absolute; top: 2px; left: 2px; display: flex; gap: 2px;">
        <button type="button" onclick="navigateModal(-1)" title="Previous (←)">&#8592;</button>
        <button type="button" onclick="navigateModal(1)" title="Next (→)">&#8594;</button>
      </div>
      <div id="metadataContent"></div>
    </div>
  </div>

  <script>
    function showMetadata(baseName) {
      const metadataModal = document.getElementById('metadataModal');
      const metadataContent = document.getElementById('metadataContent');
      const videoWrapper = document.querySelector(`[data-filename="${baseName}"]`);
      const metadata = JSON.parse(videoWrapper.getAttribute('data-metadata'));
      const videoPath = videoWrapper.getAttribute('data-video-path');
      const trailerPath = videoWrapper.getAttribute('data-trailer-path');
      const videoPartsAttr = videoWrapper.getAttribute('data-video-parts');
      const videoParts = videoPartsAttr ? JSON.parse(videoPartsAttr) : [];

      let starsHtml = '<div class="rating-container"><strong>Rate this:</strong> <div class="star-rating" data-video="' + baseName + '">';
      
      for (let i = 1; i <= 5; i++) {
        const activeClass = (metadata.rating && parseInt(metadata.rating) >= i) ? 'active' : '';
        starsHtml += '<span class="star ' + activeClass + '" data-value="' + i + '">★</span>';
      }
      starsHtml += '<button class="save-rating">Save</button><span class="rating-saved">Saved★</span></div></div>';

      let videoLinkHtml = '';
      if (videoParts.length > 0) {
        let partsHtml = '<div style="margin-top: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 5px; flex-wrap: wrap;"><strong>Play Parts: </strong>';
        videoParts.forEach((part, index) => {
            const partUrl = part.dir + '/' + part.file;
            partsHtml += `<a href="${partUrl}" target="_blank" class="play-button" style="text-decoration: none;">${index + 1}</a>`;
        });
        partsHtml += '</div>';
        videoLinkHtml += partsHtml;
      }

      if (videoPath) {
        let playlistUrls = [];
        if (videoParts.length > 0) {
            videoParts.forEach((part) => {
                const partPath = part.dir + '/' + part.file;
                const cleanPartSrc = (partPath.startsWith('./') ? partPath.substring(2) : partPath).replace(/\\/g, '/');
                playlistUrls.push(new URL(cleanPartSrc, window.location.href).href);
            });
        } else {
            const cleanVideoSrc = (videoPath.startsWith('./') ? videoPath.substring(2) : videoPath).replace(/\\/g, '/');
            playlistUrls.push(new URL(cleanVideoSrc, window.location.href).href);
        }

        const playlistParam = playlistUrls.join(',');
        const cleanVideoSrc = (videoPath.startsWith('./') ? videoPath.substring(2) : videoPath).replace(/\\/g, '/');
        const fullVideoUrl = new URL(cleanVideoSrc, window.location.href).href;
        const videoTitle = metadata.title || baseName;
        const coverImage = videoWrapper.querySelector('.cover-image').src;

        videoLinkHtml += `<p><strong>Direct Link:</strong> <a href="${fullVideoUrl}" target="_blank">${fullVideoUrl}</a></p>`;
        // videoLinkHtml += `<p><a href="VR180/index.html?playlist=${playlistParam}&title=${encodeURIComponent(videoTitle)}&poster=${encodeURIComponent(coverImage)}" target="_blank" class="play-button" style="display: inline-block; text-decoration: none; padding: 10px 20px; background-color: #091b2fff; border-radius: 5px; font-weight: bold;">Play in VR180 Player</a></p>`;
      }

      let trailerHtml = '';
      if (trailerPath) {
        trailerHtml = `
          <div style="flex: 1 1 30%; min-width: 40%; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; margin-top: 0px; margin-right: 0px;">
            <video src="${trailerPath}" autoplay loop muted style="width: 40%; border-radius: 8px; box-shadow: 0 8px 16px rgba(0,0,0,0.2);"></video>
          </div>
        `;
      } else {
        const coverEl = videoWrapper.querySelector('.cover-image');
        if (coverEl && !coverEl.src.includes('placeholder.jpg')) {
          trailerHtml = `
          <div style="flex: 1 1 30%; min-width: 40%; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; margin-top: 10%; margin-right: 0px;">
            <img src="${coverEl.src}" alt="Cover Image" style="width: 100%; border-radius: 8px; box-shadow: 0 8px 16px rgba(0,0,0,0.2);">
          </div>
        `;
        }
      }

      metadataContent.innerHTML = `
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 20px;">
          <div style="flex: 1 1 30%; min-width: 300px;">
            <h3 style="margin-top: 5%;">${metadata.title || ''}</h3>
            ${starsHtml}
            <p><strong>Director:</strong> ${metadata.director || ''}</p>
            <p><strong>Studio:</strong> ${metadata.studio || ''}</p>
            <p><strong>Premiered:</strong> ${metadata.premiered || ''}</p>
            <p><strong>Added:</strong> ${metadata.dateadded || ''}</p>
            <p><strong>Plot:</strong> ${metadata.plot || ''}</p>
            <p><strong>Runtime:</strong> ${metadata.runtime || ''} minutes</p>
            <p><strong>Rating:</strong> ${metadata.rating || ''}/5</p>
            <p><strong>Genres:</strong> ${metadata.genres ? metadata.genres.map(genre => `<span class="clickable-genre">${genre}</span>`).join(', ') : ''}</p>
            <p><strong>Actors:</strong> ${metadata.actors ? metadata.actors.map(actor => `<span class="clickable-actor">${actor}</span>`).join(', ') : ''}</p>
            ${videoLinkHtml}
          </div>
          ${trailerHtml}
        </div>
    `;

    const stars = metadataContent.querySelectorAll('.star');
    stars.forEach(star => {
      star.addEventListener('click', function() {
        const value = parseInt(this.getAttribute('data-value'));

        stars.forEach(s => {
          const starValue = parseInt(s.getAttribute('data-value'));
          if (starValue <= value) {
            s.classList.add('active');
          } else {
            s.classList.remove('active');
          }
        });
      });
    });

    const saveButton = metadataContent.querySelector('.save-rating');
    saveButton.addEventListener('click', function() {
      const videoName = this.parentNode.getAttribute('data-video');
      let rating = 0;

      const activeStars = this.parentNode.querySelectorAll('.star.active');
      rating = activeStars.length;

      fetch('save_rating.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'video=' + encodeURIComponent(videoName) + '&rating=' + rating
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {

          const savedMsg = this.parentNode.querySelector('.rating-saved');
          savedMsg.style.display = 'inline';
          setTimeout(() => { savedMsg.style.display = 'none'; }, 5000);
          const videoWrapper = document.querySelector(`[data-filename="${videoName}"]`);
          if (videoWrapper) {
            const metadata = JSON.parse(videoWrapper.getAttribute('data-metadata'));
            metadata.rating = rating.toString();
            videoWrapper.setAttribute('data-metadata', JSON.stringify(metadata));
            const ratingText = metadataContent.querySelector('p:nth-of-type(6)');
            ratingText.innerHTML = `<strong>Rating:</strong> ${rating}/5`;
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
    });

    const metadataFields = metadataContent.querySelectorAll('p strong');
    metadataFields.forEach(field => {
        field.style.cursor = 'pointer';
        field.addEventListener('click', () => {
            const searchTerm = field.nextSibling.textContent.trim();
            document.getElementById('searchBar').value = searchTerm;
            closeModal();
            document.getElementById('searchBar').dispatchEvent(new Event('input'));
        });
    });

    const genreFields = metadataContent.querySelectorAll('.clickable-genre');
    genreFields.forEach(genre => {
        genre.style.cursor = 'pointer';
        genre.addEventListener('click', () => {
            const searchTerm = genre.textContent.trim();
            document.getElementById('searchBar').value = searchTerm;
            closeModal();
            document.getElementById('searchBar').dispatchEvent(new Event('input'));
        });
    });

    const actorFields = metadataContent.querySelectorAll('.clickable-actor');
    actorFields.forEach(actor => {
        actor.style.cursor = 'pointer';
        actor.addEventListener('click', () => {
            const searchTerm = actor.textContent.trim();
            document.getElementById('searchBar').value = searchTerm;
            closeModal();
            document.getElementById('searchBar').dispatchEvent(new Event('input'));
        });
    });

    metadataModal.dataset.current = baseName;
    metadataModal.style.display = 'block';
    }

    function closeModal() {
      document.getElementById('metadataModal').style.display = 'none';
    }

    function navigateModal(step) {
      const modal = document.getElementById('metadataModal');
      const items = Array.from(document.querySelectorAll('.video-wrapper'))
        .filter(el => el.style.display !== 'none');
      const idx = items.findIndex(el => el.getAttribute('data-filename') === modal.dataset.current);
      if (idx === -1 || items.length < 2) return;
      const next = items[(idx + step + items.length) % items.length];
      showMetadata(next.getAttribute('data-filename'));
    }

    document.addEventListener('keydown', function(e) {
      const modal = document.getElementById('metadataModal');
      if (modal.style.display !== 'block') return;
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
      if (e.key === 'ArrowRight') { e.preventDefault(); navigateModal(1); }
      else if (e.key === 'ArrowLeft') { e.preventDefault(); navigateModal(-1); }
      else if (e.key === 'Escape') { closeModal(); }
    });

    window.onclick = function(event) {
      const metadataModal = document.getElementById('metadataModal');
      if (event.target == metadataModal) {
        metadataModal.style.display = 'none';
      }
    }
  </script>

  <script>

  function openAllParts(baseName) {
  const element = document.querySelector(`[data-filename="${baseName}"]`);
  const rawParts = element ? element.getAttribute('data-video-parts') : '[]';
  const videoParts = JSON.parse(rawParts || '[]');

  videoParts.forEach(part => {
    const partPath = (part.dir + '/' + part.file).replace(/\\/g, '/');
    window.open(partPath, '_blank');
  });
}
  </script>

  <script>

    document.addEventListener('DOMContentLoaded', () => {
      const searchBar = document.getElementById('searchBar');
      const clearSearch = document.getElementById('clearSearch');
      const filterTags = document.querySelectorAll('.filter-tag');

      filterTags.forEach(tag => {
        tag.addEventListener('click', () => {
          searchBar.value = tag.textContent.trim();
          searchBar.dispatchEvent(new Event('input'));
        });
      });

      const shuffleSearch = document.getElementById('shuffleSearch');
      if (shuffleSearch) {
        shuffleSearch.addEventListener('click', () => {
          const sortSelect = document.getElementById('sortSelect');
          if (sortSelect) {
            sortSelect.value = 'dateadded';
            sortSelect.dispatchEvent(new Event('change'));
          }
        });
      }

      if (clearSearch) {
        clearSearch.addEventListener('click', () => {
          searchBar.value = '';
          searchBar.dispatchEvent(new Event('input'));

          const sortSelect = document.getElementById('sortSelect');
          if (sortSelect) {
            sortSelect.value = 'dateadded';
            sortSelect.dispatchEvent(new Event('change'));
          }
        });
      }
    });

    document.getElementById('searchBar').addEventListener('input', function() {
      const clearBtn = document.getElementById('clearSearch');
      if (clearBtn) {
        clearBtn.style.display = this.value.trim() !== '' ? 'block' : 'none';
      }
      const searchQuery = this.value.toLowerCase();
      const videos = document.querySelectorAll('.video-wrapper');

      let visibleCount = 0;

      videos.forEach(function(video) {
        const filename = video.getAttribute('data-filename').toLowerCase();
        const metadataAttr = video.getAttribute('data-metadata');
        const metadata = metadataAttr ? JSON.parse(video.getAttribute('data-metadata')) : {};

        let match = false;
        const ratingMatch = searchQuery.match(/^(\d+)\/(\d+)$/);

        if (ratingMatch && metadata.rating) {
          const searchRating = parseInt(ratingMatch[1]);
          const metadataRating = parseInt(metadata.rating);

          if (metadataRating === searchRating) {
            match = true;
          }
        } else {
          if (filename.includes(searchQuery)) {
            match = true;
          } else {
            for (const key in metadata) {
              if (Array.isArray(metadata[key])) {
                metadata[key].forEach(item => {
                  if (typeof item === 'string' && item.toLowerCase().includes(searchQuery)) {
                    match = true;
                  }
                });
              } else if (typeof metadata[key] === 'string') {
                if (metadata[key].toLowerCase().includes(searchQuery)) {
                  match = true;
                }
              }
            }
          }
        }

        if (match) {
          video.style.display = 'block';
          visibleCount++;
        } else {
          video.style.display = 'none';
        }
      });

      const totalCount = document.getElementById('totalCount');
      if (totalCount) {
        if (searchQuery.trim() === '') {
          totalCount.innerHTML = `Total Videos: ${videos.length}`;
        } else {
          totalCount.innerHTML = `Showing ${visibleCount} Videos matching "${this.value}"`;
        }
      }
    });
  </script>

  <script>
  const videoWrappers = document.querySelectorAll('.video-wrapper');

  videoWrappers.forEach(wrapper => {
    const trailerPreview = wrapper.querySelector('.trailer-preview');
    const playTrailerButton = wrapper.querySelector('.play-trailer-button');

    if (trailerPreview) {
        wrapper.addEventListener('mouseenter', () => {
            trailerPreview.play();
            playTrailerButton.style.display = 'none'; 
        });

        wrapper.addEventListener('mouseleave', () => {
            trailerPreview.pause();
            playTrailerButton.style.display = 'none'; 
        });
    }
  });
  </script>

  <script>

    document.getElementById('sortSelect').addEventListener('change', function() {
      const sortBy = this.value;
      const container = document.getElementById('videoContainer');
      const videos = Array.from(container.querySelectorAll('.video-wrapper'));

      videos.sort((a, b) => {
        const metaA = JSON.parse(a.getAttribute('data-metadata'));
        const metaB = JSON.parse(b.getAttribute('data-metadata'));

        switch (sortBy) {
          case 'studio':
            const studioA = metaA.studio ? metaA.studio.toLowerCase() : 'zzz'; 
            const studioB = metaB.studio ? metaB.studio.toLowerCase() : 'zzz';
            return studioA.localeCompare(studioB); 
            
          case 'label':
            const labelA = metaA.label ? metaA.label.toLowerCase() : 'zzz'; 
            const labelB = metaB.label ? metaB.label.toLowerCase() : 'zzz';
            return labelA.localeCompare(labelB); 

          case 'premiered':
            const premA = metaA.premiered ? new Date(metaA.premiered).getTime() : 0;
            const premB = metaB.premiered ? new Date(metaB.premiered).getTime() : 0;
            return premB - premA; 
      
          case 'dateadded':
              const timeA = metaA.dateadded ? new Date(metaA.dateadded).getTime() : 0;
              const timeB = metaB.dateadded ? new Date(metaB.dateadded).getTime() : 0;
              return timeB - timeA; 

          case 'rating':
            return (parseInt(metaB.rating) || 0) - (parseInt(metaA.rating) || 0);
            
          case 'actor':
            const actorA = metaA.actors && metaA.actors.length > 0 ? metaA.actors[0].toLowerCase() : 'zzz'; 
            const actorB = metaB.actors && metaB.actors.length > 0 ? metaB.actors[0].toLowerCase() : 'zzz';
            return actorA.localeCompare(actorB); 

          case 'genre':
            const genresA = metaA.genres && metaA.genres.length > 0 ? metaA.genres.slice().sort().join(',').toLowerCase() : 'zzz'; 
            const genresB = metaB.genres && metaB.genres.length > 0 ? metaB.genres.slice().sort().join(',').toLowerCase() : 'zzz';
            return genresA.localeCompare(genresB); 
       
          case 'title':
            const titleA = metaA.title ? metaA.title.toLowerCase() : 'zzz';
            const titleB = metaB.title ? metaB.title.toLowerCase() : 'zzz';
            return titleA.localeCompare(titleB); 

          case 'director':
            const directorA = metaA.director ? metaA.director.toLowerCase() : 'zzz';
            const directorB = metaB.director ? metaB.director.toLowerCase() : 'zzz';
            return directorA.localeCompare(directorB); 

          case 'runtime':
            const runtimeA = parseInt(metaA.runtime) || 0;
            const runtimeB = parseInt(metaB.runtime) || 0;
            return runtimeB - runtimeA; 

          case 'subtitles':
            const hasSrtA = metaA.has_srt ? 1 : 0;
            const hasSrtB = metaB.has_srt ? 1 : 0;
            return hasSrtB - hasSrtA; 

          case 'random':
            return Math.random() - 0.5; 

          case 'default':
           const filenameA = a.getAttribute('data-filename').toLowerCase();
           const filenameB = b.getAttribute('data-filename').toLowerCase();
           return filenameA.localeCompare(filenameB);
        }
      });

      videos.forEach((video, index) => {
        video.style.order = index;
      });
    });
  </script>

</body>
</html>
