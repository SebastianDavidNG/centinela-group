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
	var debugPreciosAdmin = !!config.debug_precios_admin;
	var showMailMeta = !!config.show_mail_meta;
	var ivaDefault = typeof config.iva_default !== 'undefined' ? config.iva_default : 19;
	var logoDefaultUrl = config.logo_default_url || '';
	var cotizacionEditar = config.cotizacion_editar || null;
	var misCotizacionesUrl = config.mis_cotizaciones_url || '';

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
	var $subtotalUsdRef = $('#centinela-cotizador-subtotal-usd-ref');
	var $ivaValorUsdRef = $('#centinela-cotizador-iva-valor-usd-ref');
	var $totalUsdRef = $('#centinela-cotizador-total-usd-ref');
	var $refUsdBloque = $('#centinela-cotizador-ref-usd-bloque');
	var $refUsdSubtotalEl = $('#centinela-cotizador-ref-usd-subtotal');
	var $refUsdIvaEl = $('#centinela-cotizador-ref-usd-iva');
	var $refUsdTotalEl = $('#centinela-cotizador-ref-usd-total');
	var $manualRef = $('#centinela-cotizador-manual-ref');
	var $manualModelo = $('#centinela-cotizador-manual-modelo');
	var $manualDescripcion = $('#centinela-cotizador-manual-descripcion');
	var $manualCantidad = $('#centinela-cotizador-manual-cantidad');
	var $manualDescuento = $('#centinela-cotizador-manual-descuento');
	var $manualPrecio = $('#centinela-cotizador-manual-precio');
	var $manualAddBtn = $('#centinela-cotizador-manual-add');
	var $manualCancelBtn = $('#centinela-cotizador-manual-cancel');

	var timerBusqueda = null;
	var DEBOUNCE_MS = 180;
	var xhrBusqueda = null;
	var lastSuggestions = [];
	var lastSuggestionsMessage = '';
	var lastSuggestionsQuery = '';
	var lastSuggestionsTipo = '';
	var tcBaseCop = 0;
	var manualEditingRowId = '';

	function clearManualForm() {
		$manualRef.val('');
		$manualModelo.val('');
		$manualDescripcion.val('');
		$manualPrecio.val('');
		$manualCantidad.val('1');
		$manualDescuento.val('0');
	}

	function setManualFormMode(isEditing) {
		manualEditingRowId = isEditing ? manualEditingRowId : '';
		$manualAddBtn.text(isEditing ? (i18n.manual_update || 'Guardar cambios') : (i18n.manual_add || 'Agregar a la tabla'));
		$manualAddBtn.toggleClass('button-primary', !!isEditing);
		$manualAddBtn.toggleClass('button-secondary', !isEditing);
		$manualAddBtn.attr('title', isEditing ? (i18n.manual_update_tooltip || 'Guardar cambios del producto manual') : '');
		$manualCancelBtn.attr('title', i18n.manual_cancel_tooltip || 'Cancelar edición del producto manual');
		$manualCancelBtn.prop('hidden', !isEditing);
	}

	function startManualEdit($tr) {
		if (!$tr || !$tr.length) return;
		$tbody.find('tr.producto-fila.is-editing-manual').removeClass('is-editing-manual');
		manualEditingRowId = String($tr.attr('data-producto-id') || '');
		$tr.addClass('is-editing-manual');
		$manualRef.val($tr.attr('data-referencia') || '');
		$manualModelo.val($tr.attr('data-modelo') || '');
		$manualDescripcion.val($tr.attr('data-titulo') || '');
		$manualCantidad.val($tr.find('.centinela-cotizador-input-cantidad').val() || '1');
		$manualDescuento.val($tr.find('.centinela-cotizador-input-descuento').val() || '0');
		$manualPrecio.val($tr.find('.centinela-cotizador-input-precio').val() || '');
		setManualFormMode(true);
		var $manualWrap = $('.centinela-cotizador-manual-wrap').first();
		if ($manualWrap.length && $manualWrap[0] && typeof $manualWrap[0].scrollIntoView === 'function') {
			$manualWrap[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
		$manualModelo.trigger('focus');
	}

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
		lastSuggestions = Array.isArray(items) ? items.slice() : [];
		lastSuggestionsMessage = message || '';
		lastSuggestionsQuery = ($busqueda.val() || '').trim();
		lastSuggestionsTipo = $tipo.val() || 'titulo';
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
				'<span class="centinela-cotizador-sug-titulo">' + (titulo || modelo || id) + '</span>' +
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
			if (xhrBusqueda && xhrBusqueda.readyState !== 4) {
				xhrBusqueda.abort();
				xhrBusqueda = null;
			}
			hideSugerencias();
			return;
		}
		if (xhrBusqueda && xhrBusqueda.readyState !== 4) {
			xhrBusqueda.abort();
		}
		showLoading(true);
		xhrBusqueda = $.post(ajaxUrl, {
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
				xhrBusqueda = null;
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
		var isManual = !!producto.manual;
		var id = isManual
			? (producto.id && String(producto.id).indexOf('manual-') === 0
				? String(producto.id)
				: 'manual-' + Date.now() + '-' + Math.random().toString(36).slice(2, 9))
			: String(producto.id || '');
		var referencia = producto.referencia != null ? String(producto.referencia) : '';
		var modelo = producto.modelo || producto.titulo || '';
		var titulo = producto.titulo || '';
		var precioLista = parseNum(producto.precio_lista);
		var precioOferta = parseNum(producto.precio_oferta);
		var tieneOferta = !!producto.tiene_oferta;
		if (isManual) {
			var pIniSrc = producto.precio_inicial != null ? parseNum(producto.precio_inicial) : precioLista;
			precioLista = pIniSrc;
			precioOferta = pIniSrc;
			tieneOferta = false;
		} else if (precioOferta <= 0) {
			precioOferta = precioLista;
		}
		var precioInicial = producto.precio_inicial != null ? parseNum(producto.precio_inicial) : getPrecioSegunTipo({ precio_lista: precioLista, precio_oferta: precioOferta, tiene_oferta: tieneOferta });
		var cantidad = producto.cantidad_inicial != null ? Math.max(1, parseInt(producto.cantidad_inicial, 10) || 1) : 1;
		var descuento = producto.descuento_inicial != null ? parseNum(producto.descuento_inicial) : 0;
		var importe = cantidad * precioInicial * (1 - descuento / 100);

		$filaVacia.hide();

		var tituloAttr = escHtml(titulo);
		var modeloAttr = escHtml(modelo);
		var refAttr = escHtml(referencia);
		var refBlock = referencia ? '<span class="centinela-cotizador-producto-ref">' + escHtml(referencia) + '</span>' : '';
		var manualAttr = isManual ? ' data-manual="1"' : '';
		var manualEditBtn = isManual ? '<button type="button" class="button button-small centinela-cotizador-btn-editar-manual">' + (i18n.manual_edit || 'Editar') + '</button>' : '';
		var $tr = $('<tr class="producto-fila"' + manualAttr + ' data-producto-id="' + escHtml(id) + '" data-modelo="' + modeloAttr + '" data-titulo="' + tituloAttr + '" data-referencia="' + refAttr + '" data-precio-lista="' + precioLista + '" data-precio-oferta="' + precioOferta + '" data-tiene-oferta="' + (tieneOferta ? '1' : '0') + '">' +
			'<td class="centinela-cotizador-col-modelo"><div class="centinela-cotizador-producto-cell">' +
			refBlock +
			'<span class="centinela-cotizador-producto-modelo">' + (modelo ? escHtml(modelo) : '') + '</span>' +
			'<span class="centinela-cotizador-producto-titulo">' + (titulo ? escHtml(titulo) : '') + '</span></div></td>' +
			'<td class="centinela-cotizador-col-cantidad"><input type="number" class="centinela-cotizador-input-cantidad" min="1" step="1" value="' + cantidad + '" /></td>' +
			'<td class="centinela-cotizador-col-descuento"><input type="number" class="centinela-cotizador-input-descuento" min="0" max="100" step="0.01" value="' + descuento + '" /></td>' +
			'<td class="centinela-cotizador-col-precio"><input type="number" class="centinela-cotizador-input-precio" min="0" step="0.01" value="' + precioInicial + '" /></td>' +
			'<td class="centinela-cotizador-col-importe"><span class="centinela-cotizador-importe">' + formatPrecio(importe) + '</span></td>' +
			'<td class="centinela-cotizador-col-acciones">' + manualEditBtn + '<button type="button" class="button button-link-delete centinela-cotizador-btn-eliminar">' + (i18n.eliminar || 'Eliminar') + '</button></td>' +
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
			var deletingRowId = String($tr.attr('data-producto-id') || '');
			$tr.remove();
			if (manualEditingRowId && manualEditingRowId === deletingRowId) {
				manualEditingRowId = '';
				clearManualForm();
				setManualFormMode(false);
			}
			if ($tbody.find('tr.producto-fila').length === 0) {
				$filaVacia.show();
			}
			updateResumen();
		});
		$tr.find('.centinela-cotizador-btn-editar-manual').on('click', function () {
			startManualEdit($tr);
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
		var tcRaw = parseNum($tipoCambio.val());
		if (tcBaseCop <= 0 && tcRaw > 0) {
			tcBaseCop = tcRaw;
		}
		var tc = tcRaw > 0 ? tcRaw : 1;
		var moneda = $moneda.val() || 'COP';
		var tcCop = tcRaw > 0 ? tcRaw : tcBaseCop;
		var factorCop = tcBaseCop > 0 && tcCop > 0 ? (tcCop / tcBaseCop) : 1;
		var subtotalCOPMostrado = subtotalCOP;
		var ivaValorCOPMostrado = ivaValorCOP;
		var totalCOPMostrado = totalCOP;
		if (moneda === 'COP' && tcBaseCop > 0 && tcCop > 0) {
			subtotalCOPMostrado = subtotalCOP * factorCop;
			ivaValorCOPMostrado = ivaValorCOP * factorCop;
			totalCOPMostrado = totalCOP * factorCop;
		}
		function hideCopUsdRefs() {
			$subtotalUsdRef.prop('hidden', true).text('');
			$ivaValorUsdRef.prop('hidden', true).text('');
			$totalUsdRef.prop('hidden', true).text('');
		}

		if (moneda === 'USD') {
			hideCopUsdRefs();
			$subtotalEl.text('USD $ ' + formatPrecioUSD(subtotalCOP / tc));
			$ivaValorEl.text('USD $ ' + formatPrecioUSD(ivaValorCOP / tc));
			$totalEl.text('USD $ ' + formatPrecioUSD(totalCOP / tc));
			if ($refUsdBloque.length) {
				$refUsdBloque.prop('hidden', true);
			}
		} else {
			$subtotalEl.text('CO $ ' + formatPrecio(subtotalCOPMostrado));
			$ivaValorEl.text('CO $ ' + formatPrecio(ivaValorCOPMostrado));
			$totalEl.text('CO $ ' + formatPrecio(totalCOPMostrado));
			if (tcRaw > 0) {
				var usdSub = subtotalCOPMostrado / tcRaw;
				var usdIva = ivaValorCOPMostrado / tcRaw;
				var usdTot = totalCOPMostrado / tcRaw;
				var aprox = i18n.aprox_usd_prefix || '≈ USD $ ';
				var lineUsd = function (n) {
					return aprox + formatPrecioUSD(n);
				};
				if ($subtotalUsdRef.length) {
					$subtotalUsdRef.prop('hidden', false).text(lineUsd(usdSub));
				}
				if ($ivaValorUsdRef.length) {
					$ivaValorUsdRef.prop('hidden', false).text(lineUsd(usdIva));
				}
				if ($totalUsdRef.length) {
					$totalUsdRef.prop('hidden', false).text(lineUsd(usdTot));
				}
				if ($refUsdBloque.length && $refUsdSubtotalEl.length && $refUsdIvaEl.length && $refUsdTotalEl.length) {
					$refUsdBloque.prop('hidden', false);
					$refUsdSubtotalEl.text('USD $ ' + formatPrecioUSD(usdSub));
					$refUsdIvaEl.text('USD $ ' + formatPrecioUSD(usdIva));
					$refUsdTotalEl.text('USD $ ' + formatPrecioUSD(usdTot));
				}
			} else {
				hideCopUsdRefs();
				if ($refUsdBloque.length) {
					$refUsdBloque.prop('hidden', true);
				}
			}
		}
	}

	var tcMsgTimer = null;
	/** Si el usuario editó el TRM a mano, no sobrescribir el campo cuando llegue tarde la respuesta de Syscom. */
	var tcApplySyscomToField = true;
	var tcFetchSeq = 0;

	function showTcMsg(text) {
		var $m = $('#centinela-cotizador-tc-msg');
		if (!$m.length) return;
		if (tcMsgTimer) clearTimeout(tcMsgTimer);
		$m.text(text || '');
		if (text) {
			tcMsgTimer = setTimeout(function () {
				$m.text('');
				tcMsgTimer = null;
			}, 4500);
		}
	}

	/**
	 * @param {{ force?: boolean }} opts force=true (Cargar TRM Syscom): siempre rellena el campo con la API.
	 */
	function fetchTipoCambio(opts) {
		opts = opts || {};
		var force = !!opts.force;
		if (force) {
			tcApplySyscomToField = true;
		}
		tcFetchSeq += 1;
		var seq = tcFetchSeq;
		$tipoCambio.prop('readonly', true).attr('placeholder', '…');
		showTcMsg('');
		$.post(ajaxUrl, {
			action: 'centinela_cotizador_tipo_cambio',
			nonce: nonce
		})
			.done(function (res) {
				if (seq !== tcFetchSeq) {
					return;
				}
				if (res.success && res.data && res.data.tipo_cambio) {
					if (tcApplySyscomToField || force) {
						$tipoCambio.val(res.data.tipo_cambio).attr('placeholder', '');
					}
					updateResumen();
					if (tcApplySyscomToField || force) {
						showTcMsg(i18n.trm_syscom_ok || 'TRM Syscom cargado. Totales actualizados.');
					}
				} else {
					$tipoCambio.attr('placeholder', 'Ingrese manual');
				}
			})
			.fail(function (xhr, status) {
				if (status === 'abort') {
					return;
				}
				if (seq !== tcFetchSeq) {
					return;
				}
				$tipoCambio.attr('placeholder', 'Ingrese manual');
			})
			.always(function () {
				if (seq === tcFetchSeq) {
					$tipoCambio.prop('readonly', false);
				}
			});
	}

	function applyTipoPrecioToRows() {
		var tipoPrecio = $('#centinela-cotizador-tipo-precio').val() || 'lista';
		$tbody.find('tr.producto-fila').each(function () {
			var $tr = $(this);
			if ($tr.attr('data-manual') === '1') {
				return;
			}
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
			return;
		}
		var tipoActual = $tipo.val() || 'titulo';
		if (
			q.length >= 2 &&
			lastSuggestionsQuery === q &&
			lastSuggestionsTipo === tipoActual &&
			(lastSuggestions.length > 0 || lastSuggestionsMessage)
		) {
			showSugerencias(lastSuggestions, lastSuggestionsMessage);
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
	$moneda.add($ivaPct).on('input change', updateResumen);

	function markTcEditedByUser() {
		tcApplySyscomToField = false;
	}

	function onTipoCambioLive() {
		markTcEditedByUser();
		updateResumen();
	}

	// TRM: recalcular totales al vuelo (input + change cubre flechas/step en la mayoría de navegadores; paste/keyup refuerza casos raros)
	$tipoCambio.on('input change', onTipoCambioLive);
	$tipoCambio.on('paste', function () {
		markTcEditedByUser();
		setTimeout(updateResumen, 0);
	});
	$tipoCambio.on('keyup', function (e) {
		if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
			onTipoCambioLive();
		}
	});
	$tipoCambio.on('blur', updateResumen);

	// Tipo de Precio (Lista / Oferta): actualizar precio en todas las filas según API Syscom
	$('#centinela-cotizador-tipo-precio').on('change', applyTipoPrecioToRows);

	$manualAddBtn.on('click', function () {
		var ref = String($manualRef.val() || '').trim();
		var modelo = String($manualModelo.val() || '').trim();
		var titulo = String($manualDescripcion.val() || '').trim();
		var cant = parseNum($manualCantidad.val());
		var desc = parseNum($manualDescuento.val());
		var prec = parseNum($manualPrecio.val());
		if (!modelo) {
			window.alert(i18n.manual_error_modelo || 'Indique el modelo o nombre del producto.');
			return;
		}
		if (prec <= 0) {
			window.alert(i18n.manual_error_precio || 'Indique un precio mayor que cero.');
			return;
		}
		if (cant < 1) {
			cant = 1;
		}
		if (manualEditingRowId) {
			var $row = $tbody.find('tr.producto-fila[data-producto-id="' + manualEditingRowId + '"][data-manual="1"]').first();
			if ($row.length) {
				$row.attr('data-referencia', ref);
				$row.attr('data-modelo', modelo);
				$row.attr('data-titulo', titulo);
				$row.find('.centinela-cotizador-producto-ref').remove();
				if (ref) {
					$row.find('.centinela-cotizador-producto-cell').prepend('<span class="centinela-cotizador-producto-ref">' + escHtml(ref) + '</span>');
				}
				$row.find('.centinela-cotizador-producto-modelo').text(modelo);
				$row.find('.centinela-cotizador-producto-titulo').text(titulo || '');
				$row.find('.centinela-cotizador-input-cantidad').val(cant);
				$row.find('.centinela-cotizador-input-descuento').val(desc);
				$row.find('.centinela-cotizador-input-precio').val(prec).trigger('change');
				$row.removeClass('is-editing-manual');
			}
			manualEditingRowId = '';
			clearManualForm();
			$tbody.find('tr.producto-fila.is-editing-manual').removeClass('is-editing-manual');
			setManualFormMode(false);
			return;
		}
		addFila({
			manual: true,
			referencia: ref,
			modelo: modelo,
			titulo: titulo,
			precio_lista: prec,
			precio_oferta: prec,
			tiene_oferta: false,
			precio_inicial: prec,
			cantidad_inicial: cant,
			descuento_inicial: desc
		});
		clearManualForm();
		$manualModelo.val('').trigger('focus');
	});
	$manualCancelBtn.on('click', function () {
		manualEditingRowId = '';
		clearManualForm();
		$tbody.find('tr.producto-fila.is-editing-manual').removeClass('is-editing-manual');
		setManualFormMode(false);
	});

	// TRM: al crear cotización nueva se carga Syscom; al editar una guardada se respeta el TRM de la cotización (sin pisar).
	if (cotizacionEditar && cotizacionEditar.datos) {
		// loadCotizacionEditar más abajo aplica tipo_cambio guardado.
	} else {
		fetchTipoCambio();
	}

	// Aplicar el valor del campo (manual o ya cargado) y recalcular subtotal/total en USD.
	$('#centinela-cotizador-actualizar-tc').on('click', function () {
		var tc = parseNum($tipoCambio.val());
		if (tc <= 0) {
			showTcMsg(i18n.tc_invalido || 'Indica un tipo de cambio mayor que 0.');
			$tipoCambio.trigger('focus');
			return;
		}
		updateResumen();
		showTcMsg(i18n.tc_recalculado || 'Totales actualizados con el tipo de cambio del campo.');
	});

	$('#centinela-cotizador-sync-tc-syscom').on('click', function () {
		fetchTipoCambio({ force: true });
	});

	if (debugPreciosAdmin) {
		$('#centinela-cotizador-debug-precios-btn').on('click', function () {
			var id = String($('#centinela-cotizador-debug-producto-id').val() || '').replace(/\D/g, '');
			var $out = $('#centinela-cotizador-debug-precios-out');
			if (!id) {
				$out.removeAttr('hidden').text(i18n.debug_id_required || 'Introduce un ID numérico.');
				return;
			}
			$out.removeAttr('hidden').text('…');
			$.post(ajaxUrl, {
				action: 'centinela_cotizador_debug_producto_precios',
				nonce: nonce,
				producto_id: id
			})
				.done(function (res) {
					if (res.success && res.data && res.data.report) {
						$out.text(JSON.stringify(res.data.report, null, 2));
					} else {
						$out.text(JSON.stringify(res, null, 2));
					}
				})
				.fail(function (xhr) {
					$out.text(xhr.responseText || 'Error');
				});
		});
	}

	// Recoger datos del formulario para enviar/guardar
	function getCotizacionPayload() {
		var productos = [];
		$tbody.find('tr.producto-fila').each(function () {
			var $tr = $(this);
			var row = {
				id: $tr.attr('data-producto-id'),
				modelo: $tr.attr('data-modelo') || '',
				titulo: $tr.attr('data-titulo') || '',
				cantidad: parseNum($tr.find('.centinela-cotizador-input-cantidad').val()),
				descuento: parseNum($tr.find('.centinela-cotizador-input-descuento').val()),
				precio: parseNum($tr.find('.centinela-cotizador-input-precio').val()),
				importe: parseNum($tr.find('.centinela-cotizador-importe').attr('data-importe'))
			};
			var ref = ($tr.attr('data-referencia') || '').trim();
			if (ref) {
				row.referencia = ref;
			}
			if ($tr.attr('data-manual') === '1') {
				row.manual = true;
			}
			productos.push(row);
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
			orden_compra: String($('#centinela-cotizador-orden-compra').val() || '').trim(),
			productos: productos,
			cliente: {
				nombre: $('#centinela-cotizador-cliente-nombre').val(),
				telefono: $('#centinela-cotizador-cliente-telefono').val(),
				nit_cc: $('#centinela-cotizador-cliente-nitcc').val(),
				sitio_web: $('#centinela-cotizador-cliente-sitio-web').val(),
				direccion: $('#centinela-cotizador-cliente-direccion').val(),
				direccion_fisica: $('#centinela-cotizador-cliente-direccion-fisica').val(),
				ciudad: $('#centinela-cotizador-cliente-ciudad').val() || '',
				email: $('#centinela-cotizador-cliente-email').val(),
				email_adicionales: $('#centinela-cotizador-cliente-email-adicionales').val(),
				vigencia: $('#centinela-cotizador-cliente-vigencia').val(),
				comentarios: $('#centinela-cotizador-cliente-comentarios').val()
			},
			contacto: {
				nombre: $('#centinela-cotizador-mi-nombre').val(),
				email: $('#centinela-cotizador-mi-email').val(),
				telefono: $('#centinela-cotizador-mi-telefono').val(),
				metodo_pago: $('#centinela-cotizador-metodo-pago').val()
			},
			envio: {
				embarcar_a: $('#centinela-cotizador-embarcar-a').val() || '',
				via: $('#centinela-cotizador-envio-via').val() || '',
				quien_recibe: $('#centinela-cotizador-envio-quien-recibe').val() || '',
				direccion: $('#centinela-cotizador-envio-direccion').val() || '',
				ciudad: $('#centinela-cotizador-envio-ciudad').val() || '',
				cel: $('#centinela-cotizador-envio-cel').val() || ''
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

	/** Tras el primer guardado en una cotización nueva, el servidor devuelve id; lo guardamos para no crear duplicados. */
	function rememberCotizacionPostId(data) {
		if (!data || data.id === undefined || data.id === null || data.id === '') {
			return;
		}
		var id = parseInt(data.id, 10);
		if (id > 0) {
			$('#centinela-cotizador-editar-id').val(String(id));
		}
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
		$('#centinela-cotizador-modal-descargar-pdf-btn').prop('disabled', false).text(i18n.descargar_pdf || 'Descargar PDF');
		var $preview = $('#centinela-cotizador-email-preview');
		var $loading = $('#centinela-cotizador-email-preview-loading');
		$preview.attr('srcdoc', '');
		$loading.show();
		openModal('#centinela-cotizador-modal-enviar-guardar');
		var datos = getCotizacionPayload();
		$.post(ajaxUrl, {
			action: 'centinela_cotizador_preview_email',
			nonce: nonce,
			datos: JSON.stringify(datos),
			editar_id: $('#centinela-cotizador-editar-id').val() || 0
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
					var d = res.data || {};
					rememberCotizacionPostId(d);
					var enviado = d.enviado !== false && d.enviado !== 0;
					var mailMetaBlock = showMailMeta ? {
						mail_template: d.mail_template,
						mail_body_only: d.mail_body_only,
						mail_split_send: d.mail_split_send,
						mail_trace: d.mail_trace,
						mail_trace_pdf: d.mail_trace_pdf,
						mail_config_warnings: d.mail_config_warnings,
						mail_to: d.mail_to,
						mail_from: d.mail_from,
						mail_reply_to: d.mail_reply_to,
						mail_attachments_n: d.mail_attachments_n,
						enviado: enviado
					} : null;
					if (mailMetaBlock && typeof console !== 'undefined' && console.info) {
						// Consola: pestaña "Consola" en DevTools (no depende de la pestaña Red).
						console.info('[Centinela cotizador] centinela_cotizador_enviar_guardar', mailMetaBlock, 'respuesta completa:', res);
					}
					if (!enviado) {
						$msg.removeClass('success').addClass('error').text(d.message || '');
						if (d.mail_error) {
							$msg.append(document.createTextNode(' '));
							$msg.append($('<code style="display:block;margin-top:6px;word-break:break-all;font-size:12px;"></code>').text(d.mail_error));
						}
					} else {
						$msg.removeClass('error').addClass('success').text(d.message || '');
						// Con metadatos de correo el modal permanece más tiempo para poder leer / copiar.
						var closeMs = showMailMeta ? 90000 : 2000;
						setTimeout(function () { closeModal('#centinela-cotizador-modal-enviar-guardar'); }, closeMs);
					}
					if (mailMetaBlock) {
						$msg.append($('<pre class="centinela-cotizador-mail-meta" style="margin-top:10px;padding:8px;background:#f4f4f4;border:1px solid #ddd;border-radius:4px;font-size:11px;white-space:pre-wrap;word-break:break-all;max-height:180px;overflow:auto;text-align:left;"></pre>').text(JSON.stringify(mailMetaBlock, null, 2)));
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

	// Descargar PDF de vista previa (misma generación que "Ver cómo llegaría", sin abrir el HTML en pestaña)
	$('#centinela-cotizador-modal-descargar-pdf-btn').on('click', function () {
		var $btn = $('#centinela-cotizador-modal-descargar-pdf-btn');
		var $msg = $('#centinela-modal-enviar-msg');
		var labelPdf = i18n.descargar_pdf || 'Descargar PDF';
		var labelWorking = i18n.generando_pdf || 'Generando PDF…';
		$msg.removeClass('success error').text('');
		$btn.prop('disabled', true).text(labelWorking);
		var datos = getCotizacionPayload();
		$.post(ajaxUrl, {
			action: 'centinela_cotizador_preview_envio',
			nonce: nonce,
			datos: JSON.stringify(datos),
			formato_adjunto: 'pdf',
			editar_id: $('#centinela-cotizador-editar-id').val() || 0
		})
			.done(function (res) {
				$btn.prop('disabled', false).text(labelPdf);
				if (res.success && res.data) {
					rememberCotizacionPostId(res.data);
				}
				if (res.success && res.data && res.data.adjunto_url) {
					var url = res.data.adjunto_url;
					var nombre = res.data.adjunto_nombre || 'cotizacion.pdf';
					var a = document.createElement('a');
					a.href = url;
					a.setAttribute('download', nombre);
					a.target = '_blank';
					a.rel = 'noopener';
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					$msg.addClass('success').text(i18n.pdf_generado_ok || 'PDF generado.');
				} else if (res.success && res.data) {
					$msg.addClass('error').text(res.data.message || i18n.error_pdf_adjunto || 'Error');
				} else {
					$msg.addClass('error').text((res.data && res.data.message) ? res.data.message : 'Error');
				}
			})
			.fail(function (xhr) {
				$btn.prop('disabled', false).text(labelPdf);
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
					rememberCotizacionPostId(res.data);
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
					rememberCotizacionPostId(res.data || {});
					$msg.addClass('success').text(res.data.message || '');
					setTimeout(function () {
						closeModal('#centinela-cotizador-modal-guardar');
						if (misCotizacionesUrl) {
							window.location.href = misCotizacionesUrl;
						}
					}, 1500);
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
		$('#centinela-cotizador-orden-compra').val(datos.orden_compra || '');

		$tbody.find('tr.producto-fila').remove();
		$filaVacia.show();
		var productos = datos.productos || [];
		productos.forEach(function (p) {
			var precio = parseNum(p.precio);
			var isManual = !!p.manual;
			addFila({
				manual: isManual,
				id: p.id || '',
				referencia: p.referencia || '',
				modelo: p.modelo || '',
				titulo: p.titulo || '',
				precio_lista: precio,
				precio_oferta: precio,
				tiene_oferta: false,
				precio_inicial: precio,
				cantidad_inicial: parseNum(p.cantidad) || 1,
				descuento_inicial: parseNum(p.descuento) || 0
			});
			$tbody.find('tr.producto-fila').last().find('.centinela-cotizador-input-cantidad').trigger('change');
		});

		var cliente = datos.cliente || {};
		$('#centinela-cotizador-cliente-nombre').val(cliente.nombre || '');
		$('#centinela-cotizador-cliente-telefono').val(cliente.telefono || '');
		$('#centinela-cotizador-cliente-nitcc').val(cliente.nit_cc || '');
		$('#centinela-cotizador-cliente-sitio-web').val(cliente.sitio_web || '');
		$('#centinela-cotizador-cliente-direccion').val(cliente.direccion || '');
		$('#centinela-cotizador-cliente-direccion-fisica').val(cliente.direccion_fisica || '');
		$('#centinela-cotizador-cliente-ciudad').val(cliente.ciudad || '');
		$('#centinela-cotizador-cliente-email').val(cliente.email || '');
		$('#centinela-cotizador-cliente-email-adicionales').val(cliente.email_adicionales || '');
		$('#centinela-cotizador-cliente-vigencia').val(cliente.vigencia || '');
		$('#centinela-cotizador-cliente-comentarios').val(cliente.comentarios || '');

		var contacto = datos.contacto || {};
		$('#centinela-cotizador-mi-nombre').val(contacto.nombre || '');
		$('#centinela-cotizador-mi-email').val(contacto.email || '');
		$('#centinela-cotizador-mi-telefono').val(contacto.telefono || '');
		$('#centinela-cotizador-metodo-pago').val(contacto.metodo_pago || '');

		var envio = datos.envio || {};
		var embarcarVal = envio.embarcar_a || '';
		if (!embarcarVal && contacto.embarcar_a) {
			embarcarVal = contacto.embarcar_a === 'entrega_local' ? 'envio_local' : contacto.embarcar_a;
		}
		$('#centinela-cotizador-embarcar-a').val(embarcarVal || '');
		$('#centinela-cotizador-envio-via').val(envio.via || '');
		$('#centinela-cotizador-envio-quien-recibe').val(envio.quien_recibe || '');
		$('#centinela-cotizador-envio-direccion').val(envio.direccion || '');
		$('#centinela-cotizador-envio-ciudad').val(envio.ciudad || '');
		$('#centinela-cotizador-envio-cel').val(envio.cel || '');

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
