/**
 * Lightbox de imagen: clic en imagen principal (quickview o detalle producto) abre vista grande.
 * Navegación entre imágenes de la galería. Estilo CozyCorner.
 */
(function () {
  'use strict';

  var currentImages = [];
  var currentIndex = 0;
  var listenersBound = false;
  var fullScreenSupported = false;

  function getEl(id) {
    return document.getElementById(id);
  }

  function getFullscreenElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
  }

  function isFullscreen() {
    return !!getFullscreenElement();
  }

  function requestFullscreen(el) {
    if (!el) return Promise.reject(new Error('No element'));
    if (el.requestFullscreen) return el.requestFullscreen();
    if (el.webkitRequestFullscreen) return el.webkitRequestFullscreen();
    return Promise.reject(new Error('Fullscreen not supported'));
  }

  function exitFullscreen() {
    if (document.exitFullscreen) return document.exitFullscreen();
    if (document.webkitExitFullscreen) return document.webkitExitFullscreen();
    return Promise.resolve();
  }

  function updateFullscreenButton() {
    var btn = getEl('centinela-lightbox-fullscreen');
    if (!btn) return;
    if (!fullScreenSupported) {
      btn.style.display = 'none';
      return;
    }
    var label = isFullscreen() ? 'Salir de pantalla completa' : 'Pantalla completa';
    btn.setAttribute('aria-label', label);
    btn.setAttribute('title', label);
    btn.classList.toggle('is-active', isFullscreen());
  }

  function toggleFullscreen() {
    if (!fullScreenSupported) return;
    if (isFullscreen()) {
      exitFullscreen().catch(function () {});
      return;
    }
    var box = getEl('centinela-image-lightbox');
    requestFullscreen(box || document.documentElement).catch(function () {});
  }

  function bindListeners() {
    if (listenersBound) return;
    var lightbox = getEl('centinela-image-lightbox');
    if (!lightbox) return;
    listenersBound = true;
    var prevBtn = getEl('centinela-lightbox-prev');
    var nextBtn = getEl('centinela-lightbox-next');
    var fullscreenBtn = getEl('centinela-lightbox-fullscreen');
    fullScreenSupported = !!(lightbox.requestFullscreen || lightbox.webkitRequestFullscreen || document.exitFullscreen || document.webkitExitFullscreen);
    updateFullscreenButton();
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-close-lightbox]')) {
        e.preventDefault();
        close();
      }
    });
    if (prevBtn) prevBtn.addEventListener('click', function (e) { e.preventDefault(); goPrev(); });
    if (nextBtn) nextBtn.addEventListener('click', function (e) { e.preventDefault(); goNext(); });
    if (fullscreenBtn) fullscreenBtn.addEventListener('click', function (e) { e.preventDefault(); toggleFullscreen(); });
    document.addEventListener('fullscreenchange', updateFullscreenButton);
    document.addEventListener('webkitfullscreenchange', updateFullscreenButton);
    document.addEventListener('keydown', function (e) {
      if (!lightbox || !lightbox.classList.contains('centinela-lightbox--open')) return;
      if (e.key === 'Escape') {
        close();
        return;
      }
      if (e.key === 'ArrowLeft') {
        goPrev();
        e.preventDefault();
      }
      if (e.key === 'ArrowRight') {
        goNext();
        e.preventDefault();
      }
    });
  }

  function showImage(index, isTransition) {
    var img = getEl('centinela-lightbox-img');
    var prevBtn = getEl('centinela-lightbox-prev');
    var nextBtn = getEl('centinela-lightbox-next');
    if (!img) return;
    var i = Math.max(0, Math.min(index, currentImages.length - 1));
    currentIndex = i;
    var src = currentImages[i] || '';
    img.style.display = src ? '' : 'none';
    if (prevBtn) prevBtn.style.display = currentImages.length > 1 ? '' : 'none';
    if (nextBtn) nextBtn.style.display = currentImages.length > 1 ? '' : 'none';

    if (isTransition && src) {
      img.classList.add('centinela-lightbox__img--changing');
      img.alt = '';
      var done = false;
      var onReady = function () {
        if (done) return;
        done = true;
        img.classList.remove('centinela-lightbox__img--changing');
        img.removeEventListener('load', onReady);
      };
      img.addEventListener('load', onReady);
      img.src = src;
      setTimeout(onReady, 280);
    } else {
      img.classList.remove('centinela-lightbox__img--changing');
      img.src = src;
      img.alt = '';
    }
  }

  function open(images, index) {
    if (!images || !images.length) return;
    var lightbox = getEl('centinela-image-lightbox');
    var img = getEl('centinela-lightbox-img');
    if (!lightbox || !img) return;
    bindListeners();
    currentImages = images.slice();
    currentIndex = typeof index === 'number' ? Math.max(0, Math.min(index, currentImages.length - 1)) : 0;
    showImage(currentIndex, false);
    lightbox.setAttribute('aria-hidden', 'false');
    lightbox.classList.add('centinela-lightbox--open');
    document.body.style.overflow = 'hidden';
    var prevBtn = getEl('centinela-lightbox-prev');
    if (prevBtn) prevBtn.focus();
  }

  function close() {
    var lightbox = getEl('centinela-image-lightbox');
    if (lightbox) {
      lightbox.setAttribute('aria-hidden', 'true');
      lightbox.classList.remove('centinela-lightbox--open');
    }
    if (isFullscreen()) {
      exitFullscreen().catch(function () {});
    }
    document.body.style.overflow = '';
  }

  function goPrev() {
    if (currentImages.length <= 1) return;
    currentIndex = currentIndex <= 0 ? currentImages.length - 1 : currentIndex - 1;
    showImage(currentIndex, true);
  }

  function goNext() {
    if (currentImages.length <= 1) return;
    currentIndex = currentIndex >= currentImages.length - 1 ? 0 : currentIndex + 1;
    showImage(currentIndex, true);
  }

  window.centinelaOpenImageLightbox = function (images, index) {
    open(images || [], index);
  };
})();
