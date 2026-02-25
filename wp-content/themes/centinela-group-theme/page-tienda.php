<?php
/**
 * Template Name: Tienda (Centinela)
 * Plantilla unificada: /tienda/ = productos API Syscom, /tienda-centinela/ = productos WooCommerce.
 * Misma estructura y estilos en ambas (hero, sidebar, grid).
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$es_tienda_wc = ( function_exists( 'is_shop' ) && is_shop() ) || ( get_queried_object() && get_queried_object()->post_name === 'tienda-centinela' );

wp_enqueue_script( 'centinela-tienda-ajax', get_template_directory_uri() . '/assets/js/tienda-ajax.js', array(), defined( 'CENTINELA_THEME_VERSION' ) ? CENTINELA_THEME_VERSION : '1.0.0', true );
// Vista rápida: misma plantilla para Syscom y para tienda-centinela (WooCommerce).
wp_enqueue_script( 'centinela-tienda-quickview', get_template_directory_uri() . '/assets/js/tienda-quickview.js', array( 'centinela-tienda-ajax', 'centinela-image-lightbox' ), defined( 'CENTINELA_THEME_VERSION' ) ? CENTINELA_THEME_VERSION : '1.0.0', true );

get_header();

if ( $es_tienda_wc ) {
	$tienda_base       = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/tienda-centinela/' );
	$tienda_syscom_url = home_url( '/tienda/' );
	$product_cats      = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0 ) );
	$product_cats      = is_wp_error( $product_cats ) ? array() : $product_cats;
	$current_cat       = is_product_category() ? get_queried_object() : null;
	$min_price_actual  = isset( $_GET['min_price'] ) ? sanitize_text_field( $_GET['min_price'] ) : '';
	$max_price_actual  = isset( $_GET['max_price'] ) ? sanitize_text_field( $_GET['max_price'] ) : '';
	$paged             = max( 1, (int) get_query_var( 'paged', 1 ) );
	$wc_query_args     = array( 'post_type' => 'product', 'posts_per_page' => 12, 'paged' => $paged, 'post_status' => 'publish' );
	if ( $current_cat && isset( $current_cat->term_id ) ) {
		$wc_query_args['tax_query'] = array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $current_cat->term_id ) );
	}
	if ( $min_price_actual !== '' || $max_price_actual !== '' ) {
		$meta_q = array( 'relation' => 'AND' );
		if ( $min_price_actual !== '' ) {
			$meta_q[] = array( 'key' => '_price', 'value' => (float) $min_price_actual, 'compare' => '>=', 'type' => 'NUMERIC' );
		}
		if ( $max_price_actual !== '' ) {
			$meta_q[] = array( 'key' => '_price', 'value' => (float) $max_price_actual, 'compare' => '<=', 'type' => 'NUMERIC' );
		}
		$wc_query_args['meta_query'] = $meta_q;
	}
	$wc_products_query = new WP_Query( $wc_query_args );
} else {
	$arbol = array();
if ( class_exists( 'Centinela_Syscom_API' ) && method_exists( 'Centinela_Syscom_API', 'get_categorias_arbol' ) ) {
	$arbol = Centinela_Syscom_API::get_categorias_arbol();
	if ( is_wp_error( $arbol ) ) {
		$arbol = array();
	}
}
$syscom_ok = class_exists( 'Centinela_Syscom_API' ) && Centinela_Syscom_API::has_credentials();

// Categoría: desde URL amigable (/tienda/videovigilancia/.../redes/) o fallback ?categoria= (legacy).
// Si la ruta no viene por rewrite (p. ej. entrada desde menú secundario), extraer de REQUEST_URI para que coincida con el sidebar.
$cat_path = get_query_var( 'centinela_tienda_cat_path' );
if ( ( $cat_path === '' || $cat_path === false ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
	$req_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	if ( $req_path && preg_match( '#/tienda/(.+)$#', $req_path, $m ) ) {
		$cat_path = trim( $m[1], '/' );
	}
}
$cat_path_clean = ( $cat_path !== '' && $cat_path !== false ) ? trim( trim( $cat_path ), '/' ) : '';
$categoria_id   = '';
if ( $cat_path_clean !== '' && function_exists( 'centinela_resolve_cat_path_to_syscom_id' ) ) {
	$categoria_id = centinela_resolve_cat_path_to_syscom_id( $cat_path_clean ) ?: '';
}
if ( $categoria_id === '' && isset( $_GET['categoria'] ) ) {
	$categoria_id = sanitize_text_field( $_GET['categoria'] );
}
$pagina_actual = max( 1, (int) ( isset( $_GET['pag'] ) ? $_GET['pag'] : 1 ) );
$ordenar       = isset( $_GET['ordenar'] ) ? sanitize_text_field( $_GET['ordenar'] ) : 'relevancia';
$marca_actual   = isset( $_GET['marca'] ) ? sanitize_text_field( $_GET['marca'] ) : '';
$min_price_actual = isset( $_GET['min_price'] ) ? sanitize_text_field( $_GET['min_price'] ) : '';
$max_price_actual = isset( $_GET['max_price'] ) ? sanitize_text_field( $_GET['max_price'] ) : '';
$tienda_base    = home_url( '/tienda/' );
$tienda_data    = function_exists( 'centinela_tienda_get_productos_data' ) ? centinela_tienda_get_productos_data( $categoria_id, $pagina_actual, $ordenar, $cat_path_clean, $marca_actual, $min_price_actual, $max_price_actual ) : array( 'productos' => array(), 'paginas' => 0, 'marcas' => array() );
$productos_html = function_exists( 'centinela_tienda_render_productos_html' ) ? centinela_tienda_render_productos_html( $categoria_id, $pagina_actual, $ordenar, $cat_path_clean, $marca_actual, $min_price_actual, $max_price_actual, array( 'productos' => isset( $tienda_data['productos'] ) ? $tienda_data['productos'] : array(), 'paginas' => isset( $tienda_data['paginas'] ) ? (int) $tienda_data['paginas'] : 0 ) ) : '';
	$marcas_sidebar = isset( $tienda_data['marcas'] ) && is_array( $tienda_data['marcas'] ) ? $tienda_data['marcas'] : array();
}

// Rangos de precio (compartidos por ambas tiendas)
$centinela_price_ranges = array(
	array( 'label' => _x( 'Todos', 'price filter', 'centinela-group-theme' ), 'min' => '', 'max' => '' ),
	array( 'label' => 'CO $ 0 – 100.000', 'min' => '0', 'max' => '100000' ),
	array( 'label' => 'CO $ 100.000 – 500.000', 'min' => '100000', 'max' => '500000' ),
	array( 'label' => 'CO $ 500.000 – 1.000.000', 'min' => '500000', 'max' => '1000000' ),
	array( 'label' => 'CO $ 1.000.000 – 2.000.000', 'min' => '1000000', 'max' => '2000000' ),
	array( 'label' => 'CO $ 2.000.000 – 5.000.000', 'min' => '2000000', 'max' => '5000000' ),
	array( 'label' => _x( 'Más de 5.000.000', 'price filter', 'centinela-group-theme' ), 'min' => '5000000', 'max' => '50000000' ),
);

// Mismo SVG que el botón "Pedir cotización" (uniformidad en el sitio)
$centinela_chevron_svg = '<svg class="centinela-header__cta-icon centinela-tienda__cat-summary-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';

// Hero (misma lógica para ambas tiendas)
$tienda_page_id = get_queried_object_id();
$tienda_built_with_elementor = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->db->is_built_with_elementor( $tienda_page_id );
if ( ! $tienda_built_with_elementor ) {
	$hero_image = $tienda_page_id ? get_the_post_thumbnail_url( $tienda_page_id, 'full' ) : '';
	get_template_part( 'template-parts/hero', 'page-inner', array(
		'title'     => $es_tienda_wc ? _x( 'Productos propios', 'shop page title', 'centinela-group-theme' ) : _x( 'Tienda', 'page title', 'centinela-group-theme' ),
		'image_url' => $hero_image ? $hero_image : '',
	) );
}

?>
<?php
// Área de contenido para Elementor. Siempre llamamos the_content() para que Elementor permita editar.
// En tienda-centinela en frontend quitamos solo el filtro de WooCommerce que inyecta el listado en the_content,
// así se muestra el Hero y el resto de Elementor pero no el listado duplicado (ya lo muestra .centinela-tienda).
$en_editor_elementor = class_exists( '\Elementor\Plugin' ) && (
	\Elementor\Plugin::$instance->editor->is_edit_mode() ||
	( method_exists( \Elementor\Plugin::$instance->preview, 'is_preview_mode' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() )
);
if ( $es_tienda_wc && ! $en_editor_elementor && class_exists( 'WC_Template_Loader' ) ) {
	remove_filter( 'the_content', array( 'WC_Template_Loader', 'unsupported_theme_shop_content_filter' ), 10 );
}
while ( have_posts() ) {
	the_post();
	?>
	<div class="centinela-tienda-elementor-content">
		<?php the_content(); ?>
	</div>
	<?php
}
rewind_posts();
?>
<div class="centinela-tienda">
	<div class="centinela-tienda__inner max-w-7xl mx-auto px-4 py-8 md:py-12 flex flex-col md:flex-row gap-8">
		<aside class="centinela-tienda__sidebar w-full md:w-64 flex-shrink-0" role="navigation" aria-label="<?php esc_attr_e( 'Categorías de la tienda', 'centinela-group-theme' ); ?>">
			<div class="centinela-tienda__filters">
				<h2 class="centinela-tienda__sidebar-title"><?php esc_html_e( 'Categorías', 'centinela-group-theme' ); ?></h2>
				<?php if ( $es_tienda_wc ) : ?>
					<nav class="centinela-tienda__nav" role="navigation" aria-label="<?php esc_attr_e( 'Filtrar por categoría', 'centinela-group-theme' ); ?>">
						<div class="centinela-tienda__cat-group">
							<a href="<?php echo esc_url( $tienda_base ); ?>" class="centinela-tienda__cat-link centinela-tienda__cat-link--all <?php echo ! $current_cat ? 'centinela-tienda__cat-link--active' : ''; ?>"><?php esc_html_e( 'Todos los productos', 'centinela-group-theme' ); ?></a>
						</div>
						<?php foreach ( $product_cats as $cat ) : ?>
							<?php $cat_link = get_term_link( $cat ); $is_active = $current_cat && (int) $current_cat->term_id === (int) $cat->term_id; ?>
							<div class="centinela-tienda__cat-group">
								<a href="<?php echo esc_url( is_wp_error( $cat_link ) ? $tienda_base : $cat_link ); ?>" class="centinela-tienda__cat-link <?php echo $is_active ? 'centinela-tienda__cat-link--active' : ''; ?>"><?php echo esc_html( $cat->name ); ?></a>
							</div>
						<?php endforeach; ?>
					</nav>
					<div class="centinela-tienda__filter-block centinela-tienda__filter-price">
						<h3 class="centinela-tienda__filter-title"><?php esc_html_e( 'Precio', 'centinela-group-theme' ); ?></h3>
						<div class="centinela-tienda__price-ranges" role="list" aria-label="<?php esc_attr_e( 'Rango de precio', 'centinela-group-theme' ); ?>">
							<?php foreach ( $centinela_price_ranges as $range ) :
								$q = array();
								if ( $range['min'] !== '' ) { $q['min_price'] = $range['min']; }
								if ( $range['max'] !== '' ) { $q['max_price'] = $range['max']; }
								$price_url = empty( $q ) ? $tienda_base : add_query_arg( $q, $tienda_base );
								$is_active = ( (string) $min_price_actual === (string) $range['min'] ) && ( (string) $max_price_actual === (string) $range['max'] );
								?>
								<a href="<?php echo esc_url( $price_url ); ?>" class="centinela-tienda__price-range-link <?php echo $is_active ? 'centinela-tienda__price-range-link--active' : ''; ?>"><?php echo esc_html( $range['label'] ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="centinela-tienda__filter-block centinela-tienda__wc-shop-link">
						<h3 class="centinela-tienda__filter-title"><?php esc_html_e( 'Más productos', 'centinela-group-theme' ); ?></h3>
						<a href="<?php echo esc_url( $tienda_syscom_url ); ?>" class="centinela-tienda__cat-link centinela-tienda__cat-link--wc"><?php esc_html_e( 'Productos Syscom', 'centinela-group-theme' ); ?></a>
					</div>
				<?php elseif ( ! empty( $arbol ) ) : ?>
					<nav class="centinela-tienda__nav" id="centinela-tienda-sidebar" role="navigation" aria-label="<?php esc_attr_e( 'Filtrar por categoría', 'centinela-group-theme' ); ?>">
						<div class="centinela-tienda__cat-group">
							<a href="<?php echo esc_url( $tienda_base ); ?>" class="centinela-tienda__cat-link centinela-tienda__cat-link--all <?php echo $categoria_id === '' ? 'centinela-tienda__cat-link--active' : ''; ?>" data-categoria-id="" data-cat-path=""><?php esc_html_e( 'Todos los productos', 'centinela-group-theme' ); ?></a>
						</div>
						<?php foreach ( $arbol as $cat ) : ?>
							<?php
							$cat_id    = isset( $cat['id'] ) ? (string) $cat['id'] : '';
							$url_cat   = function_exists( 'centinela_get_tienda_cat_url' ) ? centinela_get_tienda_cat_url( $cat ) : add_query_arg( 'categoria', $cat_id, $tienda_base );
							$cat_path  = function_exists( 'centinela_get_tienda_cat_url' ) ? ( sanitize_title( $cat['nombre'] ) ) : '';
							$is_active = $categoria_id === $cat_id;
							$has_children = ! empty( $cat['hijos'] );
							?>
							<?php if ( $has_children ) : ?>
								<details class="centinela-tienda__cat-details" data-cat-id="<?php echo esc_attr( $cat_id ); ?>">
									<summary class="centinela-tienda__cat-summary"><span class="centinela-tienda__cat-summary-text"><?php echo esc_html( $cat['nombre'] ); ?></span><?php echo $centinela_chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></summary>
									<div class="centinela-tienda__cat-children">
										<ul class="centinela-tienda__sub">
											<?php
											$parent_slug = sanitize_title( $cat['nombre'] );
											foreach ( $cat['hijos'] as $sub ) :
												$sub_id    = isset( $sub['id'] ) ? (string) $sub['id'] : '';
												$url_sub   = function_exists( 'centinela_get_tienda_cat_url' ) ? centinela_get_tienda_cat_url( $sub, $parent_slug ) : add_query_arg( 'categoria', $sub_id, $tienda_base );
												$sub_path  = $parent_slug . '/' . sanitize_title( $sub['nombre'] );
												$sub_active = $categoria_id === $sub_id;
												$sub_has_children = ! empty( $sub['hijos'] );
												?>
												<li>
													<?php if ( $sub_has_children ) : ?>
														<details class="centinela-tienda__cat-details centinela-tienda__cat-details--sub">
															<summary class="centinela-tienda__cat-summary centinela-tienda__cat-summary--sub"><span class="centinela-tienda__cat-summary-text"><?php echo esc_html( $sub['nombre'] ); ?></span><?php echo $centinela_chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></summary>
															<div class="centinela-tienda__cat-children">
																<ul class="centinela-tienda__sub centinela-tienda__sub--deep">
																	<?php foreach ( $sub['hijos'] as $nieto ) :
																		$nieto_id   = isset( $nieto['id'] ) ? (string) $nieto['id'] : '';
																		$url_nieto  = function_exists( 'centinela_get_tienda_cat_url' ) ? centinela_get_tienda_cat_url( $nieto, $sub_path ) : add_query_arg( 'categoria', $nieto_id, $tienda_base );
																		$nieto_path = $sub_path . '/' . sanitize_title( $nieto['nombre'] );
																		$nieto_active = $categoria_id === $nieto_id;
																		?>
																		<li><a href="<?php echo esc_url( $url_nieto ); ?>" class="centinela-tienda__cat-link centinela-tienda__cat-link--nested <?php echo $nieto_active ? 'centinela-tienda__cat-link--active' : ''; ?>" data-categoria-id="<?php echo esc_attr( $nieto_id ); ?>" data-cat-path="<?php echo esc_attr( $nieto_path ); ?>"><?php echo esc_html( $nieto['nombre'] ); ?></a></li>
																	<?php endforeach; ?>
																</ul>
															</div>
														</details>
													<?php else : ?>
														<a href="<?php echo esc_url( $url_sub ); ?>" class="centinela-tienda__cat-link centinela-tienda__cat-link--sub <?php echo $sub_active ? 'centinela-tienda__cat-link--active' : ''; ?>" data-categoria-id="<?php echo esc_attr( $sub_id ); ?>" data-cat-path="<?php echo esc_attr( $sub_path ); ?>"><?php echo esc_html( $sub['nombre'] ); ?></a>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								</details>
							<?php else : ?>
								<div class="centinela-tienda__cat-group">
									<a href="<?php echo esc_url( $url_cat ); ?>" class="centinela-tienda__cat-link <?php echo $is_active ? 'centinela-tienda__cat-link--active' : ''; ?>" data-categoria-id="<?php echo esc_attr( $cat_id ); ?>" data-cat-path="<?php echo esc_attr( $cat_path ); ?>"><?php echo esc_html( $cat['nombre'] ); ?></a>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</nav>
				<?php else : ?>
					<p class="centinela-tienda__empty">
						<?php
						if ( ! $syscom_ok ) {
							esc_html_e( 'Configure la API Syscom en Ajustes → Syscom Colombia para ver las categorías.', 'centinela-group-theme' );
						} else {
							esc_html_e( 'Categorías no disponibles. Compruebe la conexión con la API.', 'centinela-group-theme' );
						}
						?>
					</p>
				<?php endif; ?>

				<?php if ( ! $es_tienda_wc && $syscom_ok ) : ?>
					<!-- Filtro por marca (solo tienda Syscom) -->
					<div class="centinela-tienda__filter-block centinela-tienda__filter-marcas">
						<h3 class="centinela-tienda__filter-title"><?php esc_html_e( 'Marcas', 'centinela-group-theme' ); ?></h3>
						<div id="centinela-tienda-marcas-list" class="centinela-tienda__marcas-list" role="list" aria-label="<?php esc_attr_e( 'Filtrar por marca', 'centinela-group-theme' ); ?>">
							<?php
							$marca_base = ( $cat_path_clean !== '' ) ? home_url( '/tienda/' . trim( $cat_path_clean ) . '/' ) : home_url( '/tienda/' );
							$marca_qs   = array();
							if ( $min_price_actual !== '' ) {
								$marca_qs[] = 'min_price=' . rawurlencode( $min_price_actual );
							}
							if ( $max_price_actual !== '' ) {
								$marca_qs[] = 'max_price=' . rawurlencode( $max_price_actual );
							}
							$marca_all_url = $marca_qs ? $marca_base . '?' . implode( '&', $marca_qs ) : $marca_base;
							?>
							<a href="<?php echo esc_url( $marca_all_url ); ?>" class="centinela-tienda__marca-link <?php echo $marca_actual === '' ? 'centinela-tienda__marca-link--active' : ''; ?>" data-marca=""><?php esc_html_e( 'Todas las marcas', 'centinela-group-theme' ); ?></a>
							<?php foreach ( $marcas_sidebar as $marca_nombre ) : ?>
								<?php
								$qm = array( 'marca' => $marca_nombre );
								if ( $min_price_actual !== '' ) {
									$qm['min_price'] = $min_price_actual;
								}
								if ( $max_price_actual !== '' ) {
									$qm['max_price'] = $max_price_actual;
								}
								$marca_url = add_query_arg( $qm, $marca_base );
								$is_active = $marca_actual === $marca_nombre;
								?>
								<a href="<?php echo esc_url( $marca_url ); ?>" class="centinela-tienda__marca-link <?php echo $is_active ? 'centinela-tienda__marca-link--active' : ''; ?>" data-marca="<?php echo esc_attr( $marca_nombre ); ?>"><?php echo esc_html( $marca_nombre ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Filtro por precio: rangos con links (estilo CozyCorner) -->
					<div class="centinela-tienda__filter-block centinela-tienda__filter-price">
						<h3 class="centinela-tienda__filter-title"><?php esc_html_e( 'Precio', 'centinela-group-theme' ); ?></h3>
						<div id="centinela-tienda-price-ranges" class="centinela-tienda__price-ranges" role="list" aria-label="<?php esc_attr_e( 'Rango de precio', 'centinela-group-theme' ); ?>">
							<?php
							$price_base = ( $cat_path_clean !== '' ) ? home_url( '/tienda/' . trim( $cat_path_clean ) . '/' ) : home_url( '/tienda/' );
							foreach ( $centinela_price_ranges as $range ) :
								$q = array();
								if ( $marca_actual !== '' ) {
									$q['marca'] = $marca_actual;
								}
								if ( $range['min'] !== '' ) {
									$q['min_price'] = $range['min'];
								}
								if ( $range['max'] !== '' ) {
									$q['max_price'] = $range['max'];
								}
								$price_url = empty( $q ) ? $price_base : add_query_arg( $q, $price_base );
								$is_active = ( (string) $min_price_actual === (string) $range['min'] ) && ( (string) $max_price_actual === (string) $range['max'] );
								?>
								<a href="<?php echo esc_url( $price_url ); ?>" class="centinela-tienda__price-range-link <?php echo $is_active ? 'centinela-tienda__price-range-link--active' : ''; ?>" data-min-price="<?php echo esc_attr( $range['min'] ); ?>" data-max-price="<?php echo esc_attr( $range['max'] ); ?>"><?php echo esc_html( $range['label'] ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php
				if ( ! $es_tienda_wc ) {
					$wc_shop_url = function_exists( 'centinela_get_wc_shop_url' ) ? centinela_get_wc_shop_url() : '';
					$wc_shop_is_other_page = $wc_shop_url !== '' && ( ! function_exists( 'centinela_is_wc_shop_same_as_tienda_page' ) || ! centinela_is_wc_shop_same_as_tienda_page() );
					if ( $wc_shop_is_other_page ) :
						?>
						<div class="centinela-tienda__filter-block centinela-tienda__wc-shop-link">
							<h3 class="centinela-tienda__filter-title"><?php esc_html_e( 'Más productos', 'centinela-group-theme' ); ?></h3>
							<a href="<?php echo esc_url( $wc_shop_url ); ?>" class="centinela-tienda__cat-link centinela-tienda__cat-link--wc"><?php esc_html_e( 'Otros productos', 'centinela-group-theme' ); ?></a>
						</div>
						<?php
					endif;
				}
				?>
			</div>
		</aside>

		<main class="centinela-tienda__main flex-grow min-w-0">
			<h1 class="centinela-tienda__main-title screen-reader-text"><?php echo $es_tienda_wc ? esc_html__( 'Productos propios', 'centinela-group-theme' ) : esc_html__( 'Tienda', 'centinela-group-theme' ); ?></h1>
			<?php if ( $es_tienda_wc ) : ?>
				<div class="centinela-tienda__content">
					<?php if ( $wc_products_query->have_posts() ) : ?>
						<div class="centinela-tienda__grid">
							<?php
							while ( $wc_products_query->have_posts() ) {
								$wc_products_query->the_post();
								$product = wc_get_product( get_the_ID() );
								if ( ! $product || ! $product->is_visible() ) {
									continue;
								}
								$url = get_permalink();
								$titulo = get_the_title();
								$img = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
								$precio = $product->get_price_html();
								?>
								<article class="centinela-tienda__card" data-product-id="<?php echo esc_attr( get_the_ID() ); ?>" data-quickview-source="wc">
									<div class="centinela-tienda__card-image-wrap">
										<a href="<?php echo esc_url( $url ); ?>" class="centinela-tienda__card-link centinela-tienda__card-image" aria-label="<?php echo esc_attr( $titulo ); ?>">
											<?php if ( $img ) : ?>
												<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $titulo ); ?>" loading="lazy" />
											<?php else : ?>
												<span class="centinela-tienda__card-placeholder"><?php esc_html_e( 'Sin imagen', 'centinela-group-theme' ); ?></span>
											<?php endif; ?>
										</a>
										<div class="centinela-tienda__card-overlay">
											<button type="button" class="centinela-tienda__quickview-btn" data-product-id="<?php echo esc_attr( get_the_ID() ); ?>" data-quickview-source="wc"><span class="centinela-tienda__overlay-btn-text"><?php esc_html_e( 'Vista rápida', 'centinela-group-theme' ); ?></span><svg class="centinela-header__cta-icon centinela-tienda__overlay-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></button>
											<a href="<?php echo esc_url( $url ); ?>" class="centinela-tienda__add-cart"><span class="centinela-tienda__overlay-btn-text"><?php esc_html_e( 'Ver producto', 'centinela-group-theme' ); ?></span><svg class="centinela-header__cta-icon centinela-tienda__overlay-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
										</div>
									</div>
									<div class="centinela-tienda__card-body">
										<h2 class="centinela-tienda__card-title">
											<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $titulo ); ?></a>
										</h2>
										<?php if ( $precio ) : ?>
											<div class="centinela-tienda__card-price-wrap">
												<p class="centinela-tienda__card-price"><?php echo $precio; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
											</div>
										<?php endif; ?>
									</div>
								</article>
								<?php
							}
							wp_reset_postdata();
							?>
						</div>
						<?php if ( $wc_products_query->max_num_pages > 1 ) : ?>
							<nav class="centinela-tienda__pagination" aria-label="<?php esc_attr_e( 'Paginación', 'centinela-group-theme' ); ?>">
								<?php
								for ( $p = 1; $p <= min( $wc_products_query->max_num_pages, 10 ); $p++ ) :
									$pag_url = $p > 1 ? add_query_arg( 'paged', $p, $tienda_base ) : $tienda_base;
									?>
									<a href="<?php echo esc_url( $pag_url ); ?>" class="centinela-tienda__page-link <?php echo (int) $p === (int) $paged ? 'centinela-tienda__page-link--current' : ''; ?>"><?php echo (int) $p; ?></a>
								<?php endfor; ?>
							</nav>
						<?php endif; ?>
					<?php else : ?>
						<p class="centinela-tienda__empty centinela-tienda__empty--main"><?php esc_html_e( 'No hay productos en esta categoría.', 'centinela-group-theme' ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div id="centinela-tienda-ajax-content" class="centinela-tienda__content" data-categoria="<?php echo esc_attr( $categoria_id ); ?>" data-cat-path="<?php echo esc_attr( $cat_path_clean ); ?>" data-pagina="<?php echo (int) $pagina_actual; ?>" data-ordenar="<?php echo esc_attr( $ordenar ); ?>" data-marca="<?php echo esc_attr( $marca_actual ); ?>" data-min-price="<?php echo esc_attr( $min_price_actual ); ?>" data-max-price="<?php echo esc_attr( $max_price_actual ); ?>">
					<?php
					if ( $productos_html !== '' ) {
						echo $productos_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						if ( ! $syscom_ok ) {
							echo '<p class="centinela-tienda__empty centinela-tienda__empty--main">' . esc_html__( 'Configure la API Syscom en Ajustes → Syscom Colombia para mostrar productos.', 'centinela-group-theme' ) . '</p>';
						} else {
							echo '<p class="centinela-tienda__empty centinela-tienda__empty--main">' . esc_html__( 'No hay productos disponibles. Compruebe la conexión con la API Syscom.', 'centinela-group-theme' ) . '</p>';
						}
					}
					?>
				</div>
			<?php endif; ?>
		</main>
	</div>

	<!-- Modal Vista rápida (tienda Syscom y tienda-centinela WooCommerce) -->
	<div id="centinela-quickview-modal" class="centinela-quickview" role="dialog" aria-modal="true" aria-labelledby="centinela-quickview-title" aria-hidden="true">
		<div class="centinela-quickview__backdrop" data-close-quickview></div>
		<div class="centinela-quickview__box">
			<button type="button" class="centinela-quickview__close" aria-label="<?php esc_attr_e( 'Cerrar', 'centinela-group-theme' ); ?>" data-close-quickview></button>
			<div class="centinela-quickview__inner">
				<div class="centinela-quickview__gallery">
					<div class="centinela-quickview__thumbs" id="centinela-quickview-thumbs"></div>
					<div class="centinela-quickview__main-wrap">
						<div class="centinela-quickview__main-image" id="centinela-quickview-main-area">
							<img src="" alt="" id="centinela-quickview-img" />
						</div>
						<div class="centinela-quickview__zoom-panel" id="centinela-quickview-zoom" aria-hidden="true"></div>
					</div>
				</div>
				<div class="centinela-quickview__info">
					<p id="centinela-quickview-categoria" class="centinela-quickview__categoria"></p>
					<h2 id="centinela-quickview-title" class="centinela-quickview__title"></h2>
					<p id="centinela-quickview-price" class="centinela-quickview__price"></p>
					<div class="centinela-quickview__cart-row">
						<label for="centinela-quickview-qty" class="centinela-quickview__qty-label"><?php esc_html_e( 'Cantidad', 'centinela-group-theme' ); ?></label>
						<input type="number" id="centinela-quickview-qty" class="centinela-quickview__qty" min="1" value="1" />
						<button type="button" id="centinela-quickview-addcart" class="centinela-quickview__btn centinela-quickview__btn--primary"><?php esc_html_e( 'Agregar al carrito', 'centinela-group-theme' ); ?></button>
					</div>
					<p id="centinela-quickview-modelo" class="centinela-quickview__meta"></p>
					<p id="centinela-quickview-marca" class="centinela-quickview__meta"></p>
					<div class="centinela-quickview__actions">
						<a href="#" id="centinela-quickview-link" class="centinela-quickview__btn centinela-quickview__btn--secondary"><?php esc_html_e( 'Ver producto', 'centinela-group-theme' ); ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
get_footer();
