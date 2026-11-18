<?php

namespace WPML\PB\Gutenberg;

use WPML\EditHistory\InternalWrite;
use WPML\EditHistory\PanelChoice;
use WPML\EditHistory\PostUnits;
use WPML\EditHistory\Recorder;
use WPML\EditHistory\Subject;
use WPML\Infrastructure\WordPress\Component\LanguagePanel\Rest\SaveFlags;
use WPML\PB\Integrations\Divi\Helper as DiviHelper;
use WPML\Posts\MetaBoxLoaderLeg;

class EditHistoryHooks implements Integration {

	const PRIORITY = PHP_INT_MAX - 20;

	const EDITOR = 'block-editor';

	const RECORDED_WRITERS = [ InternalWrite::PUBLIC_API => InternalWrite::SERVICE ];

	const CLICK_MARKER = 'wpml_edit_history_click_';

	const CLICK_MARKER_TTL = 600;

	private $recorder;

	private $units;

	private $sitepress;

	private $before = [];

	private $written = [];

	private $clicks = [];

	public function __construct( Recorder $recorder, PostUnits $units, \SitePress $sitepress ) {
		$this->recorder  = $recorder;
		$this->units     = $units;
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'pre_post_update', [ $this, 'rememberBefore' ], 10, 1 );
		add_action( 'wp_after_insert_post', [ $this, 'record' ], self::PRIORITY, 2 );
	}

	public function rememberBefore( $postId ) {
		$postId = (int) $postId;
		if ( array_key_exists( $postId, $this->before ) ) {
			return;
		}

		$post  = get_post( $postId );
		$saved = $post instanceof \WP_Post ? $this->personsSave( $post ) : null;
		if ( ! $saved ) {
			return;
		}

		[ $original, $language ] = $saved;
		$isOriginal              = (int) $original->ID === $postId;

		$this->before[ $postId ] = $isOriginal || $this->recorder->needsBefore( Subject::post( $postId ), $language, (int) $original->ID )
			? $this->units->read( $post, $original )
			: null;
	}

	public function record( $postId, $post ) {
		$postId = (int) $postId;
		$saved  = $post instanceof \WP_Post ? $this->personsSave( $post ) : null;
		if ( ! $saved ) {
			return;
		}

		[ $original, $language ] = $saved;

		if ( (int) $original->ID === $postId ) {
			$this->recorder->stampOriginal( Subject::post( $postId ), $this->before[ $postId ] ?? null, $this->units->read( $post, $post ) );

			return;
		}

		$recordedSource = self::recordedSource();
		$entryId        = $this->recorder->recordTranslation(
			Subject::post( $postId ),
			$language,
			(int) $original->ID,
			$this->units->read( $post, $original ),
			$this->before[ $postId ] ?? null,
			fn() => $this->units->read( $original, $original ),
			$recordedSource ? (string) InternalWrite::writer() : self::EDITOR,
			get_current_user_id() ?: null,
			$this->written[ $postId ] ?? $this->clickOfMetaBoxRequest( $postId ),
			PanelChoice::source() ?? $recordedSource ?? Recorder::SOURCE_CLIENT
		);

		if ( $entryId ) {
			$this->written[ $postId ] = $entryId;
		}

		$this->leaveClickMarker( $postId );
	}

	private function personsSave( \WP_Post $post ) {
		if (
			( InternalWrite::isActive() && ! PanelChoice::isActive() && ! self::recordedSource() )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| in_array( $post->post_status, [ 'auto-draft', 'trash' ], true )
			|| ! has_blocks( $post->post_content )
			|| ! $this->sitepress->is_translated_post_type( $post->post_type )
			|| DiviHelper::isDiviPost( $post->ID )
		) {
			return null;
		}

		$elementType = 'post_' . $post->post_type;
		$details     = $this->sitepress->get_element_language_details( $post->ID, $elementType );
		$language    = is_object( $details ) ? (string) ( $details->language_code ?? '' ) : '';
		if ( '' === $language ) {
			return null;
		}

		$originalId = (int) apply_filters( 'wpml_original_element_id', null, $post->ID, $elementType );
		$original   = $originalId && $originalId !== (int) $post->ID ? get_post( $originalId ) : $post;

		return $original instanceof \WP_Post ? [ $original, $language ] : null;
	}

	private static function recordedSource() {
		$writer = InternalWrite::writer();

		return null === $writer ? null : ( self::RECORDED_WRITERS[ $writer ] ?? null );
	}

	private function clickOfMetaBoxRequest( int $postId ) {
		if ( ! array_key_exists( $postId, $this->clicks ) ) {
			$entryId = MetaBoxLoaderLeg::is_request() ? (int) get_transient( self::CLICK_MARKER . $postId ) : 0;
			if ( $entryId ) {
				delete_transient( self::CLICK_MARKER . $postId );
			}
			$this->clicks[ $postId ] = $entryId ?: null;
		}

		return $this->clicks[ $postId ];
	}

	private function leaveClickMarker( int $postId ) {
		if ( ! SaveFlags::isRestSave() || ! SaveFlags::metaBoxSaveFollows() ) {
			return;
		}

		if ( isset( $this->written[ $postId ] ) ) {
			set_transient( self::CLICK_MARKER . $postId, $this->written[ $postId ], self::CLICK_MARKER_TTL );
		} else {
			delete_transient( self::CLICK_MARKER . $postId );
		}
	}
}
