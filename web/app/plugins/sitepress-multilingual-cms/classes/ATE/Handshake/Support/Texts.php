<?php

namespace WPML\TM\ATE\Handshake\Support;

use WPML\TM\ATE\Handshake\RetryAnswers;

class Texts {


	public static function title() {
		/* translators: Heading of the box on WPML > Support that shows whether translations reach this site. */
		return __( 'Translation delivery', 'sitepress' );
	}

	public static function intro() {
		/* translators: The line under the heading of the Translation delivery box on WPML > Support. */
		return __( 'Translations stuck in progress, waiting, or not translated? See where your translations are, what is wrong, and what you can do now.', 'sitepress' );
	}

	public static function siteColumn() {
		/* translators: Heading of the left column of the Translation delivery box: what this WordPress site recorded about receiving translations. */
		return __( 'What this site knows', 'sitepress' );
	}

	public static function systemColumn() {
		/* translators: Heading of the right column of the Translation delivery box: what WPML's translation service recorded about this site. */
		return __( 'What the translation system sees', 'sitepress' );
	}

	public static function readTogether() {
		/* translators: Label before the sentence that reads the two columns of the Translation delivery box together. */
		return __( 'Read together:', 'sitepress' );
	}

	public static function recentEvents() {
		/* translators: Label of the collapsed list of the latest delivery events. */
		return __( 'Recent events', 'sitepress' );
	}

	public static function eventsOnThisSite() {
		/* translators: Heading of the events this site recorded. */
		return __( 'On this site', 'sitepress' );
	}

	public static function eventsInTheSystem() {
		/* translators: Heading of the events WPML's translation service recorded for this site. */
		return __( 'In the translation system', 'sitepress' );
	}

	public static function noEvents() {
		/* translators: Shown when a list of delivery events is empty. */
		return __( 'Nothing recorded yet.', 'sitepress' );
	}

	public static function recommended() {
		/* translators: Badge next to the setting or action WPML advises: on the media translation and Translation Management settings screens, and in the Translation delivery box. Past participle used as a label: this is what WPML advises. */
		return __( 'Recommended', 'sitepress' );
	}

	public static function detailsForSupport() {
		/* translators: Label of a collapsed line holding the technical error text, which the Report button sends to the support team. */
		return __( 'Details for support', 'sitepress' );
	}

	public static function dateAt( $date, $time ) {
		/* translators: A date and a time, e.g. "27 Oct at 09:40". %1$s: the date; %2$s: the time. */
		return sprintf( __( '%1$s at %2$s', 'sitepress' ), $date, $time );
	}

	public static function markLabel( $kind ) {
		$labels = [
			/* translators: Says that all is well: the label of the button that confirms a dialog and carries out what it asks about, and the text read by screen readers for a green check mark. */
			'ok'   => __( 'OK', 'sitepress' ),
			/* translators: Status of something that is being done right now, for example a translation being made. Also read by screen readers for a blue dot in the Translation delivery box. */
			'info' => __( 'In progress', 'sitepress' ),
			/* translators: Text equivalent of an amber exclamation mark, read by screen readers. */
			'warn' => __( 'Needs attention', 'sitepress' ),
			/* translators: Text equivalent of a red cross, read by screen readers. */
			'bad'  => __( 'Problem', 'sitepress' ),
			/* translators: Text equivalent of a grey dash, read by screen readers. */
			'none' => __( 'Nothing to report', 'sitepress' ),
		];

		return isset( $labels[ $kind ] ) ? $labels[ $kind ] : $labels['none'];
	}


	public static function status( $situation, $failed = 0 ) {
		switch ( $situation ) {
			case 'collecting':
				/* translators: Status line of the Translation delivery box. */
				return __( 'Collecting now', 'sitepress' );
			case 'failed_on_site':
				/* translators: Status line of the Translation delivery box. %d: a number of translations. */
				return sprintf( _n( '%d translation could not be applied', '%d translations could not be applied', $failed, 'sitepress' ), $failed );
			case 'unreachable':
				/* translators: Status line of the Translation delivery box. */
				return __( 'We cannot reach your site', 'sitepress' );
			case 'blocked':
				/* translators: Status line of the Translation delivery box. */
				return __( 'Something is blocking your translations', 'sitepress' );
			case 'signature':
				/* translators: Status line of the Translation delivery box. */
				return __( 'Your site does not accept our notifications', 'sitepress' );
			case 'hears_but_silent':
				/* translators: Status line of the Translation delivery box, and the subject of our email, quoted on the page it links to. */
				return __( 'Your translations are waiting', 'sitepress' );
			case 'not_confirmed':
				/* translators: Status line of the Translation delivery box, when the translation system answers this site's calls without confirming how it delivers. */
				return __( 'The translation system did not confirm this site', 'sitepress' );
			case 'keys_refused':
				/* translators: Status line of the Translation delivery box, when the translation system no longer accepts the keys this site signs its calls with. */
				return __( 'This site\'s keys are refused', 'sitepress' );
			case 'site_moved':
				/* translators: Status line of the Translation delivery box, when the translation system no longer recognizes this site at its address (it moved, or it is a copy). */
				return __( 'This site\'s registration does not match its address', 'sitepress' );
			default:
				/* translators: Status line of the Translation delivery box, and title of the delivery check's result card. */
				return __( 'All good', 'sitepress' );
		}
	}

	public static function headline( $situation, $failed = 0 ) {
		switch ( $situation ) {
			case 'collecting':
				/* translators: Headline of the Translation delivery box. */
				return __( 'Your translations are on their way. Nothing to do.', 'sitepress' );
			case 'failed_on_site':
				/* translators: Headline of the Translation delivery box, and of the admin notice that leads to it. %d: a number of translations. */
				return sprintf( _n( '%d translation could not be applied on this site.', '%d translations could not be applied on this site.', $failed, 'sitepress' ), $failed );
			case 'unreachable':
				/* translators: Headline of the Translation delivery box. */
				return __( 'The translation system cannot reach this site.', 'sitepress' );
			case 'blocked':
				/* translators: Headline of the Translation delivery box. */
				return __( 'Something on this site blocks the translation system\'s calls.', 'sitepress' );
			case 'signature':
				/* translators: Headline of the Translation delivery box. */
				return __( 'This site no longer accepts the translation system\'s notifications.', 'sitepress' );
			case 'hears_but_silent':
				/* translators: Headline of the Translation delivery box. */
				return __( 'Your site answers our calls but does not collect your translations.', 'sitepress' );
			case 'not_confirmed':
				/* translators: Headline of the Translation delivery box. */
				return __( 'The translation system answers this site without confirming how it delivers. Your site keeps collecting translations.', 'sitepress' );
			case 'keys_refused':
				/* translators: Headline of the Translation delivery box. */
				return __( 'The translation system no longer accepts this site\'s keys. Your site cannot send or collect translations until it is registered again.', 'sitepress' );
			case 'site_moved':
				/* translators: Headline of the Translation delivery box, when the translation system no longer recognizes this site at its address (it moved, or it is a copy). */
				return __( 'The translation system no longer recognizes this site at its address. Your site cannot send or collect translations until it is registered again.', 'sitepress' );
			default:
				/* translators: Headline of the Translation delivery box. */
				return __( 'Your translations arrive. Nothing is waiting.', 'sitepress' );
		}
	}

	public static function mailNote( $condition, $written = null ) {
		$subjects = [
			/* translators: The subject of our email, quoted on the page it links to. */
			'failed_on_site'   => __( 'Translations could not be applied on your site', 'sitepress' ),
			/* translators: The subject of our email, quoted on the page it links to. */
			'unreachable'      => __( 'We cannot reach your site to deliver your translations', 'sitepress' ),
			/* translators: The subject of our email, quoted on the page it links to. */
			'blocked'          => __( 'Something on your site is blocking your translations', 'sitepress' ),
			/* translators: The subject of our email, quoted on the page it links to. */
			'signature'        => __( 'Your site did not accept our notification', 'sitepress' ),
			/* translators: Status line of the Translation delivery box, and the subject of our email, quoted on the page it links to. */
			'hears_but_silent' => __( 'Your translations are waiting', 'sitepress' ),
		];

		if ( ! isset( $subjects[ $condition ] ) ) {
			return '';
		}

		if ( 'hears_but_silent' === $condition && null !== $written && (int) $written > 0 ) {
			/* translators: Second sentence of the note on a page opened from our email. */
			$then = __( 'Opening it collected your translations.', 'sitepress' );
		} elseif ( 'hears_but_silent' === $condition && null !== $written ) {
			/* translators: Second sentence of the note on a page opened from our email, when collecting as the page opened wrote no translation. */
			$then = __( 'Opening it did not bring in any translation.', 'sitepress' );
		} else {
			/* translators: Second sentence of the note on a page opened from our email. */
			$then = __( 'We checked delivery as the page opened.', 'sitepress' );
		}

		/* translators: %1$s: the subject of our email; %2$s: what happened when the page opened. */
		return sprintf( __( 'You opened this page from our email “%1$s”. %2$s', 'sitepress' ), $subjects[ $condition ], $then );
	}


	public static function situationLabels() {
		return [
			/* translators: Row label of the situation card in the Translation delivery box. */
			__( 'What is wrong', 'sitepress' ),
			/* translators: Row label of the situation card in the Translation delivery box. */
			__( 'Your translations', 'sitepress' ),
			/* translators: Row label of the situation card in the Translation delivery box. */
			__( 'What to do first', 'sitepress' ),
			/* translators: Row label of the situation card in the Translation delivery box. */
			__( 'What we recommend', 'sitepress' ),
		];
	}

	public static function wrongFailed( $failed ) {
		/* translators: Situation card, "What is wrong". %d: a number of translations. */
		return sprintf( _n( 'Your site received %d translation but could not save it. Your site recorded the error shown below.', 'Your site received these %d translations but could not save them. For each one, your site recorded the error shown below.', $failed, 'sitepress' ), $failed );
	}

	public static function meansFailed() {
		/* translators: Situation card, "Your translations", for translations that could not be applied. */
		return __( '<b>Your translations are safe.</b> Their words are back in your account, and Retry sends them again; the words are charged again.', 'sitepress' );
	}

	public static function firstFailed() {
		/* translators: Situation card, "What to do first", for translations that could not be applied. */
		return __( 'Fix what each error names. The details for support show the error your site recorded.', 'sitepress' );
	}

	public static function recNotConfirmed() {
		/* translators: Situation card, "What we recommend", when the translation system answers this site without confirming how it delivers. */
		return __( 'Click <b>Check delivery now</b>. If this stays, click <b>Report</b>: the details for support go with it.', 'sitepress' );
	}

	public static function recNotConfirmedPlain() {
		/* translators: Situation card, "What we recommend", when the translation system answers this site without confirming how it delivers, and Report opens an empty support request. */
		return __( 'Click <b>Check delivery now</b>. If this stays, click <b>Report</b>. It opens a support request. Say that the translation system does not confirm how it delivers to your site.', 'sitepress' );
	}

	public static function recFailed() {
		/* translators: Situation card, "What we recommend", for translations that could not be applied. */
		return __( 'Then click <b>Retry all</b>. If one fails again, click <b>Report</b>: the details for support go with it.', 'sitepress' );
	}

	public static function recFailedPlain() {
		/* translators: Situation card, "What we recommend", for translations that could not be applied, when Report opens an empty support request. "Could not be applied" is the label of the list of these translations. */
		return __( 'Then click <b>Retry all</b>. If one fails again, click <b>Report</b>. It opens a support request. Name the translations listed under <b>Could not be applied</b> and the errors they show.', 'sitepress' );
	}

	public static function wrongUnreachable( $at, $lastCall ) {
		$text = '' === $at
			/* translators: Situation card, "What is wrong". */
			? __( 'The translation system told your site that translations were ready, and nothing answered.', 'sitepress' )
			/* translators: Situation card, "What is wrong". %s: a time. */
			: sprintf( __( 'At %s the translation system told your site that translations were ready, and nothing answered.', 'sitepress' ), $at );

		return $text . self::noCallSince( $lastCall );
	}

