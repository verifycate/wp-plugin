(function () {
	'use strict';

	var data = window.verifycateAdmin || {};

	function toggleCustomFields() {
		var source = document.getElementById('verifycate-source');
		var custom = document.querySelector('.verifycate-custom-fields');

		if (!source || !custom) {
			return;
		}

		custom.hidden = source.value.indexOf('custom:') !== 0;
	}

	function loadAttributeKeys() {
		var select = document.getElementById('verifycate-certificate');
		var target = document.querySelector('.verifycate-attribute-keys');

		if (!select || !target || !data.ajaxUrl) {
			return;
		}

		select.addEventListener('change', function () {
			if (!select.value) {
				target.textContent = '';
				return;
			}

			target.textContent = data.loading || 'Loading…';

			var params = new URLSearchParams({
				action: 'verifycate_certificate',
				nonce: data.nonce,
				id: select.value
			});

			fetch(data.ajaxUrl + '?' + params.toString(), { credentials: 'same-origin' })
				.then(function (response) {
					return response.json();
				})
				.then(function (payload) {
					if (!payload || !payload.success) {
						target.textContent = '';
						return;
					}

					var attributes = payload.data.attributes || [];
					target.textContent = '';

					if (!attributes.length) {
						target.textContent = data.noAttributes || '';
						return;
					}

					var label = document.createElement('p');
					label.textContent = data.attributes || 'Available attribute keys:';

					var list = document.createElement('ul');
					attributes.forEach(function (attribute) {
						var item = document.createElement('li');
						var code = document.createElement('code');
						code.textContent = attribute.key;
						item.appendChild(code);

						if (attribute.label && attribute.label !== attribute.key) {
							item.appendChild(document.createTextNode(' ' + attribute.label));
						}

						list.appendChild(item);
					});

					target.appendChild(label);
					target.appendChild(list);
				})
				.catch(function () {
					target.textContent = '';
				});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var source = document.getElementById('verifycate-source');

		if (source) {
			source.addEventListener('change', toggleCustomFields);
			toggleCustomFields();
		}

		loadAttributeKeys();

		document.querySelectorAll('.verifycate-delete-mapping').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				if (!window.confirm(data.confirmDelete || 'Delete this mapping?')) {
					event.preventDefault();
				}
			});
		});

		document.querySelectorAll('.verifycate-copy').forEach(function (button) {
			button.addEventListener('click', function () {
				var value = button.getAttribute('data-copy') || '';

				if (!value || !navigator.clipboard) {
					return;
				}

				navigator.clipboard.writeText(value).then(function () {
					var original = button.textContent;
					button.textContent = data.copied || 'Copied';
					window.setTimeout(function () {
						button.textContent = original;
					}, 2000);
				});
			});
		});
	});
})();
