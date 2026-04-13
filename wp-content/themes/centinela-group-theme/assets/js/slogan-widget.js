(function () {
  'use strict';

  function parseIntSafe(value, fallback) {
    var n = parseInt(value, 10);
    return isFinite(n) ? n : fallback;
  }

  function initSlogan(node) {
    if (!node || node.getAttribute('data-slogan-init') === '1') return;

    var fullText = (node.getAttribute('data-slogan-text') || '').trim();
    if (!fullText) return;

    var typedEl = node.querySelector('.centinela-slogan__typed');
    if (!typedEl) return;

    var typeSpeed = Math.max(20, parseIntSafe(node.getAttribute('data-typing-speed'), 75));
    var deleteSpeed = Math.max(20, parseIntSafe(node.getAttribute('data-deleting-speed'), 45));
    var pauseMs = Math.max(300, parseIntSafe(node.getAttribute('data-pause-ms'), 1300));
    var loop = node.getAttribute('data-loop') === '1';
    var prefersReducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    node.setAttribute('data-slogan-init', '1');

    if (prefersReducedMotion) {
      typedEl.textContent = fullText;
      return;
    }

    var index = 0;
    var deleting = false;
    var timer = null;

    function tick() {
      if (!deleting) {
        index = Math.min(fullText.length, index + 1);
        typedEl.textContent = fullText.slice(0, index);

        if (index >= fullText.length) {
          if (!loop) return;
          deleting = true;
          timer = window.setTimeout(tick, pauseMs);
          return;
        }
        timer = window.setTimeout(tick, typeSpeed);
        return;
      }

      index = Math.max(0, index - 1);
      typedEl.textContent = fullText.slice(0, index);

      if (index <= 0) {
        deleting = false;
        timer = window.setTimeout(tick, 260);
        return;
      }
      timer = window.setTimeout(tick, deleteSpeed);
    }

    typedEl.textContent = '';
    tick();

  }

  function initAll(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    root.querySelectorAll('.centinela-slogan').forEach(initSlogan);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(document); });
  } else {
    initAll(document);
  }

  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/centinela_slogan.default', function ($scope) {
      var el = $scope && $scope[0] ? $scope[0] : null;
      if (el) initAll(el);
    });
  }
})();
