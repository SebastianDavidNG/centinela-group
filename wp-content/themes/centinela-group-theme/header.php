<?php
/**
 * Cabecera del sitio
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'min-h-screen flex flex-col antialiased' ); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site flex flex-col min-h-screen">

	<div class="centinela-header-bar" role="banner">
		<?php centinela_theme_default_header(); ?>
		<?php centinela_theme_submenu(); ?>
	</div>

	<main id="content" class="site-main flex-grow">
