/**
 * Motore Azure AI Speech. Altri motori possono copiare questa interfaccia start/stop.
 * Con assessPronunciation: Pronunciation Assessment (punteggi + parole).
 */
(function (window) {
	'use strict';

	var recognizer = null;
	var starting = false;
	var lastAssessment = null;
	var sessionGen = 0;

	function loadSdk(url, done, fail) {
		if (window.SpeechSDK) {
			done();
			return;
		}
		var s = document.createElement('script');
		s.src = url;
		s.async = true;
		s.onload = function () {
			if (window.SpeechSDK) {
				done();
			} else {
				fail('sdk');
			}
		};
		s.onerror = function () {
			fail('sdk');
		};
		document.head.appendChild(s);
	}

	function fetchToken(opts, done, fail) {
		var body = new window.FormData();
		body.append('action', 'llm_stt_token');
		body.append('nonce', opts.nonce || '');
		body.append('engine', 'azure');
		window.fetch(opts.ajaxUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin'
		}).then(function (res) {
			return res.json();
		}).then(function (json) {
			if (json && json.success && json.data && json.data.token) {
				done(json.data);
				return;
			}
			fail('token');
		}).catch(function () {
			fail('token');
		});
	}

	function numOrNull(v) {
		if (v === undefined || v === null || v === '') {
			return null;
		}
		var n = Number(v);
		return isFinite(n) ? n : null;
	}

	function parseAssessment(SpeechSDK, result) {
		var out = {
			pronunciation: null,
			accuracy: null,
			fluency: null,
			completeness: null,
			prosody: null,
			words: []
		};
		if (!result) {
			return out;
		}
		try {
			var pa = SpeechSDK.PronunciationAssessmentResult.fromResult(result);
			if (pa) {
				out.pronunciation = numOrNull(pa.pronunciationScore);
				out.accuracy = numOrNull(pa.accuracyScore);
				out.fluency = numOrNull(pa.fluencyScore);
				out.completeness = numOrNull(pa.completenessScore);
				out.prosody = numOrNull(pa.prosodyScore);
			}
		} catch (ePa) { /* ignore */ }
		try {
			var raw = result.properties && result.properties.getProperty
				? result.properties.getProperty(SpeechSDK.PropertyId.SpeechServiceResponse_JsonResult)
				: '';
			var json = raw ? JSON.parse(raw) : null;
			var nbest = json && json.NBest && json.NBest[0];
			var paJson = nbest && nbest.PronunciationAssessment;
			if (paJson) {
				if (out.pronunciation == null) {
					out.pronunciation = numOrNull(paJson.PronScore);
				}
				if (out.accuracy == null) {
					out.accuracy = numOrNull(paJson.AccuracyScore);
				}
				if (out.fluency == null) {
					out.fluency = numOrNull(paJson.FluencyScore);
				}
				if (out.completeness == null) {
					out.completeness = numOrNull(paJson.CompletenessScore);
				}
				if (out.prosody == null) {
					out.prosody = numOrNull(paJson.ProsodyScore);
				}
			}
			var words = nbest && nbest.Words;
			if (words && words.length) {
				out.words = words.map(function (w) {
					var wpa = (w && w.PronunciationAssessment) || {};
					return {
						word: (w && w.Word) || '',
						accuracy: numOrNull(wpa.AccuracyScore),
						errorType: String(wpa.ErrorType || 'None')
					};
				});
			}
		} catch (eJson) { /* ignore */ }
		return out;
	}

	function assessmentWeight(a) {
		if (!a) {
			return -1;
		}
		var words = (a.words && a.words.length) || 0;
		var complete = a.completeness != null ? a.completeness : 0;
		return (words * 1000) + complete;
	}

	function rememberAssessment(SpeechSDK, result) {
		var next = parseAssessment(SpeechSDK, result);
		if (assessmentWeight(next) >= assessmentWeight(lastAssessment)) {
			lastAssessment = next;
		}
	}

	function stopRecognizer(rec, done) {
		var finished = false;
		function once() {
			if (finished) {
				return;
			}
			finished = true;
			try { rec.close(); } catch (eClose) { /* ignore */ }
			if (typeof done === 'function') {
				done();
			}
		}
		try {
			rec.stopContinuousRecognitionAsync(once, once);
		} catch (eStop) {
			once();
		}
	}

	function stop(done) {
		starting = false;
		if (!recognizer) {
			if (typeof done === 'function') {
				done();
			}
			return;
		}
		var rec = recognizer;
		recognizer = null;
		stopRecognizer(rec, done);
	}

	function start(opts) {
		opts = opts || {};
		sessionGen += 1;
		var myGen = sessionGen;
		stop();
		starting = true;
		lastAssessment = null;
		loadSdk(opts.sdkUrl, function () {
			if (!starting || myGen !== sessionGen) {
				return;
			}
			fetchToken(opts, function (data) {
				if (!starting || myGen !== sessionGen) {
					return;
				}
				try {
					var SpeechSDK = window.SpeechSDK;
					var cfg = SpeechSDK.SpeechConfig.fromAuthorizationToken(data.token, data.region || opts.region);
					cfg.speechRecognitionLanguage = opts.lang || 'en-US';
					if (opts.assessPronunciation && SpeechSDK.OutputFormat) {
						cfg.outputFormat = SpeechSDK.OutputFormat.Detailed;
					}
					var audio = SpeechSDK.AudioConfig.fromDefaultMicrophoneInput();
					var rec = new SpeechSDK.SpeechRecognizer(cfg, audio);
					if (opts.assessPronunciation) {
						if (!SpeechSDK.PronunciationAssessmentConfig) {
							throw new Error('no-pronunciation');
						}
						var pron = new SpeechSDK.PronunciationAssessmentConfig(
							String(opts.referenceText || ''),
							SpeechSDK.PronunciationAssessmentGradingSystem.HundredMark,
							SpeechSDK.PronunciationAssessmentGranularity.Word,
							true
						);
						try {
							pron.enableProsodyAssessment = true;
						} catch (ePros) { /* ignore */ }
						pron.applyTo(rec);
					}
					recognizer = rec;
					rec.recognizing = function (s, e) {
						if (myGen !== sessionGen || !e || !e.result || !opts.onInterim) {
							return;
						}
						opts.onInterim(e.result.text || '');
					};
					rec.recognized = function (s, e) {
						if (myGen !== sessionGen || !e || !e.result || e.result.reason !== SpeechSDK.ResultReason.RecognizedSpeech) {
							return;
						}
						if (opts.assessPronunciation) {
							rememberAssessment(SpeechSDK, e.result);
							if (opts.onPronunciation && lastAssessment) {
								opts.onPronunciation(lastAssessment);
							}
						}
						if (opts.onFinal) {
							opts.onFinal(e.result.text || '');
						}
					};
					rec.canceled = function (s, e) {
						if (myGen !== sessionGen) {
							return;
						}
						var code = (e && String(e.errorDetails || '').toLowerCase().indexOf('permission') !== -1)
							? 'not-allowed'
							: 'canceled';
						stop();
						if (opts.onError) {
							opts.onError(code);
						}
					};
					rec.sessionStarted = function () {
						if (myGen !== sessionGen) {
							return;
						}
						if (opts.onStart) {
							opts.onStart();
						}
					};
					rec.startContinuousRecognitionAsync(
						function () { /* started */ },
						function () {
							if (myGen !== sessionGen) {
								return;
							}
							stop();
							if (opts.onError) {
								opts.onError('start');
							}
						}
					);
				} catch (err) {
					if (myGen !== sessionGen) {
						return;
					}
					stop();
					if (opts.onError) {
						opts.onError('start');
					}
				}
			}, function (code) {
				if (myGen !== sessionGen) {
					return;
				}
				starting = false;
				if (opts.onError) {
					opts.onError(code || 'token');
				}
			});
		}, function (code) {
			if (myGen !== sessionGen) {
				return;
			}
			starting = false;
			if (opts.onError) {
				opts.onError(code || 'sdk');
			}
		});
	}

	window.llmSttAzure = {
		start: start,
		stop: stop,
		getLastAssessment: function () {
			return lastAssessment;
		}
	};
})(window);
