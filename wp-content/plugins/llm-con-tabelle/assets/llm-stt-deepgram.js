/**
 * Motore Deepgram. La chiave resta sul server: il browser invia l'audio
 * a WordPress, che lo passa a Deepgram (la grant JWT spesso è 403 sulle chiavi usage).
 */
(function (window) {
	'use strict';

	var recorder = null;
	var stream = null;
	var starting = false;
	var chunks = [];
	var mime = '';
	var lastOpts = null;
	var flushTimer = null;

	function pickMime() {
		var types = [
			'audio/webm;codecs=opus',
			'audio/webm',
			'audio/mp4'
		];
		var i;
		if (!window.MediaRecorder) {
			return '';
		}
		for (i = 0; i < types.length; i++) {
			if (window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported(types[i])) {
				return types[i];
			}
		}
		return '';
	}

	function stopTracks() {
		if (stream) {
			try {
				stream.getTracks().forEach(function (t) { t.stop(); });
			} catch (e) { /* ignore */ }
			stream = null;
		}
	}

	function stopRecorder() {
		if (recorder) {
			try {
				if (recorder.state !== 'inactive') {
					recorder.stop();
				}
			} catch (e1) { /* ignore */ }
			recorder = null;
		}
	}

	function transcribe(blob, opts, done) {
		if (!blob || blob.size < 80) {
			done('');
			return;
		}
		var body = new window.FormData();
		body.append('action', 'llm_stt_transcribe');
		body.append('nonce', (opts && opts.nonce) || '');
		body.append('lang', (opts && opts.lang) || 'en-US');
		body.append('audio', blob, 'speech.webm');
		window.fetch((opts && opts.ajaxUrl) || '', {
			method: 'POST',
			body: body,
			credentials: 'same-origin'
		}).then(function (res) {
			return res.json();
		}).then(function (json) {
			if (json && json.success && json.data && typeof json.data.text === 'string') {
				done(json.data.text);
				return;
			}
			done(null);
		}).catch(function () {
			done(null);
		});
	}

	function blobFromChunks() {
		if (!chunks.length) {
			return null;
		}
		try {
			return new window.Blob(chunks, { type: mime || 'audio/webm' });
		} catch (e) {
			return null;
		}
	}

	function stop() {
		starting = false;
		if (flushTimer !== null) {
			clearTimeout(flushTimer);
			flushTimer = null;
		}
		stopRecorder();
		stopTracks();
		chunks = [];
	}

	function flush(done) {
		var opts = lastOpts || {};
		var rec = recorder;
		starting = false;
		if (flushTimer !== null) {
			clearTimeout(flushTimer);
			flushTimer = null;
		}
		function finish(text) {
			if (typeof text === 'string' && text && opts.onFinal) {
				opts.onFinal(text);
			} else if (text === null && opts.onError) {
				opts.onError('transcribe');
			}
			stopTracks();
			chunks = [];
			if (typeof done === 'function') {
				done();
			}
		}
		if (!rec || rec.state === 'inactive') {
			transcribe(blobFromChunks(), opts, finish);
			recorder = null;
			return;
		}
		rec.onstop = function () {
			recorder = null;
			transcribe(blobFromChunks(), opts, finish);
		};
		try {
			rec.stop();
		} catch (e) {
			recorder = null;
			transcribe(blobFromChunks(), opts, finish);
		}
	}

	function start(opts) {
		opts = opts || {};
		stop();
		lastOpts = opts;
		starting = true;
		chunks = [];
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
			starting = false;
			if (opts.onError) {
				opts.onError('start');
			}
			return;
		}
		navigator.mediaDevices.getUserMedia({ audio: true }).then(function (mic) {
			if (!starting) {
				mic.getTracks().forEach(function (t) { t.stop(); });
				return;
			}
			stream = mic;
			mime = pickMime();
			var recOpts = mime ? { mimeType: mime } : {};
			var rec;
			try {
				rec = new window.MediaRecorder(mic, recOpts);
			} catch (e) {
				stop();
				if (opts.onError) {
					opts.onError('start');
				}
				return;
			}
			recorder = rec;
			rec.ondataavailable = function (ev) {
				if (ev.data && ev.data.size > 0) {
					chunks.push(ev.data);
				}
			};
			try {
				rec.start(250);
			} catch (e2) {
				stop();
				if (opts.onError) {
					opts.onError('start');
				}
				return;
			}
			if (opts.onStart) {
				opts.onStart();
			}
		}).catch(function () {
			starting = false;
			if (opts.onError) {
				opts.onError('not-allowed');
			}
		});
	}

	window.llmSttDeepgram = {
		start: start,
		stop: stop,
		flush: flush
	};
})(window);
