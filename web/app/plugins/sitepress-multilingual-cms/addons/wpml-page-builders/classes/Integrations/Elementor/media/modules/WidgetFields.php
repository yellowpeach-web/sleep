<?php

namespace WPML\PB\Elementor\Media\Modules;

class WidgetFields extends \WPML_Elementor_Media_Node {

	const WIDGETS = [
		'testimonial'                  => [
			'simple_fields' => [ 'testimonial_image' ],
		],
		'flip-box'                     => [
			'simple_fields'     => [ 'image' ],
			'background_fields' => [ 'background_a', 'background_b' ],
		],
		'author-box'                   => [
			'simple_fields' => [ 'author_avatar' ],
		],
		'price-list'                   => [
			'repeater_fields' => [ 'price_list' => [ 'image' ] ],
		],
		'testimonial-carousel'         => [
			'repeater_fields' => [ 'slides' => [ 'image' ] ],
		],
		'reviews'                      => [
			'repeater_fields' => [ 'slides' => [ 'image' ] ],
		],
		'text-path'                    => [
			'simple_fields' => [ 'custom_path' ],
		],
		'lottie'                       => [
			'simple_fields' => [ 'source_json' ],
		],
		'floating-bars-var-1'          => self::FLOATING_BARS,
		'floating-bars-var-2'          => self::FLOATING_BARS,
		'floating-bars-var-3'          => self::FLOATING_BARS,
		'theme-post-featured-image'    => [
			'simple_fields' => [ 'image' ],
		],
		'woocommerce-category-image'   => [
			'simple_fields' => [ 'image' ],
		],
		'woocommerce-checkout-page'    => [
			'background_fields' => [
				'forms_fields_normal_background',
				'forms_fields_focus_background',
				'forms_buttons_normal_background',
				'forms_buttons_hover_background',
				'purchase_button_normal_background',
				'purchase_button_hover_background',
				'payment_info_box_title_background',
			],
		],
		'woocommerce-cart'             => [
			'background_fields' => [
				'forms_fields_normal_background',
				'forms_fields_focus_background',
				'forms_buttons_normal_background',
				'forms_buttons_hover_background',
				'checkout_button_normal_background',
				'checkout_button_hover_background',
			],
		],
		'woocommerce-my-account'       => [
			'background_fields' => [
				'tabs_normal_background',
				'tabs_hover_background',
				'tabs_active_background',
				'forms_fields_normal_background',
				'forms_fields_focus_background',
				'forms_buttons_background',
				'forms_buttons_hover_background',
				'tables_button_normal_background',
				'tables_button_hover_background',
			],
		],
		'woocommerce-purchase-summary' => [
			'background_fields' => [
				'order_details_button_normal_background',
				'order_details_button_hover_background',
			],
		],
	];

	const FLOATING_BARS = [
		'background_fields' => [ 'floating_bar_background_type', 'floating_bar_background_overlay_type' ],
	];

	private $fields;

	public function __construct( \IWPML_PB_Media_Find_And_Translate $media_translate, array $fields ) {
		parent::__construct( $media_translate );
		$this->fields = $fields;
	}

	public function translate( $settings, $target_lang, $source_lang ) {
		foreach ( $this->getList( 'simple_fields' ) as $field ) {
			$settings = $this->translate_image_property( $settings, $field, $target_lang, $source_lang );
		}

		$backgroundImages = array_map(
			function ( $prefix ) {
				return $prefix . '_image';
			},
			$this->getList( 'background_fields' )
		);
		foreach ( AllNodes::getDeviceFields( $backgroundImages ) as $field ) {
			$settings = $this->translate_image_property( $settings, $field, $target_lang, $source_lang );
		}

		foreach ( $this->getList( 'repeater_fields' ) as $repeater => $fields ) {
			if ( ! isset( $settings[ $repeater ] ) || ! is_array( $settings[ $repeater ] ) ) {
				continue;
			}

			foreach ( $settings[ $repeater ] as &$item ) {
				foreach ( $fields as $field ) {
					$item = $this->translate_image_property( $item, $field, $target_lang, $source_lang );
				}
			}
			unset( $item );
		}

		return $settings;
	}

	private function getList( string $type ): array {
		return $this->fields[ $type ] ?? [];
	}
}
