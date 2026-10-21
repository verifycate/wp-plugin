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

		if (select.value) {
			select.dispatchEvent(new Event('change', { bubbles: true }));
		}
	}

	function enhanceComboboxes() {
		document.querySelectorAll('[data-combobox]').forEach(function (wrapper) {
			var select = wrapper.querySelector('select');
			var control = wrapper.querySelector('.verifycate-combobox__control');
			var input = wrapper.querySelector('.verifycate-combobox__input');
			var toggle = wrapper.querySelector('.verifycate-combobox__toggle');
			var list = wrapper.querySelector('.verifycate-combobox__list');

			if (!select || !control || !input || !list) {
				return;
			}

			var items = [];
			var clean = function (value) {
				return (value || '').replace(/\s+/g, ' ').trim();
			};
			Array.prototype.forEach.call(select.children, function (child) {
				if (child.tagName === 'OPTGROUP') {
					Array.prototype.forEach.call(child.children, function (option) {
						if (option.value) {
							items.push({ value: option.value, label: clean(option.textContent), group: clean(child.label) });
						}
					});
				} else if (child.tagName === 'OPTION' && child.value) {
					items.push({ value: child.value, label: clean(child.textContent), group: '' });
				}
			});

			var filtered = items.slice();
			var activeIndex = -1;

			select.required = false;
			wrapper.classList.add('is-enhanced');
			control.hidden = false;
			input.placeholder = select.getAttribute('data-search-placeholder') || 'Search…';
			input.setAttribute('aria-controls', list.id);

			function itemFor(value) {
				return items.find(function (item) {
					return item.value === value;
				});
			}

			function syncInput() {
				var item = itemFor(select.value);
				input.value = item ? item.label : '';
			}

			function render() {
				list.textContent = '';

				if (!filtered.length) {
					var empty = document.createElement('li');
					empty.className = 'verifycate-combobox__empty';
					empty.textContent = select.getAttribute('data-empty') || 'No matches';
					list.appendChild(empty);
					return;
				}

				filtered.forEach(function (item, index) {
					var option = document.createElement('li');
					option.className = 'verifycate-combobox__option';
					option.id = select.id + '-option-' + index;
					option.setAttribute('role', 'option');
					option.setAttribute('data-value', item.value);
					option.textContent = item.group ? item.group + ' — ' + item.label : item.label;

					if (index === activeIndex) {
						option.classList.add('is-active');
						option.setAttribute('aria-selected', 'true');
					}

					list.appendChild(option);
				});

				if (activeIndex >= 0) {
					input.setAttribute('aria-activedescendant', select.id + '-option-' + activeIndex);
					var active = list.children[activeIndex];
					if (active && active.scrollIntoView) {
						active.scrollIntoView({ block: 'nearest' });
					}
				} else {
					input.removeAttribute('aria-activedescendant');
				}
			}

			function open() {
				if (!list.hidden) {
					return;
				}
				list.hidden = false;
				input.setAttribute('aria-expanded', 'true');
				filter('');
			}

			function close() {
				list.hidden = true;
				input.setAttribute('aria-expanded', 'false');
				input.removeAttribute('aria-activedescendant');
			}

			function filter(query) {
				var needle = query.trim().toLowerCase();
				filtered = items.filter(function (item) {
					return !needle || (item.label + ' ' + item.group).toLowerCase().indexOf(needle) !== -1;
				});
				activeIndex = filtered.length ? 0 : -1;
				render();
			}

			function choose(index) {
				if (index < 0 || index >= filtered.length) {
					return;
				}

				var item = filtered[index];
				select.value = item.value;
				select.dispatchEvent(new Event('change', { bubbles: true }));
				syncInput();
				close();
				input.focus();
			}

			input.addEventListener('focus', function () {
				open();
				input.select();
			});

			input.addEventListener('input', function () {
				open();
				filter(input.value);
			});

			input.addEventListener('keydown', function (event) {
				if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
					event.preventDefault();
					open();

					if (!filtered.length) {
						return;
					}

					if (event.key === 'ArrowDown') {
						activeIndex = Math.min(filtered.length - 1, activeIndex + 1);
					} else {
						activeIndex = Math.max(0, activeIndex - 1);
					}

					render();
					return;
				}

				if (event.key === 'Enter') {
					if (!list.hidden) {
						event.preventDefault();
						choose(activeIndex);
					}
					return;
				}

				if (event.key === 'Escape') {
					event.preventDefault();
					close();
					syncInput();
					return;
				}

				if (event.key === 'Tab') {
					close();
				}
			});

			input.addEventListener('blur', function () {
				window.setTimeout(function () {
					if (list.hidden) {
						syncInput();
					}
				}, 150);
			});

			toggle.addEventListener('click', function () {
				if (list.hidden) {
					input.focus();
					open();
				} else {
					close();
				}
			});

			list.addEventListener('mousedown', function (event) {
				var option = event.target.closest('.verifycate-combobox__option');

				if (!option) {
					return;
				}

				event.preventDefault();
				var index = filtered.findIndex(function (item) {
					return item.value === option.getAttribute('data-value');
				});
				choose(index);
			});

			document.addEventListener('click', function (event) {
				if (!wrapper.contains(event.target)) {
					close();
					syncInput();
				}
			});

			select.addEventListener('change', syncInput);
			syncInput();
		});

		document.querySelectorAll('form').forEach(function (form) {
			var requiredComboboxes = form.querySelectorAll('[data-combobox] select[data-required]');

			if (!requiredComboboxes.length) {
				return;
			}

			form.addEventListener('submit', function (event) {
				var invalid = Array.prototype.find.call(requiredComboboxes, function (select) {
					return !select.value;
				});

				if (invalid) {
					event.preventDefault();
					var input = invalid.closest('[data-combobox]').querySelector('.verifycate-combobox__input');
					if (input) {
						input.focus();
						input.setAttribute('aria-invalid', 'true');
					}
				}
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		enhanceComboboxes();

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
