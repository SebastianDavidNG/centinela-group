<?php
/**
 * Cabecera por defecto del tema (estilos según Figma Hero)
 * BEM: .centinela-header
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function centinela_theme_default_header() {
	?>
	<header id="masthead" class="centinela-header site-header">
		<div class="centinela-header__inner">
			<div class="centinela-header__brand site-branding">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<?php bloginfo( 'name' ); ?>
					</a>
					<?php if ( get_bloginfo( 'description' ) ) : ?>
						<span class="site-description"><?php bloginfo( 'description' ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<nav id="site-navigation" class="centinela-header__nav site-navigation" aria-label="<?php esc_attr_e( 'Menú principal', 'centinela-group-theme' ); ?>">
				<?php // En móvil: lupa a la izquierda del hamburguesa (orden visual con CSS) ?>
				<button type="button" class="centinela-header__search centinela-header__search--mobile" aria-label="<?php esc_attr_e( 'Buscar', 'centinela-group-theme' ); ?>" aria-expanded="false" aria-controls="centinela-search-form" id="centinela-search-toggle-mobile">
					<svg class="centinela-header__search-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
				</button>
				<button type="button" class="centinela-header__toggle" aria-expanded="false" aria-controls="primary-menu-mobile" id="menu-toggle">
					<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
				</button>
				<?php
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'menu_id'        => 'primary-menu',
					'menu_class'     => 'centinela-header__menu',
					'container'      => false,
					'fallback_cb'    => false,
					'depth'          => 2,
				) );
				?>
			</nav>
			<?php
			$centinela_cart_page    = get_page_by_path( 'carrito', OBJECT, 'page' );
			$centinela_cart_url     = $centinela_cart_page ? get_permalink( $centinela_cart_page ) : home_url( '/carrito/' );
			$centinela_checkout_page = get_page_by_path( 'finalizar-compra', OBJECT, 'page' );
			$centinela_checkout_url  = $centinela_checkout_page ? get_permalink( $centinela_checkout_page ) : $centinela_cart_url;
			if ( function_exists( 'wc_get_cart_url' ) ) {
				$centinela_cart_url = wc_get_cart_url();
			}
			if ( function_exists( 'wc_get_checkout_url' ) ) {
				$centinela_checkout_url = wc_get_checkout_url();
			}
			$centinela_tienda_url = home_url( '/tienda/' );
			// Forzar HTTP en localhost para evitar redirección a https (Docker/local sin SSL).
			$centinela_cart_url     = function_exists( 'centinela_force_http_on_localhost' ) ? centinela_force_http_on_localhost( $centinela_cart_url ) : $centinela_cart_url;
			$centinela_checkout_url = function_exists( 'centinela_force_http_on_localhost' ) ? centinela_force_http_on_localhost( $centinela_checkout_url ) : $centinela_checkout_url;
			$centinela_tienda_url   = function_exists( 'centinela_force_http_on_localhost' ) ? centinela_force_http_on_localhost( $centinela_tienda_url ) : $centinela_tienda_url;
			?>
			<div class="centinela-header__actions">
				<div class="centinela-header__action-wrap centinela-header__cart-wrap">
					<a href="<?php echo esc_url( $centinela_cart_url ); ?>" class="centinela-header__icon-btn centinela-header__cart-btn" aria-label="<?php esc_attr_e( 'Carrito', 'centinela-group-theme' ); ?>">
						<svg class="centinela-header__icon-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
						<span class="centinela-header__cart-count" id="centinela-header-cart-count" data-count="0">0</span>
					</a>
					<div class="centinela-header__dropdown centinela-header__cart-dropdown" id="centinela-cart-dropdown" aria-hidden="true"
						data-cart-url="<?php echo esc_url( $centinela_cart_url ); ?>"
						data-checkout-url="<?php echo esc_url( $centinela_checkout_url ); ?>"
						data-tienda-url="<?php echo esc_url( $centinela_tienda_url ); ?>">
						<div class="centinela-header__dropdown-inner">
							<h3 class="centinela-header__dropdown-title"><?php esc_html_e( 'Tu carrito', 'centinela-group-theme' ); ?> <span id="centinela-cart-dropdown-count">(0 <?php esc_html_e( 'productos', 'centinela-group-theme' ); ?>)</span></h3>
							<p class="centinela-header__cart-empty" id="centinela-cart-dropdown-empty"><?php esc_html_e( 'Tu carrito está vacío.', 'centinela-group-theme' ); ?></p>
							<div class="centinela-header__cart-content" id="centinela-cart-dropdown-content" style="display: none;">
								<div class="centinela-header__cart-items" id="centinela-cart-dropdown-items"></div>
								<div class="centinela-header__cart-footer">
									<p class="centinela-header__cart-subtotal"><span class="centinela-header__cart-subtotal-label"><?php esc_html_e( 'Subtotal:', 'centinela-group-theme' ); ?></span> <span id="centinela-cart-dropdown-subtotal" class="centinela-header__cart-subtotal-value">0 COP</span></p>
									<a href="<?php echo esc_url( $centinela_checkout_url ); ?>" id="centinela-cart-dropdown-checkout" class="centinela-header__dropdown-cta centinela-header__dropdown-cta--checkout"><?php esc_html_e( 'Finalizar compra', 'centinela-group-theme' ); ?></a>
									<div class="centinela-header__cart-links">
										<a href="<?php echo esc_url( $centinela_cart_url ); ?>" id="centinela-cart-dropdown-view" class="centinela-header__cart-link"><?php esc_html_e( 'Ver carrito', 'centinela-group-theme' ); ?></a>
										<a href="<?php echo esc_url( $centinela_tienda_url ); ?>" id="centinela-cart-dropdown-continue" class="centinela-header__cart-link"><?php esc_html_e( 'Continuar comprando', 'centinela-group-theme' ); ?></a>
									</div>
								</div>
							</div>
							<a href="<?php echo esc_url( $centinela_cart_url ); ?>" class="centinela-header__dropdown-cta centinela-header__cart-empty-cta" id="centinela-cart-dropdown-empty-cta"><?php esc_html_e( 'Ir al carrito', 'centinela-group-theme' ); ?></a>
						</div>
					</div>
				</div>
				<div class="centinela-header__action-wrap centinela-header__account-wrap">
					<a href="<?php echo esc_url( wp_login_url() ); ?>" class="centinela-header__icon-btn centinela-header__account-btn" aria-label="<?php esc_attr_e( 'Cuenta', 'centinela-group-theme' ); ?>">
						<svg class="centinela-header__icon-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
					</a>
					<div class="centinela-header__dropdown centinela-header__account-dropdown" aria-hidden="true">
						<div class="centinela-header__dropdown-inner">
							<?php if ( is_user_logged_in() ) : ?>
								<a href="<?php echo esc_url( get_edit_user_link() ); ?>" class="centinela-header__dropdown-link"><?php esc_html_e( 'Mi cuenta', 'centinela-group-theme' ); ?></a>
								<a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="centinela-header__dropdown-link"><?php esc_html_e( 'Cerrar sesión', 'centinela-group-theme' ); ?></a>
							<?php else : ?>
								<p class="centinela-header__dropdown-label"><?php esc_html_e( 'Iniciar sesión', 'centinela-group-theme' ); ?></p>
								<a href="<?php echo esc_url( wp_login_url() ); ?>" class="centinela-header__dropdown-link"><?php esc_html_e( 'Entrar', 'centinela-group-theme' ); ?></a>
								<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="centinela-header__dropdown-link"><?php esc_html_e( 'Crear cuenta', 'centinela-group-theme' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<a href="#pedir-cotizacion" class="centinela-header__cta centinela-header__cta--desktop">
					<span class="centinela-header__cta-text"><?php esc_html_e( 'Pedir cotización', 'centinela-group-theme' ); ?></span>
					<svg class="centinela-header__cta-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
				</a>
				<button type="button" class="centinela-header__search centinela-header__search--desktop" aria-label="<?php esc_attr_e( 'Buscar', 'centinela-group-theme' ); ?>" aria-expanded="false" aria-controls="centinela-search-form" id="centinela-search-toggle">
					<svg class="centinela-header__search-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
				</button>
			</div>
		</div>
		<?php get_template_part( 'template-parts/header', 'search-overlay' ); ?>
		<div id="primary-menu-mobile" class="centinela-header__menu-mobile centinela-mobile-overlay" aria-hidden="true">
			<div class="centinela-mobile-overlay__backdrop" aria-hidden="true"></div>
			<div class="centinela-mobile-overlay__panel">
				<header class="centinela-mobile-overlay__header">
					<span class="centinela-mobile-overlay__title"><?php esc_html_e( 'Menú', 'centinela-group-theme' ); ?></span>
					<button type="button" class="centinela-mobile-overlay__close" id="centinela-mobile-close" aria-label="<?php esc_attr_e( 'Cerrar menú', 'centinela-group-theme' ); ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
					</button>
				</header>
			<ul id="primary-menu-mobile-list" class="centinela-header__menu centinela-mobile-menu">
				<?php
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'menu_id'        => '',
					'menu_class'     => '',
					'container'      => false,
					'fallback_cb'    => false,
					'items_wrap'     => '%3$s',
				) );
				?>
				<?php
				// Categorías de productos (API Syscom) dentro del menú hamburguesa – solo móvil, estilo Hotlock
				$arbol_mobile = class_exists( 'Centinela_Syscom_API' ) ? Centinela_Syscom_API::get_categorias_arbol() : array();
				if ( ! is_wp_error( $arbol_mobile ) && ! empty( $arbol_mobile ) ) :
					?>
					<li class="centinela-mobile-menu__cat-wrap menu-item-has-children">
						<a href="<?php echo esc_url( home_url( '/tienda/' ) ); ?>" class="centinela-mobile-menu__cat-label"><?php esc_html_e( 'Productos', 'centinela-group-theme' ); ?></a>
						<button type="button" class="centinela-mobile-menu__cat-toggle" aria-expanded="false" aria-controls="centinela-mobile-cats" aria-label="<?php esc_attr_e( 'Ver categorías', 'centinela-group-theme' ); ?>">
							<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 4l4 4 4-4"/></svg>
						</button>
						<ul id="centinela-mobile-cats" class="centinela-mobile-menu__sub sub-menu" aria-hidden="true">
							<?php foreach ( $arbol_mobile as $cat ) : ?>
								<?php
								$url_cat = function_exists( 'centinela_get_tienda_cat_url' ) ? centinela_get_tienda_cat_url( $cat ) : home_url( '/tienda/' ) . '?categoria=' . rawurlencode( $cat['id'] );
								$url_cat = apply_filters( 'centinela_submenu_category_url', $url_cat, $cat );
								$tiene_hijos = ! empty( $cat['hijos'] );
								?>
								<li class="<?php echo $tiene_hijos ? 'menu-item-has-children' : ''; ?>">
									<a href="<?php echo esc_url( $url_cat ); ?>"><?php echo esc_html( $cat['nombre'] ); ?></a>
									<?php if ( $tiene_hijos ) : ?>
										<button type="button" class="centinela-mobile-menu__sub-toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Ver subcategorías', 'centinela-group-theme' ); ?>">
											<svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l4 4 4-4"/></svg>
										</button>
										<ul class="sub-menu" aria-hidden="true">
											<?php
											$parent_slug = sanitize_title( $cat['nombre'] );
											foreach ( $cat['hijos'] as $sub ) :
												$url_sub = function_exists( 'centinela_get_tienda_cat_url' ) ? centinela_get_tienda_cat_url( $sub, $parent_slug ) : home_url( '/tienda/' ) . '?categoria=' . rawurlencode( $sub['id'] );
												$url_sub = apply_filters( 'centinela_submenu_category_url', $url_sub, $sub );
												$tiene_nietos = ! empty( $sub['hijos'] );
												$sub_path = $parent_slug ? $parent_slug . '/' . sanitize_title( $sub['nombre'] ) : sanitize_title( $sub['nombre'] );
												?>
												<li class="<?php echo $tiene_nietos ? 'menu-item-has-children' : ''; ?>">
													<a href="<?php echo esc_url( $url_sub ); ?>"><?php echo esc_html( $sub['nombre'] ); ?></a>
													<?php if ( $tiene_nietos ) : ?>
														<button type="button" class="centinela-mobile-menu__sub-toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Ver más', 'centinela-group-theme' ); ?>">
															<svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l4 4 4-4"/></svg>
														</button>
														<ul class="sub-menu" aria-hidden="true">
															<?php foreach ( $sub['hijos'] as $nieto ) :
																$url_nieto = function_exists( 'centinela_get_tienda_cat_url' ) ? centinela_get_tienda_cat_url( $nieto, $sub_path ) : home_url( '/tienda/' ) . '?categoria=' . rawurlencode( $nieto['id'] );
																$url_nieto = apply_filters( 'centinela_submenu_category_url', $url_nieto, $nieto );
																?>
																<li><a href="<?php echo esc_url( $url_nieto ); ?>"><?php echo esc_html( $nieto['nombre'] ); ?></a></li>
															<?php endforeach; ?>
														</ul>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endif; ?>
			</ul>
			<div class="centinela-mobile-overlay__footer">
				<a href="#pedir-cotizacion" class="centinela-mobile-overlay__cta centinela-mobile-menu-cta" id="centinela-mobile-cta">
					<span><?php esc_html_e( 'Pedir cotización', 'centinela-group-theme' ); ?></span>
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
				</a>
			</div>
			</div>
		</div>
	</header>
	<?php
}

/**
 * Submenú de categorías desde API Syscom Colombia (debajo del header, con subcategorías y estilo Figma).
 */
