/**
 * Gallery Pro – Masonry y Justified layout (Centinela Group)
 * Compatible con el lightbox de Elementor (data-elementor-open-lightbox).
 * Esta galería NO usa Swiper; limpiamos preloaders inyectados para que las imágenes se vean.
 */
(function () {
  'use strict';

  /** Quitar preloaders y restaurar src en imágenes si algo las convirtió a swiper-lazy */
  function cleanupGalleryLazy(container) {
    if (!container) return;
    var grid = container.querySelector('.centinela-gallery-pro__grid');
    if (!grid) return;
    var preloaders = grid.querySelectorAll('.swiper-lazy-preloader');
    preloaders.forEach(function (el) {
      if (el.parentNode) el.parentNode.removeChild(el);
    });
    var imgs = grid.querySelectorAll('img.swiper-lazy, img[data-src]');
    imgs.forEach(function (img) {
      var src = img.getAttribute('data-src') || img.src;
      if (src) {
        img.src = src;
        img.removeAttribute('data-src');
        img.classList.remove('swiper-lazy', 'swiper-lazy-loading', 'swiper-lazy-loaded');
      }
    });
  }

  function runMasonry(container) {
    var grid = container.querySelector('.centinela-gallery-pro__grid');
    var items = container && grid ? grid.querySelectorAll('.centinela-gallery-pro__item') : [];
    var colWidth = parseInt(container.dataset.masonryWidth, 10) || 250;
    var gap = parseFloat(getComputedStyle(container).gap) || 15;
    if (!grid || !items.length) return;

    grid.style.display = 'block';
    grid.style.position = 'relative';
    var containerWidth = grid.offsetWidth;
    var numCols = Math.max(1, Math.floor((containerWidth + gap) / (colWidth + gap)));
    var colHeights = new Array(numCols);
    var i;
    for (i = 0; i < numCols; i++) colHeights[i] = 0;

    items.forEach(function (item) {
      item.style.position = 'absolute';
      item.style.width = colWidth + 'px';
      item.style.left = '';
      item.style.top = '';
      var img = item.querySelector('img');
      if (!img) return;
      var nw = img.naturalWidth || img.offsetWidth || 1;
      var nh = img.naturalHeight || img.offsetHeight || 1;
      var itemHeight = (nh / nw) * colWidth;
      var minIdx = 0;
      for (i = 1; i < numCols; i++) {
        if (colHeights[i] < colHeights[minIdx]) minIdx = i;
      }
      var left = minIdx * (colWidth + gap);
      var top = colHeights[minIdx];
      item.style.left = left + 'px';
      item.style.top = top + 'px';
      colHeights[minIdx] = top + itemHeight + gap;
    });

    var maxH = 0;
    for (i = 0; i < numCols; i++) {
      if (colHeights[i] > maxH) maxH = colHeights[i];
    }
    grid.style.height = (maxH || 0) + 'px';
  }

  function runJustified(container) {
    var grid = container.querySelector('.centinela-gallery-pro__grid');
    var items = container && grid ? grid.querySelectorAll('.centinela-gallery-pro__item') : [];
    var rowHeight = parseInt(container.dataset.justifiedHeight, 10) || 200;
    var gap = parseFloat(getComputedStyle(container).gap) || 15;
    if (!grid || !items.length) return;

    grid.style.display = 'block';
    grid.style.position = 'relative';
    var containerWidth = grid.offsetWidth;
    var totalW = containerWidth + gap;
    var currentRow = [];
    var currentRowWidth = 0;
    var rows = [];
    var naturalWidths = [];
    var naturalHeights = [];

    items.forEach(function (item, idx) {
      var img = item.querySelector('img');
      if (!img) return;
      var nw = img.naturalWidth || img.offsetWidth || 1;
      var nh = img.naturalHeight || img.offsetHeight || 1;
      naturalWidths[idx] = nw;
      naturalHeights[idx] = nh;
    });

    function flushRow(indices) {
      if (indices.length === 0) return;
      var totalNaturalW = 0;
      indices.forEach(function (i) {
        totalNaturalW += (naturalWidths[i] || 1) / (naturalHeights[i] || 1);
      });
      var targetHeight = rowHeight;
      var scale = (containerWidth - (indices.length - 1) * gap) / (totalNaturalW * targetHeight);
      var left = 0;
      indices.forEach(function (i) {
        var item = items[i];
        var nw = naturalWidths[i] || 1;
        var nh = naturalHeights[i] || 1;
        var w = (nw / nh) * targetHeight * scale;
        item.style.position = 'absolute';
        item.style.width = w + 'px';
        item.style.height = targetHeight + 'px';
        item.style.left = left + 'px';
        item.style.top = rows.length * (targetHeight + gap) + 'px';
        var img = item.querySelector('img');
        if (img) {
          img.style.width = '100%';
          img.style.height = '100%';
          img.style.objectFit = 'cover';
        }
        left += w + gap;
      });
      rows.push({ height: targetHeight, count: indices.length });
    }

    var rowIndices = [];
    var rowNaturalWidth = 0;
    for (var idx = 0; idx < items.length; idx++) {
      var nw = naturalWidths[idx] || 1;
      var nh = naturalHeights[idx] || 1;
      var itemWidth = (nw / nh) * rowHeight;
      if (rowIndices.length > 0 && rowNaturalWidth + itemWidth + gap > containerWidth) {
        flushRow(rowIndices);
        rowIndices = [];
        rowNaturalWidth = 0;
      }
      rowIndices.push(idx);
      rowNaturalWidth += itemWidth + (rowIndices.length > 1 ? gap : 0);
    }
    if (rowIndices.length > 0) flushRow(rowIndices);

    var totalHeight = 0;
    rows.forEach(function (r) {
      totalHeight += r.height + gap;
    });
    grid.style.height = (totalHeight - gap) + 'px';
  }

  function initGallery(container) {
    var layout = container.dataset.layout;
    if (layout === 'masonry') {
      runMasonry(container);
    } else if (layout === 'justified') {
      runJustified(container);
    }
  }

  function onImagesLoaded(container, callback) {
    var grid = container ? container.querySelector('.centinela-gallery-pro__grid') : null;
    if (!grid) {
      callback();
      return;
    }
    var imgs = grid.querySelectorAll('img');
    var total = imgs.length;
    if (total === 0) {
      callback();
      return;
    }
    var done = 0;
    function check() {
      done++;
      if (done >= total) callback();
    }
    imgs.forEach(function (img) {
      if (img.complete && img.naturalWidth !== undefined && img.naturalWidth > 0) {
        check();
      } else {
        img.addEventListener('load', check);
        img.addEventListener('error', check);
        // Por si ya estaban en caché y load no se dispara
        if (img.complete) check();
      }
    });
  }

  function init() {
    try {
      var allGalleries = document.querySelectorAll('.centinela-gallery-pro');
      allGalleries.forEach(function (el) {
        cleanupGalleryLazy(el);
      });
      var galleries = document.querySelectorAll('.centinela-gallery-pro[data-layout="masonry"], .centinela-gallery-pro[data-layout="justified"]');
      galleries.forEach(function (el) {
        onImagesLoaded(el, function () {
          initGallery(el);
        });
      });
    } catch (e) {
      console.warn('Centinela Gallery Pro init:', e);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Repetir limpieza por si el preloader se inyecta después (p. ej. por otro script)
  setTimeout(function () {
    document.querySelectorAll('.centinela-gallery-pro').forEach(cleanupGalleryLazy);
  }, 500);
  setTimeout(function () {
    document.querySelectorAll('.centinela-gallery-pro').forEach(cleanupGalleryLazy);
  }, 1500);

  // Elementor frontend: re-run when preview updates (solo si hooks existe)
  if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks && typeof elementorFrontend.hooks.addAction === 'function') {
    elementorFrontend.hooks.addAction('frontend/element_ready/centinela_gallery_pro.default', function ($scope) {
      var container = $scope && $scope[0] ? $scope[0] : null;
      if (!container || !container.classList.contains('centinela-gallery-pro')) return;
      cleanupGalleryLazy(container);
      onImagesLoaded(container, function () {
        initGallery(container);
      });
    });
  }
})();
