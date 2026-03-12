<?php
/**
 * Elementor Widget: Gallery Pro (Centinela Group)
 * Réplica del widget Gallery de Elementor Pro: Grid, Masonry, Justified, lightbox, overlay, etc.
 * Uso libre con Elementor Free.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Gallery_Pro_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_gallery_pro';
	}

	public function get_title() {
		return __( 'Gallery Pro', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-gallery-advanced';
	}

	public function get_categories() {
		return array( 'centinela', 'basic' );
	}

	public function get_keywords() {
		return array( 'gallery', 'galería', 'images', 'masonry', 'justified', 'lightbox', 'grid', 'centinela' );
	}

	public function get_script_depends() {
		return array( 'centinela-gallery-pro' );
	}

	public function get_style_depends() {
		return array( 'centinela-theme-scss', 'e-swiper' );
	}

	protected function register_controls() {
		$this->register_content_section();
		$this->register_layout_section();
		$this->register_overlay_section();
		$this->register_style_images_section();
		$this->register_style_overlay_section();
		$this->register_style_caption_section();
	}

	protected function register_content_section() {
		$this->start_controls_section(
			'section_gallery',
			array(
				'label' => __( 'Galería', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'wp_gallery',
			array(
				'label'   => __( 'Añadir imágenes', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::GALLERY,
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Image_Size::get_type(),
			array(
				'name'    => 'thumbnail',
				'exclude' => array( 'custom' ),
			)
		);

		$this->add_control(
			'gallery_link',
			array(
				'label'   => __( 'Enlace', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'file',
				'options' => array(
					'file'       => __( 'Archivo de medios', 'centinela-group-theme' ),
					'attachment' => __( 'Página de adjunto', 'centinela-group-theme' ),
					'none'       => __( 'Ninguno', 'centinela-group-theme' ),
				),
			)
		);

		$this->add_control(
			'open_lightbox',
			array(
				'label'     => __( 'Lightbox', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'default',
				'options'   => array(
					'default' => __( 'Por defecto', 'centinela-group-theme' ),
					'yes'     => __( 'Sí', 'centinela-group-theme' ),
					'no'      => __( 'No', 'centinela-group-theme' ),
				),
				'condition' => array( 'gallery_link' => 'file' ),
			)
		);

		$this->add_control(
			'lightbox_show_title',
			array(
				'label'        => __( 'Mostrar título en lightbox', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Mostrar u ocultar el título (y descripción) de cada imagen dentro del modal del lightbox.', 'centinela-group-theme' ),
				'condition'    => array(
					'gallery_link' => 'file',
					'open_lightbox!' => 'no',
				),
			)
		);

		$this->add_control(
			'gallery_rand',
			array(
				'label'   => __( 'Ordenar por', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''    => __( 'Por defecto', 'centinela-group-theme' ),
					'rand' => __( 'Aleatorio', 'centinela-group-theme' ),
				),
			)
		);

		$this->add_control(
			'gallery_display_caption',
			array(
				'label'   => __( 'Pie de imagen', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					'none' => __( 'Ninguno', 'centinela-group-theme' ),
					''     => __( 'Caption del adjunto', 'centinela-group-theme' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_layout_section() {
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Diseño', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Tipo de diseño', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid'      => __( 'Grid (proporción fija)', 'centinela-group-theme' ),
					'masonry'   => __( 'Masonry', 'centinela-group-theme' ),
					'justified' => __( 'Justificado', 'centinela-group-theme' ),
				),
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columnas', 'centinela-group-theme' ),
				'type'           => \Elementor\Controls_Manager::NUMBER,
				'min'            => 1,
				'max'            => 10,
				'default'        => 4,
				'tablet_default' => 3,
				'mobile_default' => 2,
				'condition'      => array( 'layout' => 'grid' ),
			)
		);

		$this->add_control(
			'aspect_ratio',
			array(
				'label'     => __( 'Relación de aspecto', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '1',
				'options'   => array(
					'1'    => '1:1',
					'0.75' => '4:3',
					'0.5625' => '16:9',
					'1.333' => '3:4',
					'1.778' => '9:16',
				),
				'condition' => array( 'layout' => 'grid' ),
			)
		);

		$this->add_control(
			'justified_row_height',
			array(
				'label'     => __( 'Altura de fila (px)', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'     => array(
					'px' => array( 'min' => 100, 'max' => 400, 'step' => 10 ),
				),
				'default'   => array( 'size' => 200 ),
				'condition' => array( 'layout' => 'justified' ),
			)
		);

		$this->add_control(
			'masonry_column_width',
			array(
				'label'     => __( 'Ancho de columna (px)', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'     => array(
					'px' => array( 'min' => 100, 'max' => 400, 'step' => 10 ),
				),
				'default'   => array( 'size' => 250 ),
				'condition' => array( 'layout' => 'masonry' ),
			)
		);

		$this->end_controls_section();
	}

	protected function register_overlay_section() {
		$this->start_controls_section(
			'section_overlay',
			array(
				'label' => __( 'Overlay al pasar el ratón', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_overlay',
			array(
				'label'        => __( 'Mostrar overlay', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'overlay_content',
			array(
				'label'     => __( 'Contenido del overlay', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::SELECT2,
				'multiple'  => true,
				'options'   => array(
					'title'     => __( 'Título', 'centinela-group-theme' ),
					'caption'   => __( 'Caption', 'centinela-group-theme' ),
					'description' => __( 'Descripción', 'centinela-group-theme' ),
				),
				'default'   => array( 'title' ),
				'condition' => array( 'show_overlay' => 'yes' ),
			)
		);

		$this->add_control(
			'hover_animation',
			array(
				'label'   => __( 'Animación al pasar el ratón', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'zoom',
				'options' => array(
					'none' => __( 'Ninguna', 'centinela-group-theme' ),
					'zoom' => __( 'Zoom', 'centinela-group-theme' ),
					'slide-up' => __( 'Deslizar arriba', 'centinela-group-theme' ),
					'fade' => __( 'Desvanecer', 'centinela-group-theme' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_images_section() {
		$this->start_controls_section(
			'section_style_images',
			array(
				'label' => __( 'Imágenes', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'image_spacing',
			array(
				'label'   => __( 'Espaciado (gap)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'   => array(
					'px' => array( 'min' => 0, 'max' => 100 ),
					'em' => array( 'min' => 0, 'max' => 10, 'step' => 0.1 ),
					'rem' => array( 'min' => 0, 'max' => 10, 'step' => 0.1 ),
				),
				'default' => array( 'size' => 15, 'unit' => 'px' ),
				'selectors' => array(
					'{{WRAPPER}} .centinela-gallery-pro' => 'gap: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}}.centinela-gallery-pro--masonry .centinela-gallery-pro' => 'column-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'image_border',
				'selector' => '{{WRAPPER}} .centinela-gallery-pro__item img',
			)
		);

		$this->add_responsive_control(
			'image_border_radius',
			array(
				'label'      => __( 'Border radius', 'centinela-group-theme' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .centinela-gallery-pro__item img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .centinela-gallery-pro__item a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .centinela-gallery-pro__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'image_box_shadow',
				'selector' => '{{WRAPPER}} .centinela-gallery-pro__item img',
			)
		);

		$this->add_control(
			'image_opacity',
			array(
				'label'     => __( 'Opacidad', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ) ),
				'selectors' => array(
					'{{WRAPPER}} .centinela-gallery-pro__item img' => 'opacity: {{SIZE}};',
				),
			)
		);

		$this->add_control(
			'image_opacity_hover',
			array(
				'label'     => __( 'Opacidad (hover)', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ) ),
				'default'   => array( 'size' => 1 ),
				'selectors' => array(
					'{{WRAPPER}} .centinela-gallery-pro__item:hover img' => 'opacity: {{SIZE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_overlay_section() {
		$this->start_controls_section(
			'section_style_overlay',
			array(
				'label'     => __( 'Overlay', 'centinela-group-theme' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_overlay' => 'yes' ),
			)
		);

		$this->add_control(
			'overlay_background',
			array(
				'label'     => __( 'Fondo del overlay', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'rgba(0,0,0,0.6)',
				'selectors' => array(
					'{{WRAPPER}} .centinela-gallery-pro__overlay' => 'background: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'overlay_content_color',
			array(
				'label'     => __( 'Color del texto', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .centinela-gallery-pro__overlay-text' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'overlay_typography',
				'selector' => '{{WRAPPER}} .centinela-gallery-pro__overlay-text',
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_caption_section() {
		$this->start_controls_section(
			'section_style_caption',
			array(
				'label'     => __( 'Pie de imagen', 'centinela-group-theme' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'gallery_display_caption' => '' ),
			)
		);

		$this->add_responsive_control(
			'caption_align',
			array(
				'label'     => __( 'Alineación', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => __( 'Izquierda', 'centinela-group-theme' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => __( 'Centro', 'centinela-group-theme' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => __( 'Derecha', 'centinela-group-theme' ), 'icon' => 'eicon-text-align-right' ),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}} .centinela-gallery-pro__caption' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'caption_color',
			array(
				'label'     => __( 'Color', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .centinela-gallery-pro__caption' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'caption_typography',
				'selector' => '{{WRAPPER}} .centinela-gallery-pro__caption',
			)
		);

		$this->add_responsive_control(
			'caption_spacing',
			array(
				'label'     => __( 'Espaciado superior', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors' => array(
					'{{WRAPPER}} .centinela-gallery-pro__caption' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function get_gallery_ids() {
		$settings = $this->get_settings_for_display();
		if ( empty( $settings['wp_gallery'] ) || ! is_array( $settings['wp_gallery'] ) ) {
			return array();
		}
		$ids = wp_list_pluck( $settings['wp_gallery'], 'id' );
		if ( ! empty( $settings['gallery_rand'] ) && $settings['gallery_rand'] === 'rand' ) {
			shuffle( $ids );
		}
		return $ids;
	}

	protected function get_lightbox_attributes( $attachment_id ) {
		$settings = $this->get_settings_for_display();
		$open_lightbox = isset( $settings['open_lightbox'] ) ? $settings['open_lightbox'] : 'default';
		$group_id = 'centinela-gallery-pro-' . $this->get_id();
		$show_title = ! empty( $settings['lightbox_show_title'] ) && $settings['lightbox_show_title'] === 'yes';

		if ( $show_title ) {
			$this->add_lightbox_data_attributes( 'lightbox-' . $attachment_id, $attachment_id, $open_lightbox, $group_id, false );
		} else {
			$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
			$is_global_lightbox = $kit && 'yes' === $kit->get_settings( 'global_image_lightbox' );
			if ( 'no' === $open_lightbox || ( 'default' === $open_lightbox && ! $is_global_lightbox ) ) {
				return '';
			}
			$action_hash_params = array(
				'id'        => $attachment_id,
				'url'       => wp_get_attachment_url( $attachment_id ),
				'slideshow' => $group_id,
			);
			$attributes = array(
				'data-elementor-open-lightbox'       => 'yes',
				'data-elementor-lightbox-slideshow' => $group_id,
				'data-e-action-hash'                 => \Elementor\Plugin::instance()->frontend->create_action_hash( 'lightbox', $action_hash_params ),
			);
			$this->add_render_attribute( 'lightbox-' . $attachment_id, $attributes, null, false );
		}

		$attrs = $this->get_render_attribute_string( 'lightbox-' . $attachment_id );
		return $attrs ? $attrs : '';
	}

	protected function get_link_url( $attachment_id ) {
		$settings = $this->get_settings_for_display();
		$link = $settings['gallery_link'];
		if ( $link === 'none' ) {
			return '';
		}
		if ( $link === 'attachment' ) {
			return get_attachment_link( $attachment_id );
		}
		return wp_get_attachment_url( $attachment_id );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$ids = $this->get_gallery_ids();
		if ( empty( $ids ) ) {
			return;
		}

		$layout       = $settings['layout'];
		$show_overlay = $settings['show_overlay'] === 'yes';
		$overlay_content = $settings['overlay_content'];
		if ( ! is_array( $overlay_content ) ) {
			$overlay_content = array();
		}
		$thumbnail_size = $settings['thumbnail_size'];
		$display_caption = $settings['gallery_display_caption'] === '';
		$hover_animation = $settings['hover_animation'];
		$use_lightbox = ( $settings['gallery_link'] === 'file' && $settings['open_lightbox'] !== 'no' );

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		$global_lightbox = $kit && 'yes' === $kit->get_settings( 'global_image_lightbox' );
		$use_lightbox = $use_lightbox && ( $settings['open_lightbox'] === 'yes' || $global_lightbox );

		$columns        = (int) ( $settings['columns'] ?? 4 );
		$columns_tablet = (int) ( $settings['columns_tablet'] ?? 3 );
		$columns_mobile = (int) ( $settings['columns_mobile'] ?? 2 );
		$aspect_ratio   = isset( $settings['aspect_ratio'] ) ? (float) $settings['aspect_ratio'] : 1;
		$justified_height = isset( $settings['justified_row_height']['size'] ) ? (int) $settings['justified_row_height']['size'] : 200;
		$masonry_width   = isset( $settings['masonry_column_width']['size'] ) ? (int) $settings['masonry_column_width']['size'] : 250;

		$this->add_render_attribute( 'wrapper', 'class', array(
			'centinela-gallery-pro',
			'centinela-gallery-pro--' . $layout,
			'centinela-gallery-pro--hover-' . $hover_animation,
		) );
		$this->add_render_attribute( 'wrapper', 'data-layout', $layout );
		$this->add_render_attribute( 'wrapper', 'data-columns', $columns );
		$this->add_render_attribute( 'wrapper', 'data-columns-tablet', $columns_tablet );
		$this->add_render_attribute( 'wrapper', 'data-columns-mobile', $columns_mobile );
		$this->add_render_attribute( 'wrapper', 'data-aspect-ratio', $aspect_ratio );
		$this->add_render_attribute( 'wrapper', 'data-justified-height', $justified_height );
		$this->add_render_attribute( 'wrapper', 'data-masonry-width', $masonry_width );

		$wrapper_style = '';
		if ( $layout === 'grid' ) {
			$wrapper_style = sprintf(
				' style="--cgp-cols:%d;--cgp-cols-tablet:%d;--cgp-cols-mobile:%d;--cgp-aspect:%s;"',
				$columns,
				$columns_tablet,
				$columns_mobile,
				$aspect_ratio
			);
		}

		$wrapper_attrs = $this->get_render_attribute_string( 'wrapper' );
		?>
		<div <?php echo $wrapper_attrs . $wrapper_style; ?>>
			<div class="centinela-gallery-pro__grid">
				<?php
				foreach ( $ids as $id ) {
					$id = (int) $id;
					if ( ! $id ) {
						continue;
					}
					$img_src = wp_get_attachment_image_url( $id, $thumbnail_size );
					$full_src = wp_get_attachment_image_url( $id, 'full' );
					if ( ! $img_src ) {
						continue;
					}
					$caption = $display_caption ? wp_get_attachment_caption( $id ) : '';
					$title_attr = get_the_title( $id );
					$desc = get_post_field( 'post_content', $id );

					$link_url = $this->get_link_url( $id );
					$lightbox_attrs = ( $use_lightbox && $settings['gallery_link'] === 'file' ) ? $this->get_lightbox_attributes( $id ) : '';
					if ( \Elementor\Plugin::$instance->editor->is_edit_mode() && $lightbox_attrs !== '' ) {
						$lightbox_attrs .= ' class="elementor-clickable"';
					}

					$has_link = ( $link_url !== '' || $lightbox_attrs !== '' );
					if ( $has_link && $link_url === '' && $lightbox_attrs !== '' ) {
						$link_url = $full_src ? $full_src : $img_src;
					}
					?>
					<div class="centinela-gallery-pro__item">
						<?php if ( $has_link ) : ?>
							<a href="<?php echo esc_url( $link_url ); ?>" class="centinela-gallery-pro__link" <?php echo $lightbox_attrs; ?>>
						<?php endif; ?>
						<div class="centinela-gallery-pro__image-wrap">
							<img src="<?php echo esc_url( $img_src ); ?>" alt="<?php echo esc_attr( $title_attr ); ?>" loading="lazy" />
							<?php if ( $show_overlay && ( in_array( 'title', $overlay_content, true ) || in_array( 'caption', $overlay_content, true ) || in_array( 'description', $overlay_content, true ) ) ) : ?>
								<div class="centinela-gallery-pro__overlay">
									<div class="centinela-gallery-pro__overlay-text">
										<?php
										if ( in_array( 'title', $overlay_content, true ) && $title_attr ) {
											echo '<span class="centinela-gallery-pro__overlay-title">' . esc_html( $title_attr ) . '</span>';
										}
										if ( in_array( 'caption', $overlay_content, true ) && $caption ) {
											echo '<span class="centinela-gallery-pro__overlay-caption">' . esc_html( $caption ) . '</span>';
										}
										if ( in_array( 'description', $overlay_content, true ) && $desc ) {
											echo '<span class="centinela-gallery-pro__overlay-desc">' . wp_kses_post( wp_trim_words( $desc, 15 ) ) . '</span>';
										}
										?>
									</div>
								</div>
							<?php endif; ?>
						</div>
						<?php if ( $has_link ) : ?>
							</a>
						<?php endif; ?>
						<?php if ( $display_caption && $caption !== '' ) : ?>
							<div class="centinela-gallery-pro__caption"><?php echo esc_html( $caption ); ?></div>
						<?php endif; ?>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}
}
