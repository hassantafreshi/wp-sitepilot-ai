(function () {
	'use strict';

	var cfg = window.SitePilotWidget || {};
	var apiRoot = cfg.restUrl || '/wp-json/sitepilot/v1/';
	var nonce = cfg.nonce || '';
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

	// DOM helpers
	function $(id) { return document.getElementById(id); }
	function qs(s, ctx) { return (ctx || document).querySelector(s); }
	function qsa(s, ctx) { return (ctx || document).querySelectorAll(s); }

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		if (attrs) Object.keys(attrs).forEach(function (k) {
			if (k === 'class') node.className = attrs[k];
			else if (k === 'text') node.textContent = attrs[k];
			else if (k === 'html') node.innerHTML = attrs[k];
			else if (k === 'style' && typeof attrs[k] === 'object') {
				Object.keys(attrs[k]).forEach(function(sk){ node.style[sk] = attrs[k][sk]; });
			}
			else node.setAttribute(k, attrs[k]);
		});
		if (children) children.forEach(function(c){ if(c) node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
		return node;
	}

	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s || '';
		return d.innerHTML;
	}

	function api(path, body) {
		return fetch(apiRoot + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			body: JSON.stringify(body || {}),
		}).then(function(r){ return r.json(); });
	}

	function getSettings() {
		return api('widget/settings').then(function(data){
			if (data && data.settings) settings = data.settings;
			return settings;
		});
	}

	// ── Toggle ──
	function toggle() {
		var panel = $('spw-panel');
		var btn = $('spw-toggle');
		if (!panel || !btn) return;
		isOpen = !isOpen;
		panel.classList.toggle('spw-panel--open', isOpen);
		btn.classList.toggle('spw-toggle--open', isOpen);
		if (isOpen) {
			loadConversation();
			qs('.spw-input', panel).focus();
		} else {
			if (utterance && synth) synth.cancel();
			if (mediaRecorder && mediaRecorder.state === 'recording') stopRecording();
		}
	}

	// ── Messages ──
	function addMessage(role, content, sources) {
		var msgs = $('spw-msgs');
		if (!msgs) return;
		var div = el('div', { class: 'spw-msg spw-msg--' + role });
		div.textContent = content;
		if (role === 'assistant' && content) {
			div.textContent = '';
			var words = content.split(/(\s+)/);
			wordSpans = [];
			words.forEach(function(w){
				var span = el('span', { class: 'spw-word', text: w });
				wordSpans.push({ span: span, charLen: w.length });
				div.appendChild(span);
			});
		}
		if (sources && sources.length) {
			var srcDiv = el('div', { class: 'spw-msg__sources', html: 'Sources: ' + sources.map(function(s){
				return '<a href="' + esc(s.url) + '" target="_blank" rel="noopener">' + esc(s.title) + '</a>';
			}).join(', ') });
			div.appendChild(srcDiv);
		}
		msgs.appendChild(div);
		msgs.scrollTop = msgs.scrollHeight;
		return div;
	}

	function showTyping() {
		var msgs = $('spw-msgs');
		if (!msgs || $('spw-typing')) return;
		var div = el('div', { id: 'spw-typing', class: 'spw-typing' }, [
			el('span', { class: 'spw-typing__dot' }),
			el('span', { class: 'spw-typing__dot' }),
			el('span', { class: 'spw-typing__dot' }),
		]);
		msgs.appendChild(div);
		msgs.scrollTop = msgs.scrollHeight;
	}

	function hideTyping() {
		var d = $('spw-typing');
		if (d) d.remove();
	}

	// ── Karaoke TTS ──
	function speakText(text) {
		if (!synth || !settings.voice_enabled) return;
		if (utterance) synth.cancel();
		utterance = new SpeechSynthesisUtterance(text);
		utterance.rate = 1.0;
		utterance.pitch = 1.0;
		utterance.lang = document.documentElement.lang || 'en-US';
		if (settings.voice_name) {
			var voices = synth.getVoices();
			var v = voices.find(function(vv){ return vv.name === settings.voice_name; });
			if (v) utterance.voice = v;
		}
		var charIdx = 0;
		utterance.onboundary = function(e) {
			if (e.name === 'word') {
				var idx = e.charIndex;
				wordSpans.forEach(function(ws, i){
					ws.span.classList.toggle('spw-word--active', idx >= charIdx && idx < charIdx + ws.charLen);
				});
			}
		};
		utterance.onend = function() {
			wordSpans.forEach(function(ws){ ws.span.classList.remove('spw-word--active'); });
			utterance = null;
		};
		synth.speak(utterance);
	}

	// ── Send ──
	function send(text) {
		if (!text || isProcessing) return;
		var input = qs('.spw-input');
		if (input) input.value = '';
		addMessage('user', text);
		isProcessing = true;
		showTyping();

		api('widget/ask', { question: text, conversation_id: convId }).then(function(data){
			hideTyping();
			isProcessing = false;
			if (data && data.answer) {
				convId = data.conversation_id || convId;
				var msgDiv = addMessage('assistant', data.answer, data.sources);
				if (settings.voice_enabled && settings.voice_auto_play !== false && data.answer) {
					speakText(data.answer);
				}
			} else if (data && data.message) {
				addMessage('assistant', data.message);
			} else {
				addMessage('assistant', 'Sorry, I could not process your request.');
			}
		}).catch(function(){
			hideTyping();
			isProcessing = false;
			addMessage('assistant', 'Connection error. Please try again.');
		});
	}

	function loadConversation() {
		if (!convId) return;
		api('widget/conversation', { id: convId }).then(function(data){
			if (data && data.messages) {
				var msgs = $('spw-msgs');
				if (msgs) msgs.innerHTML = '';
				data.messages.forEach(function(m){
					addMessage(m.role || 'user', m.content, m.sources);
				});
			}
		});
	}

	// ── Voice Recording ──
	function startRecording() {
		if (isRecording) return;
		navigator.mediaDevices.getUserMedia({ audio: true }).then(function(stream){
			mediaRecorder = new MediaRecorder(stream);
			audioChunks = [];
			mediaRecorder.ondataavailable = function(e) {
				if (e.data.size > 0) audioChunks.push(e.data);
			};
			mediaRecorder.onstop = function() {
				var blob = new Blob(audioChunks, { type: 'audio/webm' });
				stream.getTracks().forEach(function(t){ t.stop(); });
				sendAudio(blob);
			};
			mediaRecorder.start();
			isRecording = true;
			var mic = $('spw-mic');
			if (mic) mic.classList.add('spw-mic--recording');
		}).catch(function(){
			addMessage('assistant', 'Microphone access denied. Please allow microphone access in your browser settings.');
		});
	}

	function stopRecording() {
		if (mediaRecorder && mediaRecorder.state === 'recording') {
			mediaRecorder.stop();
		}
		isRecording = false;
		var mic = $('spw-mic');
		if (mic) mic.classList.remove('spw-mic--recording');
	}

	function sendAudio(blob) {
		showTyping();
		var reader = new FileReader();
		reader.onload = function() {
			var base64 = reader.result.split(',')[1];
			api('voice/transcribe', { audio: base64, format: 'webm' }).then(function(data){
				hideTyping();
				if (data && data.text) {
					send(data.text);
				} else {
					addMessage('assistant', 'Could not transcribe audio. Please try again.');
				}
			}).catch(function(){
				hideTyping();
				addMessage('assistant', 'Transcription failed.');
			});
		};
		reader.readAsDataURL(blob);
	}

	// ── Handoff ──
	function showHandoffForm() {
		var btn = $('spw-handoff-btn');
		if (btn) btn.textContent = 'Sending...';
		btn.disabled = true;

		var msgs = [];
		qsa('.spw-msg').forEach(function(el){
			msgs.push({
				role: el.classList.contains('spw-msg--user') ? 'user' : 'assistant',
				content: el.textContent,
			});
		});

		api('widget/handoff', { messages: msgs, conversation_id: convId }).then(function(data){
			btn.textContent = data && data.ok ? 'Sent! We will get back to you.' : 'Send failed. Try email instead.';
			setTimeout(function(){ btn.disabled = false; }, 3000);
		}).catch(function(){
			btn.textContent = 'Error. Please try again.';
			btn.disabled = false;
		});
	}

	// ── Init ──
	function init() {
		var btn = $('spw-toggle');
		if (!btn) return;

		btn.addEventListener('click', toggle);

		var sendBtn = $('spw-send');
		var input = qs('.spw-input');
		if (sendBtn && input) {
			sendBtn.addEventListener('click', function(){ send(input.value); });
			input.addEventListener('keydown', function(e) {
				if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); }
			});
		}

		var mic = $('spw-mic');
		if (mic) {
			mic.addEventListener('click', function() {
				if (isRecording) stopRecording(); else startRecording();
			});
		}

		var handoffBtn = $('spw-handoff-btn');
		if (handoffBtn) handoffBtn.addEventListener('click', showHandoffForm);

		getSettings().then(function(s) {
			if (s.auto_open) {
				setTimeout(toggle, (s.auto_open_delay || 15) * 1000);
			}
		});

		// Auto-open if user showed intent to chat
		if (window.location.hash === '#sitepilot-chat') toggle();
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
