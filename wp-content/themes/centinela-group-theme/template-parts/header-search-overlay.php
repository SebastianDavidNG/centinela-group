<?php
/**
 * Overlay de búsqueda (estilo WiseGuard)
 * Se muestra al hacer clic en el icono de lupa; el formulario se implementará después.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="centinela-search-overlay" class="centinela-search-overlay" aria-hidden="true" hidden>
	<div class="centinela-search-overlay__inner">
		<form role="search" method="get" class="centinela-search-overlay__form" action="<?php echo esc_url( home_url( '/' ) ); ?>" id="centinela-search-form">
			<label for="centinela-search-field" class="screen-reader-text"><?php esc_html_e( 'Buscar', 'centinela-group-theme' ); ?></label>
			<input type="search" id="centinela-search-field" class="centinela-search-overlay__input" placeholder="<?php esc_attr_e( 'Buscar…', 'centinela-group-theme' ); ?>" value="<?php echo get_search_query(); ?>" name="s" autocomplete="off" />
			<button type="submit" class="centinela-search-overlay__submit" aria-label="<?php esc_attr_e( 'Enviar búsqueda', 'centinela-group-theme' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
			</button>
		</form>
		<button type="button" class="centinela-search-overlay__close" aria-label="<?php esc_attr_e( 'Cerrar búsqueda', 'centinela-group-theme' ); ?>" id="centinela-search-close">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
		</button>
	</div>
</div>
