/**
 * Bloque imagen y texto – Slider (Swiper) cuando hay más de un ítem
 * Transición suave entre slides; flechas opcionales.
 */
(function () {
  'use strict';

  if (typeof window.Swiper === 'undefined') return;

  var blocks = document.querySelectorAll('.centinela-content-block--slider .centinela-content-block__swiper');
  blocks.forEach(function (swiperEl) {
    var block = swiperEl.closest('.centinela-content-block');
    if (!block) return;

    var showArrows = block.dataset.arrows === '1';
    var speed = block.dataset.speed ? parseInt(block.dataset.speed, 10) : 600;
    var prevEl = block.querySelector('.centinela-content-block__prev');
    var nextEl = block.querySelector('.centinela-content-block__next');

    var config = {
      loop: true,
      speed: Math.max(200, Math.min(1500, speed || 600)),
      slidesPerView: 1,
      slidesPerGroup: 1,
      spaceBetween: 0,
      effect: 'slide',
      allowTouchMove: true,
      a11y: {
        prevSlideMessage: 'Bloque anterior',
        nextSlideMessage: 'Bloque siguiente',
      },
    };

    if (showArrows && prevEl && nextEl) {
      config.navigation = { nextEl: nextEl, prevEl: prevEl };
    }

    new window.Swiper(swiperEl, config);
  });
})();