	public static function wrongBlocked( $at, $path, $status, $lastCall ) {
		if ( '' === $at && null === $status ) {
			/* translators: Situation card, "What is wrong". %s: a web address path. */
			$text = sprintf( __( 'The translation system called %s on your site, and something other than WordPress answered.', 'sitepress' ), $path );
		} elseif ( '' === $at ) {
			/* translators: Situation card, "What is wrong". %1$s: a web address path; %2$d: an HTTP status code, e.g. 403. */
			$text = sprintf( __( 'The translation system called %1$s on your site, and something other than WordPress answered with status %2$d.', 'sitepress' ), $path, $status );
		} elseif ( null === $status ) {
			/* translators: Situation card, "What is wrong". %1$s: a time; %2$s: a web address path. */
			$text = sprintf( __( 'At %1$s the translation system called %2$s on your site, and something other than WordPress answered.', 'sitepress' ), $at, $path );
		} else {
			/* translators: Situation card, "What is wrong". %1$s: a time; %2$s: a web address path; %3$d: an HTTP status code, e.g. 403. */
			$text = sprintf( __( 'At %1$s the translation system called %2$s on your site, and something other than WordPress answered with status %3$d.', 'sitepress' ), $at, $path, $status );
		}

		return $text . self::noCallSince( $lastCall );
	}

	private static function noCallSince( $lastCall ) {
		/* translators: Sentence added to "What is wrong". %s: a date and time. */
		return '' === $lastCall ? '' : ' ' . sprintf( __( 'This site has received no call since %s.', 'sitepress' ), $lastCall );
	}

	public static function wrongSignature( $since, $status, $registered, $current ) {
		$what = null === $status
			/* translators: Part of "What is wrong": the site answered the call and refused it. */
			? __( 'your site answers our calls but does not accept them', 'sitepress' )
			/* translators: Part of "What is wrong". %d: an HTTP status code, e.g. 401. */
			: sprintf( __( 'your site answers our calls but does not accept them (status %d)', 'sitepress' ), $status );

		$text = '' === $since
			/* translators: Situation card, "What is wrong". %s: "your site answers our calls but does not accept them". */
			? sprintf( __( 'Now %s', 'sitepress' ), $what )
			/* translators: Situation card, "What is wrong". %1$s: a date and time; %2$s: "your site answers our calls but does not accept them". */
			: sprintf( __( 'Since %1$s %2$s', 'sitepress' ), $since, $what );

		if ( '' !== $registered ) {
			/* translators: End of "What is wrong". %1$s: the address the site is registered for; %2$s: the address it runs at. */
			return $text . sprintf( __( ': this site is registered for %1$s and now runs at %2$s.', 'sitepress' ), $registered, $current );
		}

		return $text . '.';
	}

	public static function firstSignature() {
		/* translators: Situation card, "What to do first", for a site whose registration does not match. */
		return __( 'Compare the two addresses under Registration. If this site is a copy of another site (a staging site or a clone), it needs its own site key.', 'sitepress' );
	}

	public static function recSignature( $current ) {
		/* translators: Situation card, "What we recommend". %s: this site's address. */
		return sprintf( __( 'Click <b>Re-register this site</b> and enter the site key for %s.', 'sitepress' ), $current );
	}

	public static function wrongSilent( $at, $plan, $lastRun, $reason ) {
		if ( '' === $at ) {
			/* translators: Situation card, "What is wrong". */
			$text = __( 'Your site answered our notification', 'sitepress' );
		} elseif ( '' === $plan ) {
			/* translators: Situation card, "What is wrong". %s: a time. */
			$text = sprintf( __( 'Your site answered our notification at %s', 'sitepress' ), $at );
		} else {
			/* translators: Situation card, "What is wrong". %1$s: a time; %2$s: how the site said it collects, e.g. "starts its own collection request". */
			$text = sprintf( __( 'Your site answered our notification at %1$s and said it %2$s', 'sitepress' ), $at, $plan );
		}

		$text .= '' === $lastRun
			/* translators: Part of "What is wrong". */
			? __( ', but it has not collected anything', 'sitepress' )
			/* translators: Part of "What is wrong". %s: a date and time. */
			: sprintf( __( ', but it has not collected anything since %s', 'sitepress' ), $lastRun );

		$why = [
			/* translators: End of "What is wrong": why the site does not collect. */
			'loopback' => __( ': your site cannot call its own address.', 'sitepress' ),
			/* translators: End of "What is wrong": why the site does not collect. WP-Cron is WordPress's scheduler. */
			'cron'     => __( ': WP-Cron does not run on your site.', 'sitepress' ),
		];

		return $text . ( isset( $why[ $reason ] ) ? $why[ $reason ] : '.' );
	}

	public static function means( $situation, $waiting, $since ) {
		/* translators: Start of the situation card's "Your translations" row, shown in bold. */
		$safe = '<b>' . __( 'Your translations are safe.', 'sitepress' ) . '</b>';

		if ( $waiting < 1 ) {
			$until = [
				/* translators: Situation card, "Your translations", after "Your translations are safe." */
				'unreachable'      => __( 'Nothing is waiting now. New translations will wait until the translation system reaches your site again.', 'sitepress' ),
				/* translators: Situation card, "Your translations", after "Your translations are safe." */
				'blocked'          => __( 'Nothing is waiting now. New translations will wait until the calls get through.', 'sitepress' ),
				/* translators: Situation card, "Your translations", after "Your translations are safe." */
				'signature'        => __( 'Nothing is waiting now. New translations will wait until the site is registered for its address.', 'sitepress' ),
				/* translators: Situation card, "Your translations", after "Your translations are safe." */
				'hears_but_silent' => __( 'Nothing is waiting now. New translations will wait until someone opens WP admin, unless you apply the fix below.', 'sitepress' ),
				/* translators: Situation card, "Your translations", after "Your translations are safe." */
				'keys_refused'     => __( 'Nothing is waiting now. New translations cannot be sent until the site is registered again.', 'sitepress' ),
				/* translators: Situation card, "Your translations", after "Your translations are safe." */
				'site_moved'       => __( 'Nothing is waiting now. New translations cannot be sent until the site is registered again.', 'sitepress' ),
			];

			return $safe . ' ' . ( isset( $until[ $situation ] ) ? $until[ $situation ] : '' );
		}

		if ( 'hears_but_silent' === $situation ) {
			/* translators: Situation card, "Your translations". %d: a number of translations. */
			return $safe . ' ' . sprintf( _n( '%d is finished and waiting. Collect now brings it in right away.', '%d are finished and waiting. Collect now brings them in right away.', $waiting, 'sitepress' ), $waiting );
		}

		$when = [
			/* translators: End of "Your translations": when the site gets the waiting translations. */
			'unreachable' => __( 'Your site collects them as soon as it answers again.', 'sitepress' ),
			/* translators: End of "Your translations": when the site gets the waiting translations. */
			'blocked'     => __( 'Your site collects them as soon as the calls get through.', 'sitepress' ),
			/* translators: End of "Your translations": when the site gets the waiting translations. */
			'signature'   => __( 'Your site collects them as soon as it is registered for its address.', 'sitepress' ),
			/* translators: End of "Your translations": when the site gets the waiting translations. */
			'keys_refused' => __( 'Your site collects them once it is registered again.', 'sitepress' ),
			/* translators: End of "Your translations": when the site gets the waiting translations. */
			'site_moved'   => __( 'Your site collects them once it is registered again.', 'sitepress' ),
		];
		$tail = isset( $when[ $situation ] ) ? ' ' . $when[ $situation ] : '';

		if ( '' === $since ) {
			/* translators: Situation card, "Your translations". %d: a number of translations. */
			return $safe . ' ' . sprintf( _n( '%d is finished and waiting for your site.', '%d are finished and waiting for your site.', $waiting, 'sitepress' ), $waiting ) . $tail;
		}

		/* translators: Situation card, "Your translations". %1$d: a number of translations; %2$s: a date and time. */
		return $safe . ' ' . sprintf( _n( '%1$d is finished and waiting for your site, the oldest since %2$s.', '%1$d are finished and waiting for your site, the oldest since %2$s.', $waiting, 'sitepress' ), $waiting, $since ) . $tail;
	}

	public static function firstUnreachable() {
		/* translators: Situation card, "What to do first". */
		return __( 'Click <b>Check delivery now</b>. It collects what is waiting and asks the translation system to call your site again, then tells you what happened.', 'sitepress' );
	}

	public static function recUnreachable( $path ) {
		/* translators: Situation card, "What we recommend". %s: a web address path. */
		return sprintf( __( 'If the translation system still cannot reach your site: check that the site is online from outside your network and that %s answers. Then click <b>Check again</b>.', 'sitepress' ), $path );
	}

	public static function firstBlocked( $path ) {
		/* translators: Situation card, "What to do first". %s: a web address path. */
		return sprintf( __( 'In your firewall, security plugin or CDN, find the blocked request to %s and allow requests from WPML\'s translation system to it.', 'sitepress' ), $path );
	}

	public static function recBlocked( $path ) {
		/* translators: Situation card, "What we recommend". %s: a web address path. */
		return sprintf( __( 'Then click <b>Check delivery now</b>: it shows what answers at %s now.', 'sitepress' ), $path );
	}

	public static function firstSilent() {
		/* translators: Situation card, "What to do first", for a site that does not collect. */
		return __( 'Click <b>Collect now</b>.', 'sitepress' );
	}

	public static function recSilent( $reason ) {
		if ( 'host' === $reason ) {
			/* translators: Situation card, "What we recommend", for a host that stops PHP once the answer is sent. */
			return __( 'So that your site collects without anyone logged in, ask your host to let PHP finish its work after the answer is sent (PHP-FPM). A real cron job that calls wp-cron.php every 5 minutes also works.', 'sitepress' );
		}

		/* translators: Situation card, "What we recommend", for a site that cannot call its own address. */
		return __( 'So that your site collects without anyone logged in, ask your host to let the site call its own address. A real cron job that calls wp-cron.php every 5 minutes also works.', 'sitepress' );
	}

	public static function wrongKeysRefused( $since, $answer ) {
		$why = [
			/* translators: End of "What is wrong" for refused keys. */
			'access_key_not_found'  => __( 'it no longer knows this site\'s access key', 'sitepress' ),
			/* translators: End of "What is wrong" for refused keys. */
			'access_key_not_active' => __( 'this site\'s access key is switched off', 'sitepress' ),
			/* translators: End of "What is wrong" for refused keys. */
			'invalid_signature'     => __( 'this site\'s keys do not match the ones it holds', 'sitepress' ),
		];
		/* translators: End of "What is wrong" for refused keys, when the reason is not known. */
		$what = isset( $why[ $answer ] ) ? $why[ $answer ] : __( 'it does not accept this site\'s keys', 'sitepress' );

		return '' === $since
			/* translators: Situation card, "What is wrong". %s: why, e.g. "it no longer knows this site's access key". */
			? sprintf( __( 'The translation system refuses every call from this site: %s.', 'sitepress' ), $what )
			/* translators: Situation card, "What is wrong". %1$s: a date and time; %2$s: why, e.g. "it no longer knows this site's access key". */
			: sprintf( __( 'Since %1$s the translation system refuses every call from this site: %2$s.', 'sitepress' ), $since, $what );
	}

	public static function firstKeysRefused() {
		/* translators: Situation card, "What to do first", for keys the translation system refuses. */
		return __( 'Click <b>Register this site again</b>. Waiting does not fix this: your site gets new keys only when it registers again.', 'sitepress' );
	}

	public static function recKeysRefused() {
		/* translators: Situation card, "What we recommend", for keys the translation system refuses. */
		return __( 'Then click <b>Check delivery now</b>. If the keys are still refused, click <b>Have questions? Contact us</b>.', 'sitepress' );
	}

	public static function togetherKeysRefused() {
		/* translators: "Read together" line, for keys the translation system refuses. */
		return __( 'The translation system answers this site, but refuses the keys it signs its calls with. Nothing can be sent or collected until the site is registered again.', 'sitepress' );
	}

