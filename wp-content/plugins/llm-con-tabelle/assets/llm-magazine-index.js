/**
 * Accordion lingua per [llm_riviste_indice]: una sola sezione aperta.
 */
(function () {
	'use strict';

	function setOpen(root, item) {
		var items = root.querySelectorAll('.llm-mag-index__acc-item');
		for (var i = 0; i < items.length; i++) {
			var other = items[i];
			var open = other === item;
			other.classList.toggle('is-open', open);
			var toggle = other.querySelector('.llm-mag-index__acc-toggle');
			var panel = other.querySelector('.llm-mag-index__acc-panel');
			if (toggle) {
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
			if (panel) {
				if (open) {
					panel.removeAttribute('hidden');
				} else {
					panel.setAttribute('hidden', '');
				}
			}
		}
	}

	function init(root) {
		if (root.getAttribute('data-llm-mag-ready') === '1') {
			return;
		}
		root.setAttribute('data-llm-mag-ready', '1');
		root.addEventListener('click', function (event) {
			var btn = event.target.closest('.llm-mag-index__acc-toggle');
			if (!btn || !root.contains(btn)) {
				return;
			}
			var item = btn.closest('.llm-mag-index__acc-item');
			if (!item || item.classList.contains('is-open')) {
				return;
			}
			setOpen(root, item);
		});
	}

	function boot() {
		var roots = document.querySelectorAll('[data-llm-mag-accordion]');
		for (var i = 0; i < roots.length; i++) {
			init(roots[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
