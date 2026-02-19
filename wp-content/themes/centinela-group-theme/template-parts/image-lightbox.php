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
		<button type="button" class="centinela-lightbox__close" aria-label="<?php esc_attr_e( 'Cerrar', 'centinela-group-theme' ); ?>" data-close-lightbox></button>
		<button type="button" class="centinela-lightbox__prev" aria-label="<?php esc_attr_e( 'Imagen anterior', 'centinela-group-theme' ); ?>" id="centinela-lightbox-prev"></button>
		<div class="centinela-lightbox__content">
			<img src="" alt="" id="centinela-lightbox-img" class="centinela-lightbox__img" />
		</div>
		<button type="button" class="centinela-lightbox__next" aria-label="<?php esc_attr_e( 'Imagen siguiente', 'centinela-group-theme' ); ?>" id="centinela-lightbox-next"></button>
	</div>
</div>
