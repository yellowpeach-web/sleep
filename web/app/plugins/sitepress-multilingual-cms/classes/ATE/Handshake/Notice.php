<?php

namespace WPML\TM\ATE\Handshake;

use WPML\OutboundLinks\OutboundLinks;

class Notice implements \IWPML_Backend_Action {

	const CAPABILITIES = [ 'manage_options', 'manage_translations' ];

	const SUPPORT_URL = 'https://app.wpml.org/support';

	const KIND_FAILED      = 'failed_on_site';
	const KIND_RESEND      = 'needs_resend';
	const KIND_CORRECTIONS = 'corrections';

	const HIDDEN_BATCH_META = 'wpml_ate_handshake_corrections_hidden';

	const CORRECTIONS_TAB = 'corrections';

	private $marks;

	private static $logged = [];

	public function __construct( ?Marks $marks = null ) {
		$this->marks = $marks ?: new Marks();
	}

	public function add_hooks() {
		add_filter( 'wpml_tm_dashboard_notices', [ $this, 'addDashboardNotice' ] );
		add_action( 'admin_notices', [ $this, 'renderOnQueue' ] );
		add_action( 'admin_notices', [ $this, 'renderCorrectionsOnWpmlPages' ] );
		add_filter( 'wpml_translations_corrections_tab', [ Corrections\Page::class, 'tabDefinition' ] );
		add_action( 'wpml_translations_corrections_tab_body', [ Corrections\Page::class, 'printPage' ] );
	}

	public function renderCorrectionsOnWpmlPages() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( is_object( $screen ) && isset( $screen->id ) && false !== strpos( (string) $screen->id, 'translations-queue' ) ) {
			return;
		}

		if ( ! self::isWpmlAdminPage() || self::isCorrectionsPage() || ! self::userMayAct() ) {
			return;
		}

		$html = $this->correctionsHtml();

		if ( '' === $html ) {
			return;
		}

