/**
 * wpmldev-8628: the image scan on the Media Translation page (spec s.13 L-3, L-4, L-6).
 *
 * Every state of the notice is written by the server (templates/menus/media-translation-text-scan.twig) from the one
 * status body of GET status (R-1); this script shows one state at a time, updates the counts of a running scan,
 * starts a scan (R-2), drives it while the page is open (R-3) and saves the setting (R-6).
 *
 * Enqueued only while the text scan is on; `wpml_media_text_scan` = {root, nonce, canAct, modeLabels, strings}.
 */
jQuery(function ($) {

	'use strict';

	var config = window.wpml_media_text_scan;
	var root   = $('#wpml-media-text-scan');

	if (!config || !root.length) {
		return;
	}

	var POLL_DELAY  = 3000;
	var ERROR_DELAY = 10000;

	var panel     = $('#wpml-media-text-scan-settings');
	var form      = panel.find('form');
	var status    = readStatus();
	var savedMode = form.find('input[type=radio]:checked').val() || (status && status.mode) || '';
	var locale    = document.documentElement.lang || undefined;

	var timer       = null;
	var inFlight    = false;
	var reloading   = false;
	var askedFrom   = null;
	var askedState  = null;
	var panelOpener = null;

	function readStatus() {
		try {
			return JSON.parse(root.attr('data-status') || 'null');
		} catch (e) {
			return null;
		}
	}

	/**
	 * The same mapping as WPML\Media\TextScan\Listing::noticeState().
	 */
	function stateOf(s) {
		var modeOff = !s.mode || s.mode === 'off';

		if (s.status === 'running') {
			return s.retry_at ? 'interrupted' : 'running';
		}
		if (s.status === 'paused') {
			return modeOff ? 'paused-off' : 'running';
		}
		if (s.status === 'done') {
			return 'finished';
		}
		return modeOff ? 'off' : 'idle';
	}

	/**
	 * Disables buttons for the length of a request and returns the function that enables them again. A button disabled
	 * while it holds the focus drops the focus to the page, so that function also gives the focus back to it. When the
	 * answer has hidden that button (its notice gave way to another), the focus goes to the title of the notice now
	 * shown, so call it after the answer is applied. Only a request the user started passes here: a polled status never
	 * moves the focus, and a focus the user has moved elsewhere in the meantime stays where it is.
	 */
	function disableFor(buttons) {
		var focused = buttons.filter(function () {
			return this === document.activeElement;
		}).get(0) || null;

		buttons.prop('disabled', true);

		return function () {
			buttons.prop('disabled', false);
			if (!focused || (document.activeElement && document.activeElement !== document.body)) {
				return;
			}
			if ($(focused).is(':visible')) {
				focused.focus();
				return;
			}
			var title = root.find('[data-ts-state]:not([hidden]) .wpml-media-text-scan__title').filter(':visible').first();
			if (title.length) {
				if (!title.is('[tabindex]')) {
					title.attr('tabindex', '-1');
				}
				title.trigger('focus');
			}
		};
	}

	function formatNumber(n) {
		try {
			return Number(n).toLocaleString(locale);
		} catch (e) {
			return String(n);
		}
	}

	function show(state) {
		var block = root.find('[data-ts-state="' + state + '"]');
		if (!block.length) {
			return;
		}
		root.find('[data-ts-state]').prop('hidden', true);
		block.prop('hidden', false);
		syncOpeners();
	}

	function currentState() {
		return root.find('[data-ts-state]:not([hidden])').attr('data-ts-state');
	}

	/**
	 * The form of a count's string for that count: the page writes two forms, translated in PHP (`data-ts-one` for a
	 * count of 1, `data-ts-other` for any other count). Its %s / %1$s / %2$s placeholders take the values in order.
	 */
	function countText(el, count, values) {
		var pattern = String(el.attr(count === 1 ? 'data-ts-one' : 'data-ts-other') || '');
		var next    = 0;

		return pattern.replace(/%(?:(\d+)\$)?s/g, function (match, position) {
			var value = position ? values[parseInt(position, 10) - 1] : values[next++];
			return value === undefined ? match : value;
		});
	}

	/**
	 * Writes a status body into the notice: the counts of the scan, the setting's value, and the state to show. The
	 * total and the held count can change while the page is open (an image added to a running scan), so every count
	 * is written, and the count checked is never more than the total, as on the server.
	 */
	function applyStatus(s) {
		var total     = Math.max(0, parseInt(s.total, 10) || 0);
		var processed = Math.min(total, Math.max(0, parseInt(s.processed, 10) || 0));
		var held      = Math.max(0, parseInt(s.held, 10) || 0);
		var percent   = total > 0 ? Math.min(100, Math.floor(100 * processed / total)) : 0;
		var modeOff   = !s.mode || s.mode === 'off';

		root.find('[data-ts="processed"]').text(formatNumber(processed));
		root.find('[data-ts="total"]').text(formatNumber(total));
		root.find('[role="progressbar"]').each(function () {
			var bar = $(this);
			bar.attr('aria-valuemax', total);
			bar.attr('aria-valuenow', processed);
			bar.attr('aria-valuetext', countText(bar, total, [formatNumber(processed), formatNumber(total)]));
			bar.children('span').css('width', percent + '%');
		});
		root.find('[data-ts="held"]').each(function () {
			var line = $(this);
			line.text(countText(line, held, [formatNumber(held)]));
			line.prop('hidden', held === 0);
		});

		if (config.modeLabels && config.modeLabels[s.mode]) {
			root.find('[data-ts="mode-label"]').text(config.modeLabels[s.mode]);
		}
		root.find('[data-ts-when="mode-on"]').prop('hidden', modeOff);
		root.find('[data-ts-when="mode-off"]').prop('hidden', !modeOff);

		show(stateOf(s));
	}

	function request(method, path, body) {
		return $.ajax({
			url:         config.root + path,
			method:      method,
			dataType:    'json',
			contentType: body ? 'application/json' : undefined,
			data:        body ? JSON.stringify(body) : undefined,
			beforeSend:  function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', config.nonce);
			}
		});
	}

	function errorText(xhr) {
		var code = xhr && xhr.responseJSON && xhr.responseJSON.code;

		if (code === 'wpml_text_scan_not_allowed') {
			return config.strings.notAllowed;
		}
		if (code === 'wpml_text_scan_mode_off') {
			return config.strings.modeOff;
		}
		if (code === 'wpml_text_scan_running') {
			return config.strings.running;
		}
		return config.strings.failed;
	}

	function showError(block, text) {
		block.find('.wpml-media-text-scan__error').text(text || '');
	}

	function reload() {
		if (reloading) {
			return;
		}
		reloading = true;
		window.location.reload();
	}

	/* ---- L-3: while a scan runs and the page is open, one tick every 3 s (one request at a time) ---- */

	function schedule(delay) {
		window.clearTimeout(timer);
		timer = window.setTimeout(poll, delay);
	}

	function stopPolling() {
		window.clearTimeout(timer);
		timer = null;
	}

	function isActive(s) {
		var state = stateOf(s);
		return state === 'running' || state === 'interrupted';
	}

	/**
	 * A status body from any route: the page reloads once whenever the new status is `done` and the previous was not
	 * `done` (rows and counts are written again, the filters stay in the URL); otherwise the notice is updated, and
	 * polled again while it runs.
	 */
	function onStatus(s) {
		if (!s || typeof s !== 'object' || !s.status) {
			if (status && isActive(status)) {
				schedule(ERROR_DELAY);
			}
			return;
		}

		var wasDone = status ? status.status === 'done' : false;

		if (s.status === 'done' && !wasDone) {
			reload();
			return;
		}

		status = s;
		applyStatus(s);

		if (isActive(s)) {
			schedule(POLL_DELAY);
		} else {
			stopPolling();
		}
	}

	function poll() {
		if (inFlight || reloading) {
			return;
		}
		inFlight = true;

		// A user who may not use automatic translation reads the status; the tick (R-3) would refuse them.
		request(config.canAct ? 'POST' : 'GET', config.canAct ? 'tick' : 'status')
			.done(function (s) {
				inFlight = false;
				onStatus(s);
			})
			.fail(function () {
				inFlight = false;
				schedule(ERROR_DELAY);
			});
	}

	/* ---- L-4: "Scan images" opens the confirmation; "Start scan" starts the scan ---- */

	root.on('click', '.js-wpml-media-text-scan-ask', function () {
		askedFrom  = this;
		askedState = currentState();
		var confirm = root.find('[data-ts-state="confirm"]');
		showError(confirm, '');
		show('confirm');
		confirm.find('.wpml-media-text-scan__title').trigger('focus');
	});

	root.on('click', '.js-wpml-media-text-scan-cancel', function () {
		show(askedState || (status ? stateOf(status) : 'idle'));
		if (askedFrom) {
			$(askedFrom).trigger('focus');
		}
	});

	root.on('click', '.js-wpml-media-text-scan-start', function () {
		var confirm = root.find('[data-ts-state="confirm"]');
		var release = disableFor(confirm.find('button'));

		showError(confirm, '');
		request('POST', 'start', { scope: 'all' })
			.done(function () {
				// The page is written again with the running scan and its total.
				reload();
			})
			.fail(function (xhr) {
				release();
				showError(confirm, errorText(xhr));
			});
	});

	/* ---- L-3: "Try again now" is one forced tick: R-3 {force: true} clears retry_at under the scan lock, then ticks.
	   A `busy` answer means another tick held the lock and nothing was cleared, so the force is sent again. ---- */

	var FORCE_TRIES = 3;
	var FORCE_DELAY = 1000;

	root.on('click', '.js-wpml-media-text-scan-retry', function () {
		var block  = root.find('[data-ts-state="interrupted"]');
		var button = $(this);

		if (inFlight) {
			return;
		}
		inFlight = true;
		stopPolling();
		var release = disableFor(button);
		showError(block, '');

		function forceTick(triesLeft) {
			request('POST', 'tick', { force: true })
				.done(function (s) {
					if (s && s.busy && triesLeft > 1) {
						window.setTimeout(function () {
							forceTick(triesLeft - 1);
						}, FORCE_DELAY);
						return;
					}
					inFlight = false;
					onStatus(s);
					release();
				})
				.fail(function (xhr) {
					inFlight = false;
					release();
					showError(block, errorText(xhr));
					schedule(ERROR_DELAY);
				});
		}

		forceTick(FORCE_TRIES);
	});

	/* ---- L-6: the setting panel, opened by the notice's disclosure buttons ---- */

	function syncOpeners() {
		root.find('.js-wpml-media-text-scan-open').attr('aria-expanded', panel.length && !panel.prop('hidden') ? 'true' : 'false');
	}

	function syncChecked() {
		form.find('input[type=radio]').each(function () {
			$(this).closest('label').toggleClass('is-checked', this.checked);
		});
	}

	function clearMessages() {
		panel.find('.wpml-media-text-scan__message').text('');
	}

	function message(kind) {
		clearMessages();
		var el = panel.find('.wpml-media-text-scan__message--' + kind);
		el.text(el.attr('data-text') || '');
	}

	function restoreMode() {
		form.find('input[type=radio]').each(function () {
			this.checked = this.value === savedMode;
		});
		syncChecked();
	}

	function openPanel(opener) {
		panelOpener = opener;
		clearMessages();
		panel.prop('hidden', false);
		syncOpeners();
		var radio = form.find('input[type=radio]:checked');
		(radio.length ? radio : form.find('input[type=radio]').first()).trigger('focus');
	}

	function closePanel() {
		panel.prop('hidden', true);
		restoreMode();
		clearMessages();
		syncOpeners();
		if (panelOpener && $(panelOpener).is(':visible')) {
			$(panelOpener).trigger('focus');
		}
	}

	root.on('click', '.js-wpml-media-text-scan-open', function () {
		if (!panel.length) {
			return;
		}
		if (panel.prop('hidden')) {
			openPanel(this);
		} else {
			closePanel();
		}
	});

	form.on('change', 'input[type=radio]', syncChecked);

	root.on('click', '.js-wpml-media-text-scan-settings-cancel', closePanel);

	form.on('submit', function (event) {
		event.preventDefault();

		var radio = form.find('input[type=radio]:checked');
		if (!radio.length) {
			form.find('input[type=radio]').first().trigger('focus');
			return;
		}

		var mode   = radio.val();
		var release = disableFor(form.find('button[type=submit]'));
		clearMessages();

		request('POST', 'settings', { mode: mode })
			.done(function (answer) {
				savedMode = answer && answer.mode ? answer.mode : mode;
				restoreMode();
				message('saved');
				// A scan the setting paused resumes on the server (Q-7.2): read the status again and show it.
				request('GET', 'status')
					.done(onStatus)
					.always(release);
			})
			.fail(function () {
				release();
				message('failed');
			});
	});

	if (status && isActive(status)) {
		schedule(POLL_DELAY);
	}
});
