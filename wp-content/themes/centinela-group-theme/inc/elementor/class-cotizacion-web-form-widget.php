<?php
/**
 * Elementor Widget: Formulario de Cotización Web – Centinela Group
 * Formulario configurable que guarda envíos en Cotizaciones Web Form y opcionalmente envía copia por correo.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Cotizacion_Web_Form_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'centinela_cotizacion_web_form';
	}

	public function get_title() {
		return __( 'Formulario Cotización Web', 'centinela-group-theme' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_categories() {
		return array( 'centinela', 'basic' );
	}

	public function get_keywords() {
		return array( 'formulario', 'cotización', 'cotizaciones', 'form', 'contacto', 'centinela' );
	}

	public function get_script_depends() {
		return array( 'centinela-cotizacion-web-form' );
	}

	public function get_style_depends() {
		return array( 'centinela-theme-scss', 'centinela-cotizacion-web-form' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_form',
			array(
				'label' => __( 'Formulario', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'form_title',
			array(
				'label'       => __( 'Título del formulario', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'PEDIR UNA COTIZACIÓN', 'centinela-group-theme' ),
				'label_block' => true,
				'placeholder' => __( 'Ej: PEDIR UNA COTIZACIÓN', 'centinela-group-theme' ),
			)
		);

		$this->add_control(
			'title_image',
			array(
				'label'     => __( 'Imagen/icono izquierda del título', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::MEDIA,
				'default'   => array(),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'submit_text',
			array(
				'label'       => __( 'Texto del botón', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Enviar solicitud', 'centinela-group-theme' ),
				'label_block' => true,
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'field_type',
			array(
				'label'   => __( 'Tipo de campo', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'text',
				'options' => array(
					'text'     => __( 'Texto', 'centinela-group-theme' ),
					'email'    => __( 'Email', 'centinela-group-theme' ),
					'tel'      => __( 'Teléfono', 'centinela-group-theme' ),
					'textarea' => __( 'Área de texto', 'centinela-group-theme' ),
					'select'   => __( 'Lista desplegable', 'centinela-group-theme' ),
					'radio'    => __( 'Radio (Sistemas de interés, etc.)', 'centinela-group-theme' ),
				),
			)
		);

		$repeater->add_control(
			'field_label',
			array(
				'label'       => __( 'Etiqueta', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'placeholder' => __( 'Ej: Nombre', 'centinela-group-theme' ),
			)
		);

		$repeater->add_control(
			'field_name',
			array(
				'label'       => __( 'Nombre del campo (ID)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'placeholder' => __( 'nombre (sin espacios)', 'centinela-group-theme' ),
				'description' => __( 'Si está vacío se usará la etiqueta. Sin espacios.', 'centinela-group-theme' ),
			)
		);

		$repeater->add_control(
			'placeholder',
			array(
				'label'       => __( 'Placeholder', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'condition'   => array(
					'field_type!' => array( 'select', 'radio' ),
				),
			)
		);

		$repeater->add_control(
			'field_required',
			array(
				'label'        => __( 'Obligatorio', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$repeater->add_control(
			'select_options',
			array(
				'label'       => __( 'Opciones (una por línea)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => '',
				'placeholder' => "Opción 1\nOpción 2\nOpción 3",
				'condition'   => array(
					'field_type' => 'select',
				),
			)
		);

		$repeater->add_control(
			'radio_options',
			array(
				'label'       => __( 'Opciones (una por línea)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => '',
				'placeholder' => "Sistema 1\nSistema 2\nSistema 3",
				'condition'   => array(
					'field_type' => 'radio',
				),
			)
		);

		$this->add_control(
			'form_fields',
			array(
				'label'       => __( 'Campos del formulario', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'field_type'    => 'text',
						'field_label'  => __( 'Nombre', 'centinela-group-theme' ),
						'field_name'   => 'nombre',
						'placeholder'  => '',
						'field_required' => 'yes',
					),
					array(
						'field_type'    => 'email',
						'field_label'  => __( 'Email', 'centinela-group-theme' ),
						'field_name'   => 'email',
						'placeholder'  => '',
						'field_required' => 'yes',
					),
					array(
						'field_type'    => 'tel',
						'field_label'  => __( 'Teléfono', 'centinela-group-theme' ),
						'field_name'   => 'telefono',
						'placeholder'  => '',
						'field_required' => '',
					),
					array(
						'field_type'    => 'textarea',
						'field_label'  => __( 'Mensaje o descripción del requerimiento', 'centinela-group-theme' ),
						'field_name'   => 'mensaje',
						'placeholder'  => '',
						'field_required' => '',
					),
				),
				'title_field' => '{{{ field_label }}}',
			)
		);

		$this->add_control(
			'columns_desktop',
			array(
				'label'   => __( 'Columnas (escritorio)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '1',
				'options' => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
			)
		);

		$this->add_control(
			'columns_tablet',
			array(
				'label'   => __( 'Columnas (tablet)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '1',
				'options' => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
				),
			)
		);

		$this->add_control(
			'columns_mobile',
			array(
				'label'   => __( 'Columnas (móvil)', 'centinela-group-theme' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '1',
				'options' => array(
					'1' => '1',
					'2' => '2',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_email',
			array(
				'label' => __( 'Envío de copia por correo', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'send_email_copy',
			array(
				'label'        => __( 'Enviar copia a correos', 'centinela-group-theme' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Sí', 'centinela-group-theme' ),
				'label_off'    => __( 'No', 'centinela-group-theme' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'email_recipients',
			array(
				'label'       => __( 'Correos (uno o más)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => '',
				'placeholder' => "ventas@tudominio.com\nadmin@tudominio.com",
				'description' => __( 'Un correo por línea, o separados por coma. Recibirán una copia de cada envío.', 'centinela-group-theme' ),
				'condition'   => array(
					'send_email_copy' => 'yes',
				),
			)
		);

		$this->add_control(
			'form_source',
			array(
				'label'       => __( 'Etiqueta de origen (solo admin)', 'centinela-group-theme' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Formulario cotización Home', 'centinela-group-theme' ),
				'placeholder' => __( 'Ej: Formulario página de contacto', 'centinela-group-theme' ),
				'description' => __( 'Se guarda junto al envío para distinguir desde qué página o variante del formulario se envió (Home, Contacto, etc.). No se muestra al usuario.', 'centinela-group-theme' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_form',
			array(
				'label' => __( 'Estilo del formulario', 'centinela-group-theme' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_section_heading',
			array(
				'label' => __( 'Sección título (Figma)', 'centinela-group-theme' ),
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'title_section_bg',
			array(
				'label'     => __( 'Fondo sección título', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#021c37',
				'selectors' => array(
					'{{WRAPPER}} .centinela-cwf__title-wrap' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Color del título', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .centinela-cwf__title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .centinela-cwf__title',
				'fields_options' => array(
					'typography' => array( 'default' => 'custom' ),
					'font_size'  => array( 'default' => array( 'unit' => 'px', 'size' => 40 ) ),
					'font_weight'=> array( 'default' => '600' ),
				),
			)
		);

		$this->add_control(
			'label_color',
			array(
				'label'     => __( 'Color de etiquetas', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .centinela-cwf__label' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'input_bg',
			array(
				'label'     => __( 'Fondo de campos', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .centinela-cwf__input' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .centinela-cwf__textarea' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .centinela-cwf__select' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label'     => __( 'Color del botón', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .centinela-cwf__submit' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label'     => __( 'Color del botón (hover)', 'centinela-group-theme' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .centinela-cwf__submit:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$submit_text = isset( $settings['submit_text'] ) ? $settings['submit_text'] : __( 'Enviar solicitud', 'centinela-group-theme' );
		$fields = isset( $settings['form_fields'] ) && is_array( $settings['form_fields'] ) ? $settings['form_fields'] : array();
		$send_email = isset( $settings['send_email_copy'] ) && $settings['send_email_copy'] === 'yes';
		$email_recipients = $send_email && ! empty( $settings['email_recipients'] ) ? $settings['email_recipients'] : '';
		$form_source = isset( $settings['form_source'] ) ? $settings['form_source'] : '';
		$cols_desktop = isset( $settings['columns_desktop'] ) ? $settings['columns_desktop'] : '1';
		$cols_tablet  = isset( $settings['columns_tablet'] ) ? $settings['columns_tablet'] : '1';
		$cols_mobile  = isset( $settings['columns_mobile'] ) ? $settings['columns_mobile'] : '1';

		$form_id = 'centinela-cwf-' . $this->get_id();
		$nonce = wp_nonce_field( 'centinela_cotizaciones_web_form_submit', 'centinela_cwf_nonce', true, false );
		?>
		<div class="centinela-cwf" id="<?php echo esc_attr( $form_id ); ?>">
			<form class="centinela-cwf__form" action="" method="post" novalidate>
				<?php echo $nonce; ?>
				<input type="hidden" name="centinela_cwf_emails" value="<?php echo esc_attr( $email_recipients ); ?>" />
				<?php if ( $form_source !== '' ) : ?>
					<input type="hidden" name="centinela_cwf_source" value="<?php echo esc_attr( $form_source ); ?>" />
				<?php endif; ?>
				<div class="centinela-cwf__fields centinela-cwf__fields--d-<?php echo esc_attr( $cols_desktop ); ?> centinela-cwf__fields--t-<?php echo esc_attr( $cols_tablet ); ?> centinela-cwf__fields--m-<?php echo esc_attr( $cols_mobile ); ?>">
					<?php
					foreach ( $fields as $index => $item ) {
						$label = isset( $item['field_label'] ) ? $item['field_label'] : '';
						$name = isset( $item['field_name'] ) ? trim( (string) $item['field_name'] ) : '';
						if ( $name === '' && $label !== '' ) {
							$name = sanitize_title( $label );
							$name = str_replace( '-', '_', $name );
						}
						if ( $name === '' ) {
							$name = 'field_' . $index;
						}
						$type = isset( $item['field_type'] ) ? $item['field_type'] : 'text';
						$placeholder = isset( $item['placeholder'] ) ? $item['placeholder'] : '';
						$required = isset( $item['field_required'] ) && $item['field_required'] === 'yes';
						$field_id = $form_id . '-field-' . $index;
						$display_label = $label !== '' ? $label : $name;
						$row_class = 'centinela-cwf__row';
						if ( $type === 'radio' ) {
							$row_class .= ' centinela-cwf__row--full';
						}
						?>
						<div class="<?php echo esc_attr( $row_class ); ?>">
							<?php if ( $type !== 'radio' ) : ?>
								<label for="<?php echo esc_attr( $field_id ); ?>" class="centinela-cwf__label">
									<?php echo esc_html( $display_label ); ?>
									<?php if ( $required ) : ?>
										<span class="centinela-cwf__required" aria-hidden="true">*</span>
									<?php endif; ?>
								</label>
							<?php endif; ?>
							<?php
							if ( $type === 'textarea' ) {
								?>
								<textarea
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( $name ); ?>"
									class="centinela-cwf__input centinela-cwf__textarea"
									placeholder="<?php echo esc_attr( $placeholder ); ?>"
									rows="4"
									<?php echo $required ? ' required' : ''; ?>
								></textarea>
								<?php
							} elseif ( $type === 'select' ) {
								$opts = isset( $item['select_options'] ) ? $item['select_options'] : '';
								$options = array_filter( array_map( 'trim', explode( "\n", $opts ) ) );
								?>
								<select
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( $name ); ?>"
									class="centinela-cwf__input centinela-cwf__select"
									<?php echo $required ? ' required' : ''; ?>
								>
									<option value=""><?php esc_html_e( '— Seleccionar —', 'centinela-group-theme' ); ?></option>
									<?php foreach ( $options as $opt ) : ?>
										<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php
							} elseif ( $type === 'radio' ) {
								$opts = isset( $item['radio_options'] ) ? $item['radio_options'] : '';
								$options = array_filter( array_map( 'trim', explode( "\n", $opts ) ) );
								?>
								<div class="centinela-cwf__radio-group" role="group" aria-labelledby="<?php echo esc_attr( $field_id . '-legend' ); ?>">
									<span id="<?php echo esc_attr( $field_id . '-legend' ); ?>" class="centinela-cwf__label centinela-cwf__radio-legend">
										<?php echo esc_html( $display_label ); ?>
										<?php if ( $required ) : ?>
											<span class="centinela-cwf__required" aria-hidden="true">*</span>
										<?php endif; ?>
									</span>
									<div class="centinela-cwf__radio-list">
										<?php foreach ( $options as $opt_idx => $opt ) : ?>
											<?php $opt_id = $field_id . '-opt-' . $opt_idx; ?>
											<label class="centinela-cwf__radio-item">
												<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $opt ); ?>" id="<?php echo esc_attr( $opt_id ); ?>" class="centinela-cwf__radio-input" <?php echo $required ? ' required' : ''; ?> />
												<span class="centinela-cwf__radio-label"><?php echo esc_html( $opt ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
								<?php
							} else {
								$input_type = in_array( $type, array( 'email', 'tel' ), true ) ? $type : 'text';
								?>
								<input
									type="<?php echo esc_attr( $input_type ); ?>"
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( $name ); ?>"
									class="centinela-cwf__input"
									placeholder="<?php echo esc_attr( $placeholder ); ?>"
									<?php echo $required ? ' required' : ''; ?>
								/>
								<?php
							}
							if ( $required ) :
								$error_msg = __( 'Este campo es obligatorio', 'centinela-group-theme' );
								?>
								<span class="centinela-cwf__field-error" role="alert" aria-live="polite" hidden><?php echo esc_html( $error_msg ); ?></span>
							<?php endif; ?>
						</div>
						<?php
					}
					?>
				</div>
				<div class="centinela-cwf__messages" role="alert" aria-live="polite" hidden></div>
				<div class="centinela-cwf__submit-wrap">
					<button type="submit" class="centinela-cwf__submit">
						<span class="centinela-cwf__submit-text"><?php echo esc_html( $submit_text ); ?></span>
						<span class="centinela-cwf__submit-loading" aria-hidden="true" hidden><?php esc_html_e( 'Enviando…', 'centinela-group-theme' ); ?></span>
					</button>
				</div>
			</form>
		</div>
		<?php
	}
}

/**
 * Renderizar el título del formulario ANTES de .elementor-widget-container (estructura tipo Figma).
 * Así el título queda como hermano anterior al contenedor y puede verse "sobre" el formulario con fondo de Elementor.
 *
 * @param \Elementor\Widget_Base $widget Instancia del widget.
 */
function centinela_cwf_render_title_before_container( $widget ) {
	if ( ! $widget instanceof \Elementor\Widget_Base || $widget->get_name() !== 'centinela_cotizacion_web_form' ) {
		return;
	}
	$settings = $widget->get_settings_for_display();
	$form_title = isset( $settings['form_title'] ) ? $settings['form_title'] : __( 'PEDIR UNA COTIZACIÓN', 'centinela-group-theme' );
	if ( $form_title === '' ) {
		return;
	}
	$title_image = isset( $settings['title_image']['url'] ) && $settings['title_image']['url'] !== '' ? $settings['title_image'] : null;
	?>
	<div class="centinela-cwf__title-wrap" data-name="Titulo del formulario">
		<?php if ( $title_image && ! empty( $title_image['url'] ) ) : ?>
			<div class="centinela-cwf__title-icon">
				<img decoding="async" src="<?php echo esc_url( $title_image['url'] ); ?>" alt="" />
			</div>
		<?php endif; ?>
		<h2 class="centinela-cwf__title"><?php echo esc_html( $form_title ); ?></h2>
	</div>
	<?php
}
