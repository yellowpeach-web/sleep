<?php

use WPML\PB\Cornerstone\Modules\ComponentParameters;
use WPML\PB\Cornerstone\Modules\ModuleWithItemsFromConfig;
use WPML\FP\Obj;
use WPML\PB\Cornerstone\Utils;
use WPML\PB\TranslationJob\SourceLabels;

class WPML_Cornerstone_Translatable_Nodes implements IWPML_Page_Builders_Translatable_Nodes {

	const SETTINGS_FIELD = '_modules';

	protected $nodes_to_translate;

	private $fields_by_node_type;

	public function get( $node_id, $settings ) {

		if ( isset( $settings['_type'] ) && Utils::typeIsLayout( $settings['_type'] ) ) {
			return $this->get_background_image_alt_strings( $node_id, $settings );
		}

		if ( ! $this->nodes_to_translate ) {
			$this->initialize_nodes_to_translate();
		}

		$strings = array();

		foreach ( $this->nodes_to_translate as $node_type => $node_data ) {
			if ( $this->conditions_ok( $node_data, $settings ) ) {
				foreach ( $node_data['fields'] as $field ) {
					$field_key = $field['field'];
					if ( isset( $settings[ $field_key ] ) && trim( $settings[ $field_key ] ) ) {

						$string = new WPML_PB_String(
							$settings[ $field_key ],
							$this->get_string_name( $node_id, $field, $settings ),
							$field['type'],
							$field['editor_type'],
							$this->get_wrap_tag( $settings )
						);

						$strings[] = $string;
					}
				}

				foreach ( $this->get_integration_instances( $node_data ) as $node ) {
					$strings = $node->get( $node_id, $settings, $strings );
				}
			}
		}

		return $strings;
	}

	private function get_background_image_alt_strings( $node_id, $settings ) {
		$strings = [];

		foreach ( $this->get_background_image_alt_fields() as $field ) {
			$value = Obj::prop( $field['field'], $settings );
			if ( is_string( $value ) && trim( $value ) ) {
				$strings[] = new WPML_PB_String(
					$value,
					$this->get_string_name( $node_id, $field, $settings ),
					$field['type'],
					$field['editor_type']
				);
			}
		}

		return $strings;
	}

	private function get_background_image_alt_fields() {
		return [
			[
				'field'       => 'bg_lower_img_alt',
				/* translators: Field label in WPML's translation editor for a page built with Cornerstone. It is the alternative text of the image in the lower background layer of a section, row or column. */
				'type'        => __( 'Background: Lower Layer Image Alt Text', 'sitepress' ),
				'editor_type' => 'LINE',
			],
			[
				'field'       => 'bg_upper_img_alt',
				/* translators: Field label in WPML's translation editor for a page built with Cornerstone. It is the alternative text of the image in the upper background layer of a section, row or column. */
				'type'        => __( 'Background: Upper Layer Image Alt Text', 'sitepress' ),
				'editor_type' => 'LINE',
			],
		];
	}

	public function update( $node_id, $settings, WPML_PB_String $string ) {

		if ( ! $this->nodes_to_translate ) {
			$this->initialize_nodes_to_translate();
		}

		foreach ( $this->nodes_to_translate as $node_type => $node_data ) {
			if ( $this->conditions_ok( $node_data, $settings ) ) {
				foreach ( $node_data['fields'] as $field ) {
					$field_key = $field['field'];
					if ( $this->get_string_name( $node_id, $field, $settings ) === $string->get_name() ) {
						$settings[ $field_key ] = $string->get_value();
					}
				}

				foreach ( $this->get_integration_instances( $node_data ) as $node ) {
					$settings = $node->update( $node_id, $settings, $string );
				}
			}
		}

		foreach ( $this->get_background_image_alt_fields() as $field ) {
			if ( isset( $settings[ $field['field'] ] ) && $this->get_string_name( $node_id, $field, $settings ) === $string->get_name() ) {
				$settings[ $field['field'] ] = $string->get_value();
			}
		}

		return $settings;
	}

