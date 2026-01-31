/**
 * Hero Slider – Inicialización Swiper (shortcode + widget Elementor)
 * Soporta varios sliders en página; opciones por data-* del contenedor.
 */
(function () {
  'use strict';

  if (typeof window.Swiper === 'undefined') return;

  var containers = document.querySelectorAll('.centinela-hero__swiper');
  containers.forEach(function (container) {
    var section = container.closest('.centinela-hero');
    var autoplay = section && section.dataset.autoplay === '1';
    var autoplayDelay = section && section.dataset.autoplayDelay ? parseInt(section.dataset.autoplayDelay, 10) : 5500;
    var showArrows = !section || section.dataset.arrows !== '0';
    var showPagination = !section || section.dataset.pagination !== '0';

    var paginationEl = container.querySelector('.centinela-hero__pagination');
    var nextEl = container.querySelector('.centinela-hero__next');
    var prevEl = container.querySelector('.centinela-hero__prev');

    var config = {
      loop: true,
      speed: 600,
      effect: 'slide',
      a11y: {
        prevSlideMessage: 'Slide anterior',
        nextSlideMessage: 'Slide siguiente',
        paginationBulletMessage: 'Ir al slide {{index}}',
      },
    };

    if (autoplay) {
      config.autoplay = { delay: autoplayDelay, disableOnInteraction: false };
    }

    if (showPagination && paginationEl) {
      config.pagination = { el: paginationEl, clickable: true };
    }

    if (showArrows && nextEl && prevEl) {
      config.navigation = { nextEl: nextEl, prevEl: prevEl };
    }

    new window.Swiper(container, config);
  });
})();
