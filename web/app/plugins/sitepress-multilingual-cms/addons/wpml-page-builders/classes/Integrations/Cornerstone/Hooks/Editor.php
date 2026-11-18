<?php

namespace WPML\PB\Cornerstone\Hooks;

use WPML\FP\Cast;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class Editor implements \IWPML_Frontend_Action {

	const SAVE_ROUTE = 'themeco/data/save';

	const PREVIEW_DOCUMENT_TYPES_OUTSIDE_CONTENT = [ 'header', 'footer', 'layout', 'component' ];

	private $isBuilderSaveRequest = false;

	private $previewDocumentId = 0;

	public function add_hooks() {
		Hooks::onAction( 'cs_before_save_request' )
			->then( [ $this, 'markBuilderSaveRequest' ] );

		Hooks::onFilter( 'wpml_pb_is_editing_translation_with_native_editor', 10, 2 )
			->then(
				spreadArgs(
					function( $isTranslationWithNativeEditor, $translatedPostId ) {
						return $isTranslationWithNativeEditor
						|| (
							$this->isBuilderSaveRequest()
							&& self::getEditedId() === $translatedPostId
						  );
					}
				)
			);

		Hooks::onAction( 'cs_content' )
			->then( [ $this, 'maybeDisplayModalPageBuilderWarning' ] );

		Hooks::onAction( 'cs_before_preview_frame' )
			->then( spreadArgs( [ $this, 'rememberPreviewDocumentId' ] ) );

		foreach ( self::PREVIEW_DOCUMENT_TYPES_OUTSIDE_CONTENT as $documentType ) {
			Hooks::onAction( 'cs_before_preview_frame_' . $documentType )
				->then( [ $this, 'maybeDisplayModalPageBuilderWarningForPreviewDocument' ] );
		}
	}

	public function markBuilderSaveRequest() {
		$this->isBuilderSaveRequest = true;
	}

	private function isBuilderSaveRequest() {
		return $this->isBuilderSaveRequest || Str::includes( self::SAVE_ROUTE, (string) Obj::prop( 'REQUEST_URI', $_SERVER ) );
	}

	private static function getEditedId() {
		$decodeCornerstoneData = function( $data ) {
			$request = Obj::prop( 'request', $data );

			if ( Obj::prop( 'gzip', $data ) ) {
				return (array) json_decode( gzdecode( base64_decode( $request, true ) ), true );
			}

			return (array) $request;
		};

		$key   = version_compare( constant( 'CS_VERSION' ), '7.1.3', '>=' ) ? 'document' : 'builder';
		$getId = Obj::path( [ 'requests', $key, 'id' ] );

		return Maybe::fromNullable( \WP_REST_Server::get_raw_data() )
			->map( 'json_decode' )
			->map( $decodeCornerstoneData )
			->map( $getId )
			->map( Cast::toInt() )
			->getOrElse( null );
	}

	public function maybeDisplayModalPageBuilderWarning() {
		if ( is_user_logged_in() && get_the_ID() ) {
			do_action( 'wpml_maybe_display_modal_page_builder_warning', get_the_ID(), 'Cornerstone' );
		}
	}

	public function rememberPreviewDocumentId( $previewState ) {
		$this->previewDocumentId = (int) Obj::propOr( 0, 'documentId', (array) $previewState );
	}

	public function maybeDisplayModalPageBuilderWarningForPreviewDocument() {
		if ( is_user_logged_in() && $this->previewDocumentId ) {
			do_action( 'wpml_maybe_display_modal_page_builder_warning', $this->previewDocumentId, 'Cornerstone' );
		}
	}
}
