<?php

namespace WPML\PB\Shutdown;

class Hooks implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	const PRIORITY_FINISH_RESPONSE           = 9;
	const PRIORITY_REGISTER_STRINGS          = 10;
	const PRIORITY_SAVE_TRANSLATIONS_TO_POST = 20;
	const PRIORITY_TRANSLATE_MEDIA           = 30;
	const PRIORITY_TRANSLATIONS_WRITTEN      = 40;

	const ACTION_TRANSLATION_WRITTEN = 'wpml_pb_post_translation_written';

	const EDITOR_SAVE_BEFORE_RESPONSE = 'wpml_editor_save_before_response';

	const UNIT_REGISTER_STRINGS = 'registerStrings';
	const UNIT_TRANSLATE_MEDIA  = 'translateMedias';

	private $pbIntegration;

	private $responseFinished = false;

	private $handled = [
		self::UNIT_REGISTER_STRINGS => [],
		self::UNIT_TRANSLATE_MEDIA  => [],
	];

	private $written = [];

	public function __construct( \WPML_PB_Integration $pbIntegration ) {
		$this->pbIntegration = $pbIntegration;
	}

	public function add_hooks() {
		add_action( 'shutdown', [ $this, 'finishResponse' ], self::PRIORITY_FINISH_RESPONSE );
		add_action( 'shutdown', [ $this, 'registerStrings' ], self::PRIORITY_REGISTER_STRINGS );
		add_action( 'shutdown', [ $this->pbIntegration, 'save_translations_to_post' ], self::PRIORITY_SAVE_TRANSLATIONS_TO_POST );
		add_action( 'shutdown', [ $this, 'translateMedias' ], self::PRIORITY_TRANSLATE_MEDIA );

		add_action( self::EDITOR_SAVE_BEFORE_RESPONSE, [ $this, 'registerStringsNow' ], self::PRIORITY_REGISTER_STRINGS );
		add_action( self::EDITOR_SAVE_BEFORE_RESPONSE, [ $this, 'saveTranslationsToPostNow' ], self::PRIORITY_SAVE_TRANSLATIONS_TO_POST );
		add_action( self::EDITOR_SAVE_BEFORE_RESPONSE, [ $this, 'translateMediasNow' ], self::PRIORITY_TRANSLATE_MEDIA );

		add_action( 'wpml_page_builder_string_translated', [ $this, 'rememberWritten' ], PHP_INT_MAX, 2 );
		add_action( 'shutdown', [ $this, 'announceWritten' ], self::PRIORITY_TRANSLATIONS_WRITTEN );
		add_action( self::EDITOR_SAVE_BEFORE_RESPONSE, [ $this, 'announceWritten' ], self::PRIORITY_TRANSLATIONS_WRITTEN );

		add_action( 'save_post', [ $this, 'forgetHandled' ], PHP_INT_MAX, 1 );
	}

	public function finishResponse() {
		if ( $this->responseFinished ) {
			return;
		}

		if ( ! $this->pbIntegration->get_save_post_queue() && ! $this->pbIntegration->has_translations_to_save() ) {
			return;
		}

		$this->responseFinished = true;

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			self::keepRunningWhenTheBrowserLeaves();
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			self::keepRunningWhenTheBrowserLeaves();
			litespeed_finish_request();
		}
	}

	private static function keepRunningWhenTheBrowserLeaves() {
		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}
	}

	public function registerStrings() {
		$this->run( self::UNIT_REGISTER_STRINGS, [ $this->pbIntegration, 'register_all_strings_for_translation' ] );
	}

	public function registerStringsNow( $postId ) {
		$this->run( self::UNIT_REGISTER_STRINGS, [ $this->pbIntegration, 'register_all_strings_for_translation' ], (int) $postId );
	}

	public function saveTranslationsToPostNow( $postId ) {
		$this->pbIntegration->save_translations_to_post();
	}

	public function translateMedias() {
		$this->run( self::UNIT_TRANSLATE_MEDIA, [ $this->pbIntegration, 'translate_media' ] );
	}

	public function translateMediasNow( $postId ) {
		$this->run( self::UNIT_TRANSLATE_MEDIA, [ $this->pbIntegration, 'translate_media' ], (int) $postId );
	}

	public function rememberWritten( $kind, $translatedPostId ) {
		$this->written[ (int) $translatedPostId ] = true;
	}

	public function announceWritten() {
		$written       = array_keys( $this->written );
		$this->written = [];

		foreach ( $written as $translatedPostId ) {
			do_action( self::ACTION_TRANSLATION_WRITTEN, $translatedPostId );
		}
	}

	public function forgetHandled( $postId ) {
		$postId = (int) $postId;

		foreach ( array_keys( $this->handled ) as $unit ) {
			unset( $this->handled[ $unit ][ $postId ] );
		}
	}

	private function run( $unit, callable $fn, $onlyPostId = null ) {
		foreach ( $this->pbIntegration->get_save_post_queue() as $queuedId => $post ) {
			$queuedId = (int) $queuedId;

			if ( null !== $onlyPostId && $queuedId !== $onlyPostId ) {
				continue;
			}

			if ( isset( $this->handled[ $unit ][ $queuedId ] ) ) {
				continue;
			}

			if ( true === $fn( $post ) && self::UNIT_TRANSLATE_MEDIA === $unit ) {
				$this->written[ $queuedId ] = true;
			}
			$this->handled[ $unit ][ $queuedId ] = true;
		}
	}
}
