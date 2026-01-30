/**
 * Centinela Group Theme - Script principal
 * Menú móvil, buscador overlay (estilo WiseGuard), accesibilidad
 */
(function () {
  'use strict';

  var menuToggle = document.getElementById('menu-toggle');
  var mobileMenu = document.getElementById('primary-menu-mobile');

  if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', function () {
      var expanded = menuToggle.getAttribute('aria-expanded') === 'true';
      menuToggle.setAttribute('aria-expanded', !expanded);
      mobileMenu.classList.toggle('is-open', !expanded);
      mobileMenu.setAttribute('aria-hidden', expanded);
      if (expanded) {
        mobileMenu.setAttribute('hidden', '');
      } else {
        mobileMenu.removeAttribute('hidden');
      }
    });
  }

  // Overlay de búsqueda (abrir/cerrar)
  var searchToggle = document.getElementById('centinela-search-toggle');
  var searchOverlay = document.getElementById('centinela-search-overlay');
  var searchClose = document.getElementById('centinela-search-close');
  var searchField = document.getElementById('centinela-search-field');

  function openSearch() {
    if (!searchOverlay) return;
    searchOverlay.setAttribute('aria-hidden', 'false');
    searchOverlay.removeAttribute('hidden');
    if (searchToggle) searchToggle.setAttribute('aria-expanded', 'true');
    if (searchField) {
      searchField.focus();
    }
  }

  function closeSearch() {
    if (!searchOverlay) return;
    searchOverlay.setAttribute('aria-hidden', 'true');
    searchOverlay.setAttribute('hidden', '');
    if (searchToggle) searchToggle.setAttribute('aria-expanded', 'false');
    if (searchToggle) searchToggle.focus();
  }

  if (searchToggle && searchOverlay) {
    searchToggle.addEventListener('click', openSearch);
  }
  if (searchClose && searchOverlay) {
    searchClose.addEventListener('click', closeSearch);
  }
  if (searchOverlay) {
    searchOverlay.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeSearch();
    });
  }
})();
