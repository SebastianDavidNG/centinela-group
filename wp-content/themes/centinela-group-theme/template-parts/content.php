<?php
/**
 * Contenido de entrada en listados
 *
 * @package Centinela_Group_Theme
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'border-b border-gray-200 pb-8 last:border-0' ); ?>>
	<header class="entry-header mb-4">
		<?php the_title( '<h2 class="entry-title text-2xl font-semibold text-gray-900"><a href="' . esc_url( get_permalink() ) . '" class="no-underline hover:text-gray-600">', '</a></h2>' ); ?>
		<div class="entry-meta text-sm text-gray-500 mt-2">
			<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			<?php if ( get_the_author() ) : ?>
				<span class="mx-2">·</span>
				<span><?php the_author(); ?></span>
			<?php endif; ?>
		</div>
	</header>
	<div class="entry-summary text-gray-600">
		<?php the_excerpt(); ?>
	</div>
	<a href="<?php the_permalink(); ?>" class="inline-block mt-4 text-gray-900 font-medium hover:underline">
		<?php esc_html_e( 'Leer más', 'centinela-group-theme' ); ?> &rarr;
	</a>
</article>
