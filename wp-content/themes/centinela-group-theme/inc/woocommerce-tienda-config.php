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
 * Hacer que los productos WooCommerce usen la base /tienda-centinela/ en la URL.
 * Así las fichas de producto quedan en /tienda-centinela/nombre-producto/ (misma familia que la tienda).
 */
function centinela_filter_woocommerce_permalinks( $value ) {
	$value = is_array( $value ) ? $value : array();
	$value['product_base'] = 'tienda-centinela';
	return $value;
}
add_filter( 'option_woocommerce_permalinks', 'centinela_filter_woocommerce_permalinks', 1 );

/**
 * Regla de reescritura con prioridad alta para que /tienda-centinela/slug-producto/ resuelva como producto,
 * y no como subpágina de la página "tienda-centinela" (que provocaba 404).
 */
function centinela_tienda_centinela_product_rewrite_rule() {
	add_rewrite_rule(
		'tienda-centinela/([^/]+)/?$',
		'index.php?product=$matches[1]',
		'top'
	);
}
add_action( 'init', 'centinela_tienda_centinela_product_rewrite_rule', 20 );

/**
 * Buscar producto WooCommerce por slug (post_name).
 *
 * @param string $slug post_name del producto.
 * @return int 0 si no existe.
 */
function centinela_get_product_id_by_slug( $slug ) {
	$slug = sanitize_title( $slug );
	if ( $slug === '' ) {
		return 0;
	}
	// Búsqueda directa por post_name y post_type para evitar problemas con get_posts/query_var.
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_name = %s AND post_status = 'publish' LIMIT 1",
		$slug
	) );
	if ( $id ) {
		return $id;
	}
	$posts = get_posts( array(
		'post_type'      => 'product',
		'name'           => $slug,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	if ( ! empty( $posts ) ) {
		return (int) $posts[0];
	}
	return 0;
}

/**
 * Si la URL es /tienda-centinela/slug/ y existe un producto con ese slug, forzar la carga del producto.
 * Usamos el filtro 'request' para que la consulta principal reciba name + post_type.
 */
function centinela_request_tienda_centinela_product( $query_vars ) {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return $query_vars;
	}
	$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	$path = $path ? trim( $path ) : '';
	if ( ! preg_match( '#^/?tienda-centinela/([^/]+)/?$#', $path, $m ) ) {
		return $query_vars;
	}
	$slug = trim( $m[1], '/' );
	if ( $slug === '' ) {
		return $query_vars;
	}
	$product_id = centinela_get_product_id_by_slug( $slug );
	if ( ! $product_id ) {
		$product_id = function_exists( 'wc_get_product_id_by_sku' ) ? wc_get_product_id_by_sku( $slug ) : 0;
	}
	if ( ! $product_id ) {
		return $query_vars;
	}
	return array(
		'name'      => $slug,
		'post_type' => 'product',
		'product'   => $slug,
	);
}
add_filter( 'request', 'centinela_request_tienda_centinela_product', 1 );

/**
 * Si la URL es /tienda-centinela/slug/ y existe el producto, forzar la carga de su ficha (evitar 404).
 */
function centinela_template_redirect_tienda_centinela_product() {
	global $wp_query;

	if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	$path = $path ? trim( $path, '/' ) : '';
	// Aceptar "tienda-centinela/producto-centinela" con o sin barras.
	if ( $path !== 'tienda-centinela' && strpos( $path, 'tienda-centinela/' ) !== 0 ) {
		return;
	}
	$parts = explode( '/', $path );
	if ( count( $parts ) !== 2 || $parts[0] !== 'tienda-centinela' ) {
		return;
	}
	$slug = $parts[1];
	if ( $slug === '' ) {
		return;
	}

	// Buscar por post_name; si no, por SKU.
	$product_id = centinela_get_product_id_by_slug( $slug );
	if ( ! $product_id && function_exists( 'wc_get_product_id_by_sku' ) ) {
		$product_id = wc_get_product_id_by_sku( $slug );
	}

	// Depuración: ?centinela_debug_product=1 muestra si encontramos el producto (quitar en producción).
	if ( ! empty( $_GET['centinela_debug_product'] ) ) {
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'slug=' . esc_html( $slug ) . "\n";
		echo 'product_id=' . (int) $product_id . "\n";
		echo 'template_exists=' . ( file_exists( get_template_directory() . '/woocommerce/single-product.php' ) ? 'yes' : 'no' );
		exit;
	}

	if ( ! $product_id ) {
		return;
	}

	// Siempre forzar esta URL como ficha de producto (evitar 404 por conflicto con la página tienda-centinela).
	$wp_query = new WP_Query( array(
		'p'         => $product_id,
		'post_type' => 'product',
	) );
	if ( ! $wp_query->have_posts() ) {
		return;
	}

	$wp_query->the_post();
	status_header( 200 );
	nocache_headers();

	$template = get_template_directory() . '/woocommerce/single-product.php';
	if ( ! file_exists( $template ) ) {
		$template = get_stylesheet_directory() . '/woocommerce/single-product.php';
	}
	if ( file_exists( $template ) ) {
		include $template;
		exit;
	}
}
add_action( 'template_redirect', 'centinela_template_redirect_tienda_centinela_product', 0 );

