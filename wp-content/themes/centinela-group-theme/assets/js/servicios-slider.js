/**
 * Servicios Slider – Inicialización Swiper (widget Elementor)
 * Desktop: 3 slides visibles, loop. Mobile: 1 slide, arrastre.
 * Paginación por "páginas" (p. ej. 6 ítems = 2 puntos en desktop).
 * Iguala altura de todos los slides por JS.
 */
(function () {
  'use strict';

  if (typeof window.Swiper === 'undefined') return;

  function equalizeSlideHeights(container) {
    var wrapper = container && container.querySelector('.swiper-wrapper');
    var slides = wrapper && wrapper.querySelectorAll('.centinela-servicios__slide');
    if (!wrapper || !slides || slides.length === 0) return;

    wrapper.style.alignItems = 'flex-start';
    wrapper.style.height = '';

    var maxHeight = 0;
    for (var i = 0; i < slides.length; i++) {
      slides[i].style.height = '';
      var h = slides[i].offsetHeight;
      if (h > maxHeight) maxHeight = h;
    }

    if (maxHeight > 0) {
      wrapper.style.height = maxHeight + 'px';
      wrapper.style.alignItems = 'stretch';
      for (var j = 0; j < slides.length; j++) {
        slides[j].style.height = '100%';
      }
    }
  }

  function renderPagePagination(swiper, section, paginationEl) {
    if (!paginationEl || !section) return;
    var itemsCount = parseInt(section.dataset.itemsCount, 10) || 0;
    if (itemsCount <= 0) return;

    var slidesPerGroup = typeof swiper.params.slidesPerGroup === 'number' ? swiper.params.slidesPerGroup : 1;
    var totalPages = Math.ceil(itemsCount / slidesPerGroup);
    if (totalPages <= 0) return;

    var currentPage = Math.min(Math.floor(swiper.realIndex / slidesPerGroup), totalPages - 1);
    currentPage = Math.max(0, currentPage);

    var html = '';
    for (var i = 0; i < totalPages; i++) {
      var activeClass = (i === currentPage) ? ' swiper-pagination-bullet-active' : '';
      html += '<span class="swiper-pagination-bullet' + activeClass + '" role="button" tabindex="0" data-page="' + i + '" aria-label="Ir a página ' + (i + 1) + '"></span>';
    }
    paginationEl.innerHTML = html;
  }

  function bindPagePaginationClick(swiper, section, paginationEl) {
    if (!paginationEl || !section) return;
    paginationEl.addEventListener('click', function (e) {
      var bullet = e.target.closest('.swiper-pagination-bullet');
      if (!bullet || !bullet.dataset.page) return;
      var page = parseInt(bullet.dataset.page, 10);
      var slidesPerGroup = typeof swiper.params.slidesPerGroup === 'number' ? swiper.params.slidesPerGroup : 1;
      swiper.slideToLoop(page * slidesPerGroup);
    });
  }

  var containers = document.querySelectorAll('.centinela-servicios__swiper');
  containers.forEach(function (container) {
    var section = container.closest('.centinela-servicios');
    var showArrows = section && section.dataset.arrows === '1';
    var showPagination = section && section.dataset.pagination === '1';
    var autoplay = section && section.dataset.autoplay === '1';
    var autoplayDelay = section && section.dataset.autoplayDelay ? parseInt(section.dataset.autoplayDelay, 10) : 5000;
    var paginationEl = section ? section.querySelector('.centinela-servicios__pagination') : null;
    var nextEl = section ? section.querySelector('.centinela-servicios__next') : null;
    var prevEl = section ? section.querySelector('.centinela-servicios__prev') : null;

    var config = {
      loop: true,
      speed: 500,
      slidesPerView: 1,
      slidesPerGroup: 1,
      spaceBetween: 24,
      breakpoints: {
        768: {
          slidesPerView: 2,
          slidesPerGroup: 2,
          spaceBetween: 40,
        },
        1025: {
          slidesPerView: 3,
          slidesPerGroup: 3,
          spaceBetween: 40,
        },
      },
      a11y: {
        prevSlideMessage: 'Servicios anteriores',
        nextSlideMessage: 'Servicios siguientes',
        paginationBulletMessage: 'Ir a página {{index}}',
      },
      on: {
        init: function () {
          var self = this;
          setTimeout(function () {
            equalizeSlideHeights(self.el);
            if (showPagination && paginationEl) {
              renderPagePagination(self, section, paginationEl);
              bindPagePaginationClick(self, section, paginationEl);
            }
          }, 50);
        },
        slideChangeTransitionEnd: function () {
          if (showPagination && paginationEl) {
            renderPagePagination(this, section, paginationEl);
          }
        },
      },
    };

    if (showPagination && paginationEl) {
      config.pagination = false;
    }

    if (showArrows && nextEl && prevEl) {
      config.navigation = { nextEl: nextEl, prevEl: prevEl };
    }

    if (autoplay) {
      config.autoplay = {
        delay: autoplayDelay,
        disableOnInteraction: false,
      };
    }

    var swiper = new window.Swiper(container, config);

    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        equalizeSlideHeights(container);
        if (showPagination && paginationEl && swiper) {
          renderPagePagination(swiper, section, paginationEl);
        }
      }, 150);
    });
  });
})();
