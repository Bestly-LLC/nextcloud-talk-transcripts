/**
 * Talk Transcripts — admin settings page handler.
 * No framework dependency — runs directly against the Nextcloud-rendered form.
 *
 * @copyright Copyright (c) 2026 Bestly LLC <hello@bestly.tech>
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	const form = document.getElementById('talk_transcripts_form');
	if (!form) {
		return;
	}

	const status = document.getElementById('tt_save_status');
	const csrfToken = form.dataset.csrf;

	// "Clear key" buttons just set a hidden marker the controller respects.
	function attachClearHandler(buttonId, hiddenName) {
		const btn = document.getElementById(buttonId);
		if (!btn) return;
		btn.addEventListener('click', function () {
			let marker = form.querySelector('input[name="' + hiddenName + '"]');
			if (!marker) {
				marker = document.createElement('input');
				marker.type = 'hidden';
				marker.name = hiddenName;
				form.appendChild(marker);
			}
			marker.value = '1';
			const visiblePw = form.querySelector('input[type="password"][name="' + hiddenName.replace('_clear', '') + '"]');
			if (visiblePw) {
				visiblePw.value = '';
				visiblePw.placeholder = '(cleared on save)';
			}
		});
	}

	attachClearHandler('tt_whisper_clear', 'whisper_api_key_clear');
	attachClearHandler('tt_summary_clear', 'summary_api_key_clear');

	function setStatus(msg, kind) {
		status.textContent = msg;
		status.className = 'msg ' + (kind || '');
	}

	form.addEventListener('submit', async function (event) {
		event.preventDefault();
		setStatus('Saving…', '');

		// Gather payload as plain object; checkboxes need explicit boolean coercion.
		const fd = new FormData(form);
		const payload = {};
		for (const [k, v] of fd.entries()) {
			payload[k] = v;
		}
		// Checkboxes that are unchecked don't appear in FormData — coerce explicitly.
		payload.enabled = form.querySelector('#tt_enabled').checked;
		payload.summary_enabled = form.querySelector('#tt_summary_enabled').checked;
		payload.whisper_api_key_clear = !!form.querySelector('input[name="whisper_api_key_clear"]');
		payload.summary_api_key_clear = !!form.querySelector('input[name="summary_api_key_clear"]');

		const url = OC.generateUrl('/apps/talk_transcripts/api/v1/admin/settings');

		try {
			const res = await fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'OCS-APIRequest': 'true',
					'requesttoken': csrfToken,
				},
				body: JSON.stringify({ config: payload }),
			});

			if (!res.ok) {
				const text = await res.text();
				setStatus('Save failed: ' + res.status + ' ' + text.substring(0, 200), 'error');
				return;
			}
			setStatus('Saved.', 'success');
			// Clear the password fields after a successful save so a re-save
			// doesn't accidentally re-submit a previous key value.
			form.querySelector('#tt_whisper_api_key').value = '';
			form.querySelector('#tt_summary_api_key').value = '';
		} catch (err) {
			setStatus('Save failed: ' + err.message, 'error');
		}
	});
})();
