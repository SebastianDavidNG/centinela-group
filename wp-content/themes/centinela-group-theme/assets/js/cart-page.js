/**
 * Página Carrito: renderiza el contenido desde localStorage (centinela_cart_items).
 * Estilo CozyCorner: tabla de productos, subtotal, Finalizar compra, Continuar comprando.
 */
(function () {
  'use strict';

  function getItems() {
    return typeof window.centinelaGetCartItems === 'function' ? window.centinelaGetCartItems() : [];
  }

  function parsePrice(str) {
    return typeof window.centinelaParsePrice === 'function' ? window.centinelaParsePrice(str) : 0;
  }

  function formatPrice(num) {
    return typeof window.centinelaFormatPrice === 'function' ? window.centinelaFormatPrice(num) : num + ' COP';
  }

  function removeItem(index) {
    if (typeof window.centinelaRemoveItemFromCart === 'function') window.centinelaRemoveItemFromCart(index);
    if (typeof window.centinelaUpdateCartCount === 'function') window.centinelaUpdateCartCount();
    render();
  }

  function updateQty(index, newQty) {
    if (typeof window.centinelaUpdateItemQty === 'function') window.centinelaUpdateItemQty(index, newQty);
    if (typeof window.centinelaUpdateCartCount === 'function') window.centinelaUpdateCartCount();
    render();
  }

  function escapeHtml(s) {
    if (!s) return '';
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  function productUrl(id) {
    return window.location.origin + '/tienda/producto/' + encodeURIComponent(String(id)) + '/';
  }

  function render() {
    var emptyEl = document.getElementById('centinela-cart-empty');
    var contentEl = document.getElementById('centinela-cart-content');
    var tbody = document.getElementById('centinela-cart-tbody');
    var subtotalEl = document.getElementById('centinela-cart-subtotal');
    if (!emptyEl || !contentEl || !tbody) return;

    var items = getItems();
    if (items.length === 0) {
      emptyEl.style.display = 'block';
      contentEl.style.display = 'none';
      if (subtotalEl) subtotalEl.textContent = '0 COP';
      return;
    }

    emptyEl.style.display = 'none';
    contentEl.style.display = 'block';

    var subtotalNum = 0;
    var rows = '';
    items.forEach(function (item, index) {
      var qty = Math.max(1, parseInt(item.qty, 10) || 1);
      var priceNum = parsePrice(item.price);
      var lineTotal = priceNum * qty;
      subtotalNum += lineTotal;
      var title = item.title ? escapeHtml(item.title) : ('Producto #' + escapeHtml(item.id));
      var imgHtml = item.image
        ? '<img src="' + escapeHtml(item.image) + '" alt="" loading="lazy" class="centinela-cart__thumb-img" />'
        : '<span class="centinela-cart__thumb-placeholder"></span>';
      var productUrlAttr = productUrl(item.id);
      rows += '<tr class="centinela-cart__row" data-index="' + index + '">';
      rows += '<td class="centinela-cart__cell centinela-cart__cell--product">';
      rows += '<a href="' + escapeHtml(productUrlAttr) + '" class="centinela-cart__product-link">';
      rows += '<span class="centinela-cart__thumb">' + imgHtml + '</span>';
      rows += '<span class="centinela-cart__product-name">' + title + '</span>';
      rows += '</a></td>';
      rows += '<td class="centinela-cart__cell centinela-cart__cell--price">' + formatPrice(priceNum) + '</td>';
      rows += '<td class="centinela-cart__cell centinela-cart__cell--qty">';
      rows += '<input type="number" class="centinela-cart__qty-input" min="1" value="' + qty + '" data-index="' + index + '" aria-label="Cantidad" />';
      rows += '</td>';
      rows += '<td class="centinela-cart__cell centinela-cart__cell--subtotal">' + formatPrice(lineTotal) + '</td>';
      rows += '<td class="centinela-cart__cell centinela-cart__cell--remove">';
      rows += '<button type="button" class="centinela-cart__remove" data-index="' + index + '" aria-label="Quitar producto">&times;</button>';
      rows += '</td></tr>';
    });
    tbody.innerHTML = rows;
    if (subtotalEl) subtotalEl.textContent = formatPrice(subtotalNum);

    tbody.querySelectorAll('.centinela-cart__remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idx = parseInt(btn.getAttribute('data-index'), 10);
        removeItem(idx);
      });
    });
    tbody.querySelectorAll('.centinela-cart__qty-input').forEach(function (input) {
      input.addEventListener('change', function () {
        var idx = parseInt(input.getAttribute('data-index'), 10);
        var val = parseInt(input.value, 10) || 1;
        if (val < 1) val = 1;
        input.value = val;
        updateQty(idx, val);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', render);
  } else {
    render();
  }
})();
