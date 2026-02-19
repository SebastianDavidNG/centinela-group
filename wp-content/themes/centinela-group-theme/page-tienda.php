<?php
/**
 * Template Name: Tienda (Centinela)
 * Plantilla: Página Tienda (estilo Hotlock).
 * Hero + sidebar categorías Syscom + grid de productos (API Syscom).
 * Asignar esta plantilla a la página que quieras usar como /tienda.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_script(
	'centinela-tienda-ajax',
	get_template_directory_uri() . '/assets/js/tienda-ajax.js',
	array(),
	defined( 'CENTINELA_THEME_VERSION' ) ? CENTINELA_THEME_VERSION : '1.0.0',
	true
);
wp_enqueue_script(
	'centinela-tienda-quickview',
	get_template_directory_uri() . '/assets/js/tienda-quickview.js',
	array( 'centinela-tienda-ajax', 'centinela-image-lightbox' ),
	defined( 'CENTINELA_THEME_VERSION' ) ? CENTINELA_THEME_VERSION : '1.0.0',
	true
);

get_header();

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
$tienda_base   = home_url( '/tienda/' );
$productos_html = function_exists( 'centinela_tienda_render_productos_html' ) ? centinela_tienda_render_productos_html( $categoria_id, $pagina_actual, $ordenar, $cat_path_clean ) : '';

// Mismo SVG que el botón "Pedir cotización" (uniformidad en el sitio)
$centinela_chevron_svg = '<svg class="centinela-header__cta-icon centinela-tienda__cat-summary-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';

// Hero: si la página está hecha con Elementor, no mostrar el hero del template para que uses el widget "Hero página interna" arriba; si no, mostrar hero con imagen destacada de la página.
$tienda_page_id = get_queried_object_id();
$tienda_built_with_elementor = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->db->is_built_with_elementor( $tienda_page_id );
if ( ! $tienda_built_with_elementor ) {
	$hero_image = $tienda_page_id ? get_the_post_thumbnail_url( $tienda_page_id, 'full' ) : '';
	get_template_part( 'template-parts/hero', 'page-inner', array(
		'title'     => _x( 'Tienda', 'page title', 'centinela-group-theme' ),
		'image_url' => $hero_image ? $hero_image : '',
	) );
}

// Área de contenido para Elementor (obligatorio para que Elementor pueda editar la página). Si usas Elementor, pon aquí el widget "Hero página interna" arriba.
while ( have_posts() ) :
	the_post();
	?>
	<div class="centinela-tienda-elementor-content">
		<?php the_content(); ?>
	</div>
	<?php
endwhile;
rewind_posts();
?>

<div class="centinela-tienda">
	<div class="centinela-tienda__inner max-w-7xl mx-auto px-4 py-8 md:py-12 flex flex-col md:flex-row gap-8">
		<aside class="centinela-tienda__sidebar w-full md:w-64 flex-shrink-0" role="navigation" aria-label="<?php esc_attr_e( 'Categorías de la tienda', 'centinela-group-theme' ); ?>">
			<div class="centinela-tienda__filters sticky top-24">
				<h2 class="centinela-tienda__sidebar-title"><?php esc_html_e( 'Categorías', 'centinela-group-theme' ); ?></h2>
				<?php if ( ! empty( $arbol ) ) : ?>
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
			</div>
		</aside>

		<main class="centinela-tienda__main flex-grow min-w-0">
			<h1 class="centinela-tienda__main-title screen-reader-text"><?php esc_html_e( 'Tienda', 'centinela-group-theme' ); ?></h1>
			<div id="centinela-tienda-ajax-content" class="centinela-tienda__content" data-categoria="<?php echo esc_attr( $categoria_id ); ?>" data-cat-path="<?php echo esc_attr( $cat_path_clean ); ?>" data-pagina="<?php echo (int) $pagina_actual; ?>" data-ordenar="<?php echo esc_attr( $ordenar ); ?>">
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
		</main>
	</div>

	<!-- Modal Vista rápida (estilo CozyCorner) -->
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