	private function get_integration_instances( $node_data ) {
		$instances = [];

		if ( isset( $node_data['integration-class'] ) ) {
			try {
				$instances[] = new $node_data['integration-class']();
			} catch ( Exception $e ) {}
		}

		if ( isset( $node_data['fields_in_item'] ) ) {
			foreach ( $node_data['fields_in_item'] as $config ) {
				$instances[] = new ModuleWithItemsFromConfig( $config );
			}
		}

		foreach ( $instances as $instance ) {
			if ( $instance instanceof WPML_Cornerstone_Module_With_Items ) {
				$instance->set_fields_of_item_nodes( $this->get_fields_by_node_type() );
			}
		}

		return $instances;
	}

	private function get_fields_by_node_type() {
		if ( null !== $this->fields_by_node_type ) {
			return $this->fields_by_node_type;
		}

		$this->fields_by_node_type = [];

		foreach ( $this->nodes_to_translate as $node_data ) {
			$node_type = Obj::path( [ 'conditions', '_type' ], $node_data );
			if ( is_string( $node_type ) && ! empty( $node_data['fields'] ) ) {
				$this->fields_by_node_type[ $node_type ] = array_column( $node_data['fields'], 'field' );
			}
		}

		return $this->fields_by_node_type;
	}

	public function get_string_name( $node_id, $field, $settings ) {
		return $field['field'] . '-' . $settings['_type'] . '-' . $node_id;
	}

	private function get_wrap_tag( $settings ) {
		if ( isset( $settings['_type'] ) && 'headline' === $settings['_type'] ) {
			return Obj::propOr( 'h1', 'text_tag', $settings );
		}

		return '';
	}

	private function conditions_ok( $node_data, $settings ) {
		$conditions_meet = true;
		foreach ( $node_data['conditions'] as $field_key => $field_value ) {
			if ( ! isset( $settings[ $field_key ] ) || $settings[ $field_key ] !== $field_value ) {
				$conditions_meet = false;
				break;
			}
		}

		return $conditions_meet;
	}

