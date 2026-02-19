<?php
/**
 * Elementor Widget: Hero Slider (Centinela Group)
 * Permite construir el hero slider por página con repeater de slides.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Hero_Slider_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_hero_slider';
	}

	public function get_title() {
		return __( 'Hero Slider', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-slides';
	}

	public function get_categories() {
		return array( 'centinela' );
	}

	public function get_keywords() {
		return array( 'hero', 'slider', 'centinela', 'slides' );
	}

	public function get_script_depends() {
		return array( 'swiper', 'centinela-hero-slider', 'centinela-video-modal' );
	}

	public function get_style_depends() {
		return array( 'swiper', 'centinela-theme-scss' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_slides',
			array(
				'label' => __( 'Slides', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'image',
			array(
				'label'       => __( 'Imagen de fondo', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::MEDIA,
				'default'     => array(),
				'description' => __( 'Recomendado: agregar una imagen de fondo para cada slide. Si no se agrega, se usará un degradado por defecto. El overlay (Rectangle 1642) se muestra siempre sobre la imagen.', 'centinela-group-theme' ),
			)
		);

		$repeater->add_control(
			'title',
			array(
				'label'       => __( 'Título', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Seguridad Inteligente para tu Negocio', 'centinela-group-theme' ),
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'text',
			array(
				'label'   => __( 'Texto', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'Soluciones integrales en videovigilancia, control de acceso, detección de fuego y más.', 'centinela-group-theme' ),
				'rows'    => 3,
			)
		);

		$repeater->add_control(
			'cta_text',
			array(
				'label'   => __( 'Texto botón principal', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Pedir cotización', 'centinela-group-theme' ),
			)
		);

		$repeater->add_control(
			'cta_url',
			array(
				'label'       => __( 'URL botón principal', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => '#pedir-cotizacion',
				'default'     => array( 'url' => '#pedir-cotizacion' ),
			)
		);

		$repeater->add_control(
			'cta_secondary_text',
			array(
				'label'   => __( 'Texto botón secundario', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Cómo funciona', 'centinela-group-theme' ),
			)
		);

		$repeater->add_control(
			'cta_secondary_url',
			array(
				'label'       => __( 'URL botón secundario (o video)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://... o YouTube, Vimeo, .mp4',
				'default'     => array( 'url' => '#como-funciona' ),
				'description' => __( 'Enlace normal o URL de video (YouTube, Vimeo o archivo .mp4). Si es video, se abrirá en un modal.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'slides',
			array(
				'label'       => __( 'Slides', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'title'              => __( 'Seguridad Inteligente para tu Negocio', 'centinela-group-theme' ),
						'text'               => __( 'Soluciones integrales en videovigilancia, control de acceso, detección de fuego y más.', 'centinela-group-theme' ),
						'cta_text'           => __( 'Proteger mi negocio', 'centinela-group-theme' ),
						'cta_url'            => array( 'url' => '#pedir-cotizacion' ),
						'cta_secondary_text' => __( 'Cómo funciona', 'centinela-group-theme' ),
						'cta_secondary_url'  => array( 'url' => '#como-funciona' ),
					),
					array(
						'title'              => __( 'Tecnología y Confianza', 'centinela-group-theme' ),
						'text'               => __( 'Diseñamos e instalamos sistemas de seguridad adaptados a tus necesidades.', 'centinela-group-theme' ),
						'cta_text'           => __( 'Pedir cotización', 'centinela-group-theme' ),
						'cta_url'            => array( 'url' => '#pedir-cotizacion' ),
						'cta_secondary_text' => '',
						'cta_secondary_url'  => array( 'url' => '' ),
					),
				),
				'title_field' => '{{{ title }}}',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_slider',
			array(
				'label' => __( 'Slider', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'        => __( 'Autoplay', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'autoplay_delay',
			array(
				'label'     => __( 'Delay autoplay (ms)', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 5500,
				'min'       => 1000,
				'max'       => 15000,
				'step'      => 500,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);

		$this->add_control(
			'show_arrows',
			array(
				'label'        => __( 'Mostrar flechas', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_pagination',
			array(
				'label'        => __( 'Mostrar puntos', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->end_controls_section();

		// Título y texto: tamaño de fuente por dispositivo (desktop, tablet, mobile).
		$this->start_controls_section(
			'section_typography',
			array(
				'label' => __( 'Título y texto', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_responsive_control(
			'title_font_size',
			array(
				'label'      => __( 'Tamaño del título', 'centinela-group-theme' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array(
					'px'  => array(
						'min'  => 14,
						'max'  => 72,
						'step' => 1,
					),
					'rem' => array(
						'min'  => 1,
						'max'  => 4.5,
						'step' => 0.125,
					),
				),
				'default'    => array(
					'unit' => 'rem',
					'size' => '',
				),
				'description' => __( 'Desktop, tablet y mobile. Si está vacío se usa el valor por defecto del tema.', 'centinela-group-theme' ),
			)
		);

		$this->add_responsive_control(
			'text_font_size',
			array(
				'label'      => __( 'Tamaño del texto', 'centinela-group-theme' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array(
					'px'  => array(
						'min'  => 12,
						'max'  => 28,
						'step' => 1,
					),
					'rem' => array(
						'min'  => 0.75,
						'max'  => 1.75,
						'step' => 0.125,
					),
				),
				'default'    => array(
					'unit' => 'rem',
					'size' => '',
				),
				'description' => __( 'Desktop, tablet y mobile. Si está vacío se usa el valor por defecto del tema.', 'centinela-group-theme' ),
			)
		);

		$this->end_controls_section();

		// Icons Section (Hero Copy – ref. Figma). Line - spacer icons + ítems con imagen, título y Leer más.
		$this->start_controls_section(
			'section_icons',
			array(
				'label' => __( 'Icons Section', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$icons_repeater = new \Elementor\Repeater();
		$icons_repeater->add_control(
			'icon_image',
			array(
				'label'   => __( 'Imagen / Icono', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::MEDIA,
				'default' => array(),
			)
		);
		$icons_repeater->add_control(
			'icon_title',
			array(
				'label'       => __( 'Título', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			)
		);
		$icons_repeater->add_control(
			'icon_link_text',
			array(
				'label'   => __( 'Texto del botón', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Leer más', 'centinela-group-theme' ),
			)
		);
		$icons_repeater->add_control(
			'icon_url',
			array(
				'label'       => __( 'Enlace', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => __( 'Página interna o URL externa', 'centinela-group-theme' ),
				'default'     => array(),
			)
		);

		$this->add_control(
			'icons',
			array(
				'label'       => __( 'Iconos (3 visibles en fila)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $icons_repeater->get_controls(),
				'default'     => array(),
				'title_field' => '{{{ icon_title }}}',
				'description' => __( 'Se muestran 3 iconos en fila centrados, en la parte inferior del slider. La línea separadora (Line - spacer icons) aparece sobre ellos. Imagen: SVG, JPG o PNG.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'icons_text_color',
			array(
				'label'   => __( 'Color del texto', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Obtiene el valor CSS de font-size a partir del control responsive de Elementor.
	 *
	 * @param array  $settings Configuración del widget.
	 * @param string $key      Clave base (ej. 'title_font_size').
	 * @param string $device   '' (mobile), '_tablet', '_desktop'.
	 * @return string Valor CSS (ej. '2.75rem') o vacío si no está definido.
	 */
	private function get_responsive_font_size( $settings, $key, $device = '' ) {
		$full_key = $key . $device;
		$value    = isset( $settings[ $full_key ] ) && is_array( $settings[ $full_key ] ) ? $settings[ $full_key ] : null;
		if ( empty( $value ) || ! isset( $value['size'] ) || $value['size'] === '' ) {
			return '';
		}
		$unit = isset( $value['unit'] ) ? $value['unit'] : 'rem';
		return $value['size'] . $unit;
	}

	/**
	 * Detecta si una URL es de video (YouTube, Vimeo o .mp4).
	 *
	 * @param string $url URL a comprobar.
	 * @return bool
	 */
	private static function is_video_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}
		$url = trim( $url );
		return ( strpos( $url, 'youtube.com' ) !== false || strpos( $url, 'youtu.be' ) !== false
			|| strpos( $url, 'vimeo.com' ) !== false
			|| preg_match( '/\.mp4$/i', $url ) );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$slides   = $settings['slides'];
		if ( empty( $slides ) ) {
			return;
		}

		$autoplay         = $settings['autoplay'] === 'yes';
		$autoplay_delay   = isset( $settings['autoplay_delay'] ) ? (int) $settings['autoplay_delay'] : 5500;
		$show_arrows      = $settings['show_arrows'] === 'yes';
		$show_pagination  = $settings['show_pagination'] === 'yes';
		$widget_id        = 'centinela-hero-' . $this->get_id();
		$icons            = isset( $settings['icons'] ) && is_array( $settings['icons'] ) ? array_slice( $settings['icons'], 0, 3 ) : array();
		$has_icons        = ! empty( $icons );
		$icons_text_color = isset( $settings['icons_text_color'] ) ? $settings['icons_text_color'] : '#FFFFFF';

		// Tamaños de fuente responsivos (título y texto).
		$title_size_mobile  = $this->get_responsive_font_size( $settings, 'title_font_size', '_mobile' );
		$title_size_tablet  = $this->get_responsive_font_size( $settings, 'title_font_size', '_tablet' );
		$title_size_desktop = $this->get_responsive_font_size( $settings, 'title_font_size', '' );
		$text_size_mobile   = $this->get_responsive_font_size( $settings, 'text_font_size', '_mobile' );
		$text_size_tablet   = $this->get_responsive_font_size( $settings, 'text_font_size', '_tablet' );
		$text_size_desktop  = $this->get_responsive_font_size( $settings, 'text_font_size', '' );
		$has_typography     = $title_size_mobile !== '' || $title_size_tablet !== '' || $title_size_desktop !== ''
			|| $text_size_mobile !== '' || $text_size_tablet !== '' || $text_size_desktop !== '';
		if ( $has_typography ) {
			$selector = '.centinela-hero[data-hero-id="' . esc_attr( $widget_id ) . '"]';
			echo '<style id="' . esc_attr( $widget_id ) . '-typography">';
			if ( $title_size_mobile !== '' ) {
				echo $selector . ' .centinela-hero__title { font-size: ' . esc_attr( $title_size_mobile ) . ' !important; }';
			}
			if ( $text_size_mobile !== '' ) {
				echo $selector . ' .centinela-hero__text { font-size: ' . esc_attr( $text_size_mobile ) . ' !important; }';
			}
			if ( $title_size_tablet !== '' || $text_size_tablet !== '' ) {
				echo '@media (min-width: 768px) { ';
				if ( $title_size_tablet !== '' ) {
					echo $selector . ' .centinela-hero__title { font-size: ' . esc_attr( $title_size_tablet ) . ' !important; }';
				}
				if ( $text_size_tablet !== '' ) {
					echo $selector . ' .centinela-hero__text { font-size: ' . esc_attr( $text_size_tablet ) . ' !important; }';
				}
				echo ' }';
			}
			if ( $title_size_desktop !== '' || $text_size_desktop !== '' ) {
				echo '@media (min-width: 1025px) { ';
				if ( $title_size_desktop !== '' ) {
					echo $selector . ' .centinela-hero__title { font-size: ' . esc_attr( $title_size_desktop ) . ' !important; }';
				}
				if ( $text_size_desktop !== '' ) {
					echo $selector . ' .centinela-hero__text { font-size: ' . esc_attr( $text_size_desktop ) . ' !important; }';
				}
				echo ' }';
			}
			echo '</style>';
		}

		$arrow_svg = '<svg class="centinela-hero__cta-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
		$play_svg  = '<svg class="centinela-hero__cta-play-icon" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7L8 5z"/></svg>';
		?>
		<section class="centinela-hero" aria-label="<?php esc_attr_e( 'Slider principal', 'centinela-group-theme' ); ?>" data-hero-id="<?php echo esc_attr( $widget_id ); ?>" data-autoplay="<?php echo $autoplay ? '1' : '0'; ?>" data-autoplay-delay="<?php echo esc_attr( $autoplay_delay ); ?>" data-arrows="<?php echo $show_arrows ? '1' : '0'; ?>" data-pagination="<?php echo $show_pagination ? '1' : '0'; ?>">
			<div class="centinela-hero__swiper swiper">
				<div class="centinela-hero__track swiper-wrapper">
					<?php foreach ( $slides as $slide ) : ?>
						<?php
						$image       = isset( $slide['image']['url'] ) ? $slide['image']['url'] : '';
						$has_bg_image = $image !== '';
						$bg_style    = $has_bg_image ? ' background-image: url(' . esc_url( $image ) . ');' : '';
						$bg_class    = $has_bg_image ? '' : ' centinela-hero__bg--no-image';
						$title       = isset( $slide['title'] ) ? $slide['title'] : '';
						$text        = isset( $slide['text'] ) ? $slide['text'] : '';
						$cta_text    = isset( $slide['cta_text'] ) ? $slide['cta_text'] : '';
						$cta_url     = isset( $slide['cta_url']['url'] ) ? $slide['cta_url']['url'] : '';
						$cta_sec_text = isset( $slide['cta_secondary_text'] ) ? $slide['cta_secondary_text'] : '';
						$cta_sec_url  = isset( $slide['cta_secondary_url']['url'] ) ? $slide['cta_secondary_url']['url'] : '';
						$cta_sec_is_video = self::is_video_url( $cta_sec_url );
						?>
						<div class="centinela-hero__slide swiper-slide">
							<div class="centinela-hero__bg<?php echo esc_attr( $bg_class ); ?>" style="<?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" role="img" aria-label="<?php echo esc_attr( $title ); ?>"></div>
							<div class="centinela-hero__overlay"></div>
							<div class="centinela-hero__inner">
								<div class="centinela-hero__content-area">
									<div class="centinela-hero__content">
										<?php if ( $title !== '' ) : ?>
											<h2 class="centinela-hero__title"><?php echo esc_html( $title ); ?></h2>
										<?php endif; ?>
										<?php if ( $text !== '' ) : ?>
											<p class="centinela-hero__text"><?php echo esc_html( $text ); ?></p>
										<?php endif; ?>
										<div class="centinela-hero__actions">
											<?php if ( $cta_text !== '' && $cta_url !== '' ) : ?>
												<a href="<?php echo esc_url( $cta_url ); ?>" class="centinela-hero__cta centinela-hero__cta--primary"><?php echo esc_html( $cta_text ); ?><?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
											<?php endif; ?>
											<?php if ( ( $cta_sec_text !== '' || $cta_sec_url !== '' ) && $cta_sec_url !== '' ) : ?>
												<?php if ( $cta_sec_is_video ) : ?>
													<a href="#" class="centinela-hero__cta centinela-hero__cta--play" data-video-url="<?php echo esc_attr( $cta_sec_url ); ?>" title="<?php echo esc_attr( $cta_sec_text ?: __( 'Reproducir video', 'centinela-group-theme' ) ); ?>" aria-label="<?php echo esc_attr( $cta_sec_text ?: __( 'Reproducir video', 'centinela-group-theme' ) ); ?>"><?php echo $play_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
												<?php else : ?>
													<a href="<?php echo esc_url( $cta_sec_url ); ?>" class="centinela-hero__cta centinela-hero__cta--play" title="<?php echo esc_attr( $cta_sec_text ); ?>" aria-label="<?php echo esc_attr( $cta_sec_text ); ?>"><?php echo $play_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
												<?php endif; ?>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $show_pagination ) : ?>
					<div class="centinela-hero__pagination swiper-pagination"></div>
				<?php endif; ?>
				<?php if ( $show_arrows ) : ?>
					<button type="button" class="centinela-hero__prev swiper-button-prev" aria-label="<?php esc_attr_e( 'Anterior', 'centinela-group-theme' ); ?>"></button>
					<button type="button" class="centinela-hero__next swiper-button-next" aria-label="<?php esc_attr_e( 'Siguiente', 'centinela-group-theme' ); ?>"></button>
				<?php endif; ?>
			</div>
			<?php if ( $has_icons ) : ?>
				<div class="centinela-hero__icons-fixed">
					<div class="centinela-hero__icons-line-wrap">
						<div class="centinela-hero__icons-line" aria-hidden="true"></div>
					</div>
					<div class="centinela-hero__icons-inner">
						<div class="centinela-hero__icons-section" style="<?php echo esc_attr( $icons_text_color ? '--centinela-icons-text-color:' . $icons_text_color . ';' : '' ); ?>">
							<ul class="centinela-hero__icons-list">
								<?php foreach ( $icons as $idx => $icon ) : ?>
									<?php
									$icon_img  = isset( $icon['icon_image']['url'] ) ? $icon['icon_image']['url'] : '';
									$icon_title = isset( $icon['icon_title'] ) ? $icon['icon_title'] : '';
									$icon_link_text = isset( $icon['icon_link_text'] ) ? $icon['icon_link_text'] : __( 'Leer más', 'centinela-group-theme' );
									$icon_url  = isset( $icon['icon_url']['url'] ) ? $icon['icon_url']['url'] : '';
									?>
									<li class="centinela-hero__icons-item">
										<?php if ( $idx < count( $icons ) - 1 ) : ?>
											<div class="centinela-hero__icons-spacer" aria-hidden="true"></div>
										<?php endif; ?>
										<?php if ( $icon_img !== '' ) : ?>
											<div class="centinela-hero__icons-image">
												<img src="<?php echo esc_url( $icon_img ); ?>" alt="<?php echo esc_attr( $icon_title ); ?>" loading="lazy" />
											</div>
										<?php endif; ?>
										<?php if ( $icon_title !== '' ) : ?>
											<h3 class="centinela-hero__icons-title"><?php echo esc_html( $icon_title ); ?></h3>
										<?php endif; ?>
										<?php
										if ( $icon_link_text !== '' && $icon_url !== '' ) :
											$clip_id = 'centinela-leer-mas-clip-' . $widget_id . '-' . $idx;
											$read_more_svg = '<svg class="centinela-hero__icons-link-icon" width="26" height="26" viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><g clip-path="url(#' . esc_attr( $clip_id ) . ')"><rect width="26" height="26" rx="13" fill="white"/><path d="M13 0C5.82045 0 0 5.82045 0 13C0 20.1796 5.82045 26 13 26C20.1796 26 26 20.1796 26 13C26 5.82045 20.1796 0 13 0ZM13 23.8333C7.01705 23.8333 2.16668 18.9829 2.16668 13C2.16668 7.01705 7.01705 2.16668 13 2.16668C18.9829 2.16668 23.8333 7.01705 23.8333 13C23.8333 18.9829 18.9829 23.8333 13 23.8333Z" fill="white"/><path d="M20.3667 13.648C20.3783 13.6327 20.3902 13.6176 20.4009 13.6016C20.4112 13.5861 20.4202 13.5701 20.4296 13.5543C20.4383 13.5398 20.4474 13.5257 20.4554 13.5107C20.464 13.4946 20.4713 13.478 20.4791 13.4616C20.4864 13.4459 20.4943 13.4306 20.5009 13.4146C20.5075 13.3987 20.5128 13.3824 20.5186 13.3662C20.5248 13.349 20.5314 13.3321 20.5367 13.3145C20.5416 13.2984 20.5452 13.2819 20.5493 13.2656C20.5538 13.2476 20.5589 13.23 20.5625 13.2117C20.5662 13.1928 20.5684 13.1737 20.5711 13.1547C20.5734 13.1388 20.5765 13.1231 20.578 13.1069C20.5851 13.0358 20.5851 12.9642 20.578 12.8931C20.5765 12.877 20.5734 12.8613 20.5711 12.8453C20.5684 12.8263 20.5662 12.8073 20.5625 12.7884C20.5589 12.7701 20.5538 12.7524 20.5493 12.7345C20.5452 12.7181 20.5416 12.7017 20.5367 12.6855C20.5314 12.668 20.5248 12.651 20.5186 12.6338C20.5128 12.6177 20.5075 12.6014 20.5009 12.5854C20.4943 12.5694 20.4865 12.5541 20.4791 12.5385C20.4713 12.522 20.464 12.5055 20.4554 12.4894C20.4474 12.4744 20.4383 12.4602 20.4296 12.4457C20.4202 12.4299 20.4112 12.4139 20.4009 12.3985C20.3902 12.3825 20.3783 12.3674 20.3667 12.352C20.3571 12.3391 20.3482 12.3259 20.3379 12.3134C20.3154 12.2859 20.2916 12.2596 20.2666 12.2345C20.2664 12.2343 20.2663 12.2341 20.2661 12.234L15.9328 7.90067C15.5097 7.47761 14.8238 7.47761 14.4007 7.90067C13.9777 8.32373 13.9777 9.00968 14.4007 9.43274L16.8847 11.9167H6.50006C5.90176 11.9167 5.41675 12.4017 5.41675 13C5.41675 13.5983 5.90176 14.0833 6.50006 14.0833H16.8847L14.4007 16.5673C13.9777 16.9904 13.9777 17.6763 14.4007 18.0994C14.8238 18.5224 15.5097 18.5224 15.9328 18.0994L20.2661 13.7661C20.2663 13.7659 20.2664 13.7656 20.2666 13.7655C20.2916 13.7404 20.3154 13.7141 20.3379 13.6867C20.3482 13.6741 20.3571 13.6609 20.3667 13.648Z" fill="black"/></g><defs><clipPath id="' . esc_attr( $clip_id ) . '"><rect width="26" height="26" rx="13" fill="white"/></clipPath></defs></svg>';
											?>
											<a href="<?php echo esc_url( $icon_url ); ?>" class="centinela-hero__icons-link"><?php echo $read_more_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="centinela-hero__icons-link-text"><?php echo esc_html( $icon_link_text ); ?></span></a>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}
}
