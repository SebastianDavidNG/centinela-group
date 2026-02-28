/**
 * Cotizador Admin: autocompletado de productos Syscom, filas editables, importe y eliminar
 *
 * @package Centinela_Group_Theme
 */

(function ($) {
	'use strict';

	var config = window.centinelaCotizador || {};
	var ajaxUrl = config.ajax_url || '';
	var nonce = config.nonce || '';
	var i18n = config.i18n || {};
	var ivaDefault = typeof config.iva_default !== 'undefined' ? config.iva_default : 19;
	var logoDefaultUrl = config.logo_default_url || '';
	var cotizacionEditar = config.cotizacion_editar || null;

	var $titulo = $('#centinela-cotizador-titulo');
	var $tipo = $('#centinela-cotizador-tipo-busqueda');
	var $busqueda = $('#centinela-cotizador-busqueda');
	var $wrap = $('.centinela-cotizador-autocomplete-wrap');
	var $sugerencias = $('#centinela-cotizador-sugerencias');
	var $tbody = $('#centinela-cotizador-filas');
	var $filaVacia = $('#centinela-cotizador-fila-vacia');
	var $tipoCambio = $('#centinela-cotizador-tipo-cambio');
	var $moneda = $('#centinela-cotizador-moneda');
	var $ivaPct = $('#centinela-cotizador-iva-pct');
	var $subtotalEl = $('#centinela-cotizador-subtotal');
	var $ivaValorEl = $('#centinela-cotizador-iva-valor');
	var $totalEl = $('#centinela-cotizador-total');

	var timerBusqueda = null;
	var DEBOUNCE_MS = 350;

	function formatPrecio(num) {
		if (typeof num !== 'number' || isNaN(num)) return '0';
		return Number(num).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
	}

	function formatPrecioUSD(num) {
		if (typeof num !== 'number' || isNaN(num)) return '0.00';
		return Number(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function parseNum(val) {
		if (val === '' || val === null || val === undefined) return 0;
		var n = parseFloat(String(val).replace(/[^\d.,-]/g, '').replace(',', '.'));
		return isNaN(n) ? 0 : n;
	}

	function updatePlaceholder() {
		var tipo = $tipo.val();
		$busqueda.attr('placeholder', tipo === 'modelo' ? (i18n.buscar_placeholder_modelo || 'Buscar por modelo…') : (i18n.buscar_placeholder_titulo || 'Buscar por título…'));
	}

	function showLoading(show) {
		$wrap.toggleClass('is-loading', !!show);
	}

	function escHtml(s) {
		if (s == null) return '';
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function showSugerencias(items, message) {
		$sugerencias.empty().attr('hidden', false);
		if (message) {
			$sugerencias.append('<li><span style="padding:10px 12px;display:block;color:#646970;">' + escHtml(message || i18n.sin_resultados || 'Sin resultados.') + '</span></li>');
			return;
		}
		items.forEach(function (p) {
			var id = escHtml(p.id || '');
			var titulo = escHtml(p.titulo || '');
			var modelo = escHtml(p.modelo || '');
			var precioLista = parseNum(p.precio_lista);
			var precioOferta = parseNum(p.precio_oferta);
			var tieneOferta = !!p.tiene_oferta;
			if (precioOferta <= 0) precioOferta = precioLista;
			var btn = $('<button type="button" role="option" data-id="' + id + '" data-titulo="' + titulo + '" data-modelo="' + modelo + '" data-precio-lista="' + precioLista + '" data-precio-oferta="' + precioOferta + '" data-tiene-oferta="' + (tieneOferta ? '1' : '0') + '">' +
				'<span>' + (titulo || modelo || id) + '</span>' +
				(modelo ? '<span class="centinela-cotizador-sug-modelo">' + modelo + '</span>' : '') +
				'<span class="centinela-cotizador-sug-precio">' + formatPrecio(precioLista) + (tieneOferta ? ' / Oferta: ' + formatPrecio(precioOferta) : '') + '</span>' +
				'</button>');
			$sugerencias.append($('<li></li>').append(btn));
		});
	}

	function hideSugerencias() {
		$sugerencias.attr('hidden', true).empty();
	}

	function buscarProductos() {
		var q = $busqueda.val().trim();
		if (q.length < 2) {
			hideSugerencias();
			return;
		}
		showLoading(true);
		$.post(ajaxUrl, {
			action: 'centinela_cotizador_buscar_productos',
			nonce: nonce,
			busqueda: q,
			tipo: $tipo.val() || 'titulo'
		})
			.done(function (res) {
				if (res.success && res.data && res.data.productos && res.data.productos.length) {
					showSugerencias(res.data.productos, null);
				} else {
					showSugerencias([], i18n.sin_resultados || 'Sin resultados.');
				}
			})
			.fail(function () {
				showSugerencias([], i18n.error_busqueda || 'Error al buscar.');
			})
			.always(function () {
				showLoading(false);
			});
	}

	function getPrecioSegunTipo(producto) {
		var lista = parseNum(producto.precio_lista);
		var oferta = parseNum(producto.precio_oferta);
		var tieneOferta = !!producto.tiene_oferta;
		if (oferta <= 0) oferta = lista;
		var tipoPrecio = $('#centinela-cotizador-tipo-precio').val() || 'lista';
		return tipoPrecio === 'oferta' && tieneOferta ? oferta : lista;
	}

	function addFila(producto) {
		var id = producto.id || '';
		var modelo = producto.modelo || producto.titulo || '';
		var titulo = producto.titulo || '';
		var precioLista = parseNum(producto.precio_lista);
		var precioOferta = parseNum(producto.precio_oferta);
		var tieneOferta = !!producto.tiene_oferta;
		if (precioOferta <= 0) precioOferta = precioLista;
		var precioInicial = getPrecioSegunTipo({ precio_lista: precioLista, precio_oferta: precioOferta, tiene_oferta: tieneOferta });
		var cantidad = 1;
		var descuento = 0;
		var importe = cantidad * precioInicial * (1 - descuento / 100);

		$filaVacia.hide();

		var tituloAttr = escHtml(titulo);
		var modeloAttr = escHtml(modelo);
		var $tr = $('<tr class="producto-fila" data-producto-id="' + escHtml(id) + '" data-modelo="' + modeloAttr + '" data-titulo="' + tituloAttr + '" data-precio-lista="' + precioLista + '" data-precio-oferta="' + precioOferta + '" data-tiene-oferta="' + (tieneOferta ? '1' : '0') + '">' +
			'<td class="centinela-cotizador-col-modelo"><div class="centinela-cotizador-producto-cell">' +
			'<span class="centinela-cotizador-producto-modelo">' + (modelo ? modeloAttr : '') + '</span>' +
			'<span class="centinela-cotizador-producto-titulo">' + (titulo ? escHtml(titulo) : '') + '</span></div></td>' +
			'<td class="centinela-cotizador-col-cantidad"><input type="number" class="centinela-cotizador-input-cantidad" min="1" step="1" value="1" /></td>' +
			'<td class="centinela-cotizador-col-descuento"><input type="number" class="centinela-cotizador-input-descuento" min="0" max="100" step="0.01" value="0" /></td>' +
			'<td class="centinela-cotizador-col-precio"><input type="number" class="centinela-cotizador-input-precio" min="0" step="0.01" value="' + precioInicial + '" /></td>' +
			'<td class="centinela-cotizador-col-importe"><span class="centinela-cotizador-importe">' + formatPrecio(importe) + '</span></td>' +
			'<td class="centinela-cotizador-col-acciones"><button type="button" class="button button-link-delete centinela-cotizador-btn-eliminar">' + (i18n.eliminar || 'Eliminar') + '</button></td>' +
			'</tr>');

		function recalcImporte() {
			var cant = parseNum($tr.find('.centinela-cotizador-input-cantidad').val());
			var desc = parseNum($tr.find('.centinela-cotizador-input-descuento').val());
			var prec = parseNum($tr.find('.centinela-cotizador-input-precio').val());
			var imp = cant * prec * (1 - desc / 100);
			var $importeSpan = $tr.find('.centinela-cotizador-importe');
			$importeSpan.text(formatPrecio(imp)).attr('data-importe', imp);
			updateResumen();
		}

		$tr.find('.centinela-cotizador-input-cantidad, .centinela-cotizador-input-descuento, .centinela-cotizador-input-precio').on('input change', recalcImporte);

		$tr.find('.centinela-cotizador-btn-eliminar').on('click', function () {
			$tr.remove();
			if ($tbody.find('tr.producto-fila').length === 0) {
				$filaVacia.show();
			}
			updateResumen();
		});

		$tr.find('.centinela-cotizador-importe').attr('data-importe', importe);
		$tbody.append($tr);
		updateResumen();
	}

	function updateResumen() {
		var subtotalCOP = 0;
		$tbody.find('tr.producto-fila').each(function () {
			var imp = parseNum($(this).find('.centinela-cotizador-importe').attr('data-importe'));
			subtotalCOP += imp;
		});
		var ivaPct = parseNum($ivaPct.val());
		if (ivaPct < 0) ivaPct = 0;
		if (ivaPct > 100) ivaPct = 100;
		var ivaValorCOP = subtotalCOP * (ivaPct / 100);
		var totalCOP = subtotalCOP + ivaValorCOP;
		var tc = parseNum($tipoCambio.val());
		if (tc <= 0) tc = 1;
		var moneda = $moneda.val() || 'COP';
		if (moneda === 'USD') {
			$subtotalEl.text('USD $ ' + formatPrecioUSD(subtotalCOP / tc));
			$ivaValorEl.text('USD $ ' + formatPrecioUSD(ivaValorCOP / tc));
			$totalEl.text('USD $ ' + formatPrecioUSD(totalCOP / tc));
		} else {
			$subtotalEl.text('CO $ ' + formatPrecio(subtotalCOP));
			$ivaValorEl.text('CO $ ' + formatPrecio(ivaValorCOP));
			$totalEl.text('CO $ ' + formatPrecio(totalCOP));
		}
	}

	function fetchTipoCambio() {
		$tipoCambio.prop('readonly', true).attr('placeholder', '…');
		$.post(ajaxUrl, {
			action: 'centinela_cotizador_tipo_cambio',
			nonce: nonce
		})
			.done(function (res) {
				if (res.success && res.data && res.data.tipo_cambio) {
					$tipoCambio.val(res.data.tipo_cambio).attr('placeholder', '');
					updateResumen();
				} else {
					$tipoCambio.attr('placeholder', 'Ingrese manual');
				}
			})
			.fail(function () {
				$tipoCambio.attr('placeholder', 'Ingrese manual');
			})
			.always(function () {
				$tipoCambio.prop('readonly', false);
			});
	}

	function applyTipoPrecioToRows() {
		var tipoPrecio = $('#centinela-cotizador-tipo-precio').val() || 'lista';
		$tbody.find('tr.producto-fila').each(function () {
			var $tr = $(this);
			var lista = parseNum($tr.attr('data-precio-lista'));
			var oferta = parseNum($tr.attr('data-precio-oferta'));
			var tieneOferta = $tr.attr('data-tiene-oferta') === '1';
			if (oferta <= 0) oferta = lista;
			var precio = tipoPrecio === 'oferta' && tieneOferta ? oferta : lista;
			$tr.find('.centinela-cotizador-input-precio').val(precio).trigger('change');
		});
	}

	function onSelectSugerencia(e) {
		var $btn = $(e.currentTarget);
		if (!$btn.is('button[role="option"]')) return;
		var id = $btn.data('id');
		var titulo = $btn.data('titulo') || '';
		var modelo = $btn.data('modelo') || titulo;
		var precioLista = parseNum($btn.data('precio-lista'));
		var precioOferta = parseNum($btn.data('precio-oferta'));
		var tieneOferta = $btn.data('tiene-oferta') === 1 || $btn.data('tiene-oferta') === '1';
		var producto = { id: id, titulo: titulo, modelo: modelo, precio_lista: precioLista, precio_oferta: precioOferta, tiene_oferta: tieneOferta };
		var $filaExistente = $tbody.find('tr.producto-fila[data-producto-id="' + id + '"]');
		if ($filaExistente.length) {
			var $cant = $filaExistente.find('.centinela-cotizador-input-cantidad');
			$cant.val(parseInt($cant.val(), 10) + 1).trigger('change');
		} else {
			addFila(producto);
		}
		$busqueda.val('');
		hideSugerencias();
		$busqueda.focus();
	}

	// Inicialización
	$tipo.on('change', updatePlaceholder);
	updatePlaceholder();

	$busqueda.on('input', function () {
		clearTimeout(timerBusqueda);
		timerBusqueda = setTimeout(buscarProductos, DEBOUNCE_MS);
	});

	$busqueda.on('focus', function () {
		var q = $busqueda.val().trim();
		if (q.length >= 2 && $sugerencias.find('button[role="option"]').length) {
			$sugerencias.attr('hidden', false);
		}
	});

	$busqueda.on('keydown', function (e) {
		if (e.key === 'Escape') {
			hideSugerencias();
			$busqueda.blur();
		}
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('.centinela-cotizador-autocomplete-wrap').length) {
			hideSugerencias();
		}
	});

	$sugerencias.on('click', 'button[role="option"]', onSelectSugerencia);

	// Resumen: IVA por defecto desde config
	$ivaPct.val(ivaDefault);

	// Resumen: cambios en moneda, tipo de cambio, IVA
	$moneda.add($tipoCambio).add($ivaPct).on('input change', updateResumen);

	// Tipo de Precio (Lista / Oferta): actualizar precio en todas las filas según API Syscom
	$('#centinela-cotizador-tipo-precio').on('change', applyTipoPrecioToRows);

	// Tipo de cambio: cargar al abrir la página
	fetchTipoCambio();

	$('#centinela-cotizador-actualizar-tc').on('click', function () {
		fetchTipoCambio();
	});

	// Recoger datos del formulario para enviar/guardar
	function getCotizacionPayload() {
		var productos = [];
		$tbody.find('tr.producto-fila').each(function () {
			var $tr = $(this);
			productos.push({
				id: $tr.attr('data-producto-id'),
				modelo: $tr.attr('data-modelo') || '',
				titulo: $tr.attr('data-titulo') || '',
				cantidad: parseNum($tr.find('.centinela-cotizador-input-cantidad').val()),
				descuento: parseNum($tr.find('.centinela-cotizador-input-descuento').val()),
				precio: parseNum($tr.find('.centinela-cotizador-input-precio').val()),
				importe: parseNum($tr.find('.centinela-cotizador-importe').attr('data-importe'))
			});
		});
		var subtotalCOP = 0;
		$tbody.find('tr.producto-fila').each(function () {
			subtotalCOP += parseNum($(this).find('.centinela-cotizador-importe').attr('data-importe'));
		});
		var ivaPct = parseNum($ivaPct.val());
		var ivaValor = subtotalCOP * (ivaPct / 100);
		var total = subtotalCOP + ivaValor;
		return {
			titulo: $('#centinela-cotizador-titulo').val(),
			productos: productos,
			cliente: {
				nombre: $('#centinela-cotizador-cliente-nombre').val(),
				email: $('#centinela-cotizador-cliente-email').val(),
				vigencia: $('#centinela-cotizador-cliente-vigencia').val(),
				comentarios: $('#centinela-cotizador-cliente-comentarios').val()
			},
			contacto: {
				nombre: $('#centinela-cotizador-mi-nombre').val(),
				email: $('#centinela-cotizador-mi-email').val(),
				telefono: $('#centinela-cotizador-mi-telefono').val(),
				metodo_pago: $('#centinela-cotizador-metodo-pago').val()
			},
			moneda: $moneda.val() || 'COP',
			tipo_cambio: parseNum($tipoCambio.val()),
			tipo_precio: $('#centinela-cotizador-tipo-precio').val() || 'lista',
			iva_pct: ivaPct,
			subtotal: subtotalCOP,
			iva_valor: ivaValor,
			total: total,
			logo_url: $('#centinela-cotizador-logo-url').val() || ''
		};
	}

	function openModal(id) {
		$(id).attr('hidden', false);
	}
	function closeModal(id) {
		$(id).attr('hidden', true);
	}

	$(document).on('click', '[data-close-modal]', function () {
		var id = '#' + $(this).data('close-modal');
		closeModal(id);
	});
	$(document).on('click', '.centinela-cotizador-modal-backdrop', function () {
		var id = $(this).closest('.centinela-cotizador-modal').attr('id');
		if (id) closeModal('#' + id);
	});

	// Logo de la cotización: selector desde biblioteca de medios
	$('#centinela-cotizador-logo-select').on('click', function () {
		var $input = $('#centinela-cotizador-logo-url');
		var $img = $('#centinela-cotizador-logo-img');
		var $placeholder = $('#centinela-cotizador-logo-placeholder');
		var frame = wp.media({
			title: 'Seleccionar logo',
			library: { type: 'image' },
			multiple: false,
			button: { text: 'Usar este logo' }
		});
		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
			var urlFull = att.url;
			$input.val(urlFull);
			$img.attr('src', url).show();
			$placeholder.hide();
		});
		frame.open();
	});
	$('#centinela-cotizador-logo-reset').on('click', function () {
		var $input = $('#centinela-cotizador-logo-url');
		var $img = $('#centinela-cotizador-logo-img');
		var $placeholder = $('#centinela-cotizador-logo-placeholder');
		$input.val(logoDefaultUrl || '');
		if (logoDefaultUrl) {
			$img.attr('src', logoDefaultUrl).show();
			$placeholder.hide();
		} else {
			$img.hide().attr('src', '');
			$placeholder.show();
		}
	});

	// Enviar y Guardar: abrir modal (cargar vista previa del correo), al pulsar Enviar -> guardar + enviar email
	$('#centinela-cotizador-enviar-guardar').on('click', function () {
		if ($tbody.find('tr.producto-fila').length === 0) {
			alert(i18n.agregue_productos || 'Agregue al menos un producto a la cotización.');
			return;
		}
		$('#centinela-modal-enviar-msg').removeClass('success error').text('');
		var $preview = $('#centinela-cotizador-email-preview');
		var $loading = $('#centinela-cotizador-email-preview-loading');
		$preview.attr('srcdoc', '');
		$loading.show();
		openModal('#centinela-cotizador-modal-enviar-guardar');
		var datos = getCotizacionPayload();
		$.post(ajaxUrl, {
			action: 'centinela_cotizador_preview_email',
			nonce: nonce,
			datos: JSON.stringify(datos)
		})
			.done(function (res) {
				$loading.hide();
				if (res.success && res.data && res.data.html) {
					$preview.attr('srcdoc', res.data.html);
				}
			})
			.fail(function () {
				$loading.hide();
			});
	});

	$('#centinela-cotizador-modal-enviar-btn').on('click', function () {
		var $msg = $('#centinela-modal-enviar-msg');
		$msg.removeClass('success error').text('…');
		var datos = getCotizacionPayload();
		$.post(ajaxUrl, {
			action: 'centinela_cotizador_enviar_guardar',
			nonce: nonce,
			datos: JSON.stringify(datos),
			formato_adjunto: $('#centinela-modal-formato-adjunto').val() || 'pdf',
			editar_id: $('#centinela-cotizador-editar-id').val() || 0
		})
			.done(function (res) {
				if (res.success) {
					$msg.addClass('success').text(res.data.message || '');
					setTimeout(function () { closeModal('#centinela-cotizador-modal-enviar-guardar'); }, 2000);
				} else {
					$msg.addClass('error').text(res.data && res.data.message ? res.data.message : 'Error');
				}
			})
			.fail(function (xhr) {
				var m = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Error de conexión.';
				$msg.addClass('error').text(m);
			});
	});

	// Ver cómo llegaría el correo: genera HTML + adjunto en el servidor, abre el HTML en nueva pestaña
	$('#centinela-cotizador-modal-ver-envio-btn').on('click', function () {
		var $msg = $('#centinela-modal-enviar-msg');
		$msg.removeClass('success error').text('…');
		var datos = getCotizacionPayload();
		$.post(ajaxUrl, {
			action: 'centinela_cotizador_preview_envio',
			nonce: nonce,
			datos: JSON.stringify(datos),
			formato_adjunto: $('#centinela-modal-formato-adjunto').val() || 'pdf',
			editar_id: $('#centinela-cotizador-editar-id').val() || 0
		})
			.done(function (res) {
				if (res.success && res.data) {
					$msg.addClass('success').text(res.data.message || '');
					if (res.data.html_url) {
						window.open(res.data.html_url, '_blank', 'noopener');
					}
					if (res.data.adjunto_url) {
						var linkText = res.data.adjunto_nombre ? 'Descargar ' + res.data.adjunto_nombre : 'Descargar adjunto';
						$msg.append(' <a href="' + res.data.adjunto_url + '" target="_blank" rel="noopener">' + linkText + '</a>');
					}
				} else {
					$msg.addClass('error').text(res.data && res.data.message ? res.data.message : 'Error');
				}
			})
			.fail(function (xhr) {
				var m = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Error de conexión.';
				$msg.addClass('error').text(m);
			});
	});

	// Guardar: modal confirmación moneda -> guardar
	$('#centinela-cotizador-guardar').on('click', function () {
		if ($tbody.find('tr.producto-fila').length === 0) {
			alert(i18n.agregue_productos || 'Agregue al menos un producto a la cotización.');
			return;
		}
		var moneda = $moneda.val() || 'COP';
		$('#centinela-modal-guardar-text').text((i18n.guardar_en_moneda || '¿Desea guardar la cotización en la moneda seleccionada?') + ' (' + moneda + ')');
		$('#centinela-modal-guardar-msg').removeClass('success error').text('');
		openModal('#centinela-cotizador-modal-guardar');
	});

	$('#centinela-cotizador-modal-guardar-btn').on('click', function () {
		var $msg = $('#centinela-modal-guardar-msg');
		$msg.removeClass('success error').text('…');
		var datos = getCotizacionPayload();
		$.post(ajaxUrl, {
			action: 'centinela_cotizador_guardar',
			nonce: nonce,
			datos: JSON.stringify(datos),
			editar_id: $('#centinela-cotizador-editar-id').val() || 0
		})
			.done(function (res) {
				if (res.success) {
					$msg.addClass('success').text(res.data.message || '');
					setTimeout(function () { closeModal('#centinela-cotizador-modal-guardar'); }, 1500);
				} else {
					$msg.addClass('error').text(res.data && res.data.message ? res.data.message : 'Error');
				}
			})
			.fail(function (xhr) {
				var m = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Error de conexión.';
				$msg.addClass('error').text(m);
			});
	});

	// Enviar al Carrito: modal con dirección/ciudad/departamento -> crear orden y mostrar link
	$('#centinela-cotizador-enviar-carrito').on('click', function () {
		if ($tbody.find('tr.producto-fila').length === 0) {
			alert(i18n.agregue_productos || 'Agregue al menos un producto a la cotización.');
			return;
		}
		$('#centinela-modal-direccion, #centinela-modal-ciudad, #centinela-modal-departamento').val('');
		$('#centinela-modal-carrito-msg').removeClass('success error').text('');
		$('#centinela-cotizador-link-wrap').hide();
		openModal('#centinela-cotizador-modal-carrito');
	});

	$('#centinela-cotizador-modal-carrito-btn').on('click', function () {
		var $msg = $('#centinela-modal-carrito-msg');
		$msg.removeClass('success error').text('…');
		var datos = getCotizacionPayload();
		$.post(ajaxUrl, {
			action: 'centinela_cotizador_enviar_carrito',
			nonce: nonce,
			datos: JSON.stringify(datos),
			centinela_direccion: $('#centinela-modal-direccion').val(),
			centinela_ciudad: $('#centinela-modal-ciudad').val(),
			centinela_departamento: $('#centinela-modal-departamento').val()
		})
			.done(function (res) {
				if (res.success && res.data.redirect) {
					$msg.addClass('success').text(res.data.message || '');
					$('#centinela-cotizador-pago-link').val(res.data.redirect);
					$('#centinela-cotizador-link-wrap').show();
				} else {
					$msg.addClass('error').text(res.data && res.data.message ? res.data.message : 'Error');
				}
			})
			.fail(function (xhr) {
				var m = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Error de conexión.';
				$msg.addClass('error').text(m);
			});
	});

	$('#centinela-cotizador-copiar-link').on('click', function () {
		var input = document.getElementById('centinela-cotizador-pago-link');
		if (input && input.value) {
			input.select();
			input.setSelectionRange(0, 99999);
			document.execCommand('copy');
			$(this).text(i18n.link_copiado || 'Link copiado.');
			setTimeout(function () { $('#centinela-cotizador-copiar-link').text('Copiar'); }, 2000);
		}
	});

	// Cargar cotización al editar (desde Mis Cotizaciones)
	function loadCotizacionEditar(editar) {
		if (!editar || !editar.datos) return;
		var datos = editar.datos;
		var id = editar.id ? parseInt(editar.id, 10) : 0;
		if (id) $('#centinela-cotizador-editar-id').val(id);

		$titulo.val(datos.titulo || '');

		$tbody.find('tr.producto-fila').remove();
		$filaVacia.show();
		var productos = datos.productos || [];
		productos.forEach(function (p) {
			var precio = parseNum(p.precio);
			addFila({
				id: p.id || '',
				modelo: p.modelo || '',
				titulo: p.titulo || '',
				precio_lista: precio,
				precio_oferta: precio,
				tiene_oferta: false
			});
			var $last = $tbody.find('tr.producto-fila').last();
			$last.find('.centinela-cotizador-input-cantidad').val(parseNum(p.cantidad) || 1);
			$last.find('.centinela-cotizador-input-descuento').val(parseNum(p.descuento) || 0);
			$last.find('.centinela-cotizador-input-precio').val(precio);
			$last.attr('data-modelo', (p.modelo != null && p.modelo !== '') ? p.modelo : '');
			$last.find('.centinela-cotizador-producto-modelo').text(p.modelo || '');
			$last.find('.centinela-cotizador-input-cantidad').trigger('change');
		});

		var cliente = datos.cliente || {};
		$('#centinela-cotizador-cliente-nombre').val(cliente.nombre || '');
		$('#centinela-cotizador-cliente-email').val(cliente.email || '');
		$('#centinela-cotizador-cliente-vigencia').val(cliente.vigencia || '');
		$('#centinela-cotizador-cliente-comentarios').val(cliente.comentarios || '');

		var contacto = datos.contacto || {};
		$('#centinela-cotizador-mi-nombre').val(contacto.nombre || '');
		$('#centinela-cotizador-mi-email').val(contacto.email || '');
		$('#centinela-cotizador-mi-telefono').val(contacto.telefono || '');
		$('#centinela-cotizador-metodo-pago').val(contacto.metodo_pago || '');

		$moneda.val(datos.moneda || 'COP');
		$tipoCambio.val(parseNum(datos.tipo_cambio));
		$('#centinela-cotizador-tipo-precio').val(datos.tipo_precio || 'lista');
		$ivaPct.val(parseNum(datos.iva_pct) || 19);

		var logoUrl = datos.logo_url || logoDefaultUrl || '';
		$('#centinela-cotizador-logo-url').val(logoUrl);
		if (logoUrl) {
			$('#centinela-cotizador-logo-img').attr('src', logoUrl).show();
			$('#centinela-cotizador-logo-placeholder').hide();
		} else {
			$('#centinela-cotizador-logo-img').hide().attr('src', '');
			$('#centinela-cotizador-logo-placeholder').show();
		}

		updateResumen();
	}

	if (cotizacionEditar && cotizacionEditar.datos) {
		loadCotizacionEditar(cotizacionEditar);
	}

	// Primera actualización del resumen
	updateResumen();

})(jQuery);
