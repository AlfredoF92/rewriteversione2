/**
 * Filtri Area Personale: aggiorna il Loop Grid senza ricaricare la pagina (stesso schema di Elementor ajax-pagination).
 */
(function () {
	'use strict';

	function findLoopWidget(root, explicitDataId) {
		if (explicitDataId) {
			var byId =
				document.querySelector('.elementor-widget-loop-grid[data-id="' + explicitDataId + '"]') ||
				document.querySelector('.elementor-widget.elementor-widget-loop-grid[data-id="' + explicitDataId + '"]');
			if (byId) {
				return byId;
			}
			var wrap = document.querySelector('[data-id="' + explicitDataId + '"]');
			if (wrap) {
				var inner = wrap.querySelector('.elementor-widget-loop-grid');
				if (inner) {
					return inner;
				}
				if (wrap.classList.contains('elementor-widget-loop-grid')) {
					return wrap;
				}
			}
			return null;
		}
		var loops = document.querySelectorAll('.elementor-widget-loop-grid');
		for (var i = 0; i < loops.length; i++) {
			var loop = loops[i];
			var pos = root.compareDocumentPosition(loop);
			if (pos & Node.DOCUMENT_POSITION_FOLLOWING) {
				return loop;
			}
		}
		return null;
	}

	function getWidgetDataId(loopWidget) {
		if (!loopWidget) {
			return '';
		}
		var id = loopWidget.getAttribute('data-id');
		if (id) {
			return id;
		}
		var widget = loopWidget.closest('.elementor-widget[data-id]');
		return widget ? widget.getAttribute('data-id') || '' : '';
	}

	function stripLoopPaginationParams(url, dataId) {
		if (!dataId) {
			return;
		}
		var key = 'e-page-' + dataId;
		url.searchParams.delete(key);
	}

	function mergeFormIntoUrl(form, baseUrl) {
		var url = new URL(baseUrl, window.location.origin);
		var formData = new FormData(form);
		formData.forEach(function (value, name) {
			if (name.indexOf('llm_ap_') !== 0) {
				return;
			}
			if (value === '' || value === null) {
				url.searchParams.delete(name);
			} else {
				url.searchParams.set(name, value);
			}
		});
		return url;
	}

	function getWidgetOuter(loopWidget) {
		if (!loopWidget) {
			return null;
		}
		if (loopWidget.getAttribute('data-id') && loopWidget.classList.contains('elementor-widget')) {
			return loopWidget;
		}
		return loopWidget.closest('.elementor-widget[data-id]');
	}

	function replaceLoopContainer(doc, dataId, loopWidget) {
		var outer = getWidgetOuter(loopWidget);
		var selector = '[data-id="' + dataId + '"] .elementor-widget-container';
		var fresh = doc.querySelector(selector);
		if (!fresh || !outer) {
			return false;
		}
		var current = outer.querySelector(':scope > .elementor-widget-container');
		if (!current) {
			current = outer.querySelector('.elementor-widget-container');
		}
		if (!current) {
			return false;
		}
		var imported = document.importNode(fresh, true);
		outer.replaceChild(imported, current);
		return true;
	}

	function runElementorHooks(loopWidget) {
		if (typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler && typeof elementorFrontend.elementsHandler.runReadyTrigger === 'function') {
			elementorFrontend.elementsHandler.runReadyTrigger(loopWidget);
		}
		if (typeof jQuery !== 'undefined') {
			jQuery(window).trigger('elementor-pro/loop-builder/after-insert-posts');
		}
		if (typeof document.dispatchEvent === 'function' && window.ElementorProFrontendConfig && ElementorProFrontendConfig.settings && ElementorProFrontendConfig.settings.lazy_load_background_images) {
			document.dispatchEvent(new Event('elementor/lazyload/observe'));
		}
	}

	function setLoading(root, loopWidget, on) {
		root.classList.toggle('llm-ap-loop-filters--loading', on);
		if (loopWidget) {
			loopWidget.classList.toggle('llm-ap-loop-filters-target--loading', on);
		}
	}

	function applyUrl(root, url, loopWidget, dataId) {
		setLoading(root, loopWidget, true);
		return fetch(url.href, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(function (res) {
				if (!res.ok) {
					throw new Error('HTTP ' + res.status);
				}
				return res.text();
			})
			.then(function (html) {
				var parser = new DOMParser();
				var doc = parser.parseFromString(html, 'text/html');
				if (!replaceLoopContainer(doc, dataId, loopWidget)) {
					window.location.assign(url.href);
					return;
				}
				if (window.history && window.history.pushState) {
					window.history.pushState(null, '', url.href);
				}
				runElementorHooks(loopWidget);
			})
			.catch(function () {
				window.location.assign(url.href);
			})
			.finally(function () {
				setLoading(root, loopWidget, false);
			});
	}

	function initRoot(root) {
		var form = root.querySelector('.llm-ap-loop-filters__form');
		if (!form) {
			return;
		}
		var explicitId = root.getAttribute('data-llm-ap-loop-data-id') || '';
		var loopWidget = findLoopWidget(root, explicitId);
		var dataId = getWidgetDataId(loopWidget);
		if (!loopWidget || !dataId) {
			return;
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var url = mergeFormIntoUrl(form, window.location.href);
			stripLoopPaginationParams(url, dataId);
			applyUrl(root, url, loopWidget, dataId);
		});

		var reset = root.querySelector('.llm-ap-loop-filters__reset');
		if (reset) {
			reset.addEventListener('click', function (e) {
				e.preventDefault();
				var url = new URL(reset.href);
				stripLoopPaginationParams(url, dataId);
				var fe = form.elements;
				if (fe.llm_ap_scope) {
					fe.llm_ap_scope.value = '';
				}
				if (fe.llm_ap_target_lang) {
					fe.llm_ap_target_lang.value = '';
				}
				if (fe.llm_ap_s) {
					fe.llm_ap_s.value = '';
				}
				applyUrl(root, url, loopWidget, dataId);
			});
		}
	}

	function init() {
		document.querySelectorAll('.llm-ap-loop-filters').forEach(initRoot);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
