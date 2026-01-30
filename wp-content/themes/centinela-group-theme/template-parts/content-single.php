<?php
/**
 * Contenido de entrada única
 *
 * @package Centinela_Group_Theme
 */
?>
<header class="entry-header mb-8">
	<?php the_title( '<h1 class="entry-title text-3xl md:text-4xl font-bold text-gray-900">', '</h1>' ); ?>
	<div class="entry-meta text-sm text-gray-500 mt-2">
		<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
		<?php if ( get_the_author() ) : ?>
			<span class="mx-2">·</span>
			<span><?php the_author(); ?></span>
		<?php endif; ?>
	</div>
</header>
<div class="entry-content prose prose-gray max-w-none">
	<?php
	the_content();
	wp_link_pages( array(
		'before' => '<div class="page-links mt-6">',
		'after'  => '</div>',
	) );
	?>
</div>
