<?php
/**
 * Configuración para convivir: tienda Syscom (API) en /tienda/ + productos WooCommerce en /tienda-centinela/.
 * Avisos en el escritorio y helper para enlazar a la tienda WC.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comprobar si la petición es la URL de la tienda WC: /tienda-centinela/ o /tienda-centinela
 */
function centinela_is_tienda_centinela_request() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}
	$req_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	if ( ! $req_path ) {
		return false;
	}
	$path = trim( $req_path, '/' );
	return $path === 'tienda-centinela';
}

/**
 * Comprobar si la petición es cualquier URL de la tienda Syscom: /tienda/ o /tienda/categoria/...
 * Incluye rutas como /tienda/radiocomunicacion/proteccion-contra-descarga/coaxial/ (centinela-submenu).
 */
function centinela_is_tienda_syscom_request() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}
	$req_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	if ( ! $req_path ) {
		return false;
	}
	$path = trim( $req_path, '/' );
	return $path === 'tienda' || strpos( $path, 'tienda/' ) === 0;
}

/**
 * Hacer que /tienda/ y /tienda/categoria/... sean siempre la tienda Syscom y /tienda-centinela/ la tienda WooCommerce.
 * - Cualquier URL bajo /tienda/ (raíz o con ruta de categoría): devolvemos la ID de "tienda-centinela" como shop page,
 *   así is_shop() es false y se cargan los productos de la API Syscom.
 * - Cuando visita /tienda-centinela/, devolvemos la ID de "tienda-centinela" como shop page,
 *   así is_shop() es true y se cargan los productos WC.
 */
function centinela_filter_shop_page_id_on_tienda_url( $shop_page_id ) {
	$tienda_centinela = get_page_by_path( 'tienda-centinela', OBJECT, 'page' );
	if ( ! $tienda_centinela ) {
		return $shop_page_id;
	}
	// /tienda/ o /tienda/categoria/... → tienda Syscom (is_shop debe ser false).
	if ( centinela_is_tienda_syscom_request() ) {
		return (string) $tienda_centinela->ID;
	}
	// /tienda-centinela/ → sí es shop.
	if ( centinela_is_tienda_centinela_request() ) {
		return (string) $tienda_centinela->ID;
	}
	return $shop_page_id;
}
add_filter( 'option_woocommerce_shop_page_id', 'centinela_filter_shop_page_id_on_tienda_url', 1 );

/**
 * Vaciar el contenido de la página en tienda-centinela solo en frontend.
 * WooCommerce inyecta el listado de productos en the_content cuando la página es la shop;
 * al vaciarlo aquí evitamos duplicar el listado (la plantilla ya muestra el grid en .centinela-tienda).
 * Solo se aplica cuando la plantilla registra este filtro; no vacía en el editor de Elementor.
 *
 * @param string $content Contenido del post.
 * @return string
 */
function centinela_tienda_suppress_shop_page_content( $content ) {
	$post = get_post();
	if ( ! $post || $post->post_name !== 'tienda-centinela' ) {
		return $content;
	}
	return '';
}

/**
 * En la tienda WC (tienda-centinela) quitar wrappers y breadcrumb por defecto para usar nuestra estructura.
 */
function centinela_woocommerce_remove_shop_wrappers() {
	if ( ! is_shop() ) {
		return;
	}
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
}
add_action( 'wp', 'centinela_woocommerce_remove_shop_wrappers', 5 );

/**
 * Obtener la URL de la página de tienda de WooCommerce (productos creados en WC).
 * Útil para enlazar desde la tienda Syscom a los productos propios.
 *
 * @return string URL de la tienda WC o vacío si no está configurada.
 */
function centinela_get_wc_shop_url() {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return '';
	}
	$shop_id = wc_get_page_id( 'shop' );
	if ( $shop_id <= 0 ) {
		return '';
	}
	$url = get_permalink( $shop_id );
	return $url ? $url : '';
}

/**
 * Comprobar si la página de tienda de WooCommerce es la misma que la página "tienda" (Syscom).
 * Cuando es la misma, /tienda/ sigue mostrando la plantilla Centinela por el filtro template_include,
 * pero en el escritorio conviene avisar para que puedan crear una página aparte para productos WC.
 */
function centinela_is_wc_shop_same_as_tienda_page() {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return false;
	}
	$shop_page_id = wc_get_page_id( 'shop' );
	if ( $shop_page_id <= 0 ) {
		return false;
	}
	$tienda_page = get_page_by_path( 'tienda', OBJECT, 'page' );
	if ( ! $tienda_page ) {
		return false;
	}
	return (int) $tienda_page->ID === (int) $shop_page_id;
}

/**
 * Aviso en el escritorio: si la tienda WC es la misma que la página "tienda", sugerir crear una página aparte.
 */
function centinela_admin_notice_wc_shop_vs_tienda() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	if ( get_option( 'centinela_dismiss_wc_shop_notice', '' ) === '1' ) {
		return;
	}
	if ( ! centinela_is_wc_shop_same_as_tienda_page() ) {
		return;
	}

	$shop_settings_url = admin_url( 'admin.php?page=wc-settings&tab=products' );
	$create_shop_url    = admin_url( 'post-new.php?post_type=page' );
	$dismiss_url       = wp_nonce_url(
		add_query_arg( 'centinela_dismiss_wc_shop_notice', '1', admin_url( 'index.php' ) ),
		'centinela_dismiss_wc_shop'
	);
	?>
	<div class="notice notice-info is-dismissible" id="centinela-wc-shop-notice">
		<p>
			<strong><?php esc_html_e( 'Centinela: Tienda y WooCommerce', 'centinela-group-theme' ); ?></strong>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: 1: link to WC settings, 2: link to create new page */
					__( 'La página <strong>Tienda</strong> muestra los productos de la API Syscom. Para vender también productos creados directamente en WooCommerce, puede crear una página nueva (por ejemplo «Shop» o «Productos propios») y asignarla como <a href="%1$s">Página de la tienda de WooCommerce</a>. Así tendrá: <em>/tienda/</em> = productos Syscom y la nueva página = productos WooCommerce. <a href="%2$s">Crear página</a>', 'centinela-group-theme' ),
					esc_url( $shop_settings_url ),
					esc_url( $create_shop_url )
				),
				array( 'a' => array( 'href' => true ), 'strong' => array(), 'em' => array() )
			);
			?>
		</p>
		<p>
			<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-secondary"><?php esc_html_e( 'No volver a mostrar', 'centinela-group-theme' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'centinela_admin_notice_wc_shop_vs_tienda' );

/**
 * Guardar dismiss del aviso.
 */
function centinela_dismiss_wc_shop_notice() {
	if ( ! isset( $_GET['centinela_dismiss_wc_shop_notice'] ) || $_GET['centinela_dismiss_wc_shop_notice'] !== '1' ) {
		return;
	}
	if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'centinela_dismiss_wc_shop' ) ) {
		return;
	}
	update_option( 'centinela_dismiss_wc_shop_notice', '1' );
	wp_safe_redirect( remove_query_arg( array( 'centinela_dismiss_wc_shop_notice', '_wpnonce' ), admin_url( 'index.php' ) ) );
	exit;
}
add_action( 'admin_init', 'centinela_dismiss_wc_shop_notice' );
