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

	var attributeSamples = {
		'{user_name}': 'Ada Lovelace',
		'{user_email}': 'ada@example.com',
		'{user_login}': 'ada',
		'{user_id}': '42',
		'{course_title}': 'Leadership Essentials',
		'{course_id}': '42',
		'{completion_date}': '21 Oct 2026',
		'{completion_date_iso}': '2026-10-21',
		'{score}': '92',
		'{site_name}': 'Acme Academy',
		'{site_url}': 'https://acme.test'
	};

	var primaryTokens = {
		'{user_name}': 'Learner name',
		'{user_email}': 'Email',
		'{course_title}': 'Course title',
		'{completion_date}': 'Date',
		'{score}': 'Score'
	};

	function previewValue(value) {
		var output = value || '';

		Object.keys(attributeSamples).forEach(function (token) {
			output = output.split(token).join(attributeSamples[token]);
		});

		output = output.replace(/\{(user_meta|course_meta|context):([^}]+)\}/g, '[$2]');

		return output.trim() || '—';
	}

	function syncRawAttributes() {
		var raw = document.getElementById('verifycate-attributes');
		var lines = [];

		if (!raw) {
			return;
		}

		document.querySelectorAll('.verifycate-attribute-row').forEach(function (row) {
			var input = row.querySelector('[data-attribute-input]');
			var key = row.getAttribute('data-attribute-key');

			if (input && key && input.value.trim() !== '') {
				lines.push(key + ' = ' + input.value.trim());
			}
		});

		raw.value = lines.join('\n');
	}

	function updateRowPreview(row) {
		var input = row.querySelector('[data-attribute-input]');
		var preview = row.querySelector('[data-preview]');

		if (input && preview) {
			preview.textContent = previewValue(input.value);
		}
	}

	function insertAtCursor(input, text) {
		var start = input.selectionStart || 0;
		var end = input.selectionEnd || 0;
		var before = input.value.slice(0, start);
		var after = input.value.slice(end);
		var prefix = before !== '' && !/\s$/.test(before) ? ' ' : '';
		var suffix = after !== '' && !/^\s/.test(after) ? ' ' : '';
		var inserted = prefix + text + suffix;

		input.value = before + inserted + after;
		input.focus();

		if (input.setSelectionRange) {
			input.setSelectionRange(start + inserted.length, start + inserted.length);
		}
	}

	function enhanceAttributeRow(row) {
		if (row.getAttribute('data-enhanced') === '1') {
			return;
		}

		row.setAttribute('data-enhanced', '1');

		var input = row.querySelector('[data-attribute-input]');
		var more = row.querySelector('[data-token-more]');
		var clear = row.querySelector('[data-clear-attribute]');

		if (!input) {
			return;
		}

		input.addEventListener('input', function () {
			updateRowPreview(row);
			syncRawAttributes();
		});

		row.querySelectorAll('[data-token]').forEach(function (button) {
			button.addEventListener('click', function () {
				insertAtCursor(input, button.getAttribute('data-token'));
				updateRowPreview(row);
				syncRawAttributes();
			});
		});

		if (more) {
			more.addEventListener('change', function () {
				if (!more.value) {
					return;
				}

				insertAtCursor(input, more.value);
				more.value = '';
				updateRowPreview(row);
				syncRawAttributes();
			});
		}

		if (clear) {
			clear.addEventListener('click', function () {
				input.value = '';
				input.focus();
				updateRowPreview(row);
				syncRawAttributes();
			});
		}

		updateRowPreview(row);
	}

	function createAttributeRow(attribute, value) {
		var row = document.createElement('div');
		var head = document.createElement('div');
		var label = document.createElement('label');
		var code = document.createElement('code');
		var clear = document.createElement('button');
		var input = document.createElement('input');
		var tokens = document.createElement('div');
		var tokensLabel = document.createElement('span');
		var more = document.createElement('select');
		var empty = document.createElement('option');
		var preview = document.createElement('p');
		var previewValueNode = document.createElement('span');
		var id = 'verifycate-attribute-' + String(attribute.key).replace(/[^a-zA-Z0-9_-]/g, '-');

		row.className = 'verifycate-attribute-row';
		row.setAttribute('data-attribute-key', attribute.key);

		head.className = 'verifycate-attribute-row__head';
		label.textContent = attribute.label || attribute.key;
		label.setAttribute('for', id);
		code.textContent = attribute.key;
		clear.type = 'button';
		clear.className = 'button-link verifycate-attribute-row__clear';
		clear.setAttribute('data-clear-attribute', '');
		clear.textContent = data.clear || 'Clear';
		head.appendChild(label);
		head.appendChild(code);
		head.appendChild(clear);

		input.type = 'text';
		input.id = id;
		input.className = 'large-text verifycate-attribute-row__input';
		input.value = value || '';
		input.placeholder = 'e.g. {course_title}';
		input.setAttribute('data-attribute-input', '');

		tokens.className = 'verifycate-attribute-row__tokens';
		tokensLabel.className = 'verifycate-attribute-row__tokens-label';
		tokensLabel.textContent = data.insertTokenLabel || 'Insert token:';
		tokens.appendChild(tokensLabel);

		Object.keys(primaryTokens).forEach(function (token) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'verifycate-token';
			button.setAttribute('data-token', token);
			button.title = token;
			button.textContent = primaryTokens[token];
			tokens.appendChild(button);
		});

		more.className = 'verifycate-token-more';
		more.setAttribute('data-token-more', '');
		more.setAttribute('aria-label', data.moreTokens || 'More tokens');
		empty.value = '';
		empty.textContent = data.moreTokensPlaceholder || 'More tokens…';
		more.appendChild(empty);

		(data.tokens || []).forEach(function (token) {
			if (primaryTokens[token.token] || token.token.indexOf(':') !== -1) {
				return;
			}

			var option = document.createElement('option');
			option.value = token.token;
			option.textContent = token.label;
			option.title = token.token;
			more.appendChild(option);
		});

		tokens.appendChild(more);

		preview.className = 'description verifycate-attribute-row__preview';
		preview.appendChild(document.createTextNode((data.preview || 'Preview:') + ' '));
		previewValueNode.setAttribute('data-preview', '');
		previewValueNode.textContent = '—';
		preview.appendChild(previewValueNode);

		row.appendChild(head);
		row.appendChild(input);
		row.appendChild(tokens);
		row.appendChild(preview);
		enhanceAttributeRow(row);

		return row;
	}

	function renderAttributeRows(attributes, values) {
		var target = document.querySelector('[data-attribute-rows]');

		if (!target) {
			return;
		}

		target.textContent = '';

		if (!attributes.length) {
			var empty = document.createElement('p');
			empty.className = 'description';
			empty.textContent = data.noAttributes || 'This certificate has no declared attributes.';
			target.appendChild(empty);
			syncRawAttributes();
			return;
		}

		attributes.forEach(function (attribute) {
			target.appendChild(createAttributeRow(attribute, values[attribute.key] || ''));
		});

		syncRawAttributes();
	}

	function collectAttributeValues() {
		var values = {};

		document.querySelectorAll('.verifycate-attribute-row').forEach(function (row) {
			var key = row.getAttribute('data-attribute-key');
			var input = row.querySelector('[data-attribute-input]');

			if (key && input) {
				values[key] = input.value;
			}
		});

		return values;
	}

	function loadAttributeRows() {
		var select = document.getElementById('verifycate-certificate');
		var target = document.querySelector('[data-attribute-rows]');

		if (!select || !target || !data.ajaxUrl) {
			return;
		}

		document.querySelectorAll('.verifycate-attribute-row').forEach(enhanceAttributeRow);
		syncRawAttributes();

		var raw = document.getElementById('verifycate-attributes');
		if (raw) {
			raw.addEventListener('input', function () {
				var values = {};
				raw.value.split(/\r\n|\r|\n/).forEach(function (line) {
					var separator = line.indexOf('=');

					if (separator > 0) {
						values[line.slice(0, separator).trim()] = line.slice(separator + 1).trim();
					}
				});

				document.querySelectorAll('.verifycate-attribute-row').forEach(function (row) {
					var input = row.querySelector('[data-attribute-input]');
					var key = row.getAttribute('data-attribute-key');

					if (input && key) {
						input.value = values[key] || '';
						updateRowPreview(row);
					}
				});
			});
		}

		select.addEventListener('change', function () {
			var current = collectAttributeValues();

			if (!select.value) {
				target.textContent = data.chooseCertificate || 'Choose a certificate to load its attributes.';
				syncRawAttributes();
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

					renderAttributeRows(payload.data.attributes || [], current);
				})
				.catch(function () {
					target.textContent = '';
				});
		});
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

		loadAttributeRows();

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
