/*
 * llm-idiom-admin.js — elenco compatto espressioni.
 */
(function () {
	'use strict';

	function el(id) {
		return document.getElementById(id);
	}

	function root() {
		return el('llm-idiom-items');
	}

	function items() {
		var r = root();
		return r ? Array.prototype.slice.call(r.querySelectorAll('.llm-idiom-admin__item')) : [];
	}

	function reindex() {
		items().forEach(function (block, index) {
			block.setAttribute('data-index', String(index));
			block.querySelectorAll('[name]').forEach(function (field) {
				var name = field.getAttribute('name') || '';
				field.setAttribute('name', name.replace(/llm_idiom_items\[[^\]]+\]/, 'llm_idiom_items[' + index + ']'));
			});
			var preview = block.querySelector('.llm-idiom-admin__preview');
			var phrase = block.querySelector('.llm-idiom-phrase');
			if (preview && phrase) {
				var t = (phrase.value || '').trim();
				preview.textContent = t || '(vuota)';
				preview.setAttribute('title', t || '(vuota)');
			}
		});
		var countEl = el('llm-idiom-count');
		if (countEl) {
			var n = items().length;
			countEl.textContent = n === 1 ? '1 espressione' : n + ' espressioni';
		}
	}

	function regroup() {
		var r = root();
		if (!r) {
			return;
		}
		var all = items();
		if (!all.length) {
			r.innerHTML = '<p class="llm-idiom-admin__empty">Nessuna espressione. Aggiungine una.</p>';
			return;
		}
		var map = {};
		all.forEach(function (block) {
			var catInput = block.querySelector('.llm-idiom-category');
			var cat = (catInput && catInput.value.trim()) || 'Senza categoria';
			if (!map[cat]) {
				map[cat] = [];
			}
			map[cat].push(block);
		});
		r.innerHTML = '';
		Object.keys(map)
			.sort(function (a, b) {
				if (a === 'Senza categoria') return 1;
				if (b === 'Senza categoria') return -1;
				return a.localeCompare(b);
			})
			.forEach(function (cat) {
				var section = document.createElement('section');
				section.className = 'llm-idiom-admin__cat';
				var title = document.createElement('h4');
				title.className = 'llm-idiom-admin__cat-title';
				title.innerHTML =
					cat + ' <span class="llm-idiom-admin__cat-count">(' + map[cat].length + ')</span>';
				var list = document.createElement('div');
				list.className = 'llm-idiom-admin__cat-list';
				map[cat].forEach(function (block) {
					list.appendChild(block);
				});
				section.appendChild(title);
				section.appendChild(list);
				r.appendChild(section);
			});
		reindex();
	}

	function addItem() {
		var tpl = el('llm-idiom-item-template');
		var r = root();
		if (!tpl || !r) {
			return;
		}
		var empty = r.querySelector('.llm-idiom-admin__empty');
		if (empty) {
			empty.remove();
		}
		var wrap = document.createElement('div');
		wrap.innerHTML = tpl.innerHTML;
		var block = wrap.querySelector('.llm-idiom-admin__item');
		if (!block) {
			return;
		}
		block.classList.add('is-open');
		var idField = block.querySelector('.llm-idiom-id');
		if (idField) {
			idField.value = '';
		}
		var lists = r.querySelectorAll('.llm-idiom-admin__cat-list');
		if (lists.length) {
			lists[lists.length - 1].appendChild(block);
		} else {
			r.appendChild(block);
		}
		reindex();
	}

	document.addEventListener('DOMContentLoaded', function () {
		var addBtn = el('llm-idiom-add');
		if (addBtn) {
			addBtn.addEventListener('click', function (e) {
				e.preventDefault();
				addItem();
			});
		}

		document.addEventListener('click', function (e) {
			var t = e.target;
			if (!t || !t.closest) {
				return;
			}
			var block = t.closest('.llm-idiom-admin__item');
			if (!block) {
				return;
			}
			if (t.classList.contains('llm-idiom-toggle') || t.closest('.llm-idiom-toggle')) {
				e.preventDefault();
				block.classList.toggle('is-open');
				var btn = block.querySelector('.llm-idiom-toggle');
				if (btn) {
					btn.textContent = block.classList.contains('is-open') ? 'Chiudi' : 'Modifica';
				}
			}
			if (t.classList.contains('llm-idiom-remove') || t.closest('.llm-idiom-remove')) {
				e.preventDefault();
				if (window.confirm('Eliminare questa espressione?')) {
					block.remove();
					regroup();
				}
			}
		});

		document.addEventListener('change', function (e) {
			var t = e.target;
			if (t && t.classList && t.classList.contains('llm-idiom-category')) {
				regroup();
			}
		});

		document.addEventListener('input', function (e) {
			var t = e.target;
			if (t && t.classList && t.classList.contains('llm-idiom-phrase')) {
				reindex();
			}
		});
	});
})();
