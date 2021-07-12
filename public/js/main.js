$(function () {
	$('form#search-form')
		.find('input[name="search_by"]')
		.on('change', function () {
			$('#fieldset-search_by_string').toggle($(this).val() === 'string');
			$('#fieldset-search_by_location').toggle($(this).val() === 'location');
		})
		.end()
		.on('submit', function () {
			const $buttons = $(this).find('button'),
				$fieldSet = $(this).find('.fieldset:visible'),
				$results = $('div#results');

			$buttons.prop('disabled', true);
			$results.empty();

			$.ajax({
				url: $fieldSet.data('url'),
				data: $fieldSet.find('input, textarea, select').serialize(),
				dataType: 'json'
			}).then(
				function (data) {
					let $list = $('<ul>');
					$results.append($list);
					data.forEach(function (item) {
						if (!!item.distance) {
							$list.append(`<li>${item.postcode} (${item.distance} km)</li>`);
						} else {
							$list.append(`<li>${item.postcode}</li>`);
						}
					});
					$buttons.prop('disabled', false);
				},
				function (jqXHR, textStatus, errorThrown) {
					$results.html('<h2>' + errorThrown + '</h2>');
					$buttons.prop('disabled', false);
				}
			);

			return false;
		})
		.on('reset', function () {
			$('#fieldset-search_by_string').show();
			$('#fieldset-search_by_location').hide();
			$('div#results').empty();
		});
});
