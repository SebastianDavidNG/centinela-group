<?php
/**
 * Elementor Widget: Image Box – Centinela Group
 * Caja cuadrada con imagen de fondo, overlay (rgba 16,120,165 0.2), título centrado y enlace en todo el elemento.
 * Hover: overlay transparente con transición elegante; el texto siempre delante.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Image_Box_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_image_box';
	}

	public function get_title() {
		return __( 'Image Box', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-image-box';
	}

	public function get_categories() {
		return array( 'centinela', 'basic' );
	}

	public function get_keywords() {
		return array( 'image', 'box', 'imagen', 'caja', 'overlay', 'centinela', 'link' );
	}

	public function get_style_depends() {
		return array( 'centinela-theme-scss' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Contenido', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'image',
			array(
				'label'   => __( 'Imagen de fondo', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::MEDIA,
				'default' => array(),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Título', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Título', 'centinela-group-theme' ),
				'label_block' => true,
				'placeholder' => __( 'Escribe el título', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'link',
			array(
				'label'       => __( 'Enlace (todo el elemento)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://…',
				'default'     => array( 'url' => '', 'is_external' => false, 'nofollow' => false ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Estilo', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'overlay_color',
			array(
				'label'     => __( 'Color del overlay', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'rgba(16, 120, 165, 0.2)',
				'selectors' => array(
					'{{WRAPPER}} .centinela-image-box__overlay' => 'background: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Color del título', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .centinela-image-box__title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .centinela-image-box__title',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$image   = isset( $settings['image'] ) && ! empty( $settings['image']['url'] ) ? $settings['image'] : null;
		$title   = isset( $settings['title'] ) ? $settings['title'] : '';
		$link    = isset( $settings['link']['url'] ) && $settings['link']['url'] !== '' ? $settings['link'] : null;

		$link_attrs = '';
		if ( $link ) {
			$this->add_link_attributes( 'link', $link );
			$link_attrs = $this->get_render_attribute_string( 'link' );
		}

		$bg_style = '';
		if ( $image && ! empty( $image['url'] ) ) {
			$bg_style = ' style="background-image: url(' . esc_url( $image['url'] ) . ');"';
		}
		?>
		<div class="centinela-image-box">
			<?php if ( $link_attrs ) : ?>
				<a class="centinela-image-box__wrap" <?php echo $link_attrs; ?>>
			<?php else : ?>
				<div class="centinela-image-box__wrap">
			<?php endif; ?>
				<div class="centinela-image-box__bg"<?php echo $bg_style; ?>></div>
				<div class="centinela-image-box__overlay"></div>
				<?php if ( $title !== '' ) : ?>
					<h3 class="centinela-image-box__title"><?php echo esc_html( $title ); ?></h3>
				<?php endif; ?>
			<?php if ( $link_attrs ) : ?>
				</a>
			<?php else : ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
