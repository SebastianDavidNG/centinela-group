<?php
/**
 * Pie de página del tema – Estructura y diseño según Figma
 *
 * Columnas: Logo | Servicios | Casos de éxito | Nosotros | Contacto
 * Barra inferior: Redes sociales (izq) | Copyright (der)
 *
 * Logo del footer: campo ACF "footer_logo" (Image). En ACF gratuito, asignar el campo
 * a "Página" y configurarlo en la página de portada para que sea global.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obtiene la imagen del logo del footer desde ACF.
 *
 * Busca el campo "footer_logo" en este orden:
 * 1. Cualquier entrada (post) que tenga el campo "footer_logo" con imagen (p. ej. "Logo Footer").
 * 2. Entrada con slug "logo-footer" si no se encontró por meta.
 * 3. Página de portada (fallback si el grupo ACF está en Página).
 *
 * @return array|null Array con 'url', 'alt', 'id' o null si no hay imagen.
 */
function centinela_get_footer_logo_acf() {
	if ( ! function_exists( 'get_field' ) ) {
		return null;
	}

	$post_id = null;

	// 1) Entrada que tenga el campo "footer_logo" (p. ej. la entrada "Logo Footer").
	// ACF guarda el ID de la imagen en post_meta "footer_logo" (o en _footer_logo el ID del attachment).
	$logo_posts = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'     => 'footer_logo',
				'value'   => '',
				'compare' => '!=',
			),
		),
	) );
	if ( ! empty( $logo_posts[0] ) ) {
		$post_id = (int) $logo_posts[0];
	}

	// 1b) Si no hay ninguna entrada con el campo, probar por slug "logo-footer".
	if ( $post_id === null ) {
		$by_slug = get_posts( array(
			'name'           => 'logo-footer',
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		if ( ! empty( $by_slug[0] ) ) {
			$post_id = (int) $by_slug[0];
		}
	}

	// 2) Fallback: página de portada.
	if ( $post_id === null ) {
		$page_id = (int) get_option( 'page_on_front' );
		if ( $page_id > 0 ) {
			$post_id = $page_id;
		}
	}

	if ( $post_id === null ) {
		return null;
	}

	$field = get_field( 'footer_logo', $post_id );
	if ( empty( $field ) ) {
		return null;
	}

	// ACF Image: array (url, alt, ID...) o solo el ID.
	if ( is_numeric( $field ) ) {
		$id  = (int) $field;
		$img = $id ? wp_get_attachment_image_src( $id, 'medium' ) : null;
		if ( ! $img ) {
			return null;
		}
		return array(
			'url' => $img[0],
			'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'id'  => $id,
		);
	}
	if ( is_array( $field ) && ! empty( $field['url'] ) ) {
		return array(
			'url' => $field['url'],
			'alt' => isset( $field['alt'] ) ? $field['alt'] : '',
			'id'  => isset( $field['ID'] ) ? (int) $field['ID'] : 0,
		);
	}
	return null;
}

function centinela_theme_default_footer() {
	$has_servicios   = has_nav_menu( 'footer_servicios' );
	$has_casos_exito = has_nav_menu( 'footer_casos_exito' );
	$has_nosotros    = has_nav_menu( 'footer_nosotros' );
	$has_legacy_footer = has_nav_menu( 'footer' );
	$has_contacto    = is_active_sidebar( 'footer-contacto' );
	$has_social      = is_active_sidebar( 'footer-social' );

	$footer_logo = centinela_get_footer_logo_acf();
	?>
	<footer id="colophon" class="site-footer centinela-footer mt-auto" role="contentinfo">
		<div class="centinela-footer__inner">
			<div class="centinela-footer__grid">
				<div class="centinela-footer__col centinela-footer__col--logo">
					<?php if ( $footer_logo && ! empty( $footer_logo['url'] ) ) : ?>
						<div class="centinela-footer__logo">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" class="centinela-footer__logo-link">
								<img src="<?php echo esc_url( $footer_logo['url'] ); ?>" alt="<?php echo esc_attr( $footer_logo['alt'] ? $footer_logo['alt'] : get_bloginfo( 'name' ) ); ?>" class="centinela-footer__logo-img" width="187" height="55" loading="lazy" />
							</a>
						</div>
					<?php elseif ( has_custom_logo() ) : ?>
						<div class="centinela-footer__logo">
							<?php the_custom_logo(); ?>
						</div>
					<?php else : ?>
						<div class="centinela-footer__logo centinela-footer__logo-text">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" class="centinela-footer__logo-link centinela-footer__logo-link--text">
								<span class="centinela-footer__site-name"><?php bloginfo( 'name' ); ?></span>
							</a>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $has_servicios ) : ?>
					<div class="centinela-footer__col centinela-footer__col--menu">
						<h3 class="centinela-footer__title"><?php esc_html_e( 'Servicios', 'centinela-group-theme' ); ?></h3>
						<?php
						wp_nav_menu( array(
							'theme_location' => 'footer_servicios',
							'menu_class'     => 'centinela-footer__nav',
							'container'      => false,
						) );
						?>
					</div>
				<?php endif; ?>

				<?php if ( $has_casos_exito ) : ?>
					<div class="centinela-footer__col centinela-footer__col--menu">
						<h3 class="centinela-footer__title"><?php esc_html_e( 'Casos de éxito', 'centinela-group-theme' ); ?></h3>
						<?php
						wp_nav_menu( array(
							'theme_location' => 'footer_casos_exito',
							'menu_class'     => 'centinela-footer__nav',
							'container'      => false,
						) );
						?>
					</div>
				<?php endif; ?>

				<?php if ( $has_nosotros ) : ?>
					<div class="centinela-footer__col centinela-footer__col--menu">
						<h3 class="centinela-footer__title"><?php esc_html_e( 'Nosotros', 'centinela-group-theme' ); ?></h3>
						<?php
						wp_nav_menu( array(
							'theme_location' => 'footer_nosotros',
							'menu_class'     => 'centinela-footer__nav',
							'container'      => false,
						) );
						?>
					</div>
				<?php endif; ?>

				<?php if ( $has_legacy_footer && ! $has_servicios && ! $has_casos_exito && ! $has_nosotros ) : ?>
					<div class="centinela-footer__col centinela-footer__col--menu">
						<h3 class="centinela-footer__title"><?php esc_html_e( 'Enlaces', 'centinela-group-theme' ); ?></h3>
						<?php
						wp_nav_menu( array(
							'theme_location' => 'footer',
							'menu_class'     => 'centinela-footer__nav',
							'container'      => false,
						) );
						?>
					</div>
				<?php endif; ?>

				<?php if ( $has_contacto ) : ?>
					<div class="centinela-footer__col centinela-footer__col--contacto">
						<?php dynamic_sidebar( 'footer-contacto' ); ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="centinela-footer__line" aria-hidden="true"></div>

			<div class="centinela-footer__bar">
				<?php if ( $has_social ) : ?>
					<div class="centinela-footer__social">
						<?php dynamic_sidebar( 'footer-social' ); ?>
					</div>
				<?php endif; ?>
				<p class="centinela-footer__copyright">
					<?php esc_html_e( 'Copyright © 2025 Centinela Group. Todos los derechos reservados.', 'centinela-group-theme' ); ?>
				</p>
			</div>
		</div>
	</footer>
	<?php
}
