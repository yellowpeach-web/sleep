<?php

namespace WPML\TM\ATE\ClonedSites;

use function WPML\Container\make;

class ReconnectNotice implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const SCRIPT_HANDLE = 'wpml-connection-status';

	const BOOTSTRAP_GLOBAL = 'wpmlConnectionState';

	const STATE_HANDLE = 'wpml-connection-state';

	private $auth;

	public function __construct( \WPML_TM_ATE_Authentication $auth ) {
		$this->auth = $auth;
	}

	public function add_hooks() {
		add_filter( 'wpml_tm_dashboard_notices', [ $this, 'addDashboardNotice' ] );
		add_action( 'admin_notices', [ $this, 'renderEscalatedNotice' ] );
		add_action( 'admin_notices', [ $this, 'renderQuietOnAteScreens' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueOnAteScreens' ] );
	}

	public function enqueueOnAteScreens() {
		if ( ! self::isAteScreen() && ! self::isSetupScreen() ) {
			return;
		}

		self::probeOnPageLoad();
		self::enqueueComponent( (string) $this->auth->get_site_id() );
	}

	private static function probeOnPageLoad() {
		if ( ApiCommunication::probedThisRequest() ) {
			return;
		}

		if ( ! ReconnectState::get() || ! ReconnectState::isNoAnswer() || ! ReconnectState::isReconnecting() ) {
			return;
		}

		if ( ReconnectState::openedByCreate() && ReconnectState::isDue() && ReconnectState::hasReadAnswered() ) {
			return;
		}

		try {
			make( ConnectionProbe::class )->run( false );
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}

	public function addDashboardNotice( $notices ) {
		if ( ! ReconnectState::isReconnecting() ) {
			return $notices;
		}

		$notices   = is_array( $notices ) ? $notices : [];
		$notices[] = function () {
			echo wp_kses( $this->render(), self::allowedTags() );
		};

		return $notices;
	}

	public function renderEscalatedNotice() {
		if ( ! ReconnectState::isReconnecting() || ! ReconnectState::hasEscalated() ) {
			return;
		}

		if ( ReconnectState::isEscalationDismissed( $this->diagnosticCode() ) ) {
			return;
		}

		echo wp_kses( $this->render(), self::allowedTags() );
	}

	public function renderQuietOnAteScreens() {
		if ( ! ReconnectState::isReconnecting() || ! self::isAteScreen() ) {
			return;
		}

		if ( ReconnectState::hasEscalated() && ! ReconnectState::isEscalationDismissed( $this->diagnosticCode() ) ) {
			return;
		}

		echo wp_kses( $this->render(), self::allowedTags() );
	}

	public static function allowedTags() {
		return [
			'div'      => [ 'class' => true, 'id' => true, 'data-wpml-connection-status' => true, 'style' => true ],
			'p'        => [ 'class' => true, 'style' => true ],
			'span'     => [ 'aria-hidden' => true, 'style' => true ],
			'svg'      => [ 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'xmlns' => true, 'aria-hidden' => true, 'focusable' => true ],
			'path'     => [ 'd' => true, 'fill' => true ],
			'a'        => [ 'class' => true, 'href' => true, 'target' => true, 'rel' => true, 'style' => true ],
			'code'     => [],
			'strong'   => [],
			'br'       => [],
			'label'    => [ 'for' => true ],
			'textarea' => [ 'id' => true, 'readonly' => true, 'rows' => true, 'cols' => true, 'style' => true ],
		];
	}

	public static function isComponentScreen() {
		return self::isAteScreen() || self::isSetupScreen();
	}

	private static function isSetupScreen() {
		return defined( 'WPML_PLUGIN_FOLDER' ) && WPML_PLUGIN_FOLDER . '/menu/setup.php' === self::currentPage();
	}

	private static function currentPage() {
		$page = isset( $GLOBALS['plugin_page'] ) ? $GLOBALS['plugin_page'] : '';

		if ( is_string( $page ) && '' !== $page ) {
			return $page;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $uri || false === strpos( $uri, 'page=' ) ) {
			return '';
		}

		$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		$args  = [];
		parse_str( $query, $args );

		return isset( $args['page'] ) && is_string( $args['page'] ) ? $args['page'] : '';
	}

	public static function isAteScreen() {
		$page = self::currentPage();

		if ( 'wpml-ai-translation-billing' === $page ) {
			return true;
		}

		if ( ! defined( 'WPML_TM_FOLDER' ) ) {
			return false;
		}

		return WPML_TM_FOLDER . '/menu/main.php' === $page
			   || WPML_TM_FOLDER . '/menu/settings' === $page;
	}

	public function render() {
		if ( ReconnectState::isNoAnswer() ) {
			return self::renderFallback( 'banner' );
		}

		if ( ! ReconnectState::hasEscalated() ) {
			return self::renderQuiet();
		}

		if ( ReconnectState::isEscalationDismissed( $this->diagnosticCode() ) ) {
			return self::renderQuiet();
		}

		return $this->renderEscalated();
	}

	private function diagnosticCode() {
		return ReconnectState::diagnosticCode( (string) $this->auth->get_site_id() );
	}

	public static function renderQuiet( $inline = false ) {
		return '<div class="notice' . ( $inline ? ' inline' : '' ) . ' notice-info wpml-ate-reconnecting">'
		       . NoticeBrand::row()
		       . '<p>'
		       . esc_html__( 'WPML is reconnecting to our translation system.', 'sitepress' )
		       . '</p></div>';
	}

	public static function renderFallback( $placement = 'banner' ) {
		$placement = in_array( $placement, [ 'banner', 'inline', 'wizard' ], true ) ? $placement : 'banner';
		$class     = 'wpml-connection-status wpml-connection-status--' . $placement;

		if ( 'banner' === $placement ) {
			$class = 'notice notice-warning ' . $class;
		}

		/* translators: Button label in a notice about reaching the translation service: try it again straight away. Verb phrase, imperative. */
		$retryNow = esc_html__( 'Retry now', 'sitepress' );
		/* translators: Button label in a notice: do the same thing once more. Verb, imperative. */
		$retry = esc_html__( 'Retry', 'sitepress' );

		$seconds = max( 1, ReconnectState::secondsUntilProbe() ?: ReconnectState::PROBE_FLOOR_SECONDS );

		$createOpened = ReconnectState::openedByCreate();
		/* translators: Shown in a notice about reaching the translation server, after sending content for translation failed: sending again is what checks the connection. Imperative. */
		$sendAgain = esc_html__( 'Send again to check the connection.', 'sitepress' );

		if ( 'cannot-access' === ReconnectState::displayPhase() ) {
			$host = ReconnectState::host();
			$text = '' !== $host
				? sprintf(
					/* translators: %s: the host name of the translation server WPML could not reach (Amir's text, 2026-09-03) */
					esc_html__( "WPML cannot access our server at %s. Please check that your hosting isn't blocking this access.", 'sitepress' ),
					esc_html( $host )
				)
				: esc_html__( "WPML cannot access our server. Please check that your hosting isn't blocking this access.", 'sitepress' );
			$countdown = $createOpened
				? '<p class="wpml-connection-status__remedy description">' . $sendAgain . '</p>'
				: '<p class="wpml-connection-status__countdown description">' . sprintf(
					/* translators: %d: seconds until the next automatic attempt to reach the translation server */
					esc_html__( 'Retrying again in %d s…', 'sitepress' ),
					$seconds
				) . '</p>';
			$controls  = '<a class="button wpml-connection-status__retry" href="' . esc_url( ReconnectActions::retryUrl() ) . '">' . $retry . '</a>'
				. self::supportLink();
		} else {
			/* translators: Shown in a notice after sending content for translation failed because the translation server did not answer. */
			$cantReachNow = esc_html__( "WPML can't reach the translation server right now.", 'sitepress' );
			$text         = $createOpened
				? $cantReachNow . ' ' . $sendAgain
				: sprintf(
					/* translators: %d: seconds until the next attempt to reach the translation server */
					esc_html__( "WPML can't reach our translation server. Retrying in %d s…", 'sitepress' ),
					$seconds
				);
			$countdown = '';
			$controls  = '<a class="button wpml-connection-status__retry" href="' . esc_url( ReconnectActions::retryUrl() ) . '">' . $retryNow . '</a>';
			if ( $createOpened && ReconnectState::isSupportBlockDue() ) {
				$controls .= self::supportLink();
			}
		}

		return '<div class="' . esc_attr( $class ) . '" data-wpml-connection-status="' . esc_attr( $placement ) . '">'
			. '<p class="wpml-connection-status__text">' . $text . '</p>'
			. '<p class="wpml-connection-status__controls">' . $controls . '</p>'
			. $countdown
			. '</div>';
	}

	private static function supportLink() {
		$supportUrl = \WPML\OutboundLinks\OutboundLinks::to(
			'https://app.wpml.org/support',
			array(
				'medium'   => 'notice',
				'campaign' => 'support',
			)
		);

		return '<a class="wpml-connection-status__support-link" style="margin-left:12px" href="' . esc_url( $supportUrl ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'Need help? Contact WPML support', 'sitepress' )
			. '</a>';
	}

	public static function renderInlineFallback() {
		return self::renderFallback( 'inline' );
	}

	private static function configuredHosts() {
		try {
			$endpoints = make( \WPML_TM_ATE_AMS_Endpoints::class );

			return [
				'ate' => (string) $endpoints->get_ATE_host(),
				'ams' => (string) $endpoints->get_AMS_host(),
			];
		} catch ( \Throwable $e ) {
			return [ 'ate' => '', 'ams' => '' ];
		}
	}

	public static function bootstrap( $siteId = '' ) {
		$state = ReconnectState::clientState( (string) $siteId );

		$state['configuredHosts'] = self::configuredHosts();
		$state['rest']            = [
			'status' => rest_url( ConnectionStatusEndpoint::REST_NAMESPACE . ConnectionStatusEndpoint::ROUTE_STATUS ),
			'probe'  => rest_url( ConnectionStatusEndpoint::REST_NAMESPACE . ConnectionStatusEndpoint::ROUTE_PROBE ),
			'hide'   => rest_url( ConnectionStatusEndpoint::REST_NAMESPACE . ConnectionStatusEndpoint::ROUTE_HIDE ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
		];
		$state['widgetScriptId'] = \WPML\TM\ATE\Dashboard\ATEDashboardLoader::ATE_DASHBOARD_ID . '-js';
		$state['pollSeconds']    = ReconnectState::PROBE_FLOOR_SECONDS;
		$state['bannerScreen'] = self::isAteScreen();

		return $state;
	}

	public static function enqueueComponent( $siteId = null ) {
		if ( ! defined( 'WPML_PUBLIC_DIR' ) || ! function_exists( 'wp_register_script' ) ) {
			return;
		}

		if ( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		if ( null === $siteId ) {
			try {
				$siteId = (string) make( \WPML_TM_ATE_Authentication::class )->get_site_id();
			} catch ( \Throwable $e ) {
				$siteId = '';
			}
		}

		$version = defined( 'WPML_VERSION' ) ? WPML_VERSION : ICL_SITEPRESS_SCRIPT_VERSION;

		if ( ! wp_script_is( self::STATE_HANDLE, 'registered' ) ) {
			wp_register_script( self::STATE_HANDLE, '', [], $version, true );
		}

		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			wp_register_script(
				self::SCRIPT_HANDLE,
				plugins_url( 'public/js/connection-status.js', WPML_PUBLIC_DIR ),
				[ 'wpml-node-modules', 'wp-i18n', self::STATE_HANDLE ],
				$version,
				true
			);
			if ( defined( 'WPML_ROOT_DIR' ) ) {
				wp_set_script_translations( self::SCRIPT_HANDLE, 'wpml', WPML_ROOT_DIR . '/languages/' );
			}
		}

		wp_enqueue_script( self::STATE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );

		$siteId = (string) $siteId;
		add_action(
			'admin_print_footer_scripts',
			function () use ( $siteId ) {
				$encoded = wp_json_encode( self::bootstrap( $siteId ) );

				wp_add_inline_script(
					self::STATE_HANDLE,
					'window.' . self::BOOTSTRAP_GLOBAL . ' = ' . ( $encoded ? $encoded : '{}' ) . ';'
				);
			},
			1
		);
	}

	private function renderEscalated() {
		$siteId = (string) $this->auth->get_site_id();
		$code   = ReconnectState::diagnosticCode( $siteId );
		$report = ReconnectState::debugReport( $siteId );

		$headline = esc_html__(
			"WPML can't reconnect this site to our translation system.",
			'sitepress'
		);

		$reassurance = esc_html__(
			'WPML keeps trying in the background. Once support fixes this, the site reconnects on its own.',
			'sitepress'
		);

		/* translators: Button label in a notice about reaching the translation service: try it again straight away. Verb phrase, imperative. */
		$retry = esc_html__( 'Retry now', 'sitepress' );

		$retryHint = esc_html__( 'If something has just been fixed on your account, try again without waiting.', 'sitepress' );

		$ask = sprintf(
			/* translators: Line at the end of a notice about automatic translation; "it" is the attempt that failed. %s: the code the support team needs. */
			esc_html__( 'If it keeps failing, contact WPML support and quote this code: %s', 'sitepress' ),
			'<code>' . esc_html( $code ) . '</code>'
		);

		$bundleLabel = esc_html__( 'Copy this into your support message:', 'sitepress' );

		$hide = esc_html__( 'Hide this message for 24 hours', 'sitepress' );

		return '<div class="notice notice-warning wpml-ate-reconnect-escalated">'
		       . NoticeBrand::row()
		       . '<p><strong>' . $headline . '</strong></p>'
		       . '<p><a class="button button-primary wpml-ate-reconnect-retry" href="'
		       . esc_url( ReconnectActions::retryUrl() ) . '">' . $retry . '</a></p>'
		       . '<p class="description">' . $retryHint . '</p>'
		       . '<p>' . $ask . '</p>'
		       . '<p>' . $reassurance . '</p>'
		       . '<p><label for="wpml-ate-reconnect-report">' . $bundleLabel . '</label></p>'
		       . '<p><textarea id="wpml-ate-reconnect-report" readonly rows="12" cols="80" '
		       . 'style="width:100%;max-width:640px;font-family:monospace;">'
		       . esc_textarea( $report )
		       . '</textarea></p>'
		       . '<p><a class="wpml-ate-reconnect-hide" href="'
		       . esc_url( ReconnectActions::dismissUrl() ) . '">' . $hide . '</a></p>'
		       . '</div>';
	}
}
