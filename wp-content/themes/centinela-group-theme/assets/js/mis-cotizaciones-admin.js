/**
 * Mis Cotizaciones: búsqueda en vivo por nombre / NIT (AJAX).
 *
 * @package Centinela_Group_Theme
 */
(function ($) {
	'use strict';

	var cfg = window.centinelaMisCotizaciones || {};
	var ajaxUrl = cfg.ajax_url || '';
	var nonce = cfg.nonce || '';
	var i18n = cfg.i18n || {};
	var timer = null;
	var debounceMs = 300;
	var currentQ = '';
	var currentPaged = 1;

	function fetchList(q, paged) {
		if (!ajaxUrl || !nonce) {
			return;
		}
		currentQ = q != null ? String(q) : '';
		currentPaged = paged || 1;
		var $tb = $('#centinela-mis-cotizaciones-tbody');
		if (!$tb.length) {
			return;
		}
		$tb.addClass('is-loading');
		var $st = $('#centinela-mis-cotizaciones-buscar-estado');
		if ($st.length) {
			$st.text(i18n.loading || '');
		}

		$.post(ajaxUrl, {
			action: 'centinela_mis_cotizaciones_list',
			nonce: nonce,
			q: currentQ,
			paged: currentPaged
		})
			.done(function (res) {
				if (!res.success || !res.data) {
					renderRows([]);
					renderPagination(0, 0, 1, currentQ);
					if ($st.length) {
						$st.text('');
					}
					return;
				}
				var d = res.data;
				var found = parseInt(d.found_posts, 10) || 0;
				var maxPg = parseInt(d.max_num_pages, 10) || 0;
				var pg = parseInt(d.paged, 10) || 1;
				renderRows(d.rows || []);
				renderPagination(found, maxPg, pg, currentQ);
				if ($st.length) {
					$st.text(found ? found + ' ' + (i18n.cotizacion || '') : '');
				}
			})
			.fail(function () {
				renderRows([]);
				if ($st.length) {
					$st.text('');
				}
			})
			.always(function () {
				$tb.removeClass('is-loading');
			});
	}

	function renderRows(rows) {
		var $tb = $('#centinela-mis-cotizaciones-tbody');
		$tb.empty();
		if (!rows || !rows.length) {
			$tb.append(
				$('<tr class="centinela-mis-cot-no-results"></tr>').append(
					$('<td colspan="7"></td>').text(i18n.no_results || '')
				)
			);
			return;
		}
		rows.forEach(function (r) {
			var $tr = $('<tr></tr>');
			$tr.append($('<td></td>').append($('<strong></strong>').text(r.title || '')));
			$tr.append($('<td></td>').text(r.cliente || ''));
			$tr.append($('<td></td>').text(r.nit || ''));
			$tr.append($('<td></td>').text(r.moneda || ''));
			$tr.append($('<td></td>').text(r.total_fmt || ''));
			$tr.append($('<td></td>').text(r.fecha || ''));
			var $td = $('<td></td>');
			$td.append(
				$('<a class="button button-small"></a>')
					.attr('href', r.editar_url || '#')
					.text(i18n.editar || 'Editar')
			);
			$td.append(document.createTextNode(' '));
			var $dup = $('<a class="button button-small"></a>')
				.attr('href', r.duplicar_url || '#')
				.text(i18n.duplicar || 'Duplicar');
			$dup.on('click', function (e) {
				if (r.confirm_duplicar && !window.confirm(r.confirm_duplicar)) {
					e.preventDefault();
				}
			});
			$td.append($dup);
			if (r.can_delete && r.eliminar_url) {
				$td.append(document.createTextNode(' '));
				var $del = $('<a class="button button-small button-link-delete"></a>')
					.attr('href', r.eliminar_url)
					.text(i18n.eliminar || 'Eliminar');
				$del.on('click', function (e) {
					if (r.confirm_eliminar && !window.confirm(r.confirm_eliminar)) {
						e.preventDefault();
					}
				});
				$td.append($del);
			}
			$tr.append($td);
			$tb.append($tr);
		});
	}

	function renderPagination(found, maxPages, paged, q) {
		var $w = $('#centinela-mis-cotizaciones-pagination');
		if (!$w.length) {
			return;
		}
		if (!maxPages || maxPages <= 1) {
			$w.empty().hide();
			return;
		}
		$w.show().empty();
		var $inner = $('<div class="tablenav-pages"></div>');
		if (paged > 1) {
			$inner.append(
				$('<button type="button" class="button"></button>')
					.html('&laquo;')
					.on('click', function () {
						fetchList(q, paged - 1);
					})
			);
			$inner.append(document.createTextNode(' '));
		}
		$inner.append(
			$('<span class="displaying-num"></span>').text(
				(i18n.mostrando || '') + ' ' + found + ' ' + (i18n.cotizacion || '')
			)
		);
		$inner.append(
			$('<span class="paging-input" style="margin:0 8px;"></span>').text(String(paged) + ' / ' + String(maxPages))
		);
		if (paged < maxPages) {
			$inner.append(document.createTextNode(' '));
			$inner.append(
				$('<button type="button" class="button"></button>')
					.html('&raquo;')
					.on('click', function () {
						fetchList(q, paged + 1);
					})
			);
		}
		$w.append($inner);
	}

	$(function () {
		var $in = $('#centinela-mis-cotizaciones-buscar');
		var $clr = $('#centinela-mis-cotizaciones-limpiar');
		if (!$in.length) {
			return;
		}

		$in.on('input', function () {
			clearTimeout(timer);
			var v = $.trim($in.val());
			if ($clr.length) {
				if (v) {
					$clr.removeAttr('hidden');
				} else {
					$clr.attr('hidden', 'hidden');
				}
			}
			timer = setTimeout(function () {
				fetchList(v, 1);
			}, debounceMs);
		});

		if ($clr.length) {
			$clr.on('click', function () {
				$in.val('');
				$clr.attr('hidden', 'hidden');
				fetchList('', 1);
				$in.trigger('focus');
			});
		}
	});
})(jQuery);
