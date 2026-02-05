/**
 * Centinela Group Theme - Script principal
 * Menú móvil, buscador overlay (estilo WiseGuard), accesibilidad
 */
(function () {
  'use strict';

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
      if (href && href.indexOf('#') !== -1) {
        e.preventDefault();
        closeMobileMenu();
        var id = href.split('#')[1];
        var target = id ? document.getElementById(id) : null;
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
          window.location.hash = href;
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
})();
