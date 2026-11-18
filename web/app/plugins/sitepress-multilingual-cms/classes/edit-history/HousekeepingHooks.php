<?php

namespace WPML\EditHistory;

class HousekeepingHooks implements \IWPML_Action {

	use \WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;

	const EVENT = 'wpml_edit_history_sweep';

	const CURSOR_OPTION = 'wpml_edit_history_sweep_cursor';

	private $log;

	private $store;

	private $sweep;

	private $privacy;

	public function __construct( $log, StateStore $store, Sweep $sweep, PrivacyTools $privacy, ?callable $filesMatchLoadedVersion = null ) {
		$this->log                     = $log;
		$this->store                   = $store;
		$this->sweep                   = $sweep;
		$this->privacy                 = $privacy;
		$this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
	}

	public function add_hooks() {
		add_action( 'delete_post', [ $this, 'onPostDeleted' ] );
		add_action( 'deleted_user', [ $this, 'onUserDeleted' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ $this->privacy, 'addExporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this->privacy, 'addEraser' ] );

		add_action( 'init', [ $this, 'schedule' ] );
		add_action( self::EVENT, [ $this, 'runSweep' ] );
		add_action( 'wpml_deactivated', [ self::class, 'unschedule' ] );
		add_action( 'wpml_reset_plugins_before', [ self::class, 'unschedule' ] );
		add_filter( 'wpml_reset_options', [ $this, 'addResetOptions' ] );
	}

	public function onPostDeleted( $postId ) {
		if ( ! $this->filesMatchLoadedVersion() ) {
			return;
		}

		$subject = Subject::post( (int) $postId );
		$this->log->deleteSubject( $subject );
		$this->store->delete( $subject );
	}

	public function onUserDeleted( $userId ) {
		if ( ! $this->filesMatchLoadedVersion() ) {
			return;
		}

		$this->log->forgetUser( (int) $userId );
	}

	public function schedule() {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::EVENT );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::EVENT );
	}

	public function runSweep() {
		$next = $this->sweep->run( self::cursor( (string) get_option( self::CURSOR_OPTION, '' ) ) );
		if ( $next ) {
			update_option( self::CURSOR_OPTION, $next->kind() . ':' . $next->id(), false );
		} else {
			delete_option( self::CURSOR_OPTION );
		}
	}

	public function addResetOptions( $options ) {
		$options[] = self::CURSOR_OPTION;

		return $options;
	}

	private static function cursor( string $stored ) {
		$parts = explode( ':', $stored );

		return 2 === count( $parts ) && '' !== $parts[0] && ctype_digit( $parts[1] ) ? new Subject( $parts[0], (int) $parts[1] ) : null;
	}
}
