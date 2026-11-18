<?php

namespace WPML\TranslationPolicy\Panel;

use WPML\TranslationPolicy\Policy\PolicyTable;
use WPML\TranslationPolicy\Ptc\PtcConfirm;

class PanelData {

	const METHODS = [ PolicyTable::METHOD_PTC, PolicyTable::METHOD_SELF ];

	private $choices;

	const VIEW_DASHBOARD = 'dashboard';
	const VIEW_WPML      = 'wpml';
	const VIEW_NATIVE    = 'native';

	private $typeLabel;

	private $isTranslateEverythingOn;

	private $labelsInText;

	public function __construct( PostChoices $choices, callable $typeLabel, callable $isTranslateEverythingOn, bool $labelsInText = false ) {
		$this->choices                 = $choices;
		$this->typeLabel               = $typeLabel;
		$this->labelsInText            = $labelsInText;
		$this->isTranslateEverythingOn = $isTranslateEverythingOn;
	}

	public function filter( $data, $postId ) {
		if ( ! is_array( $data ) || empty( $data['editor_mode'] ) || empty( $data['is_original'] ) ) {
			return $data;
		}

		$postId   = (int) $postId;
		$postType = (string) ( $data['post_type'] ?? '' );
		if ( $postId <= 0 || '' === $postType ) {
			return $data;
		}

		$choices   = $this->choices->forPost( $postId, $postType );
		$method    = $choices['method']['value'];
		$typeLabel = (string) call_user_func( $this->typeLabel, $postType );

		$data['controls']['policy_choice'] = true;
		if ( is_array( $data['editor_mode'] ) ) {
			$data['editor_mode']['wpml_editor_name'] = Words::wpmlEditor( $choices['wpml_editor'] );
		}
		$data['policy']                    = [
			'editor'            => self::withSameAs( $choices['editor'], $typeLabel ),
			'method'            => self::withSameAs( $choices['method'], $typeLabel ),
			'methods'           => array_map(
				function ( $value ) {
					return [
						'value' => $value,
						'label' => Words::method( $value ),
						'line'  => Words::methodLine( $value ),
					];
				},
				self::METHODS
			),
			'methods_label'     => Words::methodsLabel(),
			'wpml_editor'       => self::wpmlEditorLink( $choices['wpml_editor'], $choices['type_editor'] ),
			'native_editor'     => [
				'name' => Words::wordPressEditor(),
			],
			'open_view'         => self::openView( $choices['editor'], $choices['method'] ),
			'dashboard_status'  => Words::dashboardStatus( $method, (bool) call_user_func( $this->isTranslateEverythingOn ) ),
			'translate_heading' => Words::translateHeading( $method ),
			'reset_label'       => Words::useTypeSetting( $typeLabel ),
			'save_path'         => SaveEndpoint::path( $postId ),
			'ptc_confirm'       => [
				'needed' => ! PtcConfirm::isConfirmedBy( get_current_user_id() ),
				'path'   => '/' . PtcConfirm::REST_NAMESPACE . PtcConfirm::ROUTE,
				'words'  => PtcConfirm::dialogWords(),
			],
		];

		if ( isset( $data['translations'] ) && is_array( $data['translations'] ) ) {
			foreach ( $data['translations'] as $i => $cell ) {
				$label = is_array( $cell ) ? $this->controlLabel( $cell, $method ) : null;
				if ( null !== $label ) {
					$data['translations'][ $i ]['policy_label'] = $label;
				}
			}
		}

		return $data;
	}

	private function controlLabel( array $cell, string $method ) {
		if ( $this->labelsInText ) {
			$cssClass  = (string) ( $cell['css_class'] ?? '' );
			$isControl = in_array( $cssClass, [ \WPML_Post_Status_Display::ICON_TRANSLATION_ADD, \WPML_Post_Status_Display::ICON_TRANSLATION_NEEDS_UPDATE ], true );

			return $isControl && '' !== (string) ( $cell['text'] ?? '' ) ? (string) $cell['text'] : null;
		}

		return empty( $cell['has_translation'] ) ? Words::translateTo( $method, (string) ( $cell['display_name'] ?? '' ) ) : null;
	}

	private static function openView( array $editor, array $method ): string {
		$decides = [ PostChoices::STATE_OWN, PostChoices::STATE_LOCK ];
		if ( ! in_array( $editor['state'], $decides, true ) && ! in_array( $method['state'], $decides, true ) ) {
			return self::VIEW_DASHBOARD;
		}

		return in_array( $editor['value'], PolicyTable::WPML_EDITORS, true ) ? self::VIEW_WPML : self::VIEW_NATIVE;
	}

	private static function withSameAs( array $column, string $typeLabel ): array {
		$column['same_as'] = PostChoices::STATE_INHERITED === $column['state'] ? Words::sameAsType( $typeLabel ) : null;

		return $column;
	}

	private static function wpmlEditorLink( string $editor, string $typeEditor ): array {
		$other = PolicyTable::EDITOR_CTE === $editor ? PolicyTable::EDITOR_ATE : PolicyTable::EDITOR_CTE;

		return [
			'value'        => $editor,
			'name'         => Words::wpmlEditor( $editor ),
			'switch_to'    => $other,
			'switch_label' => Words::useWpmlEditor( $other ),
			'for_type'     => in_array( $typeEditor, PolicyTable::WPML_EDITORS, true ),
		];
	}
}
