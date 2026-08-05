/* llm-guest-browser-data.js — riempie lo shortcode [llm_guest_browser_data]. */
(function () {
	'use strict';

	var cfg = window.llmGuestBrowserData || {};
	var i18n = cfg.i18n || {};

	function t(key) {
		return i18n[key] || key;
	}

	function esc(str) {
		return String(str == null ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function displayOrNone(value) {
		return value ? esc(value) : esc(t('none'));
	}

	function formatBytes(bytes) {
		var n = parseInt(bytes, 10) || 0;
		if (n < 1024) {
			return n + ' ' + t('bytes');
		}
		return (Math.round((n / 1024) * 100) / 100) + ' KB';
	}

	function bindNameForm(root) {
		var form = root.querySelector('[data-llm-guest-name-form]');
		if (!form || !window.llmGuestBrowserStore) {
			return;
		}
		var input = form.querySelector('input');
		var msg = form.querySelector('[data-llm-guest-name-msg]');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var saved = window.llmGuestBrowserStore.setName(input ? input.value : '');
			if (input) {
				input.value = saved;
			}
			if (msg) {
				msg.textContent = t('name_saved');
				msg.hidden = false;
				window.setTimeout(function () {
					msg.hidden = true;
				}, 1600);
			}
		});
	}

	function resolveDisplayName() {
		var browserName = '';
		if (window.llmGuestBrowserStore && typeof window.llmGuestBrowserStore.getName === 'function') {
			browserName = window.llmGuestBrowserStore.getName() || '';
		}
		return browserName || cfg.displayName || '';
	}

	function fillLoggedInHello(root) {
		var el = root.querySelector('[data-llm-guest-hello]');
		if (!el) {
			return;
		}
		var name = resolveDisplayName();
		var template = t('hello_name') || 'Ciao, %s';
		el.textContent = template.replace('%s', name || t('none'));
	}

	function render(root) {
		if (!cfg.isGuest) {
			fillLoggedInHello(root);
			return;
		}
		if (!window.llmGuestBrowserStore) {
			return;
		}

		var loading = root.querySelector('.llm-guest-browser-data__loading');
		var body = root.querySelector('.llm-guest-browser-data__body');
		if (!body) {
			return;
		}

		var snap = window.llmGuestBrowserStore.collectSnapshot();
		var totals = snap.totals || {};
		var storage = snap.storage || { items: [], totalBytes: 0 };
		var stories = snap.stories || [];
		var name = snap.name || '';

		var html = '';

		html += '<form class="llm-guest-browser-data__hello" data-llm-guest-name-form>';
		html += '<label class="llm-guest-browser-data__ciao" for="llm-guest-display-name">' + esc(t('hello')) + '</label>';
		html += '<input id="llm-guest-display-name" class="llm-guest-browser-data__name-input" type="text" maxlength="60" autocomplete="nickname" placeholder="' + esc(t('name_placeholder')) + '" value="' + esc(name) + '" />';
		html += '<button type="submit" class="llm-guest-browser-data__name-save">' + esc(t('name_save')) + '</button>';
		html += '<span class="llm-guest-browser-data__name-msg" data-llm-guest-name-msg hidden></span>';
		html += '</form>';

		html += '<p class="llm-guest-browser-data__stats">';
		html += '<span><b>' + esc(totals.stories || 0) + '</b> ' + esc(t('stories_count')) + '</span>';
		html += '<span><b>' + esc(totals.finished || 0) + '</b> ' + esc(t('finished_count')) + '</span>';
		html += '<span><b>' + esc(totals.phrasesDone || 0) + '</b> ' + esc(t('phrases_done')) + '</span>';
		html += '<span><b>' + esc(totals.points || 0) + '</b> ' + esc(t('points')) + '</span>';
		html += '<span><b>' + esc(formatBytes(storage.totalBytes)) + '</b></span>';
		html += '</p>';

		html += '<p class="llm-guest-browser-data__prefs">';
		html += displayOrNone(snap.knownLang) + ' → ' + displayOrNone(snap.learningLang);
		html += ' · ' + displayOrNone(snap.learningMode);
		if (snap.learningOptions) {
			html += ' · ' + esc(snap.learningOptions);
		}
		html += '</p>';

		if (!stories.length) {
			html += '<p class="llm-guest-browser-data__empty">' + esc(t('empty_stories')) + '</p>';
		} else {
			html += '<table class="llm-guest-browser-data__table">';
			html += '<thead><tr>';
			html += '<th>' + esc(t('story')) + '</th>';
			html += '<th>' + esc(t('phrase')) + '</th>';
			html += '<th>' + esc(t('done')) + '</th>';
			html += '<th>' + esc(t('points')) + '</th>';
			html += '<th>' + esc(t('status')) + '</th>';
			html += '</tr></thead><tbody>';
			stories.forEach(function (s) {
				var label = s.title ? s.title : ('#' + s.storyId);
				var phraseLabel = (s.phraseIndex + 1) + (s.phrasesTotal ? '/' + s.phrasesTotal : '');
				var doneLabel = s.phrasesDone + (s.phrasesTotal ? '/' + s.phrasesTotal : '');
				html += '<tr>';
				html += '<td>' + esc(label) + '</td>';
				html += '<td>' + esc(phraseLabel) + '</td>';
				html += '<td>' + esc(doneLabel) + '</td>';
				html += '<td>' + esc(s.points || 0) + '</td>';
				html += '<td>' + esc(s.finished ? t('finished') : t('in_progress')) + '</td>';
				html += '</tr>';
			});
			html += '</tbody></table>';
		}

		if (storage.items && storage.items.length) {
			html += '<p class="llm-guest-browser-data__keys">';
			html += storage.items.map(function (item) {
				return '<code>' + esc(item.key) + '</code> ' + esc(formatBytes(item.bytes));
			}).join(' · ');
			html += '</p>';
		}

		var disclaimer = t('disclaimer').replace('%s', formatBytes(storage.totalBytes));
		html += '<p class="llm-guest-browser-data__disclaimer">' + esc(disclaimer) + '</p>';

		body.innerHTML = html;
		body.hidden = false;
		if (loading) {
			loading.hidden = true;
		}
		bindNameForm(body);
	}

	function init() {
		document.querySelectorAll('[data-llm-guest-browser-data]').forEach(render);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
