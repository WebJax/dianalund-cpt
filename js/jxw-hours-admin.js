(function ($) {
	'use strict';

	$(function () {
		var $table = $('#jxw-hours-exceptions');
		if (!$table.length) {
			return;
		}

		$('#jxw-hours-add-exception').on('click', function (event) {
			event.preventDefault();
			var $tbody = $table.find('tbody');
			var $last = $tbody.find('tr.jxw-hours-exception').last();
			var index = $tbody.find('tr.jxw-hours-exception').length;
			var $clone = $last.clone();
			$clone.find('input, select').each(function () {
				var $field = $(this);
				var name = $field.attr('name');
				if (name) {
					$field.attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
				}
				if ($field.is('select')) {
					$field.val('');
				} else {
					$field.val('');
				}
			});
			$tbody.append($clone);
		});

		$table.on('click', '.jxw-hours-remove-exception', function (event) {
			event.preventDefault();
			var $rows = $table.find('tr.jxw-hours-exception');
			if ($rows.length < 2) {
				$rows.find('input, select').val('');
				return;
			}
			$(this).closest('tr').remove();
		});
	});
})(jQuery);
