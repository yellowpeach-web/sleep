<?php

use WPML\Element\API\PostTranslations;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use function WPML\FP\pipe;
use function WPML\FP\spreadArgs;

class WPML_ACF_Location_Rules implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {
	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		Hooks::onFilter( 'acf/location/rule_match', 11, 3 )->then( spreadArgs( [ $this, 'rule_match' ] ) );
		Hooks::onFilter( 'acf/load_field_group' )->then( spreadArgs( [ $this, 'adjust_post_id_on_edit_screen' ] ) );
	}
	
	public function rule_match( $match, $rule, $options ) {
		$match = $this->rule_match_post( $match, $rule, $options );
		return $this->rule_match_page_parent( $match, $rule, $options );
	}

	private function rule_match_post( $match, $rule, $options ) {
		if ( isset( $rule['param'] )
			 && in_array( $rule['param'], get_post_types() )
			 && ! $this->sitepress->is_translated_post_type( 'acf-field-group' )
			 && isset( $options['post_id'] )
		) {
			$valid_translation_ids = array_filter( self::get_translation_ids( $options['post_id'] ), fn( $id ) => 0 !== $id );
			$match = $this->match_against_operator(
				in_array( (int) $rule['value'], $valid_translation_ids, true ),
				$rule['operator']
			);
		}

		return $match;
	}
	
	private function rule_match_page_parent( $match, $rule, $options ) {
		if ( isset( $rule['param'], $rule['value'], $rule['operator'], $options['lang'] )
			 && 'page_parent' === $rule['param']
			 && ! $this->sitepress->is_translated_post_type( 'acf-field-group' )
		) {
			$page_parent = Obj::propOr( wp_get_post_parent_id( get_the_ID() ), 'page_parent', $options );
			$valid_translation_ids = array_filter( self::get_translation_ids( $rule['value'] ), fn( $id ) => 0 !== $id );
			$match       = $this->match_against_operator(
				intval( Obj::propOr( $rule['value'], $options['lang'], $valid_translation_ids ) ) === intval( $page_parent ),
				$rule['operator']
			);
		}
		return $match;
	}
	
	private function match_against_operator( $match, $operator ) {
		if ( '!=' === $operator ) {
			$match = ! $match;
		}
		return $match;
	}
	
	public function adjust_post_id_on_edit_screen( $group ) {
		if ( $this->is_field_group_edit_screen( $group['ID'] ) &&
			$this->group_has_post_rule( $group )
		) {
			$group['location'] = $this->replace_post_ids( $group['location'] );
		}
		return $group;
	}
	
	private function is_field_group_edit_screen( $groupId ) {
		return
			isset( $_GET['post'], $_GET['action'] ) &&
			(int) $groupId === (int) $_GET['post'] &&
			'edit' === $_GET['action'] &&
			get_post_type( $groupId ) === 'acf-field-group';
	}
	
	private function group_has_post_rule( $group ) {
		if ( isset( $group['location'] ) && is_array( $group['location'] ) ) {
			return $this->has_post_rule( $group['location'] );
		}
		return false;
	}
	
	private function has_post_rule( $location ) {
		foreach ( $location as $chunk ) {
			if ( isset( $chunk['param'] ) ) {
				if( in_array( $chunk['param'], get_post_types() ) ) {
					return true;
				}
			} elseif( is_array( $chunk ) ) {
				return $this->has_post_rule( $chunk );
			}
		}
		return false;
	}
	
	private function replace_post_ids( $location ) {
		foreach( $location as $key => $chunk ) {
			if ( isset( $chunk['param'], $chunk['value'] ) && is_numeric( $chunk['value'] ) && in_array( $chunk['param'], get_post_types() ) ) {
				$location[ $key ]['value'] = $this->replace_id( $location[ $key ]['value'], $chunk['param'] );
			} elseif ( is_array( $chunk ) ) {
				$location[ $key ] = $this->replace_post_ids( $chunk );
			}
		}
		return $location;
	}
	
	private function replace_id( $post_id, $post_type ) {
		return apply_filters( 'wpml_object_id', $post_id, $post_type, true );
	}

	public static function get_translation_ids( $postId ) {
		return wpml_collect( PostTranslations::get( $postId ) )
			->mapWithKeys( function( $data ) {
				return [ Obj::prop( 'language_code', $data ) => intval( Obj::prop( 'element_id', $data ) ) ];
			} )
			->toArray();
	}
}
