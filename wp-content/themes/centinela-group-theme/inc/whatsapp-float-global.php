<?php
/**
 * Botón flotante WhatsApp global: mostrar en todas las páginas cuando está configurado en la portada
 * Lee la configuración del widget desde la página de inicio (Elementor) e inyecta el botón en el footer
 * en el resto de páginas para que "Todas las páginas" funcione aunque el widget solo esté en el Home.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Busca recursivamente en la estructura de Elementor el primer widget centinela_whatsapp_float.
 *
 * @param array $elements Elementos de Elementor.
 * @return array|null Settings del widget o null.
 */
function centinela_whatsapp_float_find_widget_settings( $elements ) {
	if ( ! is_array( $elements ) ) {
		return null;
	}
	foreach ( $elements as $element ) {
		if ( ! is_array( $element ) ) {
			continue;
		}
		if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'centinela_whatsapp_float' && ! empty( $element['settings'] ) ) {
			return $element['settings'];
		}
		if ( ! empty( $element['elements'] ) ) {
			$found = centinela_whatsapp_float_find_widget_settings( $element['elements'] );
			if ( $found !== null ) {
				return $found;
			}
		}
	}
	return null;
}

/**
 * Obtiene la configuración del widget WhatsApp desde la página de inicio (Elementor).
 *
 * @return array|null Array de settings o null.
 */
function centinela_whatsapp_float_get_settings_from_front_page() {
	$front_page_id = (int) get_option( 'page_on_front', 0 );
	if ( $front_page_id < 1 ) {
		return null;
	}
	$data = get_post_meta( $front_page_id, '_elementor_data', true );
	if ( ! is_string( $data ) || $data === '' ) {
		return null;
	}
	$elements = json_decode( $data, true );
	if ( ! is_array( $elements ) ) {
		return null;
	}
	return centinela_whatsapp_float_find_widget_settings( $elements );
}

/**
 * Normaliza número de teléfono: solo dígitos.
 *
 * @param string $phone Número.
 * @return string
 */
function centinela_whatsapp_float_normalize_phone( $phone ) {
	if ( empty( $phone ) || ! is_string( $phone ) ) {
		return '';
	}
	return preg_replace( '/\D/', '', trim( $phone ) );
}

/**
 * Comprueba si el botón debe mostrarse según display_mode y display_pages.
 *
 * @param string $display_mode 'all' | 'include' | 'exclude'.
 * @param array  $display_pages IDs de páginas.
 * @return bool
 */
function centinela_whatsapp_float_should_display( $display_mode, $display_pages ) {
	if ( $display_mode === 'all' || empty( $display_mode ) ) {
		return true;
	}
	$current_id = get_queried_object_id();
	$page_ids   = array_filter( array_map( 'intval', (array) $display_pages ) );
	if ( $display_mode === 'include' ) {
		return in_array( $current_id, $page_ids, true );
	}
	if ( $display_mode === 'exclude' ) {
		return ! in_array( $current_id, $page_ids, true );
	}
	return true;
}

/**
 * Renderiza el HTML del botón flotante WhatsApp.
 *
 * @param array $settings Settings del widget (phone, message, position, open_new_tab, etc.).
 */
function centinela_whatsapp_float_render_button( $settings ) {
	$phone   = centinela_whatsapp_float_normalize_phone( isset( $settings['phone'] ) ? $settings['phone'] : '' );
	$message = isset( $settings['message'] ) ? trim( (string) $settings['message'] ) : '';
	$position = isset( $settings['position'] ) ? $settings['position'] : 'right';
	$new_tab  = isset( $settings['open_new_tab'] ) && $settings['open_new_tab'] === 'yes';

	if ( $phone === '' ) {
		return;
	}

	$url = 'https://wa.me/' . $phone;
	if ( $message !== '' ) {
		$url .= '?text=' . rawurlencode( $message );
	}

	$link_class = 'centinela-whatsapp-float centinela-whatsapp-float--' . esc_attr( $position );
	$icon_svg   = '<svg class="centinela-whatsapp-float__icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
	?>
	<a href="<?php echo esc_url( $url ); ?>" class="<?php echo $link_class; ?>" title="<?php esc_attr_e( 'Escribir por WhatsApp', 'centinela-group-theme' ); ?>" aria-label="<?php esc_attr_e( 'Abrir chat de WhatsApp', 'centinela-group-theme' ); ?>"<?php echo $new_tab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
		<?php echo $icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</a>
	<?php
}

/**
 * En wp_footer: si la portada tiene el widget WhatsApp y estamos en otra página, inyectar el botón.
 * Así "Mostrar en todas las páginas" funciona aunque el widget solo esté en el Home.
 */
function centinela_whatsapp_float_maybe_output_global() {
	$front_page_id = (int) get_option( 'page_on_front', 0 );
	$current_id    = get_queried_object_id();

	// Si la portada es "últimas entradas", no tenemos página de la que leer el widget.
	if ( $front_page_id < 1 ) {
		return;
	}

	// En la propia portada el widget ya se renderiza dentro del contenido; no duplicar.
	if ( $current_id === $front_page_id ) {
		return;
	}

	$settings = centinela_whatsapp_float_get_settings_from_front_page();
	if ( empty( $settings ) || ! is_array( $settings ) ) {
		return;
	}

	$display_mode  = isset( $settings['display_mode'] ) ? $settings['display_mode'] : 'all';
	$display_pages = isset( $settings['display_pages'] ) ? $settings['display_pages'] : array();

	if ( ! centinela_whatsapp_float_should_display( $display_mode, $display_pages ) ) {
		return;
	}

	centinela_whatsapp_float_render_button( $settings );
}
add_action( 'wp_footer', 'centinela_whatsapp_float_maybe_output_global', 5 );
