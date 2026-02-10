<?php
/**
 * Template Name: Finalizar compra (Checkout)
 * Plantilla: Página Checkout – resumen desde localStorage (carrito Centinela), sin shortcode WC.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_script(
	'centinela-checkout-page',
	get_template_directory_uri() . '/assets/js/checkout-page.js',
	array( 'centinela-theme-script' ),
	defined( 'CENTINELA_THEME_VERSION' ) ? CENTINELA_THEME_VERSION : '1.0.0',
	true
);

$checkout_id       = get_queried_object_id();
$checkout_title    = $checkout_id ? get_the_title( $checkout_id ) : get_the_title();
$checkout_hero_img = $checkout_id ? get_the_post_thumbnail_url( $checkout_id, 'full' ) : get_the_post_thumbnail_url( get_the_ID(), 'full' );
$tienda_url        = home_url( '/tienda/' );
$carrito_url       = home_url( '/carrito/' );
if ( function_exists( 'centinela_force_http_on_localhost' ) ) {
	$tienda_url  = centinela_force_http_on_localhost( $tienda_url );
	$carrito_url = centinela_force_http_on_localhost( $carrito_url );
}

get_header();

get_template_part( 'template-parts/hero', 'page-inner', array(
	'title'      => $checkout_title,
	'breadcrumb' => true,
	'image_url'  => $checkout_hero_img ? $checkout_hero_img : '',
) );
?>

<main id="content" class="site-main flex-grow">
	<div class="centinela-checkout centinela-container">
		<div id="centinela-checkout-empty" class="centinela-checkout__empty" style="display: none;">
			<p class="centinela-checkout__empty-text"><?php esc_html_e( 'Tu carrito está vacío. Añade productos desde la tienda para finalizar tu compra.', 'centinela-group-theme' ); ?></p>
			<a href="<?php echo esc_url( $tienda_url ); ?>" class="centinela-btn centinela-btn--primary"><?php esc_html_e( 'Ir a la tienda', 'centinela-group-theme' ); ?></a>
		</div>

		<div id="centinela-checkout-content" class="centinela-checkout__content" style="display: none;">
			<h2 class="centinela-checkout__section-title"><?php esc_html_e( 'Resumen de tu pedido', 'centinela-group-theme' ); ?></h2>
			<div id="centinela-checkout-list" class="centinela-checkout__list"></div>
			<p class="centinela-checkout__subtotal-row">
				<strong><?php esc_html_e( 'Subtotal', 'centinela-group-theme' ); ?></strong>
				<span id="centinela-checkout-subtotal" class="centinela-checkout__subtotal-value">0 COP</span>
			</p>
			<p class="centinela-checkout__edit-cart">
				<a href="<?php echo esc_url( $carrito_url ); ?>" class="centinela-btn centinela-cart__btn centinela-cart__btn--secondary"><?php esc_html_e( 'Editar carrito', 'centinela-group-theme' ); ?></a>
			</p>

			<form id="centinela-checkout-form" class="centinela-checkout-form" action="" method="post" novalidate>
				<fieldset class="centinela-checkout-form__fieldset">
					<legend class="centinela-checkout-form__legend"><?php esc_html_e( 'Datos de contacto', 'centinela-group-theme' ); ?></legend>
					<div class="centinela-checkout-form__grid centinela-checkout-form__grid--2">
						<p class="centinela-checkout-form__field">
							<label for="centinela-checkout-nombre" class="centinela-checkout-form__label"><?php esc_html_e( 'Nombre completo', 'centinela-group-theme' ); ?> <span class="centinela-checkout-form__required">*</span></label>
							<input type="text" id="centinela-checkout-nombre" name="centinela_nombre" class="centinela-checkout-form__input" required autocomplete="name" placeholder="<?php esc_attr_e( 'Ej. Juan Pérez', 'centinela-group-theme' ); ?>">
							<span class="centinela-checkout-form__error" id="centinela-checkout-nombre-error" role="alert"></span>
						</p>
						<p class="centinela-checkout-form__field">
							<label for="centinela-checkout-email" class="centinela-checkout-form__label"><?php esc_html_e( 'Correo electrónico', 'centinela-group-theme' ); ?> <span class="centinela-checkout-form__required">*</span></label>
							<input type="email" id="centinela-checkout-email" name="centinela_email" class="centinela-checkout-form__input" required autocomplete="email" placeholder="<?php esc_attr_e( 'correo@ejemplo.com', 'centinela-group-theme' ); ?>">
							<span class="centinela-checkout-form__error" id="centinela-checkout-email-error" role="alert"></span>
						</p>
					</div>
					<div class="centinela-checkout-form__grid centinela-checkout-form__grid--1">
						<p class="centinela-checkout-form__field">
							<label for="centinela-checkout-telefono" class="centinela-checkout-form__label"><?php esc_html_e( 'Teléfono', 'centinela-group-theme' ); ?> <span class="centinela-checkout-form__required">*</span></label>
							<input type="tel" id="centinela-checkout-telefono" name="centinela_telefono" class="centinela-checkout-form__input" required autocomplete="tel" placeholder="<?php esc_attr_e( 'Ej. 300 123 4567', 'centinela-group-theme' ); ?>">
							<span class="centinela-checkout-form__error" id="centinela-checkout-telefono-error" role="alert"></span>
						</p>
						<p class="centinela-checkout-form__field">
							<label for="centinela-checkout-notas" class="centinela-checkout-form__label"><?php esc_html_e( 'Notas del pedido', 'centinela-group-theme' ); ?></label>
							<textarea id="centinela-checkout-notas" name="centinela_notas" class="centinela-checkout-form__input centinela-checkout-form__textarea" rows="3" autocomplete="off" placeholder="<?php esc_attr_e( 'Instrucciones especiales, horario de entrega, etc.', 'centinela-group-theme' ); ?>"></textarea>
						</p>
					</div>
				</fieldset>

				<fieldset class="centinela-checkout-form__fieldset">
					<legend class="centinela-checkout-form__legend"><?php esc_html_e( 'Dirección de envío', 'centinela-group-theme' ); ?></legend>
					<div class="centinela-checkout-form__grid centinela-checkout-form__grid--1">
						<p class="centinela-checkout-form__field">
							<label for="centinela-checkout-direccion" class="centinela-checkout-form__label"><?php esc_html_e( 'Dirección', 'centinela-group-theme' ); ?> <span class="centinela-checkout-form__required">*</span></label>
							<input type="text" id="centinela-checkout-direccion" name="centinela_direccion" class="centinela-checkout-form__input" required autocomplete="street-address" placeholder="<?php esc_attr_e( 'Calle, número, barrio', 'centinela-group-theme' ); ?>">
							<span class="centinela-checkout-form__error" id="centinela-checkout-direccion-error" role="alert"></span>
						</p>
						<p class="centinela-checkout-form__field">
							<label for="centinela-checkout-complemento" class="centinela-checkout-form__label"><?php esc_html_e( 'Complemento (opcional)', 'centinela-group-theme' ); ?></label>
							<input type="text" id="centinela-checkout-complemento" name="centinela_complemento" class="centinela-checkout-form__input" autocomplete="address-line2" placeholder="<?php esc_attr_e( 'Apto, piso, oficina, etc.', 'centinela-group-theme' ); ?>">
						</p>
					</div>
					<div class="centinela-checkout-form__grid centinela-checkout-form__grid--3">
						<p class="centinela-checkout-form__field">
							<label for="centinela-checkout-ciudad" class="centinela-checkout-form__label"><?php esc_html_e( 'Ciudad', 'centinela-group-theme' ); ?> <span class="centinela-checkout-form__required">*</span></label>
							<input type="text" id="centinela-checkout-ciudad" name="centinela_ciudad" class="centinela-checkout-form__input" required autocomplete="address-level2" placeholder="<?php esc_attr_e( 'Ej. Bogotá', 'centinela-group-theme' ); ?>">
							<span class="centinela-checkout-form__error" id="centinela-checkout-ciudad-error" role="alert"></span>
						</p>
						<p class="centinela-checkout-form__field">
							<label for="centinela-checkout-departamento" class="centinela-checkout-form__label"><?php esc_html_e( 'Departamento', 'centinela-group-theme' ); ?> <span class="centinela-checkout-form__required">*</span></label>
							<select id="centinela-checkout-departamento" name="centinela_departamento" class="centinela-checkout-form__input centinela-checkout-form__select" required autocomplete="address-level1">
								<option value=""><?php esc_html_e( 'Seleccione', 'centinela-group-theme' ); ?></option>
								<?php
								$departamentos = array(
									'Amazonas', 'Antioquia', 'Arauca', 'Atlántico', 'Bogotá D.C.', 'Bolívar', 'Boyacá', 'Caldas', 'Caquetá', 'Casanare', 'Cauca', 'Cesar', 'Chocó', 'Córdoba', 'Cundinamarca', 'Guainía', 'Guaviare', 'Huila', 'La Guajira', 'Magdalena', 'Meta', 'Nariño', 'Norte de Santander', 'Putumayo', 'Quindío', 'Risaralda', 'San Andrés y Providencia', 'Santander', 'Sucre', 'Tolima', 'Valle del Cauca', 'Vaupés', 'Vichada',
								);
								foreach ( $departamentos as $d ) {
									echo '<option value="' . esc_attr( $d ) . '">' . esc_html( $d ) . '</option>';
								}
								?>
							</select>
							<span class="centinela-checkout-form__error" id="centinela-checkout-departamento-error" role="alert"></span>
						</p>
						<p class="centinela-checkout-form__field">
							<label for="centinela-checkout-codigo-postal" class="centinela-checkout-form__label"><?php esc_html_e( 'Código postal (opcional)', 'centinela-group-theme' ); ?></label>
							<input type="text" id="centinela-checkout-codigo-postal" name="centinela_codigo_postal" class="centinela-checkout-form__input" autocomplete="postal-code" placeholder="<?php esc_attr_e( 'Ej. 110111', 'centinela-group-theme' ); ?>">
						</p>
					</div>
					<div class="centinela-checkout-form__grid centinela-checkout-form__grid--1">
						<p class="centinela-checkout-form__field">
							<label for="centinela-checkout-pais" class="centinela-checkout-form__label"><?php esc_html_e( 'País', 'centinela-group-theme' ); ?></label>
							<input type="text" id="centinela-checkout-pais" name="centinela_pais" class="centinela-checkout-form__input" value="<?php echo esc_attr( 'Colombia' ); ?>" readonly autocomplete="country-name">
						</p>
					</div>
				</fieldset>

				<div class="centinela-checkout-form__payment" id="centinela-checkout-payment-section">
					<h3 class="centinela-checkout-form__payment-title"><?php esc_html_e( 'Método de pago', 'centinela-group-theme' ); ?></h3>
					<div id="centinela-checkout-payment-methods" class="centinela-checkout-form__payment-methods" aria-live="polite">
						<p class="centinela-checkout-form__payment-placeholder"><?php esc_html_e( 'Aquí se mostrarán las opciones de pago cuando estén configuradas. Seleccione un método para confirmar su compra.', 'centinela-group-theme' ); ?></p>
					</div>
					<input type="hidden" name="centinela_metodo_pago" id="centinela-checkout-metodo-pago" value="">
				</div>

				<p class="centinela-checkout-form__submit-wrap">
					<button type="submit" id="centinela-checkout-submit" class="centinela-btn centinela-btn--primary centinela-cart__btn centinela-checkout-form__submit">
						<?php esc_html_e( 'Confirmar pedido', 'centinela-group-theme' ); ?>
					</button>
				</p>
			</form>
		</div>
	</div>
</main>

<?php
get_footer();
