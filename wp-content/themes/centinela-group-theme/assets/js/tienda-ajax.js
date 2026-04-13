/**
 * Tienda: filtrado por categoría y paginación sin recargar la página.
 * Intercepta clics en sidebar y paginación, llama al REST API y actualiza el grid.
 */
(function () {
  'use strict';

  const container = document.querySelector('.centinela-tienda');
  if (!container) return;

  const contentEl = document.getElementById('centinela-tienda-ajax-content');
  const sidebarEl = document.getElementById('centinela-tienda-sidebar');
  const mobileSidebarEl = document.getElementById('centinela-tienda-mobile-sidebar');
  const mobileFiltersToggleEl = document.getElementById('centinela-mobile-filters-toggle');
  const mobileFiltersCountEl = document.getElementById('centinela-mobile-filters-count');
  const mobileFiltersCloseEl = document.getElementById('centinela-mobile-filters-close');
  const mobileFiltersBackdropEl = document.getElementById('centinela-mobile-filters-backdrop');
  const brandSelectEl = document.getElementById('centinela-tienda-brand-select');
  if (!contentEl) return;

  function isMobileFiltersViewport() {
    return !!window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
  }

  function openMobileFilters() {
    if (!mobileSidebarEl || !isMobileFiltersViewport()) return;
    mobileSidebarEl.classList.add('is-mobile-open');
    if (mobileFiltersBackdropEl) {
      mobileFiltersBackdropEl.removeAttribute('hidden');
    }
    if (mobileFiltersToggleEl) {
      mobileFiltersToggleEl.setAttribute('aria-expanded', 'true');
    }
    document.body.classList.add('centinela-no-scroll');
    syncBrandSelectValue();
  }

  function closeMobileFilters() {
    if (!mobileSidebarEl) return;
    mobileSidebarEl.classList.remove('is-mobile-open');
    if (mobileFiltersBackdropEl) {
      mobileFiltersBackdropEl.setAttribute('hidden', '');
    }
    if (mobileFiltersToggleEl) {
      mobileFiltersToggleEl.setAttribute('aria-expanded', 'false');
    }
    document.body.classList.remove('centinela-no-scroll');
  }

  function updateMobileFiltersCount(overrides) {
    if (!mobileFiltersCountEl || !mobileFiltersToggleEl) return;
    overrides = overrides || {};
    var count = 0;
    var categoria = (overrides.categoria != null ? String(overrides.categoria) : (contentEl.getAttribute('data-categoria') || '')).trim();
    var marca = (overrides.marca != null ? String(overrides.marca) : (contentEl.getAttribute('data-marca') || '')).trim();
    var minP = (overrides.minPrice != null ? String(overrides.minPrice) : (contentEl.getAttribute('data-min-price') || '')).trim();
    var maxP = (overrides.maxPrice != null ? String(overrides.maxPrice) : (contentEl.getAttribute('data-max-price') || '')).trim();

    // Fallback visual: si el estado data-* aún no se actualiza, leer clases activas.
    if (!categoria && sidebarEl) {
      var activeCat = sidebarEl.querySelector('.centinela-tienda__cat-link--active[data-categoria-id]');
      if (activeCat) {
        categoria = (activeCat.getAttribute('data-categoria-id') || '').trim();
      }
    }
    if (!marca && brandSelectEl && brandSelectEl.value) {
      marca = String(brandSelectEl.value).trim();
    }
    if (!minP && !maxP) {
      var activePrice = container.querySelector('.centinela-tienda__price-range-link--active');
      if (activePrice) {
        minP = (activePrice.getAttribute('data-min-price') || '').trim();
        maxP = (activePrice.getAttribute('data-max-price') || '').trim();
      }
    }

    if (categoria) count++;
    if (marca) count++;
    if (minP || maxP) count++;
    mobileFiltersCountEl.textContent = String(count);
    if (count > 0) {
      mobileFiltersCountEl.removeAttribute('hidden');
      mobileFiltersToggleEl.classList.add('is-has-filters');
      mobileFiltersToggleEl.setAttribute('aria-label', 'Filtrar productos, ' + count + ' filtros activos');
    } else {
      mobileFiltersCountEl.setAttribute('hidden', '');
      mobileFiltersToggleEl.classList.remove('is-has-filters');
      mobileFiltersToggleEl.setAttribute('aria-label', 'Filtrar productos');
    }
  }

  var tiendaBase = (document.querySelector('link[rel="canonical"]') && document.querySelector('link[rel="canonical"]').href) ? document.querySelector('link[rel="canonical"]').href : (window.location.origin + '/tienda/');
  if (window.location.pathname.indexOf('/tienda') !== -1) {
    tiendaBase = window.location.origin + window.location.pathname.replace(/\/?$/, '/');
  }

  function getRestUrl(params) {
    var q = Object.keys(params).filter(function (k) { return params[k] !== '' && params[k] != null && params[k] !== false; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    return '/wp-json/centinela/v1/tienda-productos?' + q;
  }

  function fetchProductos(categoria, pagina, ordenar, catPath, marca, minPrice, maxPrice, extra) {
    extra = extra || {};
    var params = { pagina: String(pagina), ordenar: ordenar || 'relevancia' };
    if (categoria) params.categoria = categoria;
    if (catPath) params.cat_path = catPath;
    if (marca != null && String(marca).trim() !== '') params.marca = String(marca).trim();
    if (minPrice) params.min_price = minPrice;
    if (maxPrice) params.max_price = maxPrice;
    if (extra.quick) params.quick = '1';
    var url = getRestUrl(params);
    return fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); });
  }

  /**
   * Rellena los precios de las tarjetas con el endpoint de vista rápida (mismo que detalle).
   * Así el listado muestra siempre el mismo precio que la página de detalle.
   */
  function fillCardPricesFromQuickView() {
    var cards = contentEl.querySelectorAll('.centinela-tienda__card[data-product-id]');
    var syscomCards = [];
    cards.forEach(function (card) {
      if (card.getAttribute('data-quickview-source') === 'wc') return;
      var id = (card.getAttribute('data-product-id') || '').trim();
      if (id && /^[0-9]+$/.test(id)) syscomCards.push({ card: card, id: id });
    });
    if (syscomCards.length === 0) return;
    var baseUrl = (typeof wp !== 'undefined' && wp.apiFetch && wp.apiFetch.defaultOptions && wp.apiFetch.defaultOptions.root) ? wp.apiFetch.defaultOptions.root : (window.location.origin + '/wp-json');
    syscomCards.forEach(function (item) {
      fetch(baseUrl + '/centinela/v1/producto-quick-view?id=' + encodeURIComponent(item.id), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data || !data.precio_formateado) return;
          var wrap = item.card.querySelector('.centinela-tienda__card-price-wrap');
          if (!wrap) return;
          var priceEl = wrap.querySelector('.centinela-tienda__card-price');
          if (priceEl) priceEl.textContent = data.precio_formateado;
        })
        .catch(function () {});
    });
  }

  function setContent(html, catPath, pagina, marca, minPrice, maxPrice) {
    contentEl.innerHTML = html || '';
    if (catPath !== undefined) contentEl.setAttribute('data-cat-path', catPath || '');
    if (pagina !== undefined) contentEl.setAttribute('data-pagina', String(pagina));
    if (marca !== undefined) contentEl.setAttribute('data-marca', marca || '');
    if (minPrice !== undefined) contentEl.setAttribute('data-min-price', minPrice || '');
    if (maxPrice !== undefined) contentEl.setAttribute('data-max-price', maxPrice || '');
    bindPagination();
    syncPriceRangeActive(minPrice, maxPrice);
    fillCardPricesFromQuickView();
    updateMobileFiltersCount();
    syncBrandSelectValue();
  }

  function buildTiendaUrl(catPath, pagina, marca, minPrice, maxPrice) {
    var base = window.location.origin + '/tienda/';
    if (catPath && catPath.trim()) base = window.location.origin + '/tienda/' + catPath.replace(/\/+$/, '') + '/';
    var q = [];
    if (pagina && parseInt(pagina, 10) > 1) q.push('pag=' + String(pagina));
    if (marca && marca.trim()) q.push('marca=' + encodeURIComponent(marca.trim()));
    if (minPrice && minPrice.trim()) q.push('min_price=' + encodeURIComponent(minPrice.trim()));
    if (maxPrice && maxPrice.trim()) q.push('max_price=' + encodeURIComponent(maxPrice.trim()));
    if (q.length) return base + '?' + q.join('&');
    return base;
  }

  function updateState(categoria, catPath, pagina, marca, minPrice, maxPrice, useReplace) {
    var url = buildTiendaUrl(catPath || '', pagina, marca || '', minPrice || '', maxPrice || '');
    var state = { tienda: true, categoria: categoria || '', cat_path: catPath || '', pagina: pagina || '1', marca: marca || '', min_price: minPrice || '', max_price: maxPrice || '' };
    if (useReplace) {
      window.history.replaceState(state, '', url);
    } else {
      window.history.pushState(state, '', url);
    }
    contentEl.setAttribute('data-categoria', categoria || '');
    contentEl.setAttribute('data-cat-path', catPath || '');
    contentEl.setAttribute('data-pagina', pagina || '1');
    contentEl.setAttribute('data-marca', marca || '');
    contentEl.setAttribute('data-min-price', minPrice || '');
    contentEl.setAttribute('data-max-price', maxPrice || '');
    updateMobileFiltersCount();
  }

  function syncPriceRangeActive(minPrice, maxPrice) {
    var wrap = document.getElementById('centinela-tienda-price-ranges');
    if (!wrap) return;
    var minP = (minPrice !== undefined && minPrice !== null) ? String(minPrice) : (contentEl.getAttribute('data-min-price') || '');
    var maxP = (maxPrice !== undefined && maxPrice !== null) ? String(maxPrice) : (contentEl.getAttribute('data-max-price') || '');
    wrap.querySelectorAll('.centinela-tienda__price-range-link').forEach(function (a) {
      var active = (a.getAttribute('data-min-price') || '') === minP && (a.getAttribute('data-max-price') || '') === maxP;
      a.classList.toggle('centinela-tienda__price-range-link--active', active);
    });
  }

  var TIENDA_FILTER_LOADING_CLASS = 'centinela-tienda__filter-loading-overlay';

  function showTiendaFilterLoading() {
    var existing = contentEl.querySelector('.' + TIENDA_FILTER_LOADING_CLASS);
    if (existing) {
      existing.remove();
    }
    var wrap = document.createElement('div');
    wrap.className = TIENDA_FILTER_LOADING_CLASS;
    wrap.setAttribute('role', 'status');
    wrap.setAttribute('aria-live', 'polite');
    wrap.setAttribute('aria-busy', 'true');
    var p = document.createElement('p');
    p.className = 'centinela-tienda__filter-loading-text';
    p.textContent = 'Buscando coincidencias…';
    wrap.appendChild(p);
    contentEl.appendChild(wrap);
  }

  function removeTiendaFilterLoading() {
    var existing = contentEl.querySelector('.' + TIENDA_FILTER_LOADING_CLASS);
    if (existing) {
      existing.remove();
    }
  }

  function setTiendaFilterLoadingMessage(msg) {
    var p = contentEl.querySelector('.centinela-tienda__filter-loading-text');
    if (p && msg) p.textContent = msg;
  }

  var brandScrollObserver = null;

  function teardownBrandInfiniteScroll() {
    if (brandScrollObserver) {
      brandScrollObserver.disconnect();
      brandScrollObserver = null;
    }
    contentEl.removeAttribute('data-brand-infinite-active');
    contentEl.removeAttribute('data-brand-pages-total');
    contentEl.removeAttribute('data-brand-pages-loaded');
    contentEl.removeAttribute('data-brand-infinite-cap');
    var sen = contentEl.querySelector('.centinela-tienda__infinite-sentinel');
    if (sen) sen.remove();
    var st = contentEl.querySelector('.centinela-tienda__infinite-status');
    if (st) st.remove();
    var pag = contentEl.querySelector('.centinela-tienda__pagination');
    if (pag) {
      pag.classList.remove('centinela-tienda__pagination--brand-deferred');
      pag.removeAttribute('hidden');
    }
  }

  /** Máximo de páginas a prefetchar por scroll; el resto queda en el paginador (evita decenas de peticiones). */
  var TIENDA_BRAND_INFINITE_MAX_PAGES = 15;

  /**
   * Tras cargar la página 1 por marca, oculta el paginador y añade páginas 2+ al hacer scroll.
   */
  function setupBrandInfiniteScroll(pagesTotal) {
    teardownBrandInfiniteScroll();
    var marca = (contentEl.getAttribute('data-marca') || '').trim();
    if (!marca || pagesTotal < 2) return;
    var grid = contentEl.querySelector('.centinela-tienda__grid');
    if (!grid) return;
    var pag = contentEl.querySelector('.centinela-tienda__pagination');
    if (pag) {
      pag.classList.add('centinela-tienda__pagination--brand-deferred');
      pag.setAttribute('hidden', '');
    }
    contentEl.setAttribute('data-brand-infinite-active', '1');
    contentEl.setAttribute('data-brand-pages-total', String(pagesTotal));
    contentEl.setAttribute('data-brand-pages-loaded', '1');
    contentEl.setAttribute('data-brand-infinite-cap', String(Math.min(pagesTotal, TIENDA_BRAND_INFINITE_MAX_PAGES)));
    var status = document.createElement('p');
    status.className = 'centinela-tienda__infinite-status';
    status.setAttribute('aria-live', 'polite');
    status.textContent = 'Desplázate para cargar más productos';
    var sent = document.createElement('div');
    sent.className = 'centinela-tienda__infinite-sentinel';
    sent.setAttribute('aria-hidden', 'true');
    grid.after(status);
    status.after(sent);
    var loadingMore = false;
    var ordenarEl = contentEl.getAttribute('data-ordenar') || 'relevancia';
    brandScrollObserver = new IntersectionObserver(function (entries) {
      if (!entries[0].isIntersecting || loadingMore) return;
      var loaded = parseInt(contentEl.getAttribute('data-brand-pages-loaded'), 10) || 1;
      var total = parseInt(contentEl.getAttribute('data-brand-pages-total'), 10) || 1;
      var cap = parseInt(contentEl.getAttribute('data-brand-infinite-cap'), 10) || total;
      if (loaded >= total) {
        brandScrollObserver.disconnect();
        brandScrollObserver = null;
        sent.remove();
        status.textContent = 'Has visto todos los productos de esta marca.';
        return;
      }
      if (loaded >= cap && loaded < total) {
        brandScrollObserver.disconnect();
        brandScrollObserver = null;
        sent.remove();
        status.textContent = 'Usa la paginación inferior para ver el resto de productos.';
        if (pag) {
          pag.removeAttribute('hidden');
          pag.classList.remove('centinela-tienda__pagination--brand-deferred');
        }
        return;
      }
      loadingMore = true;
      status.textContent = 'Cargando más productos…';
      var categoria = contentEl.getAttribute('data-categoria') || '';
      var catPath = contentEl.getAttribute('data-cat-path') || '';
      var minP = contentEl.getAttribute('data-min-price') || '';
      var maxP = contentEl.getAttribute('data-max-price') || '';
      fetchProductos(categoria, loaded + 1, ordenarEl, catPath, marca, minP, maxP, {})
        .then(function (data) {
          var html = data && data.html != null ? data.html : '';
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var newGrid = doc.querySelector('.centinela-tienda__grid');
          if (newGrid && grid) {
            newGrid.querySelectorAll('.centinela-tienda__card').forEach(function (card) {
              grid.appendChild(document.importNode(card, true));
            });
          }
          var nextLoaded = loaded + 1;
          contentEl.setAttribute('data-brand-pages-loaded', String(nextLoaded));
          fillCardPricesFromQuickView();
          var capN = parseInt(contentEl.getAttribute('data-brand-infinite-cap'), 10) || total;
          if (nextLoaded >= total) {
            status.textContent = 'Has visto todos los productos de esta marca.';
            brandScrollObserver.disconnect();
            brandScrollObserver = null;
            sent.remove();
          } else if (nextLoaded >= capN) {
            status.textContent = 'Usa la paginación inferior para ver el resto de productos.';
            brandScrollObserver.disconnect();
            brandScrollObserver = null;
            sent.remove();
            if (pag) {
              pag.removeAttribute('hidden');
              pag.classList.remove('centinela-tienda__pagination--brand-deferred');
            }
          } else {
            status.textContent = 'Desplázate para cargar más productos';
          }
        })
        .catch(function () {
          status.textContent = 'No se pudieron cargar más productos. Usa el menú de categorías o recarga.';
        })
        .finally(function () {
          loadingMore = false;
        });
    }, { root: null, rootMargin: '160px', threshold: 0 });
    brandScrollObserver.observe(sent);
  }

  function setSidebarActive(categoriaId) {
    if (!sidebarEl) return;
    var links = sidebarEl.querySelectorAll('.centinela-tienda__cat-link');
    links.forEach(function (a) {
      var id = (a.getAttribute('data-categoria-id') || '').trim();
      if (id === (categoriaId || '')) {
        a.classList.add('centinela-tienda__cat-link--active');
        var details = a.closest('.centinela-tienda__cat-details');
        while (details) {
          details.setAttribute('open', '');
          details = details.parentElement && details.parentElement.closest('.centinela-tienda__cat-details');
        }
      } else {
        a.classList.remove('centinela-tienda__cat-link--active');
      }
    });
  }

  function loadAndShow(categoria, catPath, pagina, marca, minPrice, maxPrice, opts) {
    opts = opts || {};
    teardownBrandInfiniteScroll();
    if (isMobileFiltersViewport()) {
      closeMobileFilters();
    }
    var ordenar = contentEl.getAttribute('data-ordenar') || 'relevancia';
    var currentMarca = (marca !== undefined && marca !== null) ? marca : (contentEl.getAttribute('data-marca') || '');
    var currentMin = (minPrice !== undefined && minPrice !== null) ? minPrice : (contentEl.getAttribute('data-min-price') || '');
    var currentMax = (maxPrice !== undefined && maxPrice !== null) ? maxPrice : (contentEl.getAttribute('data-max-price') || '');
    var currentCategoria = (categoria !== undefined && categoria !== null) ? categoria : (contentEl.getAttribute('data-categoria') || '');
    updateMobileFiltersCount({
      categoria: currentCategoria,
      marca: currentMarca,
      minPrice: currentMin,
      maxPrice: currentMax
    });
    contentEl.setAttribute('aria-busy', 'true');
    contentEl.classList.add('centinela-tienda__content--loading');
    contentEl.classList.remove('centinela-tienda__content--partial');
    showTiendaFilterLoading();

    var progressiveBrand = !!(opts.progressiveBrand && String(currentMarca || '').trim() && String(pagina || '1') === '1' && !String(currentMin || '').trim() && !String(currentMax || '').trim());

    function applyProductosResponse(data, skipHistory) {
      var prevPath = (contentEl.getAttribute('data-cat-path') || '').trim();
      var prevCat = (contentEl.getAttribute('data-categoria') || '').trim();
      var html = data && data.html != null ? data.html : '';
      var resCatPath = (data && data.cat_path != null) ? String(data.cat_path).trim() : String(catPath || '').trim();
      var resPagina = (data && data.pagina != null) ? String(data.pagina) : (pagina || '1');
      var resMarca = (data && data.marca != null) ? data.marca : currentMarca;
      var resMin = (data && data.min_price != null) ? data.min_price : currentMin;
      var resMax = (data && data.max_price != null) ? data.max_price : currentMax;
      var nextCat = (categoria !== undefined && categoria !== null) ? String(categoria).trim() : prevCat;
      var catContextChanged = (prevPath !== resCatPath) || (prevCat !== nextCat);
      setContent(html, resCatPath, resPagina, resMarca, resMin, resMax);
      if (!skipHistory) {
        updateState(categoria, resCatPath, resPagina, resMarca, resMin, resMax, !!opts.initialHydration);
      }
      setSidebarActive(categoria);
      if (catContextChanged) {
        loadMarcas(categoria, resCatPath);
      }
      syncPriceRangeActive(resMin, resMax);
      var pagesTotal = (data && data.paginas != null) ? parseInt(data.paginas, 10) : 0;
      if (!skipHistory && resMarca && String(resPagina) === '1' && pagesTotal > 1) {
        setupBrandInfiniteScroll(pagesTotal);
      }
    }

    var fetchChain;
    if (progressiveBrand) {
      fetchChain = fetchProductos(categoria, 1, ordenar, catPath, currentMarca, currentMin, currentMax, { quick: true })
        .then(function (q) {
          if (q && q.html && q.partial) {
            applyProductosResponse(q, true);
            contentEl.classList.add('centinela-tienda__content--partial');
            setTiendaFilterLoadingMessage('Completando catálogo de la marca…');
          }
          return fetchProductos(categoria, pagina || 1, ordenar, catPath, currentMarca, currentMin, currentMax, {});
        })
        .catch(function () {
          return fetchProductos(categoria, pagina || 1, ordenar, catPath, currentMarca, currentMin, currentMax, {});
        });
    } else {
      fetchChain = fetchProductos(categoria, pagina || 1, ordenar, catPath, currentMarca, currentMin, currentMax, {});
    }

    fetchChain
      .then(function (data) {
        applyProductosResponse(data, false);
      })
      .catch(function () {
        setContent('<p class="centinela-tienda__empty centinela-tienda__empty--main">Error al cargar productos.</p>', catPath || '', pagina || '1', currentMarca, currentMin, currentMax);
        syncPriceRangeActive(currentMin, currentMax);
      })
      .finally(function () {
        removeTiendaFilterLoading();
        contentEl.removeAttribute('aria-busy');
        contentEl.classList.remove('centinela-tienda__content--loading');
        contentEl.classList.remove('centinela-tienda__content--partial');
        contentEl.removeAttribute('data-centinela-defer-brand');
      });
  }

  function syncBrandSelectValue() {
    if (!brandSelectEl) return;
    var cur = (contentEl.getAttribute('data-marca') || '').trim();
    if (!cur) {
      brandSelectEl.value = '';
      return;
    }
    brandSelectEl.value = cur;
    if (brandSelectEl.value !== cur) {
      for (var i = 0; i < brandSelectEl.options.length; i++) {
        if ((brandSelectEl.options[i].value || '').toLowerCase() === cur.toLowerCase()) {
          brandSelectEl.selectedIndex = i;
          return;
        }
      }
      brandSelectEl.value = '';
    }
  }

  function renderMarcasSelectOptions(marcas) {
    if (!brandSelectEl) return;
    var statusEl = document.getElementById('centinela-tienda-brand-select-status');
    var firstOpt = brandSelectEl.querySelector('option[value=""]');
    var allLabel = firstOpt ? firstOpt.textContent : 'Todas las marcas';
    brandSelectEl.innerHTML = '';
    var optAll = document.createElement('option');
    optAll.value = '';
    optAll.textContent = allLabel;
    brandSelectEl.appendChild(optAll);
    var seen = {};
    (marcas || []).forEach(function (m) {
      var name = String(m || '').trim();
      if (!name || seen[name]) return;
      seen[name] = true;
      var o = document.createElement('option');
      o.value = name;
      o.textContent = name;
      brandSelectEl.appendChild(o);
    });
    syncBrandSelectValue();
    brandSelectEl.removeAttribute('aria-busy');
    brandSelectEl.disabled = false;
    if (statusEl) statusEl.textContent = '';
  }

  function loadMarcas(categoria, catPath) {
    if (!brandSelectEl) return;
    var statusEl = document.getElementById('centinela-tienda-brand-select-status');
    var existingOptionsCount = brandSelectEl.options ? brandSelectEl.options.length : 0;
    brandSelectEl.setAttribute('aria-busy', 'true');
    brandSelectEl.disabled = true;
    if (statusEl) statusEl.textContent = 'Cargando marcas…';
    var params = {};
    if (categoria) params.categoria = categoria;
    if (catPath) params.cat_path = catPath;
    var q = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    fetch('/wp-json/centinela/v1/tienda-marcas?' + q, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var marcas = (data && data.marcas) ? data.marcas : [];
        // Si la API responde vacío de forma transitoria, no destruir las marcas ya cargadas en el select.
        if ((!Array.isArray(marcas) || marcas.length === 0) && existingOptionsCount > 1) {
          brandSelectEl.removeAttribute('aria-busy');
          brandSelectEl.disabled = false;
          if (statusEl) statusEl.textContent = '';
          return;
        }
        renderMarcasSelectOptions(marcas);
      })
      .catch(function () {
        brandSelectEl.removeAttribute('aria-busy');
        brandSelectEl.disabled = false;
        if (statusEl) statusEl.textContent = 'No se pudieron cargar las marcas.';
      });
  }

  function bindPagination() {
    var pagLinks = contentEl.querySelectorAll('.centinela-tienda__page-link[data-pagina]');
    pagLinks.forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var pagina = contentEl.getAttribute('data-pagina') || '1';
        var categoria = contentEl.getAttribute('data-categoria') || '';
        var catPath = contentEl.getAttribute('data-cat-path') || '';
        var marca = contentEl.getAttribute('data-marca') || '';
        var minP = contentEl.getAttribute('data-min-price') || '';
        var maxP = contentEl.getAttribute('data-max-price') || '';
        loadAndShow(categoria, catPath, a.getAttribute('data-pagina'), marca, minP, maxP);
      });
    });
  }

  if (sidebarEl) {
    sidebarEl.addEventListener('click', function (e) {
      var catLink = e.target.closest('.centinela-tienda__cat-link[data-categoria-id]');
      if (catLink) {
        e.preventDefault();
        var categoriaId = (catLink.getAttribute('data-categoria-id') || '').trim();
        var catPath = (catLink.getAttribute('data-cat-path') || '').trim();
        var marca = contentEl.getAttribute('data-marca') || '';
        var minP = contentEl.getAttribute('data-min-price') || '';
        var maxP = contentEl.getAttribute('data-max-price') || '';
        loadAndShow(categoriaId, catPath, '1', marca, minP, maxP);
        return;
      }
    });
  }

  // Clic en rango de precio (lista tipo CozyCorner)
  function handlePriceFilterActivation(e) {
    var priceLink = e.target.closest('.centinela-tienda__price-range-link');
    if (!priceLink) return;
    e.preventDefault();
    e.stopPropagation();
    var categoria = contentEl.getAttribute('data-categoria') || '';
    var catPath = contentEl.getAttribute('data-cat-path') || '';
    var marca = contentEl.getAttribute('data-marca') || '';
    var minP = (priceLink.getAttribute('data-min-price') || '').trim();
    var maxP = (priceLink.getAttribute('data-max-price') || '').trim();
    loadAndShow(categoria, catPath, '1', marca, minP, maxP);
  }
  container.addEventListener('click', handlePriceFilterActivation);
  // Mobile Safari a veces deja el primer tap en "focus"; touchend fuerza activación al primer toque.
  container.addEventListener('touchend', handlePriceFilterActivation, { passive: false });

  bindPagination();

  syncPriceRangeActive();
  updateMobileFiltersCount();

  // Precios del listado = mismo que detalle/vista rápida (vía endpoint producto-quick-view)
  fillCardPricesFromQuickView();

  // Abrir los bloques desplegables que contienen la categoría activa
  (function openActiveCategoryDetails() {
    if (!sidebarEl) return;
    var active = sidebarEl.querySelector('.centinela-tienda__cat-link--active');
    if (!active) return;
    var details = active.closest('.centinela-tienda__cat-details');
    while (details) {
      details.setAttribute('open', '');
      details = details.parentElement && details.parentElement.closest('.centinela-tienda__cat-details');
    }
  })();

  window.addEventListener('popstate', function (e) {
    if (e.state && e.state.tienda) {
      loadAndShow(e.state.categoria || '', e.state.cat_path || '', e.state.pagina || '1', e.state.marca || '', e.state.min_price || '', e.state.max_price || '');
    }
  });

  if (mobileFiltersToggleEl) {
    mobileFiltersToggleEl.addEventListener('click', function () {
      if (mobileSidebarEl && mobileSidebarEl.classList.contains('is-mobile-open')) {
        closeMobileFilters();
      } else {
        openMobileFilters();
      }
    });
  }

  if (mobileFiltersCloseEl) {
    mobileFiltersCloseEl.addEventListener('click', function () {
      closeMobileFilters();
    });
  }

  if (mobileFiltersBackdropEl) {
    mobileFiltersBackdropEl.addEventListener('click', function () {
      closeMobileFilters();
    });
  }

  if (brandSelectEl) {
    brandSelectEl.addEventListener('change', function () {
      var categoria = contentEl.getAttribute('data-categoria') || '';
      var catPath = contentEl.getAttribute('data-cat-path') || '';
      var marca = (brandSelectEl.value || '').trim();
      var minP = contentEl.getAttribute('data-min-price') || '';
      var maxP = contentEl.getAttribute('data-max-price') || '';
      loadAndShow(categoria, catPath, '1', marca, minP, maxP, { progressiveBrand: true });
    });
    syncBrandSelectValue();
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeMobileFilters();
    }
  });

  window.addEventListener('resize', function () {
    if (!isMobileFiltersViewport()) {
      closeMobileFilters();
    }
  });

  // SSR diferido: /tienda/?marca=… entrega esqueleto y aquí se pide quick + catálogo completo (TTFB bajo).
  (function bootstrapDeferredBrandSsr() {
    if (contentEl.getAttribute('data-centinela-defer-brand') !== '1') return;
    var categoria = contentEl.getAttribute('data-categoria') || '';
    var catPath = contentEl.getAttribute('data-cat-path') || '';
    var marca = contentEl.getAttribute('data-marca') || '';
    var minP = contentEl.getAttribute('data-min-price') || '';
    var maxP = contentEl.getAttribute('data-max-price') || '';
    var pagina = contentEl.getAttribute('data-pagina') || '1';
    loadAndShow(categoria, catPath, pagina, marca, minP, maxP, { progressiveBrand: true, initialHydration: true });
  })();
})();
