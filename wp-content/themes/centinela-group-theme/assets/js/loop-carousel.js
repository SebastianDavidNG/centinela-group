/**
 * Loop Carousel – Inicialización Swiper (widget Elementor)
 * Carrusel de entradas con breakpoints, paginación por páginas, altura igualada opcional.
 */
(function () {
  'use strict';

  if (typeof window.Swiper === 'undefined') return;

  function equalizeSlideHeights(container) {
    var wrapper = container && container.querySelector('.swiper-wrapper');
    var slides = wrapper && wrapper.querySelectorAll('.centinela-loop-carousel__slide');
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
      if (!bullet || bullet.dataset.page === undefined) return;
      var page = parseInt(bullet.dataset.page, 10);
      var slidesPerGroup = typeof swiper.params.slidesPerGroup === 'number' ? swiper.params.slidesPerGroup : 1;
      swiper.slideToLoop(page * slidesPerGroup);
    });
  }

  var sections = document.querySelectorAll('.centinela-loop-carousel');
  sections.forEach(function (section) {
    var swiperEl = section.querySelector('.centinela-loop-carousel__swiper');
    if (!swiperEl) return;

    var showArrows = section.dataset.arrows === '1';
    var showPagination = section.dataset.pagination === '1';
    var autoplay = section.dataset.autoplay === '1';
    var autoplayDelay = section.dataset.autoplayDelay ? parseInt(section.dataset.autoplayDelay, 10) : 5000;
    var speed = section.dataset.speed ? parseInt(section.dataset.speed, 10) : 500;
    var loop = section.dataset.loop === '1';
    var spaceBetween = section.dataset.spaceBetween ? parseInt(section.dataset.spaceBetween, 10) : 24;
    var slidesPerView = section.dataset.slidesPerView ? parseInt(section.dataset.slidesPerView, 10) : 3;
    var slidesPerViewTablet = section.dataset.slidesPerViewTablet ? parseInt(section.dataset.slidesPerViewTablet, 10) : 2;
    var slidesPerViewMobile = section.dataset.slidesPerViewMobile ? parseInt(section.dataset.slidesPerViewMobile, 10) : 1;
    var slidesPerGroup = section.dataset.slidesPerGroup ? parseInt(section.dataset.slidesPerGroup, 10) : 1;
    var equalHeight = section.dataset.equalHeight === '1';

    var paginationEl = section.querySelector('.centinela-loop-carousel__pagination');
    var nextEl = section.querySelector('.centinela-loop-carousel__next');
    var prevEl = section.querySelector('.centinela-loop-carousel__prev');

    var config = {
      loop: loop,
      speed: Math.max(200, Math.min(1500, speed || 500)),
      slidesPerView: slidesPerViewMobile,
      slidesPerGroup: slidesPerGroup,
      spaceBetween: spaceBetween,
      breakpoints: {
        768: {
          slidesPerView: slidesPerViewTablet,
          slidesPerGroup: Math.min(slidesPerGroup, slidesPerViewTablet),
        },
        1025: {
          slidesPerView: slidesPerView,
          slidesPerGroup: Math.min(slidesPerGroup, slidesPerView),
        },
      },
      a11y: {
        prevSlideMessage: 'Anterior',
        nextSlideMessage: 'Siguiente',
        paginationBulletMessage: 'Ir a página {{index}}',
      },
      on: {},
    };

    if (equalHeight) {
      config.on.init = function () {
        var self = this;
        setTimeout(function () {
          equalizeSlideHeights(self.el);
          if (showPagination && paginationEl) {
            renderPagePagination(self, section, paginationEl);
            bindPagePaginationClick(self, section, paginationEl);
          }
        }, 50);
      };
      config.on.slideChangeTransitionEnd = function () {
        if (showPagination && paginationEl) {
          renderPagePagination(this, section, paginationEl);
        }
      };
    } else if (showPagination && paginationEl) {
      config.on.init = function () {
        var self = this;
        setTimeout(function () {
          renderPagePagination(self, section, paginationEl);
          bindPagePaginationClick(self, section, paginationEl);
        }, 50);
      };
      config.on.slideChangeTransitionEnd = function () {
        if (showPagination && paginationEl) {
          renderPagePagination(this, section, paginationEl);
        }
      };
    }

    if (showPagination && paginationEl) {
      config.pagination = false;
    } else if (showPagination && paginationEl) {
      config.pagination = { el: paginationEl, clickable: true };
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

    var swiper = new window.Swiper(swiperEl, config);

    if (equalHeight) {
      var resizeTimer;
      window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
          equalizeSlideHeights(swiperEl);
          if (showPagination && paginationEl && swiper) {
            renderPagePagination(swiper, section, paginationEl);
          }
        }, 150);
      });
    }
  });
})();