	public static function wrongSiteMoved( $registered, $current ) {
		if ( '' !== $registered && '' !== $current ) {
			/* translators: Situation card, "What is wrong". %1$s: the address the site is registered for; %2$s: the address it runs at. */
			return sprintf( __( 'The translation system refuses calls from this site: it is registered for %1$s and now runs at %2$s.', 'sitepress' ), $registered, $current );
		}

		/* translators: Situation card, "What is wrong", when the translation system no longer recognizes this site at its address. */
		return __( 'The translation system refuses calls from this site: its registration no longer matches the site\'s address (the site moved, or it is a copy of another site).', 'sitepress' );
	}

	public static function firstSiteMoved() {
		/* translators: Situation card, "What to do first", for a site the translation system no longer recognizes at its address. */
		return __( 'Click <b>Register this site again</b>. Waiting does not fix this: the translation system knows this site only at the address it was registered for.', 'sitepress' );
	}

	public static function recSiteMoved() {
		/* translators: Situation card, "What we recommend", for a site the translation system no longer recognizes at its address. */
		return __( 'Then click <b>Check delivery now</b>. If the registration still does not match, click <b>Have questions? Contact us</b>.', 'sitepress' );
	}

	public static function togetherSiteMoved() {
		/* translators: "Read together" line, for a site the translation system no longer recognizes at its address. */
		return __( 'The translation system answers this site, but no longer recognizes it at its address. Nothing can be sent or collected until the site is registered again.', 'sitepress' );
	}


	public static function togetherFailed( $failed ) {
		/* translators: "Read together" line. %d: a number of translations. */
		return sprintf( _n( 'The translation system delivered %d translation several times, and your site recorded an error each time it tried to save it. The fix is on this site.', 'The translation system delivered these %d translations several times, and your site recorded an error each time it tried to save them. The fix is on this site.', $failed, 'sitepress' ), $failed );
	}

	public static function togetherUnreachable( $at, $lastCall ) {
		if ( '' === $lastCall ) {
			/* translators: "Read together" line. %s: a time. */
			return sprintf( __( 'The translation system says nothing answered at %s. Its calls do not reach WordPress on this site.', 'sitepress' ), $at );
		}

		/* translators: "Read together" line. %1$s: a time; %2$s: a date and time. */
		return sprintf( __( 'The translation system says nothing answered at %1$s, and this site has received no call since %2$s. Its calls do not reach WordPress on this site.', 'sitepress' ), $at, $lastCall );
	}

	public static function togetherBlocked( $at, $lastCall ) {
		if ( '' === $lastCall ) {
			/* translators: "Read together" line. %s: a time. */
			return sprintf( __( 'The translation system says it called this site at %s; this site has received nothing. Something in front of WordPress answered.', 'sitepress' ), $at );
		}

		/* translators: "Read together" line. %1$s: a time; %2$s: a date and time. */
		return sprintf( __( 'The translation system says it called this site at %1$s; this site has received nothing since %2$s. Something in front of WordPress answered.', 'sitepress' ), $at, $lastCall );
	}

	public static function togetherSignature( $registered, $current ) {
		if ( '' === $registered ) {
			/* translators: "Read together" line. */
			return __( 'The translation system reaches this site and WordPress answers, but refuses the call.', 'sitepress' );
		}

		/* translators: "Read together" line. %1$s: the address the site is registered for; %2$s: the address it runs at. */
		return sprintf( __( 'The translation system reaches this site and WordPress answers, but refuses the call: the site is registered for %1$s and runs at %2$s.', 'sitepress' ), $registered, $current );
	}

	public static function togetherSilent( $at, $lastRun, $reason ) {
		/* translators: "Read together" line. %s: a time. */
		$text = sprintf( __( 'The translation system called at %s and your site answered, but nothing was collected', 'sitepress' ), $at );
		/* translators: Part of the "Read together" line. %s: a date and time. */
		$text .= '' === $lastRun ? '' : ' ' . sprintf( __( 'since %s', 'sitepress' ), $lastRun );

		return $text . ( 'loopback' === $reason
			/* translators: End of the "Read together" line. */
			? __( ': the site cannot start its own collection request.', 'sitepress' )
			: '.' );
	}


	public static function label( $key ) {
		$labels = [
			/* translators: Fact label in "What this site knows". */
			'in_progress'   => __( 'Translations in progress', 'sitepress' ),
			/* translators: Fact label in "What this site knows". */
			'last_collected' => __( 'Last collected', 'sitepress' ),
			/* translators: Fact label in "What this site knows". */
			'last_call_received' => __( 'Last call received', 'sitepress' ),
			/* translators: Fact label in both columns of the Translation delivery box. */
			'how_collects'  => __( 'How this site collects', 'sitepress' ),
			/* translators: Fact label in "What this site knows": whether the site can start its own collection request. */
			'by_itself'     => __( 'Collecting by itself', 'sitepress' ),
			/* translators: Fact label in "What this site knows". WP-Cron is WordPress's scheduler. */
			'wp_cron'       => __( 'Scheduled check (WP-Cron)', 'sitepress' ),
			/* translators: Fact label in "What this site knows": whether the translation system confirmed this site. */
			'accepted'      => __( 'Accepted', 'sitepress' ),
			/* translators: Fact label in "What this site knows", and title of the delivery check's result card. */
			'not_applied'   => __( 'Could not be applied', 'sitepress' ),
			/* translators: Fact label in "What this site knows". */
			'resend'        => __( 'Need to be sent again', 'sitepress' ),
			/* translators: Fact label in "What this site knows". */
			'registration'  => __( 'Registration', 'sitepress' ),
			/* translators: Fact label in "What the translation system sees". */
			'waiting'       => __( 'Finished, waiting', 'sitepress' ),
			/* translators: Fact label in "What the translation system sees". */
			'last_call'     => __( 'Last call', 'sitepress' ),
			/* translators: Fact label in "What the translation system sees". */
			'open_problem'  => __( 'Open problem', 'sitepress' ),
			/* translators: Fact label in "What the translation system sees". */
			'open_problems' => __( 'Open problems', 'sitepress' ),
			/* translators: Fact label in "What the translation system sees". */
			'recently_closed' => __( 'Recently closed', 'sitepress' ),
			/* translators: Heading of the list of addresses that get our delivery emails, in "What the translation system sees". */
			'recipients'    => __( 'Who gets our emails about this site', 'sitepress' ),
		];

		return isset( $labels[ $key ] ) ? $labels[ $key ] : '';
	}

	public static function none() {
		/* translators: Value of a fact in the Translation delivery box when there is nothing to list. */
		return __( 'None.', 'sitepress' );
	}

	public static function inProgress( $count, $since ) {
		if ( $count < 1 ) {
			/* translators: Fact value, "Translations in progress". */
			return __( 'None. Every translation sent from this site has arrived.', 'sitepress' );
		}

		if ( '' === $since ) {
			/* translators: Fact value, "Translations in progress". %d: a number of translations. */
			return sprintf( __( '%d sent to the translation system.', 'sitepress' ), $count );
		}

		/* translators: Fact value, "Translations in progress". %1$d: a number of translations; %2$s: a date and time. */
		return sprintf( __( '%1$d sent to the translation system, the oldest since %2$s.', 'sitepress' ), $count, $since );
	}

	public static function noOtherWaiting() {
		/* translators: Fact value, "Translations in progress", when translations could not be applied and nothing else is waiting. */
		return __( 'No other translations are waiting.', 'sitepress' );
	}

	public static function lastCollected( $at, $trigger, $applied ) {
		if ( null === $applied ) {
			/* translators: Fact value, "Last collected". %1$s: a date and time; %2$s: how the collection started, e.g. "after the translation system called". */
			return sprintf( __( '%1$s, %2$s.', 'sitepress' ), $at, self::trigger( $trigger ) );
		}

		/* translators: Fact value, "Last collected". %1$s: a date and time; %2$s: how the collection started, e.g. "after the translation system called"; %3$d: a number of translations. */
		return sprintf( __( '%1$s, %2$s: %3$d applied.', 'sitepress' ), $at, self::trigger( $trigger ), $applied );
	}

	public static function neverCollected() {
		/* translators: Fact value, "Last collected". */
		return __( 'Not yet.', 'sitepress' );
	}

	public static function trigger( $trigger ) {
		$words = [
			/* translators: How a collection started; completes "Today at 14:14, ...". */
			'manual'          => __( 'collected from this page', 'sitepress' ),
			/* translators: How a collection started; completes "Today at 14:14, ...". */
			'admin'           => __( 'when an admin logged in', 'sitepress' ),
			/* translators: How a collection started; completes "Today at 14:14, ...". */
			'cron'            => __( 'by the scheduled check', 'sitepress' ),
			/* translators: How a collection started; completes "Today at 14:14, ...". */
			'front'           => __( 'when a visitor opened the site', 'sitepress' ),
			/* translators: How a collection started; completes "Today at 14:14, ...". */
			'wake_background' => __( 'after the translation system called', 'sitepress' ),
			/* translators: How a collection started; completes "Today at 14:14, ...". */
			'wake_spawned'    => __( 'after the translation system called', 'sitepress' ),
			/* translators: How a collection started; completes "Today at 14:14, ...". */
			'drive'           => __( 'while the translation system walked the site through it', 'sitepress' ),
			/* translators: How a collection started; completes "Today at 14:14, ...". */
			'dashboard'       => __( 'when translation progress was checked', 'sitepress' ),
		];

		/* translators: How a collection started when the site did not record it. */
		return isset( $words[ $trigger ] ) ? $words[ $trigger ] : __( 'in the background', 'sitepress' );
	}

	public static function lastCallReceived( $at ) {
		if ( '' === $at ) {
			/* translators: Fact value, "Last call received". */
			return __( 'No call from the translation system received yet.', 'sitepress' );
		}

		/* translators: Fact value, "Last call received". %s: a date and time. */
		return sprintf( __( 'The translation system last called this site at %s and the site answered.', 'sitepress' ), $at );
	}

	public static function shape( $shape ) {
		$words = [
			/* translators: Fact value, "How this site collects". */
			'background' => __( 'Your site collects on its own after we tell it.', 'sitepress' ),
			/* translators: Fact value, "How this site collects". */
			'spawned'    => __( 'Your site starts its own collection request.', 'sitepress' ),
			/* translators: Fact value, "How this site collects". */
			'help'       => __( 'The translation system walks your site through the collection, one step at a time.', 'sitepress' ),
			/* translators: Fact value, "How this site collects", for a site the translation system never reached. */
			'island'     => __( 'Your site checks for translations itself when somebody uses it.', 'sitepress' ),
		];

		return isset( $words[ $shape ] ) ? $words[ $shape ] : '';
	}

	public static function saidItCollects( $capability ) {
		$words = [
			/* translators: Fact value, "How this site collects", in "What the translation system sees". */
			'background' => __( 'This site said it collects on its own after we tell it.', 'sitepress' ),
			/* translators: Fact value, "How this site collects", in "What the translation system sees". */
			'spawned'    => __( 'This site said it starts its own collection request.', 'sitepress' ),
			/* translators: Fact value, "How this site collects", in "What the translation system sees". */
			'help'       => __( 'This site said the translation system has to walk it through the collection.', 'sitepress' ),
		];

		return isset( $words[ $capability ] ) ? $words[ $capability ] : '';
	}

	public static function planWords( $plan ) {
		$words = [
			/* translators: Completes "Your site answered our notification at 14:02 and said it ...". */
			'background' => __( 'collects on its own after we tell it', 'sitepress' ),
			/* translators: Completes "Your site answered our notification at 14:02 and said it ...". */
			'spawned'    => __( 'starts its own collection request', 'sitepress' ),
		];

		return isset( $words[ $plan ] ) ? $words[ $plan ] : '';
	}

	public static function byItself( $ok, $at ) {
		if ( null === $ok ) {
			/* translators: Fact value, "Collecting by itself". */
			return __( 'Not checked yet.', 'sitepress' );
		}

		if ( '' === $at ) {
			return $ok
				/* translators: Fact value, "Collecting by itself". */
				? __( 'Your site can reach its own address.', 'sitepress' )
				/* translators: Fact value, "Collecting by itself". */
				: __( 'Your site cannot call its own address.', 'sitepress' );
		}

		return $ok
			/* translators: Fact value, "Collecting by itself". %s: a date and time. */
			? sprintf( __( 'Your site can reach its own address (checked %s).', 'sitepress' ), $at )
			/* translators: Fact value, "Collecting by itself". %s: a date and time. */
			: sprintf( __( 'Your site cannot call its own address (checked %s).', 'sitepress' ), $at );
	}