/**
 * Flush rewrite rules para que /tienda-centinela/producto-slug/ funcione (incluye la regla "top" de productos).
 */
function centinela_maybe_flush_rewrite_for_tienda_centinela() {
	if ( get_option( 'centinela_flush_rewrite_tienda_centinela', '' ) === '1' ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'centinela_flush_rewrite_tienda_centinela', '1' );
}
add_action( 'init', 'centinela_maybe_flush_rewrite_for_tienda_centinela', 999 );

/**
 * Tras añadir la regla "top" para productos bajo tienda-centinela, forzar un flush adicional una vez.
 */
function centinela_maybe_flush_rewrite_tienda_centinela_v2() {
	if ( get_option( 'centinela_flush_rewrite_tienda_centinela_v2', '' ) === '1' ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'centinela_flush_rewrite_tienda_centinela_v2', '1' );
}
add_action( 'init', 'centinela_maybe_flush_rewrite_tienda_centinela_v2', 999 );

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
 * Texto del botón "Agregar al carrito" en ficha de producto WC: igual que Syscom.
 */
function centinela_woocommerce_single_add_to_cart_text( $text, $product ) {
	return _x( 'Agregar al carrito', 'single product add to cart button', 'centinela-group-theme' );
}
add_filter( 'woocommerce_product_single_add_to_cart_text', 'centinela_woocommerce_single_add_to_cart_text', 10, 2 );

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

/**
 * REST: vista rápida para productos WooCommerce (misma forma que Syscom para el modal).
 */
function centinela_woocommerce_quickview_route() {
	register_rest_route( 'centinela/v1', '/producto-wc-quick-view', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		),
		'callback' => function ( $request ) {
			$id = (int) $request->get_param( 'id' );
			if ( ! function_exists( 'wc_get_product' ) ) {
				return new WP_REST_Response( array( 'error' => 'WooCommerce no disponible' ), 404 );
			}
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_visible() ) {
				return new WP_REST_Response( array( 'error' => 'Producto no encontrado' ), 404 );
			}
			$gallery_ids = $product->get_gallery_image_ids();
			$thumb_id    = $product->get_image_id();
			$image_ids   = $thumb_id ? array_merge( array( $thumb_id ), $gallery_ids ) : $gallery_ids;
			$image_ids   = array_unique( array_filter( $image_ids ) );
			$imagenes    = array_map( function ( $img_id ) { return wp_get_attachment_image_url( $img_id, 'medium' ); }, $image_ids );
			$imagenes_large = array_map( function ( $img_id ) { return wp_get_attachment_image_url( $img_id, 'full' ); }, $image_ids );
			$url = $product->get_permalink();
			$add_to_cart_url = add_query_arg( 'add-to-cart', $id, $url );
			$terms = get_the_terms( $id, 'product_cat' );
			$categoria = '';
			if ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$categoria = $terms[0]->name;
			}
			return new WP_REST_Response( array(
				'id'               => (string) $id,
				'titulo'           => $product->get_name(),
				'precio'           => $product->get_price( 'edit' ),
				'precio_formateado'=> $product->get_price_html(),
				'categoria'        => $categoria,
				'modelo'           => $product->get_sku(),
				'marca'            => '',
				'imagenes'         => $imagenes,
				'imagenes_large'   => $imagenes_large,
				'img_portada'      => isset( $imagenes[0] ) ? $imagenes[0] : '',
				'url'              => $url,
				'add_to_cart_url'  => $add_to_cart_url,
			), 200 );
		},
	) );
}
add_action( 'rest_api_init', 'centinela_woocommerce_quickview_route' );
