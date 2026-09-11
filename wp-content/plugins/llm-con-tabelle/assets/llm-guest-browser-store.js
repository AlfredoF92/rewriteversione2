/*
 * llm-guest-browser-store.js — progresso storie e snapshot dati guest in localStorage.
 */
(function (window) {
	'use strict';

	var STORIES_KEY = 'llm_guest_story_progress';
	var CROSSWORDS_KEY = 'llm_crossword_progress';
	var PLAY_TIME_KEY = 'llm_story_play_time';
	var NAME_KEY = 'llm_guest_display_name';
	var AVATAR_KEY = 'llm_guest_avatar';

	/** Chiavi LLM gestite nel browser (lingue, modalità, progresso, nome, avatar). */
	var KNOWN_KEYS = [
		NAME_KEY,
		AVATAR_KEY,
		'llm_interface_lang',
		'llm_learning_lang',
		'llm_learning_mode',
		'llm_learning_options',
		'llm_stt_engine',
		'llm_tts_voice',
		STORIES_KEY,
		CROSSWORDS_KEY,
		PLAY_TIME_KEY
	];

	function safeParse(raw, fallback) {
		if (!raw) {
			return fallback;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return fallback;
		}
	}

	function readStoriesMap() {
		try {
			var map = safeParse(window.localStorage.getItem(STORIES_KEY), {});
			return map && typeof map === 'object' ? map : {};
		} catch (e) {
			return {};
		}
	}

	function writeStoriesMap(map) {
		try {
			window.localStorage.setItem(STORIES_KEY, JSON.stringify(map || {}));
		} catch (e) {
			/* Quota o privacy mode: il progresso resta solo sul server. */
		}
	}

	function normalizeStory(entry, storyId) {
		entry = entry && typeof entry === 'object' ? entry : {};
		var id = parseInt(storyId || entry.storyId, 10) || 0;
		var phraseIndex = parseInt(entry.phraseIndex, 10);
		var phrasesDone = parseInt(entry.phrasesDone, 10);
		var phrasesTotal = parseInt(entry.phrasesTotal, 10);
		var points = parseInt(entry.points, 10);
		var step = parseInt(entry.step, 10);
		if (isNaN(phraseIndex)) { phraseIndex = 0; }
		if (isNaN(phrasesDone)) { phrasesDone = phraseIndex; }
		if (isNaN(phrasesTotal)) { phrasesTotal = 0; }
		if (isNaN(points)) { points = phrasesDone; }
		if (step !== 2) { step = 1; }
		var known = entry.known ? String(entry.known).toLowerCase() : '';
		var target = entry.target ? String(entry.target).toLowerCase() : '';
		return {
			storyId: id,
			title: entry.title ? String(entry.title) : '',
			phraseIndex: Math.max(0, phraseIndex),
			step: step,
			phrasesDone: Math.max(0, phrasesDone),
			phrasesTotal: Math.max(0, phrasesTotal),
			points: Math.max(0, points),
			finished: !!entry.finished || (phrasesTotal > 0 && phrasesDone >= phrasesTotal),
			known: known,
			target: target,
			updatedAt: entry.updatedAt ? String(entry.updatedAt) : ''
		};
	}

	function getStory(storyId) {
		var id = String(parseInt(storyId, 10) || 0);
		if ('0' === id) {
			return null;
		}
		var map = readStoriesMap();
		if (!map[id]) {
			return null;
		}
		return normalizeStory(map[id], id);
	}

	function setStory(storyId, data) {
		var id = String(parseInt(storyId, 10) || 0);
		if ('0' === id) {
			return null;
		}
		var map = readStoriesMap();
		var prev = map[id] ? normalizeStory(map[id], id) : {};
		var next = normalizeStory(
			{
				storyId: id,
				title: data && data.title != null ? data.title : prev.title,
				phraseIndex: data && data.phraseIndex != null ? data.phraseIndex : prev.phraseIndex,
				step: data && data.step != null ? data.step : prev.step,
				phrasesDone: data && data.phrasesDone != null ? data.phrasesDone : prev.phrasesDone,
				phrasesTotal: data && data.phrasesTotal != null ? data.phrasesTotal : prev.phrasesTotal,
				points: data && data.points != null ? data.points : prev.points,
				finished: data && data.finished != null ? data.finished : prev.finished,
				known: data && data.known != null ? data.known : prev.known,
				target: data && data.target != null ? data.target : prev.target,
				updatedAt: new Date().toISOString()
			},
			id
		);
		map[id] = next;
		writeStoriesMap(map);
		return next;
	}

	function removeStory(storyId) {
		var id = String(parseInt(storyId, 10) || 0);
		if ('0' === id) {
			return;
		}
		var map = readStoriesMap();
		if (map[id]) {
			delete map[id];
			writeStoriesMap(map);
		}
	}

	function getAllStories() {
		var map = readStoriesMap();
		var out = [];
		Object.keys(map).forEach(function (id) {
			out.push(normalizeStory(map[id], id));
		});
		out.sort(function (a, b) {
			return String(b.updatedAt).localeCompare(String(a.updatedAt));
		});
		return out;
	}

	function readCrosswordsMap() {
		try {
			var map = safeParse(window.localStorage.getItem(CROSSWORDS_KEY), {});
			return map && typeof map === 'object' ? map : {};
		} catch (e) {
			return {};
		}
	}

	function writeCrosswordsMap(map) {
		try {
			window.localStorage.setItem(CROSSWORDS_KEY, JSON.stringify(map || {}));
		} catch (e) {
			/* Quota o privacy mode: la partita resta solo in pagina. */
		}
	}

	function normalizeCrossword(entry, crosswordId) {
		entry = entry && typeof entry === 'object' ? entry : {};
		var filled = parseInt(entry.filled, 10);
		var total = parseInt(entry.total, 10);
		if (isNaN(filled)) { filled = 0; }
		if (isNaN(total)) { total = 0; }
		return {
			crosswordId: parseInt(crosswordId || entry.crosswordId, 10) || 0,
			title: entry.title ? String(entry.title) : '',
			cells: Array.isArray(entry.cells) ? entry.cells.map(String) : [],
			filled: Math.max(0, filled),
			total: Math.max(0, total),
			solved: !!entry.solved,
			updatedAt: entry.updatedAt ? String(entry.updatedAt) : ''
		};
	}

	function getCrossword(crosswordId) {
		var id = String(parseInt(crosswordId, 10) || 0);
		if ('0' === id) {
			return null;
		}
		var map = readCrosswordsMap();
		if (!map[id]) {
			return null;
		}
		return normalizeCrossword(map[id], id);
	}

	/** Con la griglia vuota togliamo la voce, per non sprecare spazio. */
	function setCrossword(crosswordId, data) {
		var id = String(parseInt(crosswordId, 10) || 0);
		if ('0' === id) {
			return null;
		}
		var next = normalizeCrossword(data, id);
		next.updatedAt = new Date().toISOString();

		var map = readCrosswordsMap();
		if (map[id] && map[id].solved) {
			next.solved = true;
		}
		if (!next.filled && !next.solved) {
			if (map[id]) {
				delete map[id];
				writeCrosswordsMap(map);
			}
			return null;
		}
		map[id] = next;
		writeCrosswordsMap(map);
		return next;
	}

	function removeCrossword(crosswordId) {
		var id = String(parseInt(crosswordId, 10) || 0);
		if ('0' === id) {
			return;
		}
		var map = readCrosswordsMap();
		if (map[id]) {
			delete map[id];
			writeCrosswordsMap(map);
		}
	}

	function getAllCrosswords() {
		var map = readCrosswordsMap();
		var out = [];
		Object.keys(map).forEach(function (id) {
			out.push(normalizeCrossword(map[id], id));
		});
		out.sort(function (a, b) {
			return String(b.updatedAt).localeCompare(String(a.updatedAt));
		});
		return out;
	}

	/**
	 * Se il browser è avanti rispetto al server, aggiorna cfg prima del render.
	 * Poi allinea sempre localStorage al valore effettivo usato.
	 */
	function hydratePhraseGameCfg(cfg) {
		if (!cfg || cfg.learningModeIsSaved) {
			return false;
		}

		var storyId = cfg.storyId;
		var phrases = cfg.phrases || [];
		var total = phrases.length;
		var local = getStory(storyId);
		var serverIx = parseInt(cfg.savedPhraseIndex, 10);
		var serverDone = parseInt(cfg.savedPhrasesCount, 10);
		if (isNaN(serverIx)) { serverIx = 0; }
		if (isNaN(serverDone)) { serverDone = serverIx; }

		var restored = false;
		if (local && !cfg.isPhraseJump) {
			var localIx = local.phraseIndex;
			var localDone = local.phrasesDone;
			if (local.finished || localDone > serverDone || localIx > serverIx) {
				cfg.savedPhraseIndex = Math.min(Math.max(localIx, localDone), total);
				cfg.savedPhrasesCount = Math.min(Math.max(localDone, localIx), total);
				cfg.savedStep = local.step || 1;
				cfg.gameFinished = !!(local.finished || cfg.savedPhrasesCount >= total);
				restored = true;
			}
		}

		var finalIx = parseInt(cfg.savedPhraseIndex, 10);
		var finalDone = parseInt(cfg.savedPhrasesCount, 10);
		if (isNaN(finalIx)) { finalIx = 0; }
		if (isNaN(finalDone)) { finalDone = finalIx; }
		finalIx = Math.min(Math.max(0, finalIx), total);
		finalDone = Math.min(Math.max(0, finalDone), total);

		var completed = [];
		var show = Math.min(finalIx, total);
		var i;
		for (i = 0; i < show; i++) {
			if (phrases[i]) {
				completed.push({
					target: phrases[i].target || '',
					interface: phrases[i].interface || '',
					index: i
				});
			}
		}
		cfg.completedStoryLines = completed;
		cfg.savedPhraseIndex = finalIx;
		cfg.savedPhrasesCount = finalDone;

		setStory(storyId, {
			title: cfg.storyTitle || (local && local.title) || '',
			phraseIndex: finalIx,
			step: cfg.savedStep || 1,
			phrasesDone: finalDone,
			phrasesTotal: total,
			points: finalDone,
			finished: !!cfg.gameFinished,
			known: cfg.interfaceLangCode || (local && local.known) || '',
			target: cfg.targetLangCode || (local && local.target) || ''
		});

		return restored;
	}

	function persistPhraseProgress(storyId, data) {
		return setStory(storyId, data || {});
	}

	function byteLength(str) {
		if (window.TextEncoder) {
			return new window.TextEncoder().encode(String(str)).length;
		}
		return unescape(encodeURIComponent(String(str))).length;
	}

	function measureKey(key) {
		var value = '';
		try {
			value = window.localStorage.getItem(key);
		} catch (e) {
			return null;
		}
		if (value === null) {
			return null;
		}
		return {
			key: key,
			bytes: byteLength(key) + byteLength(value),
			value: value
		};
	}

	function measureStorage() {
		var items = [];
		var totalBytes = 0;
		var i;
		for (i = 0; i < KNOWN_KEYS.length; i++) {
			var row = measureKey(KNOWN_KEYS[i]);
			if (row) {
				items.push(row);
				totalBytes += row.bytes;
			}
		}
		return {
			items: items,
			totalBytes: totalBytes,
			totalKb: Math.round((totalBytes / 1024) * 100) / 100
		};
	}

	function readSimple(key) {
		try {
			return window.localStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function getName() {
		var raw = readSimple(NAME_KEY);
		return raw ? String(raw).trim() : '';
	}

	function setName(name) {
		name = String(name || '').trim().slice(0, 60);
		try {
			if (name) {
				window.localStorage.setItem(NAME_KEY, name);
			} else {
				window.localStorage.removeItem(NAME_KEY);
			}
		} catch (e) {
			return '';
		}
		return name;
	}

	function catalogFiles() {
		var c = window.llmUserAvatars || {};
		return Array.isArray(c.files) ? c.files : [];
	}

	function getAvatar() {
		var raw = readSimple(AVATAR_KEY);
		return raw ? String(raw).trim() : '';
	}

	function setAvatar(file) {
		file = String(file || '').trim();
		try {
			if (file) {
				window.localStorage.setItem(AVATAR_KEY, file);
			} else {
				window.localStorage.removeItem(AVATAR_KEY);
			}
		} catch (e) {
			return '';
		}
		return file;
	}

	function pickRandomAvatar(exclude) {
		var files = catalogFiles();
		var pool = [];
		var i;
		exclude = String(exclude || '');
		for (i = 0; i < files.length; i++) {
			if (files[i] && files[i] !== exclude) {
				pool.push(files[i]);
			}
		}
		if (!pool.length) {
			pool = files.slice();
		}
		if (!pool.length) {
			return '';
		}
		return pool[Math.floor(Math.random() * pool.length)];
	}

	function ensureAvatar() {
		var files = catalogFiles();
		var current = getAvatar();
		if (current && files.indexOf(current) !== -1) {
			return current;
		}
		var picked = pickRandomAvatar(current);
		if (picked) {
			setAvatar(picked);
		}
		return picked;
	}

	function readPlayTimeMap() {
		try {
			var map = safeParse(window.localStorage.getItem(PLAY_TIME_KEY), {});
			return map && typeof map === 'object' ? map : {};
		} catch (e) {
			return {};
		}
	}

	function writePlayTimeMap(map) {
		try {
			window.localStorage.setItem(PLAY_TIME_KEY, JSON.stringify(map || {}));
		} catch (e) {
			/* Quota o privacy mode. */
		}
	}

	function getPlaySeconds(storyId) {
		var id = parseInt(storyId, 10) || 0;
		if (!id) {
			return 0;
		}
		var map = readPlayTimeMap();
		return Math.max(0, parseInt(map[String(id)], 10) || 0);
	}

	function addPlaySeconds(storyId, delta) {
		var id = parseInt(storyId, 10) || 0;
		var add = Math.max(0, parseInt(delta, 10) || 0);
		if (!id || !add) {
			return getPlaySeconds(id);
		}
		var map = readPlayTimeMap();
		var key = String(id);
		var next = Math.max(0, (parseInt(map[key], 10) || 0) + add);
		map[key] = next;
		writePlayTimeMap(map);
		return next;
	}

	function sumPlaySeconds() {
		var map = readPlayTimeMap();
		var total = 0;
		Object.keys(map).forEach(function (key) {
			total += Math.max(0, parseInt(map[key], 10) || 0);
		});
		return total;
	}

	function collectSnapshot() {
		var stories = getAllStories();
		var phrasesDone = 0;
		var points = 0;
		var finished = 0;
		stories.forEach(function (s) {
			phrasesDone += s.phrasesDone || 0;
			points += s.points || 0;
			if (s.finished) {
				finished += 1;
			}
		});
		var crosswords = getAllCrosswords();
		var crosswordsSolved = 0;
		var lettersFilled = 0;
		crosswords.forEach(function (c) {
			lettersFilled += c.filled || 0;
			if (c.solved) {
				crosswordsSolved += 1;
			}
		});
		var storage = measureStorage();
		return {
			name: getName(),
			knownLang: readSimple('llm_interface_lang') || '',
			learningLang: readSimple('llm_learning_lang') || '',
			learningMode: readSimple('llm_learning_mode') || '',
			learningOptions: readSimple('llm_learning_options') || '',
			stories: stories,
			crosswords: crosswords,
			totals: {
				stories: stories.length,
				finished: finished,
				phrasesDone: phrasesDone,
				points: points,
				crosswords: crosswords.length,
				crosswordsSolved: crosswordsSolved,
				crosswordLetters: lettersFilled,
				playSeconds: sumPlaySeconds(),
				playMinutes: Math.floor(sumPlaySeconds() / 60)
			},
			storage: storage
		};
	}

	window.llmGuestBrowserStore = {
		STORIES_KEY: STORIES_KEY,
		CROSSWORDS_KEY: CROSSWORDS_KEY,
		PLAY_TIME_KEY: PLAY_TIME_KEY,
		NAME_KEY: NAME_KEY,
		AVATAR_KEY: AVATAR_KEY,
		KNOWN_KEYS: KNOWN_KEYS,
		getName: getName,
		setName: setName,
		getAvatar: getAvatar,
		setAvatar: setAvatar,
		pickRandomAvatar: pickRandomAvatar,
		ensureAvatar: ensureAvatar,
		getStory: getStory,
		setStory: setStory,
		removeStory: removeStory,
		getAllStories: getAllStories,
		getCrossword: getCrossword,
		setCrossword: setCrossword,
		removeCrossword: removeCrossword,
		getAllCrosswords: getAllCrosswords,
		getPlaySeconds: getPlaySeconds,
		addPlaySeconds: addPlaySeconds,
		sumPlaySeconds: sumPlaySeconds,
		hydratePhraseGameCfg: hydratePhraseGameCfg,
		persistPhraseProgress: persistPhraseProgress,
		measureStorage: measureStorage,
		collectSnapshot: collectSnapshot
	};
})(window);
