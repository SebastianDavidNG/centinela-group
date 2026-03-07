<?php
/**
 * Elementor Widget: Botón con modal de video (Centinela Group)
 * Botón tipo "play" (igual al del Hero Slider) que abre un modal con video.
 * Acepta URL de YouTube, Vimeo o archivo .mp4; si no es video, actúa como enlace normal.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Video_Button_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_video_button';
	}

	public function get_title() {
		return __( 'Botón video / modal', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-play';
	}

	public function get_categories() {
		return array( 'centinela' );
	}

	public function get_keywords() {
		return array( 'botón', 'video', 'modal', 'youtube', 'vimeo', 'centinela', 'play' );
	}

	public function get_script_depends() {
		return array( 'centinela-video-modal' );
	}

	public function get_style_depends() {
		return array( 'centinela-theme-scss' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_button',
			array(
				'label' => __( 'Botón', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => __( 'Texto del botón', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Ver video', 'centinela-group-theme' ),
				'placeholder' => __( 'Ej: Ver video, Cómo funciona', 'centinela-group-theme' ),
				'description' => __( 'Texto visible junto al botón. También se usa como title y aria-label.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'button_url',
			array(
				'label'       => __( 'Enlace o video', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://... o YouTube, Vimeo, .mp4',
				'default'     => array( 'url' => '' ),
				'description' => __( 'Enlace normal o URL de video (YouTube, Vimeo o archivo .mp4). Si es video, se abrirá en un modal.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'alignment',
			array(
				'label'        => __( 'Alineación', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::CHOOSE,
				'options'      => array(
					'left'   => array(
						'title' => __( 'Izquierda', 'centinela-group-theme' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Centro', 'centinela-group-theme' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Derecha', 'centinela-group-theme' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default' => 'left',
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label'       => __( 'Color del botón', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''        => __( 'Por defecto (azul)', 'centinela-group-theme' ),
					'blue'    => __( 'Azul (primario)', 'centinela-group-theme' ),
					'blue-2'  => __( 'Azul 2', 'centinela-group-theme' ),
					'green'   => __( 'Verde (acento)', 'centinela-group-theme' ),
					'white'   => __( 'Blanco', 'centinela-group-theme' ),
					'black'   => __( 'Negro', 'centinela-group-theme' ),
					'grey'    => __( 'Gris', 'centinela-group-theme' ),
				),
				'description' => __( 'Usa la paleta del tema (variables CSS).', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label'       => __( 'Color hover', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''        => __( 'Por defecto (verde)', 'centinela-group-theme' ),
					'blue'    => __( 'Azul (primario)', 'centinela-group-theme' ),
					'blue-2'  => __( 'Azul 2', 'centinela-group-theme' ),
					'green'   => __( 'Verde (acento)', 'centinela-group-theme' ),
					'white'   => __( 'Blanco', 'centinela-group-theme' ),
					'black'   => __( 'Negro', 'centinela-group-theme' ),
					'grey'    => __( 'Gris', 'centinela-group-theme' ),
				),
				'description' => __( 'Color del círculo/borde al hacer hover (usa las mismas variables del tema).', 'centinela-group-theme' ),
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
		$settings  = $this->get_settings_for_display();
		$text      = isset( $settings['button_text'] ) ? $settings['button_text'] : __( 'Reproducir video', 'centinela-group-theme' );
		$url       = isset( $settings['button_url']['url'] ) ? $settings['button_url']['url'] : '';
		$is_video  = self::is_video_url( $url );
		$play_svg  = '<svg class="centinela-hero__cta-play-icon" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7L8 5z"/></svg>';

		if ( $url === '' ) {
			return;
		}

		$instance_id = 'centinela-video-button-' . $this->get_id();

		$alignment  = isset( $settings['alignment'] ) ? $settings['alignment'] : 'left';
		$wrap_class = 'centinela-video-button__wrap centinela-video-button__wrap--' . $alignment;

		$button_color       = isset( $settings['button_color'] ) ? (string) $settings['button_color'] : '';
		$button_hover_color = isset( $settings['button_hover_color'] ) ? (string) $settings['button_hover_color'] : '';

		$color_map = array(
			'blue'   => 'var(--centinela-blue)',
			'blue-2' => 'var(--centinela-blue-2)',
			'green'  => 'var(--centinela-green)',
			'white'  => 'var(--centinela-white)',
			'black'  => 'var(--centinela-black)',
			'grey'   => 'var(--centinela-grey)',
		);

		$base_value  = ( $button_color !== '' && isset( $color_map[ $button_color ] ) ) ? $color_map[ $button_color ] : 'var(--centinela-blue)';
		$hover_value = ( $button_hover_color !== '' && isset( $color_map[ $button_hover_color ] ) ) ? $color_map[ $button_hover_color ] : 'var(--centinela-green)';

		// Color del icono/texto: blanco cuando el fondo es oscuro (azules, verde, negro, gris), azul cuando el fondo es blanco.
		$text_color = ( 'white' === $button_color ) ? 'var(--centinela-blue)' : '#FFFFFF';

		// Enlace: solo el círculo; el texto va fuera. Espacio amplio para que se vea claro.
		$inline_css  = '#' . $instance_id . '{gap:2rem;}';

		$inline_css .= '#' . $instance_id . ' .centinela-hero__cta--play{';
		$inline_css .= 'display:inline-flex;align-items:center;width:auto;min-width:0;height:auto;min-height:0;padding:0;background:transparent;border:none;';
		$inline_css .= '}';

		$inline_css .= '#' . $instance_id . ' .centinela-hero__cta--play::before{display:none;}';

		// transform-origin right center: al hacer scale el círculo crece hacia la izquierda, no hacia el texto.
		$inline_css .= '#' . $instance_id . ' .centinela-video-button__circle{';
		$inline_css .= 'position:relative;display:inline-flex;align-items:center;justify-content:center;width:98px;height:98px;min-width:98px;min-height:98px;border-radius:50%;background-color:' . $base_value . ';color:' . $text_color . ';border:12px solid hsla(0,0%,100%,.3);flex-shrink:0;transform-origin:right center;transition:border-color .35s ease,color .35s ease,transform .35s ease,box-shadow .35s ease;';
		$inline_css .= '}';

		$inline_css .= '#' . $instance_id . ' .centinela-video-button__circle::before{';
		$inline_css .= 'content:"";position:absolute;left:0;top:0;width:100%;height:100%;background:' . $base_value . ';border-radius:100%;transition:background .35s ease;pointer-events:none;z-index:0;';
		$inline_css .= '}';

		$inline_css .= '#' . $instance_id . ' .centinela-video-button__circle .centinela-hero__cta-play-icon{';
		$inline_css .= 'position:relative;z-index:1;width:36px;height:36px;fill:currentColor;';
		$inline_css .= '}';

		$inline_css .= '#' . $instance_id . ' .centinela-hero__cta--play:hover .centinela-video-button__circle,';
		$inline_css .= '#' . $instance_id . ' .centinela-hero__cta--play:focus-visible .centinela-video-button__circle{';
		$inline_css .= 'border-color:' . $hover_value . ';background-color:' . $hover_value . ';color:' . $text_color . ';transform:scale(1.04);box-shadow:0 4px 20px rgba(34,147,121,.4);';
		$inline_css .= '}';

		$inline_css .= '#' . $instance_id . ' .centinela-hero__cta--play:hover .centinela-video-button__circle::before,';
		$inline_css .= '#' . $instance_id . ' .centinela-hero__cta--play:focus-visible .centinela-video-button__circle::before{';
		$inline_css .= 'background:' . $hover_value . ';';
		$inline_css .= '}';

		$inline_css .= '#' . $instance_id . ' .centinela-hero__cta--play:focus{outline:2px solid ' . $hover_value . ';outline-offset:2px;}';

		$inline_css .= '#' . $instance_id . ' .centinela-video-button__label{';
		$inline_css .= 'font-size:1.125rem;font-weight:700;color:#021C37;letter-spacing:0.02em;text-decoration:none;line-height:1.3;margin-left:0.5rem;';
		$inline_css .= '}';

		$title_label = $text ?: __( 'Reproducir video', 'centinela-group-theme' );
		$display_text = $text ?: __( 'Ver video', 'centinela-group-theme' );
		?>
		<style id="<?php echo esc_attr( $instance_id ); ?>-styles"><?php echo $inline_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
		<div id="<?php echo esc_attr( $instance_id ); ?>" class="<?php echo esc_attr( $wrap_class ); ?>">
			<?php if ( $is_video ) : ?>
				<a href="#" class="centinela-hero__cta centinela-hero__cta--play" data-video-url="<?php echo esc_attr( $url ); ?>" title="<?php echo esc_attr( $title_label ); ?>" aria-label="<?php echo esc_attr( $title_label ); ?>"><span class="centinela-video-button__circle"><?php echo $play_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></a>
			<?php else : ?>
				<a href="<?php echo esc_url( $url ); ?>" class="centinela-hero__cta centinela-hero__cta--play" title="<?php echo esc_attr( $title_label ); ?>" aria-label="<?php echo esc_attr( $title_label ); ?>"><span class="centinela-video-button__circle"><?php echo $play_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></a>
			<?php endif; ?>
			<span class="centinela-video-button__label"><?php echo esc_html( $display_text ); ?></span>
		</div>
		<?php
	}
}
