<?php
/**
 * Plantilla 404
 *
 * @package Centinela_Group_Theme
 */

get_header();
?>

<div class="container mx-auto px-4 py-16 md:py-24 text-center">
	<div class="max-w-xl mx-auto">
		<h1 class="text-6xl md:text-8xl font-bold text-gray-200">404</h1>
		<h2 class="text-2xl md:text-3xl font-semibold text-gray-800 mt-4"><?php esc_html_e( 'Página no encontrada', 'centinela-group-theme' ); ?></h2>
		<p class="text-gray-600 mt-4"><?php esc_html_e( 'El contenido que buscas no existe o ha sido movido.', 'centinela-group-theme' ); ?></p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="inline-block mt-8 px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 no-underline font-medium">
			<?php esc_html_e( 'Volver al inicio', 'centinela-group-theme' ); ?>
		</a>
	</div>
</div>

<?php
get_footer();
