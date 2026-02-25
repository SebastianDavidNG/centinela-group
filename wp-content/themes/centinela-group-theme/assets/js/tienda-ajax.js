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
  if (!contentEl) return;

  var tiendaBase = (document.querySelector('link[rel="canonical"]') && document.querySelector('link[rel="canonical"]').href) ? document.querySelector('link[rel="canonical"]').href : (window.location.origin + '/tienda/');
  if (window.location.pathname.indexOf('/tienda') !== -1) {
    tiendaBase = window.location.origin + window.location.pathname.replace(/\/?$/, '/');
  }

  function getRestUrl(params) {
    var q = Object.keys(params).filter(function (k) { return params[k] !== '' && params[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    return '/wp-json/centinela/v1/tienda-productos?' + q;
  }

  function fetchProductos(categoria, pagina, ordenar, catPath, marca, minPrice, maxPrice) {
    var params = { pagina: String(pagina), ordenar: ordenar || 'relevancia' };
    if (categoria) params.categoria = categoria;
    if (catPath) params.cat_path = catPath;
    if (marca) params.marca = marca;
    if (minPrice) params.min_price = minPrice;
    if (maxPrice) params.max_price = maxPrice;
    var url = getRestUrl(params);
    return fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); });
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

  function updateState(categoria, catPath, pagina, marca, minPrice, maxPrice) {
    var url = buildTiendaUrl(catPath || '', pagina, marca || '', minPrice || '', maxPrice || '');
    var state = { tienda: true, categoria: categoria || '', cat_path: catPath || '', pagina: pagina || '1', marca: marca || '', min_price: minPrice || '', max_price: maxPrice || '' };
    window.history.pushState(state, '', url);
    contentEl.setAttribute('data-categoria', categoria || '');
    contentEl.setAttribute('data-cat-path', catPath || '');
    contentEl.setAttribute('data-pagina', pagina || '1');
    contentEl.setAttribute('data-marca', marca || '');
    contentEl.setAttribute('data-min-price', minPrice || '');
    contentEl.setAttribute('data-max-price', maxPrice || '');
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

  function loadAndShow(categoria, catPath, pagina, marca, minPrice, maxPrice) {
    var ordenar = contentEl.getAttribute('data-ordenar') || 'relevancia';
    var currentMarca = (marca !== undefined && marca !== null) ? marca : (contentEl.getAttribute('data-marca') || '');
    var currentMin = (minPrice !== undefined && minPrice !== null) ? minPrice : (contentEl.getAttribute('data-min-price') || '');
    var currentMax = (maxPrice !== undefined && maxPrice !== null) ? maxPrice : (contentEl.getAttribute('data-max-price') || '');
    contentEl.setAttribute('aria-busy', 'true');
    contentEl.classList.add('centinela-tienda__content--loading');
    fetchProductos(categoria, pagina || 1, ordenar, catPath, currentMarca, currentMin, currentMax)
      .then(function (data) {
        var html = data && data.html != null ? data.html : '';
        var resCatPath = (data && data.cat_path != null) ? data.cat_path : (catPath || '');
        var resPagina = (data && data.pagina != null) ? String(data.pagina) : (pagina || '1');
        var resMarca = (data && data.marca != null) ? data.marca : currentMarca;
        var resMin = (data && data.min_price != null) ? data.min_price : currentMin;
        var resMax = (data && data.max_price != null) ? data.max_price : currentMax;
        setContent(html, resCatPath, resPagina, resMarca, resMin, resMax);
        updateState(categoria, resCatPath, resPagina, resMarca, resMin, resMax);
        setSidebarActive(categoria);
        if (data.marcas && Array.isArray(data.marcas) && data.marcas.length > 0) {
          renderMarcasList(data.marcas);
        } else if (data.marcas && Array.isArray(data.marcas) && data.marcas.length === 0 && !resMin && !resMax) {
          // Al volver a "Todos" (precio) a veces la respuesta trae marcas vacías; recuperar lista desde tienda-marcas.
          loadMarcas(categoria, resCatPath);
        } else if (data.marcas && Array.isArray(data.marcas)) {
          renderMarcasList(data.marcas);
        } else {
          loadMarcas(categoria, resCatPath);
        }
        syncPriceRangeActive(resMin, resMax);
      })
      .catch(function () {
        setContent('<p class="centinela-tienda__empty centinela-tienda__empty--main">Error al cargar productos.</p>', catPath || '', pagina || '1', currentMarca, currentMin, currentMax);
        syncPriceRangeActive(currentMin, currentMax);
      })
      .finally(function () {
        contentEl.removeAttribute('aria-busy');
        contentEl.classList.remove('centinela-tienda__content--loading');
      });
  }

  function renderMarcasList(marcas) {
    var listEl = document.getElementById('centinela-tienda-marcas-list');
    if (!listEl) return;
    // Siempre que haya productos en el grid, las marcas del filtro se reconstruyen
    // a partir de las tarjetas visibles (independiente de lo que devuelva el REST).
    var cardsContainer = contentEl;
    if (cardsContainer) {
      var cardBrandLinks = cardsContainer.querySelectorAll('.centinela-tienda__card-marca-link');
      var fromDom = [];
      cardBrandLinks.forEach(function (link) {
        var name = (link.textContent || '').trim();
        if (name && fromDom.indexOf(name) === -1) {
          fromDom.push(name);
        }
      });
      if (fromDom.length > 0) {
        marcas = fromDom;
      }
    }
    var currentMarca = contentEl.getAttribute('data-marca') || '';
    var catPath = contentEl.getAttribute('data-cat-path') || '';
    var minP = contentEl.getAttribute('data-min-price') || '';
    var maxP = contentEl.getAttribute('data-max-price') || '';
    var base = window.location.origin + '/tienda/';
    if (catPath && catPath.trim()) base = window.location.origin + '/tienda/' + catPath.replace(/\/+$/, '') + '/';
    var html = '';
    var allUrl = base;
    var allQs = [];
    if (minP) allQs.push('min_price=' + encodeURIComponent(minP));
    if (maxP) allQs.push('max_price=' + encodeURIComponent(maxP));
    if (allQs.length) allUrl += (allUrl.indexOf('?') !== -1 ? '&' : '?') + allQs.join('&');
    html += '<a href="' + allUrl + '" class="centinela-tienda__marca-link' + (!currentMarca ? ' centinela-tienda__marca-link--active' : '') + '" data-marca="">Todas las marcas</a>';
    (marcas || []).forEach(function (m) {
      var url = base;
      var qs = [];
      if (m) qs.push('marca=' + encodeURIComponent(m));
      if (minP) qs.push('min_price=' + encodeURIComponent(minP));
      if (maxP) qs.push('max_price=' + encodeURIComponent(maxP));
      if (qs.length) url += (url.indexOf('?') !== -1 ? '&' : '?') + qs.join('&');
      var active = (currentMarca === m) ? ' centinela-tienda__marca-link--active' : '';
      html += '<a href="' + url + '" class="centinela-tienda__marca-link' + active + '" data-marca="' + (m ? String(m).replace(/"/g, '&quot;') : '') + '">' + (m || '') + '</a>';
    });
    if (!marcas || marcas.length === 0) {
      html += '<span class="centinela-tienda__marcas-empty">No hay marcas en esta categoría</span>';
    }
    listEl.innerHTML = html;
    listEl.querySelectorAll('.centinela-tienda__marca-link').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var minP = contentEl.getAttribute('data-min-price') || '';
        var maxP = contentEl.getAttribute('data-max-price') || '';
        loadAndShow(contentEl.getAttribute('data-categoria') || '', contentEl.getAttribute('data-cat-path') || '', '1', a.getAttribute('data-marca') || '', minP, maxP);
      });
    });
  }

  function loadMarcas(categoria, catPath) {
    var listEl = document.getElementById('centinela-tienda-marcas-list');
    if (!listEl) return;
    var params = {};
    if (categoria) params.categoria = categoria;
    if (catPath) params.cat_path = catPath;
    var q = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    listEl.innerHTML = '<span class="centinela-tienda__marcas-loading">Cargando…</span>';
    fetch('/wp-json/centinela/v1/tienda-marcas?' + q, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var marcas = (data && data.marcas) ? data.marcas : [];
        renderMarcasList(marcas);
      })
      .catch(function () {
        listEl.innerHTML = '<span class="centinela-tienda__marcas-empty">No se pudieron cargar las marcas</span>';
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
      var marcaLink = e.target.closest('.centinela-tienda__marca-link');
      if (marcaLink) {
        e.preventDefault();
        var categoria = contentEl.getAttribute('data-categoria') || '';
        var catPath = contentEl.getAttribute('data-cat-path') || '';
        var marca = (marcaLink.getAttribute('data-marca') || '').trim();
        var minP = contentEl.getAttribute('data-min-price') || '';
        var maxP = contentEl.getAttribute('data-max-price') || '';
        loadAndShow(categoria, catPath, '1', marca, minP, maxP);
      }
    });
  }

  // Clic en rango de precio (lista tipo CozyCorner)
  container.addEventListener('click', function (e) {
    var priceLink = e.target.closest('.centinela-tienda__price-range-link');
    if (!priceLink) return;
    e.preventDefault();
    var categoria = contentEl.getAttribute('data-categoria') || '';
    var catPath = contentEl.getAttribute('data-cat-path') || '';
    var marca = contentEl.getAttribute('data-marca') || '';
    var minP = (priceLink.getAttribute('data-min-price') || '').trim();
    var maxP = (priceLink.getAttribute('data-max-price') || '').trim();
    loadAndShow(categoria, catPath, '1', marca, minP, maxP);
  });

  bindPagination();

  syncPriceRangeActive();

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
})();
