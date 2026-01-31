<?php
/**
 * Modal para reproducir video (YouTube, Vimeo, .mp4) – ref. WiseGuard
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="centinela-video-modal" class="centinela-video-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Reproducir video', 'centinela-group-theme' ); ?>" aria-hidden="true" hidden>
	<div class="centinela-video-modal__backdrop" id="centinela-video-modal-backdrop" aria-hidden="true"></div>
	<div class="centinela-video-modal__inner">
		<button type="button" class="centinela-video-modal__close" id="centinela-video-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'centinela-group-theme' ); ?>">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
		</button>
		<div class="centinela-video-modal__player" id="centinela-video-modal-player"></div>
	</div>
</div>
