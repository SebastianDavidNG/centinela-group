/**
 * Tienda: Vista rápida (modal) y lista de deseos en tarjetas de producto.
 * Estilo CozyCorner: transición entre imágenes, galería, cantidad y agregar al carrito.
 */
(function () {
  'use strict';

  var modal = document.getElementById('centinela-quickview-modal');
  if (!modal) return;

  var restBase = (typeof wp !== 'undefined' && wp.apiFetch && wp.apiFetch.defaultOptions && wp.apiFetch.defaultOptions.root) ? wp.apiFetch.defaultOptions.root : (window.location.origin + '/wp-json');

  var currentProductId = null;
  var currentProductData = null; // { titulo, image, price } para el carrito
  var currentImagenes = [];
  var currentImagenesLarge = [];
  var zoomEl = document.getElementById('centinela-quickview-zoom');
  var mainArea = document.getElementById('centinela-quickview-main-area');
  var mainImageWrap = document.querySelector('.centinela-quickview__main-image');
  var transitionDuration = 280;

  function openModal() {
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('centinela-quickview--open');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.setAttribute('aria-hidden', 'true');
    modal.classList.remove('centinela-quickview--open');
    document.body.style.overflow = '';
  }

  // Normalizar: si API envió valor con 2 decimales como pesos (697.33 → 69733, 368194.46 → 36819446).
  function normalizePrecioCOP(val) {
    if (val === '' || val === null || val === undefined) return 0;
    var s = String(val).trim().replace(/\s*COP\s*$/i, '').replace(/[^\d.,\-]/g, '');
    if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
    var num = parseFloat(s);
    if (isNaN(num)) return num;
    var intPart = Math.floor(num);
    var decPart = num - intPart;
    if (decPart > 0 && Math.abs(Math.round(num * 100) - num * 100) < 0.001) {
      return Math.round(num * 100);
    }
    return num;
  }

  // Formato Colombia: miles con punto, decimales con coma (ej: 36.819.446 COP)
  function formatPrecioCOP(val) {
    var num = normalizePrecioCOP(val);
    if (isNaN(num)) return String(val) + ' COP';
    if (num === 0 && (val === '' || val === null || val === undefined)) return '';
    var parts = Number(num).toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    var out = parts.join(',') + ' COP';
    if (parts[1] === '00') out = parts[0] + ' COP';
    return out;
  }

  function setMainImage(index) {
    var img = document.getElementById('centinela-quickview-img');
    if (!img || !currentImagenes.length) return;
    var i = Math.max(0, Math.min(index, currentImagenes.length - 1));
    var newSrc = currentImagenes[i] || '';
    var newLarge = (currentImagenesLarge[i] || currentImagenes[i]) || '';

    if (mainImageWrap) mainImageWrap.classList.add('centinela-quickview__main-image--fade');
    var onTransition = function () {
      if (mainImageWrap) mainImageWrap.removeEventListener('transitionend', onTransition);
      img.src = newSrc;
      img.alt = (document.getElementById('centinela-quickview-title') && document.getElementById('centinela-quickview-title').textContent) || '';
      img.style.display = newSrc ? '' : 'none';
      img.setAttribute('data-index', i);
      if (zoomEl) zoomEl.style.backgroundImage = newLarge ? 'url("' + newLarge + '")' : 'none';
      if (mainImageWrap) mainImageWrap.classList.remove('centinela-quickview__main-image--fade');
    };
    if (mainImageWrap) {
      mainImageWrap.addEventListener('transitionend', onTransition);
      setTimeout(function () {
        if (mainImageWrap.classList.contains('centinela-quickview__main-image--fade')) onTransition();
      }, transitionDuration + 20);
    } else {
      img.src = newSrc;
      img.setAttribute('data-index', i);
      if (zoomEl) zoomEl.style.backgroundImage = newLarge ? 'url("' + newLarge + '")' : 'none';
    }
  }

  function fillModal(data) {
    var img = document.getElementById('centinela-quickview-img');
    var categoriaEl = document.getElementById('centinela-quickview-categoria');
    var title = document.getElementById('centinela-quickview-title');
    var price = document.getElementById('centinela-quickview-price');
    var link = document.getElementById('centinela-quickview-link');
    var modeloEl = document.getElementById('centinela-quickview-modelo');
    var marcaEl = document.getElementById('centinela-quickview-marca');
    var qtyInput = document.getElementById('centinela-quickview-qty');
    var thumbs = document.getElementById('centinela-quickview-thumbs');

    currentProductId = data.id || null;
    currentProductData = {
      titulo: data.titulo || '',
      image: (data.imagenes && data.imagenes[0]) ? data.imagenes[0] : (data.img_portada || ''),
      price: data.precio != null ? String(data.precio) : ''
    };
    if (qtyInput) { qtyInput.value = 1; qtyInput.min = 1; }

    if (categoriaEl) {
      categoriaEl.textContent = data.categoria || '';
      categoriaEl.style.display = (data.categoria && data.categoria.trim()) ? '' : 'none';
    }
    if (title) title.textContent = data.titulo || '';
    if (price) price.textContent = formatPrecioCOP(data.precio);
    if (link) {
      link.href = data.url || '#';
      link.textContent = 'Ver producto';
    }
    if (modeloEl) {
      modeloEl.textContent = (data.modelo && data.modelo.trim()) ? ('Modelo: ' + data.modelo) : '';
      modeloEl.style.display = (data.modelo && data.modelo.trim()) ? '' : 'none';
    }
    if (marcaEl) {
      marcaEl.textContent = (data.marca && data.marca.trim()) ? ('Marca: ' + data.marca) : '';
      marcaEl.style.display = (data.marca && data.marca.trim()) ? '' : 'none';
    }

    var imgs = data.imagenes && data.imagenes.length ? data.imagenes : (data.img_portada ? [data.img_portada] : []);
    var imgsLarge = (data.imagenes_large && data.imagenes_large.length) ? data.imagenes_large : imgs.slice();
    currentImagenes = imgs;
    currentImagenesLarge = imgsLarge;

    if (img) {
      img.src = imgs[0] || '';
      img.alt = data.titulo || '';
      img.style.display = imgs[0] ? '' : 'none';
      img.setAttribute('data-index', 0);
    }
    if (zoomEl) {
      zoomEl.style.backgroundImage = (imgsLarge[0] || imgs[0]) ? 'url("' + (imgsLarge[0] || imgs[0]) + '")' : 'none';
      zoomEl.classList.remove('centinela-quickview__zoom-panel--visible');
    }

    if (thumbs) {
      thumbs.innerHTML = '';
      imgs.forEach(function (src, i) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'centinela-quickview__thumb' + (i === 0 ? ' centinela-quickview__thumb--active' : '');
        btn.setAttribute('data-index', i);
        var thumbImg = document.createElement('img');
        thumbImg.src = src;
        thumbImg.alt = '';
        btn.appendChild(thumbImg);
        btn.addEventListener('click', function () {
          setMainImage(i);
          thumbs.querySelectorAll('.centinela-quickview__thumb').forEach(function (t) { t.classList.remove('centinela-quickview__thumb--active'); });
          btn.classList.add('centinela-quickview__thumb--active');
        });
        thumbs.appendChild(btn);
      });
    }
  }

  function updateZoom(ev) {
    if (!zoomEl || !mainArea || !currentImagenes.length) return;
    var rect = mainArea.getBoundingClientRect();
    var x = ev.clientX - rect.left;
    var y = ev.clientY - rect.top;
    if (x < 0 || x > rect.width || y < 0 || y > rect.height) {
      zoomEl.classList.remove('centinela-quickview__zoom-panel--visible');
      return;
    }
    var img = document.getElementById('centinela-quickview-img');
    var index = (img && img.getAttribute('data-index')) ? parseInt(img.getAttribute('data-index'), 10) : 0;
    var largeUrl = currentImagenesLarge[index] || currentImagenes[index];
    if (!largeUrl) {
      zoomEl.classList.remove('centinela-quickview__zoom-panel--visible');
      return;
    }
    var zoomRect = zoomEl.getBoundingClientRect();
    var zoomW = zoomRect.width;
    var zoomH = zoomRect.height;
    var largeSize = 1000;
    var scaleX = largeSize / Math.max(rect.width, 1);
    var scaleY = largeSize / Math.max(rect.height, 1);
    var bx = (zoomW / 2) - (x * scaleX);
    var by = (zoomH / 2) - (y * scaleY);
    zoomEl.style.backgroundImage = largeUrl ? 'url("' + largeUrl + '")' : 'none';
    zoomEl.style.backgroundPosition = bx + 'px ' + by + 'px';
    zoomEl.classList.add('centinela-quickview__zoom-panel--visible');
  }

  if (mainArea && zoomEl) {
    mainArea.addEventListener('mouseenter', function (ev) {
      if (currentImagenes.length && (currentImagenesLarge[0] || currentImagenes[0])) {
        mainArea.addEventListener('mousemove', updateZoom);
        updateZoom(ev);
      }
    });
    mainArea.addEventListener('mouseleave', function () {
      mainArea.removeEventListener('mousemove', updateZoom);
      zoomEl.classList.remove('centinela-quickview__zoom-panel--visible');
    });
  }

  if (mainArea && typeof window.centinelaOpenImageLightbox === 'function') {
    mainArea.addEventListener('click', function (e) {
      if (!currentImagenes.length) return;
      e.preventDefault();
      e.stopPropagation();
      var imgs = currentImagenesLarge.length ? currentImagenesLarge : currentImagenes;
      var imgEl = document.getElementById('centinela-quickview-img');
      var idx = (imgEl && imgEl.getAttribute('data-index')) ? parseInt(imgEl.getAttribute('data-index'), 10) : 0;
      window.centinelaOpenImageLightbox(imgs, isNaN(idx) ? 0 : idx);
    });
    mainArea.style.cursor = 'pointer';
  }

  document.getElementById('centinela-quickview-addcart') && document.getElementById('centinela-quickview-addcart').addEventListener('click', function () {
    if (!currentProductId) return;
    var qtyInput = document.getElementById('centinela-quickview-qty');
    var qty = 1;
    if (qtyInput) {
      qty = parseInt(qtyInput.value, 10) || 1;
      if (qty < 1) qty = 1;
    }
    if (typeof window.centinelaAddToCart === 'function') {
      window.centinelaAddToCart({
        id: String(currentProductId),
        qty: qty,
        title: currentProductData ? currentProductData.titulo : '',
        image: currentProductData ? currentProductData.image : '',
        price: currentProductData ? currentProductData.price : ''
      });
    }
    closeModal();
  });

  function loadQuickView(productId) {
    var url = restBase + '/centinela/v1/producto-quick-view?id=' + encodeURIComponent(productId);
    fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.error) return;
        fillModal(data);
        openModal();
      })
      .catch(function () {});
  }

  document.addEventListener('click', function (e) {
    var quickBtn = e.target.closest('.centinela-tienda__quickview-btn');
    if (quickBtn) {
      e.preventDefault();
      var id = quickBtn.getAttribute('data-product-id');
      if (id) loadQuickView(id);
      return;
    }
    if (e.target.closest('[data-close-quickview]')) {
      e.preventDefault();
      closeModal();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('centinela-quickview--open')) closeModal();
  });

  document.addEventListener('click', function (e) {
    var w = e.target.closest('.centinela-tienda__wishlist');
    if (w) {
      e.preventDefault();
      w.classList.toggle('centinela-tienda__wishlist--active');
    }
  });
})();
