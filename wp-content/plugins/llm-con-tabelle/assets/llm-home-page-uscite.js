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
		var i18n = parseJson(root.getAttribute('data-i18n'), {});
		var year = parseInt(root.getAttribute('data-year'), 10) || new Date().getFullYear();
		var month = parseInt(root.getAttribute('data-month'), 10) || (new Date().getMonth() + 1);
		var selected = root.getAttribute('data-selected') || '';
		var greetings = parseJson(root.getAttribute('data-greetings'), []);
		var helloName = (root.getAttribute('data-hello-name') || '').trim();

		var track = qs(root, '[data-llm-uscite-track]');
		var prev = qs(root, '[data-llm-uscite-prev]');
		var next = qs(root, '[data-llm-uscite-next]');
		var cal = qs(root, '[data-llm-uscite-cal]');
		var label = qs(root, '[data-llm-uscite-cal-label]');
		var shiftPrev = qs(root, '[data-llm-uscite-cal-prev]');
		var shiftNext = qs(root, '[data-llm-uscite-cal-next]');
		var detailTitle = qs(root, '[data-llm-uscite-detail-title]');
		var detailList = qs(root, '[data-llm-uscite-detail-list]');

		function scrollCards(dir) {
			if (!track) {
				return;
			}
			var card = track.querySelector('.llm-uscite__card');
			var w = card ? card.getBoundingClientRect().width + 12 : 280;
			track.scrollBy({ left: dir * w, behavior: 'smooth' });
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

		function monthName(n) {
			var months = i18n.months || [];
			return months[n - 1] || '';
		}

		function neighborMonth(y, m, delta) {
			var d = new Date(y, m - 1 + delta, 1);
			return { y: d.getFullYear(), m: d.getMonth() + 1 };
		}

		function renderDetail(key) {
			selected = key;
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
				var row = document.createElement(item.url ? 'a' : 'div');
				row.className = 'llm-uscite__row';
				if (item.url) {
					row.href = item.url;
				}
				var cover = document.createElement('div');
				cover.className = 'llm-uscite__row-cover';
				if (item.cover) {
					cover.style.backgroundImage = 'url(' + item.cover + ')';
				}
				var body = document.createElement('div');
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
			if (shiftPrev) {
				shiftPrev.textContent = '‹ ' + monthName(prevM.m);
			}
			if (shiftNext) {
				shiftNext.textContent = monthName(nextM.m) + ' ›';
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
				renderCal();
			});
		}
		if (shiftNext) {
			shiftNext.addEventListener('click', function () {
				var n = neighborMonth(year, month, 1);
				year = n.y;
				month = n.m;
				renderCal();
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
			if (selected) {
				renderDetail(selected);
			}
		}

		renderCal();
		if (selected) {
			renderDetail(selected);
		}
		startGreetingCycle(root, greetings, helloName, applySnapLang);
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

	function startGreetingCycle(root, greetings, name, onLang) {
		var helloEl = qs(root, '[data-llm-uscite-hello]');
		var subEl = qs(root, '[data-llm-uscite-hello-sub]');
		var flagEl = qs(root, '[data-llm-uscite-flag]');
		var latestEl = qs(root, '[data-llm-uscite-title="latest"]');
		var calTitleEl = qs(root, '[data-llm-uscite-title="calendar"]');
		var pairsEl = qs(root, '[data-llm-uscite-title="pairs"]');
		if (!helloEl || !subEl || !greetings.length) {
			return;
		}
		var ix = 0;
		var typeMs = 125;
		var fadeMs = 420;
		var pauseMs = 10000;
		var first = true;

		function lineFor(item) {
			if (name) {
				return String(item.helloName || item.hello || '').replace('%s', name);
			}
			return String(item.hello || '');
		}

		function run() {
			var item = greetings[ix % greetings.length];
			ix += 1;
			if (flagEl) {
				flagEl.textContent = item.flag || '';
			}
			if (typeof onLang === 'function') {
				onLang(item);
			}
			helloEl.textContent = '';
			subEl.textContent = '';
			var greeting = typewriterInto(helloEl, lineFor(item), typeMs).then(function () {
				return typewriterInto(subEl, item.sub || '', typeMs);
			});
			var titles;
			if (first) {
				first = false;
				if (latestEl) {
					latestEl.textContent = item.latest || '';
				}
				if (calTitleEl) {
					calTitleEl.textContent = item.calendar || '';
				}
				if (pairsEl) {
					pairsEl.textContent = item.pairs || '';
				}
				titles = Promise.resolve();
			} else {
				titles = Promise.all([
					fadeText(latestEl, item.latest || '', fadeMs),
					fadeText(calTitleEl, item.calendar || '', fadeMs),
					fadeText(pairsEl, item.pairs || '', fadeMs)
				]);
			}
			Promise.all([greeting, titles]).then(function () {
				setTimeout(run, pauseMs);
			});
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
