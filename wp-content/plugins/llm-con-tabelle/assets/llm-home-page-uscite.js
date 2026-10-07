/**
 * [home-page-uscite] — carosello + calendario.
 */
(function () {
	function qs(root, sel) {
		return root.querySelector(sel);
	}

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function dayKey(y, m, d) {
		return y + '-' + pad(m) + '-' + pad(d);
	}

	function parseJson(raw, fallback) {
		try {
			return JSON.parse(raw || '');
		} catch (e) {
			return fallback;
		}
	}

	function init(root) {
		var events = parseJson(root.getAttribute('data-events'), {});
		var latestAll = parseJson(root.getAttribute('data-latest'), []);
		var upcomingAll = parseJson(root.getAttribute('data-upcoming'), []);
		var i18n = parseJson(root.getAttribute('data-i18n'), {});
		var year = parseInt(root.getAttribute('data-year'), 10) || new Date().getFullYear();
		var month = parseInt(root.getAttribute('data-month'), 10) || (new Date().getMonth() + 1);
		var selected = root.getAttribute('data-selected') || '';
		var todayKey = root.getAttribute('data-today') || '';
		var greetings = parseJson(root.getAttribute('data-greetings'), []);
		var helloName = (root.getAttribute('data-hello-name') || '').trim();

		var track = qs(root, '[data-llm-uscite-track]');
		var prev = qs(root, '[data-llm-uscite-prev]');
		var next = qs(root, '[data-llm-uscite-next]');
		var pairsTrack = qs(root, '[data-llm-uscite-pairs-track]');
		var pairsPrev = qs(root, '[data-llm-uscite-pairs-prev]');
		var pairsNext = qs(root, '[data-llm-uscite-pairs-next]');
		var cal = qs(root, '[data-llm-uscite-cal]');
		var label = qs(root, '[data-llm-uscite-cal-label]');
		var shiftPrev = qs(root, '[data-llm-uscite-cal-prev]');
		var shiftNext = qs(root, '[data-llm-uscite-cal-next]');
		var detailTitle = qs(root, '[data-llm-uscite-detail-title]');
		var detailList = qs(root, '[data-llm-uscite-detail-list]');
		var isAdmin = root.getAttribute('data-is-admin') === '1';
		var storyPop = qs(root, '[data-llm-uscite-story-pop]');
		var storyPopCover = qs(root, '[data-llm-uscite-story-pop-cover]');
		var storyPopMeta = qs(root, '[data-llm-uscite-story-pop-meta]');
		var storyPopTitle = qs(root, '[data-llm-uscite-story-pop-title]');
		var storyPopSub = qs(root, '[data-llm-uscite-story-pop-sub]');
		var storyPopPlot = qs(root, '[data-llm-uscite-story-pop-plot]');
		var storyPopCta = qs(root, '[data-llm-uscite-story-pop-cta]');
		var storyPopAdmin = qs(root, '[data-llm-uscite-story-pop-admin]');
		var storyPopScheduled = qs(root, '[data-llm-uscite-story-pop-scheduled]');

		function closeStoryPop() {
			if (!storyPop) {
				return;
			}
			storyPop.setAttribute('hidden', 'hidden');
			document.body.classList.remove('llm-uscite-story-pop-open');
		}

		function openStoryPop(item) {
			if (!storyPop || !item) {
				return;
			}
			if (storyPopCover) {
				if (item.cover) {
					storyPopCover.style.backgroundImage = 'url(' + item.cover + ')';
					storyPopCover.classList.remove('is-empty');
				} else {
					storyPopCover.style.backgroundImage = '';
					storyPopCover.classList.add('is-empty');
				}
			}
			if (storyPopMeta) {
				storyPopMeta.textContent = item.dateLabel || item.timeLabel || '';
			}
			if (storyPopTitle) {
				storyPopTitle.textContent = item.title || '';
			}
			if (storyPopSub) {
				var sub = item.pair || item.subtitle || '';
				storyPopSub.textContent = sub;
				storyPopSub.hidden = !sub;
			}
			if (storyPopPlot) {
				var plot = item.plot || '';
				storyPopPlot.textContent = plot;
				storyPopPlot.hidden = !plot;
			}
			var canOpen = !!item.canOpen;
			var showAdminAccess = isAdmin && !!item.url && (item.status === 'future' || item.status === 'draft' || !canOpen);
			if (storyPopCta) {
				if (canOpen && item.url && !showAdminAccess) {
					storyPopCta.href = item.url;
					storyPopCta.textContent = i18n.more || 'Apri';
					storyPopCta.hidden = false;
					storyPopCta.removeAttribute('hidden');
				} else {
					storyPopCta.hidden = true;
					storyPopCta.setAttribute('hidden', 'hidden');
					storyPopCta.removeAttribute('href');
					storyPopCta.textContent = '';
				}
			}
			if (storyPopAdmin) {
				if (showAdminAccess) {
					storyPopAdmin.href = item.url;
					storyPopAdmin.textContent = i18n.adminAccess || 'Accedi da admin';
					storyPopAdmin.hidden = false;
					storyPopAdmin.removeAttribute('hidden');
				} else {
					storyPopAdmin.hidden = true;
					storyPopAdmin.setAttribute('hidden', 'hidden');
					storyPopAdmin.removeAttribute('href');
					storyPopAdmin.textContent = '';
				}
			}
			if (storyPopScheduled) {
				if (!canOpen || showAdminAccess) {
					storyPopScheduled.textContent = item.scheduledLabel || (i18n.scheduled || 'Programmata');
					storyPopScheduled.hidden = false;
					storyPopScheduled.removeAttribute('hidden');
				} else {
					storyPopScheduled.hidden = true;
					storyPopScheduled.setAttribute('hidden', 'hidden');
				}
			}
			storyPop.removeAttribute('hidden');
			document.body.classList.add('llm-uscite-story-pop-open');
		}

		if (storyPop) {
			storyPop.querySelectorAll('[data-llm-uscite-story-pop-close]').forEach(function (el) {
				el.addEventListener('click', function (e) {
					e.preventDefault();
					closeStoryPop();
				});
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && storyPop && !storyPop.hasAttribute('hidden')) {
					closeStoryPop();
				}
			});
		}

		function scrollCards(dir) {
			if (!track) {
				return;
			}
			var card = track.querySelector('.llm-uscite__card');
			var w = card ? card.getBoundingClientRect().width + 12 : 280;
			track.scrollBy({ left: dir * w, behavior: 'smooth' });
		}

		function updateCarouselArrows() {
			if (!track) {
				return;
			}
			var sl = track.scrollLeft;
			var max = track.scrollWidth - track.clientWidth;
			if (prev) {
				prev.classList.toggle('is-hidden', sl <= 4);
			}
			if (next) {
				next.classList.toggle('is-hidden', max <= 4 || sl >= max - 4);
			}
		}

		if (prev) {
			prev.addEventListener('click', function () {
				scrollCards(-1);
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				scrollCards(1);
			});
		}
		if (track) {
			track.addEventListener('scroll', updateCarouselArrows, { passive: true });
		}

		function pairStep() {
			if (!pairsTrack) {
				return 260;
			}
			var card = pairsTrack.querySelector('.llm-uscite__pair-card');
			if (!card) {
				return 260;
			}
			var styles = window.getComputedStyle(pairsTrack);
			var gap = parseFloat(styles.columnGap || styles.gap) || 8.8;
			return (card.getBoundingClientRect().width + gap) * 2;
		}

		function updatePairArrows() {
			if (!pairsTrack) {
				return;
			}
			var sl = pairsTrack.scrollLeft;
			var max = pairsTrack.scrollWidth - pairsTrack.clientWidth;
			if (pairsPrev) {
				pairsPrev.classList.toggle('is-hidden', sl <= 4);
			}
			if (pairsNext) {
				pairsNext.classList.toggle('is-hidden', max <= 4 || sl >= max - 4);
			}
		}

		function scrollPairs(dir) {
			if (!pairsTrack) {
				return;
			}
			pairsTrack.scrollBy({ left: dir * pairStep(), behavior: 'smooth' });
		}

		if (pairsPrev) {
			pairsPrev.addEventListener('click', function () {
				scrollPairs(-1);
			});
		}
		if (pairsNext) {
			pairsNext.addEventListener('click', function () {
				scrollPairs(1);
			});
		}
		if (pairsTrack) {
			pairsTrack.addEventListener('scroll', updatePairArrows, { passive: true });
			window.addEventListener('resize', updatePairArrows);
			updatePairArrows();
		}

		function filterStories(list, known, target) {
			return (list || []).filter(function (item) {
				return item && item.known === known && item.target === target;
			});
		}

		function eventsFromStories(list) {
			var by = {};
			(list || []).forEach(function (item) {
				var day = item && item.day ? String(item.day) : '';
				if (!day) {
					return;
				}
				if (!by[day]) {
					by[day] = [];
				}
				by[day].push(item);
			});
			return by;
		}

		function staggerFadeIn(nodes, stepMs) {
			var list = Array.prototype.slice.call(nodes || []);
			if (!list.length) {
				return;
			}
			var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			if (reduce) {
				list.forEach(function (el) {
					el.classList.remove('is-enter');
				});
				return;
			}
			list.forEach(function (el) {
				el.classList.add('is-enter');
			});
			window.requestAnimationFrame(function () {
				list.forEach(function (el, i) {
					window.setTimeout(function () {
						el.classList.remove('is-enter');
					}, 50 + i * (stepMs || 90));
				});
			});
		}

		function renderCarousel(items) {
			if (!track) {
				return;
			}
			track.innerHTML = '';
			if (!items.length) {
				var empty = document.createElement('p');
				empty.className = 'llm-uscite__empty';
				empty.textContent = i18n.noStories || '';
				track.appendChild(empty);
				return;
			}
			items.forEach(function (item) {
				var url = item.url || '';
				var el = document.createElement(url ? 'a' : 'article');
				el.className = 'llm-uscite__card llm-uscite__card--carousel is-enter';
				if (url) {
					el.href = url;
				}
				if (item.title) {
					el.setAttribute('aria-label', item.title);
				}
				var cover = document.createElement('div');
				cover.className = 'llm-uscite__cover' + (item.cover ? '' : ' llm-uscite__cover--empty');
				cover.setAttribute('aria-hidden', 'true');
				if (item.cover) {
					cover.style.backgroundImage = 'url(' + JSON.stringify(String(item.cover)).slice(1, -1) + ')';
				}
				var cefr = String(item.cefr || '').toUpperCase();
				if (/^[ABC][12]$/.test(cefr)) {
					var level = document.createElement('span');
					level.className = 'llm-uscite__level llm-uscite__level--' + cefr.toLowerCase();
					var code = document.createElement('strong');
					code.className = 'llm-uscite__level-code';
					code.textContent = cefr;
					level.appendChild(code);
					var langName = String(item.targetLabel || '').trim();
					if (langName) {
						level.appendChild(document.createTextNode(' '));
						var langEl = document.createElement('span');
						langEl.className = 'llm-uscite__level-lang';
						langEl.textContent = langName;
						level.appendChild(langEl);
					}
					cover.appendChild(level);
				}
				el.appendChild(cover);
				track.appendChild(el);
			});
			staggerFadeIn(track.querySelectorAll('.llm-uscite__card'), 85);
			updateCarouselArrows();
		}

		function daysInMonth(y, m) {
			var prefix = y + '-' + pad(m) + '-';
			return Object.keys(events).filter(function (k) {
				return k.indexOf(prefix) === 0;
			}).sort();
		}

		function goToTodayMonth() {
			if (!todayKey || todayKey.length < 7) {
				return;
			}
			var parts = todayKey.split('-');
			year = parseInt(parts[0], 10) || year;
			month = parseInt(parts[1], 10) || month;
		}

		function nextDayWithStories(fromKey) {
			var keys = Object.keys(events).sort();
			var from = fromKey || todayKey || '';
			var i;
			for (i = 0; i < keys.length; i++) {
				if (keys[i] >= from) {
					return keys[i];
				}
			}
			return '';
		}

		function detailKeyForToday() {
			if (todayKey && events[todayKey] && events[todayKey].length) {
				return todayKey;
			}
			return nextDayWithStories(todayKey);
		}

		function focusTodayAndUpcoming() {
			goToTodayMonth();
			selected = todayKey || '';
			renderCal();
			renderDetail(detailKeyForToday() || selected, true);
		}

		function applyFeeds(known, target) {
			var href = '';
			var pairTitle = '';
			if (pairsTrack) {
				var active = pairsTrack.querySelector('[data-llm-uscite-pair].is-active');
				href = active ? (active.getAttribute('data-url') || '') : '';
				pairTitle = active ? (active.getAttribute('data-pair-title') || '') : '';
			}
			Array.prototype.forEach.call(root.querySelectorAll('[data-llm-uscite-all-stories]'), function (link) {
				if (href) {
					link.setAttribute('href', href);
					link.classList.remove('is-disabled');
					link.removeAttribute('aria-disabled');
				} else {
					link.removeAttribute('href');
					link.classList.add('is-disabled');
					link.setAttribute('aria-disabled', 'true');
				}
			});
			var calPair = qs(root, '[data-llm-uscite-cal-pair]');
			if (calPair && pairTitle) {
				calPair.textContent = pairTitle;
			}
			renderCarousel(filterStories(latestAll, known, target).slice(0, 8));
			events = eventsFromStories(filterStories(upcomingAll, known, target));
			focusTodayAndUpcoming();
		}

		Array.prototype.forEach.call(root.querySelectorAll('[data-llm-uscite-all-stories]'), function (link) {
			link.addEventListener('click', function (e) {
				if (link.classList.contains('is-disabled') || !link.getAttribute('href')) {
					e.preventDefault();
				}
			});
		});

		function isSoonPair(card) {
			return !!(card && (card.classList.contains('llm-uscite__pair-card--soon') || !card.getAttribute('data-url')));
		}

		function selectPairCard(card) {
			if (!card || !pairsTrack || isSoonPair(card)) {
				return;
			}
			Array.prototype.forEach.call(pairsTrack.querySelectorAll('[data-llm-uscite-pair]'), function (el) {
				el.classList.remove('is-active');
				el.setAttribute('aria-pressed', 'false');
			});
			card.classList.add('is-active');
			card.setAttribute('aria-pressed', 'true');
			if (typeof card.scrollIntoView === 'function') {
				card.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
			}
			applyFeeds(card.getAttribute('data-known') || '', card.getAttribute('data-target') || '');
		}

		if (pairsTrack) {
			pairsTrack.addEventListener('click', function (e) {
				if (e.target.closest('[data-llm-uscite-pair-link]')) {
					return;
				}
				var card = e.target.closest('[data-llm-uscite-pair]');
				if (!card || !pairsTrack.contains(card) || isSoonPair(card)) {
					return;
				}
				selectPairCard(card);
			});
			pairsTrack.addEventListener('keydown', function (e) {
				if (e.key !== 'Enter' && e.key !== ' ') {
					return;
				}
				if (e.target.closest('[data-llm-uscite-pair-link]')) {
					return;
				}
				var card = e.target.closest('[data-llm-uscite-pair]');
				if (!card || !pairsTrack.contains(card) || isSoonPair(card)) {
					return;
				}
				e.preventDefault();
				selectPairCard(card);
			});
		}

		function monthName(n) {
			var months = i18n.months || [];
			return months[n - 1] || '';
		}

		function neighborMonth(y, m, delta) {
			var d = new Date(y, m - 1 + delta, 1);
			return { y: d.getFullYear(), m: d.getMonth() + 1 };
		}

		var detailShown = '';

		function renderDetail(key, keepCalSelection) {
			if (!keepCalSelection) {
				selected = key;
			}
			detailShown = key;
			var items = events[key] || [];
			if (detailTitle) {
				var pretty = key;
				if (key && key.length >= 10) {
					var p = key.split('-');
					pretty = (parseInt(p[2], 10) || '') + ' ' + monthName(parseInt(p[1], 10) || 1);
				}
				detailTitle.textContent = (i18n.eventsOn || 'Uscite %s').replace('%s', pretty);
			}
			if (!detailList) {
				return;
			}
			detailList.innerHTML = '';
			if (!items.length) {
				var empty = document.createElement('p');
				empty.className = 'llm-uscite__empty';
				empty.textContent = i18n.noDay || '';
				detailList.appendChild(empty);
				return;
			}
			items.forEach(function (item) {
				var canOpen = !!item.canOpen;
				var isFuture = item.status === 'future' || item.status === 'draft';
				var openInPop = !canOpen || (isAdmin && isFuture);
				var row = document.createElement(openInPop ? 'button' : 'a');
				row.className = 'llm-uscite__row';
				if (openInPop) {
					row.type = 'button';
					if (!canOpen) {
						row.classList.add('llm-uscite__row--locked');
					}
					row.addEventListener('click', function (e) {
						e.preventDefault();
						openStoryPop(item);
					});
				} else if (item.url) {
					row.href = item.url;
				}
				var cover = document.createElement('div');
				cover.className = 'llm-uscite__row-cover';
				if (item.cover) {
					cover.style.backgroundImage = 'url(' + item.cover + ')';
				}
				var body = document.createElement('div');
				body.className = 'llm-uscite__row-body';
				if (isAdmin && item.statusLabel) {
					var badge = document.createElement('span');
					badge.className = 'llm-uscite__row-status is-' + (item.status || 'draft');
					badge.textContent = item.statusLabel;
					body.appendChild(badge);
				}
				var meta = document.createElement('p');
				meta.className = 'llm-uscite__row-meta';
				meta.textContent = item.dateLabel || item.timeLabel || '';
				var title = document.createElement('h3');
				title.className = 'llm-uscite__row-title';
				title.textContent = item.title || '';
				var sub = document.createElement('p');
				sub.className = 'llm-uscite__card-sub';
				sub.textContent = item.pair || item.subtitle || '';
				body.appendChild(meta);
				body.appendChild(title);
				if (sub.textContent) {
					body.appendChild(sub);
				}
				row.appendChild(cover);
				row.appendChild(body);
				detailList.appendChild(row);
			});
		}

		function renderCal() {
			if (!cal) {
				return;
			}
			cal.innerHTML = '';
			if (label) {
				label.textContent = monthName(month) + ' ' + year;
			}
			var prevM = neighborMonth(year, month, -1);
			var nextM = neighborMonth(year, month, 1);
			var compactNav = window.matchMedia('(max-width: 560px)').matches;
			if (shiftPrev) {
				shiftPrev.textContent = compactNav ? '‹' : ('‹ ' + monthName(prevM.m));
			}
			if (shiftNext) {
				shiftNext.textContent = compactNav ? '›' : (monthName(nextM.m) + ' ›');
			}

			var dows = i18n.dows || ['LUN', 'MAR', 'MER', 'GIO', 'VEN', 'SAB', 'DOM'];
			dows.forEach(function (d) {
				var el = document.createElement('div');
				el.className = 'llm-uscite__dow';
				el.textContent = d;
				cal.appendChild(el);
			});

			var first = new Date(year, month - 1, 1);
			var startDow = (first.getDay() + 6) % 7;
			var daysIn = new Date(year, month, 0).getDate();
			var prevDays = new Date(year, month - 1, 0).getDate();
			var cells = [];
			var i;
			for (i = 0; i < startDow; i++) {
				cells.push({
					y: prevM.y,
					m: prevM.m,
					d: prevDays - startDow + 1 + i,
					out: true
				});
			}
			for (i = 1; i <= daysIn; i++) {
				cells.push({ y: year, m: month, d: i, out: false });
			}
			while (cells.length % 7 !== 0) {
				var n = cells.length - (startDow + daysIn) + 1;
				cells.push({ y: nextM.y, m: nextM.m, d: n, out: true });
			}

			cells.forEach(function (cell) {
				var key = dayKey(cell.y, cell.m, cell.d);
				var items = events[key] || [];
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'llm-uscite__cell';
				if (cell.out) {
					btn.classList.add('llm-uscite__cell--out');
				}
				if (items.length) {
					btn.classList.add('llm-uscite__cell--event');
				}
				if (key === selected) {
					btn.classList.add('llm-uscite__cell--selected');
				}
				if (todayKey && key === todayKey) {
					btn.classList.add('llm-uscite__cell--today');
				}
				btn.setAttribute('data-day', key);

				var num = document.createElement('span');
				num.className = 'llm-uscite__cell-num';
				num.textContent = String(cell.d);
				btn.appendChild(num);

				if (items.length) {
					var thumbs = document.createElement('span');
					thumbs.className = 'llm-uscite__thumbs';
					items.slice(0, 3).forEach(function (item) {
						var t = document.createElement('span');
						t.className = 'llm-uscite__thumb';
						if (item.cover) {
							t.style.backgroundImage = 'url(' + item.cover + ')';
						}
						thumbs.appendChild(t);
					});
					btn.appendChild(thumbs);
				}

				btn.addEventListener('click', function () {
					if (cell.out) {
						year = cell.y;
						month = cell.m;
						selected = key;
						renderCal();
						renderDetail(key);
						return;
					}
					selected = key;
					Array.prototype.forEach.call(cal.querySelectorAll('.llm-uscite__cell--selected'), function (el) {
						el.classList.remove('llm-uscite__cell--selected');
					});
					btn.classList.add('llm-uscite__cell--selected');
					renderDetail(key);
				});
				cal.appendChild(btn);
			});
		}

		if (shiftPrev) {
			shiftPrev.addEventListener('click', function () {
				var n = neighborMonth(year, month, -1);
				year = n.y;
				month = n.m;
				var inMonth = daysInMonth(year, month);
				selected = inMonth.length ? inMonth[0] : '';
				renderCal();
				renderDetail(selected);
			});
		}
		if (shiftNext) {
			shiftNext.addEventListener('click', function () {
				var n = neighborMonth(year, month, 1);
				year = n.y;
				month = n.m;
				var inMonth = daysInMonth(year, month);
				selected = inMonth.length ? inMonth[0] : '';
				renderCal();
				renderDetail(selected);
			});
		}

		function applySnapLang(item) {
			if (!item) {
				return;
			}
			if (item.months) {
				i18n.months = item.months;
			}
			if (item.dows) {
				i18n.dows = item.dows;
			}
			if (item.eventsOn) {
				i18n.eventsOn = item.eventsOn;
			}
			if (item.noDay) {
				i18n.noDay = item.noDay;
			}
			if (item.more) {
				i18n.more = item.more;
			}
			var nav = qs(root, '.llm-uscite__nav');
			if (nav && item.carouselNav) {
				nav.setAttribute('aria-label', item.carouselNav);
			}
			if (prev && item.prev) {
				prev.setAttribute('aria-label', item.prev);
			}
			if (next && item.next) {
				next.setAttribute('aria-label', item.next);
			}
			renderCal();
			renderDetail(detailShown || detailKeyForToday() || selected, true);
		}

		focusTodayAndUpcoming();
		if (pairsTrack) {
			staggerFadeIn(pairsTrack.querySelectorAll('[data-llm-uscite-pair]'), 95);
		}
		if (track) {
			staggerFadeIn(track.querySelectorAll('.llm-uscite__card'), 85);
			updateCarouselArrows();
		}
		if (window.matchMedia) {
			var navMq = window.matchMedia('(max-width: 560px)');
			var onNavMq = function () {
				renderCal();
			};
			if (typeof navMq.addEventListener === 'function') {
				navMq.addEventListener('change', onNavMq);
			} else if (typeof navMq.addListener === 'function') {
				navMq.addListener(onNavMq);
			}
		}
		startGreetingCycle(root, greetings, helloName);
	}

	function typewriterInto(el, text, ms) {
		return new Promise(function (resolve) {
			if (!el) {
				resolve();
				return;
			}
			el.textContent = '';
			var chars = Array.from(String(text || ''));
			if (!chars.length) {
				resolve();
				return;
			}
			var node = document.createTextNode('');
			var cursor = document.createElement('span');
			cursor.className = 'llm-uscite__cursor';
			cursor.setAttribute('aria-hidden', 'true');
			el.appendChild(node);
			el.appendChild(cursor);
			var i = 0;
			function tick() {
				if (i >= chars.length) {
					if (cursor.parentNode) {
						cursor.parentNode.removeChild(cursor);
					}
					resolve();
					return;
				}
				node.nodeValue += chars[i];
				i += 1;
				setTimeout(tick, ms);
			}
			tick();
		});
	}

	function fadeText(el, text, ms) {
		return new Promise(function (resolve) {
			if (!el) {
				resolve();
				return;
			}
			var hold = typeof ms === 'number' ? ms : 420;
			el.classList.add('is-fading');
			setTimeout(function () {
				el.textContent = text || '';
				el.classList.remove('is-fading');
				setTimeout(resolve, hold);
			}, hold);
		});
	}

	function escapeHtml(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function startGreetingCycle(root, greetings, name) {
		var helloEl = qs(root, '[data-llm-uscite-hello]');
		var subEl = qs(root, '[data-llm-uscite-hello-sub]');
		if (!helloEl || !subEl || !greetings.length) {
			return;
		}
		var ix = Math.floor(Math.random() * greetings.length);
		var first = true;
		var fadeMs = 450;
		var pauseMs = 10000;
		var gradCount = 6;

		function paintHello(item) {
			var word = String(item.hello || '');
			var gix = ix % gradCount;
			helloEl.setAttribute('data-grad', String(gix));
			// Nome utente nascosto per ora: solo la parola di saluto.
			helloEl.innerHTML =
				'<span class="llm-uscite__hello-word">' + escapeHtml(word) + '</span>';
			subEl.textContent = item.sub || '';
		}

		function show(item, withFade) {
			if (!withFade) {
				paintHello(item);
				return;
			}
			helloEl.classList.add('is-fading');
			subEl.classList.add('is-fading');
			setTimeout(function () {
				paintHello(item);
				helloEl.classList.remove('is-fading');
				subEl.classList.remove('is-fading');
			}, fadeMs);
		}

		function run() {
			var item = greetings[ix % greetings.length];
			show(item, !first);
			first = false;
			ix += 1;
			setTimeout(run, pauseMs);
		}

		run();
	}

	function boot() {
		document.querySelectorAll('[data-llm-uscite]').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
