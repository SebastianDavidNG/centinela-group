<?php
/**
 * Lightbox de imagen (estilo CozyCorner): clic en imagen principal abre vista grande con navegación.
 * Se usa desde Vista rápida y desde la página de detalle del producto.
 *
 * @package Centinela_Group_Theme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="centinela-image-lightbox" class="centinela-lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Imagen ampliada', 'centinela-group-theme' ); ?>" aria-hidden="true">
	<div class="centinela-lightbox__backdrop" data-close-lightbox></div>
	<div class="centinela-lightbox__box">
		<button type="button" class="centinela-lightbox__fullscreen" id="centinela-lightbox-fullscreen" aria-label="<?php esc_attr_e( 'Pantalla completa', 'centinela-group-theme' ); ?>" title="<?php esc_attr_e( 'Pantalla completa', 'centinela-group-theme' ); ?>">
			<svg class="centinela-lightbox__fullscreen-icon centinela-lightbox__fullscreen-icon--expand e-font-icon-svg e-eicon-frame-expand" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M9 3H3v6"></path>
				<path d="M15 3h6v6"></path>
				<path d="M21 15v6h-6"></path>
				<path d="M3 15v6h6"></path>
			</svg>
			<svg class="centinela-lightbox__fullscreen-icon centinela-lightbox__fullscreen-icon--compress" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M9 9H3V3"></path>
				<path d="M15 9h6V3"></path>
				<path d="M15 15h6v6"></path>
				<path d="M9 15H3v6"></path>
			</svg>
		</button>
		<button type="button" class="centinela-lightbox__close" aria-label="<?php esc_attr_e( 'Cerrar', 'centinela-group-theme' ); ?>" data-close-lightbox></button>
		<button type="button" class="centinela-lightbox__prev" aria-label="<?php esc_attr_e( 'Imagen anterior', 'centinela-group-theme' ); ?>" id="centinela-lightbox-prev"></button>
		<div class="centinela-lightbox__content">
			<img src="" alt="" id="centinela-lightbox-img" class="centinela-lightbox__img" />
		</div>
		<button type="button" class="centinela-lightbox__next" aria-label="<?php esc_attr_e( 'Imagen siguiente', 'centinela-group-theme' ); ?>" id="centinela-lightbox-next"></button>
	</div>
</div>
