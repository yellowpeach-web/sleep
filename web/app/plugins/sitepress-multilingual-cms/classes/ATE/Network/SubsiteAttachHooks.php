<?php

namespace WPML\TM\ATE\Network;

use WPML\Core\WP\App\Resources;
use WPML\LIB\WP\User;
use WPML\TM\ATE\AutoTranslate\Endpoint\EnableATE;
use WPML\TM\ATE\ClonedSites\ReconnectNotice;
use WPML\TM\ATE\Network\Endpoints\DismissSuggestion;
use function WPML\Container\make;

class SubsiteAttachHooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const ROOT_ID = 'wpml-ate-subsite-attach-root';

	private $auth;

	private static $retriedThisRequest = false;

	private $config;

	public function __construct( \WPML_TM_ATE_Authentication $auth ) {
		$this->auth = $auth;
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'retryWhenDue' ], 999 );
		add_filter( 'wpml_tm_dashboard_notices', [ $this, 'addDashboardNotice' ] );
		add_action( 'admin_notices', [ $this, 'renderOnAteScreens' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function retryWhenDue() {
		if ( self::$retriedThisRequest || ! $this->isWaitingForAttach() || ! SubsiteAttachState::isDue() ) {
			return;
		}

		if ( ! User::canManageTranslations() ) {
			return;
		}

		self::$retriedThisRequest = true;
		make( EnableATE::class )->enable();
	}

	public function addDashboardNotice( $notices ) {
		if ( ! $this->getConfig() ) {
			return $notices;
		}

		$notices   = is_array( $notices ) ? $notices : [];
		$notices[] = [ $this, 'renderRoot' ];

		return $notices;
	}

	public function renderOnAteScreens() {
		if ( ReconnectNotice::isAteScreen() && $this->getConfig() ) {
			$this->renderRoot();
		}
	}

	public function renderRoot() {
		echo '<div id="' . esc_attr( self::ROOT_ID ) . '" class="notice"></div>';
	}

	public function enqueue() {
		if ( ! ReconnectNotice::isAteScreen() ) {
			return;
		}

		$config = $this->getConfig();
		if ( ! $config ) {
			return;
		}

		$enqueue = Resources::enqueueApp( 'ate-auto-migration' );
		$enqueue( [
			'name' => 'wpml_ate_subsite_attach',
			'data' => $config,
		] );
	}

	private function getConfig() {
		if ( null === $this->config ) {
			$this->config = $this->buildConfig() ?: [];
		}

		return $this->config ?: null;
	}

	private function buildConfig() {
		if ( ! SubsiteAttach::isSubsite() || ! User::canManageTranslations() ) {
			return null;
		}

		if ( $this->isWaitingForAttach() && SubsiteAttachState::isPending() ) {
			return [ 'pending' => SubsiteAttachState::clientState() ];
		}

		if ( $this->hasOwnAccount() ) {
			return [
				'suggestConnecting' => true,
				'endpoints'         => [ 'dismissSuggestion' => DismissSuggestion::class ],
			];
		}

		return null;
	}

	private function isWaitingForAttach(): bool {
		return \WPML_TM_ATE_Status::is_enabled() && ! $this->auth->has_keys();
	}

	private function hasOwnAccount(): bool {
		if ( ! $this->auth->has_keys() || ! SubsiteAttachState::suggestsConnecting() ) {
			return false;
		}

		$main = SubsiteAttach::mainSiteAccount();

		return $main && $main['uuid'] !== (string) $this->auth->get_site_id();
	}
}
