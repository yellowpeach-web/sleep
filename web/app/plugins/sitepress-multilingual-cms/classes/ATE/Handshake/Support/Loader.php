<?php

namespace WPML\TM\ATE\Handshake\Support;

use WPML\TM\ATE\Sync\SyncChunk;

class Loader implements \IWPML_Backend_Action {

	const AJAX_ACTION   = 'wpml_ate_delivery_box';
	const CAPABILITY    = 'manage_options';
	const AFTER_SEARCH  = 'wpml_admin_support_landing_after_search';
	const SEARCH_FILTER = 'wpml_admin_support_search_entries';
	const SUPPORT_PAGE  = 'admin.php?page=sitepress-multilingual-cms/menu/support.php';

	const OP_CHUNK         = 'chunk';
	const OP_CHUNK_DEFAULT = 'chunk_default';

	public function add_hooks() {
		add_action( self::AFTER_SEARCH, [ $this, 'renderBox' ] );
		\WPML\Request\Adapter\Ajax::register(
			self::AJAX_ACTION,
			\WPML\Request\Policy\Policy::capability( self::CAPABILITY, \WPML\Request\Policy\Authenticity::actionNonce( self::AJAX_ACTION, 'nonce' ) ),
			[ $this, 'ajax' ]
		);
		add_filter( self::SEARCH_FILTER, [ $this, 'searchEntries' ] );
	}

	public static function shown() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}

		try {
			return (bool) \WPML\Container\make( \WPML_TM_ATE_Authentication::class )->has_keys();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	public static function condition( $from ) {
		$condition = sanitize_key( (string) $from );

		return in_array( $condition, Box::CONDITIONS, true ) ? $condition : '';
	}

	public function renderBox() {
		if ( ! static::shown() ) {
			return;
		}

		try {
			$blocks = ( new Blocks() )->read();
			$html   = ( new Box() )->render(
				( new SiteFacts() )->read(),
				( new SystemView() )->read(),
				$blocks,
				self::condition( (string) filter_input( INPUT_GET, 'delivery' ) ),
				admin_url( 'admin-ajax.php' ),
				wp_create_nonce( self::AJAX_ACTION )
			);
		} catch ( \Throwable $e ) {
			return;
		}

		echo wp_kses( $html, Box::allowedHtml() );
		wp_print_inline_script_tag( Box::script() );
	}

	public function ajax() {
		if ( ! check_ajax_referer( self::AJAX_ACTION, 'nonce', false ) || ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'card' => Box::refusedCard() ], 403 );

			return;
		}

		$op        = isset( $_POST['op'] ) && is_string( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$condition = isset( $_POST['delivery'] ) && is_string( $_POST['delivery'] ) ? self::condition( sanitize_key( wp_unslash( $_POST['delivery'] ) ) ) : '';
		$chunk     = isset( $_POST['chunk'] ) && is_string( $_POST['chunk'] ) ? sanitize_text_field( wp_unslash( $_POST['chunk'] ) ) : '';

		wp_send_json_success( self::answer( $op, $condition, $chunk ) );
	}

	public static function answer( $op, $condition = '', $chunk = '' ) {
		$box     = new Box();
		$found   = '';
		$written = null;

		if ( 'check' === $op ) {
			list( $card, $system, $found, $written ) = self::check( $box );
		} elseif ( Actions::COLLECT === $op ) {
			list( $card, $system, $found ) = self::collect( $box );
		} elseif ( self::OP_CHUNK === $op || self::OP_CHUNK_DEFAULT === $op ) {
			$saved  = self::OP_CHUNK_DEFAULT === $op ? SyncChunk::reset() : SyncChunk::save( $chunk );
			$card   = $box->chunkCard( $saved );
			$system = ( new SystemView() )->read();
		} else {
			$card   = self::card( $op, $box );
			$system = Actions::CANCEL_PREVIEW === $op ? ( new SystemView() )->read() : ( new SystemView() )->read( true );
		}

		$inner = $box->inner( ( new SiteFacts() )->read(), $system, ( new Blocks() )->read(), $condition, $card, $found, $written );

		return [
			'card'  => wp_kses( $card, Box::allowedHtml() ),
			'inner' => wp_kses( $inner, Box::allowedHtml() ),
		];
	}

	public static function card( $op, ?Box $box = null, ?Actions $actions = null ) {
		$box = $box ?: new Box();

		if ( 'check' === $op ) {
			return self::check( $box )[0];
		}

		try {
			if ( in_array( $op, Actions::ACTIONS, true ) ) {
				$card = $box->actionCard( ( $actions ?: new Actions() )->run( $op ) );
			} else {
				$card = $box->actionCard(
					[
						'action' => $op,
						'ok'     => false,
						'error'  => 'unknown_action',
					]
				);
			}
		} catch ( \Throwable $e ) {
			$card = $box->actionCard(
				[
					'action' => $op,
					'ok'     => false,
					'error'  => get_class( $e ),
				]
			);
		}

		return $card;
	}

	public static function check( Box $box, ?Check $check = null, ?SystemView $view = null ) {
		$view   = $view ?: new SystemView();
		$before = $view->read();

		try {
			$result = ( $check ?: new Check() )->run();
		} catch ( \Throwable $e ) {
			$card = $box->actionCard(
				[
					'action' => 'check',
					'ok'     => false,
					'error'  => get_class( $e ),
				]
			);

			return [ $card, $view->read( true ), '', null ];
		}

		$after   = $view->read( true );
		$collect = isset( $result['collect'] ) && is_array( $result['collect'] ) ? $result['collect'] : [];
		$written = ! empty( $collect['ran'] ) && empty( $collect['busy'] ) ? (int) $collect['applied'] : null;

		return [ $box->checkCard( $result, $after, $before ), $after, Box::foundByCheck( $result, $before, $after ), $written ];
	}

	public static function collect( Box $box, ?Actions $actions = null, ?SystemView $view = null ) {
		try {
			$result = ( $actions ?: new Actions() )->run( Actions::COLLECT );
		} catch ( \Throwable $e ) {
			$result = [
				'action' => Actions::COLLECT,
				'ok'     => false,
				'error'  => get_class( $e ),
			];
		}

		$system = ( $view ?: new SystemView() )->read( true );
		$found  = ! isset( $result['error'] ) && Box::notCollected( $result, $system ) ? Check::HEARS_BUT_SILENT : '';

		return [ $box->actionCard( $result, $system ), $system, $found ];
	}

	public function searchEntries( $entries ) {
		$entries = is_array( $entries ) ? $entries : [];

		if ( ! static::shown() ) {
			return $entries;
		}

		$subs = [];

		foreach ( Texts::searchLines() as $label ) {
			$subs[] = [
				'label'  => $label,
				'anchor' => Box::ID,
			];
		}

		$entries[] = [
			'section' => Texts::title(),
			'tier'    => 'safe',
			'href'    => admin_url( self::SUPPORT_PAGE ),
			'subs'    => $subs,
		];

		return $entries;
	}
}
