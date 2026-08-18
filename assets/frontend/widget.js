/**
 * AI Site Assistant AutoPilot — visitor chat widget.
 *
 * Behaviour follows the patterns visitors now expect from an AI chat window:
 * suggested questions before the free-text field, a typing indicator, a stop
 * control while a reply is generating, errors that offer a retry instead of a
 * dead end, and a log that only auto-scrolls when the reader is at the bottom.
 */
(function () {
	'use strict';

	var cfg = window.AISAAutoPilotWidget || {};
	var apiRoot = cfg.restUrl || '/wp-json/aisaautopilot/v1/';
	var nonce = cfg.nonce || '';
	var t = cfg.i18n || {};

	var convId = null;
	var isOpen = false;
	var isRecording = false;
	var isProcessing = false;
	var mediaRecorder = null;
	var audioChunks = [];
	var synth = window.speechSynthesis;
	var utterance = null;
	var wordSpans = [];
	var settings = {};
	var lastQuestion = '';
	var abortCtl = null;
	var lastFocus = null;

	// ── helpers ──────────────────────────────────────────

	function $(id) { return document.getElementById(id); }
	function qs(s, ctx) { return (ctx || document).querySelector(s); }
	function qsa(s, ctx) { return (ctx || document).querySelectorAll(s); }
	function str(key, fallback) { return t[key] || fallback; }

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		if (attrs) Object.keys(attrs).forEach(function (k) {
			if (k === 'class') node.className = attrs[k];
			else if (k === 'text') node.textContent = attrs[k];
			else if (k === 'html') node.innerHTML = attrs[k];
			else node.setAttribute(k, attrs[k]);
		});
		if (children) children.forEach(function (c) {
			if (c) node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
		});
		return node;
	}

	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s || '';
		return d.innerHTML;
	}

	function api(path, body, signal) {
		return fetch(apiRoot + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			body: JSON.stringify(body || {}),
			signal: signal,
		}).then(function (r) { return r.json(); });
	}

	function upload(path, form) {
		return fetch(apiRoot + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: nonce ? { 'X-WP-Nonce': nonce } : {},
			body: form,
		}).then(function (r) {
			return r.json().then(function (data) {
				if (!r.ok) throw new Error(data.message || str('errorGeneric', 'Something went wrong.'));
				return data;
			});
		});
	}

	function getSettings() {
		return fetch(apiRoot + 'widget/settings', {
			method: 'GET',
			credentials: 'same-origin',
			headers: nonce ? { 'X-WP-Nonce': nonce } : {},
		}).then(function (r) { return r.json(); }).then(function (data) {
			if (data && data.ok) settings = data;
			return settings;
		}).catch(function () { return settings; });
	}

	/** The assistant mark, reusing whatever icon the site configured. */
	function avatarMarkup() {
		var source = qs('.spw-avatar');
		return source ? source.innerHTML : '';
	}

	/**
	 * A source citation as a real anchor.
	 *
	 * Built through the DOM and gated on protocol so a citation can never carry
	 * a javascript: or data: URL into a click handler, whatever the server sent.
	 */
	function sourceLink(source) {
		var label = String((source && source.title) || '').slice(0, 120);
		var link = document.createElement('a');
		link.href = String((source && source.url) || '');

		if (link.protocol !== 'http:' && link.protocol !== 'https:') {
			return el('span', { text: label });
		}

		link.textContent = label;
		link.target = '_blank';
		link.rel = 'noopener noreferrer nofollow';
		return link;
	}

	function clockLabel() {
		try {
			return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		} catch (e) {
			return '';
		}
	}

	// ── scrolling ────────────────────────────────────────

	function atBottom() {
		var msgs = $('spw-msgs');
		if (!msgs) return true;
		return msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 48;
	}

	function scrollToEnd(force) {
		var msgs = $('spw-msgs');
		if (!msgs) return;
		if (force || atBottom()) msgs.scrollTop = msgs.scrollHeight;
		syncJump();
	}

	/**
	 * Reading the scroll position straight after writing it can land mid-layout,
	 * so settle on the next frame before deciding whether to offer the jump.
	 */
	function syncJump() {
		var jump = $('spw-jump');
		if (!jump) return;
		window.requestAnimationFrame(function () { jump.hidden = atBottom(); });
	}

	// ── messages ─────────────────────────────────────────

	function hideWelcome() {
		var welcome = $('spw-welcome');
		if (welcome) welcome.remove();
	}

	/**
	 * Append one turn.
	 *
	 * @param {string} role      'user' | 'assistant'
	 * @param {string} content   Message text.
	 * @param {Array}  sources   Optional [{title,url}].
	 * @param {Object} options   { error: bool, retry: bool }
	 */
	function addMessage(role, content, sources, options) {
		var msgs = $('spw-msgs');
		if (!msgs) return null;
		options = options || {};
		hideWelcome();

		var mine = role === 'user';
		var stick = atBottom();

		var bubble = el('div', {
			class: 'spw-msg spw-msg--' + (mine ? 'user' : 'ai') + (options.error ? ' spw-msg--error' : ''),
		});

		// Assistant replies are split into words so read-aloud can highlight them.
		if (!mine && content && !options.error) {
			content.split(/(\s+)/).forEach(function (w) {
				var span = el('span', { class: 'spw-word', text: w });
				wordSpans.push({ span: span, charLen: w.length });
				bubble.appendChild(span);
			});
		} else {
			bubble.textContent = content;
		}

		if (sources && sources.length) {
			var box = el('div', { class: 'spw-msg__sources' }, [
				el('span', { text: str('sources', 'Sources') }),
			]);
			sources.forEach(function (s) { box.appendChild(sourceLink(s)); });
			bubble.appendChild(box);
		}

		var stack = el('div', { class: 'spw-row__stack' }, [bubble]);

		if (options.retry) {
			var retry = el('button', {
				type: 'button',
				class: 'spw-retry',
				text: str('retry', 'Try again'),
			});
			retry.addEventListener('click', function () {
				row.remove();
				if (lastQuestion) send(lastQuestion, true);
			});
			stack.appendChild(retry);
		}

		stack.appendChild(el('div', { class: 'spw-time', text: clockLabel() }));

		var row = el('div', { class: 'spw-row spw-row--' + (mine ? 'user' : 'ai') }, [
			mine ? null : el('span', { class: 'spw-row__avatar', html: avatarMarkup() }),
			stack,
		]);

		msgs.appendChild(row);
		scrollToEnd(stick || mine);
		return bubble;
	}

	function showTyping() {
		var msgs = $('spw-msgs');
		if (!msgs || $('spw-typing')) return;
		hideWelcome();
		msgs.appendChild(el('div', { id: 'spw-typing', class: 'spw-typing' }, [
			el('span', { class: 'spw-typing__dot' }),
			el('span', { class: 'spw-typing__dot' }),
			el('span', { class: 'spw-typing__dot' }),
		]));
		scrollToEnd(true);
	}

	function hideTyping() {
		var d = $('spw-typing');
		if (d) d.remove();
	}

	// ── busy state ───────────────────────────────────────

	function setBusy(busy) {
		isProcessing = busy;
		var send = $('spw-send');
		if (!send) return;
		send.classList.toggle('spw-send--busy', busy);
		send.setAttribute('aria-label', busy ? str('stop', 'Stop') : str('send', 'Send'));
		send.title = busy ? str('stop', 'Stop') : str('send', 'Send');
	}

	function stopGenerating() {
		if (abortCtl) {
			try { abortCtl.abort(); } catch (e) { /* already settled */ }
			abortCtl = null;
		}
		hideTyping();
		setBusy(false);
	}

	// ── read aloud ───────────────────────────────────────

	function speakText(text) {
		if (!synth || !settings.voice_enabled) return;
		if (utterance) synth.cancel();
		utterance = new SpeechSynthesisUtterance(text);
		utterance.rate = 1.0;
		utterance.pitch = 1.0;
		utterance.lang = document.documentElement.lang || 'en-US';
		if (settings.voice_name) {
			var v = (synth.getVoices() || []).find(function (vv) { return vv.name === settings.voice_name; });
			if (v) utterance.voice = v;
		}
		var charIdx = 0;
		utterance.onboundary = function (e) {
			if (e.name !== 'word') return;
			var idx = e.charIndex;
			wordSpans.forEach(function (ws) {
				ws.span.classList.toggle('spw-word--active', idx >= charIdx && idx < charIdx + ws.charLen);
			});
		};
		utterance.onend = function () {
			wordSpans.forEach(function (ws) { ws.span.classList.remove('spw-word--active'); });
			utterance = null;
		};
		synth.speak(utterance);
	}

	// ── send ─────────────────────────────────────────────

	function send(text, isRetry) {
		text = (text || '').trim();
		if (!text || isProcessing) return;

		var input = qs('.spw-input');
		lastQuestion = text;

		if (!isRetry) {
			addMessage('user', text);
			if (input) { input.value = ''; autoGrow(input); }
		}

		wordSpans = [];
		setBusy(true);
		showTyping();

		abortCtl = window.AbortController ? new AbortController() : null;

		api('widget/ask', { question: text, conversation_id: convId }, abortCtl ? abortCtl.signal : undefined)
			.then(function (data) {
				hideTyping();
				setBusy(false);
				abortCtl = null;

				if (data && data.answer) {
					convId = data.conversation_id || convId;
					addMessage('assistant', data.answer, data.sources);
					if (settings.voice_enabled && settings.voice_auto) speakText(data.answer);
					return;
				}

				// A server-supplied message is more useful than a generic apology.
				addMessage('assistant', (data && data.message) || str('errorGeneric', 'Sorry, I could not answer that.'), null, {
					error: true,
					retry: true,
				});
			})
			.catch(function (error) {
				hideTyping();
				setBusy(false);
				abortCtl = null;
				if (error && error.name === 'AbortError') return;
				// The question is kept so "try again" costs the visitor nothing.
				addMessage('assistant', str('errorNetwork', 'Connection problem. Please try again.'), null, {
					error: true,
					retry: true,
				});
			});
	}

	// ── composer ─────────────────────────────────────────

	function autoGrow(node) {
		if (!node) return;
		node.style.height = 'auto';
		node.style.height = Math.min(node.scrollHeight, 120) + 'px';
	}

	// ── open / close ─────────────────────────────────────

	function setOpen(open) {
		var panel = $('spw-panel');
		var btn = $('spw-toggle');
		if (!panel || !btn) return;

		isOpen = open;
		panel.classList.toggle('spw-panel--open', open);
		btn.classList.toggle('spw-toggle--open', open);
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');

		if (open) {
			lastFocus = document.activeElement;
			var input = qs('.spw-input', panel);
			if (input) input.focus({ preventScroll: true });
			syncJump();
			return;
		}

		if (utterance && synth) synth.cancel();
		if (mediaRecorder && mediaRecorder.state === 'recording') stopRecording();
		stopGenerating();
		// Send focus back where the visitor left it.
		if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
		else btn.focus({ preventScroll: true });
	}

	function toggle() { setOpen(!isOpen); }

	function toggleFull() {
		var panel = $('spw-panel');
		var btn = $('spw-expand');
		if (!panel || !btn) return;
		var full = panel.classList.toggle('spw-panel--full');
		var label = full ? str('exitFullScreen', 'Exit full screen') : str('fullScreen', 'Full screen');
		btn.setAttribute('aria-label', label);
		btn.title = label;
		scrollToEnd(true);
	}

	// ── voice ────────────────────────────────────────────

	function startRecording() {
		if (isRecording) return;
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
			addMessage('assistant', str('voiceUnsupported', 'Voice recording is not supported in this browser.'), null, { error: true });
			return;
		}
		navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
			var preferred = MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
				? 'audio/webm;codecs=opus'
				: '';
			try {
				mediaRecorder = preferred ? new MediaRecorder(stream, { mimeType: preferred }) : new MediaRecorder(stream);
			} catch (e) {
				stream.getTracks().forEach(function (tr) { tr.stop(); });
				addMessage('assistant', str('voiceUnsupported', 'Voice recording is not supported in this browser.'), null, { error: true });
				return;
			}

			audioChunks = [];
			var startedAt = Date.now();
			var stopTimer = null;

			mediaRecorder.ondataavailable = function (e) { if (e.data.size > 0) audioChunks.push(e.data); };
			mediaRecorder.onstop = function () {
				if (stopTimer) window.clearTimeout(stopTimer);
				var blob = new Blob(audioChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
				stream.getTracks().forEach(function (tr) { tr.stop(); });
				sendAudio(blob, Math.max(0, Date.now() - startedAt));
			};

			mediaRecorder.start();
			isRecording = true;
			var mic = $('spw-mic');
			if (mic) {
				mic.classList.add('spw-mic--recording');
				mic.setAttribute('aria-label', str('stopRecording', 'Stop recording'));
			}
			stopTimer = window.setTimeout(function () {
				if (mediaRecorder && mediaRecorder.state === 'recording') stopRecording();
			}, 45000);
		}).catch(function () {
			addMessage('assistant', str('micDenied', 'Microphone access was denied.'), null, { error: true });
		});
	}

	function stopRecording() {
		if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
		isRecording = false;
		var mic = $('spw-mic');
		if (mic) {
			mic.classList.remove('spw-mic--recording');
			mic.setAttribute('aria-label', str('voiceInput', 'Voice input'));
		}
	}

	function sendAudio(blob, durationMs) {
		if (!blob || !blob.size) {
			addMessage('assistant', str('noAudio', 'No audio was captured. Please try again.'), null, { error: true });
			return;
		}
		showTyping();
		var form = new FormData();
		form.append('audio', blob, 'aisaautopilot-widget-command.webm');
		form.append('duration_ms', String(Math.min(45000, Math.max(0, durationMs || 0))));
		form.append('language', document.documentElement.lang || '');

		upload('public/voice', form).then(function (data) {
			hideTyping();
			if (data && data.ok && data.transcript) {
				// The transcript lands in the field so it can be corrected before sending.
				var input = qs('.spw-input');
				if (input) {
					input.value = data.transcript;
					autoGrow(input);
					input.focus();
				}
				return;
			}
			addMessage('assistant', (data && data.message) || str('transcribeFailed', 'Could not transcribe that. Please try again.'), null, { error: true });
		}).catch(function (error) {
			hideTyping();
			addMessage('assistant', error.message || str('transcribeFailed', 'Could not transcribe that. Please try again.'), null, { error: true });
		});
	}

	// ── handoff ──────────────────────────────────────────

	function requestHandoff() {
		var btn = $('spw-handoff-btn');
		if (!btn) return;
		var label = qs('span', btn);
		var original = label ? label.textContent : '';

		btn.disabled = true;
		if (label) label.textContent = cfg.handoffSending || str('sending', 'Sending…');

		var msgs = [];
		qsa('.spw-msg').forEach(function (node) {
			msgs.push({
				role: node.classList.contains('spw-msg--user') ? 'user' : 'assistant',
				content: node.textContent,
			});
		});

		api('widget/handoff', { messages: msgs, conversation_id: convId }).then(function (data) {
			var ok = data && data.ok;
			if (label) label.textContent = ok ? (cfg.handoffSent || str('sent', 'Sent — we will be in touch.')) : str('sendFailed', 'Could not send. Please try again.');
			window.setTimeout(function () {
				btn.disabled = false;
				if (label) label.textContent = original;
			}, 4000);
		}).catch(function () {
			if (label) label.textContent = str('sendFailed', 'Could not send. Please try again.');
			btn.disabled = false;
			window.setTimeout(function () { if (label) label.textContent = original; }, 4000);
		});
	}

	// ── init ─────────────────────────────────────────────

	function init() {
		var btn = $('spw-toggle');
		if (!btn) return;

		btn.setAttribute('aria-expanded', 'false');
		btn.setAttribute('aria-controls', 'spw-panel');
		btn.addEventListener('click', toggle);

		var sendBtn = $('spw-send');
		var input = qs('.spw-input');

		if (sendBtn) {
			sendBtn.addEventListener('click', function () {
				if (isProcessing) { stopGenerating(); return; }
				if (input) send(input.value);
			});
		}

		if (input) {
			input.addEventListener('input', function () { autoGrow(input); });
			input.addEventListener('keydown', function (e) {
				// Enter sends; Shift+Enter starts a new line.
				if (e.key === 'Enter' && !e.shiftKey) {
					e.preventDefault();
					send(input.value);
				}
			});
		}

		var mic = $('spw-mic');
		if (mic) mic.addEventListener('click', function () { isRecording ? stopRecording() : startRecording(); });

		var handoffBtn = $('spw-handoff-btn');
		if (handoffBtn) handoffBtn.addEventListener('click', requestHandoff);

		var closeBtn = $('spw-close');
		if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });

		var expandBtn = $('spw-expand');
		if (expandBtn) expandBtn.addEventListener('click', toggleFull);

		var jump = $('spw-jump');
		if (jump) jump.addEventListener('click', function () { scrollToEnd(true); });

		var msgs = $('spw-msgs');
		if (msgs) msgs.addEventListener('scroll', syncJump);

		// Suggested questions send immediately — one tap to a useful answer.
		qsa('[data-spw-ask]').forEach(function (chip) {
			chip.addEventListener('click', function () { send(chip.getAttribute('data-spw-ask')); });
		});

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape' || !isOpen) return;
			var panel = $('spw-panel');
			if (panel && panel.classList.contains('spw-panel--full')) { toggleFull(); return; }
			setOpen(false);
		});

		getSettings().then(function (s) {
			var delay = parseInt(s.auto_open, 10);
			if (delay > 0) window.setTimeout(function () { if (!isOpen) setOpen(true); }, delay * 1000);
		});

		if (window.location.hash === '#aisaautopilot-chat') setOpen(true);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
