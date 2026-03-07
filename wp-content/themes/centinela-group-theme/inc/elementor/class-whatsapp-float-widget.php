<?php
/**
 * Elementor Widget: Botón flotante WhatsApp (Centinela Group)
 * Botón fijo abajo a izquierda o derecha, con animación scale en loop.
 * Enlace a WhatsApp con número y mensaje predefinido.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_WhatsApp_Float_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_whatsapp_float';
	}

	public function get_title() {
		return __( 'Botón flotante WhatsApp', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-whatsapp';
	}

	public function get_categories() {
		return array( 'centinela' );
	}

	public function get_keywords() {
		return array( 'whatsapp', 'flotante', 'chat', 'centinela', 'botón' );
	}

	public function get_style_depends() {
		return array( 'centinela-theme-scss' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_whatsapp',
			array(
				'label' => __( 'WhatsApp', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'phone',
			array(
				'label'       => __( 'Número de WhatsApp', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Ej: 573001234567', 'centinela-group-theme' ),
				'description' => __( 'Solo dígitos, con código de país (ej: 57 para Colombia). Sin + ni espacios.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'message',
			array(
				'label'       => __( 'Mensaje predefinido', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => '',
				'placeholder' => __( 'Hola, me gustaría más información...', 'centinela-group-theme' ),
				'description' => __( 'Opcional. Texto que aparecerá en el chat al abrir WhatsApp.', 'centinela-group-theme' ),
				'rows'        => 3,
			)
		);

		$this->add_control(
			'position',
			array(
				'label'   => __( 'Posición', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => array(
					'left'  => array(
						'title' => __( 'Izquierda', 'centinela-group-theme' ),
						'icon'  => 'eicon-h-align-left',
					),
					'right' => array(
						'title' => __( 'Derecha', 'centinela-group-theme' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'default' => 'right',
			)
		);

		$this->add_control(
			'open_new_tab',
			array(
				'label'        => __( 'Abrir en nueva pestaña', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_visibility',
			array(
				'label' => __( 'Visibilidad', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'display_mode',
			array(
				'label'   => __( 'Mostrar en', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'all',
				'options' => array(
					'all'      => __( 'Todas las páginas', 'centinela-group-theme' ),
					'include'  => __( 'Solo en páginas seleccionadas', 'centinela-group-theme' ),
					'exclude'  => __( 'En todas excepto las seleccionadas', 'centinela-group-theme' ),
				),
			)
		);

		$pages_options = array();
		$pages         = get_pages( array( 'post_status' => 'publish', 'number' => 500, 'sort_column' => 'post_title' ) );
		foreach ( $pages as $page ) {
			$pages_options[ $page->ID ] = $page->post_title;
		}

		$this->add_control(
			'display_pages',
			array(
				'label'       => __( 'Páginas', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $pages_options,
				'default'     => array(),
				'condition'   => array(
					'display_mode' => array( 'include', 'exclude' ),
				),
				'description' => __( 'Elige en qué páginas debe mostrarse u ocultarse el botón.', 'centinela-group-theme' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Normaliza el número: solo dígitos.
	 *
	 * @param string $phone Número tal como lo escribe el usuario.
	 * @return string Solo dígitos.
	 */
	private static function normalize_phone( $phone ) {
		if ( empty( $phone ) || ! is_string( $phone ) ) {
			return '';
		}
		return preg_replace( '/\D/', '', trim( $phone ) );
	}

	/**
	 * Comprueba si el botón debe mostrarse según la visibilidad configurada.
	 *
	 * @param string $display_mode 'all' | 'include' | 'exclude'.
	 * @param array  $display_pages IDs de páginas.
	 * @return bool
	 */
	private static function should_display( $display_mode, $display_pages ) {
		if ( $display_mode === 'all' || empty( $display_mode ) ) {
			return true;
		}
		$current_id   = get_queried_object_id();
		$page_ids     = array_filter( array_map( 'intval', (array) $display_pages ) );
		if ( $display_mode === 'include' ) {
			return in_array( $current_id, $page_ids, true );
		}
		if ( $display_mode === 'exclude' ) {
			return ! in_array( $current_id, $page_ids, true );
		}
		return true;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$phone    = self::normalize_phone( isset( $settings['phone'] ) ? $settings['phone'] : '' );
		$message  = isset( $settings['message'] ) ? trim( (string) $settings['message'] ) : '';
		$position = isset( $settings['position'] ) ? $settings['position'] : 'right';
		$new_tab  = isset( $settings['open_new_tab'] ) && $settings['open_new_tab'] === 'yes';
		$display_mode  = isset( $settings['display_mode'] ) ? $settings['display_mode'] : 'all';
		$display_pages = isset( $settings['display_pages'] ) ? $settings['display_pages'] : array();

		if ( $phone === '' ) {
			return;
		}

		// En el editor siempre mostramos el botón; la visibilidad por páginas solo aplica en el front.
		$is_editor = defined( 'ELEMENTOR_VERSION' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
		if ( ! $is_editor && ! self::should_display( $display_mode, $display_pages ) ) {
			return;
		}

		$url = 'https://wa.me/' . $phone;
		if ( $message !== '' ) {
			$url .= '?text=' . rawurlencode( $message );
		}

		$link_class = 'centinela-whatsapp-float centinela-whatsapp-float--' . $position;
		$icon_svg   = '<svg class="centinela-whatsapp-float__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $link_class ); ?>" title="<?php esc_attr_e( 'Escribir por WhatsApp', 'centinela-group-theme' ); ?>" aria-label="<?php esc_attr_e( 'Abrir chat de WhatsApp', 'centinela-group-theme' ); ?>"<?php echo $new_tab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
			<?php echo $icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
		<?php
	}
}
