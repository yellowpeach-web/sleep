<?php

namespace ACFML\TranslationEditor;

use ACFML\Helper\Fields;
use ACFML\Field\CompositeValue;
use ACFML\FieldGroup\FieldNamePatterns;
use ACFML\Strings\Config;
use ACFML\Strings\Factory as StringsFactory;
use ACFML\Strings\Package;
use ACFML\Strings\TranslationJobFilter;
use WPML\FP\Fns;
use WPML\FP\Str;
use WPML\FP\Obj;

class JobFilter implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const ACF_TOP_LEVEL_GROUP_ID    = 'acf';
	const ACF_TOP_LEVEL_GROUP_TITLE = 'Advanced Custom Fields (ACF)';

	const SPECIAL_LABELS = [
		'cpt'          => 'Post Type',
		'taxonomy'     => 'Taxonomy',
		'options-page' => 'Options Page',
	];

	private $jobToGroupId = [];

	private $fieldNamePatterns;

	public function __construct( FieldNamePatterns $fieldNamePatterns ) {
		$this->fieldNamePatterns = $fieldNamePatterns;
	}

	public function add_hooks() {
		add_filter( 'wpml_tm_adjust_translation_fields', [ $this, 'addTitleAndGroupInfo' ], 10, 2 );
		add_filter( 'wpml_tm_adjust_translation_job', [ $this, 'reorderFields' ], 10, 2 );
	}

	public function addTitleAndGroupInfo( $fields, $job = null ) {
		if ( ! is_array( $fields ) || ! is_object( $job ) || empty( $job->original_doc_id ) ) {
			return $fields;
		}

		foreach ( $fields as &$field ) {
			$field = $this->processField( $field, $job );
		}

		return $fields;
	}

	private function processField( $field, $job ) {
		$fieldTitle                           = (string) Obj::prop( 'title', $field );
		$groupKeyFromJob                      = $this->getGroupKeyFromJob( $job );
		list( $groupKey, , $namespace, $key ) = TranslationJobFilter::parseFieldName( $fieldTitle, $groupKeyFromJob );
		$isSimpleLabel                        = $groupKey && $namespace && $key;

		$matchSpecialLabels = function( $string ) {
			return wpml_collect( self::SPECIAL_LABELS )
				->keys()
				->first( Str::startsWith( Fns::__, $string ) );
		};

		if ( $isSimpleLabel ) {
			$label = Obj::prop( 'title', Config::get( $namespace, $key ) );
			$field = $this->handleFieldLabels( $field, $label, $fieldTitle, $groupKey );
		} elseif ( $matchSpecialLabels( $fieldTitle ) ) {
			$prefix = $matchSpecialLabels( $fieldTitle );
			$field  = $this->handleSpecialLabels( $field, $prefix );
		} else {
			$field = $this->handleContent( $field, $job );
		}

		return $field;
	}

	private function handleFieldLabels( $field, $label, $title, $groupKey ) {
		$field['title'] = $label ?: $title;
		$field['group'] = [
			self::ACF_TOP_LEVEL_GROUP_ID => self::ACF_TOP_LEVEL_GROUP_TITLE,
		];

		$fieldGroup = acf_get_field_group( $groupKey );

		$field['group'][ 'acf_labels_' . $groupKey ] = sprintf( '%s Labels', $fieldGroup['title'] );

		return $field;
	}

	private function handleSpecialLabels( $field, $prefix ) {
		$field['group'] = [
			self::ACF_TOP_LEVEL_GROUP_ID => self::ACF_TOP_LEVEL_GROUP_TITLE,
			$prefix . '-labels'          => self::SPECIAL_LABELS[ $prefix ] . ' Labels',
		];
		$field['title'] = substr( $field['title'], strlen( $prefix ) );
		$field['title'] = preg_replace( '/-[0-9a-f]+$/', '', $field['title'] );
		$field['title'] = apply_filters( 'wpml_labelize_string', $field['title'] );
		$field['title'] = trim( $field['title'] );

		return $field;
	}

	private function handleContent( $field, $job ) {
		$fieldName   = Str::match( '/^field-(.*?)-\d+(?:-.+)?$/', $field['field_type'] );
		$customField = $fieldName ? $fieldName[1] : '';

		if ( $customField ) {
			return $this->handleCustomField( $field, $job->original_doc_id, $customField );
		}

		$optionFieldName = Str::match( '/^options-(.*?)-(field_(.*?))-(.*?)$/', $field['field_type'] );
		$optionField     = $optionFieldName ? $optionFieldName[1] : '';

		if ( $optionField ) {
			list( $optionField ) = CompositeValue::splitName( $optionField );

			return $this->handleCustomField( $field, 'options', $optionField );
		}

		return $field;
	}

	private function handleCustomField( array $field, $objectId, string $customField ) {
		$acfObject = get_field_object( $customField, $objectId );

		if ( false !== $acfObject ) {
			$parentId   = $acfObject['parent'];
			$fieldGroup = acf_get_field_group( $parentId );

			return $this->handleAcfField( $field, $objectId, $acfObject, $fieldGroup );
		}

		return $field;
	}

	private function handleAcfField( array $field, $objectId, array $acfObject, $fieldGroup ) {
		if ( ! $fieldGroup ) {
			return $this->handleAcfSubField( $field, $objectId, $acfObject, acf_get_field_group( $this->getGroupKeyWithPatterns( $acfObject['name'] ) ) );
		}

		$field['title'] = $acfObject['label'];
		$field['group'] = [
			self::ACF_TOP_LEVEL_GROUP_ID => self::ACF_TOP_LEVEL_GROUP_TITLE,
			$fieldGroup['key']           => $fieldGroup['title'],
		];

		return $field;
	}

	private function handleAcfSubField( array $field, $objectId, array $acfObject, $fieldGroup = false ) {
		$parent = $acfObject['parent'];
		$title  = $acfObject['label'];
		$index  = '';

		$isTopLevelField = function( $id ) {
			return (bool) acf_get_field_group( $id );
		};

		while ( ! $isTopLevelField( $parent ) ) {
			$parentName = Str::match( '/^(.*)_(\d+)_' . $acfObject['_name'] . '$/', $acfObject['name'] );
			if ( $parentName ) {
				$index = ' #' . ( $parentName[2] + 1 );
			} else {
				$parentName = Str::match( '/^(.*)_' . $acfObject['_name'] . '$/', $acfObject['name'] );
			}

			$parentObject = get_field_object( $parentName[1], $objectId );
			if ( ! $parentObject ) {
				break;
			}

			$fieldTitle = $parentObject['label'] . $index;
			$title      = $fieldTitle . ' / ' . $title;

			$parent     = $parentObject['parent'];
			$fieldGroup = (bool) $fieldGroup ? $fieldGroup : acf_get_field_group( $parent );
			$acfObject  = $parentObject;
		}

		$field['title'] = $title;
		$field['group'] = [
			self::ACF_TOP_LEVEL_GROUP_ID => self::ACF_TOP_LEVEL_GROUP_TITLE,
		];

		if ( $fieldGroup && isset( $fieldGroup['key'], $fieldGroup['title'] ) ) {
			$field['group'][ $fieldGroup['key'] ] = $fieldGroup['title'];
		}

		return $field;
	}

	private function getGroupKeyWithPatterns( string $name ) {
		return $this->fieldNamePatterns->findMatchingGroup( $name );
	}

	private function getGroupKeyFromJob( $job ) {
		if ( ! array_key_exists( $job->original_doc_id, $this->jobToGroupId ) ) {
			if ( Package::FIELD_GROUP_PACKAGE_ELEMENT_TYPE === $job->original_post_type ) {
				$this->jobToGroupId[ $job->original_doc_id ] = StringsFactory::createWpmlPackage( $job->original_doc_id )->name;
			} else {
				$this->jobToGroupId[ $job->original_doc_id ] = null;
			}
		}

		return $this->jobToGroupId[ $job->original_doc_id ];
	}

	public function reorderFields( $jobFields, $job = null ) {
		if ( ! is_array( $jobFields ) || ! is_object( $job ) || empty( $job->original_doc_id ) ) {
			return $jobFields;
		}

		$postType = Obj::prop( 'original_post_type', $job );
		if ( Str::startsWith( 'package', $postType ) ) {
			return $jobFields;
		}

		$postId   = Obj::prop( 'original_doc_id', $job );
		$metaKeys = get_field_objects( $postId, false );
		if ( ! $metaKeys ) {
			return $jobFields;
		}

		$orderedFields = $this->getOrderedFields( $postId, $metaKeys );
		$orderMap      = array_flip( $orderedFields );

		$acfFields    = [];
		$acfPositions = [];

		foreach ( $jobFields as $index => $field ) {
			$key = $this->getKey( $field );
			if ( isset( $orderMap[ $key ] ) ) {
				$acfFields[]    = [
					'field'          => $field,
					'original_index' => $index,
					'order_rank'     => $orderMap[ $key ],
				];
				$acfPositions[] = $index;
			}
		}

		if ( count( $acfFields ) < 2 ) {
			return $jobFields;
		}

		usort( $acfFields, function ( $a, $b ) {
			if ( $a['order_rank'] === $b['order_rank'] ) {
				return $a['original_index'] <=> $b['original_index'];
			}
			return $a['order_rank'] <=> $b['order_rank'];
		} );

		$result      = $jobFields;
		$acfIterator = 0;
		foreach ( $acfPositions as $position ) {
			$result[ $position ] = $acfFields[ $acfIterator ]['field'];
			$acfIterator++;
		}

		return array_values( $result );
	}

	private function getKey( $field ) {
		$element = Obj::path( [ 'attributes', 'id' ], $field );

		return Str::pregReplace( [ '/^field-/', '/-0$/' ], '', $element );
	}

	private function getOrderedFields( $postId, $metaKeys ) {
		$orderedFields = [];

		$iterate = function( $key, $value, $prefix = '' ) use ( &$orderedFields, &$iterate, $postId ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $subKey => $subValue ) {
					$newPrefix = $prefix;
					if ( is_numeric( $subKey ) ) {
						$newPrefix .= '_' . $subKey;
					} elseif ( Fields::LAYOUT_KEY !== $subKey ) {
						$field_object = get_field_object( $subKey, $postId, false, false );
						if ( $field_object ) {
								$newPrefix .= '_' . $field_object['name'];
						}
					}

					$iterate( $subKey, $subValue, $newPrefix );
				}
			} elseif ( Fields::LAYOUT_KEY !== $key ) {
				$orderedFields[] = $prefix;
			}
		};

		foreach ( $metaKeys as $metaKey => $metaValue ) {
			$iterate( $metaKey, $metaValue['value'], $metaValue['name'] );
		}

		return $orderedFields;
	}

}