		echo wp_kses( $html, self::allowedHtml() );
		wp_print_inline_script_tag( $this->script() );
	}

	private static function isWpmlAdminPage() {
		$page = \WPML\SuperGlobals\Request::page();

		foreach ( [ 'sitepress-multilingual-cms', 'tm/', 'wpml' ] as $prefix ) {
			if ( 0 === strpos( $page, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	public static function isCorrectionsPage() {
		return 'tm/menu/main.php' === \WPML\SuperGlobals\Request::page()
			&& self::CORRECTIONS_TAB === \WPML\SuperGlobals\Request::param( 'tab' );
	}

	public static function correctionsUrl() {
		return admin_url( 'admin.php?page=tm/menu/main.php&tab=' . self::CORRECTIONS_TAB );
	}

	public function addDashboardNotice( $notices ) {
		$notices   = is_array( $notices ) ? $notices : [];
		$notices[] = [ $this, 'render' ];

		return $notices;
	}

	public function renderOnQueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( is_object( $screen ) && isset( $screen->id ) && false !== strpos( (string) $screen->id, 'translations-queue' ) ) {
			$this->render();
		}
	}

	public function render() {
		if ( ! self::userMayAct() ) {
			return;
		}

		$html = $this->html();

		if ( '' === $html ) {
			return;
		}

		echo wp_kses( $html, self::allowedHtml() );
		wp_print_inline_script_tag( $this->script() );
	}

	private static function allowedHtml() {
		return [
			'div'    => [
				'class'     => true,
				'data-kind' => true,
			],
			'p'      => [],
			'h3'     => [],
			'a'      => [
				'class' => true,
				'href'  => true,
			],
			'form'   => [
				'method' => true,
				'target' => true,
				'action' => true,
				'style'  => true,
			],
			'input'  => [
				'type'  => true,
				'name'  => true,
				'value' => true,
			],
			'button' => [
				'type'                        => true,
				'class'                       => true,
				'data-wpml-ate-handshake-url' => true,
			],
		];
	}

	public function html() {
		$failed = $this->marks->jobsMarked( Marks::FAILED_ON_SITE );
		$resend = $this->marks->jobsMarked( Marks::NEEDS_RESEND );
		$html   = $this->correctionsHtml();

		if ( ! $failed && ! $resend ) {
			return $html;
		}

		if ( $failed ) {
			$html .= $this->failedHtml( $failed );
			self::logShown( self::KIND_FAILED, count( $failed ) );
		}

		if ( $resend ) {
			$html .= $this->resendHtml( $resend );
			self::logShown( self::KIND_RESEND, count( $resend ) );
		}

		return $html;
	}

	private function failedHtml( array $jobs ) {
		$count = count( $jobs );

		$text = sprintf(
			/* translators: Headline of the Translation delivery box, and of the admin notice that leads to it. %d: a number of translations. */
			_n( '%d translation could not be applied on this site.', '%d translations could not be applied on this site.', $count, 'sitepress' ),
			$count
		);

		$html  = '<div class="notice notice-error wpml-ate-handshake-notice" data-kind="' . esc_attr( self::KIND_FAILED ) . '">';
		$html .= '<p>' . esc_html( $text ) . ' ' . esc_html__( 'Retry asks our translation system to send them again. If that does not help, report it to our support team.', 'sitepress' ) . '</p>';
		$html .= '<p>';
		$html .= $this->actionButton( Routes::RETRY, __( 'Retry', 'sitepress' ), 'button button-primary' );
		$html .= ' ' . $this->reportForm( $jobs );
		$html .= '</p>';

		return $html . '</div>';
	}

	private function resendHtml( array $jobs ) {
		$count = count( $jobs );

		$text = sprintf(
			/* translators: %d: the number of translations. */
			_n( '%d translation needs to be sent again.', '%d translations need to be sent again.', $count, 'sitepress' ),
			$count
		);

		$html  = '<div class="notice notice-warning wpml-ate-handshake-notice" data-kind="' . esc_attr( self::KIND_RESEND ) . '">';
		$html .= '<p>' . esc_html( $text ) . ' ' . esc_html( $this->resendReason() ) . '</p>';
		$html .= '<p>' . $this->actionButton( Routes::RESEND, __( 'Resend', 'sitepress' ), 'button button-primary' ) . '</p>';

		return $html . '</div>';
	}

	private function resendReason() {
		$reasons = $this->marks->resendReasons();
		$closed  = in_array( Delivery::RESEND_CLOSED_NOT_NEEDED, $reasons, true );

		if ( $closed && count( $reasons ) === 1 ) {
			return __( 'Our translation system closed them before your site collected them.', 'sitepress' );
		}

		if ( $closed ) {
			return __( 'Our translation system closed them or has no record of them.', 'sitepress' );
		}

		return __( 'Our translation system has no record of them.', 'sitepress' );
	}

	public function correctionsHtml() {
		$pages = Corrections\Offer::pages();

		if ( Corrections\Offer::count() < 1 || $pages < 1 ) {
			return '';
		}

		$batch = (string) State::getKey( 'corrections_batch_id' );

		if ( self::hiddenBatch() === $batch ) {
			return '';
		}

		$reason = (string) State::getKey( 'corrections_reason' );
		$hide   = add_query_arg( 'batch', rawurlencode( $batch ), Routes::url( Routes::CORRECTIONS_HIDE ) );

		$html  = '<div class="notice notice-info wpml-ate-handshake-notice" data-kind="' . esc_attr( self::KIND_CORRECTIONS ) . '">';
		$html .= '<h3>' . esc_html( Support\Texts::correctionsNoticeHeading( $pages ) ) . '</h3>';

		if ( '' !== $reason ) {
			$html .= '<p>' . esc_html( $reason ) . '</p>';
		}

		$html .= '<p>' . esc_html( Support\Texts::correctionsNoticePromise() ) . '</p>';
		$html .= '<p><a class="button button-primary" href="' . esc_url( self::correctionsUrl() ) . '">' . esc_html( Support\Texts::correctionsReview() ) . '</a> ';
		$html .= '<button type="button" class="button-link" data-wpml-ate-handshake-url="' . esc_url( $hide ) . '">' . esc_html( Support\Texts::correctionsHide() ) . '</button></p>';

		self::logShown( self::KIND_CORRECTIONS, Corrections\Offer::count() );

		return $html . '</div>';
	}

	public static function hiddenBatch() {
		$userId = (int) get_current_user_id();

		return $userId > 0 ? (string) get_user_meta( $userId, self::HIDDEN_BATCH_META, true ) : '';
	}

	public static function hideBatch( $batch ) {
		$userId = (int) get_current_user_id();

		if ( $userId < 1 || '' === (string) $batch ) {
			return false;
		}

		update_user_meta( $userId, self::HIDDEN_BATCH_META, (string) $batch );

		return true;
	}

	private function actionButton( $endpoint, $label, $class ) {
		return '<button type="button" class="' . esc_attr( $class ) . '" data-wpml-ate-handshake-url="' . esc_url( Routes::url( $endpoint ) ) . '">' . esc_html( $label ) . '</button>';
	}

	private function reportForm( array $jobs ) {
		$url   = OutboundLinks::to(
			self::SUPPORT_URL,
			[
				'medium'   => 'notice',
				'campaign' => 'translations-not-applied',
			]
		);
		$parts = wp_parse_url( $url );
		$query = [];

		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		$query['ate_jobs'] = implode(
			',',
			array_map(
				function ( $job ) {
					return (int) $job['ate_job_id'];
				},
				$jobs
			)
		);

		$action = strtok( $url, '?' );
		$html   = '<form method="get" target="_blank" action="' . esc_url( (string) $action ) . '" style="display:inline">';

		foreach ( $query as $name => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '">';
		}

		return $html . '<button type="submit" class="button">' . esc_html__( 'Report', 'sitepress' ) . '</button></form>';
	}

	private function script() {
		$nonce = wp_create_nonce( 'wp_rest' );

		return '(function(){document.querySelectorAll("[data-wpml-ate-handshake-url]").forEach(function(b){b.addEventListener("click",function(){b.disabled=true;fetch(b.getAttribute("data-wpml-ate-handshake-url"),{method:"POST",credentials:"same-origin",headers:{"X-WP-Nonce":' . wp_json_encode( $nonce ) . '}}).then(function(){window.location.reload();},function(){b.disabled=false;});});});})();';
	}

	private static function logShown( $kind, $count ) {
		if ( isset( self::$logged[ $kind ] ) ) {
			return;
		}

		self::$logged[ $kind ] = true;

		Log::write( 'notice_shown', [ 'kind' => $kind, 'count' => (int) $count ] );
	}

	public static function userMayAct() {
		foreach ( self::CAPABILITIES as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	public static function resetForTests() {
		self::$logged = [];
	}
}
