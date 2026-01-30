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
				<button type="button" class="centinela-header__toggle" aria-expanded="false" aria-controls="primary-menu" id="menu-toggle">
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
			<div class="centinela-header__actions">
				<a href="#pedir-cotizacion" class="centinela-header__cta">
					<span class="centinela-header__cta-text"><?php esc_html_e( 'Pedir cotización', 'centinela-group-theme' ); ?></span>
					<svg class="centinela-header__cta-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
				</a>
				<button type="button" class="centinela-header__search" aria-label="<?php esc_attr_e( 'Buscar', 'centinela-group-theme' ); ?>" aria-expanded="false" aria-controls="centinela-search-form" id="centinela-search-toggle">
					<svg class="centinela-header__search-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
				</button>
			</div>
		</div>
		<?php get_template_part( 'template-parts/header', 'search-overlay' ); ?>
		<div id="primary-menu-mobile" class="centinela-header__menu-mobile" aria-hidden="true">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'menu_id'        => 'primary-menu-mobile-list',
				'menu_class'     => 'centinela-header__menu',
				'container'      => false,
				'fallback_cb'    => false,
			) );
			?>
		</div>
	</header>
	<?php
}

/**
 * Submenú de categorías desde API Syscom Colombia (debajo del header, estilo Figma).
 */
function centinela_theme_submenu() {
	$categorias = Centinela_Syscom_API::get_categorias();
	if ( is_wp_error( $categorias ) || empty( $categorias ) ) {
		return;
	}
	?>
	<nav class="centinela-submenu" aria-label="<?php esc_attr_e( 'Categorías de productos', 'centinela-group-theme' ); ?>">
		<div class="centinela-submenu__inner">
			<ul class="centinela-submenu__list">
				<?php
				foreach ( $categorias as $index => $cat ) {
					$url = apply_filters( 'centinela_submenu_category_url', home_url( '/productos/' ) . '?categoria=' . rawurlencode( $cat['id'] ), $cat );
					?>
					<li class="centinela-submenu__item">
						<a href="<?php echo esc_url( $url ); ?>" class="centinela-submenu__link"><?php echo esc_html( $cat['nombre'] ); ?></a>
					</li>
					<?php
				}
				?>
			</ul>
		</div>
	</nav>
	<?php
}
