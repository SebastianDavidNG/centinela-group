/**
 * Modal de video (YouTube, Vimeo, .mp4) – ref. WiseGuard
 * Abre el modal y muestra iframe o <video> según la URL.
 */
(function () {
  'use strict';

  var modal = document.getElementById('centinela-video-modal');
  var player = document.getElementById('centinela-video-modal-player');
  var closeBtn = document.getElementById('centinela-video-modal-close');
  var backdrop = document.getElementById('centinela-video-modal-backdrop');

  function getVideoType(url) {
    if (!url || typeof url !== 'string') return null;
    var u = url.trim();
    if (u.indexOf('youtube.com') !== -1 || u.indexOf('youtu.be') !== -1) return 'youtube';
    if (u.indexOf('vimeo.com') !== -1) return 'vimeo';
    if (u.toLowerCase().endsWith('.mp4')) return 'mp4';
    return null;
  }

  function getEmbedUrl(url, type) {
    if (type === 'youtube') {
      var id = null;
      var m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
      if (m) id = m[1];
      return id ? 'https://www.youtube.com/embed/' + id + '?autoplay=1' : null;
    }
    if (type === 'vimeo') {
      var v = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
      return v ? 'https://player.vimeo.com/video/' + v[1] + '?autoplay=1' : null;
    }
    if (type === 'mp4') return url;
    return null;
  }

  function openModal(videoUrl) {
    var type = getVideoType(videoUrl);
    var embedUrl = type ? getEmbedUrl(videoUrl, type) : null;
    if (!embedUrl) return;

    player.innerHTML = '';
    if (type === 'youtube' || type === 'vimeo') {
      var iframe = document.createElement('iframe');
      iframe.src = embedUrl;
      iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
      iframe.setAttribute('allowfullscreen', '');
      player.appendChild(iframe);
    } else if (type === 'mp4') {
      var video = document.createElement('video');
      video.src = embedUrl;
      video.controls = true;
      video.autoplay = true;
      player.appendChild(video);
    }

    modal.setAttribute('aria-hidden', 'false');
    modal.removeAttribute('hidden');
    closeBtn.focus();
  }

  function closeModal() {
    modal.setAttribute('aria-hidden', 'true');
    modal.setAttribute('hidden', '');
    player.innerHTML = '';
  }

  function handleClose() {
    closeModal();
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-video-url]');
    if (!trigger) return;
    var url = trigger.getAttribute('data-video-url');
    if (url) {
      e.preventDefault();
      openModal(url);
    }
  });

  if (closeBtn) closeBtn.addEventListener('click', handleClose);
  if (backdrop) backdrop.addEventListener('click', handleClose);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
      handleClose();
    }
  });
})();
