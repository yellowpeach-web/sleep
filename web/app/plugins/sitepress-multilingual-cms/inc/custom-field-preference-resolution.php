<?php

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\ContainerFreeServices;
use WPML\TM\Settings\PreferenceWriter;

function wpml_resolve_custom_field_preferences( array $meta_keys, $element_type = 'post' ) {
	if ( ! $meta_keys ) {
		return [];
	}

	return ContainerFreeServices::unknownPreferenceResolver()->resolve(
		array_map( 'strval', $meta_keys ),
		'term' === $element_type ? ElementType::TERM : ElementType::POST
	);
}

function wpml_delete_custom_field_preferences( array $meta_keys, $element_type = 'post' ) {
	if ( ! $meta_keys ) {
		return true;
	}

	return PreferenceWriter::deleteNames(
		'term' === $element_type ? ElementType::TERM : ElementType::POST,
		array_map( 'strval', $meta_keys )
	);
}

add_action(
	'switch_blog',
	fn() => ContainerFreeServices::unknownPreferenceResolver()->reset()
);