function centinela_theme_submenu() {
	$arbol = Centinela_Syscom_API::get_categorias_arbol();
	if ( is_wp_error( $arbol ) || empty( $arbol ) ) {
		return;
	}
	?>
	<nav class="centinela-submenu" aria-label="<?php esc_attr_e( 'Categorías de productos', 'centinela-group-theme' ); ?>">
		<div class="centinela-submenu__inner">
			<ul class="centinela-submenu__list">
				<?php
				foreach ( $arbol as $cat ) {
					$url_cat = function_exists( 'centinela_get_tienda_cat_url' ) ? centinela_get_tienda_cat_url( $cat ) : home_url( '/tienda/' ) . '?categoria=' . rawurlencode( $cat['id'] );
					$url_cat = apply_filters( 'centinela_submenu_category_url', $url_cat, $cat );
					$tiene_hijos = ! empty( $cat['hijos'] );
					$item_class  = 'centinela-submenu__item' . ( $tiene_hijos ? ' centinela-submenu__item--has-dropdown' : '' );
					?>
					<li class="<?php echo esc_attr( $item_class ); ?>" <?php echo $tiene_hijos ? ' data-centinela-submenu-id="' . esc_attr( $cat['id'] ) . '"' : ''; ?>>
						<a href="<?php echo esc_url( $url_cat ); ?>" class="centinela-submenu__link"><?php echo esc_html( $cat['nombre'] ); ?></a>
						<?php if ( $tiene_hijos ) : ?>
							<button type="button" class="centinela-submenu__toggle" aria-expanded="false" aria-controls="centinela-submenu-dropdown-<?php echo esc_attr( $cat['id'] ); ?>" aria-label="<?php esc_attr_e( 'Ver subcategorías', 'centinela-group-theme' ); ?>">
								<svg class="centinela-submenu__toggle-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 4l4 4 4-4"/></svg>
							</button>
						<?php endif; ?>
					</li>
					<?php
				}
				?>
			</ul>
			<?php
			$tiene_algún_dropdown = false;
			foreach ( $arbol as $cat ) {
				if ( ! empty( $cat['hijos'] ) ) {
					$tiene_algún_dropdown = true;
					break;
				}
			}
			if ( $tiene_algún_dropdown ) :
				?>
				<div class="centinela-submenu__dropdown-wrap" aria-hidden="true">
					<?php foreach ( $arbol as $cat ) : ?>
						<?php if ( ! empty( $cat['hijos'] ) ) : ?>
							<div id="centinela-submenu-dropdown-<?php echo esc_attr( $cat['id'] ); ?>" class="centinela-submenu__dropdown" role="group" aria-label="<?php echo esc_attr( $cat['nombre'] ); ?>" data-centinela-submenu-id="<?php echo esc_attr( $cat['id'] ); ?>">
								<h3 class="centinela-submenu__dropdown-title"><?php echo esc_html( $cat['nombre'] ); ?></h3>
								<div class="centinela-submenu__groups">
									<?php
									$parent_slug_n1 = sanitize_title( $cat['nombre'] );
									foreach ( $cat['hijos'] as $sub ) :
										$url_sub = function_exists( 'centinela_get_tienda_cat_url' ) ? centinela_get_tienda_cat_url( $sub, $parent_slug_n1 ) : home_url( '/tienda/' ) . '?categoria=' . rawurlencode( $sub['id'] );
										$url_sub = apply_filters( 'centinela_submenu_category_url', $url_sub, $sub );
										$tiene_nietos = ! empty( $sub['hijos'] );
										$parent_slug_n2 = $parent_slug_n1 ? $parent_slug_n1 . '/' . sanitize_title( $sub['nombre'] ) : sanitize_title( $sub['nombre'] );
										?>
										<div class="centinela-submenu__group">
											<?php if ( $tiene_nietos ) : ?>
												<h4 class="centinela-submenu__group-title"><?php echo esc_html( $sub['nombre'] ); ?></h4>
												<ul class="centinela-submenu__sublist">
													<?php foreach ( $sub['hijos'] as $nieto ) :
														$url_nieto = function_exists( 'centinela_get_tienda_cat_url' ) ? centinela_get_tienda_cat_url( $nieto, $parent_slug_n2 ) : home_url( '/tienda/' ) . '?categoria=' . rawurlencode( $nieto['id'] );
														$url_nieto = apply_filters( 'centinela_submenu_category_url', $url_nieto, $nieto );
														?>
														<li class="centinela-submenu__subitem">
															<a href="<?php echo esc_url( $url_nieto ); ?>" class="centinela-submenu__sublink"><?php echo esc_html( $nieto['nombre'] ); ?></a>
														</li>
													<?php endforeach; ?>
												</ul>
											<?php else : ?>
												<ul class="centinela-submenu__sublist">
													<li class="centinela-submenu__subitem">
														<a href="<?php echo esc_url( $url_sub ); ?>" class="centinela-submenu__sublink"><?php echo esc_html( $sub['nombre'] ); ?></a>
													</li>
												</ul>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</nav>
	<?php
}
