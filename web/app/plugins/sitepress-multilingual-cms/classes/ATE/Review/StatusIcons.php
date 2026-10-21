<?php


namespace WPML\TM\ATE\Review;

use WPML\Element\API\Languages;
use WPML\Element\API\PostTranslations;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use WPML\Setup\Option;
use WPML\TM\API\Jobs;
use WPML\TranslationRoles\LangPairPermissions;
use function WPML\FP\partial;
use function WPML\FP\pipe;

class StatusIcons implements \IWPML_Backend_Action {

	private $langPairPermissions;

	public function __construct( ?LangPairPermissions $langPairPermissions = null ) {
		$this->langPairPermissions = $langPairPermissions;
	}

	public function add_hooks() {
		if ( ! Option::isTMAllowed() ) {
			// Blog License. No access to Review mechanic.
			return;
		}

		Hooks::onFilter( 'wpml_css_class_to_translation', PHP_INT_MAX , 6 )
		     ->then( Hooks::getArgs( [ 0 => 'default', 1 => 'postId', 2 => 'languageCode', 3 => 'trid', 4 => 'status', 5 => 'review_status' ] ) )
		     ->then( static::ifNeedsReview( self::unlessRemovableGhost(
			     $this->getReviewIconClass(),
			     Fns::always( \WPML_Post_Status_Display::ICON_TRANSLATION_ADD )
		     ) ) );

		add_action( 'init', [ __CLASS__, 'addGetReviewTitleFilter' ] );

		Hooks::onFilter( 'wpml_link_to_translation', PHP_INT_MAX, 7 )
		     ->then( Hooks::getArgs( [ 0 => 'default', 1 => 'postId', 2 => 'langCode', 3 => 'trid', 5 => 'status', 6 => 'review_status' ] ) )
		     ->then( $this->setLink() );
	}

	public static function ifNeedsReview( $fn ) {
		$doesNeedReview = function( $job ) {
			$review_status = isset( $job['review_status'] ) && $job['review_status']
				? $job['review_status']
				: ReviewStatus::ACCEPTED;
			return ReviewStatus::needsReview( $review_status );
		};

		return Logic::ifElse( $doesNeedReview, $fn, Obj::prop( 'default' ) );
	}

	public static function addGetReviewTitleFilter() {
		Hooks::onFilter( 'wpml_text_to_translation', PHP_INT_MAX, 7 )
			->then( Hooks::getArgs( [ 0 => 'default', 1 => 'postId', 2 => 'languageCode', 3 => 'trid', 5 => 'status', 6 => 'review_status' ] ) )
			->then( static::ifNeedsReview( self::unlessRemovableGhost(
				self::getReviewTitle( 'languageCode' ),
				self::getAddTitle( 'languageCode' )
			) ) );
	}

	public static function getReviewTitle( $langProp ) {
		return pipe(
			self::getLanguageName( $langProp ),
			/* translators: %s: language name. */
			Fns::unary( partial( 'sprintf', __( 'Review %s language', 'sitepress' ) ) )
		);
	}

	public static function getEditTitle( $langProp ) {
		return pipe(
			self::getLanguageName( $langProp ),
			/* translators: %s: language name. */
			Fns::unary( partial( 'sprintf', __( 'Edit %s translation', 'sitepress' ) ) )
		);
	}

	private static function getAddTitle( $langProp ) {
		return pipe(
			self::getLanguageName( $langProp ),
			/* translators: Name of the icon in the list of content that starts a translation in a language that has none yet. %s: the name of that language. */
			Fns::unary( partial( 'sprintf', __( 'Add translation to %s', 'sitepress' ) ) )
		);
	}

	private static function isGhost( $postId, $langCode ) {
		$translation = Obj::prop( $langCode, PostTranslations::get( (int) $postId ) );

		if ( ! $translation || Obj::prop( 'original', $translation ) ) {
			return false;
		}

		$translationPostId = (int) Obj::prop( 'element_id', $translation );

		return $translationPostId <= 0 || ! \get_post( $translationPostId );
	}