	public static function wpCron( array $cron, $lastRan ) {
		if ( $cron['disabled'] ) {
			/* translators: Fact value, "Scheduled check (WP-Cron)". DISABLE_WP_CRON is the setting's name and stays in English. */
			return __( 'Switched off on this site (DISABLE_WP_CRON); it runs only if a server cron job runs it.', 'sitepress' );
		}

		if ( (int) $cron['next'] < 1 ) {
			/* translators: Fact value, "Scheduled check (WP-Cron)". */
			return __( 'Not scheduled: nothing needs it now.', 'sitepress' );
		}

		$minutes = max( 1, (int) round( (int) $cron['interval'] / 60 ) );
		/* translators: Part of "Scheduled check (WP-Cron)". %d: a number of minutes. */
		$every = sprintf( _n( 'Every %d minute', 'Every %d minutes', $minutes, 'sitepress' ), $minutes );

		if ( '' === $lastRan ) {
			return $every . '.';
		}

		return $cron['overdue']
			/* translators: Fact value, "Scheduled check (WP-Cron)". %1$s: "Every 15 minutes"; %2$s: a date and time. */
			? sprintf( __( '%1$s, but it last ran on %2$s.', 'sitepress' ), $every, $lastRan )
			/* translators: Fact value, "Scheduled check (WP-Cron)". %1$s: "Every 15 minutes"; %2$s: a date and time. */
			: sprintf( __( '%1$s; last ran at %2$s.', 'sitepress' ), $every, $lastRan );
	}

	public static function acceptedValue( $acceptedAt, $refusedAt, $status ) {
		if ( '' !== $refusedAt ) {
			/* translators: Fact value, "Accepted". %1$s: a date and time; %2$d: an HTTP status code. */
			return sprintf( __( 'Not confirmed since %1$s (status %2$d). Your site keeps collecting translations.', 'sitepress' ), $refusedAt, (int) $status );
		}

		if ( '' !== $acceptedAt ) {
			/* translators: Fact value, "Accepted". %s: a date and time. */
			return sprintf( __( 'The translation system confirmed this site on %s.', 'sitepress' ), $acceptedAt );
		}

		/* translators: Fact value, "Accepted". */
		return __( 'Not confirmed yet.', 'sitepress' );
	}

	public static function acceptedKeysRefused( $refusedAt, $status ) {
		/* translators: Fact value, "Accepted". %1$s: a date and time; %2$d: an HTTP status code. */
		return sprintf( __( 'Refused since %1$s (status %2$d): the translation system does not accept this site\'s keys.', 'sitepress' ), $refusedAt, (int) $status );
	}

	public static function translations( $count ) {
		/* translators: Fact value before a list. %d: a number of translations. */
		return sprintf( _n( '%d translation:', '%d translations:', $count, 'sitepress' ), $count );
	}

	public static function resendValue( $count ) {
		/* translators: Fact value, "Need to be sent again". %d: a number of translations. */
		return sprintf( _n( '%d translation: the translation system no longer holds it.', '%d translations: the translation system no longer holds them.', $count, 'sitepress' ), $count );
	}

	public static function jobPair( $language ) {
		/* translators: Under a translation's title. %s: the target language. */
		return sprintf( __( '→ %s', 'sitepress' ), $language );
	}

	public static function recorded( $reason ) {
		/* translators: Under a translation that could not be applied. %s: the reason, e.g. "the translated content is longer than WordPress can store". */
		return sprintf( __( 'What your site recorded: %s.', 'sitepress' ), $reason );
	}

	public static function untitled() {
		/* translators: Shown in place of the title of a piece of content that has none. The brackets show that it is not a title of its own. */
		return __( '(no title)', 'sitepress' );
	}

	public static function reason( $kind ) {
		$words = [
			/* translators: Why a translation could not be applied. */
			'too_long'  => __( 'the translated content is longer than WordPress can store', 'sitepress' ),
			/* translators: Why a translation could not be applied. */
			'php_error' => __( 'a PHP error while saving (the details for support show it)', 'sitepress' ),
			/* translators: Why a translation could not be applied. */
			'database'  => __( 'the database refused the save', 'sitepress' ),
			/* translators: Why a translation could not be applied. */
			'save'      => __( 'your site could not save it', 'sitepress' ),
			/* translators: Why a translation could not be applied. */
			'download'  => __( 'your site could not download it', 'sitepress' ),
			/* translators: Why a translation could not be applied. */
			'sync'      => __( 'your site could not read its state from the translation system', 'sitepress' ),
		];

		return isset( $words[ $kind ] ) ? $words[ $kind ] : '';
	}

	public static function registeredFor( $url ) {
		/* translators: Fact value, "Registration". %s: a website address. */
		return sprintf( __( 'Registered for %s, the address this site runs at.', 'sitepress' ), $url );
	}

	public static function registeredForLabel() {
		/* translators: Label before the address the site key was registered for. */
		return __( 'Registered for', 'sitepress' );
	}

	public static function runsAtLabel() {
		/* translators: Label before the address this site runs at. */
		return __( 'Runs at', 'sitepress' );
	}

	public static function addressesDiffer() {
		/* translators: Fact value, "Registration", under the two addresses. */
		return __( 'The two addresses differ.', 'sitepress' );
	}


	public static function systemUnreachable() {
		/* translators: The right column of the Translation delivery box when WPML's translation service did not answer. */
		return __( 'This site could not reach the translation system just now, so this column is empty. Check delivery now says more.', 'sitepress' );
	}

	public static function systemNoRecord() {
		/* translators: The right column of the Translation delivery box. */
		return __( 'The translation system has no delivery record for this site yet.', 'sitepress' );
	}

	public static function systemNotRegistered() {
		/* translators: The right column of the Translation delivery box. */
		return __( 'This site is not connected to the translation system yet.', 'sitepress' );
	}

	public static function systemRefused() {
		/* translators: The right column of the Translation delivery box when the translation system refuses this site's keys. */
		return __( 'The translation system refuses this site\'s keys, so this column is empty.', 'sitepress' );
	}

	public static function finishedWaiting( $count, $since ) {
		if ( $count < 1 ) {
			/* translators: Fact value, "Finished, waiting". */
			return __( 'Nothing is waiting.', 'sitepress' );
		}

		if ( '' === $since ) {
			/* translators: Fact value, "Finished, waiting". %d: a number of translations. */
			return sprintf( _n( 'It holds %d finished translation for this site.', 'It holds %d finished translations for this site.', $count, 'sitepress' ), $count );
		}

		/* translators: Fact value, "Finished, waiting". %1$d: a number of translations; %2$s: a date and time. */
		return sprintf( _n( 'It holds %1$d finished translation for this site, the oldest since %2$s.', 'It holds %1$d finished translations for this site, the oldest since %2$s.', $count, 'sitepress' ), $count, $since );
	}

	public static function lastCall( $at, $class, $status, $path ) {
		if ( '' === $at ) {
			/* translators: Fact value, "Last call". */
			return __( 'It has not called this site yet.', 'sitepress' );
		}

		switch ( $class ) {
			case 'reached':
				/* translators: Fact value, "Last call". %s: a date and time. */
				return sprintf( __( 'It last called this site at %s; WordPress answered.', 'sitepress' ), $at );
			case 'unreachable':
				return null !== $status && $status > 0
					/* translators: Fact value, "Last call". %1$s: a date and time; %2$d: an HTTP status code. */
					? sprintf( __( 'It last called this site at %1$s; the site answered with a server error (status %2$d).', 'sitepress' ), $at, $status )
					/* translators: Fact value, "Last call". %s: a date and time. */
					: sprintf( __( 'It last called this site at %s; nothing answered.', 'sitepress' ), $at );
			case 'impostor':
				return null !== $status && $status > 0
					/* translators: Fact value, "Last call". %1$s: a date and time; %2$s: a web address path; %3$d: an HTTP status code. */
					? sprintf( __( 'It last called this site at %1$s; something other than WordPress answered %2$s with status %3$d.', 'sitepress' ), $at, $path, $status )
					/* translators: Fact value, "Last call". %1$s: a date and time; %2$s: a web address path. */
					: sprintf( __( 'It last called this site at %1$s; something other than WordPress answered %2$s.', 'sitepress' ), $at, $path );
			case 'signature_rejected':
				return null !== $status && $status > 0
					/* translators: Fact value, "Last call". %1$s: a date and time; %2$d: an HTTP status code. */
					? sprintf( __( 'It last called this site at %1$s; the site answered but did not accept the call (status %2$d).', 'sitepress' ), $at, $status )
					/* translators: Fact value, "Last call". %s: a date and time. */
					: sprintf( __( 'It last called this site at %s; the site answered but did not accept the call.', 'sitepress' ), $at );
			case 'no_handshake':
				/* translators: Fact value, "Last call". %s: a date and time. */
				return sprintf( __( 'It last called this site at %s; the site answered that it does not take these calls.', 'sitepress' ), $at );
			default:
				/* translators: Fact value, "Last call". %s: a date and time. */
				return sprintf( __( 'It last called this site at %s.', 'sitepress' ), $at );
		}
	}

	public static function condition( $template ) {
		$words = [
			/* translators: An open delivery problem; completes "Since 14:02: ...". */
			'failed_on_site'   => __( 'translations could not be applied', 'sitepress' ),
			/* translators: An open delivery problem; completes "Since 14:02: ...". */
			'unreachable'      => __( 'we cannot reach your site', 'sitepress' ),
			/* translators: An open delivery problem; completes "Since 14:02: ...". */
			'blocked'          => __( 'something is blocking your translations', 'sitepress' ),
			/* translators: An open delivery problem; completes "Since 14:02: ...". */
			'signature'        => __( 'your site did not accept our notification', 'sitepress' ),
			/* translators: An open delivery problem; completes "Since 14:02: ...". */
			'hears_but_silent' => __( 'your translations are waiting', 'sitepress' ),
		];

		/* translators: An open delivery problem of a kind the site does not know; completes "Since 14:02: ...". */
		return isset( $words[ $template ] ) ? $words[ $template ] : __( 'a delivery problem', 'sitepress' );
	}

	public static function openProblem( $template, $since, $mail ) {
		$text = '' === $since
			? self::condition( $template )
			/* translators: Fact value, "Open problem". %1$s: a date and time; %2$s: the problem, e.g. "we cannot reach your site". */
			: sprintf( __( 'Since %1$s: %2$s', 'sitepress' ), $since, self::condition( $template ) );

		return $text . ( '' === $mail ? '.' : '; ' . $mail . '.' );
	}

	public static function mailNotSent() {
		/* translators: Part of "Open problem", when our email about it was not sent. */
		return __( 'no email went out', 'sitepress' );
	}

	public static function mailNotSentAllStopped() {
		/* translators: Part of "Open problem": no email went out because, when it was due, every recipient had stopped our notifications. */
		return __( 'no email went out: everyone who gets our notifications for this site had stopped them', 'sitepress' );
	}

	public static function mailWent( $at, $to ) {
		if ( $to > 0 ) {
			/* translators: Part of "Open problem". %1$d: a number of addresses; %2$s: a time. */
			return sprintf( _n( 'an email went to %1$d address at %2$s', 'an email went to %1$d addresses at %2$s', $to, 'sitepress' ), $to, $at );
		}

		/* translators: Part of "Open problem". %s: a time. */
		return sprintf( __( 'an email went out at %s', 'sitepress' ), $at );
	}

	public static function recentlyClosed( $count, $words ) {
		if ( null === $words ) {
			/* translators: Fact value, "Recently closed". %d: a number of translations. */
			return sprintf( _n( '%d translation closed.', '%d translations closed.', $count, 'sitepress' ), $count );
		}

		/* translators: Fact value, "Recently closed". %1$d: a number of translations; %2$s: a number of words. */
		return sprintf( _n( '%1$d translation closed; %2$s words returned.', '%1$d translations closed; %2$s words returned.', $count, 'sitepress' ), $count, number_format_i18n( $words ) );
	}

