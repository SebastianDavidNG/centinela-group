<?php
/**
 * Elementor Widget: Slogan animado (typewriter) - Centinela Group
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Slogan_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_slogan';
	}

	public function get_title() {
		return __( 'Slogan', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-t-letter';
	}

	public function get_categories() {
		return array( 'centinela', 'basic' );
	}

	public function get_keywords() {
		return array( 'slogan', 'frase', 'typewriter', 'typing', 'centinela' );
	}

	public function get_script_depends() {
		return array( 'centinela-slogan-widget' );
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
			'slogan_text',
			array(
				'label'       => __( 'Texto del slogan', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => __( 'Protegemos lo que más importa', 'centinela-group-theme' ),
				'rows'        => 3,
				'label_block' => true,
			)
		);

		$this->add_control(
			'typing_speed',
			array(
				'label'   => __( 'Velocidad al escribir (ms)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 75,
				'min'     => 20,
				'max'     => 300,
				'step'    => 5,
			)
		);

		$this->add_control(
			'deleting_speed',
			array(
				'label'   => __( 'Velocidad al borrar (ms)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 45,
				'min'     => 20,
				'max'     => 250,
				'step'    => 5,
			)
		);

		$this->add_control(
			'pause_ms',
			array(
				'label'   => __( 'Pausa al completar (ms)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 1300,
				'min'     => 300,
				'max'     => 5000,
				'step'    => 100,
			)
		);

		$this->add_control(
			'loop_animation',
			array(
				'label'        => __( 'Repetir animación', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_cursor',
			array(
				'label'        => __( 'Mostrar cursor', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
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
			'text_color',
			array(
				'label'     => __( 'Color del texto', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .centinela-slogan__typed' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'quotes_color',
			array(
				'label'     => __( 'Color de comillas', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .centinela-slogan__quote' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'cursor_color',
			array(
				'label'     => __( 'Color de cursor', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .centinela-slogan__cursor' => 'color: {{VALUE}};',
				),
				'condition' => array(
					'show_cursor' => 'yes',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .centinela-slogan',
			)
		);

		$this->add_control(
			'show_gradient_bg',
			array(
				'label'        => __( 'Mostrar fondo degradado', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'bg_start_color',
			array(
				'label'     => __( 'Color inicial del degradado', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#021C37',
				'selectors' => array(
					'{{WRAPPER}} .centinela-slogan' => '--centinela-slogan-bg-start: {{VALUE}};',
				),
				'condition' => array(
					'show_gradient_bg' => 'yes',
				),
			)
		);

		$this->add_control(
			'bg_end_color',
			array(
				'label'     => __( 'Color final del degradado', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#229379',
				'selectors' => array(
					'{{WRAPPER}} .centinela-slogan' => '--centinela-slogan-bg-end: {{VALUE}};',
				),
				'condition' => array(
					'show_gradient_bg' => 'yes',
				),
			)
		);

		$this->add_control(
			'bg_angle',
			array(
				'label'      => __( 'Ángulo del degradado (deg)', 'centinela-group-theme' ),
				'type'       => \Elementor\Controls_Manager::NUMBER,
				'default'    => 104,
				'min'        => 0,
				'max'        => 360,
				'step'       => 1,
				'selectors'  => array(
					'{{WRAPPER}} .centinela-slogan' => '--centinela-slogan-bg-angle: {{VALUE}}deg;',
				),
				'condition'  => array(
					'show_gradient_bg' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'bg_padding',
			array(
				'label'      => __( 'Padding del contenedor', 'centinela-group-theme' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'default'    => array(
					'top'      => 14,
					'right'    => 20,
					'bottom'   => 14,
					'left'     => 20,
					'unit'     => 'px',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .centinela-slogan' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'show_gradient_bg' => 'yes',
				),
			)
		);

		$this->add_control(
			'bg_radius',
			array(
				'label'      => __( 'Radio de borde', 'centinela-group-theme' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px'  => array( 'min' => 0, 'max' => 160, 'step' => 1 ),
					'em'  => array( 'min' => 0, 'max' => 10, 'step' => 0.1 ),
					'rem' => array( 'min' => 0, 'max' => 10, 'step' => 0.1 ),
				),
				'default'    => array(
					'size' => 16,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .centinela-slogan' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'show_gradient_bg' => 'yes',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$text     = isset( $settings['slogan_text'] ) ? trim( (string) $settings['slogan_text'] ) : '';
		if ( $text === '' ) {
			return;
		}

		$typing_speed   = isset( $settings['typing_speed'] ) ? max( 20, (int) $settings['typing_speed'] ) : 75;
		$deleting_speed = isset( $settings['deleting_speed'] ) ? max( 20, (int) $settings['deleting_speed'] ) : 45;
		$pause_ms       = isset( $settings['pause_ms'] ) ? max( 300, (int) $settings['pause_ms'] ) : 1300;
		$loop           = ! empty( $settings['loop_animation'] ) && $settings['loop_animation'] === 'yes';
		$show_cursor    = ! empty( $settings['show_cursor'] ) && $settings['show_cursor'] === 'yes';
		$show_gradient  = ! empty( $settings['show_gradient_bg'] ) && $settings['show_gradient_bg'] === 'yes';
		$classes        = 'centinela-slogan';
		if ( ! $show_gradient ) {
			$classes .= ' centinela-slogan--no-bg';
		}
		?>
		<div
			class="<?php echo esc_attr( $classes ); ?>"
			data-slogan-text="<?php echo esc_attr( $text ); ?>"
			data-typing-speed="<?php echo esc_attr( $typing_speed ); ?>"
			data-deleting-speed="<?php echo esc_attr( $deleting_speed ); ?>"
			data-pause-ms="<?php echo esc_attr( $pause_ms ); ?>"
			data-loop="<?php echo $loop ? '1' : '0'; ?>"
		>
			<span class="centinela-slogan__quote" aria-hidden="true">"</span>
			<span class="centinela-slogan__typed"></span>
			<?php if ( $show_cursor ) : ?>
				<span class="centinela-slogan__cursor" aria-hidden="true">|</span>
			<?php endif; ?>
			<span class="centinela-slogan__quote" aria-hidden="true">"</span>
		</div>
		<?php
	}
}
