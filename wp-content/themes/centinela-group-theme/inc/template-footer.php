<?php
/**
 * Pie de página por defecto del tema
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function centinela_theme_default_footer() {
	?>
	<footer id="colophon" class="site-footer bg-gray-900 text-gray-300 mt-auto">
		<div class="container mx-auto px-4 py-8 md:py-12">
			<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
				<div>
					<?php if ( has_custom_logo() ) : ?>
						<?php the_custom_logo(); ?>
					<?php else : ?>
						<span class="text-lg font-semibold text-white"><?php bloginfo( 'name' ); ?></span>
					<?php endif; ?>
					<?php if ( get_bloginfo( 'description' ) ) : ?>
						<p class="mt-2 text-sm"><?php bloginfo( 'description' ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( has_nav_menu( 'footer' ) ) : ?>
					<div>
						<h3 class="text-sm font-semibold text-white uppercase tracking-wider mb-4"><?php esc_html_e( 'Enlaces', 'centinela-group-theme' ); ?></h3>
						<?php
						wp_nav_menu( array(
							'theme_location' => 'footer',
							'menu_class'     => 'space-y-2 list-none m-0 p-0',
							'container'      => false,
						) );
						?>
					</div>
				<?php endif; ?>
				<div>
					<p class="text-sm">&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'Todos los derechos reservados.', 'centinela-group-theme' ); ?></p>
				</div>
			</div>
		</div>
	</footer>
	<?php
}
