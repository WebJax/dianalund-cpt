(function ($) {
	'use strict';

	function labels() {
		return window.jxwHoursAdmin || {
			alwaysLabel: 'Alle uger',
			oddLabel: 'Ulige uger',
			evenLabel: 'Lige uger',
			deleteWeek: 'Slet uge'
		};
	}

	function reindexWeek($week, index) {
		$week.find('[name]').each(function () {
			var name = $(this).attr('name');
			if (name) {
				$(this).attr('name', name.replace(/jxw_hours_weeks\[\d+\]/, 'jxw_hours_weeks[' + index + ']'));
			}
		});
	}

	function setApplies($week, applies, title) {
		$week.attr('data-applies', applies);
		$week.find('.jxw-hours-week-applies').val(applies);
		$week.find('.jxw-hours-week-title').text(title);
	}

	function setParity(enabled) {
		var $list = $('#jxw-hours-weeks');
		$list.attr('data-parity', enabled ? '1' : '0');
		$('#jxw-hours-enable-parity').prop('hidden', enabled);
	}

	function weekdayLabel(key) {
		var map = {
			monday: 'Man',
			tuesday: 'Tir',
			wednesday: 'Ons',
			thursday: 'Tor',
			friday: 'Fre',
			saturday: 'Lør',
			sunday: 'Søn'
		};
		return map[key] || key;
	}

	function dayText($row) {
		var status = $row.find('select').val();
		var from = $row.find('input[type="time"]').eq(0).val();
		var to = $row.find('input[type="time"]').eq(1).val();
		if (status === 'closed') {
			return 'Lukket';
		}
		if (status === 'open' && from && to) {
			return from + '–' + to;
		}
		return '–';
	}

	function refreshSummary($week) {
		var keys = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
		var slots = [];
		$week.find('.jxw-hours-days tbody tr').each(function (index) {
			slots.push({
				key: keys[index],
				text: dayText($(this))
			});
		});
		var groups = [];
		slots.forEach(function (slot) {
			var last = groups[groups.length - 1];
			if (last && last.text === slot.text) {
				last.end = slot.key;
				return;
			}
			groups.push({ start: slot.key, end: slot.key, text: slot.text });
		});
		var parts = groups.map(function (group) {
			var label = weekdayLabel(group.start);
			if (group.start !== group.end) {
				label += '–' + weekdayLabel(group.end);
			}
			return label + ' ' + group.text;
		});
		$week.find('.jxw-hours-week-summary').text(parts.join(', '));
	}

	$(function () {
		var $exceptions = $('#jxw-hours-exceptions');
		if ($exceptions.length) {
			$('#jxw-hours-add-exception').on('click', function (event) {
				event.preventDefault();
				var $tbody = $exceptions.find('tbody');
				var $last = $tbody.find('tr.jxw-hours-exception').last();
				var index = $tbody.find('tr.jxw-hours-exception').length;
				var $clone = $last.clone();
				$clone.find('input, select').each(function () {
					var $field = $(this);
					var name = $field.attr('name');
					if (name) {
						$field.attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
					}
					$field.val('');
				});
				$tbody.append($clone);
			});

			$exceptions.on('click', '.jxw-hours-remove-exception', function (event) {
				event.preventDefault();
				var $rows = $exceptions.find('tr.jxw-hours-exception');
				if ($rows.length < 2) {
					$rows.find('input, select').val('');
					return;
				}
				$(this).closest('tr').remove();
			});
		}

		var $weeks = $('#jxw-hours-weeks');
		if (!$weeks.length) {
			return;
		}

		$weeks.on('change', 'select, input[type="time"]', function () {
			refreshSummary($(this).closest('.jxw-hours-week'));
		});

		$('#jxw-hours-enable-parity').on('click', function (event) {
			event.preventDefault();
			var text = labels();
			var $source = $weeks.find('.jxw-hours-week').first();
			if (!$source.length) {
				return;
			}
			var $odd = $source.clone();
			var $even = $source.clone();
			$weeks.empty().append($odd).append($even);
			setApplies($odd, 'odd', text.oddLabel);
			setApplies($even, 'even', text.evenLabel);
			reindexWeek($odd, 0);
			reindexWeek($even, 1);
			$odd.prop('open', false);
			$even.prop('open', true);
			setParity(true);
		});

		$weeks.on('click', '.jxw-hours-delete-week', function (event) {
			event.preventDefault();
			if ($weeks.attr('data-parity') !== '1') {
				return;
			}
			var text = labels();
			var $keep = $(this).closest('.jxw-hours-week');
			$weeks.children().not($keep).remove();
			setApplies($keep, 'always', text.alwaysLabel);
			reindexWeek($keep, 0);
			$keep.prop('open', false);
			setParity(false);
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