	public static function stoppedCalling( $at ) {
		/* translators: Fact value, "Last call". %s: a date and time. */
		return sprintf( __( 'It stopped calling this site on %s, after a long time without a collection; it calls again once the site collects.', 'sitepress' ), $at );
	}

	public static function getsThem( $address ) {
		/* translators: Under "Who gets our emails about this site". %s: an email address. */
		return sprintf( __( '%s gets them.', 'sitepress' ), $address );
	}

	public static function stopped( $address, $scope, $since ) {
		if ( 'all' === $scope ) {
			return '' === $since
				/* translators: %s: an email address. */
				? sprintf( __( 'Notifications are stopped for %s for every site.', 'sitepress' ), $address )
				/* translators: %1$s: an email address; %2$s: a date. */
				: sprintf( __( 'Notifications are stopped for %1$s for every site (since %2$s).', 'sitepress' ), $address, $since );
		}

		return '' === $since
			/* translators: %s: an email address. */
			? sprintf( __( 'Notifications about this site are stopped for %s.', 'sitepress' ), $address )
			/* translators: %1$s: an email address; %2$s: a date. */
			: sprintf( __( 'Notifications about this site are stopped for %1$s (since %2$s).', 'sitepress' ), $address, $since );
	}

	public static function resumeThem() {
		/* translators: Link after a line saying that delivery notifications are stopped for an address. */
		return __( 'Resume them', 'sitepress' );
	}

	public static function allBlocked() {
		/* translators: Warning at the top of the Translation delivery box when every translation manager stopped our delivery emails. */
		return __( '<b>Nobody receives our notifications about this site; you will only learn about delivery problems here.</b> To get them again, use “Resume them” under “Who gets our emails about this site”.', 'sitepress' );
	}


	public static function siteEvent( $kind, array $data ) {
		switch ( $kind ) {
			case 'wake_received':
				/* translators: An event this site recorded. */
				return __( 'the translation system called; collection started', 'sitepress' );
			case 'wake_rejected':
				/* translators: An event this site recorded. */
				return __( 'a call that did not carry this site\'s signature was refused', 'sitepress' );
			case 'drive_received':
				$written = isset( $data['written'] ) ? (int) $data['written'] : 0;
				/* translators: An event this site recorded. %d: a number of translations. */
				return sprintf( __( 'the translation system walked the site through a collection: %d applied', 'sitepress' ), $written );
			case 'run_start':
				if ( isset( $data['trigger'] ) && 'cron' === $data['trigger'] ) {
					/* translators: An event this site recorded. */
					return __( 'scheduled check ran', 'sitepress' );
				}
				return '';
			case 'run_end':
				$written = isset( $data['written'] ) ? (int) $data['written'] : 0;
				/* translators: An event this site recorded. %d: a number of translations. */
				return sprintf( __( 'collection finished: %d applied', 'sitepress' ), $written );
			case 'declined':
				return empty( $data['ok'] )
					/* translators: An event this site recorded. */
					? __( 'cancelling a translation did not reach the translation system', 'sitepress' )
					/* translators: An event that this site or the translation system recorded. */
					: __( 'a translation was cancelled; its words returned', 'sitepress' );
			case 'closure_seen':
				if ( isset( $data['closure'] ) && 'failed_on_site' === $data['closure'] ) {
					/* translators: An event this site recorded. */
					return __( 'a translation could not be applied', 'sitepress' );
				}
				/* translators: An event this site recorded. */
				return __( 'a translation was closed', 'sitepress' );
			case 'unknown_seen':
				/* translators: An event this site recorded. */
				return __( 'the translation system had no record of a translation', 'sitepress' );
			case 'retry_sent':
				if ( isset( $data['outcome'] ) && RetryAnswers::IN_PROGRESS === $data['outcome'] ) {
					/* translators: An event this site recorded. */
					return __( 'a retry was already running', 'sitepress' );
				}
				return empty( $data['ok'] )
					/* translators: An event this site recorded. */
					? __( 'a retry did not reach the translation system', 'sitepress' )
					/* translators: An event this site recorded. */
					: __( 'a translation was sent for another try', 'sitepress' );
			case 'acceptance_recorded':
				/* translators: An event this site recorded. */
				return __( 'the translation system confirmed this site', 'sitepress' );
			case 'acceptance_refused':
				/* translators: An event this site recorded. %d: an HTTP status code. */
				return sprintf( __( 'the translation system answered without confirming this site (status %d)', 'sitepress' ), isset( $data['status'] ) ? (int) $data['status'] : 0 );
			case 'loopback_probe':
				return empty( $data['ok'] )
					/* translators: An event this site recorded. */
					? __( 'checked its own address: no answer', 'sitepress' )
					/* translators: An event this site recorded. */
					: __( 'checked its own address: it answers', 'sitepress' );
			default:
				return '';
		}
	}

	public static function systemEvent( $kind, array $data, $path ) {
		$status = isset( $data['status'] ) && is_numeric( $data['status'] ) ? (int) $data['status'] : 0;

		switch ( $kind ) {
			case 'completed':
				/* translators: An event the translation system recorded. */
				return __( '1 translation ready for this site', 'sitepress' );
			case 'wakeup_reply':
				/* translators: An event the translation system recorded. */
				return __( 'called your site: WordPress answered', 'sitepress' );
			case 'wakeup_failed':
				$outcome = isset( $data['outcome'] ) ? (string) $data['outcome'] : '';
				if ( 'impostor' === $outcome ) {
					return $status > 0
						/* translators: An event the translation system recorded. %1$s: a web address path; %2$d: an HTTP status code. */
						? sprintf( __( 'called %1$s: something other than WordPress answered %2$d', 'sitepress' ), $path, $status )
						/* translators: An event the translation system recorded. %s: a web address path. */
						: sprintf( __( 'called %s: something other than WordPress answered', 'sitepress' ), $path );
				}
				if ( 'signature_rejected' === $outcome ) {
					/* translators: An event the translation system recorded. %d: an HTTP status code. */
					return sprintf( __( 'called your site: not accepted (status %d)', 'sitepress' ), $status );
				}
				if ( 'server_error' === $outcome && $status > 0 ) {
					/* translators: An event the translation system recorded. %d: an HTTP status code. */
					return sprintf( __( 'called your site: a server error (status %d)', 'sitepress' ), $status );
				}
				/* translators: An event the translation system recorded. */
				return __( 'called your site: nothing answered', 'sitepress' );
			case 'drive_reply':
				/* translators: An event the translation system recorded. */
				return __( 'walked your site through a collection', 'sitepress' );
			case 'confirmed':
				/* translators: An event the translation system recorded. */
				return __( 'your site collected 1 translation', 'sitepress' );
			case 'declined':
				/* translators: An event that this site or the translation system recorded. */
				return __( 'a translation was cancelled; its words returned', 'sitepress' );
			case 'closed_failed_on_site':
				/* translators: An event the translation system recorded. */
				return __( 'stopped offering 1 translation; its words returned', 'sitepress' );
			case 'closed_not_needed':
				/* translators: An event the translation system recorded. */
				return __( 'closed 1 translation your site no longer needed', 'sitepress' );
			case 'horizon_flagged':
				/* translators: An event the translation system recorded. */
				return __( 'stopped calling your site after a long time without a collection', 'sitepress' );
			case 'client_message_sent':
				/* translators: An event the translation system recorded. */
				return __( 'email sent about a delivery problem', 'sitepress' );
			case 'client_condition_cleared':
				/* translators: An event the translation system recorded. */
				return __( 'the delivery problem closed', 'sitepress' );
			case 'notification_block':
				/* translators: An event the translation system recorded. */
				return __( 'an address stopped our notifications', 'sitepress' );
			case 'notification_unblock':
				/* translators: An event the translation system recorded. */
				return __( 'an address resumed our notifications', 'sitepress' );
			default:
				return '';
		}
	}


	public static function action( $action ) {
		$labels = [
			/* translators: Button in the Translation delivery box, and a search line on WPML > Support that leads to that box. */
			'check'      => __( 'Check delivery now', 'sitepress' ),
			/* translators: Button in the Translation delivery box. */
			'checkagain' => __( 'Check again', 'sitepress' ),
			/* translators: Button in the Translation delivery box. */
			'collect'    => __( 'Collect now', 'sitepress' ),
			/* translators: Button in the Translation delivery box: asks for every translation that could not be applied to be delivered again. */
			'retry'      => __( 'Retry all', 'sitepress' ),
			/* translators: Button in the Translation delivery box: sends again the translations the translation system no longer holds. */
			'resend'     => __( 'Resend', 'sitepress' ),
			/* translators: Button in the Translation delivery box: opens WPML support with this problem. */
			'report'     => __( 'Report', 'sitepress' ),
			/* translators: Button in the Translation delivery box. */
			'cancel'     => __( 'Cancel waiting translations', 'sitepress' ),
			/* translators: Button in the Translation delivery box. */
			'rereg'      => __( 'Re-register this site', 'sitepress' ),
			/* translators: Button in the Translation delivery box: registers this site with the translation system again, for new keys. */
			'register'   => __( 'Register this site again', 'sitepress' ),
			/* translators: Link in the Translation delivery box that opens WPML support. */
			'contact'    => __( 'Have questions? Contact us', 'sitepress' ),
		];

		return isset( $labels[ $action ] ) ? $labels[ $action ] : '';
	}


	public static function checkingTitle() {
		/* translators: Title of the card while the delivery check runs. */
		return __( 'Checking delivery…', 'sitepress' );
	}

	public static function checkingSteps() {
		return [
			/* translators: A step of the delivery check, shown while it runs. */
			__( 'Collecting what is waiting', 'sitepress' ),
			/* translators: A step of the delivery check, shown while it runs. */
			__( 'Asking the translation system to call this site now', 'sitepress' ),
			/* translators: A step of the delivery check, shown while it runs. */
			__( 'Checking this site\'s own settings (its own address, WP-Cron, the registered address)', 'sitepress' ),
		];
	}

	public static function checkingNote() {
		/* translators: Under the steps of the delivery check while it runs. */
		return __( 'This takes up to 20 seconds. You can stay on this page.', 'sitepress' );
	}

	public static function cardTitle( $card ) {
		$titles = [
			/* translators: Status line of the Translation delivery box, and title of the delivery check's result card. */
			'all_good'           => __( 'All good', 'sitepress' ),
			/* translators: Fact label in "What this site knows", and title of the delivery check's result card. */
			'failed_on_site'     => __( 'Could not be applied', 'sitepress' ),
			/* translators: Title of the delivery check's result card. */
			'unreachable'        => __( 'Cannot be reached', 'sitepress' ),
			/* translators: Title of the delivery check's result card. */
			'blocked'            => __( 'Something is blocking', 'sitepress' ),
			/* translators: Title of the delivery check's result card. */
			'signature'          => __( 'Registration does not match', 'sitepress' ),
			/* translators: Title of the delivery check's result card. */
			'hears_but_silent'   => __( 'Answers but does not collect', 'sitepress' ),
			/* translators: Title of the delivery check's result card. */
			'no_handshake'       => __( 'Does not take the calls', 'sitepress' ),
			/* translators: Title of the delivery check's result card. */
			'no_record'          => __( 'No delivery record yet', 'sitepress' ),
			/* translators: Title of the delivery check's result card. */
			'system_unreachable' => __( 'The translation system did not answer', 'sitepress' ),
			/* translators: Title of the delivery check's result card. */
			'keys_refused'       => __( 'Keys refused', 'sitepress' ),
			/* translators: Title of the delivery check's result card, when the translation system no longer recognizes this site at its address. */
			'site_moved'         => __( 'Registration no longer matches', 'sitepress' ),
		];

		return isset( $titles[ $card ] ) ? $titles[ $card ] : '';
	}

