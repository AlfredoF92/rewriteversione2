/**
 * Anchor scroll per [llm_riviste_indice].
 * I pulsanti scrollano alla sezione corrispondente e si evidenziano.
 */
(function () {
	'use strict';

	function setActive(root, anchorId) {
		var links = root.querySelectorAll('[data-llm-mag-anchor]');
		for (var i = 0; i < links.length; i++) {
			var href = links[i].getAttribute('href') || '';
			var active = href === '#' + anchorId;
			links[i].classList.toggle('is-active', active);
		}
	}

	function init(root) {
		if (root.getAttribute('data-llm-mag-ready') === '1') {
			return;
		}
		root.setAttribute('data-llm-mag-ready', '1');

		root.addEventListener('click', function (event) {
			var link = event.target.closest('[data-llm-mag-anchor]');
			if (!link || !root.contains(link)) {
				return;
			}
			var href = link.getAttribute('href') || '';
			var anchorId = href.replace(/^#/, '');
			if (!anchorId) {
				return;
			}
			var target = document.getElementById(anchorId);
			if (target) {
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
			setActive(root, anchorId);
		});

		// Marca come attivo il pulsante della sezione visibile durante lo scroll.
		var sections = root.querySelectorAll('.llm-mag-index__section[id]');
		if (!sections.length || typeof IntersectionObserver === 'undefined') {
			return;
		}
		var observer = new IntersectionObserver(function (entries) {
			for (var i = 0; i < entries.length; i++) {
				if (entries[i].isIntersecting) {
					setActive(root, entries[i].target.id);
					break;
				}
			}
		}, { rootMargin: '-30% 0px -60% 0px', threshold: 0 });

		for (var j = 0; j < sections.length; j++) {
			observer.observe(sections[j]);
		}
	}

	function boot() {
		var roots = document.querySelectorAll('[data-llm-mag-index]');
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
