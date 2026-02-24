<?php
/**
 * Elementor Widget: Bloque imagen y texto (Content Block) – Centinela Group
 * Repeater de ítems; con un ítem = bloque estático, con varios = slider (Swiper).
 * Opciones: reverse, reverse en móvil, color botón/hover, mostrar/ocultar flechas del slider.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Content_Block_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_content_block';
	}

	public function get_title() {
		return __( 'Bloque imagen y texto', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-image-box';
	}

	public function get_categories() {
		return array( 'centinela' );
	}

	public function get_keywords() {
		return array( 'imagen', 'texto', 'bloque', 'contenido', 'slider', 'centinela', 'link' );
	}

	public function get_script_depends() {
		return array( 'swiper', 'centinela-content-block-slider' );
	}

	public function get_style_depends() {
		return array( 'swiper', 'centinela-theme-scss' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Contenido', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'image',
			array(
				'label'   => __( 'Imagen', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::MEDIA,
				'default' => array(),
			)
		);

		$repeater->add_control(
			'title',
			array(
				'label'       => __( 'Título', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'placeholder' => __( 'Título del bloque', 'centinela-group-theme' ),
			)
		);

		$repeater->add_control(
			'description',
			array(
				'label'       => __( 'Descripción', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => '',
				'rows'        => 4,
				'placeholder' => __( 'Texto descriptivo.', 'centinela-group-theme' ),
			)
		);

		$repeater->add_control(
			'link_url',
			array(
				'label'       => __( 'Enlace (URL)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://…',
				'default'     => array( 'url' => '', 'is_external' => false, 'nofollow' => false ),
			)
		);

		$repeater->add_control(
			'link_text',
			array(
				'label'       => __( 'Texto del enlace', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Saber más', 'centinela-group-theme' ),
				'label_block' => true,
				'placeholder' => __( 'Ver más, Saber más', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'items',
			array(
				'label'       => __( 'Ítems', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'title'       => '',
						'description' => '',
						'link_text'   => __( 'Saber más', 'centinela-group-theme' ),
					),
				),
				'title_field' => '{{{ title }}}',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Diseño', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'reverse',
			array(
				'label'        => __( 'Invertir orden (imagen a la derecha)', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'reverse_on_mobile',
			array(
				'label'        => __( 'Invertir orden en móvil', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label'   => __( 'Color de fondo del botón', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'blue',
				'options' => array(
					'blue'   => __( 'Azul principal (Blue)', 'centinela-group-theme' ),
					'blue_2' => __( 'Azul secundario (Blue 2)', 'centinela-group-theme' ),
					'green'  => __( 'Verde (Green)', 'centinela-group-theme' ),
					'grey'   => __( 'Gris (Grey)', 'centinela-group-theme' ),
					'black'  => __( 'Negro (Black)', 'centinela-group-theme' ),
				),
			)
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label'   => __( 'Color del botón al pasar el mouse (hover)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'green',
				'options' => array(
					'blue'   => __( 'Azul principal (Blue)', 'centinela-group-theme' ),
					'blue_2' => __( 'Azul secundario (Blue 2)', 'centinela-group-theme' ),
					'green'  => __( 'Verde (Green)', 'centinela-group-theme' ),
					'grey'   => __( 'Gris (Grey)', 'centinela-group-theme' ),
					'black'  => __( 'Negro (Black)', 'centinela-group-theme' ),
				),
			)
		);

		$this->add_control(
			'show_arrows',
			array(
				'label'        => __( 'Mostrar flechas del slider', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Solo aplica cuando hay más de un ítem (modo slider).', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'slider_speed',
			array(
				'label'       => __( 'Velocidad de transición (ms)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'default'     => 600,
				'min'         => 200,
				'max'         => 1500,
				'step'        => 100,
				'description' => __( 'Duración de la animación entre slides.', 'centinela-group-theme' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Construye atributos HTML para un enlace a partir de link_url de Elementor.
	 *
	 * @param array $link_url Array con url, is_external, nofollow.
	 * @return string
	 */
	private function get_link_attrs( $link_url ) {
		$url = isset( $link_url['url'] ) ? $link_url['url'] : '';
		if ( empty( $url ) ) {
			return '';
		}
		$parts = array();
		if ( ! empty( $link_url['is_external'] ) ) {
			$parts[] = 'target="_blank"';
			$parts[] = 'rel="noopener noreferrer' . ( ! empty( $link_url['nofollow'] ) ? ' nofollow' : '' ) . '"';
		} elseif ( ! empty( $link_url['nofollow'] ) ) {
			$parts[] = 'rel="nofollow"';
		}
		return implode( ' ', $parts );
	}

	/**
	 * Renderiza un solo ítem (imagen + body) dentro de .centinela-content-block__inner.
	 *
	 * @param array  $item        Ítem del repeater.
	 * @param string $button_color Clase de color del botón.
	 * @param string $hover_color  Clase de color hover.
	 * @param string $arrow_svg   SVG del icono del botón.
	 */
	private function render_item( $item, $button_color, $hover_color, $arrow_svg ) {
		$image_url  = isset( $item['image']['url'] ) ? $item['image']['url'] : '';
		$image_alt  = isset( $item['image']['alt'] ) && $item['image']['alt'] !== '' ? $item['image']['alt'] : ( isset( $item['title'] ) ? $item['title'] : '' );
		$title      = isset( $item['title'] ) ? trim( (string) $item['title'] ) : '';
		$desc       = isset( $item['description'] ) ? trim( (string) $item['description'] ) : '';
		$link_text  = isset( $item['link_text'] ) ? trim( (string) $item['link_text'] ) : __( 'Saber más', 'centinela-group-theme' );
		$link       = isset( $item['link_url']['url'] ) ? $item['link_url']['url'] : '';
		$link_attrs = $this->get_link_attrs( isset( $item['link_url'] ) ? $item['link_url'] : array() );
		?>
		<?php if ( $image_url ) : ?>
			<div class="centinela-content-block__media">
				<?php if ( $link ) : ?>
					<a href="<?php echo esc_url( $link ); ?>" class="centinela-content-block__media-link"<?php echo $link_attrs ? ' ' . $link_attrs : ''; ?>>
						<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" class="centinela-content-block__img" loading="lazy" />
					</a>
				<?php else : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" class="centinela-content-block__img" loading="lazy" />
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="centinela-content-block__body">
			<?php if ( $title !== '' ) : ?>
				<h2 class="centinela-content-block__title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( $desc !== '' ) : ?>
				<div class="centinela-content-block__description"><?php echo wp_kses_post( nl2br( $desc ) ); ?></div>
			<?php endif; ?>
			<?php if ( $link !== '' && $link_text !== '' ) : ?>
				<p class="centinela-content-block__link-wrap">
					<a href="<?php echo esc_url( $link ); ?>" class="centinela-content-block__link centinela-content-block__link--<?php echo esc_attr( $button_color ); ?> centinela-content-block__link--hover-<?php echo esc_attr( $hover_color ); ?>"<?php echo $link_attrs ? ' ' . $link_attrs : ''; ?>><span class="centinela-content-block__link-text"><?php echo esc_html( $link_text ); ?></span><?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items    = isset( $settings['items'] ) && is_array( $settings['items'] ) ? $settings['items'] : array();
		// Compatibilidad: si no hay ítems pero sí campos antiguos (image, title...), construir un ítem.
		if ( empty( $items ) && ( ! empty( $settings['image']['url'] ) || ! empty( $settings['title'] ) || ! empty( $settings['description'] ) ) ) {
			$items = array(
				array(
					'image'      => isset( $settings['image'] ) ? $settings['image'] : array(),
					'title'      => isset( $settings['title'] ) ? $settings['title'] : '',
					'description' => isset( $settings['description'] ) ? $settings['description'] : '',
					'link_url'   => isset( $settings['link_url'] ) ? $settings['link_url'] : array(),
					'link_text'  => isset( $settings['link_text'] ) ? $settings['link_text'] : __( 'Saber más', 'centinela-group-theme' ),
				),
			);
		}
		if ( empty( $items ) ) {
			return;
		}

		$reverse        = ! empty( $settings['reverse'] ) && $settings['reverse'] === 'yes';
		$reverse_mobile = ! empty( $settings['reverse_on_mobile'] ) && $settings['reverse_on_mobile'] === 'yes';
		$button_color   = isset( $settings['button_color'] ) && $settings['button_color'] !== '' ? $settings['button_color'] : 'blue';
		$hover_color    = isset( $settings['button_hover_color'] ) && $settings['button_hover_color'] !== '' ? $settings['button_hover_color'] : 'green';
		$allowed        = array( 'blue', 'blue_2', 'green', 'grey', 'black' );
		if ( ! in_array( $button_color, $allowed, true ) ) {
			$button_color = 'blue';
		}
		if ( ! in_array( $hover_color, $allowed, true ) ) {
			$hover_color = 'green';
		}

		$arrow_svg   = '<svg class="centinela-content-block__link-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
		$is_slider   = count( $items ) > 1;
		$widget_id   = 'centinela-content-block-' . $this->get_id();
		$show_arrows = $is_slider && ! empty( $settings['show_arrows'] ) && $settings['show_arrows'] === 'yes';
		$speed       = isset( $settings['slider_speed'] ) ? absint( $settings['slider_speed'] ) : 600;
		$speed       = max( 200, min( 1500, $speed ) );

		$block_classes = 'centinela-content-block';
		if ( $reverse ) {
			$block_classes .= ' centinela-content-block--reverse';
		}
		if ( $reverse_mobile ) {
			$block_classes .= ' centinela-content-block--reverse-mobile';
		}
		if ( $is_slider ) {
			$block_classes .= ' centinela-content-block--slider';
		}
		?>

		<div class="<?php echo esc_attr( $block_classes ); ?>" id="<?php echo esc_attr( $widget_id ); ?>" data-content-block-id="<?php echo esc_attr( $widget_id ); ?>" data-arrows="<?php echo $show_arrows ? '1' : '0'; ?>" data-speed="<?php echo esc_attr( $speed ); ?>">
			<?php if ( ! $is_slider ) : ?>
				<div class="centinela-content-block__inner">
					<?php $this->render_item( $items[0], $button_color, $hover_color, $arrow_svg ); ?>
				</div>
			<?php else : ?>
				<div class="centinela-content-block__slider-wrap">
					<div class="centinela-content-block__swiper swiper">
						<div class="centinela-content-block__track swiper-wrapper">
							<?php foreach ( $items as $item ) : ?>
								<div class="centinela-content-block__slide swiper-slide">
									<div class="centinela-content-block__inner">
										<?php $this->render_item( $item, $button_color, $hover_color, $arrow_svg ); ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<?php if ( $show_arrows ) : ?>
						<button type="button" class="centinela-content-block__prev swiper-button-prev" aria-label="<?php esc_attr_e( 'Anterior', 'centinela-group-theme' ); ?>"></button>
						<button type="button" class="centinela-content-block__next swiper-button-next" aria-label="<?php esc_attr_e( 'Siguiente', 'centinela-group-theme' ); ?>"></button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
