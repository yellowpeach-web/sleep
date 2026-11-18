<?php

namespace WPML\TranslationPolicy\Panel;

use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\TM\Editor\Dispatch\DispatchPlanFactory;
use WPML\TM\PostEditScreen\TranslationEditorPostSettings;
use WPML\TranslationPolicy\FeatureSwitch;
use WPML\TranslationPolicy\Policy\PolicySql;
use WPML\TranslationPolicy\Policy\PolicyTable;
use WPML\TranslationPolicy\Policy\PolicyWriter;
use WPML\TranslationPolicy\Ptc\PtcConfirm;
use function WPML\Container\make;

class Loader implements \IWPML_Backend_Action, \IWPML_REST_Action {

	private $controlLabels = false;

	public function add_hooks() {
		add_filter( 'wpml_post_language_panel_data', [ $this, 'filterPanelData' ], 10, 2 );
		add_filter( 'wpml_text_to_translation', [ $this, 'filterControlText' ], ControlLabels::PRIORITY, 5 );
		add_action( 'rest_api_init', [ $this, 'registerRoute' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueClassicBox' ] );
	}

	public function enqueueClassicBox() {
		if ( ! FeatureSwitch::isOn() ) {
			return;
		}

		( new ClassicBox(
			function () {
				global $sitepress;

				return make( TranslationEditorPostSettings::class, [ ':sitepress' => $sitepress ] );
			}
		) )->enqueue();
	}

	public function filterPanelData( $data, $postId ) {
		if ( ! FeatureSwitch::isOn() ) {
			return $data;
		}

		return ( new PanelData( self::choices(), [ self::class, 'typeLabel' ], [ AutomaticTranslationCapabilities::class, 'shouldTranslateEverything' ], null !== $this->controlLabels() ) )->filter( $data, $postId );
	}

	public function filterControlText( $text, $postId, $language, $trid, $cssClass = '' ) {
		$labels = $this->controlLabels();

		return $labels ? $labels->filterText( $text, $postId, $language, $trid, $cssClass ) : $text;
	}

	private function controlLabels() {
		if ( false === $this->controlLabels ) {
			$this->controlLabels = FeatureSwitch::isOn() ? self::createControlLabels() : null;
		}

		return $this->controlLabels;
	}

	private static function createControlLabels(): ControlLabels {
		global $sitepress;

		$plan = DispatchPlanFactory::create();

		return new ControlLabels(
			[ $plan, 'translatesAutomatically' ],
			function ( $postId ) use ( $sitepress ) {
				$original = $sitepress->post_translations()->get_original_element( $postId, true );

				return (int) ( $original ?: $postId );
			},
			function ( $language ) use ( $sitepress ) {
				$details = $sitepress->get_language_details( $language );

				return is_array( $details ) && isset( $details['display_name'] ) ? (string) $details['display_name'] : '';
			}
		);
	}

	public function registerRoute() {
		if ( ! FeatureSwitch::isOn() ) {
			return;
		}

		( new SaveEndpoint(
			function () {
				global $wpdb;

				$sql = new PolicySql( $wpdb );

				return new SavePostChoice( $sql, new PolicyWriter( $wpdb, new PolicyTable( $wpdb ), $sql ), new PostChoices( $sql ) );
			},
			[ SaveEndpoint::class, 'readPanel' ]
		) )->registerRoute();

		PtcConfirm::create()->registerRoute();
	}

	public static function typeLabel( string $postType ): string {
		$type  = get_post_type_object( $postType );
		$label = $type && isset( $type->labels->name ) ? $type->labels->name : '';

		return is_string( $label ) && '' !== $label ? $label : $postType;
	}

	private static function choices(): PostChoices {
		global $wpdb;

		return new PostChoices( new PolicySql( $wpdb ) );
	}
}