	private static function unlessRemovableGhost( callable $fn, callable $ghostFn ) {
		return function ( $data ) use ( $fn, $ghostFn ) {
			$isRemovableGhost = ICL_TM_COMPLETE === (int) Obj::prop( 'status', $data )
				&& self::isGhost( Obj::prop( 'postId', $data ), Obj::prop( 'languageCode', $data ) );

			return $isRemovableGhost ? $ghostFn( $data ) : $fn( $data );
		};
	}

	private static function getLanguageName( $langProp ) {
		return Fns::memorize( pipe(
			Obj::prop( $langProp ),
			Languages::getLanguageDetails(),
			Obj::prop( 'display_name' )
		) );
	}

	private function getReviewIconClass() {
		return function ( $data ) {
			return $this->canTranslatePair( Obj::prop( 'postId', $data ), Obj::prop( 'languageCode', $data ) )
				? 'otgs-ico-needs-review'
				: 'otgs-ico-needs-review otgs-ico-needs-review-disabled';
		};
	}

	private function canTranslatePair( $postId, $langCode ) {
		$translation = Obj::prop( $langCode, PostTranslations::get( (int) $postId ) );
		$sourceLang  = $translation ? Obj::prop( 'source_language_code', $translation ) : null;

		if ( ! $this->langPairPermissions ) {
			$this->langPairPermissions = new LangPairPermissions();
		}

		return $this->langPairPermissions->is_allowed( $sourceLang ?: Languages::getCurrentCode(), $langCode, (int) $postId );
	}

	private function setLink() {
		return function ( $data ) {
			if ( array_key_exists( 'review_status', $data ) ) {
				$review_status = $data['review_status'] ?: ReviewStatus::ACCEPTED;
				if ( ! ReviewStatus::needsReview( $review_status ) ) {
					return $data['default'];
				}
			}

			$data['status'] = (int) Obj::prop( 'status', $data );

			$isInProgress            = pipe(
				Obj::prop( 'status' ),
				Lst::includes( Fns::__, [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS, ICL_TM_ATE_NEEDS_RETRY ] )
			);
			$isInProgressOrCompleted = Logic::anyPass( [ $isInProgress, Relation::propEq( 'status', ICL_TM_COMPLETE ) ] );

			$getTranslations = Fns::memorize( PostTranslations::get() );

			$getTranslation = Fns::converge( Obj::prop(), [
				Obj::prop( 'langCode' ),
				pipe( Obj::prop( 'postId' ), $getTranslations )
			] );

			$getJob = Fns::converge( Jobs::getPostJob(), [
				Obj::prop( 'postId' ),
				Fns::always( 'post' ),
				Obj::prop( 'langCode' )
			] );

			$doesNeedsReview = pipe( Obj::prop( 'job' ), ReviewStatus::doesJobNeedReview() );

			$getPreviewLink = Fns::converge( PreviewLink::get(), [
				Obj::path( [ 'translation', 'element_id' ] ),
				Obj::path( [ 'job', 'job_id' ] )
			] );

			$getReviewLink = function ( $data ) use ( $getPreviewLink ) {
				if ( ! $this->canTranslatePair( Obj::prop( 'postId', $data ), Obj::prop( 'langCode', $data ) ) ) {
					return Obj::prop( 'default', $data );
				}

				$link              = $getPreviewLink( $data );
				$translationPostId = (int) Obj::path( [ 'translation', 'element_id' ], $data );

				return '' === $link && ( $translationPostId <= 0 || ! \get_post( $translationPostId ) )
					? Obj::prop( 'default', $data )
					: $link;
			};

			$disableInProgressIconOfAutomaticJob = Logic::ifElse(
				Logic::both( $isInProgress, Obj::path( [ 'job', 'automatic' ] ) ),
				Fns::always( 0 ),
				Obj::prop( 'default' )
			);

			return Maybe::of( $data )
			            ->filter( $isInProgressOrCompleted )
			            ->map( Obj::addProp( 'translation', $getTranslation ) )
			            ->filter( Obj::prop( 'translation' ) )
			            ->reject( Obj::path( [ 'translation', 'original' ] ) )
			            ->map( Obj::addProp( 'job', $getJob ) )
			            ->filter( Obj::prop( 'job' ) )
			            ->map( Logic::ifElse( $doesNeedsReview, $getReviewLink, $disableInProgressIconOfAutomaticJob ) )
			            ->getOrElse( Obj::prop( 'default', $data ) );
		};
	}
}
