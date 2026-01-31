<?php
/**
 * Hero Slider – Estilo WiseGuard / Figma
 * Shortcode [centinela_hero_slider]. Slides configurables por filtro centinela_hero_slides.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detecta si una URL es de video (YouTube, Vimeo o .mp4).
 *
 * @param string $url URL a comprobar.
 * @return bool
 */
function centinela_hero_is_video_url( $url ) {
	if ( empty( $url ) || ! is_string( $url ) ) {
		return false;
	}
	$url = trim( $url );
	return ( strpos( $url, 'youtube.com' ) !== false || strpos( $url, 'youtu.be' ) !== false
		|| strpos( $url, 'vimeo.com' ) !== false
		|| preg_match( '/\.mp4$/i', $url ) );
}

/**
 * Obtener slides del hero (filtro para personalizar).
 *
 * @return array[] Cada slide: image, image_alt, title, text, cta_text, cta_url, cta_secondary_text, cta_secondary_url
 */
function centinela_hero_get_slides() {
	$defaults = array(
		array(
			'image'                => '',
			'image_alt'            => '',
			'title'                => __( 'Seguridad Inteligente para tu Negocio', 'centinela-group-theme' ),
			'text'                 => __( 'Soluciones integrales en videovigilancia, control de acceso, detección de fuego y más.', 'centinela-group-theme' ),
			'cta_text'             => __( 'Proteger mi negocio', 'centinela-group-theme' ),
			'cta_url'              => '#pedir-cotizacion',
			'cta_secondary_text'   => __( 'Cómo funciona', 'centinela-group-theme' ),
			'cta_secondary_url'    => '#como-funciona',
		),
		array(
			'image'                => '',
			'image_alt'            => '',
			'title'                => __( 'Tecnología y Confianza', 'centinela-group-theme' ),
			'text'                 => __( 'Diseñamos e instalamos sistemas de seguridad adaptados a tus necesidades.', 'centinela-group-theme' ),
			'cta_text'             => __( 'Pedir cotización', 'centinela-group-theme' ),
			'cta_url'              => '#pedir-cotizacion',
			'cta_secondary_text'   => '',
			'cta_secondary_url'    => '',
		),
	);
	return apply_filters( 'centinela_hero_slides', $defaults );
}

/**
 * Shortcode [centinela_hero_slider]
 */
function centinela_hero_slider_shortcode() {
	$slides = centinela_hero_get_slides();
	if ( empty( $slides ) ) {
		return '';
	}

	centinela_hero_enqueue_assets();

	ob_start();
	?>
	<section class="centinela-hero" aria-label="<?php esc_attr_e( 'Slider principal', 'centinela-group-theme' ); ?>" data-autoplay="0" data-autoplay-delay="5500" data-arrows="1" data-pagination="0">
		<div class="centinela-hero__swiper swiper">
			<div class="centinela-hero__track swiper-wrapper">
				<?php foreach ( $slides as $index => $slide ) : ?>
					<?php
					$has_bg_image = ! empty( $slide['image'] );
					$bg_style     = $has_bg_image ? ' background-image: url(' . esc_url( $slide['image'] ) . ');' : '';
					$bg_class     = $has_bg_image ? '' : ' centinela-hero__bg--no-image';
					?>
					<div class="centinela-hero__slide swiper-slide">
						<div class="centinela-hero__bg<?php echo esc_attr( $bg_class ); ?>" style="<?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" role="img" aria-label="<?php echo esc_attr( $slide['image_alt'] ?: $slide['title'] ); ?>"></div>
						<div class="centinela-hero__overlay"></div>
						<div class="centinela-hero__inner">
							<div class="centinela-hero__content">
								<?php if ( ! empty( $slide['title'] ) ) : ?>
									<h2 class="centinela-hero__title"><?php echo esc_html( $slide['title'] ); ?></h2>
								<?php endif; ?>
								<?php if ( ! empty( $slide['text'] ) ) : ?>
									<p class="centinela-hero__text"><?php echo esc_html( $slide['text'] ); ?></p>
								<?php endif; ?>
								<div class="centinela-hero__actions">
									<?php if ( ! empty( $slide['cta_text'] ) && ! empty( $slide['cta_url'] ) ) : ?>
										<a href="<?php echo esc_url( $slide['cta_url'] ); ?>" class="centinela-hero__cta centinela-hero__cta--primary"><?php echo esc_html( $slide['cta_text'] ); ?><svg class="centinela-hero__cta-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
									<?php endif; ?>
									<?php
									$sec_url = isset( $slide['cta_secondary_url'] ) ? $slide['cta_secondary_url'] : '';
									$sec_text = isset( $slide['cta_secondary_text'] ) ? $slide['cta_secondary_text'] : '';
									$sec_is_video = centinela_hero_is_video_url( $sec_url );
									$play_svg = '<svg class="centinela-hero__cta-play-icon" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7L8 5z"/></svg>';
									if ( $sec_url !== '' ) :
										if ( $sec_is_video ) :
											?>
											<a href="#" class="centinela-hero__cta centinela-hero__cta--play" data-video-url="<?php echo esc_attr( $sec_url ); ?>" title="<?php echo esc_attr( $sec_text ?: __( 'Reproducir video', 'centinela-group-theme' ) ); ?>" aria-label="<?php echo esc_attr( $sec_text ?: __( 'Reproducir video', 'centinela-group-theme' ) ); ?>"><?php echo $play_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
										<?php else : ?>
											<a href="<?php echo esc_url( $sec_url ); ?>" class="centinela-hero__cta centinela-hero__cta--play" title="<?php echo esc_attr( $sec_text ); ?>" aria-label="<?php echo esc_attr( $sec_text ); ?>"><?php echo $play_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="centinela-hero__prev swiper-button-prev" aria-label="<?php esc_attr_e( 'Anterior', 'centinela-group-theme' ); ?>"></button>
			<button type="button" class="centinela-hero__next swiper-button-next" aria-label="<?php esc_attr_e( 'Siguiente', 'centinela-group-theme' ); ?>"></button>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'centinela_hero_slider', 'centinela_hero_slider_shortcode' );

/**
 * Encolar Swiper y script del hero solo cuando se usa el shortcode.
 */
function centinela_hero_enqueue_assets() {
	wp_enqueue_style(
		'swiper',
		'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
		array(),
		'11'
	);
	wp_enqueue_script(
		'swiper',
		'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
		array(),
		'11',
		true
	);
	wp_enqueue_script(
		'centinela-hero-slider',
		CENTINELA_THEME_URI . '/assets/js/hero-slider.js',
		array( 'swiper' ),
		CENTINELA_THEME_VERSION,
		true
	);
	wp_enqueue_script( 'centinela-video-modal' );
}
