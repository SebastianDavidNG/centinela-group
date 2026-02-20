<?php
/**
 * Elementor Widget: Bloque imagen y texto (Content Block) – Centinela Group
 * Sección reutilizable: imagen, título, descripción, enlace con texto editable.
 * Opciones: imagen a izquierda/derecha (reverse) y revertir orden en móvil.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Content_Block_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_content_block';
	}

	public function get_title() {
		return __( 'Bloque imagen y texto', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-image-box';
	}

	public function get_categories() {
		return array( 'centinela' );
	}

	public function get_keywords() {
		return array( 'imagen', 'texto', 'bloque', 'contenido', 'sección', 'centinela', 'link' );
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
				'label'   => __( 'Imagen', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::MEDIA,
				'default' => array(),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Título', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'placeholder' => __( 'Título del bloque', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'description',
			array(
				'label'   => __( 'Descripción', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => '',
				'rows'    => 4,
				'placeholder' => __( 'Texto descriptivo del bloque.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'link_url',
			array(
				'label'       => __( 'Enlace (URL)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => __( 'https://…', 'centinela-group-theme' ),
				'default'     => array(
					'url'         => '',
					'is_external' => false,
					'nofollow'    => false,
				),
			)
		);

		$this->add_control(
			'link_text',
			array(
				'label'       => __( 'Texto del enlace', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Saber más', 'centinela-group-theme' ),
				'label_block' => true,
				'placeholder' => __( 'Ej: Ver más, Saber más, Leer más', 'centinela-group-theme' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Diseño', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'reverse',
			array(
				'label'        => __( 'Invertir orden (imagen a la derecha)', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'description'  => __( 'Activado: imagen a la derecha y texto a la izquierda. Desactivado: imagen a la izquierda y texto a la derecha.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'reverse_on_mobile',
			array(
				'label'        => __( 'Invertir orden en móvil', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'description'  => __( 'Útil si usas varios bloques en la misma página: en móvil el orden de imagen/texto se invierte respecto al escritorio.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label'   => __( 'Color de fondo del botón', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'blue',
				'options' => array(
					'blue'   => __( 'Azul principal (Blue)', 'centinela-group-theme' ),
					'blue_2' => __( 'Azul secundario (Blue 2)', 'centinela-group-theme' ),
					'green'  => __( 'Verde (Green)', 'centinela-group-theme' ),
					'grey'   => __( 'Gris (Grey)', 'centinela-group-theme' ),
					'black'  => __( 'Negro (Black)', 'centinela-group-theme' ),
				),
			)
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label'   => __( 'Color del botón al pasar el mouse (hover)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'green',
				'options' => array(
					'blue'   => __( 'Azul principal (Blue)', 'centinela-group-theme' ),
					'blue_2' => __( 'Azul secundario (Blue 2)', 'centinela-group-theme' ),
					'green'  => __( 'Verde (Green)', 'centinela-group-theme' ),
					'grey'   => __( 'Gris (Grey)', 'centinela-group-theme' ),
					'black'  => __( 'Negro (Black)', 'centinela-group-theme' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$image_url = isset( $settings['image']['url'] ) ? $settings['image']['url'] : '';
		$image_alt = isset( $settings['image']['alt'] ) && $settings['image']['alt'] !== '' ? $settings['image']['alt'] : ( isset( $settings['title'] ) ? $settings['title'] : '' );
		$title     = isset( $settings['title'] ) ? trim( (string) $settings['title'] ) : '';
		$desc      = isset( $settings['description'] ) ? trim( (string) $settings['description'] ) : '';
		$link_text = isset( $settings['link_text'] ) ? trim( (string) $settings['link_text'] ) : __( 'Saber más', 'centinela-group-theme' );
		$link      = isset( $settings['link_url']['url'] ) ? $settings['link_url']['url'] : '';
		$link_attrs = '';
		if ( ! empty( $link ) ) {
			$parts = array();
			if ( ! empty( $settings['link_url']['is_external'] ) ) {
				$parts[] = 'target="_blank"';
				$parts[] = 'rel="noopener noreferrer' . ( ! empty( $settings['link_url']['nofollow'] ) ? ' nofollow' : '' ) . '"';
			} elseif ( ! empty( $settings['link_url']['nofollow'] ) ) {
				$parts[] = 'rel="nofollow"';
			}
			$link_attrs = implode( ' ', $parts );
		}
		$reverse         = ! empty( $settings['reverse'] ) && $settings['reverse'] === 'yes';
		$reverse_mobile  = ! empty( $settings['reverse_on_mobile'] ) && $settings['reverse_on_mobile'] === 'yes';
		$button_color    = isset( $settings['button_color'] ) && $settings['button_color'] !== '' ? $settings['button_color'] : 'blue';
		$hover_color     = isset( $settings['button_hover_color'] ) && $settings['button_hover_color'] !== '' ? $settings['button_hover_color'] : 'green';
		$allowed_colors  = array( 'blue', 'blue_2', 'green', 'grey', 'black' );
		if ( ! in_array( $button_color, $allowed_colors, true ) ) {
			$button_color = 'blue';
		}
		if ( ! in_array( $hover_color, $allowed_colors, true ) ) {
			$hover_color = 'green';
		}
		$arrow_svg = '<svg class="centinela-content-block__link-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';

		$block_classes = 'centinela-content-block';
		if ( $reverse ) {
			$block_classes .= ' centinela-content-block--reverse';
		}
		if ( $reverse_mobile ) {
			$block_classes .= ' centinela-content-block--reverse-mobile';
		}
		?>
		<div class="<?php echo esc_attr( $block_classes ); ?>">
			<div class="centinela-content-block__inner">
				<?php if ( $image_url ) : ?>
					<div class="centinela-content-block__media">
						<?php if ( $link ) : ?>
							<a href="<?php echo esc_url( $link ); ?>" class="centinela-content-block__media-link"<?php echo $link_attrs ? ' ' . $link_attrs : ''; ?>>
								<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" class="centinela-content-block__img" loading="lazy" />
							</a>
						<?php else : ?>
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" class="centinela-content-block__img" loading="lazy" />
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<div class="centinela-content-block__body">
					<?php if ( $title !== '' ) : ?>
						<h2 class="centinela-content-block__title"><?php echo esc_html( $title ); ?></h2>
					<?php endif; ?>
					<?php if ( $desc !== '' ) : ?>
						<div class="centinela-content-block__description"><?php echo wp_kses_post( nl2br( $desc ) ); ?></div>
					<?php endif; ?>
					<?php if ( $link !== '' && $link_text !== '' ) : ?>
						<p class="centinela-content-block__link-wrap">
							<a href="<?php echo esc_url( $link ); ?>" class="centinela-content-block__link centinela-content-block__link--<?php echo esc_attr( $button_color ); ?> centinela-content-block__link--hover-<?php echo esc_attr( $hover_color ); ?>"<?php echo $link_attrs ? ' ' . $link_attrs : ''; ?>><span class="centinela-content-block__link-text"><?php echo esc_html( $link_text ); ?></span><?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
