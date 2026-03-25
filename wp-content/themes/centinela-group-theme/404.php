<?php
/**
 * Plantilla 404
 *
 * @package Centinela_Group_Theme
 */

get_header();
?>

<main class="centinela-404" role="main">
	<div class="centinela-404__inner">
		<p class="centinela-404__code" aria-hidden="true">404</p>
		<h1 class="centinela-404__title"><?php esc_html_e( 'No encontramos esta página', 'centinela-group-theme' ); ?></h1>
		<p class="centinela-404__lead"><?php esc_html_e( 'La URL puede haber cambiado, estar desactualizada o no existir. Puedes volver al inicio o ir directo a la tienda.', 'centinela-group-theme' ); ?></p>

		<div class="centinela-404__actions">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="centinela-404__btn centinela-404__btn--primary">
				<?php esc_html_e( 'Volver al inicio', 'centinela-group-theme' ); ?>
			</a>
			<a href="<?php echo esc_url( home_url( '/tienda/' ) ); ?>" class="centinela-404__btn centinela-404__btn--ghost">
				<?php esc_html_e( 'Ir a la tienda', 'centinela-group-theme' ); ?>
			</a>
		</div>

		<form role="search" method="get" class="centinela-404__search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label for="centinela-404-search" class="screen-reader-text"><?php esc_html_e( 'Buscar', 'centinela-group-theme' ); ?></label>
			<input id="centinela-404-search" type="search" name="s" class="centinela-404__search-input" placeholder="<?php esc_attr_e( 'Buscar por producto, modelo o marca...', 'centinela-group-theme' ); ?>" />
			<button type="submit" class="centinela-404__search-btn"><?php esc_html_e( 'Buscar', 'centinela-group-theme' ); ?></button>
		</form>
	</div>
</main>

<?php
get_footer();
