/*
 * llm-guest-browser-store.js — progresso storie e snapshot dati guest in localStorage.
 */
(function (window) {
	'use strict';

	var STORIES_KEY = 'llm_guest_story_progress';
	var NAME_KEY = 'llm_guest_display_name';

	/** Chiavi LLM gestite nel browser (lingue, modalità, progresso, nome). */
	var KNOWN_KEYS = [
		NAME_KEY,
		'llm_interface_lang',
		'llm_learning_lang',
		'llm_learning_mode',
		'llm_learning_options',
		STORIES_KEY
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
		return {
			storyId: id,
			title: entry.title ? String(entry.title) : '',
			phraseIndex: Math.max(0, phraseIndex),
			step: step,
			phrasesDone: Math.max(0, phrasesDone),
			phrasesTotal: Math.max(0, phrasesTotal),
			points: Math.max(0, points),
			finished: !!entry.finished || (phrasesTotal > 0 && phrasesDone >= phrasesTotal),
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
		if (local) {
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
					interface: phrases[i].interface || ''
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
			finished: !!cfg.gameFinished
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
		var storage = measureStorage();
		return {
			name: getName(),
			knownLang: readSimple('llm_interface_lang') || '',
			learningLang: readSimple('llm_learning_lang') || '',
			learningMode: readSimple('llm_learning_mode') || '',
			learningOptions: readSimple('llm_learning_options') || '',
			stories: stories,
			totals: {
				stories: stories.length,
				finished: finished,
				phrasesDone: phrasesDone,
				points: points
			},
			storage: storage
		};
	}

	window.llmGuestBrowserStore = {
		STORIES_KEY: STORIES_KEY,
		NAME_KEY: NAME_KEY,
		KNOWN_KEYS: KNOWN_KEYS,
		getName: getName,
		setName: setName,
		getStory: getStory,
		setStory: setStory,
		removeStory: removeStory,
		getAllStories: getAllStories,
		hydratePhraseGameCfg: hydratePhraseGameCfg,
		persistPhraseProgress: persistPhraseProgress,
		measureStorage: measureStorage,
		collectSnapshot: collectSnapshot
	};
})(window);