	public static function cardAllGood( $applied ) {
		if ( $applied < 1 ) {
			/* translators: Body of the "All good" card. */
			return __( 'Your site collects translations. Nothing was waiting, so nothing needed to be applied.', 'sitepress' );
		}

		/* translators: Body of the "All good" card. %d: a number of translations. */
		return sprintf( _n( 'Your site collects translations. %d was applied just now.', 'Your site collects translations. %d were applied just now.', $applied, 'sitepress' ), $applied );
	}

	public static function cardReached() {
		/* translators: Body of the "All good" card, second line. */
		return __( 'The translation system called your site just now and WordPress answered.', 'sitepress' );
	}

	public static function cardFailed( $failed ) {
		/* translators: Body of the "Could not be applied" card. %d: a number of translations. */
		return sprintf( _n( '%d translation could not be applied. Nothing else was waiting.', '%d translations could not be applied. Nothing else was waiting.', $failed, 'sitepress' ), $failed );
	}

	public static function cardFailedFix() {
		/* translators: Body of the "Could not be applied" card, after the list. */
		return __( 'Fix what each error names. Then retry.', 'sitepress' );
	}

	public static function cardUnreachable( $status, $path ) {
		return [
			null !== $status && $status > 0
				/* translators: Body of the "Cannot be reached" card. %1$d: an HTTP status code; %2$s: a web address path. */
				? sprintf( __( 'The translation system could not reach this site just now (a server error, status %1$d, at %2$s).', 'sitepress' ), $status, $path )
				/* translators: Body of the "Cannot be reached" card. %s: a web address path. */
				: sprintf( __( 'The translation system could not reach this site just now (no answer at %s).', 'sitepress' ), $path ),
			/* translators: Body of the "Cannot be reached" card, second paragraph. %s: a web address path. */
			sprintf( __( 'Check that the site is online and that %s answers. Then check again.', 'sitepress' ), $path ),
		];
	}

	public static function cardBlocked( $path, $status, $what ) {
		$answered = [
			/* translators: What answered instead of WordPress, in brackets. */
			'redirect'  => __( 'a redirect', 'sitepress' ),
			/* translators: What answered instead of WordPress, in brackets. */
			'challenge' => __( 'a challenge page', 'sitepress' ),
			/* translators: What answered instead of WordPress, in brackets. */
			'page'      => __( 'a security page', 'sitepress' ),
		];
		$from = isset( $answered[ $what ] ) ? $answered[ $what ] : $answered['page'];

		return [
			null !== $status && $status > 0
				/* translators: Body of the "Something is blocking" card. %1$s: a web address path; %2$d: an HTTP status code; %3$s: what answered, e.g. "a security page". */
				? sprintf( __( 'At %1$s something other than WordPress answered with %2$d (%3$s).', 'sitepress' ), $path, $status, $from )
				/* translators: Body of the "Something is blocking" card. %1$s: a web address path; %2$s: what answered, e.g. "a security page". */
				: sprintf( __( 'At %1$s something other than WordPress answered (%2$s).', 'sitepress' ), $path, $from ),
			/* translators: Body of the "Something is blocking" card, second paragraph. %s: a web address path. */
			sprintf( __( 'Allow requests from WPML\'s translation system to %s. Then check again.', 'sitepress' ), $path ),
		];
	}

	public static function cardSignature( $registered, $current ) {
		if ( '' === $registered ) {
			return [
				/* translators: Body of the "Registration does not match" card. */
				__( 'This site did not accept the translation system\'s call: its registration no longer matches.', 'sitepress' ),
				/* translators: Body of the "Registration does not match" card, second paragraph. */
				__( 'Re-register the site. If it is a copy of another site, give it its own site key.', 'sitepress' ),
			];
		}

		return [
			/* translators: Body of the "Registration does not match" card. %1$s: the address the site is registered for; %2$s: the address it runs at. */
			sprintf( __( 'This site is registered for %1$s; it now runs at %2$s.', 'sitepress' ), $registered, $current ),
			/* translators: Body of the "Registration does not match" card, second paragraph. %s: this site's address. */
			sprintf( __( 'Re-register the site for %s. If it is a copy of another site, give it its own site key.', 'sitepress' ), $current ),
		];
	}

	public static function cardSilent( $reason ) {
		$why = [
			/* translators: Completes "Your site answers but cannot collect in the background: ...". */
			'loopback' => __( 'it cannot call its own address', 'sitepress' ),
			/* translators: Completes "Your site answers but cannot collect in the background: ...". WP-Cron is WordPress's scheduler. */
			'cron'     => __( 'WP-Cron does not run', 'sitepress' ),
			/* translators: Completes "Your site answers but cannot collect in the background: ...". */
			'host'     => __( 'the host finishes nothing after it answers', 'sitepress' ),
		];
		$key = isset( $why[ $reason ] ) ? $reason : 'host';

		return [
			/* translators: Body of the "Answers but does not collect" card. %s: why, e.g. "it cannot call its own address". */
			sprintf( __( 'Your site answers but cannot collect in the background: %s.', 'sitepress' ), $why[ $key ] ),
			self::recSilent( $key ),
		];
	}

	public static function cardNoHandshake() {
		/* translators: Body of the delivery check's card, for a site that answered the translation system's call with "no such route". */
		return __( 'The translation system reached this site, but the site answered that it does not know these calls. Update WPML; if it is up to date, contact us.', 'sitepress' );
	}

	public static function cardNoRecord() {
		/* translators: Body of the delivery check's card. */
		return __( 'The translation system has no delivery record for this site yet, so it could not call it.', 'sitepress' );
	}

	public static function cardSystemUnreachable() {
		/* translators: Body of the delivery check's card. */
		return __( 'This site could not reach the translation system, so it could not ask it to call back. Check again in a few minutes; if it repeats, contact us.', 'sitepress' );
	}

	public static function cardKeysRefused() {
		/* translators: Body of the delivery check's card when the translation system refused this site's keys. */
		return __( 'The translation system refused this site\'s keys, so it could not call your site. Waiting does not fix this. Click <b>Register this site again</b>.', 'sitepress' );
	}

	public static function cardSiteMoved() {
		/* translators: Body of the delivery check's card when the translation system no longer recognizes this site at its address (wpmldev-12517). */
		return __( 'The translation system no longer recognizes this site at its address: it moved, or it is a copy of another site. Waiting does not fix this. Click <b>Register this site again</b>.', 'sitepress' );
	}

	public static function registeredTitle() {
		/* translators: Title of the card after "Register this site again" worked. */
		return __( 'This site is registered again', 'sitepress' );
	}

	public static function registeredBody() {
		/* translators: Body of the card after "Register this site again" worked. */
		return __( 'It has new keys. The box below shows what the translation system sees now.', 'sitepress' );
	}

	public static function registerFailedTitle() {
		/* translators: Title of the card when "Register this site again" did not work. */
		return __( 'Registering this site again did not work', 'sitepress' );
	}

	public static function registerFailedBody() {
		/* translators: Body of the card when "Register this site again" did not work. */
		return __( 'The translation system did not accept the registration. Click <b>Have questions? Contact us</b>.', 'sitepress' );
	}

	public static function cardThrottled() {
		/* translators: Note on the delivery check's card when the translation system checked the site less than a minute ago. */
		return __( 'Checked less than a minute ago, so the translation system did not call again. You can check again in a minute.', 'sitepress' );
	}

	public static function collectedWhileChecking( $applied ) {
		if ( $applied < 1 ) {
			/* translators: First line of the delivery check's card. */
			return __( '<b>Collected while checking:</b> nothing was waiting.', 'sitepress' );
		}

		/* translators: First line of the delivery check's card. %d: a number of translations. */
		return sprintf( _n( '<b>Collected while checking:</b> %d translation applied.', '<b>Collected while checking:</b> %d translations applied.', $applied, 'sitepress' ), $applied );
	}

	public static function collectedNothingWhileChecking( $waiting ) {
		/* translators: Line of the delivery check's card. %d: a number of translations. */
		return sprintf( _n( '<b>Collected while checking:</b> nothing, although %d translation is finished and waiting for your site.', '<b>Collected while checking:</b> nothing, although %d translations are finished and waiting for your site.', $waiting, 'sitepress' ), $waiting );
	}

	public static function collectedNothingWhileWaiting( $waiting ) {
		/* translators: Body of the "Collect now" card. %d: a number of translations. */
		return sprintf( _n( 'Nothing was collected, although %d translation is finished and waiting for your site.', 'Nothing was collected, although %d translations are finished and waiting for your site.', $waiting, 'sitepress' ), $waiting );
	}

	public static function collectBusy() {
		/* translators: Line of a card when another collection was already running. */
		return __( 'A collection was already running, so none was started now.', 'sitepress' );
	}

	public static function problemClosed() {
		/* translators: Delivery check's card, after "Collected while checking: ...", when the delivery problem closed while the check ran. */
		return __( 'The problem is closed.', 'sitepress' );
	}

	public static function stillCannotCall( $cronRuns = true ) {
		if ( ! $cronRuns ) {
			/* translators: Delivery check's card, second line, when the problem closed but the translation system's call to the site still failed, on a site where WP-Cron does not run. */
			return __( 'The translation system still could not call your site. Your translations arrive when this page runs.', 'sitepress' );
		}

		/* translators: Delivery check's card, second line, when the problem closed but the translation system's call to the site still failed. */
		return __( 'The translation system still could not call your site. Your translations arrive when this page or the 15-minute check runs.', 'sitepress' );
	}

	public static function collectedTitle( $applied ) {
		if ( $applied < 1 ) {
			/* translators: Title of the "Collect now" card. */
			return __( 'Nothing to collect', 'sitepress' );
		}

		/* translators: Title of the "Collect now" card. %d: a number of translations. */
		return sprintf( _n( '%d translation applied', '%d translations applied', $applied, 'sitepress' ), $applied );
	}

	public static function collectedBody( $applied, $failed ) {
		if ( $applied < 1 && $failed < 1 ) {
			/* translators: Body of the "Collect now" card. */
			return __( 'Nothing was waiting.', 'sitepress' );
		}

		/* translators: Body of the "Collect now" card. */
		$text = __( 'Your translations are on your site now.', 'sitepress' );

		if ( $failed > 0 ) {
			/* translators: Body of the "Collect now" card, second sentence. %d: a number of translations. */
			$text .= ' ' . sprintf( _n( '%d could not be applied.', '%d could not be applied.', $failed, 'sitepress' ), $failed );
		}

		return $text;
	}

	public static function retriedTitle( $retried ) {
		/* translators: Title of the "Retry all" card. %d: a number of translations. */
		return sprintf( _n( '%d translation will be collected again', '%d translations will be collected again', $retried, 'sitepress' ), $retried );
	}

	public static function retriedBody( $retried, $failed ) {
		/* translators: Body of the "Retry all" card. */
		$text = _n( 'Retry charges the words again. This box shows it once it arrives.', 'Retry charges the words again. This box shows them once they arrive.', $retried, 'sitepress' );

		if ( $failed > 0 ) {
			$text .= ' ' . self::retryFailed( $failed );
		}

		return $text;
	}

	public static function retryFailed( $failed ) {
		/* translators: Body of the "Retry all" card, second sentence. %d: a number of translations. */
		return sprintf( _n( '%d could not be sent again; click Report.', '%d could not be sent again; click Report.', $failed, 'sitepress' ), $failed );
	}

	public static function retryRefusedTitle( $refused ) {
		/* translators: Title of the "Retry all" card. %d: a number of translations. */
		return sprintf( _n( '%d translation was not retried', '%d translations were not retried', $refused, 'sitepress' ), $refused );
	}

	public static function retryInProgress( $count ) {
		/* translators: Body of the "Retry all" card. %d: a number of translations. */
		return sprintf( _n( 'Retry is already running for %d translation. This box shows it once it arrives.', 'Retry is already running for %d translations. This box shows them once they arrive.', $count, 'sitepress' ), $count );
	}

