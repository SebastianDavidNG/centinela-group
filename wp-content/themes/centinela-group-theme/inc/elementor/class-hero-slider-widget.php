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
		</section>
		<?php
	}
}
