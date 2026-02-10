/**
 * Página Finalizar compra: resumen del pedido desde localStorage (centinela_cart_items).
 * Muestra lista de productos, subtotal y mensaje cuando el carrito tiene items.
 * Si el carrito está vacío, muestra mensaje y enlace a la tienda.
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
    var emptyEl = document.getElementById('centinela-checkout-empty');
    var contentEl = document.getElementById('centinela-checkout-content');
    var listEl = document.getElementById('centinela-checkout-list');
    var subtotalEl = document.getElementById('centinela-checkout-subtotal');
    if (!emptyEl || !contentEl || !listEl) return;

    var items = getItems();
    if (items.length === 0) {
      emptyEl.style.display = 'block';
      contentEl.style.display = 'none';
      return;
    }

    emptyEl.style.display = 'none';
    contentEl.style.display = 'block';

    var subtotalNum = 0;
    var html = '';
    items.forEach(function (item) {
      var qty = Math.max(1, parseInt(item.qty, 10) || 1);
      var priceNum = parsePrice(item.price);
      var lineTotal = priceNum * qty;
      subtotalNum += lineTotal;
      var title = item.title ? escapeHtml(item.title) : ('Producto #' + escapeHtml(item.id));
      var imgHtml = item.image
        ? '<img src="' + escapeHtml(item.image) + '" alt="" loading="lazy" class="centinela-checkout__thumb-img" />'
        : '<span class="centinela-checkout__thumb-placeholder"></span>';
      var productUrlAttr = productUrl(item.id);
      html += '<div class="centinela-checkout__row">';
      html += '<a href="' + escapeHtml(productUrlAttr) + '" class="centinela-checkout__product">';
      html += '<span class="centinela-checkout__thumb">' + imgHtml + '</span>';
      html += '<span class="centinela-checkout__name">' + title + '</span>';
      html += '</a>';
      html += '<span class="centinela-checkout__qty">' + qty + ' &times; ' + formatPrice(priceNum) + '</span>';
      html += '<span class="centinela-checkout__total">' + formatPrice(lineTotal) + '</span>';
      html += '</div>';
    });
    listEl.innerHTML = html;
    if (subtotalEl) subtotalEl.textContent = formatPrice(subtotalNum);
  }

  // Validación y envío del formulario de checkout
  var requiredIds = ['centinela-checkout-nombre', 'centinela-checkout-email', 'centinela-checkout-telefono', 'centinela-checkout-direccion', 'centinela-checkout-ciudad', 'centinela-checkout-departamento'];

  function showFieldError(id, message) {
    var input = document.getElementById(id);
    var errorEl = document.getElementById(id + '-error');
    if (input && errorEl) {
      errorEl.textContent = message || '';
      input.classList.toggle('centinela-checkout-form__input--error', !!message);
    }
  }

  function validateForm() {
    var valid = true;
    requiredIds.forEach(function (id) {
      var input = document.getElementById(id);
      var errorEl = input && document.getElementById(id + '-error');
      if (!input || !errorEl) return;
      var val = (input.value || '').trim();
      var msg = '';
      if (!val) {
        msg = 'Este campo es obligatorio.';
        valid = false;
      } else if (id === 'centinela-checkout-email') {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!re.test(val)) {
          msg = 'Introduzca un correo válido.';
          valid = false;
        }
      }
      showFieldError(id, msg);
    });
    return valid;
  }

  function clearErrors() {
    requiredIds.forEach(function (id) {
      showFieldError(id, '');
    });
  }

  function initForm() {
    var form = document.getElementById('centinela-checkout-form');
    var paymentInput = document.getElementById('centinela-checkout-metodo-pago');
    if (!form) return;

    requiredIds.forEach(function (id) {
      var input = document.getElementById(id);
      if (input) {
        input.addEventListener('blur', function () {
          var val = (input.value || '').trim();
          if (val && id === 'centinela-checkout-email') {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            showFieldError(id, re.test(val) ? '' : 'Introduzca un correo válido.');
          } else {
            showFieldError(id, val ? '' : 'Este campo es obligatorio.');
          }
        });
        input.addEventListener('input', function () {
          showFieldError(id, '');
        });
      }
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearErrors();
      if (!validateForm()) {
        var firstError = document.querySelector('.centinela-checkout-form__input--error');
        if (firstError) firstError.focus();
        return;
      }
      var metodoPago = (paymentInput && paymentInput.value) ? paymentInput.value.trim() : '';
      if (!metodoPago) {
        var section = document.getElementById('centinela-checkout-payment-section');
        if (section) {
          var placeholder = section.querySelector('.centinela-checkout-form__payment-placeholder');
          if (placeholder) {
            placeholder.setAttribute('role', 'alert');
            placeholder.style.color = '#c00';
          }
        }
        if (typeof window.centinelaCheckoutPaymentRequired === 'function') {
          window.centinelaCheckoutPaymentRequired();
        } else {
          alert('Para confirmar el pedido debe seleccionar un método de pago. Las opciones de pago se mostrarán aquí cuando estén configuradas.');
        }
        return;
      }
      // Con método de pago: enviar pedido. La integración de pago puede definir window.centinelaSubmitOrder(data).
      var formData = new FormData(form);
      var data = { items: getItems(), paymentMethod: metodoPago };
      formData.forEach(function (value, key) {
        data[key] = value;
      });
      if (typeof window.centinelaSubmitOrder === 'function') {
        window.centinelaSubmitOrder(data);
      } else {
        console.log('Checkout data (centinelaSubmitOrder no definido):', data);
        alert('Formulario validado. Cuando conectes el método de pago, usa window.centinelaSubmitOrder(data) para enviar el pedido.');
      }
    });
  }

  function onReady() {
    render();
    initForm();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }

  // Para que el método de pago (futuro) pueda registrarse: establecer valor y quitar placeholder de error
  window.centinelaSetPaymentMethod = function (methodId) {
    var paymentInput = document.getElementById('centinela-checkout-metodo-pago');
    var section = document.getElementById('centinela-checkout-payment-section');
    if (paymentInput) paymentInput.value = methodId || '';
    if (section) {
      var placeholder = section.querySelector('.centinela-checkout-form__payment-placeholder');
      if (placeholder) {
        placeholder.removeAttribute('role');
        placeholder.style.color = '';
      }
    }
  };
})();