	public static function retryRefused( $reason, $words, $balanceWords = null, $left = null ) {
		$words = number_format_i18n( (int) $words );

		switch ( $reason ) {
			case RetryAnswers::REASON_WAITING_PAYMENT:
				/* translators: Body of the "Retry all" card. %s: a number of words. */
				return sprintf( __( 'Retry needs %s words. Your automatic payment is in progress. Wait for it, then click <b>Retry all</b> again.', 'sitepress' ), $words );
			case RetryAnswers::REASON_SPEND_CAP:
				if ( null === $left ) {
					/* translators: Body of the "Retry all" card. %s: a number of words. "<a>" and "</a>" wrap a link: keep them. */
					return sprintf( __( 'Retry needs %s words, more than this site\'s spending limit leaves. <a>Raise the limit on your account</a>, then click <b>Retry all</b> again.', 'sitepress' ), $words );
				}

				/* translators: Body of the "Retry all" card. %1$s: a number of words; %2$s: the words the site's spending limit leaves. "<a>" and "</a>" wrap a link: keep them. */
				return sprintf( __( 'Retry needs %1$s words, and this site\'s spending limit leaves %2$s. <a>Raise the limit on your account</a>, then click <b>Retry all</b> again.', 'sitepress' ), $words, number_format_i18n( max( 0, (int) $left ) ) );
			default:
				if ( null === $balanceWords ) {
					/* translators: Body of the "Retry all" card. %s: a number of words. "<a>" and "</a>" wrap a link: keep them. */
					return sprintf( __( 'Retry needs %s words. <a>Buy words</a>, then click <b>Retry all</b> again.', 'sitepress' ), $words );
				}

				/* translators: Body of the "Retry all" card. %1$s: a number of words; %2$s: the words the account has. "<a>" and "</a>" wrap a link: keep them. */
				return sprintf( __( 'Retry needs %1$s words and your account has %2$s. <a>Buy words</a>, then click <b>Retry all</b> again.', 'sitepress' ), $words, number_format_i18n( max( 0, (int) $balanceWords ) ) );
		}
	}

	public static function resentTitle( $resent ) {
		/* translators: Title of the "Resend" card. %d: a number of translations. */
		return sprintf( _n( '%d translation sent again', '%d translations sent again', $resent, 'sitepress' ), $resent );
	}

	public static function cancelQuestion( $count ) {
		/* translators: Title of the confirmation before cancelling. %d: a number of translations. */
		return sprintf( _n( 'Cancel %d waiting translation?', 'Cancel %d waiting translations?', $count, 'sitepress' ), $count );
	}

	public static function cancelExplain() {
		return [
			/* translators: Body of the confirmation before cancelling. */
			__( 'The translation system stops holding them, and <b>the words they were charged go back to your account</b>. The exact number shows when it is done.', 'sitepress' ),
			/* translators: Body of the confirmation before cancelling, second paragraph. */
			__( 'You can send these pages for translation again later.', 'sitepress' ),
		];
	}

	public static function cancelKeep() {
		/* translators: Button of the confirmation before cancelling: closes it without cancelling. */
		return __( 'Keep them', 'sitepress' );
	}

	public static function cancelConfirm( $count ) {
		/* translators: Button of the confirmation before cancelling. %d: a number of translations. */
		return sprintf( _n( 'Cancel %d translation', 'Cancel %d translations', $count, 'sitepress' ), $count );
	}

	public static function cancelNothing() {
		/* translators: Shown instead of the cancel question when nothing is waiting. */
		return __( 'Nothing is waiting for this site, so there is nothing to cancel.', 'sitepress' );
	}

	public static function cancelledTitle( $cancelled ) {
		/* translators: Title of the card after cancelling. %d: a number of translations. */
		return sprintf( _n( '%d translation cancelled', '%d translations cancelled', $cancelled, 'sitepress' ), $cancelled );
	}

	public static function cancelledBody( $words, $failed, $unconfirmed = 0 ) {
		if ( null === $words && $unconfirmed > 0 ) {
			/* translators: Body of the card after cancelling, when the translation system did not answer the cancel yet. */
			$text = __( 'The translation system has not confirmed it yet. Your site tells it again the next time it contacts it, and the words go back to your account then.', 'sitepress' );
		} elseif ( null === $words ) {
			/* translators: Body of the card after cancelling, when the translation system did not confirm the words it returned. */
			$text = __( 'The return shows in your account\'s words history.', 'sitepress' );
		} else {
			/* translators: Body of the card after cancelling. %s: a number of words. */
			$text = sprintf( __( '%s words are back in your account. The return shows in your account\'s words history.', 'sitepress' ), number_format_i18n( $words ) );

			if ( $unconfirmed > 0 ) {
				/* translators: Body of the card after cancelling, next sentence. %d: a number of translations. */
				$text .= ' ' . sprintf( _n( 'The translation system has not confirmed %d of them yet; your site tells it again the next time it contacts it.', 'The translation system has not confirmed %d of them yet; your site tells it again the next time it contacts it.', $unconfirmed, 'sitepress' ), $unconfirmed );
			}
		}

		if ( $failed > 0 ) {
			/* translators: Body of the card after cancelling, second sentence. %d: a number of translations. */
			$text .= ' ' . sprintf( _n( '%d could not be cancelled; try again or contact us.', '%d could not be cancelled; try again or contact us.', $failed, 'sitepress' ), $failed );
		}

		return $text;
	}

	public static function panelNote() {
		/* translators: Note on a card after an action: the box below shows the new state. */
		return __( 'This panel now shows the new state.', 'sitepress' );
	}

	public static function outcomeError() {
		/* translators: Shown when an action of the Translation delivery box did not finish. */
		return __( 'This did not finish. Try again; if it repeats, contact us.', 'sitepress' );
	}

	public static function errorTitle() {
		/* translators: Title of the card when an action did not finish. */
		return __( 'Did not finish', 'sitepress' );
	}

	public static function notAllowed() {
		/* translators: Shown when someone who is not an administrator tries an action of the Translation delivery box. */
		return __( 'Only an administrator of this site can do this.', 'sitepress' );
	}


