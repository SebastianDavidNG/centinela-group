<?php
/**
 * Elementor Widget: Hero Páginas Internas (Centinela Group)
 * Misma posición que el hero del home: full width, overlay, imagen de fondo,
 * breadcrumb y título centrados (estilo WiseGuard).
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Hero_Page_Inner_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_hero_page_inner';
	}

	public function get_title() {
		return __( 'Hero página interna', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-banner';
	}

	public function get_categories() {
		return array( 'centinela' );
	}

	public function get_keywords() {
		return array( 'hero', 'breadcrumb', 'página', 'centinela', 'wiseguard' );
	}

	public function get_style_depends() {
		return array( 'centinela-theme-scss' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Contenido', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'image',
			array(
				'label'       => __( 'Imagen de fondo', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::MEDIA,
				'default'     => array(),
				'description' => __( 'Imagen de fondo del hero. Misma posición visual que el hero del home, con overlay oscuro. Recomendado: alta resolución horizontal.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Título', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'placeholder' => __( 'Dejar vacío para usar el título de la página', 'centinela-group-theme' ),
				'description' => __( 'Si está vacío, se mostrará el título de la página actual.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'show_breadcrumb',
			array(
				'label'        => __( 'Mostrar breadcrumb', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$image    = isset( $settings['image']['url'] ) ? $settings['image']['url'] : '';
		$title    = isset( $settings['title'] ) ? trim( (string) $settings['title'] ) : '';
		$breadcrumb = ! empty( $settings['show_breadcrumb'] ) && $settings['show_breadcrumb'] === 'yes';

		if ( $title === '' && function_exists( 'get_the_title' ) ) {
			$title = get_the_title();
		}

		$inline_style = $image ? ' style="background-image: url(' . esc_url( $image ) . ');"' : '';
		?>
		<header class="centinela-hero-page centinela-hero-page--elementor"<?php echo $inline_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php esc_attr_e( 'Cabecera de la página', 'centinela-group-theme' ); ?>">
			<div class="centinela-hero-page__overlay" aria-hidden="true"></div>
			<div class="centinela-hero-page__inner">
				<h1 class="centinela-hero-page__title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( $breadcrumb ) : ?>
					<nav class="centinela-hero-page__breadcrumb" aria-label="<?php esc_attr_e( 'Miga de pan', 'centinela-group-theme' ); ?>">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Inicio', 'centinela-group-theme' ); ?></a>
						<span class="centinela-hero-page__sep" aria-hidden="true">/</span>
						<span class="centinela-hero-page__current"><?php echo esc_html( $title ); ?></span>
					</nav>
				<?php endif; ?>
			</div>
		</header>
		<?php
	}
}
