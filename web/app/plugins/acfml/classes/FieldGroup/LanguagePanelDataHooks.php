<?php

namespace ACFML\FieldGroup;

class LanguagePanelDataHooks implements \IWPML_Action {

	public function add_hooks() {
		add_filter( 'wpml_post_language_panel_data', [ $this, 'addFieldGroups' ], 10, 2 );
	}

	public function addFieldGroups( $data, $postId ) {
		if ( ! is_array( $data ) || empty( $data['content_setup'] ) || ! is_array( $data['content_setup'] ) ) {
			return $data;
		}
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return $data;
		}

		$groups = GroupsOnPost::filter( (array) acf_get_field_groups(), (int) $postId );

		$data['content_setup']['acf_field_groups'] = array_values( array_map( [ self::class, 'describe' ], $groups ) );

		return $data;
	}

	private static function describe( array $group ) {
		$id      = isset( $group['ID'] ) ? (int) $group['ID'] : 0;
		$isLocal = ! empty( $group['local'] );

		return [
			'key'       => isset( $group['key'] ) ? (string) $group['key'] : '',
			'title'     => isset( $group['title'] ) ? (string) $group['title'] : '',
			'edit_link' => $id > 0 && ! $isLocal ? ( get_edit_post_link( $id, '' ) ?: null ) : null,
		];
	}
}
