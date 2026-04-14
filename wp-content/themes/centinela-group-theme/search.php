<?php
/**
 * Plantilla de resultados de búsqueda.
 * Muestra primero productos (API Syscom) y luego contenido del tema (entradas, páginas, testimonios).
 * Coincidencias flexibles para referencias (ej. DS-KIS203-T y DS KIS203 T).
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$search_query = get_search_query();
$productos    = array();
if ( $search_query !== '' && strlen( $search_query ) >= 2 && function_exists( 'centinela_search_productos_syscom' ) ) {
	$is_brand_query  = preg_match( '/^[a-zA-Z\\s]{4,}$/', (string) $search_query ) === 1;
	// Marca larga: más ítems + modo rápido (enlace a /tienda/?marca= sigue en plantilla).
	// Término corto o mixto (p. ej. "UPS", "APC", modelos): misma API busqueda que Syscom global_search; más cupo que el mínimo 12.
	$limit_productos = $is_brand_query ? 120 : 72;
	$fast            = $is_brand_query;
	$productos       = centinela_search_productos_syscom( $search_query, $limit_productos, $fast );
}

get_header();

// Hero igual que en Tienda para que la parte superior no se vea vacía. Sin breadcrumb en resultados.
$search_hero_title = $search_query !== '' ? sprintf( __( 'Resultados de la búsqueda para: %s', 'centinela-group-theme' ), $search_query ) : __( 'Búsqueda', 'centinela-group-theme' );
get_template_part( 'template-parts/hero', 'page-inner', array(
	'title'     => $search_hero_title,
	'image_url' => '',
	'breadcrumb' => false,
) );
?>

<div class="centinela-search-results container mx-auto px-4 py-8 md:py-12">
	<div class="max-w-6xl mx-auto">
		<header class="centinela-search-results__header mb-8 screen-reader-text">
			<h2 class="text-2xl md:text-3xl font-bold text-gray-900">
				<?php
				if ( $search_query !== '' ) {
					printf(
						/* translators: %s: search query */
						esc_html__( 'Resultados de búsqueda para: %s', 'centinela-group-theme' ),
						'<span class="centinela-search-results__query">' . esc_html( $search_query ) . '</span>'
					);
				} else {
					esc_html_e( 'Búsqueda', 'centinela-group-theme' );
				}
				?>
			</h2>
		</header>

		<?php if ( $search_query === '' ) : ?>
			<section class="py-8 text-center">
				<p class="text-gray-600"><?php esc_html_e( 'Escriba algo en el buscador para buscar contenido y productos.', 'centinela-group-theme' ); ?></p>
			</section>
		<?php else : ?>
			<?php $had_content = have_posts(); ?>

			<?php if ( ! empty( $productos ) ) : ?>
				<section class="centinela-search-results__productos <?php echo $had_content ? 'mb-12' : ''; ?>" aria-label="<?php esc_attr_e( 'Productos', 'centinela-group-theme' ); ?>">
					<h2 class="text-xl font-semibold text-gray-900 mb-4"><?php esc_html_e( 'Productos', 'centinela-group-theme' ); ?></h2>
					<?php if ( isset( $is_brand_query ) && $is_brand_query ) : ?>
						<?php
						$tienda_base = home_url( '/tienda/' );
						$marca_url  = add_query_arg( 'marca', rawurlencode( (string) $search_query ), $tienda_base );
						?>
						<p class="text-sm text-gray-600 mb-6">
							<a class="font-semibold text-gray-900 hover:underline" href="<?php echo esc_url( $marca_url ); ?>">
								Ver todos los productos de <?php echo esc_html( $search_query ); ?>
							</a>
						</p>
					<?php endif; ?>
					<div class="centinela-tienda__grid centinela-search-results__grid grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-4 md:gap-6">
						<?php foreach ( $productos as $prod ) : ?>
							<?php
							$url    = isset( $prod['url'] ) ? $prod['url'] : home_url( '/tienda/producto/' . ( isset( $prod['id'] ) ? $prod['id'] : '' ) . '/' );
							$titulo = isset( $prod['titulo'] ) ? $prod['titulo'] : '';
							$modelo = isset( $prod['modelo'] ) ? $prod['modelo'] : '';
							$marca  = isset( $prod['marca'] ) ? (string) $prod['marca'] : '';
							$img    = isset( $prod['imagen'] ) ? trim( (string) $prod['imagen'] ) : '';
							if ( $img === '' && function_exists( 'centinela_syscom_imagen_no_disponible_url' ) ) {
								$img = centinela_syscom_imagen_no_disponible_url();
							}
							$precio = isset( $prod['precio_lista'] ) ? $prod['precio_lista'] : 0.0;

							$marca_url = '';
							if ( $marca !== '' ) {
								$marca_url = add_query_arg( 'marca', rawurlencode( $marca ), home_url( '/tienda/' ) );
							}
							?>
							<article class="centinela-tienda__card centinela-search-results__card">
								<div class="centinela-tienda__card-image-wrap">
									<a href="<?php echo esc_url( $url ); ?>" class="centinela-tienda__card-link centinela-tienda__card-image" aria-label="<?php echo esc_attr( $titulo ); ?>">
										<?php if ( $img !== '' ) : ?>
											<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $titulo ); ?>" loading="lazy" />
										<?php else : ?>
											<span class="centinela-tienda__card-placeholder"><?php esc_html_e( 'Sin imagen', 'centinela-group-theme' ); ?></span>
										<?php endif; ?>
									</a>
								</div>
								<div class="centinela-tienda__card-body">
									<h3 class="centinela-tienda__card-title">
										<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $titulo ); ?></a>
									</h3>
									<?php if ( $modelo !== '' ) : ?>
										<p class="centinela-tienda__card-modelo text-sm text-gray-500"><?php echo esc_html( $modelo ); ?></p>
									<?php endif; ?>
									<?php if ( $marca_url !== '' ) : ?>
										<p class="centinela-tienda__card-marca mt-1">
											<a class="centinela-tienda__card-marca-link" href="<?php echo esc_url( $marca_url ); ?>">
												<?php echo esc_html( $marca ); ?>
											</a>
										</p>
									<?php endif; ?>
									<?php if ( $precio > 0 && function_exists( 'centinela_format_precio_cop' ) ) : ?>
										<p class="centinela-tienda__card-price mt-2 font-semibold text-gray-900"><?php echo esc_html( centinela_format_precio_cop( $precio ) ); ?></p>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $had_content ) : ?>
				<section class="centinela-search-results__content mb-12" aria-label="<?php esc_attr_e( 'Contenido del sitio', 'centinela-group-theme' ); ?>">
					<h2 class="text-xl font-semibold text-gray-900 mb-4"><?php esc_html_e( 'Contenido del sitio', 'centinela-group-theme' ); ?></h2>
					<div class="space-y-6">
						<?php
						while ( have_posts() ) :
							the_post();
							get_template_part( 'template-parts/content', get_post_type() );
						endwhile;
						?>
					</div>
					<?php
					the_posts_pagination( array(
						'mid_size'  => 2,
						'prev_text' => __( '&larr; Anterior', 'centinela-group-theme' ),
						'next_text' => __( 'Siguiente &rarr;', 'centinela-group-theme' ),
					) );
					?>
				</section>
			<?php endif; ?>

			<?php if ( ! $had_content && empty( $productos ) ) : ?>
				<section class="py-12 text-center">
					<p class="text-gray-600 text-lg mb-2"><?php esc_html_e( 'No se encontraron resultados para tu búsqueda.', 'centinela-group-theme' ); ?></p>
					<p class="text-gray-500"><?php esc_html_e( 'Prueba con otras palabras o con una referencia de producto (ej. nombre o modelo como DS-KIS203-T).', 'centinela-group-theme' ); ?></p>
				</section>
			<?php endif; ?>

		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
