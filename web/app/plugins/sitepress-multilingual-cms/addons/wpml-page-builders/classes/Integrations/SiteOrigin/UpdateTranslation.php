<?php

namespace WPML\PB\SiteOrigin;

class UpdateTranslation extends \WPML_Page_Builders_Update_Translation {

	private const POST_LINK_PREFIX  = 'post: ';
	private const POST_LINK_PATTERN = '/^post: *([0-9]+)$/';

	private $positionStringNames;

	public function __construct(
		\IWPML_Page_Builders_Translatable_Nodes $translatable_nodes,
		\IWPML_Page_Builders_Data_Settings $data_settings,
		?PositionStringNames $positionStringNames = null
	) {
		parent::__construct( $translatable_nodes, $data_settings );
		$this->positionStringNames = $positionStringNames ?: new PositionStringNames( $translatable_nodes );
	}

	public function update_strings_in_modules( array &$data_array ) {
		foreach ( $data_array as &$data ) {
			if ( isset( $data[ TranslatableNodes::SETTINGS_FIELD ] ) ) {
				if ( TranslatableNodes::isWrappingModule( $data ) ) {
					$this->update_strings_in_modules( $data[ TranslatableNodes::CHILDREN_FIELD ] );
				} else {
					$data = $this->update_strings_in_node( $data[ TranslatableNodes::SETTINGS_FIELD ]['class'], $data );
					$data = BuilderFields::mapWidgetLists(
						$data,
						function ( array $widgets ) {
							$this->update_strings_in_modules( $widgets );

							return $widgets;
						}
					);
				}
			} elseif ( is_array( $data ) ) {
				$this->update_strings_in_modules( $data );
			}
		}
	}

	public function update_strings_in_node( $node_id, $settings ) {
		$strings          = $this->translatable_nodes->get( $node_id, $settings );
		$originalSettings = $settings;
		$positionNames    = null;

		foreach ( $strings as $index => $pbString ) {
			$translatedValue = $this->findTranslatedValue( $pbString->get_name() );

			if ( null === $translatedValue ) {
				$positionNames   = $positionNames ?? $this->positionStringNames->get( $node_id, $originalSettings );
				$translatedValue = $this->findTranslatedValueUnderPositionName( $pbString, $positionNames[ $index ] ?? null );
			}

			$pbString->set_value( $this->linkToTranslatedPost( $pbString, $translatedValue ?? $pbString->get_value() ) );

			$settings = $this->translatable_nodes->update( $node_id, $settings, $pbString );
		}
		return $settings;
	}

	private function findTranslatedValueUnderPositionName( \WPML_PB_String $pbString, ?string $positionName ): ?string {
		if ( ! $positionName || $positionName === $pbString->get_name() ) {
			return null;
		}

		return $this->findTranslatedValue( $positionName );
	}

	private function linkToTranslatedPost( \WPML_PB_String $pbString, $value ) {
		if (
			! is_string( $value )
			|| \WPML_TM_Page_Builders::FIELD_STYLE_LINK !== $pbString->get_editor_type()
			|| ! preg_match( self::POST_LINK_PATTERN, $value, $matches )
		) {
			return $value;
		}

		$translatedPostId = apply_filters( 'wpml_object_id', (int) $matches[1], 'any', true, $this->get_lang() );

		return self::POST_LINK_PREFIX . $translatedPostId;
	}

	private function findTranslatedValue( string $name ): ?string {
		return $this->get_string_translations()[ $name ][ $this->get_lang() ]['value'] ?? null;
	}
}
