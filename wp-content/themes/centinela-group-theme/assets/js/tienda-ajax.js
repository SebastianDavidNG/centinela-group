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

  function fetchProductos(categoria, pagina, ordenar, catPath) {
    var params = { pagina: String(pagina), ordenar: ordenar || 'relevancia' };
    if (categoria) params.categoria = categoria;
    if (catPath) params.cat_path = catPath;
    var url = getRestUrl(params);
    return fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); });
  }

  function setContent(html, catPath, pagina) {
    contentEl.innerHTML = html || '';
    if (catPath !== undefined) contentEl.setAttribute('data-cat-path', catPath || '');
    if (pagina !== undefined) contentEl.setAttribute('data-pagina', String(pagina));
    bindPagination();
  }

  function buildTiendaUrl(catPath, pagina) {
    var base = window.location.origin + '/tienda/';
    if (catPath && catPath.trim()) base = window.location.origin + '/tienda/' + catPath.replace(/\/+$/, '') + '/';
    if (pagina && parseInt(pagina, 10) > 1) return base + '?pag=' + String(pagina);
    return base;
  }

  function updateState(categoria, catPath, pagina) {
    var url = buildTiendaUrl(catPath || '', pagina);
    var state = { tienda: true, categoria: categoria || '', cat_path: catPath || '', pagina: pagina || '1' };
    window.history.pushState(state, '', url);
    contentEl.setAttribute('data-categoria', categoria || '');
    contentEl.setAttribute('data-cat-path', catPath || '');
    contentEl.setAttribute('data-pagina', pagina || '1');
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

  function loadAndShow(categoria, catPath, pagina) {
    var ordenar = contentEl.getAttribute('data-ordenar') || 'relevancia';
    contentEl.setAttribute('aria-busy', 'true');
    fetchProductos(categoria, pagina || 1, ordenar, catPath)
      .then(function (data) {
        var html = data && data.html != null ? data.html : '';
        var resCatPath = (data && data.cat_path != null) ? data.cat_path : (catPath || '');
        var resPagina = (data && data.pagina != null) ? String(data.pagina) : (pagina || '1');
        setContent(html, resCatPath, resPagina);
        updateState(categoria, resCatPath, resPagina);
        setSidebarActive(categoria);
      })
      .catch(function () {
        setContent('<p class="centinela-tienda__empty centinela-tienda__empty--main">Error al cargar productos.</p>', catPath || '', pagina || '1');
      })
      .finally(function () {
        contentEl.removeAttribute('aria-busy');
      });
  }

  function bindPagination() {
    var pagLinks = contentEl.querySelectorAll('.centinela-tienda__page-link[data-pagina]');
    pagLinks.forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var pagina = a.getAttribute('data-pagina');
        var categoria = contentEl.getAttribute('data-categoria') || '';
        var catPath = contentEl.getAttribute('data-cat-path') || '';
        loadAndShow(categoria, catPath, pagina);
      });
    });
  }

  if (sidebarEl) {
    sidebarEl.addEventListener('click', function (e) {
      var link = e.target.closest('.centinela-tienda__cat-link[data-categoria-id]');
      if (!link) return;
      e.preventDefault();
      var categoriaId = (link.getAttribute('data-categoria-id') || '').trim();
      var catPath = (link.getAttribute('data-cat-path') || '').trim();
      loadAndShow(categoriaId, catPath, '1');
    });
  }

  bindPagination();

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
      loadAndShow(e.state.categoria || '', e.state.cat_path || '', e.state.pagina || '1');
    }
  });
})();
