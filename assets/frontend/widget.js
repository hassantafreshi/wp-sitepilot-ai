/**
 * AI Site Assistant AutoPilot — visitor chat widget.
 *
 * Follows the Front Chat Widget mockup: a launcher with a one-time nudge, a
 * docked panel whose greeting is the first message, suggested questions as
 * chips, sources under each answer, an in-thread "bring in a person" card, and
 * a centred full-screen view with a popular-questions column. Voice stays
 * transcript-first: a recorded clip becomes editable text and is never sent on
 * its own.
 */
(function () {
	'use strict';

	var cfg = window.AISAAutoPilotWidget || {};
	var apiRoot = cfg.restUrl || '/wp-json/aisaautopilot/v1/';
	var nonce = cfg.nonce || '';
	var t = cfg.i18n || {};

	// Voice settings come from the same rendered page as the markup, so a visitor who never
	// opens the chat costs the site nothing: no request is sent on page load. wp_localize_script
	// turns a top-level false into "", so both are read as truthiness.
	var voiceEnabled = !! cfg.voiceEnabled;
	var voiceAuto = !! cfg.voiceAuto;

	// An immediate "try again" only helps when the failure was transient. Sending the same
	// request again cannot pass an hourly limit, a spent budget or a length check.
	var NO_RETRY = ['rate_limited', 'usage_cap_reached', 'question_too_long', 'empty', 'widget_disabled', 'assistant_not_configured'];

	// The recorded conversation's id, kept for this browser tab's session so moving to another
	// page continues the same recorded conversation instead of starting a new one.
	var CONV_KEY = 'aisaautopilot_conversation';
	var convId = readConvId();
	// The last few answered turns, sent with each question so a follow-up keeps its subject.
	// Kept in memory only: a page load starts a fresh conversation.
	var turns = [];
	var MAX_TURNS = 6;
	var isOpen = false;
	var isFull = false;
	var isRecording = false;
	var isProcessing = false;
	var mediaRecorder = null;
	var audioChunks = [];
	var synth = window.speechSynthesis;
	var utterance = null;
	var wordSpans = [];
	var lastQuestion = '';
	var abortCtl = null;
	var lastFocus = null;
	var scrollLock = null;
	var nudgeTimer = null;
	var defaultPlaceholder = '';
	var handoffCards = 0;
	var recTimer = null;
	// Same 640px threshold as the stylesheet: below it the open panel is a sheet that covers
	// the whole screen, so it behaves like full screen does.
	var sheetQuery = window.matchMedia ? window.matchMedia('(max-width: 640px)') : null;

	var BOOK_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H19v15H6.5A2.5 2.5 0 0 0 4 20.5ZM4 5.5v15"/></svg>';

	// ── helpers ──────────────────────────────────────────

	function $(id) { return document.getElementById(id); }
	function qs(s, ctx) { return (ctx || document).querySelector(s); }
	function qsa(s, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(s)); }
	function str(key, fallback) { return t[key] || fallback; }
	function root() { return qs('.spw-root'); }

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

	function storage(key, value) {
		try {
			if (value === undefined) return window.sessionStorage.getItem(key);
			window.sessionStorage.setItem(key, value);
		} catch (e) { /* private mode or blocked storage: the nudge simply shows again */ }
		return null;
	}

	/**
	 * An anchor for a URL the server (or a model) wrote, or null when it is not a web address.
	 *
	 * The protocol is read back from the element, so a javascript: or data: URL can never
	 * reach a click handler however it was spelled.
	 */
	function safeLink(url) {
		var link = document.createElement('a');
		link.href = String(url || '');
		if (link.protocol !== 'http:' && link.protocol !== 'https:') return null;
		link.target = '_blank';
		link.rel = 'noopener noreferrer nofollow';
		return link;
	}

	/** A source citation as a real anchor, falling back to its plain label. */
	function sourceLink(source) {
		var label = String((source && source.title) || '').slice(0, 120);
		var link = safeLink((source && source.url) || '');
		if (!link) return el('span', { text: label });
		link.textContent = label;
		return link;
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
	 *
	 * A focused button that hides drops keyboard focus to <body>: out of the panel, and
	 * out of the modal trap in full screen or on the phone sheet. That happened when Tab
	 * reached the jump while the thread was still scrolling to the end, or after a click
	 * on it. Focus moves to the composer first, the next control in the Tab order.
	 */
	function syncJump() {
		var jump = $('spw-jump');
		if (!jump) return;
		window.requestAnimationFrame(function () {
			var hide = atBottom();
			if (hide && document.activeElement === jump) {
				var input = $('spw-input');
				if (input) input.focus({ preventScroll: true });
			}
			jump.hidden = hide;
		});
	}

	// ── messages ─────────────────────────────────────────

	/** New turns go above the suggestion chips, which stay at the end of the log. */
	function appendToLog(node) {
		var msgs = $('spw-msgs');
		if (!msgs) return;
		var chips = $('spw-chips');
		if (chips && chips.parentNode === msgs) msgs.insertBefore(node, chips);
		else msgs.appendChild(node);
	}

	// ── the assistant's answer ───────────────────────────

	/** Bold, italic, inline code and [label](url) inside one line of an answer. */
	var INLINE_MD = /(\*\*[^*\n]+\*\*|__[^_\n]+__|\*[^*\n]+\*|_[^_\n]+_|`[^`\n]+`|\[[^\]\n]+\]\([^)\s]+\))/;

	/**
	 * Render an answer written in Markdown into the bubble and return the plain text it reads as.
	 *
	 * The engine is prompted for short Markdown, so the visitor used to see the raw markers
	 * ("**Opening hours**", "- item"). Only the small subset the engine produces is understood:
	 * paragraphs, bullet and numbered lists, headings, bold, italic, inline code and links.
	 * Anything else stays literal text.
	 *
	 * Every node is built with the DOM API and links are gated on protocol, so nothing a model
	 * writes can become markup or a javascript: URL. Words are wrapped in .spw-word in reading
	 * order and the same order builds the returned string, so read-aloud highlighting lines up
	 * with the characters speech synthesis reports.
	 */
	function renderAnswer(bubble, text) {
		var plain = '';

		var addWords = function (parent, chunk) {
			chunk.split(/(\s+)/).forEach(function (part) {
				if (!part) return;
				var span = el('span', { class: 'spw-word', text: part });
				wordSpans.push({ span: span, start: plain.length, end: plain.length + part.length });
				plain += part;
				parent.appendChild(span);
			});
		};

		var addInline = function (parent, chunk) {
			chunk.split(INLINE_MD).forEach(function (part) {
				if (!part) return;
				var wrap = null;
				var inner = part;
				if (/^(\*\*[^*\n]+\*\*|__[^_\n]+__)$/.test(part)) { wrap = el('strong'); inner = part.slice(2, -2); }
				else if (/^(\*[^*\n]+\*|_[^_\n]+_)$/.test(part)) { wrap = el('em'); inner = part.slice(1, -1); }
				else if (/^`[^`\n]+`$/.test(part)) { wrap = el('code', { class: 'spw-code' }); inner = part.slice(1, -1); }
				else if (/^\[[^\]\n]+\]\([^)\s]+\)$/.test(part)) {
					var cut = part.lastIndexOf('](');
					wrap = safeLink(part.slice(cut + 2, -1));
					inner = part.slice(1, cut);
					// Not a web address: the label and its markers stay as the text they are.
					if (!wrap) inner = part;
				}
				addWords(wrap || parent, inner);
				if (wrap) parent.appendChild(wrap);
			});
		};

		// One newline between blocks keeps the spoken string in step with the spans.
		var blockBreak = function () { if ('' !== plain) plain += '\n'; };

		var list = null;
		var para = null;

		String(text || '').replace(/\r\n?/g, '\n').split('\n').forEach(function (line) {
			var trimmed = line.trim();
			if ('' === trimmed) { list = null; para = null; return; }

			var bullet = /^[-*+]\s+(.*)$/.exec(trimmed);
			var numbered = /^\d{1,3}[.)]\s+(.*)$/.exec(trimmed);
			if (bullet || numbered) {
				var tag = bullet ? 'ul' : 'ol';
				if (!list || list.tagName.toLowerCase() !== tag) {
					para = null;
					list = el(tag, { class: 'spw-md-list' });
					bubble.appendChild(list);
				}
				blockBreak();
				var item = el('li');
				addInline(item, (bullet || numbered)[1]);
				list.appendChild(item);
				return;
			}

			list = null;
			var heading = /^#{1,6}\s+(.*)$/.exec(trimmed);
			if (heading) {
				para = null;
				blockBreak();
				var head = el('p', { class: 'spw-md-h' });
				addInline(head, heading[1]);
				bubble.appendChild(head);
				return;
			}

			if (!para) {
				blockBreak();
				para = el('p', { class: 'spw-md-p' });
				bubble.appendChild(para);
			} else {
				// A single newline inside a paragraph stays the line break the engine wrote.
				para.appendChild(document.createElement('br'));
				plain += '\n';
			}
			addInline(para, trimmed);
		});

		if ('' === plain) {
			bubble.textContent = String(text || '');
			return String(text || '');
		}
		return plain;
	}

	/**
	 * The engine could not tell which topic the question is about and named the candidates.
	 *
	 * These labels are written by the model, so pressing one only fills the composer: the
	 * visitor reads the question that will be asked and sends it themselves. The chips above
	 * are different — the site owner wrote them, so they are sent on the first tap (D-24).
	 */
	function addClarifyOptions(stack, options) {
		var group = el('div', {
			class: 'spw-clarify',
			role: 'group',
			'data-spw-clarify': '',
			'aria-label': str('clarifyLabel', 'Choose what you mean'),
		});
		options.slice(0, 5).forEach(function (label) {
			var text = String(label == null ? '' : label).trim().slice(0, 120);
			if (!text) return;
			var option = el('button', {
				type: 'button',
				class: 'spw-chip spw-clarify__option',
				dir: 'auto',
				'data-spw-clarify-option': text,
				text: text,
			});
			option.addEventListener('click', function () {
				var input = $('spw-input');
				if (!input) return;
				input.value = text;
				autoGrow(input);
				input.focus();
			});
			group.appendChild(option);
		});
		if (group.firstChild) stack.appendChild(group);
	}

	/**
	 * Append one turn.
	 *
	 * @param {string} role      'user' | 'assistant'
	 * @param {string} content   Message text.
	 * @param {Array}  sources   Optional [{title,url}].
	 * @param {Object} options   { error: bool, retry: bool }
	 * @return {Object|null}     { stack, bubble, text } — text is the plain reading of the bubble.
	 */
	function addMessage(role, content, sources, options) {
		var msgs = $('spw-msgs');
		if (!msgs) return null;
		options = options || {};

		var mine = role === 'user';
		var stick = atBottom();

		var bubble = el('div', {
			class: 'spw-msg spw-msg--' + (mine ? 'user' : 'ai') + (options.error ? ' spw-msg--error' : ''),
			// A Latin answer inside a Persian page (or the other way round) reads correctly
			// only when the bubble carries the direction of its own text.
			dir: 'auto',
		});

		var plain = String(content == null ? '' : content);
		// An answer arrives as Markdown and is rendered; everything else is plain text.
		if (!mine && content && !options.error) {
			plain = renderAnswer(bubble, content);
		} else {
			bubble.textContent = content;
		}

		var stack = el('div', { class: 'spw-row__stack' }, [bubble]);

		if (sources && sources.length) {
			var line = el('div', { class: 'spw-source' }, [el('span', { class: 'spw-source__icon', html: BOOK_ICON }), el('span', { text: str('from', 'From') })]);
			sources.forEach(function (s, index) {
				if (index > 0) line.appendChild(document.createTextNode(', '));
				line.appendChild(sourceLink(s));
			});
			stack.appendChild(line);
		}

		var row = el('div', { class: 'spw-row spw-row--' + (mine ? 'user' : 'ai') }, [stack]);

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

		// The transcript the handoff card sends reads this instead of the rendered DOM, where
		// paragraphs and list items would run into each other (a bubble's textContent has no
		// block boundaries). An expando keeps it out of the markup and out of any other script.
		bubble.spwPlain = plain;

		appendToLog(row);
		scrollToEnd(stick || mine);
		return { stack: stack, bubble: bubble, text: plain };
	}

	/**
	 * A short note about the conversation itself, not a turn in it.
	 *
	 * Notes stay out of the transcript the handoff card sends, because nobody typed them.
	 */
	function addNote(kind, text) {
		appendToLog(el('p', { class: 'spw-note', 'data-spw-note': kind, dir: 'auto', text: text }));
		scrollToEnd(true);
	}

	function showTyping() {
		if ($('spw-typing')) return;
		appendToLog(el('div', { id: 'spw-typing', class: 'spw-typing', 'aria-label': str('typing', 'The assistant is writing a reply') }, [
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

	/**
	 * The site owner's suggested questions belong to a thread that is waiting for the visitor.
	 *
	 * They are out of the way while the assistant is writing, and while a handoff card still
	 * waits for an answer: that card asks the visitor to decide something, and the mockup's
	 * handoff state shows no chips either (F-10). Once the card is sent or dismissed the chips
	 * come back, because the assistant keeps working while the team reads the transcript.
	 */
	function syncChips() {
		var chips = $('spw-chips');
		if (!chips) return;
		chips.hidden = isProcessing || null !== qs('#spw-msgs .spw-handoff:not(.is-sent)');
	}

	function setBusy(busy) {
		isProcessing = busy;
		syncChips();
		var sendBtn = $('spw-send');
		if (!sendBtn) return;
		sendBtn.classList.toggle('spw-send--busy', busy);
		// "Stop" would promise more than the button does: the request is already with the
		// site, so only this browser stops waiting for it.
		var label = busy ? str('stopWaiting', 'Stop waiting for the reply') : str('send', 'Send');
		sendBtn.setAttribute('aria-label', label);
		sendBtn.title = label;
	}

	/**
	 * Stop waiting for the answer.
	 *
	 * Aborting the request only ends this browser's wait. The site has the question and
	 * finishes the answer, so when the visitor asked for it a note says so instead of
	 * letting the silent thread imply the work was cancelled.
	 *
	 * @param {boolean} explain Whether the visitor pressed the button (rather than closing the chat).
	 */
	function stopGenerating(explain) {
		var wasWaiting = !! abortCtl;
		if (abortCtl) {
			try { abortCtl.abort(); } catch (e) { /* already settled */ }
			abortCtl = null;
		}
		hideTyping();
		setBusy(false);
		if (explain && wasWaiting) {
			addNote('stopped', str('stoppedWaiting', 'You stopped waiting. The site may still finish this answer, but it will not appear here.'));
		}
	}

	// ── read aloud ───────────────────────────────────────

	function speakText(text) {
		if (!synth || !voiceEnabled) return;
		if (utterance) synth.cancel();
		utterance = new SpeechSynthesisUtterance(text);
		utterance.rate = 1.0;
		utterance.pitch = 1.0;
		utterance.lang = document.documentElement.lang || 'en-US';
		if (cfg.voiceName) {
			var v = (synth.getVoices() || []).find(function (vv) { return vv.name === cfg.voiceName; });
			if (v) utterance.voice = v;
		}
		// Each span knows where it starts in the spoken string, so the word being read is the
		// word that lights up. (Before T10 every span was compared against offset 0, so only
		// the first word ever highlighted.)
		utterance.onboundary = function (e) {
			if (e.name !== 'word') return;
			var idx = e.charIndex;
			wordSpans.forEach(function (ws) {
				ws.span.classList.toggle('spw-word--active', idx >= ws.start && idx < ws.end);
			});
		};
		utterance.onend = function () {
			wordSpans.forEach(function (ws) { ws.span.classList.remove('spw-word--active'); });
			utterance = null;
		};
		synth.speak(utterance);
	}

	// Storage can be blocked (private mode, site data off): then the id lives for this page only.
	function readConvId() {
		try {
			var stored = window.sessionStorage.getItem(CONV_KEY);
			return stored && /^[0-9a-f-]{36}$/i.test(stored) ? stored : null;
		} catch (e) {
			return null;
		}
	}

	function saveConvId(id) {
		try {
			if (id) window.sessionStorage.setItem(CONV_KEY, String(id));
		} catch (e) { /* see readConvId */ }
	}

	// ── send ─────────────────────────────────────────────

	function send(text, isRetry) {
		text = (text || '').trim();
		if (!text || isProcessing) return;

		var input = $('spw-input');
		lastQuestion = text;

		if (!isRetry) {
			addMessage('user', text);
			if (input) { input.value = ''; autoGrow(input); }
		}

		wordSpans = [];
		// The transcript has now been sent on purpose, so its "nothing was sent yet" note goes.
		setVoiceStatus('');
		setBusy(true);
		showTyping();

		abortCtl = window.AbortController ? new AbortController() : null;

		api('widget/ask', { question: text, conversation_id: convId, history: turns.slice(-MAX_TURNS) }, abortCtl ? abortCtl.signal : undefined)
			.then(function (data) {
				hideTyping();
				setBusy(false);
				abortCtl = null;

				// Only a response the site marked successful is an answer. A refusal can carry
				// text of its own (the 400 "empty" reply once did) and must not be read as one.
				if (data && data.ok && data.answer) {
					convId = data.conversation_id || convId;
					saveConvId(convId);
					turns.push({ role: 'user', text: text.slice(0, 1200) }, { role: 'assistant', text: String(data.answer).slice(0, 1200) });
					if (turns.length > MAX_TURNS) turns = turns.slice(-MAX_TURNS);
					var shown = addMessage('assistant', data.answer, data.sources);
					// The engine named the topics the question could mean: offer them as prefills.
					if (shown && Array.isArray(data.clarify_options) && data.clarify_options.length) {
						addClarifyOptions(shown.stack, data.clarify_options);
					}
					// The engine could not answer and a real channel is ready: offer the person in the thread.
					if (data.handoff_offer) showHandoffCard(false);
					if (voiceEnabled && voiceAuto && shown) speakText(shown.text);
					return;
				}

				// A server-supplied message is more useful than a generic apology.
				addMessage('assistant', (data && data.message) || str('errorGeneric', 'Sorry, I could not answer that.'), null, {
					error: true,
					retry: NO_RETRY.indexOf(String((data && data.code) || '')) === -1,
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

	// Every write to the composer goes through here (typing, a clarify option, a transcript,
	// clearing after send, a new placeholder), so it also keeps the text direction right.
	// Written text reads in its own direction (dir="auto"), but an empty box inherits the page
	// direction: Chrome resolves an empty dir="auto" textarea to ltr, which would push a
	// Persian placeholder left. An empty box is sized for its placeholder, so one that is longer
	// than the box (a narrow phone, a long translation) is read whole instead of cut after one line.
	function autoGrow(node) {
		if (!node) return;
		if (node.value) node.setAttribute('dir', 'auto');
		else node.removeAttribute('dir');
		node.style.height = 'auto';
		var height = node.scrollHeight;
		if (!node.value && node.placeholder) {
			node.value = node.placeholder;
			height = node.scrollHeight;
			node.value = '';
		}
		node.style.height = Math.min(height, 120) + 'px';
	}

	// ── launcher nudge ───────────────────────────────────

	function hideNudge(remember) {
		var nudge = $('spw-nudge');
		if (nudge) nudge.hidden = true;
		if (nudgeTimer) { window.clearTimeout(nudgeTimer); nudgeTimer = null; }
		if (remember) storage('spwNudgeDismissed', '1');
	}

	function scheduleNudge(autoOpen) {
		var nudge = $('spw-nudge');
		if (!nudge || autoOpen > 0 || storage('spwNudgeDismissed') === '1') return;
		nudgeTimer = window.setTimeout(function () {
			if (isOpen) return;
			nudge.hidden = false;
			storage('spwNudgeDismissed', '1');
			nudgeTimer = window.setTimeout(function () { hideNudge(false); }, 12000);
		}, 4000);
	}

	// ── open / close / full screen ───────────────────────

	function setOpen(open) {
		var panel = $('spw-panel');
		var btn = $('spw-toggle');
		var box = root();
		if (!panel || !btn) return;

		isOpen = open;
		hideNudge(false);
		panel.classList.toggle('spw-panel--open', open);
		if (box) box.classList.toggle('spw-root--open', open);
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		syncModal();

		if (open) {
			lastFocus = document.activeElement;
			var input = $('spw-input');
			if (input) input.focus({ preventScroll: true });
			syncJump();
			return;
		}

		if (isFull) setFull(false);
		if (utterance && synth) synth.cancel();
		if (mediaRecorder && mediaRecorder.state === 'recording') stopRecording();
		stopGenerating();
		// The launcher is visible again only once the panel closes; focus it next.
		window.requestAnimationFrame(function () {
			if (lastFocus && lastFocus !== document.body && lastFocus.focus && lastFocus.offsetParent !== null) lastFocus.focus({ preventScroll: true });
			else btn.focus({ preventScroll: true });
		});
	}

	function toggle() { setOpen(!isOpen); }

	/** Whether the open panel covers the page: full screen, or the sheet on a phone. */
	function isModal() {
		return isOpen && (isFull || !!(sheetQuery && sheetQuery.matches));
	}

	/**
	 * A panel that covers the page is modal: it says so, keeps Tab inside (trapFocus) and
	 * locks the page behind it, so the page cannot scroll away under the scrim or under the
	 * phone sheet, where a scrolling page also resizes the sheet with the browser's address
	 * bar. The docked panel on a wider screen floats beside the page and leaves it alone.
	 * Runs whenever the panel opens, closes, enters or leaves full screen, or the viewport
	 * crosses the sheet threshold (a rotated phone).
	 */
	function syncModal() {
		var panel = $('spw-panel');
		var modal = isModal();
		if (panel) panel.setAttribute('aria-modal', modal ? 'true' : 'false');
		lockPageScroll(modal);
	}

	/**
	 * The inline overflow of <html> and <body> is snapshotted before the lock and written
	 * back exactly as it was; without a snapshot the unlock does nothing. Overflow keeps
	 * the page where it was, so closing the chat never jumps the visitor to the top.
	 */
	function lockPageScroll(lock) {
		var html = document.documentElement;
		var body = document.body;
		if (lock) {
			if (scrollLock || !html) return;
			scrollLock = { html: html.style.overflow, body: body ? body.style.overflow : '' };
			html.style.overflow = 'hidden';
			if (body) body.style.overflow = 'hidden';
			return;
		}
		if (!scrollLock) return;
		if (html) html.style.overflow = scrollLock.html;
		if (body) body.style.overflow = scrollLock.body;
		scrollLock = null;
	}

	function setFull(full) {
		var panel = $('spw-panel');
		var btn = $('spw-expand');
		var scrim = $('spw-scrim');
		var box = root();
		if (!panel || !btn) return;
		var wasFull = isFull;
		isFull = full;
		panel.classList.toggle('spw-panel--full', full);
		if (box) box.classList.toggle('spw-root--full', full);
		if (scrim) scrim.hidden = !full;
		var label = full ? str('exitFullScreen', 'Exit full screen') : str('fullScreen', 'Full screen');
		btn.setAttribute('aria-label', label);
		btn.title = label;
		syncModal();
		if (full) {
			// Entering a modal view: focus starts inside it, so Tab cannot walk the page behind.
			if (!panel.contains(document.activeElement)) panel.focus({ preventScroll: true });
		} else if (wasFull && isOpen) {
			// Leaving it by the scrim, Escape or a popular question takes the focused control
			// away with the side column, so focus goes back to the control that opened the view.
			var active = document.activeElement;
			if (!active || !panel.contains(active) || active.offsetParent === null) {
				var back = btn.offsetParent !== null ? btn : $('spw-input');
				if (back) back.focus({ preventScroll: true });
			}
		}
		scrollToEnd(true);
	}

	/** While the panel is modal (full screen or the phone sheet), Tab and Shift+Tab stay inside it. */
	function trapFocus(event) {
		if (!isModal() || event.key !== 'Tab') return;
		var panel = $('spw-panel');
		var focusable = qsa('button:not([disabled]), textarea, input:not([disabled]):not([type="hidden"]), select:not([disabled]), a[href], [tabindex="0"]', panel).filter(function (node) {
			return node.offsetParent !== null && !node.hidden;
		});
		if (!focusable.length) return;
		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		var active = document.activeElement;
		// The panel itself holds focus right after expanding: Shift+Tab from it would leave
		// the modal view backwards, so it wraps to the last control instead.
		if (event.shiftKey && (active === first || active === panel)) { event.preventDefault(); last.focus(); }
		else if (!event.shiftKey && active === last) { event.preventDefault(); first.focus(); }
	}

	// ── voice ────────────────────────────────────────────

	function micButtons() { return [$('spw-mic'), $('spw-voice')].filter(Boolean); }

	/**
	 * Narrate the current voice phase for assistive tech (F-16).
	 *
	 * Sighted visitors read the phase from the recording dot and timer and the typing dots;
	 * a screen reader gets nothing from either. Passing an empty string clears the region, which is
	 * what every failure path does: the failure itself is a message in the conversation log,
	 * and a stale "Transcribing…" left behind would contradict it.
	 *
	 * @param {string} text Phase text, already translated.
	 */
	function setVoiceStatus(text) {
		var region = $('spw-voice-status');
		if (region) region.textContent = text || '';
	}

	/**
	 * Elapsed recording time as m:ss, in the page's digits (Persian digits on a Persian site).
	 *
	 * @param {number} ms Milliseconds since the recording started.
	 * @return {string}
	 */
	function elapsed(ms) {
		var seconds = Math.max(0, Math.floor(ms / 1000));
		var minutes = Math.floor(seconds / 60);
		var rest = seconds % 60;
		try {
			var lang = document.documentElement.lang || undefined;
			var one = new Intl.NumberFormat(lang, { useGrouping: false });
			var two = new Intl.NumberFormat(lang, { useGrouping: false, minimumIntegerDigits: 2 });
			return one.format(minutes) + ':' + two.format(rest);
		} catch (e) {
			return minutes + ':' + (rest < 10 ? '0' : '') + rest;
		}
	}

	/**
	 * Show or clear the recording state in the composer (F-25, T13 follow-up).
	 *
	 * The box turns coral with a live dot and the elapsed time, and both mic buttons turn
	 * into stop buttons, so the placeholder only needs a one-line "Listening…" on a phone
	 * and on the desktop; the full "tap the mic again to stop" sentence goes to the status
	 * region for screen readers, and the buttons carry "Stop recording" as label and tooltip.
	 *
	 * @param {boolean} recording Whether the microphone is live.
	 */
	function setRecordingUi(recording) {
		var input = $('spw-input');
		var box = qs('.spw-composer__box');
		var rec = $('spw-rec');
		var time = $('spw-rec-time');
		micButtons().forEach(function (mic) {
			var label = recording ? str('stopRecording', 'Stop recording') : str('voiceInput', 'Voice input');
			mic.classList.toggle('spw-mic--recording', recording);
			mic.setAttribute('aria-pressed', recording ? 'true' : 'false');
			mic.setAttribute('aria-label', label);
			mic.title = label;
		});
		if (box) box.classList.toggle('spw-composer__box--recording', recording);
		if (recTimer) { window.clearInterval(recTimer); recTimer = null; }
		if (rec) rec.hidden = !recording;
		if (recording && time) {
			var startedAt = Date.now();
			var tick = function () { time.textContent = elapsed(Date.now() - startedAt); };
			tick();
			recTimer = window.setInterval(tick, 250);
		}
		if (input) {
			if (!defaultPlaceholder) defaultPlaceholder = input.getAttribute('placeholder') || '';
			input.setAttribute('placeholder', recording ? str('listening', 'Listening…') : defaultPlaceholder);
			autoGrow(input);
		}
	}

	function startRecording() {
		if (isRecording) return;
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
			setVoiceStatus('');
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
				setVoiceStatus('');
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
			setRecordingUi(true);
			setVoiceStatus(str('recording', 'Listening… tap the mic again to stop'));
			stopTimer = window.setTimeout(function () {
				if (mediaRecorder && mediaRecorder.state === 'recording') stopRecording();
			}, 45000);
		}).catch(function () {
			setVoiceStatus('');
			addMessage('assistant', str('micDenied', 'Microphone access was denied.'), null, { error: true });
		});
	}

	function stopRecording() {
		if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
		isRecording = false;
		setRecordingUi(false);
	}

	function sendAudio(blob, durationMs) {
		if (!blob || !blob.size) {
			setVoiceStatus('');
			addMessage('assistant', str('noAudio', 'No audio was captured. Please try again.'), null, { error: true });
			return;
		}
		setVoiceStatus(str('transcribing', 'Transcribing your recording…'));
		showTyping();
		var form = new FormData();
		form.append('audio', blob, 'aisaautopilot-widget-command.webm');
		form.append('duration_ms', String(Math.min(45000, Math.max(0, durationMs || 0))));
		form.append('language', document.documentElement.lang || '');

		upload('public/voice', form).then(function (data) {
			hideTyping();
			if (data && data.ok && data.transcript) {
				// The transcript lands in the field so it can be corrected before sending.
				// Nothing is sent here: send() runs only from the Send button, Enter, or a chip.
				var input = $('spw-input');
				if (input) {
					input.value = data.transcript;
					autoGrow(input);
					input.focus();
				}
				setVoiceStatus(str('transcriptReady', 'Your words are in the message box. Check them, then press Send. Nothing was sent yet.'));
				return;
			}
			setVoiceStatus('');
			addMessage('assistant', (data && data.message) || str('transcribeFailed', 'Could not transcribe that. Please try again.'), null, { error: true });
		}).catch(function (error) {
			hideTyping();
			setVoiceStatus('');
			addMessage('assistant', error.message || str('transcribeFailed', 'Could not transcribe that. Please try again.'), null, { error: true });
		});
	}

	function toggleRecording() { if (isRecording) stopRecording(); else startRecording(); }

	// ── handoff ──────────────────────────────────────────

	/**
	 * Insert the "bring in a person" card at the end of the thread. Only one
	 * unsent card exists at a time; sending posts the transcript to the channels
	 * the site owner configured, nothing is sent until the visitor presses it.
	 */
	function showHandoffCard(focus) {
		var template = $('spw-handoff-template');
		if (!template || !template.content) return;
		if (!isOpen) setOpen(true);

		var existing = qs('#spw-msgs .spw-handoff:not(.is-sent)');
		if (existing) {
			appendToLog(existing);
			scrollToEnd(true);
			if (focus) { var again = qs('[data-spw-handoff-send]', existing); if (again) again.focus(); }
			return;
		}

		var card = template.content.firstElementChild.cloneNode(true);
		var sendBtn = qs('[data-spw-handoff-send]', card);
		var later = qs('[data-spw-handoff-dismiss]', card);
		handoffCards++;
		qsa('[data-spw-handoff-field]', card).forEach(function (input) {
			var error = qs('[data-spw-handoff-error]', input.parentNode);
			if (error) error.id = 'spw-handoff-error-' + handoffCards + '-' + input.getAttribute('data-spw-handoff-field');
			input.addEventListener('input', function () { setFieldError(input, ''); });
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' && !e.isComposing) { e.preventDefault(); requestHandoff(card); }
			});
		});
		if (sendBtn) sendBtn.addEventListener('click', function () { requestHandoff(card); });
		if (later) later.addEventListener('click', function () {
			card.remove();
			syncChips();
			var input = $('spw-input');
			if (input) input.focus();
		});
		appendToLog(card);
		syncChips();
		scrollToEnd(true);
		if (focus && sendBtn) sendBtn.focus();
	}

	// Persian and Arabic-Indic digits count as digits, as they do on the server.
	function latinDigits(value) {
		return String(value)
			.replace(/[۰-۹]/g, function (d) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)); })
			.replace(/[٠-٩]/g, function (d) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)); });
	}

	/** A first check before sending; the server validates again and has the final word. */
	function fieldProblem(input) {
		var key = input.getAttribute('data-spw-handoff-field');
		var value = input.value.trim();
		if (!value) return input.required ? str('fieldRequired', 'Please fill this in.') : '';
		if (key === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
			return str('emailInvalid', 'Please provide a valid email address.');
		}
		if (key === 'phone') {
			var phone = latinDigits(value).replace(/\s+/g, ' ');
			var digits = phone.replace(/\D/g, '').length;
			if (!/^\+?[0-9 ().\-]+$/.test(phone) || digits < 6 || digits > 15) return str('phoneInvalid', 'Please enter a valid mobile number.');
		}
		if (key === 'website' && (/\s/.test(value) || !/^(https?:\/\/)?[^\/?#:@]+\.[^\/?#:@.]{2,}(:\d+)?([\/?#].*)?$/i.test(value))) {
			return str('websiteInvalid', 'Please enter a valid website address.');
		}
		return '';
	}

	function setFieldError(input, message) {
		var error = qs('[data-spw-handoff-error]', input.parentNode);
		if (error) {
			error.textContent = message;
			error.hidden = !message;
		}
		if (message) {
			input.setAttribute('aria-invalid', 'true');
			if (error && error.id) input.setAttribute('aria-describedby', error.id);
		} else {
			input.removeAttribute('aria-invalid');
			input.removeAttribute('aria-describedby');
		}
	}

	function requestHandoff(card) {
		var sendBtn = qs('[data-spw-handoff-send]', card);
		var later = qs('[data-spw-handoff-dismiss]', card);
		var status = qs('.spw-handoff__status', card);
		if (!sendBtn || sendBtn.disabled || sendBtn.hidden) return;

		var fields = qsa('[data-spw-handoff-field]', card);
		var body = { conversation_id: convId };
		var firstProblem = null;
		fields.forEach(function (input) {
			var problem = fieldProblem(input);
			setFieldError(input, problem);
			if (problem && !firstProblem) firstProblem = input;
			body[input.getAttribute('data-spw-handoff-field')] = input.value.trim();
		});
		if (firstProblem) {
			firstProblem.focus();
			return;
		}

		sendBtn.disabled = true;
		fields.forEach(function (input) { input.readOnly = true; });
		if (later) later.disabled = true;
		if (status) status.textContent = str('sending', 'Sending…');

		var msgs = [];
		qsa('#spw-msgs .spw-msg').forEach(function (node) {
			if (node.classList.contains('spw-msg--greeting') || node.classList.contains('spw-msg--error')) return;
			msgs.push({
				role: node.classList.contains('spw-msg--user') ? 'user' : 'assistant',
				// The rendered answer is the same text the visitor heard read aloud; its
				// paragraphs and list items keep their line breaks for whoever reads the email.
				content: typeof node.spwPlain === 'string' ? node.spwPlain : node.textContent,
			});
		});

		body.messages = msgs;
		api('widget/handoff', body).then(function (data) {
			if (data && data.ok) {
				card.classList.add('is-sent');
				if (status) status.textContent = str('sent', 'Sent — we will be in touch.');
				if (later) later.hidden = true;
				sendBtn.hidden = true;
				syncChips();
				return;
			}
			fields.forEach(function (input) { input.readOnly = false; });
			sendBtn.disabled = false;
			if (later) later.disabled = false;
			// A detail the server refused is marked on its own field; other failures show under the buttons.
			var refused = data && data.fields && data.fields.length ? data.fields : [];
			refused.forEach(function (key) {
				var input = qs('[data-spw-handoff-field="' + key + '"]', card);
				if (input) setFieldError(input, data.message || str('sendFailed', 'Could not send. Please try again.'));
			});
			var first = refused.length ? qs('[data-spw-handoff-field="' + refused[0] + '"]', card) : null;
			if (first) {
				if (status) status.textContent = '';
				first.focus();
				return;
			}
			if (status) status.textContent = (data && data.message) || str('sendFailed', 'Could not send. Please try again.');
		}).catch(function () {
			fields.forEach(function (input) { input.readOnly = false; });
			if (status) status.textContent = str('sendFailed', 'Could not send. Please try again.');
			sendBtn.disabled = false;
			if (later) later.disabled = false;
		});
	}

	// ── init ─────────────────────────────────────────────

	function init() {
		var btn = $('spw-toggle');
		if (!btn) return;
		var box = root();

		btn.setAttribute('aria-expanded', 'false');
		btn.setAttribute('aria-controls', 'spw-panel');
		btn.addEventListener('click', toggle);

		var sendBtn = $('spw-send');
		var input = $('spw-input');

		if (sendBtn) {
			sendBtn.addEventListener('click', function () {
				if (isProcessing) { stopGenerating(true); return; }
				if (input) send(input.value);
			});
		}

		if (input) {
			input.addEventListener('input', function () { autoGrow(input); });
			input.addEventListener('keydown', function (e) {
				// Enter sends; Shift+Enter starts a new line.
				if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
					e.preventDefault();
					send(input.value);
				}
			});
		}

		micButtons().forEach(function (mic) { mic.addEventListener('click', toggleRecording); });

		qsa('[data-spw-handoff]').forEach(function (link) {
			link.addEventListener('click', function () { showHandoffCard(true); });
		});

		var closeBtn = $('spw-close');
		if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });

		var expandBtn = $('spw-expand');
		if (expandBtn) expandBtn.addEventListener('click', function () { setFull(!isFull); });

		var scrim = $('spw-scrim');
		if (scrim) scrim.addEventListener('click', function () { setFull(false); });

		var jump = $('spw-jump');
		if (jump) jump.addEventListener('click', function () { scrollToEnd(true); });

		var msgs = $('spw-msgs');
		if (msgs) msgs.addEventListener('scroll', syncJump);

		// Suggested questions send immediately — one tap to a useful answer. These are the
		// questions the site owner typed, not text a model produced (D-24); anything the
		// assistant proposes, such as a clarify option or a transcript, only fills the composer.
		qsa('[data-spw-ask]').forEach(function (chip) {
			chip.addEventListener('click', function () {
				// Same 768px threshold as the stylesheet: below it full screen has no side column.
				if (isFull && chip.classList.contains('spw-side__q') && window.matchMedia && window.matchMedia('(max-width: 768px)').matches) setFull(false);
				send(chip.getAttribute('data-spw-ask'));
			});
		});

		var nudgeOpen = $('spw-nudge-open');
		if (nudgeOpen) nudgeOpen.addEventListener('click', function () { hideNudge(true); setOpen(true); });
		var nudgeClose = $('spw-nudge-close');
		if (nudgeClose) nudgeClose.addEventListener('click', function () { hideNudge(true); });

		var panel = $('spw-panel');
		if (panel) panel.addEventListener('keydown', trapFocus);

		// A phone turned sideways can cross the sheet threshold with the chat open.
		if (sheetQuery) {
			if (sheetQuery.addEventListener) sheetQuery.addEventListener('change', syncModal);
			else if (sheetQuery.addListener) sheetQuery.addListener(syncModal);
		}

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape' || !isOpen) return;
			if (isFull) { setFull(false); return; }
			setOpen(false);
		});

		// Nothing is fetched here: every visitor used to cost a REST request on every page
		// view, even when the chat was never opened. The panel is rendered with this page and
		// the runtime settings travel with it (F-15).
		var autoOpen = box ? parseInt(box.getAttribute('data-spw-auto-open'), 10) || 0 : 0;
		if (autoOpen > 0) window.setTimeout(function () { if (!isOpen) setOpen(true); }, autoOpen * 1000);
		scheduleNudge(autoOpen);

		if (window.location.hash === '#aisaautopilot-chat') setOpen(true);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
