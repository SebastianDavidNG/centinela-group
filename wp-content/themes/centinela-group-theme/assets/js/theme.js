/**
 * Centinela Group Theme - Script principal
 * Menú móvil, buscador overlay (estilo WiseGuard), accesibilidad
 */
(function () {
  'use strict';

  // En localhost, forzar HTTP: si cargamos por HTTPS redirigir a HTTP (evitar ERR_SSL_PROTOCOL_ERROR).
  if (typeof window !== 'undefined' && window.location && window.location.protocol === 'https:' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
    window.location.replace('http://' + window.location.host + window.location.pathname + window.location.search + window.location.hash);
    return;
  }

  // Interceptar clics en enlaces a finalizar-compra/checkout: si el href es https en localhost, ir por http.
  document.addEventListener('click', function (e) {
    var link = e.target && e.target.closest ? e.target.closest('a[href*="finalizar-compra"], a[href*="/checkout"]') : null;
    if (!link || !link.href) return;
    var isLocal = link.hostname === 'localhost' || link.hostname === '127.0.0.1';
    if (!isLocal || link.protocol !== 'https:') return;
    e.preventDefault();
    window.location.href = 'http://' + link.host + link.pathname + link.search + link.hash;
  }, true);

  var menuToggle = document.getElementById('menu-toggle');
  var mobileMenu = document.getElementById('primary-menu-mobile');

  function closeMobileMenu() {
    if (!mobileMenu || !menuToggle) return;
    menuToggle.setAttribute('aria-expanded', 'false');
    mobileMenu.classList.remove('is-open');
    mobileMenu.setAttribute('aria-hidden', 'true');
    mobileMenu.setAttribute('hidden', '');
    mobileMenu.querySelectorAll('.centinela-mobile-menu__cat-wrap.is-open, .centinela-mobile-menu .menu-item-has-children.is-open').forEach(function (el) {
      el.classList.remove('is-open');
      var btn = el.querySelector('.centinela-mobile-menu__cat-toggle, .centinela-mobile-menu__sub-toggle');
      var sub = el.querySelector('#centinela-mobile-cats, .sub-menu');
      if (btn) btn.setAttribute('aria-expanded', 'false');
      if (sub) sub.setAttribute('aria-hidden', 'true');
    });
  }

  if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', function () {
      var expanded = menuToggle.getAttribute('aria-expanded') === 'true';
      menuToggle.setAttribute('aria-expanded', !expanded);
      mobileMenu.classList.toggle('is-open', !expanded);
      mobileMenu.setAttribute('aria-hidden', expanded);
      if (expanded) {
        mobileMenu.setAttribute('hidden', '');
        mobileMenu.querySelectorAll('.centinela-mobile-menu__cat-wrap.is-open, .centinela-mobile-menu .menu-item-has-children.is-open').forEach(function (el) {
          el.classList.remove('is-open');
          var btn = el.querySelector('.centinela-mobile-menu__cat-toggle, .centinela-mobile-menu__sub-toggle');
          var sub = el.querySelector('#centinela-mobile-cats, .sub-menu');
          if (btn) btn.setAttribute('aria-expanded', 'false');
          if (sub) sub.setAttribute('aria-hidden', 'true');
        });
      } else {
        mobileMenu.removeAttribute('hidden');
      }
    });
  }

  var overlayBackdrop = document.querySelector('.centinela-mobile-overlay__backdrop');
  if (overlayBackdrop && mobileMenu && menuToggle) {
    overlayBackdrop.addEventListener('click', function () {
      closeMobileMenu();
    });
  }

  var mobileCloseBtn = document.getElementById('centinela-mobile-close');
  if (mobileCloseBtn && mobileMenu && menuToggle) {
    mobileCloseBtn.addEventListener('click', function () {
      closeMobileMenu();
    });
  }

  var mobileCta = document.getElementById('centinela-mobile-cta');
  if (mobileCta) {
    mobileCta.addEventListener('click', function (e) {
      var href = mobileCta.getAttribute('href');
      closeMobileMenu();
      if (!href) return;

      var parsedUrl = null;
      try {
        parsedUrl = new URL(href, window.location.href);
      } catch (err) {
        return;
      }

      var hasHash = !!parsedUrl.hash;
      var samePage = parsedUrl.origin === window.location.origin && parsedUrl.pathname === window.location.pathname;
      if (hasHash && samePage) {
        e.preventDefault();
        var id = parsedUrl.hash.replace(/^#/, '');
        var target = id ? document.getElementById(id) : null;
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
          window.location.hash = parsedUrl.hash;
        }
      }
    });
  }

  // Menú hamburguesa: acordeón Productos (estilo Hotlock)
  var catToggle = document.querySelector('.centinela-mobile-menu__cat-toggle');
  var catWrap = document.querySelector('.centinela-mobile-menu__cat-wrap');
  if (catToggle && catWrap) {
    var catSub = document.getElementById('centinela-mobile-cats');
    catToggle.addEventListener('click', function (e) {
      e.preventDefault();
      var isOpen = catWrap.classList.toggle('is-open');
      catToggle.setAttribute('aria-expanded', isOpen);
      if (catSub) catSub.setAttribute('aria-hidden', !isOpen);
    });
  }
  document.querySelectorAll('.centinela-mobile-menu__sub-toggle').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var li = btn.closest('.menu-item-has-children');
      if (!li) return;
      var isOpen = li.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', isOpen);
      var sub = li.querySelector(':scope > .sub-menu');
      if (sub) sub.setAttribute('aria-hidden', !isOpen);
    });
  });

  // Overlay de búsqueda (abrir/cerrar)
  var searchToggle = document.getElementById('centinela-search-toggle');
  var searchOverlay = document.getElementById('centinela-search-overlay');
  var searchClose = document.getElementById('centinela-search-close');
  var searchField = document.getElementById('centinela-search-field');

  function openSearch() {
    if (!searchOverlay) return;
    searchOverlay.setAttribute('aria-hidden', 'false');
    searchOverlay.removeAttribute('hidden');
    var t1 = document.getElementById('centinela-search-toggle');
    var t2 = document.getElementById('centinela-search-toggle-mobile');
    if (t1) t1.setAttribute('aria-expanded', 'true');
    if (t2) t2.setAttribute('aria-expanded', 'true');
    if (searchField) {
      searchField.focus();
    }
  }

  function closeSearch() {
    if (!searchOverlay) return;
    searchOverlay.setAttribute('aria-hidden', 'true');
    searchOverlay.setAttribute('hidden', '');
    var t1 = document.getElementById('centinela-search-toggle');
    var t2 = document.getElementById('centinela-search-toggle-mobile');
    if (t1) t1.setAttribute('aria-expanded', 'false');
    if (t2) t2.setAttribute('aria-expanded', 'false');
    if (searchToggle) searchToggle.focus();
  }

  if (searchOverlay) {
    if (searchToggle) searchToggle.addEventListener('click', openSearch);
    var searchToggleMobile = document.getElementById('centinela-search-toggle-mobile');
    if (searchToggleMobile) searchToggleMobile.addEventListener('click', function () {
      openSearch();
    });
  }
  if (searchClose && searchOverlay) {
    searchClose.addEventListener('click', closeSearch);
  }
  if (searchOverlay) {
    searchOverlay.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeSearch();
    });
  }

  // Desktop: efecto "typewriter" en el buscador inline (sin abrir overlay).
  (function initDesktopSearchTeaser() {
    var inputEl = document.getElementById('centinela-desktop-search-field');
    if (!inputEl) return;
    if (window.matchMedia && window.matchMedia('(max-width: 767px)').matches) return;

    var fallbackTerms = ['Reconocimiento facial', 'Hikvision', 'Control de acceso', 'CCTV', 'EPCOM'];
    var terms = fallbackTerms.slice();
    var termIndex = 0;
    var charIndex = 0;
    var deleting = false;
    var ticker = null;
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var userInteracted = false;

    function nextDelay() {
      if (deleting) return 34;
      return 58;
    }

    function tick() {
      if (userInteracted) return;
      var full = terms[termIndex] || '';
      if (!full) return;
      if (!deleting) {
        charIndex = Math.min(full.length, charIndex + 1);
        inputEl.value = full.slice(0, charIndex);
        if (charIndex >= full.length) {
          deleting = true;
          ticker = window.setTimeout(tick, 1400);
          return;
        }
      } else {
        charIndex = Math.max(0, charIndex - 1);
        inputEl.value = full.slice(0, charIndex);
        if (charIndex <= 0) {
          deleting = false;
          termIndex = (termIndex + 1) % terms.length;
          ticker = window.setTimeout(tick, 260);
          return;
        }
      }
      ticker = window.setTimeout(tick, nextDelay());
    }

    if (reducedMotion) {
      inputEl.value = terms[0];
      return;
    }
    inputEl.value = '';
    tick();

    function stopTicker() {
      if (userInteracted) return;
      userInteracted = true;
      if (ticker) window.clearTimeout(ticker);
      ticker = null;
      // Al empezar a interactuar, limpiar el texto teaser para no mezclarlo con la búsqueda real.
      inputEl.value = '';
    }

    // Cuando el usuario quiere buscar, detenemos el "teaser".
    inputEl.addEventListener('focus', stopTicker);
    inputEl.addEventListener('keydown', stopTicker);

    function applyFetchedTerms(fetchedTerms) {
      fetchedTerms = (Array.isArray(fetchedTerms) ? fetchedTerms : [])
        .map(function (t) { return (t || '').trim(); })
        .filter(function (t) { return t.length >= 3 && t.length <= 50; });
      if (!fetchedTerms.length) return;
      terms = fetchedTerms;
      termIndex = 0;
      charIndex = 0;
      deleting = false;
      if (!userInteracted) {
        inputEl.value = '';
        if (ticker) window.clearTimeout(ticker);
        tick();
      }
    }

    // Cache de sesión para evitar request en cada navegación.
    try {
      var cachedPrompts = window.sessionStorage.getItem('centinelaSearchPromptsV1');
      if (cachedPrompts) {
        applyFetchedTerms(JSON.parse(cachedPrompts));
      }
    } catch (e) {}

    var promptsUrl = (typeof centinelaTheme !== 'undefined' && centinelaTheme.searchPromptsUrl)
      ? centinelaTheme.searchPromptsUrl
      : (window.location.origin + '/wp-json/centinela/v1/search-prompts');
    var fetchPrompts = function () {
      fetch(promptsUrl + (promptsUrl.indexOf('?') !== -1 ? '&' : '?') + 'limit=10')
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var fetchedTerms = data && Array.isArray(data.terms) ? data.terms : [];
          applyFetchedTerms(fetchedTerms);
          try {
            if (fetchedTerms && fetchedTerms.length) {
              window.sessionStorage.setItem('centinelaSearchPromptsV1', JSON.stringify(fetchedTerms));
            }
          } catch (e) {}
        })
        .catch(function () {});
    };
    if ('requestIdleCallback' in window) {
      window.requestIdleCallback(fetchPrompts, { timeout: 1200 });
    } else {
      window.setTimeout(fetchPrompts, 350);
    }
  })();

  // Búsqueda en vivo (sugerencias desde REST API: contenido + productos Syscom)
  function initLiveSearch(inputEl, suggestionsEl) {
    if (!inputEl || !suggestionsEl) return;

    var searchSuggestionsTimer = null;
    var searchAbortController = null;
    var searchResultsCache = {};
    var searchResultsCacheOrder = [];
    var searchCacheMaxEntries = 20;
    var searchLoadingLabel = 'Buscando…';
    var syscomNoImage = 'https://ftp3.syscom.mx/usuarios/fotos/imagen_no_disponible.jpg';
    var isDesktopInline = inputEl.id === 'centinela-desktop-search-field';
    var isMobileInline = inputEl.id === 'centinela-mobile-search-field';
    var isInlineSearch = isDesktopInline || isMobileInline;
    var desktopSuggestionsPortaled = false;
    /** Evita reabrir el panel si la respuesta REST llega después de cerrar con clic fuera. */
    var userDismissedSuggestions = false;
    var desktopPositionRaf = null;
    var desktopScrollRepositionBound = false;

    function ensureDesktopSuggestionsPortal() {
      if (!isDesktopInline || desktopSuggestionsPortaled) return;
      if (!window.matchMedia || !window.matchMedia('(min-width: 1025px)').matches) return;
      if (suggestionsEl.parentNode !== document.body) {
        document.body.appendChild(suggestionsEl);
      }
      desktopSuggestionsPortaled = true;
    }

    function bindDesktopScrollReposition() {
      if (!isDesktopInline || desktopScrollRepositionBound) return;
      desktopScrollRepositionBound = true;
      var onScroll = function () {
        if (desktopPositionRaf !== null) return;
        desktopPositionRaf = window.requestAnimationFrame(function () {
          desktopPositionRaf = null;
          positionDesktopSuggestions();
        });
      };
      window.addEventListener('scroll', onScroll, { passive: true, capture: true });
      window.addEventListener('resize', onScroll, { passive: true });
      if (window.visualViewport) {
        window.visualViewport.addEventListener('scroll', onScroll, { passive: true });
        window.visualViewport.addEventListener('resize', onScroll, { passive: true });
      }
      var p = inputEl.parentElement;
      while (p && p !== document.body && p !== document.documentElement) {
        var st = window.getComputedStyle(p);
        var ox = st.overflowX;
        var oy = st.overflowY;
        if (/(auto|scroll|overlay)/.test(ox) || /(auto|scroll|overlay)/.test(oy)) {
          p.addEventListener('scroll', onScroll, { passive: true });
        }
        p = p.parentElement;
      }
    }

    function positionDesktopSuggestions() {
      if (!isDesktopInline) return;
      if (!window.matchMedia || !window.matchMedia('(min-width: 1025px)').matches) {
        suggestionsEl.style.top = '';
        suggestionsEl.style.left = '';
        suggestionsEl.style.width = '';
        suggestionsEl.style.minWidth = '';
        suggestionsEl.style.maxWidth = '';
        suggestionsEl.style.boxSizing = '';
        suggestionsEl.style.transform = '';
        suggestionsEl.style.right = '';
        return;
      }
      ensureDesktopSuggestionsPortal();
      bindDesktopScrollReposition();
      var rect = inputEl.getBoundingClientRect();
      var vw = window.innerWidth;
      var margin = 16;
      var maxW = vw - margin * 2;
      var width = Math.min(980, Math.max(760, maxW));
      // Centrado horizontal en el viewport; vertical bajo el input (sigue el scroll).
      suggestionsEl.style.boxSizing = 'border-box';
      suggestionsEl.style.minWidth = '0';
      suggestionsEl.style.maxWidth = maxW + 'px';
      suggestionsEl.style.width = Math.round(width) + 'px';
      suggestionsEl.style.left = '50%';
      suggestionsEl.style.right = 'auto';
      suggestionsEl.style.top = Math.round(rect.bottom + 8) + 'px';
      suggestionsEl.style.transform = 'translateX(-50%)';
    }

    // Permite clic en la etiqueta de marca (sin crear anidamiento de <a> dentro del <a> del producto).
    suggestionsEl.addEventListener('click', function (e) {
      var target = e.target;
      if (!target || !target.closest) return;
      var marcaEl = target.closest('[data-marca-href]');
      if (!marcaEl) return;
      var href = marcaEl.getAttribute('data-marca-href');
      if (!href) return;
      e.preventDefault();
      e.stopPropagation();
      window.location.href = href;
    });
    var storageKey = 'centinelaSearchSuggestionsV3';
    var storageTtlMs = 15 * 60 * 1000; // 15 min
    var persistentStore = null;

    function nowTs() {
      return Date.now();
    }
    function loadPersistentStore() {
      if (persistentStore) return persistentStore;
      try {
        var raw = window.sessionStorage.getItem(storageKey);
        var parsed = raw ? JSON.parse(raw) : null;
        if (!parsed || typeof parsed !== 'object' || !parsed.entries || typeof parsed.entries !== 'object') {
          persistentStore = { entries: {} };
        } else {
          persistentStore = parsed;
        }
      } catch (e) {
        persistentStore = { entries: {} };
      }
      return persistentStore;
    }
    function persistStore() {
      try {
        window.sessionStorage.setItem(storageKey, JSON.stringify(loadPersistentStore()));
      } catch (e) {}
    }
    function persistentGet(key) {
      var store = loadPersistentStore();
      var item = store.entries[key];
      if (!item || !item.ts || !item.html) return null;
      if ((nowTs() - item.ts) > storageTtlMs) {
        delete store.entries[key];
        persistStore();
        return null;
      }
      return item.html;
    }
    function persistentSet(key, html) {
      var store = loadPersistentStore();
      store.entries[key] = { ts: nowTs(), html: html };
      // Limpieza simple de entradas vencidas para no crecer infinito.
      Object.keys(store.entries).forEach(function (k) {
        var item = store.entries[k];
        if (!item || !item.ts || (nowTs() - item.ts) > storageTtlMs) {
          delete store.entries[k];
        }
      });
      persistStore();
    }
    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }
    function escapeRegExp(value) {
      return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    function normalizeCacheKey(value) {
      return String(value || '')
        .toLowerCase()
        .replace(/[\s\-_]+/g, '');
    }
    function highlightText(text, query) {
      var safeText = escapeHtml(text);
      var safeQuery = escapeHtml(query);
      if (!safeQuery) return safeText;
      var pattern = new RegExp('(' + escapeRegExp(safeQuery) + ')', 'ig');
      return safeText.replace(pattern, '<span class="centinela-search-overlay__suggestions-highlight">$1</span>');
    }

    function hideSuggestions() {
      suggestionsEl.setAttribute('hidden', '');
      suggestionsEl.innerHTML = '';
      suggestionsEl.classList.remove('centinela-search-overlay__suggestions--loading');
    }
    function showLoading() {
      suggestionsEl.innerHTML = '<p class="centinela-search-overlay__suggestions-loading" aria-live="polite">' + searchLoadingLabel + '</p>';
      suggestionsEl.classList.add('centinela-search-overlay__suggestions--loading');
      suggestionsEl.removeAttribute('hidden');
      positionDesktopSuggestions();
    }
    function showSuggestions(html) {
      suggestionsEl.classList.remove('centinela-search-overlay__suggestions--loading');
      suggestionsEl.innerHTML = html;
      suggestionsEl.removeAttribute('hidden');
      positionDesktopSuggestions();
      bindProductThumbFallbacks();
    }
    function cacheGet(key) {
      if (Object.prototype.hasOwnProperty.call(searchResultsCache, key)) {
        return searchResultsCache[key];
      }
      return persistentGet(key);
    }
    function cacheSet(key, value) {
      if (!Object.prototype.hasOwnProperty.call(searchResultsCache, key)) {
        searchResultsCacheOrder.push(key);
      }
      searchResultsCache[key] = value;
      while (searchResultsCacheOrder.length > searchCacheMaxEntries) {
        var oldest = searchResultsCacheOrder.shift();
        if (oldest) delete searchResultsCache[oldest];
      }
      persistentSet(key, value);
    }
    function bindProductThumbFallbacks() {
      suggestionsEl.querySelectorAll('img[data-fallbacks]').forEach(function (img) {
        if (img.dataset.boundError === '1') return;
        img.dataset.boundError = '1';
        img.addEventListener('error', function () {
          var queue = (img.dataset.fallbacks || '')
            .split('|')
            .map(function (u) { return (u || '').trim(); })
            .filter(Boolean);
          var next = queue.shift();
          if (!next) {
            var thumbWrap = img.closest('.centinela-search-overlay__product-thumb');
            if (thumbWrap) thumbWrap.classList.add('centinela-search-overlay__product-thumb--empty');
            if (img.src !== syscomNoImage) {
              img.src = syscomNoImage;
            }
            return;
          }
          img.dataset.fallbacks = queue.join('|');
          if (img.src !== next) img.src = next;
        });
      });
    }
    function fetchSuggestions(q, options) {
      options = options || {};
      var silent = !!options.silent;
      if (!silent) {
        userDismissedSuggestions = false;
      }
      if (!q || q.length < 2) {
        if (!silent) hideSuggestions();
        return;
      }
      var cacheKey = normalizeCacheKey(q);
      var cachedHtml = cacheGet(cacheKey);
      if (cachedHtml) {
        if (!silent) showSuggestions(cachedHtml);
        return;
      }
      // UX rápida: mostrar inmediatamente el mejor prefijo cacheado mientras llega respuesta real.
      var bestPrefixKey = null;
      searchResultsCacheOrder.forEach(function (k) {
        if (cacheKey.indexOf(k) === 0 && (!bestPrefixKey || k.length > bestPrefixKey.length)) {
          bestPrefixKey = k;
        }
      });
      if (!silent && bestPrefixKey && cacheGet(bestPrefixKey)) {
        showSuggestions(cacheGet(bestPrefixKey));
      }
      if (searchAbortController) {
        searchAbortController.abort();
      }
      searchAbortController = new AbortController();
      if (!silent && !bestPrefixKey) {
        showLoading();
      }

      var apiUrl = (typeof centinelaTheme !== 'undefined' && centinelaTheme.searchApiUrl) ? centinelaTheme.searchApiUrl : (window.location.origin + '/wp-json/centinela/v1/search');
      var likelyBrandQuery = /^[a-zA-Z\s]+$/.test(q) && q.trim().length >= 4;
      // Desktop conserva más amplitud; mobile mantiene carga controlada.
      var inlineBrandLimit = isDesktopInline ? 240 : 80;
      var inlineRefLimit = isDesktopInline ? 60 : 40;
      var limitProductos = isInlineSearch ? (likelyBrandQuery ? inlineBrandLimit : inlineRefLimit) : 8;
      var limitContent = isInlineSearch ? 0 : 5;
      var params = 'q=' + encodeURIComponent(q) + '&limit_content=' + limitContent + '&limit_productos=' + limitProductos + '&suggestions=1';
      var url = apiUrl + (apiUrl.indexOf('?') !== -1 ? '&' : '?') + params;

      fetch(url, { signal: searchAbortController.signal })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var contenido = (data && data.contenido) ? data.contenido : [];
          var productos = (data && data.productos) ? data.productos : [];
          if (contenido.length === 0 && productos.length === 0) {
            hideSuggestions();
            return;
          }
          var parts = [];
          if (contenido.length > 0) {
            parts.push('<p class="centinela-search-overlay__suggestions-title">Contenido</p><ul class="centinela-search-overlay__suggestions-list">');
            contenido.forEach(function (c) {
              parts.push('<li><a href="' + (c.url || '#') + '" class="centinela-search-overlay__suggestions-link">' + highlightText(c.title || '', q) + '</a></li>');
            });
            parts.push('</ul>');
          }
          if (productos.length > 0) {
            parts.push('<p class="centinela-search-overlay__suggestions-title">Productos</p><ul class="centinela-search-overlay__suggestions-list">');
            productos.forEach(function (p) {
              var imageSrc = (p && p.imagen) ? String(p.imagen).trim() : '';
              var fallbackQueue = Array.isArray(p.imagen_fallbacks) ? p.imagen_fallbacks.slice() : [];
              fallbackQueue.push(syscomNoImage);
              fallbackQueue = fallbackQueue
                .map(function (u) { return String(u || '').trim(); })
                .filter(Boolean)
                .filter(function (u, idx, arr) { return arr.indexOf(u) === idx; });
              if (imageSrc) {
                fallbackQueue = fallbackQueue.filter(function (u) { return String(u || '').trim() !== imageSrc; });
              }
              if (!imageSrc && fallbackQueue.length) {
                imageSrc = fallbackQueue.shift();
              }
              var fallbackAttr = escapeHtml(fallbackQueue.join('|'));
              var imageHtml = imageSrc
                ? '<span class="centinela-search-overlay__product-thumb"><img src="' + escapeHtml(imageSrc) + '" alt="' + escapeHtml(p.titulo || '') + '" loading="lazy" decoding="async" data-fallbacks="' + fallbackAttr + '" /></span>'
                : '<span class="centinela-search-overlay__product-thumb centinela-search-overlay__product-thumb--empty" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5.5" width="17" height="13" rx="1.8"></rect><circle cx="9" cy="10" r="1.5"></circle><path d="M5.5 16l4.2-3.8a1 1 0 0 1 1.37.03L14 14.8l1.8-1.6a1 1 0 0 1 1.33.03L19 15"></path></svg></span>';
              var marcaRaw = (p && p.marca) ? String(p.marca).trim() : '';
              var marcaHref = marcaRaw
                ? (window.location.origin + '/tienda/?marca=' + encodeURIComponent(marcaRaw))
                : '';
              var marcaHrefSafe = marcaHref ? escapeHtml(marcaHref) : '';
              parts.push(
                '<li><a href="' + (p.url || '#') + '" class="centinela-search-overlay__suggestions-link centinela-search-overlay__suggestions-link--product">' +
                imageHtml +
                '<span class="centinela-search-overlay__product-meta">' +
                '<span class="centinela-search-overlay__product-title">' + highlightText(p.titulo || '', q) + '</span>' +
                '<span class="centinela-search-overlay__suggestions-modelo">' + (p.modelo ? highlightText(p.modelo, q) : '') + '</span>' +
                (marcaHref
                  ? '<span class="centinela-search-overlay__suggestions-marca centinela-search-overlay__suggestions-marca-link" data-marca-href="' + marcaHrefSafe + '" data-marca="' + escapeHtml(marcaRaw) + '">' + highlightText(p.marca || '', q) + '</span>'
                  : '<span class="centinela-search-overlay__suggestions-marca">' + (p.marca ? highlightText(p.marca, q) : '') + '</span>') +
                '</span>' +
                '</span>' +
                '</a></li>'
              );
            });
            parts.push('</ul>');
          }
          var htmlOut = parts.join('');
          cacheSet(cacheKey, htmlOut);
          if (!silent) {
            if (userDismissedSuggestions) {
              return;
            }
            showSuggestions(htmlOut);
          }
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          if (!silent) hideSuggestions();
        });
    }

    inputEl.addEventListener('input', function () {
      var q = (inputEl.value || '').trim();
      clearTimeout(searchSuggestionsTimer);
      if (q.length < 2) {
        hideSuggestions();
        return;
      }
      userDismissedSuggestions = false;
      // Desktop: dispara un poco antes; mobile: un poco más de debounce para menos peticiones.
      var debounceMs = isMobileInline ? 135 : 85;
      searchSuggestionsTimer = setTimeout(function () { fetchSuggestions(q); }, debounceMs);
    });
    inputEl.addEventListener('focus', function () {
      userDismissedSuggestions = false;
      var q = (inputEl.value || '').trim();
      if (q.length < 2) {
        return;
      }
      var k = normalizeCacheKey(q);
      var cached = cacheGet(k);
      if (cached) {
        showSuggestions(cached);
        return;
      }
      fetchSuggestions(q);
    });
    // No cerrar al mover cursor dentro de sugerencias; solo cerrar cuando se hace click fuera.
    document.addEventListener('click', function (e) {
      var target = e.target;
      var insideInput = inputEl.contains(target);
      var insideSuggestions = suggestionsEl.contains(target);
      if (!insideInput && !insideSuggestions) {
        userDismissedSuggestions = true;
        if (searchAbortController) {
          searchAbortController.abort();
        }
        hideSuggestions();
      }
    });
    inputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        userDismissedSuggestions = true;
        if (searchAbortController) {
          searchAbortController.abort();
        }
        hideSuggestions();
      }
    });

    // Prefetch solo en desktop: en móvil evita ~11 peticiones REST en home (mejor Lighthouse móvil).
    if (isDesktopInline) {
      var isHomePath = window.location.pathname === '/' || window.location.pathname === '';
      if (!isHomePath) return;
      var warmupSessionKey = 'centinelaSearchWarmupDoneV1';
      try {
        if (window.sessionStorage.getItem(warmupSessionKey) === '1') return;
      } catch (e) {}
      var prefetchTerms = [
        // Marcas frecuentes
        'ACCESSPRO', 'HIKVISION', 'EPCOM', 'DAHUA',
        // Referencias/modelos populares
        'AP1000', 'AP 1000', 'AP-1000',
        'AP2000', 'AP 2000', 'AP-2000',
        'AP2000HD', 'AP1000HD'
      ];
      var prefetchIdx = 0;
      var prefetchRun = function () {
        if (prefetchIdx >= prefetchTerms.length) return;
        var term = prefetchTerms[prefetchIdx++];
        var key = term.toLowerCase();
        if (cacheGet(key)) {
          prefetchRun();
          return;
        }
        fetchSuggestions(term, { silent: true });
        // Espaciado corto para calentar caché sin bloquear UI.
        window.setTimeout(prefetchRun, 240);
      };
      if ('requestIdleCallback' in window) {
        window.requestIdleCallback(prefetchRun, { timeout: 1800 });
      } else {
        window.setTimeout(prefetchRun, 500);
      }
      try {
        window.sessionStorage.setItem(warmupSessionKey, '1');
      } catch (e) {}
    }
  }

  initLiveSearch(
    document.getElementById('centinela-search-field'),
    document.getElementById('centinela-search-suggestions')
  );
  initLiveSearch(
    document.getElementById('centinela-mobile-search-field'),
    document.getElementById('centinela-mobile-search-suggestions')
  );
  initLiveSearch(
    document.getElementById('centinela-desktop-search-field'),
    document.getElementById('centinela-desktop-search-suggestions')
  );

  // Enter manual en buscador: dirigir a /tienda/?marca=<query>.
  // No afecta clic en sugerencias (esas ya navegan con su propio href).
  function bindSearchSubmitToMarca(formSelector, inputSelector) {
    var formEl = document.querySelector(formSelector);
    if (!formEl) return;
    var inputEl = formEl.querySelector(inputSelector);
    if (!inputEl) return;
    formEl.addEventListener('submit', function (e) {
      var q = (inputEl.value || '').trim();
      if (!q) return; // deja comportamiento normal para búsquedas vacías.
      e.preventDefault();
      var target = window.location.origin + '/tienda/?marca=' + encodeURIComponent(q);
      window.location.href = target;
    });
  }

  bindSearchSubmitToMarca('.centinela-header__search-inline-form', '#centinela-desktop-search-field');
  bindSearchSubmitToMarca('.centinela-mobile-search-form', '#centinela-mobile-search-field');
  bindSearchSubmitToMarca('#centinela-search-form', '#centinela-search-field');

  // Submenú de categorías: toggle de subcategorías en móvil (acordeón)
  var submenuToggles = document.querySelectorAll('.centinela-submenu__toggle');
  submenuToggles.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var targetId = btn.getAttribute('aria-controls');
      var dropdown = targetId ? document.getElementById(targetId) : null;
      if (!dropdown) return;
      var isOpen = dropdown.classList.contains('is-open');
      var submenu = document.querySelector('.centinela-submenu');
      var item = btn.closest('.centinela-submenu__item--has-dropdown');
      document.querySelectorAll('.centinela-submenu__dropdown.is-open').forEach(function (d) {
        if (d !== dropdown) {
          d.classList.remove('is-open');
          var t = document.querySelector('.centinela-submenu__toggle[aria-controls="' + d.id + '"]');
          if (t) t.setAttribute('aria-expanded', 'false');
          var otherItem = t && t.closest('.centinela-submenu__item--has-dropdown');
          if (otherItem) otherItem.classList.remove('is-active');
        }
      });
      if (isOpen) {
        dropdown.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        if (submenu) submenu.classList.remove('is-dropdown-open');
        if (item) item.classList.remove('is-active');
      } else {
        dropdown.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        if (submenu) submenu.classList.add('is-dropdown-open');
        if (item) item.classList.add('is-active');
      }
    });
  });

  // Submenú: desktop hover siempre conectado; solo actúa si viewport >= 768 (evita fallo al resize)
  var submenu = document.querySelector('.centinela-submenu');
  if (submenu) {
    var closeTimeout = null;
    var isDesktop = function () { return window.matchMedia('(min-width: 768px)').matches; };
    var closeAllDropdowns = function () {
      submenu.querySelectorAll('.centinela-submenu__dropdown.is-open').forEach(function (d) { d.classList.remove('is-open'); });
      submenu.querySelectorAll('.centinela-submenu__item--has-dropdown.is-active').forEach(function (i) { i.classList.remove('is-active'); });
      submenu.classList.remove('is-dropdown-open');
      submenu.querySelectorAll('.centinela-submenu__toggle[aria-expanded="true"]').forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
    };

    var items = submenu.querySelectorAll('.centinela-submenu__item[data-centinela-submenu-id]');
    items.forEach(function (item) {
      var id = item.getAttribute('data-centinela-submenu-id');
      var dropdown = id ? submenu.querySelector('.centinela-submenu__dropdown[data-centinela-submenu-id="' + id + '"]') : null;
      if (!dropdown) return;
      item.addEventListener('mouseenter', function () {
        if (!isDesktop()) return;
        if (closeTimeout) clearTimeout(closeTimeout);
        closeTimeout = null;
        closeAllDropdowns();
        submenu.classList.add('is-dropdown-open');
        dropdown.classList.add('is-open');
        item.classList.add('is-active');
      });
      item.addEventListener('mouseleave', function () {
        if (!isDesktop()) return;
        var self = dropdown;
        closeTimeout = setTimeout(function () {
          self.classList.remove('is-open');
          item.classList.remove('is-active');
          submenu.classList.remove('is-dropdown-open');
          closeTimeout = null;
        }, 100);
      });
    });
    var wrap = submenu.querySelector('.centinela-submenu__dropdown-wrap');
    if (wrap) {
      wrap.addEventListener('mouseenter', function () {
        if (!isDesktop()) return;
        if (closeTimeout) clearTimeout(closeTimeout);
        closeTimeout = null;
        submenu.classList.add('is-dropdown-open');
      });
      wrap.addEventListener('mouseleave', function () {
        if (!isDesktop()) return;
        closeAllDropdowns();
      });
    }

    // Al cambiar viewport: al pasar a mobile cerrar dropdowns; al pasar a desktop cerrar menú móvil y dropdowns
    var lastDesktop = isDesktop();
    window.addEventListener('resize', function () {
      var nowDesktop = isDesktop();
      if (!nowDesktop) {
        closeAllDropdowns();
      } else {
        if (!lastDesktop) {
          closeAllDropdowns();
          var mobileMenu = document.getElementById('primary-menu-mobile');
          var toggle = document.getElementById('menu-toggle');
          if (mobileMenu) {
            closeMobileMenu();
          }
          if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
          }
        }
      }
      lastDesktop = nowDesktop;
    });
  }

  // Carrito: items con { id, qty, title, image, price }. Migrar desde centinela_cotizacion_ids si existe.
  var CART_KEY = 'centinela_cart_items';
  var CART_KEY_LEGACY = 'centinela_cotizacion_ids';

  function getCartItems() {
    try {
      var stored = localStorage.getItem(CART_KEY);
      if (stored) {
        var items = JSON.parse(stored);
        return Array.isArray(items) ? items : [];
      }
      var legacy = localStorage.getItem(CART_KEY_LEGACY);
      if (legacy) {
        var ids = JSON.parse(legacy);
        if (Array.isArray(ids) && ids.length) {
          var byId = {};
          ids.forEach(function (id) {
            id = String(id);
            byId[id] = (byId[id] || 0) + 1;
          });
          var migrated = [];
          Object.keys(byId).forEach(function (id) {
            migrated.push({ id: id, qty: byId[id], title: '', image: '', price: '' });
          });
          localStorage.setItem(CART_KEY, JSON.stringify(migrated));
          try { localStorage.removeItem(CART_KEY_LEGACY); } catch (e) {}
          return migrated;
        }
      }
    } catch (e) {}
    return [];
  }

  function saveCartItems(items) {
    try {
      localStorage.setItem(CART_KEY, JSON.stringify(items));
    } catch (e) {}
  }

  // Acepta "368194.46" (API) o "368.194,46" (formato Colombia)
  function parsePrice(str) {
    if (str === '' || str == null) return 0;
    var s = String(str).trim().replace(/\s*COP\s*$/i, '').replace(/[^\d.,\-]/g, '');
    if (s.indexOf(',') !== -1) {
      s = s.replace(/\./g, '').replace(',', '.');
    }
    var n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  }

  // Formato Syscom: CO $ X,XXX.XX (miles con coma, decimales con punto).
  function formatPrice(num) {
    var n = Number(num);
    if (isNaN(n) || n < 0) return 'CO $ 0';
    if (n === 0) return 'CO $ 0';
    var parts = Number(n).toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    var out = parts.join('.');
    if (parts[1] === '00') out = parts[0];
    return 'CO $ ' + out;
  }

  function truncateTitle(title, maxLen) {
    if (!title) return '';
    maxLen = maxLen || 40;
    if (title.length <= maxLen) return title;
    return title.slice(0, maxLen).trim() + '…';
  }

  function addItemToCart(item) {
    var items = getCartItems();
    var id = String(item.id);
    var qty = Math.max(1, parseInt(item.qty, 10) || 1);
    var source = item.source === 'wc' ? 'wc' : 'syscom';
    var existing = items.filter(function (i) {
      return String(i.id) === id && (i.source || 'syscom') === source;
    })[0];
    if (existing) {
      existing.qty += qty;
      if (item.title) existing.title = item.title;
      if (item.image) existing.image = item.image;
      if (item.price !== undefined && item.price !== '') existing.price = item.price;
      if (item.product_url) existing.product_url = item.product_url;
    } else {
      var newItem = {
        id: id,
        qty: qty,
        title: item.title || '',
        image: item.image || '',
        price: item.price || ''
      };
      if (source === 'wc') newItem.source = 'wc';
      if (item.product_url) newItem.product_url = item.product_url;
      items.push(newItem);
    }
    saveCartItems(items);
  }

  function removeItemFromCart(index) {
    var items = getCartItems();
    items.splice(index, 1);
    saveCartItems(items);
  }

  function updateItemQty(index, newQty) {
    var items = getCartItems();
    if (index < 0 || index >= items.length) return;
    newQty = parseInt(newQty, 10) || 0;
    if (newQty <= 0) {
      items.splice(index, 1);
    } else {
      items[index].qty = newQty;
    }
    saveCartItems(items);
  }

  function renderCartDropdown() {
    var dropdown = document.getElementById('centinela-cart-dropdown');
    var itemsEl = document.getElementById('centinela-cart-dropdown-items');
    var contentEl = document.getElementById('centinela-cart-dropdown-content');
    var emptyEl = document.getElementById('centinela-cart-dropdown-empty');
    var emptyCta = document.getElementById('centinela-cart-dropdown-empty-cta');
    var subtotalEl = document.getElementById('centinela-cart-dropdown-subtotal');
    var checkoutBtn = document.getElementById('centinela-cart-dropdown-checkout');
    var viewLink = document.getElementById('centinela-cart-dropdown-view');
    var continueLink = document.getElementById('centinela-cart-dropdown-continue');
    if (!dropdown || !itemsEl) return;

    var items = getCartItems();
    var totalQty = 0;
    var subtotalNum = 0;
    items.forEach(function (i) { totalQty += (i.qty || 1); subtotalNum += parsePrice(i.price) * (i.qty || 1); });

    if (items.length === 0) {
      if (contentEl) contentEl.style.display = 'none';
      if (emptyEl) emptyEl.style.display = '';
      if (emptyCta) emptyCta.style.display = '';
      if (subtotalEl) subtotalEl.textContent = 'CO $ 0';
      return;
    }

    if (contentEl) contentEl.style.display = 'block';
    if (emptyEl) emptyEl.style.display = 'none';
    if (emptyCta) emptyCta.style.display = 'none';

    var cartUrl = dropdown.getAttribute('data-cart-url') || '';
    var checkoutUrl = dropdown.getAttribute('data-checkout-url') || cartUrl;
    var tiendaUrl = dropdown.getAttribute('data-tienda-url') || '';
    // Forzar http en localhost por si el servidor envió https (evitar ERR_SSL_PROTOCOL_ERROR).
    function forceHttpLocalhost(url) {
      if (typeof url !== 'string' || !url) return url;
      if (url.indexOf('https://localhost') === 0 || url.indexOf('https://127.0.0.1') === 0) return 'http' + url.slice(5);
      return url;
    }
    cartUrl = forceHttpLocalhost(cartUrl);
    checkoutUrl = forceHttpLocalhost(checkoutUrl);
    tiendaUrl = forceHttpLocalhost(tiendaUrl);
    if (checkoutBtn) checkoutBtn.href = checkoutUrl;
    if (viewLink) viewLink.href = cartUrl;
    if (continueLink) continueLink.href = tiendaUrl;

    if (subtotalEl) subtotalEl.textContent = formatPrice(subtotalNum);

    var html = '';
    items.forEach(function (item, index) {
      var qty = Math.max(1, item.qty || 1);
      var priceNum = parsePrice(item.price);
      var lineTotal = priceNum * qty;
      var title = truncateTitle(item.title || ('Producto #' + item.id), 42);
      var img = item.image ? ('<img src="' + item.image.replace(/"/g, '&quot;') + '" alt="" loading="lazy" />') : '<span class="centinela-header__cart-item-noimg"></span>';
      html += '<div class="centinela-header__cart-item" data-index="' + index + '">';
      html += '<button type="button" class="centinela-header__cart-item-remove" aria-label="Quitar producto" data-index="' + index + '">&times;</button>';
      html += '<div class="centinela-header__cart-item-thumb">' + img + '</div>';
      html += '<div class="centinela-header__cart-item-info">';
      html += '<span class="centinela-header__cart-item-name">' + (title.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</span>';
      html += '<div class="centinela-header__cart-item-qty-wrap">';
      html += '<label class="centinela-header__cart-item-qty-label" for="centinela-cart-qty-' + index + '">Cant.</label>';
      html += '<input type="number" class="centinela-header__cart-item-qty" id="centinela-cart-qty-' + index + '" min="1" value="' + qty + '" data-index="' + index + '" />';
      html += '</div>';
      html += '<span class="centinela-header__cart-item-total">' + formatPrice(lineTotal) + '</span>';
      html += '</div></div>';
    });
    itemsEl.innerHTML = html;

    itemsEl.querySelectorAll('.centinela-header__cart-item-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idx = parseInt(btn.getAttribute('data-index'), 10);
        removeItemFromCart(idx);
        updateCartCountDisplay();
      });
    });
    itemsEl.querySelectorAll('.centinela-header__cart-item-qty').forEach(function (input) {
      input.addEventListener('change', function () {
        var idx = parseInt(input.getAttribute('data-index'), 10);
        var val = parseInt(input.value, 10) || 1;
        if (val < 1) val = 1;
        input.value = val;
        updateItemQty(idx, val);
        updateCartCountDisplay();
      });
    });
  }

  function updateCartCountDisplay() {
    var items = getCartItems();
    var count = 0;
    items.forEach(function (i) { count += (i.qty || 1); });

    var badge = document.getElementById('centinela-header-cart-count');
    var dropdownText = document.getElementById('centinela-cart-dropdown-count');
    var dropdownEmpty = document.getElementById('centinela-cart-dropdown-empty');
    if (badge) {
      badge.textContent = String(count);
      badge.setAttribute('data-count', count);
    }
    if (dropdownText) {
      dropdownText.textContent = count === 1 ? '(1 producto)' : '(' + count + ' productos)';
    }
    if (dropdownEmpty) {
      dropdownEmpty.style.display = count > 0 ? 'none' : '';
    }
    var contentEl = document.getElementById('centinela-cart-dropdown-content');
    var emptyCta = document.getElementById('centinela-cart-dropdown-empty-cta');
    if (contentEl) contentEl.style.display = count > 0 ? 'block' : 'none';
    if (emptyCta) emptyCta.style.display = count > 0 ? 'none' : '';

    renderCartDropdown();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateCartCountDisplay);
  } else {
    updateCartCountDisplay();
  }
  window.centinelaUpdateCartCount = updateCartCountDisplay;
  function showAddedToCartToast(productTitle) {
    var title = (productTitle || '').trim() || 'Producto';
    var toast = document.getElementById('centinela-add-to-cart-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'centinela-add-to-cart-toast';
      toast.className = 'centinela-toast';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      document.body.appendChild(toast);
    }
    toast.innerHTML = '<span class="centinela-toast__icon" aria-hidden="true">✓</span><span class="centinela-toast__text">Agregado al carrito: <strong>' + (title.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</strong></span>';
    toast.classList.add('centinela-toast--visible');
    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(function () {
      toast.classList.remove('centinela-toast--visible');
    }, 3500);
  }

  window.centinelaAddToCart = function (item) {
    addItemToCart(item);
    updateCartCountDisplay();
    showAddedToCartToast(item && item.title);
  };
  window.centinelaGetCartItems = getCartItems;
  window.centinelaSaveCartItems = saveCartItems;
  window.centinelaRemoveItemFromCart = removeItemFromCart;
  window.centinelaUpdateItemQty = updateItemQty;
  window.centinelaParsePrice = parsePrice;
  window.centinelaFormatPrice = formatPrice;

  // Header: fixed + fondo #021C37 al scroll down con animación slide; slide up al volver al top (estilo WiseGuard)
  (function () {
    var headerBar = document.querySelector('.centinela-header-bar');
    if (!headerBar) return;
    var scrollThreshold = 60;
    var isScrolled = false;
    var animatingOut = false;
    var outEndHandler = null;

    function cancelOutAnimation() {
      if (!animatingOut) return;
      animatingOut = false;
      isScrolled = true;
      if (outEndHandler) {
        headerBar.removeEventListener('transitionend', outEndHandler);
        outEndHandler = null;
      }
      headerBar.classList.remove('centinela-header-bar--scrolled-out');
    }

    function onScroll() {
      var scrollY = window.scrollY || window.pageYOffset || 0;

      if (scrollY > scrollThreshold) {
        if (animatingOut) cancelOutAnimation();
        if (!isScrolled) {
          isScrolled = true;
          headerBar.classList.remove('centinela-header-bar--scrolled-out');
          headerBar.classList.add('centinela-header-bar--scrolled', 'centinela-header-bar--scrolled-in');
          requestAnimationFrame(function () {
            requestAnimationFrame(function () {
              headerBar.classList.remove('centinela-header-bar--scrolled-in');
            });
          });
        }
      } else {
        if (isScrolled && !animatingOut) {
          isScrolled = false;
          headerBar.classList.add('centinela-header-bar--scrolled-out');
          animatingOut = true;
          outEndHandler = function onOutEnd(e) {
            if (e.target !== headerBar || e.propertyName !== 'transform') return;
            headerBar.removeEventListener('transitionend', onOutEnd);
            outEndHandler = null;
            headerBar.classList.remove('centinela-header-bar--scrolled', 'centinela-header-bar--scrolled-out');
            animatingOut = false;
          };
          headerBar.addEventListener('transitionend', outEndHandler);
        }
      }
    }

    window.addEventListener('scroll', function () {
      window.requestAnimationFrame(onScroll);
    }, { passive: true });
    onScroll();
  })();
})();
