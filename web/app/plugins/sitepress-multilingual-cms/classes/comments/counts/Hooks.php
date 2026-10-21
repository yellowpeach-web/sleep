<?php

namespace WPML\Comments\Counts;

class Hooks implements \IWPML_Action, \IWPML_Frontend_Action_Loader, \IWPML_Backend_Action_Loader {

	private $wpdb;

	private $scheduler;

	private $store;

	private $recount;

	private $fallback;

	public function __construct( ?\wpdb $wpdb = null ) {
		$this->wpdb = $wpdb;
	}

	public function create() {
		return $this;
	}

	public function add_hooks() {
		add_action( 'init', array( $this, 'boot' ), 1 );
		add_action( Scheduler::EVENT, array( $this->recount(), 'runFromCron' ) );

		add_filter( 'wpml_reset_options', array( $this, 'addResetOptions' ) );
		add_action( 'wpml_reset_plugins_after', array( $this, 'afterReset' ) );

		register_activation_hook( WPML_PLUGIN_PATH . '/' . WPML_PLUGIN_FILE, array( $this, 'onActivation' ) );
	}

	public function boot() {
		if ( ! FeatureSwitch::readOnInit() ) {
			$this->cleanUpWhileOff();

			return;
		}

		add_action( Store::MARK_ACTION, array( $this->store(), 'markOutsideChange' ), 10, 0 );

		$server = new CountServer( $this->store(), $this->fallback(), $this->scheduler() );
		add_filter( 'wp_count_comments', array( $server, 'serve' ), CountServer::SERVE_PRIORITY, 2 );
		add_filter( 'wp_count_comments', array( $server, 'guard' ), CountServer::GUARD_PRIORITY );

		add_filter( 'wpml_is_comment_query_filtered', array( $this->fallback(), 'unfilterOwnQueries' ), 10, 3 );
		add_action( 'wp_insert_comment', array( $this->fallback(), 'forget' ), 10, 0 );
		add_action( 'transition_comment_status', array( $this->fallback(), 'forget' ), 10, 0 );

		$counters = new Counters( $this->wpdb(), $this->store() );
		add_action( 'wp_insert_comment', array( $counters, 'commentInserted' ), 10, 2 );
		add_action( 'transition_comment_status', array( $counters, 'statusChanged' ), 10, 3 );
		add_action( 'wp_update_comment_count', array( $counters, 'deferralEnded' ), 10, 0 );
		add_action( 'delete_post', array( $counters, 'deferralEnded' ), 0, 0 );
		add_action( 'shutdown', array( $counters, 'applyDeferred' ), 10, 0 );
		add_filter( 'wp_count_comments', array( $counters, 'applyBeforeUse' ), CountServer::SERVE_PRIORITY - 1, 1 );
		add_filter( 'wp_redirect', array( $counters, 'applyBeforeUse' ), 10, 1 );

		$listener = new ChangeListener( $this->store(), $this->scheduler(), null, $counters );
		add_action( 'trashed_post_comments', array( $listener, 'mark' ), 10, 0 );
		add_action( 'untrashed_post_comments', array( $listener, 'mark' ), 10, 0 );
		add_action( 'akismet_delete_comment_batch', array( $listener, 'mark' ), 10, 0 );
		add_filter( 'wp_update_comment_data', array( $listener, 'commentDataUpdated' ), 10, 2 );
		add_action( 'edit_comment', array( $listener, 'commentEdited' ), 10, 1 );
		add_action( 'wpml_translation_update', array( $listener, 'translationUpdated' ) );
		add_action( 'activated_plugin', array( $listener, 'pluginSwitched' ), 10, 0 );
		add_action( 'deactivated_plugin', array( $listener, 'pluginSwitched' ), 10, 0 );

		if ( is_admin() ) {
			$activity = new ActivityList( new ActivityScan( $this->wpdb() ), null, null, $this->store() );
			add_action( 'pre_get_comments', array( $activity, 'noteCoreShape' ), ActivityList::NOTE_PRIORITY );
			add_action( 'pre_get_comments', array( $activity, 'recognise' ), ActivityList::RECOGNISE_PRIORITY );
			add_filter( 'comments_clauses', array( $activity, 'noteCoreClauses' ), ActivityList::NOTE_PRIORITY, 2 );
			add_filter( 'comments_clauses', array( $activity, 'restrict' ), ActivityList::RESTRICT_PRIORITY, 2 );

			add_action( 'admin_enqueue_scripts', array( new ModerationScreenLanguage(), 'addScript' ), 10, 0 );

			add_action( 'rightnow_end', array( new AkismetSpamCount(), 'forget' ), AkismetSpamCount::PRIORITY, 0 );

			$column = new PostsListColumn( $this->wpdb() );
			add_filter( 'wp_list_table_class_name', array( $column, 'maybeSwapClass' ), PostsListColumn::SWAP_PRIORITY, 2 );
		}

		$this->scheduler()->addLateRecount( $this->recount() );
	}

	public function addResetOptions( $options ) {
		$options   = (array) $options;
		$options[] = Store::OPTION;
		$options[] = Recount::LOCK_OPTION;

		return $options;
	}

	public function afterReset() {
		$this->scheduler()->clear();
	}

	public function onActivation() {
		$this->store()->delete();
	}

	private function cleanUpWhileOff() {
		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( false === get_option( Store::OPTION, false ) ) {
			return;
		}

		$this->store()->delete();
		$this->scheduler()->clear();
	}

	private function wpdb() {
		if ( ! $this->wpdb ) {
			global $wpdb;

			$this->wpdb = $wpdb;
		}

		return $this->wpdb;
	}

	private function scheduler() {
		if ( ! $this->scheduler ) {
			$this->scheduler = new Scheduler();
		}

		return $this->scheduler;
	}

	private function store() {
		if ( ! $this->store ) {
			$this->store = new Store( $this->wpdb(), $this->scheduler() );
		}

		return $this->store;
	}

	private function fallback() {
		if ( ! $this->fallback ) {
			$this->fallback = new GlobalFallback();
		}

		return $this->fallback;
	}

	private function recount() {
		if ( ! $this->recount ) {
			$this->recount = new Recount( $this->wpdb(), new Exclusions(), $this->store(), $this->scheduler() );
		}

		return $this->recount;
	}
}
