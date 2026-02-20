<?php
/**
 * Elementor Widget: Servicios – Section Slider (Centinela Group)
 * Diseño Servicios - section - slider del Figma. Repeater: icono/imagen, título, descripción, imagen inferior.
 * Desktop: 3 ítems visibles, loop infinito. Mobile: 1 ítem, arrastre.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Servicios_Slider_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_servicios_slider';
	}

	public function get_title() {
		return __( 'Servicios Slider', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-slides';
	}

	public function get_categories() {
		return array( 'centinela' );
	}

	public function get_keywords() {
		return array( 'servicios', 'slider', 'centinela', 'cards', 'figma' );
	}

	public function get_script_depends() {
		return array( 'swiper', 'centinela-servicios-slider' );
	}

	public function get_style_depends() {
		return array( 'swiper', 'centinela-theme-scss' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_items',
			array(
				'label' => __( 'Servicios', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'icon_image',
			array(
				'label'   => __( 'Icono o imagen superior', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::MEDIA,
				'default' => array(),
				'description' => __( 'Imagen o icono (WordPress o librerías). Se mostrará en las dimensiones del Figma.', 'centinela-group-theme' ),
			)
		);

		$repeater->add_control(
			'title',
			array(
				'label'       => __( 'Título', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Servicio', 'centinela-group-theme' ),
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'description',
			array(
				'label'   => __( 'Descripción', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'Descripción del servicio.', 'centinela-group-theme' ),
				'rows'    => 3,
			)
		);

		$repeater->add_control(
			'image_bottom',
			array(
				'label'   => __( 'Imagen inferior', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::MEDIA,
				'default' => array(),
				'description' => __( 'Imagen en la parte inferior de la tarjeta (como en el Figma).', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'items',
			array(
				'label'       => __( 'Items', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'title'       => __( 'Videovigilancia', 'centinela-group-theme' ),
						'description' => __( 'Soluciones en cámaras y monitoreo.', 'centinela-group-theme' ),
					),
					array(
						'title'       => __( 'Control de acceso', 'centinela-group-theme' ),
						'description' => __( 'Sistemas de control de acceso.', 'centinela-group-theme' ),
					),
					array(
						'title'       => __( 'Detección de fuego', 'centinela-group-theme' ),
						'description' => __( 'Alarmas y detección de incendios.', 'centinela-group-theme' ),
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

		$this->add_control(
			'autoplay',
			array(
				'label'        => __( 'Automático', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'description'  => __( 'Avanzar slides automáticamente.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'autoplay_delay',
			array(
				'label'     => __( 'Intervalo (ms)', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 5000,
				'min'       => 1000,
				'max'       => 15000,
				'step'      => 500,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items   = isset( $settings['items'] ) && is_array( $settings['items'] ) ? $settings['items'] : array();
		if ( empty( $items ) ) {
			return;
		}

		$widget_id       = 'centinela-servicios-' . $this->get_id();
		$show_arrows     = $settings['show_arrows'] === 'yes';
		$show_pagination = $settings['show_pagination'] === 'yes';
		$autoplay        = ! empty( $settings['autoplay'] ) && $settings['autoplay'] === 'yes';
		$autoplay_delay  = isset( $settings['autoplay_delay'] ) ? absint( $settings['autoplay_delay'] ) : 5000;
		?>
		<section class="centinela-servicios" aria-label="<?php esc_attr_e( 'Servicios', 'centinela-group-theme' ); ?>" data-servicios-id="<?php echo esc_attr( $widget_id ); ?>" data-arrows="<?php echo $show_arrows ? '1' : '0'; ?>" data-pagination="<?php echo $show_pagination ? '1' : '0'; ?>" data-autoplay="<?php echo $autoplay ? '1' : '0'; ?>" data-autoplay-delay="<?php echo esc_attr( $autoplay_delay ); ?>" data-items-count="<?php echo esc_attr( count( $items ) ); ?>">
			<div class="centinela-servicios__inner">
				<div class="centinela-servicios__swiper swiper">
					<ul class="centinela-servicios__track swiper-wrapper">
						<?php foreach ( $items as $item ) : ?>
							<?php
							$icon_url   = isset( $item['icon_image']['url'] ) ? $item['icon_image']['url'] : '';
							$title      = isset( $item['title'] ) ? $item['title'] : '';
							$desc       = isset( $item['description'] ) ? $item['description'] : '';
							$bottom_url = isset( $item['image_bottom']['url'] ) ? $item['image_bottom']['url'] : '';
							?>
							<li class="centinela-servicios__slide swiper-slide">
								<div class="centinela-servicios__card">
									<?php if ( $icon_url !== '' ) : ?>
										<div class="centinela-servicios__icon">
											<img src="<?php echo esc_url( $icon_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
										</div>
									<?php endif; ?>
									<?php if ( $title !== '' ) : ?>
										<h3 class="centinela-servicios__title"><?php echo esc_html( $title ); ?></h3>
									<?php endif; ?>
									<?php if ( $desc !== '' ) : ?>
										<div class="centinela-servicios__description"><?php echo wp_kses_post( nl2br( $desc ) ); ?></div>
									<?php endif; ?>
								</div>
								<?php if ( $bottom_url !== '' ) : ?>
									<div class="centinela-servicios__image">
										<img src="<?php echo esc_url( $bottom_url ); ?>" alt="" role="presentation" loading="lazy" />
									</div>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php if ( $show_pagination ) : ?>
					<div class="centinela-servicios__pagination swiper-pagination"></div>
				<?php endif; ?>
				<?php if ( $show_arrows ) : ?>
					<button type="button" class="centinela-servicios__prev swiper-button-prev" aria-label="<?php esc_attr_e( 'Anterior', 'centinela-group-theme' ); ?>"></button>
					<button type="button" class="centinela-servicios__next swiper-button-next" aria-label="<?php esc_attr_e( 'Siguiente', 'centinela-group-theme' ); ?>"></button>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
