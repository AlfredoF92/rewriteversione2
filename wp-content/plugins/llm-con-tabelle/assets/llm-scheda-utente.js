/**
 * [scheda-utente] — coppie AJAX, modifica info, switch Cast / utente.
 * Ospite: stessi dati dal localStorage del browser.
 */
(function () {
	'use strict';

	function cfg() {
		return window.llmSchedaUtente || {};
	}

	function rootEl() {
		return document.querySelector('[data-llm-scheda]');
	}

	function isGuest(root) {
		return !!(root && (root.getAttribute('data-llm-scheda-guest') === '1' || cfg().isGuest));
	}

	function stateFrom(root) {
		return {
			mode: root.getAttribute('data-mode') || 'user',
			role: root.getAttribute('data-role') || '',
			known: root.getAttribute('data-known') || '',
			target: root.getAttribute('data-target') || ''
		};
	}

	function guestStories() {
		if (!window.llmGuestBrowserStore || typeof window.llmGuestBrowserStore.getAllStories !== 'function') {
			return [];
		}
		return window.llmGuestBrowserStore.getAllStories() || [];
	}

	function guestPlayTimeLabel() {
		var store = window.llmGuestBrowserStore;
		var minutes = 0;
		if (store && typeof store.sumPlaySeconds === 'function') {
			minutes = Math.floor((store.sumPlaySeconds() || 0) / 60);
		}
		var tpl = (cfg().i18n || {}).playTimeMin || '%d min';
		return tpl.replace('%d', String(minutes));
	}

	function guestCrosswordsSolved() {
		var store = window.llmGuestBrowserStore;
		if (!store || typeof store.collectSnapshot !== 'function') {
			return 0;
		}
		var snap = store.collectSnapshot();
		if (!snap || !snap.totals) {
			return 0;
		}
		return parseInt(snap.totals.crosswordsSolved, 10) || 0;
	}

	function splitGuestStories(stories) {
		var started = [];
		var completed = [];
		(stories || []).forEach(function (s) {
			var id = parseInt(s.storyId, 10) || 0;
			if (!id) {
				return;
			}
			if (s.finished) {
				completed.push(id);
			} else {
				started.push(id);
			}
		});
		return { started: started, completed: completed };
	}

	function pairsFor(mode, role) {
		var data = cfg().modes || {};
		if (mode === 'cast' && role && data.cast && data.cast[role]) {
			return data.cast[role].pairs || [];
		}
		return (data.user && data.user.pairs) ? data.user.pairs : [];
	}

	function renderPairs(root, pairs, known, target) {
		var wrap = root.querySelector('[data-llm-scheda-pairs]');
		if (!wrap) {
			return;
		}
		if (!pairs.length) {
			wrap.innerHTML = '<p class="llm-scheda-utente__pairs-empty">' + escapeHtml((cfg().i18n || {}).noPairs || (cfg().i18n || {}).empty || '') + '</p>';
			return;
		}
		var html = '';
		for (var i = 0; i < pairs.length; i++) {
			var p = pairs[i];
			var on = p.known === known && p.target === target;
			html += '<button type="button" class="llm-scheda-utente__pair' + (on ? ' is-active' : '') + '" data-known="' + attr(p.known) + '" data-target="' + attr(p.target) + '" role="tab" aria-selected="' + (on ? 'true' : 'false') + '">';
			html += '<span class="llm-scheda-utente__pair-flags">' + escapeHtml(p.flags || '') + '</span>';
			html += '<span class="llm-scheda-utente__pair-count">' + escapeHtml(String(p.count || 0)) + '</span>';
			html += '</button>';
		}
		wrap.innerHTML = html;
	}

	function pickDefault(pairs) {
		if (!pairs.length) {
			return { known: '', target: '' };
		}
		return { known: pairs[0].known, target: pairs[0].target };
	}

	function setModeButtons(root, mode, role) {
		var buttons = root.querySelectorAll('[data-llm-scheda-mode]');
		for (var i = 0; i < buttons.length; i++) {
			var btn = buttons[i];
			var m = btn.getAttribute('data-llm-scheda-mode') || 'user';
			var r = btn.getAttribute('data-llm-scheda-role') || '';
			var on = m === mode && r === role;
			btn.classList.toggle('is-active', on);
		}
	}

	function setStat(root, key, value) {
		var el = root.querySelector('[data-llm-scheda-stat="' + key + '"]');
		if (el) {
			el.textContent = String(value == null ? 0 : value);
		}
	}

	function loadStories(root) {
		var board = root.querySelector('[data-llm-scheda-board]');
		if (!board) {
			return;
		}
		var st = stateFrom(root);
		if (!st.known || !st.target) {
			var emptyMsg = (isGuest(root) ? (cfg().i18n || {}).noPairs : '') || (cfg().i18n || {}).empty || '';
			board.innerHTML = '<p class="llm-scheda-utente__empty">' + escapeHtml(emptyMsg) + '</p>';
			return;
		}
		board.innerHTML = '<p class="llm-scheda-utente__empty">' + escapeHtml((cfg().i18n || {}).loading || '') + '</p>';
		var body = new FormData();
		body.append('nonce', cfg().storiesNonce || '');
		body.append('known', st.known);
		body.append('target', st.target);
		if (isGuest(root)) {
			var split = splitGuestStories(guestStories());
			body.append('action', cfg().guestAct || '');
			body.append('started', split.started.join(','));
			body.append('completed', split.completed.join(','));
		} else {
			body.append('action', cfg().storiesAct || '');
			body.append('user_id', String(cfg().userId || 0));
			body.append('mode', st.mode);
			body.append('role', st.role);
		}
		fetch(cfg().ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (!json || !json.success || !json.data || typeof json.data.html !== 'string') {
					board.innerHTML = '<p class="llm-scheda-utente__empty">' + escapeHtml((cfg().i18n || {}).error || '') + '</p>';
					return;
				}
				board.innerHTML = json.data.html;
			})
			.catch(function () {
				board.innerHTML = '<p class="llm-scheda-utente__empty">' + escapeHtml((cfg().i18n || {}).error || '') + '</p>';
			});
	}

	function applyMode(root, mode, role) {
		var pairs = pairsFor(mode, role);
		var def = pickDefault(pairs);
		root.setAttribute('data-mode', mode);
		root.setAttribute('data-role', role || '');
		root.setAttribute('data-known', def.known);
		root.setAttribute('data-target', def.target);
		setModeButtons(root, mode, role || '');
		renderPairs(root, pairs, def.known, def.target);
		loadStories(root);
	}

	function fillGuestIdentity(root) {
		var store = window.llmGuestBrowserStore;
		var i18n = cfg().i18n || {};
		var fallback = i18n.guestName || 'Utente browser';
		var name = store && typeof store.getName === 'function' ? (store.getName() || '') : '';
		var nameEl = root.querySelector('[data-field="display_name"]');
		if (nameEl) {
			nameEl.textContent = name || fallback;
		}
		var nameInput = root.querySelector('[name="display_name"]');
		if (nameInput) {
			nameInput.value = name;
		}
		hydrateGuestAvatar(root);
		var stories = guestStories();
		var phrases = 0;
		var points = 0;
		var done = 0;
		var started = 0;
		stories.forEach(function (s) {
			phrases += s.phrasesDone || 0;
			points += s.points || 0;
			if (s.finished) {
				done += 1;
			} else {
				started += 1;
			}
		});
		setStat(root, 'phrases', phrases);
		setStat(root, 'crosswords', guestCrosswordsSolved());
		setStat(root, 'play_time', guestPlayTimeLabel());
		setStat(root, 'points', points);
		setStat(root, 'completed', done);
		setStat(root, 'started', started);
		setStat(root, 'bravi', 0);
		return stories;
	}

	function hydrateGuest(root) {
		var stories = fillGuestIdentity(root);
		var ids = [];
		stories.forEach(function (s) {
			var id = parseInt(s.storyId, 10) || 0;
			if (id) {
				ids.push(id);
			}
		});
		if (!ids.length) {
			if (!cfg().modes) {
				cfg().modes = {};
			}
			cfg().modes.user = { pairs: [], started: [], completed: [] };
			applyMode(root, 'user', '');
			return;
		}
		var body = new FormData();
		body.append('action', cfg().guestPairsAct || '');
		body.append('nonce', cfg().storiesNonce || '');
		body.append('ids', ids.join(','));
		fetch(cfg().ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				var pairs = (json && json.success && json.data && json.data.pairs) ? json.data.pairs : [];
				if (!cfg().modes) {
					cfg().modes = {};
				}
				cfg().modes.user = { pairs: pairs, started: [], completed: [] };
				applyMode(root, 'user', '');
			})
			.catch(function () {
				if (!cfg().modes) {
					cfg().modes = {};
				}
				cfg().modes.user = { pairs: [], started: [], completed: [] };
				applyMode(root, 'user', '');
			});
	}

	function setEdit(root, on) {
		var stories = root.querySelector('[data-llm-scheda-stories]');
		var panel = root.querySelector('[data-llm-scheda-edit-panel]');
		var btn = root.querySelector('[data-llm-scheda-edit]');
		if (stories) {
			stories.hidden = !!on;
		}
		if (panel) {
			panel.hidden = !on;
		}
		if (btn) {
			btn.classList.toggle('is-active', !!on);
		}
	}

	function saveGuestInfo(root, form) {
		var msg = form.querySelector('[data-llm-scheda-form-msg]');
		var input = form.querySelector('[name="display_name"]');
		var name = input ? String(input.value || '').trim() : '';
		if (window.llmGuestBrowserStore && typeof window.llmGuestBrowserStore.setName === 'function') {
			name = window.llmGuestBrowserStore.setName(name) || name;
		}
		var fallback = (cfg().i18n || {}).guestName || 'Utente browser';
		var nameEl = root.querySelector('[data-field="display_name"]');
		if (nameEl) {
			nameEl.textContent = name || fallback;
		}
		try {
			window.dispatchEvent(new Event('llm-guest-name-changed'));
		} catch (e) {
			/* ignore */
		}
		persistAvatar(root);
		showMsg(msg, (cfg().i18n || {}).saved, true);
		setEdit(root, false);
	}

	function saveInfo(root, form) {
		if (isGuest(root)) {
			saveGuestInfo(root, form);
			return;
		}
		var msg = form.querySelector('[data-llm-scheda-form-msg]');
		var body = new FormData(form);
		body.append('action', cfg().saveAct || '');
		body.append('nonce', cfg().saveNonce || '');
		if (msg) {
			msg.hidden = true;
			msg.classList.remove('is-ok', 'is-err');
		}
		fetch(cfg().ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (!json || !json.success || !json.data) {
					showMsg(msg, (json && json.data && json.data.message) || (cfg().i18n || {}).error, false);
					return;
				}
				var data = json.data;
				var name = root.querySelector('[data-field="display_name"]');
				var bio = root.querySelector('[data-field="description"]');
				var link = root.querySelector('[data-field="user_url"]');
				var wrap = root.querySelector('.llm-scheda-utente__link-wrap');
				var img = root.querySelector('.llm-scheda-utente__photo-img');
				if (name) {
					name.textContent = data.display_name || '';
				}
				if (bio) {
					bio.textContent = data.description || '';
					bio.hidden = !data.description;
				}
				if (link) {
					link.textContent = data.user_url || '';
					link.setAttribute('href', data.user_url || '#');
				}
				if (wrap) {
					wrap.hidden = !data.user_url;
				}
				if (img && data.photo) {
					img.setAttribute('src', data.photo);
				}
				if (data.avatar) {
					markAvatarSaved(root, data.avatar);
				}
				showMsg(msg, data.message || (cfg().i18n || {}).saved, true);
				setEdit(root, false);
			})
			.catch(function () {
				showMsg(msg, (cfg().i18n || {}).error, false);
			});
	}

	function showMsg(el, text, ok) {
		if (!el) {
			return;
		}
		el.textContent = text || '';
		el.hidden = !text;
		el.classList.toggle('is-ok', !!ok);
		el.classList.toggle('is-err', !ok);
	}

	function escapeHtml(s) {
		return String(s || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function attr(s) {
		return escapeHtml(s).replace(/'/g, '&#39;');
	}

	function avatarUrl(file) {
		if (window.llmAvatarCatalog && typeof window.llmAvatarCatalog.url === 'function') {
			return window.llmAvatarCatalog.url(file);
		}
		var c = window.llmUserAvatars || {};
		if (!file || !c.baseUrl) {
			return '';
		}
		return String(c.baseUrl).replace(/\/?$/, '/') + file + (c.version ? '?v=' + encodeURIComponent(c.version) : '');
	}

	function photoImg(root) {
		return root.querySelector('[data-llm-scheda-photo]') || root.querySelector('.llm-scheda-utente__photo-img');
	}

	function savedAvatarFile(root) {
		var img = photoImg(root);
		return img ? (img.getAttribute('data-llm-scheda-avatar') || '') : '';
	}

	function currentPreviewFile(root) {
		var input = root.querySelector('[data-llm-scheda-avatar-input]');
		if (input && input.value) {
			return input.value;
		}
		return savedAvatarFile(root);
	}

	function setAvatarPending(root, on) {
		var wrap = root.querySelector('[data-llm-scheda-avatar-pending]');
		if (wrap) {
			wrap.hidden = !on;
		}
		var photo = root.querySelector('.llm-scheda-utente__photo');
		if (photo) {
			photo.classList.toggle('is-pending', !!on);
		}
	}

	function previewAvatar(root, file, pending) {
		var img = photoImg(root);
		var src = avatarUrl(file);
		if (img && src) {
			img.setAttribute('src', src);
		}
		var input = root.querySelector('[data-llm-scheda-avatar-input]');
		if (input) {
			input.value = file || '';
		}
		setAvatarPending(root, !!pending);
	}

	function revertAvatar(root) {
		previewAvatar(root, savedAvatarFile(root), false);
	}

	function nextAvatar(root) {
		var store = window.llmGuestBrowserStore;
		var current = currentPreviewFile(root);
		var file = '';
		if (store && typeof store.pickRandomAvatar === 'function') {
			file = store.pickRandomAvatar(current);
		} else {
			var files = (window.llmUserAvatars && window.llmUserAvatars.files) || [];
			var pool = [];
			for (var i = 0; i < files.length; i++) {
				if (files[i] && files[i] !== current) {
					pool.push(files[i]);
				}
			}
			if (!pool.length) {
				pool = files.slice();
			}
			file = pool.length ? pool[Math.floor(Math.random() * pool.length)] : '';
		}
		if (file) {
			previewAvatar(root, file, file !== savedAvatarFile(root));
		}
	}

	function applyAvatarToHeader(file) {
		var src = avatarUrl(file);
		if (!src) {
			return;
		}
		document.querySelectorAll('.llm-nav-menu__avatar-img, .llm-header-user__avatar').forEach(function (el) {
			el.setAttribute('src', src);
		});
	}

	function markAvatarSaved(root, file) {
		var img = photoImg(root);
		if (img) {
			img.setAttribute('data-llm-scheda-avatar', file || '');
		}
		previewAvatar(root, file, false);
		applyAvatarToHeader(file);
	}

	function persistAvatar(root) {
		var file = currentPreviewFile(root);
		if (!file) {
			return;
		}
		if (isGuest(root)) {
			var store = window.llmGuestBrowserStore;
			if (store && typeof store.setAvatar === 'function') {
				store.setAvatar(file);
			}
			markAvatarSaved(root, file);
			try {
				window.dispatchEvent(new Event('llm-guest-avatar-changed'));
			} catch (e) {
				/* ignore */
			}
			return;
		}
		var body = new FormData();
		body.append('action', cfg().saveAct || '');
		body.append('nonce', cfg().saveNonce || '');
		body.append('avatar', file);
		fetch(cfg().ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (!json || !json.success || !json.data) {
					return;
				}
				markAvatarSaved(root, json.data.avatar || file);
			})
			.catch(function () { /* ignore */ });
	}

	function hydrateGuestAvatar(root) {
		var store = window.llmGuestBrowserStore;
		if (!store || typeof store.ensureAvatar !== 'function') {
			return;
		}
		var file = store.ensureAvatar();
		if (file) {
			markAvatarSaved(root, file);
		}
	}

	function bind(root) {
		root.addEventListener('click', function (event) {
			var pair = event.target.closest('.llm-scheda-utente__pair');
			if (pair && root.contains(pair)) {
				event.preventDefault();
				root.setAttribute('data-known', pair.getAttribute('data-known') || '');
				root.setAttribute('data-target', pair.getAttribute('data-target') || '');
				var all = root.querySelectorAll('.llm-scheda-utente__pair');
				for (var i = 0; i < all.length; i++) {
					var on = all[i] === pair;
					all[i].classList.toggle('is-active', on);
					all[i].setAttribute('aria-selected', on ? 'true' : 'false');
				}
				setEdit(root, false);
				loadStories(root);
				return;
			}

			var modeBtn = event.target.closest('[data-llm-scheda-mode]');
			if (modeBtn && root.contains(modeBtn)) {
				event.preventDefault();
				setEdit(root, false);
				applyMode(root, modeBtn.getAttribute('data-llm-scheda-mode') || 'user', modeBtn.getAttribute('data-llm-scheda-role') || '');
				return;
			}

			if (event.target.closest('[data-llm-scheda-edit]')) {
				event.preventDefault();
				var panel = root.querySelector('[data-llm-scheda-edit-panel]');
				setEdit(root, panel ? panel.hidden : true);
				return;
			}

			if (event.target.closest('[data-llm-scheda-cancel]')) {
				event.preventDefault();
				revertAvatar(root);
				setEdit(root, false);
				return;
			}

			if (event.target.closest('[data-llm-scheda-avatar-next]')) {
				event.preventDefault();
				nextAvatar(root);
				return;
			}

			if (event.target.closest('[data-llm-scheda-avatar-save]')) {
				event.preventDefault();
				persistAvatar(root);
				return;
			}

			if (event.target.closest('[data-llm-scheda-avatar-cancel]')) {
				event.preventDefault();
				revertAvatar(root);
				return;
			}
		});

		var form = root.querySelector('[data-llm-scheda-form]');
		if (form) {
			form.addEventListener('submit', function (event) {
				event.preventDefault();
				saveInfo(root, form);
			});
		}
	}

	function boot() {
		var root = rootEl();
		if (!root) {
			return;
		}
		bind(root);
		if (isGuest(root)) {
			hydrateGuest(root);
			return;
		}
		var first = root.querySelector('.llm-scheda-utente__pair.is-active');
		root.setAttribute('data-mode', 'user');
		root.setAttribute('data-role', '');
		root.setAttribute('data-known', first ? (first.getAttribute('data-known') || '') : '');
		root.setAttribute('data-target', first ? (first.getAttribute('data-target') || '') : '');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
