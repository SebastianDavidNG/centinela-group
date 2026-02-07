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

  function parsePrice(str) {
    if (str === '' || str == null) return 0;
    var s = String(str).trim().replace(/\s*COP\s*$/i, '').replace(/\./g, '').replace(',', '.');
    var n = parseFloat(s.replace(/[^\d.-]/g, ''));
    return isNaN(n) ? 0 : n;
  }

  function formatPrice(num) {
    if (num === 0) return '0 COP';
    var parts = num.toFixed(0).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return parts.join(',') + ' COP';
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
    var existing = items.filter(function (i) { return String(i.id) === id; })[0];
    if (existing) {
      existing.qty += qty;
      if (item.title) existing.title = item.title;
      if (item.image) existing.image = item.image;
      if (item.price !== undefined && item.price !== '') existing.price = item.price;
    } else {
      items.push({
        id: id,
        qty: qty,
        title: item.title || '',
        image: item.image || '',
        price: item.price || ''
      });
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
      if (subtotalEl) subtotalEl.textContent = '0 COP';
      return;
    }

    if (contentEl) contentEl.style.display = 'block';
    if (emptyEl) emptyEl.style.display = 'none';
    if (emptyCta) emptyCta.style.display = 'none';

    var cartUrl = dropdown.getAttribute('data-cart-url') || '';
    var checkoutUrl = dropdown.getAttribute('data-checkout-url') || cartUrl;
    var tiendaUrl = dropdown.getAttribute('data-tienda-url') || '';
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
  window.centinelaAddToCart = function (item) {
    addItemToCart(item);
    updateCartCountDisplay();
  };
  window.centinelaGetCartItems = getCartItems;
  window.centinelaSaveCartItems = saveCartItems;
  window.centinelaRemoveItemFromCart = removeItemFromCart;
  window.centinelaUpdateItemQty = updateItemQty;
  window.centinelaParsePrice = parsePrice;
  window.centinelaFormatPrice = formatPrice;
})();
