<?php

namespace WPML\TM\ATE\API;

use WPML\Core\Component\ATE\Domain\Credits\DebtorBlock;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\WpmlOrgOrigin;
use WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl;

class ErrorMessages {


	public static function serverUnavailable( $uuid, $rawResponse = null ) {
		$message = [
			'header'      => self::serverUnavailableHeader(),
			'description' => self::invalidResponseDescription( $uuid ),
		];

		return ErrorHandler::createError( $message, $rawResponse );
	}

	public static function offline( $uuid, $rawResponse = null ) {
		/* translators: First half of the message shown when WPML cannot reach the translation service. "It seems" is about the site's own server. */
		$description = _x( 'WPML needs an Internet connection to translate your site\'s content. It seems that your server is not allowing external connections, or your network is temporarily down.', 'part1', 'sitepress' );
		/* translators: Second half of the message shown when WPML cannot reach the translation service; "this message" is the message above. %1$s: a link, already wrapped in its tags, whose text is "WPML support", %2$s: the number that stands for this site. */
		$description .= _x( 'If this is the first time you\'re seeing this message, please wait a minute and reload the page. If the problem persists, contact %1$s for help and mention that your website ID is %2$s.', 'part2', 'sitepress' );

		$message = [
			'header'      => __( 'Cannot Connect to the Internet', 'sitepress' ),
			'description' => sprintf( $description, self::getSupportLink(), $uuid ),
		];

		return ErrorHandler::createError( $message, $rawResponse );
	}

	public static function invalidResponse( $uuid, $rawResponse = null ) {
		$message = [
			'header'      => __( 'WPML\'s Advanced Translation Editor is not working', 'sitepress' ),
			'description' => self::invalidResponseDescription( $uuid ),
		];

		return ErrorHandler::createError( $message, $rawResponse );
	}

	public static function respondedWithError() {
		return __( "WPML's Advanced Translation Editor responded with an error", 'sitepress' );
	}

	public static function serverUnavailableHeader() {
		return __( 'WPML’s Advanced Translation Editor is not responding', 'sitepress' );
	}

	public static function invalidResponseDescription( $uuid ) {
		/* translators: First half of the message shown when WPML cannot reach the translation editor; "this message" is this message itself. */
		$description = _x( 'WPML cannot connect to the translation editor. If this is the first time you’re seeing this message, please wait a minute and reload the page.', 'part1', 'sitepress' );
		/* translators: Second half of the message shown when WPML cannot reach the translation editor. %1$s: a link, already wrapped in its tags, whose text is "WPML support", %2$s: the number that stands for this site. */
		$description .= _x( 'If the problem persists, contact %1$s for help and mention that your website ID is %2$s.', 'part2', 'sitepress' );

		return sprintf( $description, self::getSupportLink(), $uuid );
	}

	public static function getSupportLink() {
		$support_url = \WPML\OutboundLinks\OutboundLinks::to(
			SupportForumUrl::URL,
			array(
				'medium'   => 'notice',
				'campaign' => 'support',
			)
		);

		return '<a href="' . esc_url( $support_url ) . '" target="_blank" rel="noreferrer">'
		       /* translators: Link text inside a sentence about a problem with automatic translation; it opens the WPML support pages. It is the name of the support team, a noun. */
		       . __( 'WPML support', 'sitepress' ) . '</a>';
	}

	public static function bodyWithoutRequiredFields() {
		return __( 'The body does not contain the required fields', 'sitepress' );
	}

	public static function ateNotActive() {
		$billingLink = '<a href="' . esc_url( wpml_tm_get_ams_ate_console_url() ) . '">'
		               /* translators: Link text inside a sentence about a problem with automatic translation; it is the name of the WPML billing screen and opens it. */
		               . esc_html__( 'AI Translation: billing and usage', 'sitepress' ) . '</a>';

		$supportLink = '<a href="' . esc_url( WpmlOrgOrigin::map( SupportForumUrl::URL ) ) . '" target="_blank" rel="noreferrer">'
		               /* translators: Link text inside a sentence about a problem with automatic translation; it opens the WPML support pages. Verb phrase, imperative: get in touch with the support team. */
		               . esc_html__( 'contact support', 'sitepress' ) . '</a>';

		$message = __( 'WPML cannot send these documents to translation because we couldn\'t confirm your site\'s account status.', 'sitepress' )
		           . "\n\n";

		return $message . sprintf(
			/* translators: Message shown when automatic translation was refused. %1$s: a link, already wrapped in its tags, whose text is the name of the billing screen, %2$s: a link whose text is "contact support". */
			__( 'Check your %1$s page for any account issues, or %2$s if the issue persists.', 'sitepress' ),
			$billingLink,
			$supportLink
		);
	}

	public static function clientRestricted() {
		$link = '<a href="' . esc_url( WpmlOrgOrigin::map( SupportForumUrl::URL ) ) . '" target="_blank" rel="noreferrer">'
		        /* translators: Link text inside a sentence about a problem with automatic translation; it opens the WPML support pages. Noun: the support team. */
		        . esc_html__( 'support', 'sitepress' ) . '</a>';

		return sprintf(
			/* translators: %s is a link to the WPML support forum, with the link text "support". */
			__( 'We noticed something unexpected in your recent translation activity and paused automatic translation on your account. Contact %s and we’ll review it with you.', 'sitepress' ),
			$link
		);
	}

	public static function clientRestrictedByUnpaidBalance( string $debtorBlock ): string {
		$billingLink = '<a href="' . esc_url( wpml_tm_get_ams_ate_console_url() ) . '">'
		               /* translators: Link text inside a sentence about a problem with automatic translation; it is the name of the WPML billing screen and opens it. */
		               . esc_html__( 'AI Translation: billing and usage', 'sitepress' ) . '</a>';
		$supportLink = '<a href="' . esc_url( WpmlOrgOrigin::map( SupportForumUrl::URL ) ) . '" target="_blank" rel="noreferrer">'
		               /* translators: Link text inside a sentence about a problem with automatic translation; it opens the WPML support pages. Noun: the support team. */
		               . esc_html__( 'support', 'sitepress' ) . '</a>';

		$message = DebtorBlock::BILLING_EMAIL === $debtorBlock
			/* translators: Message shown when automatic translation was refused because another account that uses the same billing e-mail address owes money. %1$s: a link whose text is the name of the WPML billing screen, %2$s: a link whose text is "support". */
			? __( 'Automatic translation is paused. There is an unpaid balance on another account linked to this billing e-mail. Settle it on the %1$s page or contact %2$s.', 'sitepress' )
			/* translators: Message shown when automatic translation was refused because another account that uses the same payment card owes money. %1$s: a link whose text is the name of the WPML billing screen, %2$s: a link whose text is "support". */
			: __( 'Automatic translation is paused. There is an unpaid balance on another account linked to this card. Settle it on the %1$s page or contact %2$s.', 'sitepress' );

		return sprintf( $message, $billingLink, $supportLink );
	}

}
