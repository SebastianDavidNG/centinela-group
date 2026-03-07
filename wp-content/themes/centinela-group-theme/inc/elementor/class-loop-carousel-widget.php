<?php
/**
 * Elementor Widget: Loop Carousel (Centinela Group)
 * Carrusel dinámico con consulta de entradas (posts, páginas, CPT).
 * Replica las funciones del Loop Carousel de Elementor Pro: query, slide con imagen/título/excerpt/enlace, opciones de carrusel.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Loop_Carousel_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_loop_carousel';
	}

	public function get_title() {
		return __( 'Loop Carousel', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-post-slider';
	}

	public function get_categories() {
		return array( 'centinela', 'basic' );
	}

	public function get_keywords() {
		return array( 'loop', 'carousel', 'carrusel', 'posts', 'query', 'slider', 'centinela' );
	}

	public function get_script_depends() {
		return array( 'swiper', 'centinela-loop-carousel' );
	}

	public function get_style_depends() {
		return array( 'swiper', 'centinela-theme-scss' );
	}

	protected function register_controls() {
		$this->register_query_section();
		$this->register_content_section();
		$this->register_carousel_section();
		$this->register_style_section();
	}

	protected function register_query_section() {
		$this->start_controls_section(
			'section_query',
			array(
				'label' => __( 'Consulta (Query)', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$post_types = array( 'post' => __( 'Entradas', 'centinela-group-theme' ), 'page' => __( 'Páginas', 'centinela-group-theme' ) );
		$cpts      = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
		foreach ( $cpts as $cpt ) {
			$post_types[ $cpt->name ] = $cpt->label;
		}

		$this->add_control(
			'post_type',
			array(
				'label'   => __( 'Tipo de contenido', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'post',
				'options' => $post_types,
			)
		);

		$this->add_control(
			'posts_per_page',
			array(
				'label'   => __( 'Número de ítems', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 6,
				'min'     => 1,
				'max'     => 50,
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Ordenar por', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'date',
				'options' => array(
					'date'       => __( 'Fecha', 'centinela-group-theme' ),
					'title'      => __( 'Título', 'centinela-group-theme' ),
					'menu_order' => __( 'Orden del menú', 'centinela-group-theme' ),
					'rand'       => __( 'Aleatorio', 'centinela-group-theme' ),
					'comment_count' => __( 'Comentarios', 'centinela-group-theme' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Orden', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'DESC',
				'options' => array(
					'ASC'  => __( 'Ascendente', 'centinela-group-theme' ),
					'DESC' => __( 'Descendente', 'centinela-group-theme' ),
				),
			)
		);

		$this->add_control(
			'taxonomy_filter',
			array(
				'label'       => __( 'Filtrar por taxonomía (opcional)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $this->get_taxonomy_options(),
				'multiple'    => false,
				'label_block' => true,
				'description' => __( 'Dejar vacío para mostrar todos.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'term_ids',
			array(
				'label'       => __( 'IDs de términos (opcional)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '1, 5, 12',
				'description' => __( 'IDs de categorías/etiquetas separados por coma. Solo si elegiste una taxonomía arriba.', 'centinela-group-theme' ),
				'condition'   => array( 'taxonomy_filter!' => '' ),
			)
		);

		$this->add_control(
			'offset',
			array(
				'label'   => __( 'Desplazamiento (offset)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 0,
				'min'     => 0,
			)
		);

		$this->add_control(
			'exclude_current',
			array(
				'label'        => __( 'Excluir entrada actual', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->end_controls_section();
	}

	protected function get_taxonomy_options() {
		$options = array( '' => __( '— Ninguna —', 'centinela-group-theme' ) );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxonomies as $slug => $tax ) {
			$options[ $slug ] = $tax->label;
		}
		return $options;
	}

	protected function register_content_section() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Contenido del slide', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'slide_layout',
			array(
				'label'   => __( 'Diseño del slide', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'vertical',
				'options' => array(
					'vertical' => __( 'Imagen arriba, texto abajo', 'centinela-group-theme' ),
					'horizontal' => __( 'Imagen a la izquierda, texto a la derecha', 'centinela-group-theme' ),
				),
			)
		);

		$this->add_control(
			'show_image',
			array(
				'label'        => __( 'Mostrar imagen destacada', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'image_size',
			array(
				'label'     => __( 'Tamaño de imagen', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'medium_large',
				'options'   => array(
					'thumbnail'    => __( 'Miniatura', 'centinela-group-theme' ),
					'medium'       => __( 'Mediano', 'centinela-group-theme' ),
					'medium_large' => __( 'Mediano grande', 'centinela-group-theme' ),
					'large'        => __( 'Grande', 'centinela-group-theme' ),
					'full'         => __( 'Completa', 'centinela-group-theme' ),
				),
				'condition' => array( 'show_image' => 'yes' ),
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'        => __( 'Mostrar título', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'title_tag',
			array(
				'label'     => __( 'Etiqueta del título', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'h3',
				'options'   => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p' ),
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_control(
			'show_excerpt',
			array(
				'label'        => __( 'Mostrar extracto', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'excerpt_length',
			array(
				'label'     => __( 'Longitud del extracto', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 20,
				'min'       => 5,
				'max'       => 100,
				'condition' => array( 'show_excerpt' => 'yes' ),
			)
		);

		$this->add_control(
			'show_button',
			array(
				'label'        => __( 'Mostrar botón / enlace', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'     => __( 'Texto del botón', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Leer más', 'centinela-group-theme' ),
				'condition' => array( 'show_button' => 'yes' ),
			)
		);

		$this->add_control(
			'link_entire_slide',
			array(
				'label'        => __( 'Enlazar todo el slide', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Si está activo, todo el slide será un enlace a la entrada. El botón se oculta.', 'centinela-group-theme' ),
			)
		);

		$this->end_controls_section();
	}

	protected function register_carousel_section() {
		$this->start_controls_section(
			'section_carousel',
			array(
				'label' => __( 'Carrusel', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'slides_per_view',
			array(
				'label'   => __( 'Slides visibles (escritorio)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 3,
				'min'     => 1,
				'max'     => 6,
			)
		);

		$this->add_control(
			'slides_per_view_tablet',
			array(
				'label'   => __( 'Slides visibles (tablet)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 2,
				'min'     => 1,
				'max'     => 4,
			)
		);

		$this->add_control(
			'slides_per_view_mobile',
			array(
				'label'   => __( 'Slides visibles (móvil)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 1,
				'min'     => 1,
				'max'     => 2,
			)
		);

		$this->add_control(
			'slides_per_group',
			array(
				'label'   => __( 'Slides a desplazar por acción', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 1,
				'min'     => 1,
				'max'     => 6,
				'description' => __( 'Cuántos slides avanza al usar flechas o paginación.', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'space_between',
			array(
				'label'   => __( 'Espacio entre slides (px)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 24,
				'min'     => 0,
				'max'     => 80,
			)
		);

		$this->add_control(
			'loop',
			array(
				'label'        => __( 'Loop infinito', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'speed',
			array(
				'label'   => __( 'Velocidad de transición (ms)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 500,
				'min'     => 200,
				'max'     => 1500,
				'step'    => 100,
			)
		);

		$this->add_control(
			'show_arrows',
			array(
				'label'        => __( 'Mostrar flechas', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_pagination',
			array(
				'label'        => __( 'Mostrar paginación (puntos)', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'        => __( 'Reproducción automática', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'autoplay_delay',
			array(
				'label'     => __( 'Intervalo autoplay (ms)', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 5000,
				'min'       => 1000,
				'max'       => 15000,
				'step'      => 500,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);

		$this->add_control(
			'equal_height',
			array(
				'label'        => __( 'Altura igualada de slides', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_section() {
		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Estilo', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_title',
			array(
				'label' => __( 'Título', 'centinela-group-theme' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Color', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1a1a1a',
				'selectors' => array(
					'{{WRAPPER}} .centinela-loop-carousel__title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .centinela-loop-carousel__title',
			)
		);

		$this->add_control(
			'heading_excerpt',
			array(
				'label' => __( 'Extracto', 'centinela-group-theme' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'excerpt_color',
			array(
				'label'     => __( 'Color', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#555',
				'selectors' => array(
					'{{WRAPPER}} .centinela-loop-carousel__excerpt' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'excerpt_typography',
				'selector' => '{{WRAPPER}} .centinela-loop-carousel__excerpt',
			)
		);

		$this->add_control(
			'heading_button',
			array(
				'label' => __( 'Botón', 'centinela-group-theme' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label'     => __( 'Color', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .centinela-loop-carousel__button' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_bg_color',
			array(
				'label'     => __( 'Fondo', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .centinela-loop-carousel__button' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function get_loop_posts() {
		$settings = $this->get_settings_for_display();
		$args = array(
			'post_type'      => $settings['post_type'],
			'posts_per_page' => (int) $settings['posts_per_page'],
			'orderby'        => $settings['orderby'],
			'order'          => $settings['order'],
			'post_status'    => 'publish',
			'offset'         => (int) $settings['offset'],
		);

		if ( ! empty( $settings['exclude_current'] ) && $settings['exclude_current'] === 'yes' && get_the_ID() ) {
			$args['post__not_in'] = array( get_the_ID() );
		}

		// Filtrar por taxonomía (ej. Categorías del producto → product_cat) e IDs de términos (ej. 18).
		$taxonomy = isset( $settings['taxonomy_filter'] ) ? $settings['taxonomy_filter'] : '';
		if ( is_array( $taxonomy ) ) {
			$taxonomy = ! empty( $taxonomy ) ? (string) reset( $taxonomy ) : '';
		} else {
			$taxonomy = (string) $taxonomy;
		}
		$term_ids_raw = isset( $settings['term_ids'] ) ? $settings['term_ids'] : '';
		if ( $taxonomy !== '' && $term_ids_raw !== '' && $term_ids_raw !== null ) {
			if ( is_numeric( $term_ids_raw ) ) {
				$term_ids = array( absint( $term_ids_raw ) );
			} else {
				$term_ids = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', (string) $term_ids_raw ) ) ) );
			}
			if ( ! empty( $term_ids ) ) {
				$args['tax_query'] = array(
					'relation' => 'AND',
					array(
						'taxonomy'         => $taxonomy,
						'field'            => 'term_id',
						'terms'            => $term_ids,
						'include_children' => true,
					),
				);
			}
		}

		return new \WP_Query( $args );
	}

	protected function render() {
		$query = $this->get_loop_posts();
		if ( ! $query->have_posts() ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$widget_id = 'centinela-loop-carousel-' . $this->get_id();
		$slide_layout = $settings['slide_layout'];
		$show_image = $settings['show_image'] === 'yes';
		$image_size = $settings['image_size'];
		$show_title = $settings['show_title'] === 'yes';
		$title_tag = $settings['title_tag'];
		$show_excerpt = $settings['show_excerpt'] === 'yes';
		$excerpt_length = (int) $settings['excerpt_length'];
		$show_button = $settings['show_button'] === 'yes';
		$button_text = $settings['button_text'];
		$link_entire_slide = $settings['link_entire_slide'] === 'yes';
		$slides_count = $query->post_count;

		$data_attrs = array(
			'data-loop-carousel-id' => $widget_id,
			'data-slides-per-view' => (int) $settings['slides_per_view'],
			'data-slides-per-view-tablet' => (int) $settings['slides_per_view_tablet'],
			'data-slides-per-view-mobile' => (int) $settings['slides_per_view_mobile'],
			'data-slides-per-group' => (int) $settings['slides_per_group'],
			'data-space-between' => (int) $settings['space_between'],
			'data-loop' => $settings['loop'] === 'yes' ? '1' : '0',
			'data-speed' => (int) $settings['speed'],
			'data-arrows' => $settings['show_arrows'] === 'yes' ? '1' : '0',
			'data-pagination' => $settings['show_pagination'] === 'yes' ? '1' : '0',
			'data-autoplay' => $settings['autoplay'] === 'yes' ? '1' : '0',
			'data-autoplay-delay' => (int) $settings['autoplay_delay'],
			'data-equal-height' => $settings['equal_height'] === 'yes' ? '1' : '0',
			'data-items-count' => $slides_count,
		);
		$attr_string = '';
		foreach ( $data_attrs as $k => $v ) {
			$attr_string .= ' ' . esc_attr( $k ) . '="' . esc_attr( (string) $v ) . '"';
		}
		?>
		<section class="centinela-loop-carousel centinela-loop-carousel--<?php echo esc_attr( $slide_layout ); ?>" aria-label="<?php esc_attr_e( 'Carrusel de entradas', 'centinela-group-theme' ); ?>"<?php echo $attr_string; ?>>
			<div class="centinela-loop-carousel__inner">
				<div class="centinela-loop-carousel__swiper swiper">
					<div class="centinela-loop-carousel__track swiper-wrapper">
						<?php
						while ( $query->have_posts() ) {
							$query->the_post();
							$post_id = get_the_ID();
							$permalink = get_permalink();
							$title = get_the_title();
							$excerpt = $show_excerpt ? wp_trim_words( get_the_excerpt(), $excerpt_length ) : '';
							$thumb_id = get_post_thumbnail_id( $post_id );
							$img_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, $image_size ) : '';
							?>
							<div class="centinela-loop-carousel__slide swiper-slide">
								<?php if ( $link_entire_slide ) : ?>
									<a href="<?php echo esc_url( $permalink ); ?>" class="centinela-loop-carousel__slide-link">
								<?php endif; ?>
								<div class="centinela-loop-carousel__card">
									<?php if ( $show_image && $img_url ) : ?>
										<div class="centinela-loop-carousel__image">
											<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
											<div class="centinela-loop-carousel__image-overlay" aria-hidden="true"></div>
										</div>
									<?php endif; ?>
									<div class="centinela-loop-carousel__body">
										<?php if ( $show_title && $title ) : ?>
											<<?php echo esc_html( $title_tag ); ?> class="centinela-loop-carousel__title">
												<?php if ( ! $link_entire_slide ) : ?>
													<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
												<?php else : ?>
													<?php echo esc_html( $title ); ?>
												<?php endif; ?>
											</<?php echo esc_html( $title_tag ); ?>>
										<?php endif; ?>
										<?php if ( $excerpt !== '' ) : ?>
											<div class="centinela-loop-carousel__excerpt"><?php echo wp_kses_post( $excerpt ); ?></div>
										<?php endif; ?>
										<?php if ( $show_button && ! $link_entire_slide ) : ?>
											<a href="<?php echo esc_url( $permalink ); ?>" class="centinela-loop-carousel__button"><?php echo esc_html( $button_text ); ?></a>
										<?php endif; ?>
									</div>
								</div>
								<?php if ( $link_entire_slide ) : ?>
									</a>
								<?php endif; ?>
							</div>
							<?php
						}
						wp_reset_postdata();
						?>
					</div>
				</div>
				<?php if ( $settings['show_pagination'] === 'yes' ) : ?>
					<div class="centinela-loop-carousel__pagination swiper-pagination"></div>
				<?php endif; ?>
				<?php if ( $settings['show_arrows'] === 'yes' ) : ?>
					<button type="button" class="centinela-loop-carousel__prev swiper-button-prev" aria-label="<?php esc_attr_e( 'Anterior', 'centinela-group-theme' ); ?>"></button>
					<button type="button" class="centinela-loop-carousel__next swiper-button-next" aria-label="<?php esc_attr_e( 'Siguiente', 'centinela-group-theme' ); ?>"></button>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
