/**
 * Formulario Cotización Web – envío por AJAX
 * Centinela Group Theme
 */
(function ($) {
	'use strict';

	function getFormData($form) {
		var data = {};
		$form.find('.centinela-cwf__input, .centinela-cwf__textarea, .centinela-cwf__select').each(function () {
			var $el = $(this);
			var name = $el.attr('name');
			if (!name) return;
			var label = $el.closest('.centinela-cwf__row').find('.centinela-cwf__label').first().text().replace(/\s*\*\s*$/, '').trim();
			if (!label) label = name;
			var val = $el.val();
			if (typeof val === 'string') val = val.trim();
			data[label] = val;
		});
		$form.find('.centinela-cwf__radio-group').each(function () {
			var $group = $(this);
			var label = $group.find('.centinela-cwf__radio-legend').text().replace(/\s*\*\s*$/, '').trim();
			var name = $group.find('.centinela-cwf__radio-input').attr('name');
			if (!name) return;
			if (!label) label = name;
			var $checked = $group.find('.centinela-cwf__radio-input:checked');
			data[label] = $checked.length ? $checked.val() : '';
		});
		return data;
	}

	function showMessage($wrap, type, text) {
		var $msg = $wrap.find('.centinela-cwf__messages');
		$msg.attr('hidden', false).removeClass('centinela-cwf__messages--success centinela-cwf__messages--error').addClass('centinela-cwf__messages--' + type);
		$msg.text(text);
	}

	function hideMessage($wrap) {
		$wrap.find('.centinela-cwf__messages').attr('hidden', true).removeClass('centinela-cwf__messages--success centinela-cwf__messages--error').text('');
	}

	function clearFieldErrors($form) {
		$form.find('.centinela-cwf__field-error').attr('hidden', true).removeAttr('aria-invalid');
		$form.find('.centinela-cwf__input, .centinela-cwf__textarea, .centinela-cwf__select').removeClass('centinela-cwf__input--error');
		$form.find('.centinela-cwf__radio-group').removeClass('centinela-cwf__radio-group--error');
	}

	function showFieldErrors($form) {
		clearFieldErrors($form);
		var invalid = $form[0].querySelectorAll('input:invalid, select:invalid, textarea:invalid');
		for (var i = 0; i < invalid.length; i++) {
			var el = invalid[i];
			var $row = $(el).closest('.centinela-cwf__row');
			if ($row.length) {
				$row.find('.centinela-cwf__field-error').attr('hidden', false).attr('aria-invalid', 'true');
				$(el).addClass('centinela-cwf__input--error');
			}
		}
		var radioGroups = $form.find('.centinela-cwf__radio-group');
		radioGroups.each(function () {
			var $group = $(this);
			var name = $group.find('.centinela-cwf__radio-input').attr('name');
			if (!name) return;
			var checked = $form.find('input[name="' + name + '"]:checked').length;
			if (checked === 0 && $group.find('.centinela-cwf__radio-input').prop('required')) {
				$group.closest('.centinela-cwf__row').find('.centinela-cwf__field-error').attr('hidden', false).attr('aria-invalid', 'true');
				$group.addClass('centinela-cwf__radio-group--error');
			}
		});
	}

	$(document).on('submit', '.centinela-cwf__form', function (e) {
		e.preventDefault();
		var $form = $(this);
		var $wrap = $form.closest('.centinela-cwf');
		var $submit = $form.find('.centinela-cwf__submit');
		var $submitText = $submit.find('.centinela-cwf__submit-text');
		var $submitLoading = $submit.find('.centinela-cwf__submit-loading');

		hideMessage($wrap);
		if (!$form[0].checkValidity()) {
			showFieldErrors($form);
			$form[0].reportValidity();
			return;
		}
		clearFieldErrors($form);

		$submit.prop('disabled', true);
		$submitText.attr('hidden', true);
		$submitLoading.attr('hidden', false);

		var data = getFormData($form);
		var emailsHidden = $form.find('input[name="centinela_cwf_emails"]').val() || '';

		$.ajax({
			url: (typeof centinelaCwf !== 'undefined' && centinelaCwf.ajax_url) ? centinelaCwf.ajax_url : (window.wp && wp.ajax && wp.ajax.settings && wp.ajax.settings.url) || '/wp-admin/admin-ajax.php',
			type: 'POST',
			data: {
				action: 'centinela_cotizaciones_web_form_submit',
				centinela_cwf_nonce: $form.find('input[name="centinela_cwf_nonce"]').val(),
				centinela_cwf_data: JSON.stringify(data),
				centinela_cwf_emails: emailsHidden
			},
			success: function (res) {
				if (res.success && res.data && res.data.message) {
					showMessage($wrap, 'success', res.data.message);
					$form[0].reset();
				} else {
					showMessage($wrap, 'error', (res.data && res.data.message) ? res.data.message : 'Error al enviar.');
				}
			},
			error: function (xhr, status, err) {
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : (err || 'Error de conexión. Intenta de nuevo.');
				showMessage($wrap, 'error', msg);
			},
			complete: function () {
				$submit.prop('disabled', false);
				$submitText.attr('hidden', false);
				$submitLoading.attr('hidden', true);
			}
		});
	});

	$(document).on('input change', '.centinela-cwf__form .centinela-cwf__input, .centinela-cwf__form .centinela-cwf__textarea, .centinela-cwf__form .centinela-cwf__select, .centinela-cwf__form .centinela-cwf__radio-input', function () {
		var $el = $(this);
		var $form = $el.closest('.centinela-cwf__form');
		if (!$form.length) return;
		var $row = $el.closest('.centinela-cwf__row');
		if ($row.length) {
			var valid = true;
			if (this.setCustomValidity) {
				valid = this.validity.valid;
			} else if ($el.is('[type="radio"]')) {
				var name = $el.attr('name');
				valid = $form.find('input[name="' + name + '"]:checked').length > 0;
			}
			if (valid) {
				$row.find('.centinela-cwf__field-error').attr('hidden', true);
				$row.find('.centinela-cwf__input, .centinela-cwf__textarea, .centinela-cwf__select').removeClass('centinela-cwf__input--error');
				$row.find('.centinela-cwf__radio-group').removeClass('centinela-cwf__radio-group--error');
			}
		}
	});
})(jQuery);
