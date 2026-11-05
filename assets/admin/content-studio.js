(() => {
	'use strict';
	const root = document.querySelector('[data-aisaautopilot-content]');
	if (!root || !window.AISAAutoPilotAI) return;

	const api = window.AISAAutoPilotAI;
	const prompt = root.querySelector('#aisaautopilot-content-prompt');
	const planButton = root.querySelector('#aisaautopilot-content-plan');
	const status = root.querySelector('#aisaautopilot-content-status');
	const compose = root.querySelector('[data-content-compose]');
	const preview = root.querySelector('[data-content-preview]');
	const result = root.querySelector('[data-content-result]');
	const empty = root.querySelector('[data-content-empty]');
	const recent = root.querySelector('[data-content-recent]');
	const recentList = root.querySelector('[data-content-recent-list]');
	const restartButton = root.querySelector('[data-content-restart]');
	const approveButton = root.querySelector('[data-content-approve]');
	if (!prompt || !planButton || !status || !compose || !preview || !result || !restartButton || !approveButton) return;

	// Voice: a radio group of chips. Arrow keys move the choice (mirrored in right-to-left),
	// Home and End jump to the ends; only the chosen chip is in the Tab order.
	const voiceGroup = root.querySelector('[data-content-voice]');
	const voiceOptions = voiceGroup ? Array.from(voiceGroup.querySelectorAll('[data-content-voice-option]')) : [];
	const chooseVoice = (option, focus) => {
		voiceOptions.forEach((item) => {
			const on = item === option;
			item.setAttribute('aria-checked', on ? 'true' : 'false');
			item.tabIndex = on ? 0 : -1;
		});
		if (focus) option.focus();
	};
	const currentVoice = () => {
		const checked = voiceOptions.find((item) => item.getAttribute('aria-checked') === 'true');
		return checked ? checked.dataset.contentVoiceOption : 'site';
	};
	voiceOptions.forEach((option, index) => {
		option.addEventListener('click', () => chooseVoice(option, false));
		option.addEventListener('keydown', (event) => {
			const rtl = getComputedStyle(voiceGroup).direction === 'rtl';
			const keys = { ArrowDown: 1, ArrowUp: -1, ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 };
			let next = -1;
			if (event.key in keys) next = (index + keys[event.key] + voiceOptions.length) % voiceOptions.length;
			else if (event.key === 'Home') next = 0;
			else if (event.key === 'End') next = voiceOptions.length - 1;
			if (next < 0) return;
			event.preventDefault();
			chooseVoice(voiceOptions[next], true);
		});
	});

	let actionId = '';
	let latestPreview = {};
	let hasDrafts = root.dataset.contentHasDrafts === 'true';
	let isLocked = false;
	const defaultPlanLabel = planButton.textContent.trim();

	const message = (key, fallback) => (api.i18n && api.i18n[key]) || fallback;
	const setStatus = (value) => { status.textContent = value || ''; };
	const setEmptyVisible = (visible) => {
		if (empty) empty.hidden = !visible;
	};
	const setPlanButton = (busy) => {
		planButton.textContent = busy ? message('contentWriting', 'Writing...') : defaultPlanLabel;
	};
	const lockComposer = () => {
		isLocked = true;
		prompt.disabled = true;
		planButton.disabled = true;
		voiceOptions.forEach((option) => { option.disabled = true; });
	};
	const request = async (path, data) => {
		const response = await fetch(api.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': api.nonce },
			body: JSON.stringify(data)
		});
		const payload = await response.json().catch(() => ({}));
		if (!response.ok || !payload.ok) {
			const error = new Error(payload.message || message('networkError', 'Network error. Please try again.'));
			error.code = payload.code || '';
			throw error;
		}
		return payload;
	};
	const write = (selector, value) => {
		const node = root.querySelector(selector);
		if (node) node.textContent = value || '-';
	};
	const appendRecentDraft = (draft) => {
		if (!recent || !recentList) return;

		const row = document.createElement('div');
		row.className = 'sp-content-recent__row';
		row.dataset.contentRecentRow = '';
		const main = document.createElement('div');
		main.className = 'sp-content-recent__main';
		const title = document.createElement('div');
		title.className = 'sp-content-recent__title';
		title.textContent = draft.title || message('contentUntitledDraft', 'Untitled draft');
		const meta = document.createElement('div');
		meta.className = 'sp-content-recent__meta';
		meta.textContent = `${draft.post_type === 'page' ? message('contentPage', 'Page') : message('contentPost', 'Post')} · ${message('contentJustNow', 'Just now')}`;
		const state = document.createElement('span');
		state.className = 'sp-content-recent__state sp-content-recent__state--warn';
		state.textContent = message('contentDraftSaved', 'Draft saved');
		main.append(title, meta);
		row.append(main, state);
		recentList.prepend(row);
		recent.hidden = false;
	};

	planButton.addEventListener('click', async () => {
		const brief = prompt.value.trim();
		if (!brief) {
			setStatus(message('contentBriefRequired', 'Describe the draft first.'));
			prompt.focus();
			return;
		}
		planButton.disabled = true;
		setPlanButton(true);
		setStatus(message('contentPlanning', 'Building a reviewable draft...'));
		try {
			// A draft started from an unanswered question carries its reference; the server links the draft after approval.
			const sourceGap = compose.dataset.contentSourceGap || '';
			const data = { prompt: brief, tone: currentVoice() };
			if (sourceGap) data.source_gap = sourceGap;
			const payload = await request('content/plan', data);
			actionId = payload.action_id || '';
			if (!actionId) throw new Error(message('networkError', 'Network error. Please try again.'));
			latestPreview = payload.preview || {};
			write('[data-content-title]', latestPreview.title);
			write('[data-content-slug]', latestPreview.slug);
			write('[data-content-meta]', latestPreview.meta_description);
			write('[data-content-body]', latestPreview.body);
			write('[data-content-voice-label]', latestPreview.tone_label);
			compose.hidden = true;
			setEmptyVisible(false);
			preview.hidden = false;
			result.hidden = true;
			setStatus('');
		} catch (error) {
			setStatus(error.message);
			if (error.code === 'usage_cap_reached' || error.code === 'integration_disabled') lockComposer();
		} finally {
			if (!isLocked) {
				planButton.disabled = false;
				setPlanButton(false);
			}
		}
	});

	restartButton.addEventListener('click', () => {
		actionId = '';
		latestPreview = {};
		preview.hidden = true;
		result.hidden = true;
		compose.hidden = false;
		setEmptyVisible(!hasDrafts);
		setStatus('');
		prompt.focus();
	});

	approveButton.addEventListener('click', async (event) => {
		if (!actionId) return;
		const button = event.currentTarget;
		button.disabled = true;
		setStatus(message('contentCreating', 'Creating your WordPress draft...'));
		try {
			const payload = await request('content/execute', { action_id: actionId });
			const data = payload.result || {};
			result.textContent = '';
			const text = document.createElement('p');
			text.textContent = message('contentCreated', 'Draft created. Review and publish it from WordPress when you are ready.');
			result.appendChild(text);
			if (data.edit_url) {
				const link = document.createElement('a');
				link.className = 'sp-btn sp-btn--ghost sp-btn--sm';
				link.href = data.edit_url;
				link.textContent = message('contentOpenDraft', 'Open draft in WordPress');
				result.appendChild(link);
			}
			appendRecentDraft(latestPreview);
			hasDrafts = true;
			root.dataset.contentHasDrafts = 'true';
			setEmptyVisible(false);
			actionId = '';
			preview.hidden = true;
			result.hidden = false;
			setStatus('');
		} catch (error) {
			setStatus(error.message);
			button.disabled = false;
		}
	});
})();