	public static function get_nodes_to_translate() {
		return [
			'card'                    => [
				'conditions' => [ '_type' => 'card' ],
				'fields'     => [
					[
						'field'       => 'card_front_text_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Card: Front Text Content', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
					[
						'field'       => 'card_back_text_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Card: Back Text Content', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
					[
						'field'       => 'anchor_text_primary_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Card: Anchor Text Primary Content', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
					[
						'field'       => 'anchor_text_secondary_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Card: Anchor Text Secondary Content', 'sitepress' ),
						'editor_type' => 'LINE',
					],
					[
						'field'       => 'anchor_href',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Card: Anchor Link', 'sitepress' ),
						'editor_type' => 'LINK',
					],
				],
			],
			'alert'                   => [
				'conditions' => [ '_type' => 'alert' ],
				'fields'     => [
					[
						'field'       => 'alert_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Alert Content: Alert', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
				],
			],
			'text'                    => [
				'conditions' => [ '_type' => 'text' ],
				'fields'     => [
					[
						'field'       => 'text_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Text Content: Text', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
				],
			],
			'quote'                   => [
				'conditions' => [ '_type' => 'quote' ],
				'fields'     => [
					[
						'field'       => 'quote_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Quote: Quote Content', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
					[
						'field'       => 'quote_cite_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Quote: Quote Cite', 'sitepress' ),
						'editor_type' => 'LINE',
					],
				],
			],
			'counter'                 => [
				'conditions' => [ '_type' => 'counter' ],
				'fields'     => [
					[
						'field'       => 'counter_number_prefix_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Counter: Number Prefix', 'sitepress' ),
						'editor_type' => 'LINE',
					],
					[
						'field'       => 'counter_number_suffix_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Counter: Number Suffix', 'sitepress' ),
						'editor_type' => 'LINE',
					],
				],
			],
			'content-area'            => [
				'conditions' => [ '_type' => 'content-area' ],
				'fields'     => [
					[
						'field'       => 'content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Content Area: Content', 'sitepress' ),
						'editor_type' => 'AREA',
					],
				],
			],
			'breadcrumbs'             => [
				'conditions' => [ '_type' => 'breadcrumbs' ],
				'fields'     => [
					[
						'field'       => 'breadcrumbs_home_label_text',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Breadcrumbs: Home Label Text', 'sitepress' ),
						'editor_type' => 'LINE',
					],
				],
			],
			'audio'                   => [
				'conditions' => [ '_type' => 'audio' ],
				'fields'     => [
					[
						'field'       => 'audio_embed_code',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Audio: Embed Code', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
				],
			],
			'headline'                => [
				'conditions' => [ '_type' => 'headline' ],
				'fields'     => [
					[
						'field'       => 'text_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Headline Content: Headline', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
				],
			],
			'content-area-off-canvas' => [
				'conditions' => [ '_type' => 'content-area-off-canvas' ],
				'fields'     => [
					[
						'field'       => 'off_canvas_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Canvas Content: Canvas', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
				],
			],
			'content-area-modal'      => [
				'conditions' => [ '_type' => 'content-area-modal' ],
				'fields'     => [
					[
						'field'       => 'modal_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Modal Content: Modal', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
				],
			],
			'content-area-dropdown'   => [
				'conditions' => [ '_type' => 'content-area-dropdown' ],
				'fields'     => [
					[
						'field'       => 'dropdown_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Dropdown Content: Dropdown', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
				],
			],
			'button'                  => [
				'conditions' => [ '_type' => 'button' ],
				'fields'     => [
					[
						'field'       => 'anchor_text_primary_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Anchor Text: Primary Content', 'sitepress' ),
						'editor_type' => 'LINE',
					],
					[
						'field'       => 'anchor_text_secondary_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Anchor Text: Secondary Content', 'sitepress' ),
						'editor_type' => 'LINE',
					],
				],
			],
			'video'                   => [
				'conditions' => [ '_type' => 'video' ],
				'fields'     => [
					[
						'field'       => 'video_embed_code',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Video: Embed Code', 'sitepress' ),
						'editor_type' => 'LINE',
					],
				],
			],
			'search-inline'           => [
				'conditions' => [ '_type' => 'search-inline' ],
				'fields'     => [
					[
						'field'       => 'search_placeholder',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Search Inline: Placeholder', 'sitepress' ),
						'editor_type' => 'LINE',
					],
				],
			],
			'search-modal'            => [
				'conditions' => [ '_type' => 'search-modal' ],
				'fields'     => [
					[
						'field'       => 'search_placeholder',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Search Modal: Placeholder', 'sitepress' ),
						'editor_type' => 'LINE',
					],
				],
			],
			'search-dropdown'         => [
				'conditions' => [ '_type' => 'search-dropdown' ],
				'fields'     => [
					[
						'field'       => 'search_placeholder',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Search Dropdown: Placeholder', 'sitepress' ),
						'editor_type' => 'LINE',
					],
				],
			],
			'layout-table-cell'       => [
				'conditions' => [ '_type' => 'layout-table-cell' ],
				'fields'     => [
					[
						'field'       => 'text_content',
						/* translators: Field label in WPML's translation editor for a page built with Cornerstone. Before the colon is the name Cornerstone gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
						'type'        => __( 'Table Cell: Text Content', 'sitepress' ),
						'editor_type' => 'VISUAL',
					],
				],
			],
			'accordion'               => [
				'conditions'        => [ '_type' => 'accordion' ],
				'fields'            => [],
				'integration-class' => 'WPML_Cornerstone_Accordion',
			],
			'tabs'                    => [
				'conditions'        => [ '_type' => 'tabs' ],
				'fields'            => [],
				'integration-class' => 'WPML_Cornerstone_Tabs',
			],
			'component'               => [
				'conditions'        => [ '_type' => 'component' ],
				'fields'            => [],
				'integration-class' => ComponentParameters::class,
			],
		];
	}

	public function initialize_nodes_to_translate() {
		$this->nodes_to_translate  = apply_filters( 'wpml_cornerstone_modules_to_translate', SourceLabels::run( [ self::class, 'get_nodes_to_translate' ] ) );
		$this->fields_by_node_type = null;
	}
}