	public static function searchLines() {
		return [
			/* translators: A search line on WPML > Support that leads to the Translation delivery box, in the words clients use. */
			__( 'Pages not translated', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Translations stuck', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Translation stuck', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Translations stuck in progress', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Automatic translation not working', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Translations waiting in the translation queue', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Pending translations', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Translations not delivered', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Credits used but nothing translated', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Translations taking too long', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Translation could not be applied', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Retry translations that failed', 'sitepress' ),
			/* translators: Button in the Translation delivery box, and a search line on WPML > Support that leads to that box. */
			__( 'Check delivery now', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Cancel waiting translations and get my words back', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Site moved: register it again for translations', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Firewall or security plugin blocks translations', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box. */
			__( 'Stop or resume translation notification emails', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box: the translation system answers this site without confirming how it delivers. */
			__( 'Translation system did not confirm this site', 'sitepress' ),
			/* translators: A search line on WPML > Support that leads to the Translation delivery box: translations wait for words, a payment or a spending limit. */
			__( 'Translations on hold: out of words or payment', 'sitepress' ),
		];
	}


	public static function stalledReason( $cause ) {
		switch ( $cause ) {
			case Stalled::CREDITS:
				/* translators: On the WPML translation dashboard: automatic translations wait because the site has no AI translation words left. */
				return __( 'Translations are waiting: you are out of AI translation words.', 'sitepress' );
			case Stalled::PAYMENT_PENDING:
				/* translators: On the WPML translation dashboard: automatic translations wait while the Pay-As-You-Go charge for them is processed. */
				return __( 'Translations are waiting while your payment is processed. They continue on their own once it goes through.', 'sitepress' );
			case Stalled::PAYMENT_DECLINED:
				/* translators: On the WPML translation dashboard: the Pay-As-You-Go charge for automatic translations failed. */
				return __( 'Translations are stopped: your payment could not be processed.', 'sitepress' );
			case Stalled::SPEND_CAP:
				/* translators: On the WPML translation dashboard: automatic translations wait because the site reached the monthly spending limit the client set. */
				return __( 'Translations are paused: your monthly spending limit is reached.', 'sitepress' );
			case Stalled::CLIENT_BLOCKED:
				/* translators: On the WPML translation dashboard: the translation service refuses automatic translation for this account. */
				return __( 'Translations are stopped: automatic translation is blocked for your account.', 'sitepress' );
			case Stalled::PAYMENT_METHOD:
				/* translators: On the WPML translation dashboard: automatic translations wait because the account has no way set to pay for them (no Pay-As-You-Go, no words assigned). */
				return __( 'Translations are waiting: choose how to pay for them.', 'sitepress' );
			default:
				return '';
		}
	}

	public static function stalledHold( $count, $since ) {
		if ( '' === $since ) {
			/* translators: How many automatic translations are on hold. %d: a number of translations. */
			return sprintf( _n( '%d translation is on hold.', '%d translations are on hold.', $count, 'sitepress' ), $count );
		}

		/* translators: How many automatic translations are on hold and since when. %1$d: a number of translations; %2$s: a time, or a date and time. */
		return sprintf( _n( '%1$d translation is on hold since %2$s.', '%1$d translations are on hold since %2$s.', $count, 'sitepress' ), $count, $since );
	}

	public static function stalledAction( $cause ) {
		switch ( $cause ) {
			case Stalled::CREDITS:
				/* translators: Button after the line that says automatic translations wait for words; it opens the window where AI translation words are bought. Verb, imperative. */
				return __( 'Buy words', 'sitepress' );
			case Stalled::PAYMENT_DECLINED:
				/* translators: Link after the line that says the payment for automatic translations failed. Verb, imperative. */
				return __( 'Update your payment details to continue', 'sitepress' );
			case Stalled::SPEND_CAP:
				/* translators: Link after the line that says the monthly spending limit is reached; it opens the AI Translation: billing and usage page in WordPress admin, where the limit shows. Verb, imperative. */
				return __( 'Raise the limit', 'sitepress' );
			case Stalled::CLIENT_BLOCKED:
				/* translators: Link after the line that says automatic translation is blocked for the account. Verb, imperative. */
				return __( 'Contact support', 'sitepress' );
			case Stalled::PAYMENT_METHOD:
				/* translators: Link after the line that says automatic translations wait until the client chooses how to pay; it opens the AI Translation: billing and usage page in WordPress admin, or the WPML account's words page when the account holds words. Verb, imperative. */
				return __( 'Choose a payment method', 'sitepress' );
			default:
				return '';
		}
	}

	public static function stalledAssign() {
		/* translators: Button after the line that says automatic translations wait for words, when the WPML account holds words not given to this site yet; it opens the window where those words are assigned to this site. Verb, imperative. */
		return __( 'Assign words to this site', 'sitepress' );
	}

	public static function stalledAdminBar( $cause ) {
		switch ( $cause ) {
			case Stalled::CREDITS:
				/* translators: Short text in the WordPress admin bar: automatic translations wait for AI translation words. */
				return __( 'Translations waiting: out of words', 'sitepress' );
			case Stalled::PAYMENT_PENDING:
				/* translators: Short text in the WordPress admin bar: automatic translations wait for a payment being processed. */
				return __( 'Translations waiting: payment in progress', 'sitepress' );
			case Stalled::PAYMENT_DECLINED:
				/* translators: Short text in the WordPress admin bar: the payment for automatic translations failed. */
				return __( 'Translations stopped: payment failed', 'sitepress' );
			case Stalled::SPEND_CAP:
				/* translators: Short text in the WordPress admin bar: the monthly spending limit is reached. */
				return __( 'Translations paused: spending limit reached', 'sitepress' );
			case Stalled::CLIENT_BLOCKED:
				/* translators: Short text in the WordPress admin bar: automatic translation is blocked for the account. */
				return __( 'Translations stopped: account blocked', 'sitepress' );
			case Stalled::PAYMENT_METHOD:
				/* translators: Short text in the WordPress admin bar: automatic translations wait until the client chooses how to pay. */
				return __( 'Translations waiting: choose how to pay', 'sitepress' );
			default:
				return '';
		}
	}

	public static function stalledLabel( $cause ) {
		switch ( $cause ) {
			case Stalled::CREDITS:
				/* translators: Fact label in "What this site knows": translations wait for AI translation words. */
				return __( 'Waiting for words', 'sitepress' );
			case Stalled::PAYMENT_PENDING:
				/* translators: Fact label in "What this site knows": translations wait while a payment is processed. */
				return __( 'Waiting for payment', 'sitepress' );
			case Stalled::PAYMENT_DECLINED:
				/* translators: Fact label in "What this site knows": translations wait because a payment failed. */
				return __( 'Payment failed', 'sitepress' );
			case Stalled::SPEND_CAP:
				/* translators: Fact label in "What this site knows": translations wait because the monthly spending limit is reached. */
				return __( 'Spending limit reached', 'sitepress' );
			case Stalled::CLIENT_BLOCKED:
				/* translators: Fact label in "What this site knows": automatic translation is blocked for the account. */
				return __( 'Account blocked', 'sitepress' );
			case Stalled::PAYMENT_METHOD:
				/* translators: Fact label in "What this site knows": translations wait until the client chooses how to pay. */
				return __( 'Payment method needed', 'sitepress' );
			default:
				return '';
		}
	}

	public static function headlineStalled( $cause = '' ) {
		if ( '' === self::stalledAction( (string) $cause ) && '' !== (string) $cause ) {
			/* translators: Headline of the Translation delivery box when delivery works but some translations wait while a Pay-As-You-Go payment is processed. */
			return __( 'Delivery works. Some translations are on hold until your payment goes through.', 'sitepress' );
		}

		/* translators: Headline of the Translation delivery box when delivery works but some translations wait for the client (words, a payment or a spending limit). */
		return __( 'Delivery works. Some translations are on hold until you act: see below.', 'sitepress' );
	}

	public static function stalledReasonUnassigned() {
		/* translators: On the WPML translation dashboard: automatic translations wait because no AI translation words are assigned to this site, while the WPML account still holds some. */
		return __( 'Translations are waiting: this site has no AI translation words assigned.', 'sitepress' );
	}


	public static function correctionsNoticeHeading( $pages ) {
		return sprintf(
			/* translators: Heading of the admin notice that says the translation system improved translations this site already has and offers them again. %d: a number of pages. */
			_n( 'An updated translation is available for %d page', 'Updated translations are available for %d pages', (int) $pages, 'sitepress' ),
			(int) $pages
		);
	}

	public static function correctionsNoticePromise() {
		/* translators: In the admin notice about updated translations: nothing on the site changes before the client accepts the updates, and the updates cost no AI translation words. */
		return __( 'Nothing changes until you accept them. No words are charged.', 'sitepress' );
	}

	public static function correctionsReview() {
		/* translators: Button of the admin notice about updated translations: opens the list of the updated translations. */
		return __( 'Review the updated translations', 'sitepress' );
	}

	public static function correctionsHide() {
		/* translators: Link of the admin notice about updated translations: hides the notice for this offer. */
		return __( 'Hide this notice', 'sitepress' );
	}

	public static function correctionsTab( $count ) {
		return sprintf(
			/* translators: Name of the tab of WPML → Translations that lists the updated translations waiting for the client's decision. %d: how many (one per page and language). */
			__( 'Updated translations (%d)', 'sitepress' ),
			(int) $count
		);
	}

	public static function correctionsTitle() {
		/* translators: Heading of the page that lists the translations the translation system updated and offers to this site. */
		return __( 'Updated translations', 'sitepress' );
	}

	public static function correctionsBatchIntro( $count, $date ) {
		return sprintf(
			/* translators: One line per offer on the Updated translations page. %1$d: how many translations (one per page and language), %2$s: the date the translation system updated them. */
			_n( 'The translation system updated %1$d translation on %2$s.', 'The translation system updated %1$d translations on %2$s.', (int) $count, 'sitepress' ),
			(int) $count,
			(string) $date
		);
	}

	public static function correctionsNoReasonIntro() {
		/* translators: On the Updated translations page, after the line about one offer, when the translation system gave no reason for the update. */
		return __( 'No reason was given.', 'sitepress' );
	}

	public static function correctionsPromise() {
		/* translators: On the Updated translations page: nothing on the site changes before the client accepts, and the updates cost no AI translation words. */
		return __( 'Nothing changes until you accept. No words are charged for these updates.', 'sitepress' );
	}

	public static function correctionsAcceptQuestion() {
		/* translators: Collapsed question on the Updated translations page; opening it shows what accepting does. */
		return __( 'What happens when I accept?', 'sitepress' );
	}

	public static function correctionsAcceptAnswer() {
		/* translators: The answer to "What happens when I accept?" on the Updated translations page. */
		return __( 'WPML collects the accepted translations from the translation system and updates the pages, as it does for any new translation. The pages keep their publish status. The translation editor shows the new text the next time you open it.', 'sitepress' );
	}

	public static function correctionsSkipQuestion() {
		/* translators: Collapsed question on the Updated translations page; opening it shows what skipping does. */
		return __( 'What happens when I skip?', 'sitepress' );
	}

	public static function correctionsSkipAnswer() {
		/* translators: The answer to "What happens when I skip?" on the Updated translations page. */
		return __( 'The page keeps the translation it has now. The translation system closes the update. You can ask us to send it again later.', 'sitepress' );
	}

	public static function correctionsSelectAll() {
		/* translators: Screen-reader label of the checkbox in the header of the Updated translations table, which ticks or clears every row. */
		return __( 'Select all', 'sitepress' );
	}

	public static function correctionsPageColumn() {
		/* translators: Column heading on the Updated translations page: the page whose translation was updated. */
		return __( 'Page', 'sitepress' );
	}

	public static function correctionsLanguageColumn() {
		/* translators: Column heading and field label in the WPML admin, for the language of a piece of content, for example on the Updated translations page (the language of the updated translation). Noun, singular. */
		return __( 'Language', 'sitepress' );
	}

	public static function correctionsWhyColumn() {
		/* translators: Column heading on the Updated translations page: why the translation system updated the translation. */
		return __( 'Why', 'sitepress' );
	}

	public static function correctionsNoteColumn() {
		/* translators: Column heading on the Updated translations page: a note about the row, for example that the translation was edited on this site. */
		return __( 'Note', 'sitepress' );
	}

	public static function correctionsNoReason() {
		/* translators: In the Why column of the Updated translations page, when the translation system gave no reason. */
		return __( 'No reason given', 'sitepress' );
	}

	public static function correctionsMore() {
		/* translators: Toggle in the Why column of the Updated translations page that shows the rest of a long reason. */
		return __( 'More', 'sitepress' );
	}

	public static function correctionsEdited() {
		/* translators: In the Note column of the Updated translations page: the translation was changed in the WordPress editor of this site after the translation system delivered it. */
		return __( 'Edited on this site', 'sitepress' );
	}

	public static function correctionsPageDeleted() {
		/* translators: In the Page column of the Updated translations page, when the page no longer exists on this site. */
		return __( 'Page deleted', 'sitepress' );
	}

	public static function correctionsApply( $count ) {
		if ( (int) $count < 1 ) {
			/* translators: The main button of the Updated translations page while no row is ticked (the button is disabled). */
			return __( 'Apply selected', 'sitepress' );
		}

		return sprintf(
			/* translators: The main button of the Updated translations page. %d: how many rows are ticked. */
			_n( 'Apply %d selected', 'Apply %d selected', (int) $count, 'sitepress' ),
			(int) $count
		);
	}

	public static function correctionsSkipAll() {
		/* translators: Button of the Updated translations page that declines every update shown. */
		return __( 'Skip all', 'sitepress' );
	}

	public static function correctionsDecideLater() {
		/* translators: Link of the Updated translations page that goes back to the dashboard and leaves the updates waiting. */
		return __( 'Decide later', 'sitepress' );
	}

	public static function correctionsAccepted( $count ) {
		return sprintf(
			/* translators: Shown on the Updated translations page after Apply. %d: how many updated translations were accepted. */
			_n( '%d translation accepted. WPML is collecting it now; the page updates within a few minutes.', '%d translations accepted. WPML is collecting them now; the pages update within a few minutes.', (int) $count, 'sitepress' ),
			(int) $count
		);
	}

	public static function correctionsSkipped( $count ) {
		return sprintf(
			/* translators: Shown on the Updated translations page after Skip all. %d: how many updates were declined. */
			_n( '%d update skipped. Your page keeps its current translation.', '%d updates skipped. Your pages keep their current translations.', (int) $count, 'sitepress' ),
			(int) $count
		);
	}

	public static function correctionsNoAnswer() {
		/* translators: Shown on the Updated translations page when the translation system did not answer the client's decision. */
		return __( 'The translation system did not answer. Nothing was changed. Try again in a few minutes.', 'sitepress' );
	}

	public static function correctionsEmpty() {
		/* translators: The Updated translations page when no update waits for the client's decision. */
		return __( 'No updated translations are waiting.', 'sitepress' );
	}


	public static function supportSettings() {
		/* translators: Collapsed section at the bottom of the Translation delivery box, with settings a WPML supporter may change for this site. */
		return __( 'Settings for support', 'sitepress' );
	}

	public static function chunkLabel() {
		/* translators: Label of a number field in the Translation delivery box's "Settings for support": how many translations the site asks the translation system about in one request. */
		return __( 'Translations per request', 'sitepress' );
	}

	public static function chunkReadout( $value, $isDefault, $default ) {
		if ( $isDefault ) {
			/* translators: %d: a number. In the Translation delivery box's "Settings for support". */
			return sprintf( __( '%d. This is the default for this site.', 'sitepress' ), (int) $value );
		}

		/* translators: %1$d: the number set for this site, %2$d: the default. In the Translation delivery box's "Settings for support". */
		return sprintf( __( '%1$d, set for this site. The default is %2$d.', 'sitepress' ), (int) $value, (int) $default );
	}

	public static function chunkHelp() {
		/* translators: %1$d and %2$d: the smallest and the largest number allowed. Help under a number field in the Translation delivery box's "Settings for support". */
		return sprintf( __( 'How many translations this site asks about each time it checks which ones are ready. From %1$d to %2$d. A higher number collects a large backlog faster.', 'sitepress' ), \WPML\TM\ATE\Sync\SyncChunk::MIN, \WPML\TM\ATE\Sync\SyncChunk::MAX );
	}

	public static function chunkButton( $which ) {
		return 'default' === $which
			/* translators: Button in the Translation delivery box's "Settings for support": goes back to the default number. */
			? __( 'Use the default', 'sitepress' )
			/* translators: Button label that keeps what was entered, for example in the Translation delivery box's "Settings for support" and in the "Translation editor and method" section of WPML → Settings. Verb, imperative. */
			: __( 'Save', 'sitepress' );
	}

	public static function chunkSavedTitle( $value ) {
		/* translators: %d: a number. Card title in the Translation delivery box after a supporter saved the number of translations per request. */
		return sprintf( __( 'Saved: %d translations per request', 'sitepress' ), (int) $value );
	}

	public static function chunkDefaultTitle( $value ) {
		/* translators: %d: a number. Card title in the Translation delivery box after a supporter went back to the default number of translations per request. */
		return sprintf( __( 'Back to the default: %d translations per request', 'sitepress' ), (int) $value );
	}

	public static function chunkClamped( $value ) {
		/* translators: %1$d and %2$d: the smallest and the largest number allowed, %3$d: the number saved. In a card of the Translation delivery box. */
		return sprintf( __( 'The number must be from %1$d to %2$d, so %3$d was saved.', 'sitepress' ), \WPML\TM\ATE\Sync\SyncChunk::MIN, \WPML\TM\ATE\Sync\SyncChunk::MAX, (int) $value );
	}

	public static function chunkInvalid() {
		/* translators: %1$d and %2$d: the smallest and the largest number allowed. In a card of the Translation delivery box when the number field held no number. */
		return sprintf( __( 'Enter a whole number from %1$d to %2$d.', 'sitepress' ), \WPML\TM\ATE\Sync\SyncChunk::MIN, \WPML\TM\ATE\Sync\SyncChunk::MAX );
	}

	public static function chunkNotSavedTitle() {
		/* translators: Card title in the Translation delivery box when the number of translations per request was not saved. */
		return __( 'Not saved', 'sitepress' );
	}
}
