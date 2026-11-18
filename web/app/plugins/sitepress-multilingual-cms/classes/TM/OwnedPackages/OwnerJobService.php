<?php

namespace WPML\TM\OwnedPackages;

use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\PB\TranslationJob\Groups;
use WPML\PB\TranslationJob\LabelResolver;

class OwnerJobService {
	const PACKAGE_TYPE_EXTERNAL = 'external';
	const TRANSLATION_COMPLETE  = 10;

	const FIELD_STYLE_AREA   = 'AREA';
	const FIELD_STYLE_VISUAL = 'VISUAL';
	const FIELD_STYLE_LINK   = 'LINK';

	const TOP_LEVEL_GROUP_ID = \WPML_TM_Xliff_Writer::DEFAULT_GROUP_ID;

	private $sitepress;

	private $wpmlPbIntegration;

	private $jobs_with_lost_strings = [];

	private $attachments = [];

	private $createFieldWrapper;

	private $labelResolver;

	private $packageKinds = [];

	private $previousTranslations;

	public function __construct( ?\SitePress $sitepress = null, ?\WPML_PB_Integration $wpmlPbIntegration = null, ?callable $createFieldWrapper = null, ?LabelResolver $labelResolver = null ) {
		$this->sitepress          = $sitepress;
		$this->wpmlPbIntegration  = $wpmlPbIntegration;
		$this->createFieldWrapper = $createFieldWrapper;
		$this->labelResolver      = $labelResolver;
	}

	public function translation_job_data_filter( array $translation_package, $post, $isOriginal = false ) {
		if ( self::PACKAGE_TYPE_EXTERNAL !== $translation_package['type'] && isset( $post->ID ) ) {
			$post_element   = $this->createPostElement( $post->ID );
			$source_post_id = $post->ID;
			$job_lang_from  = $post_element->get_language_code();

			if ( ! $post_element->is_root_source() && $isOriginal && \WPML_PB_Last_Translation_Edit_Mode::is_native_editor( $post->ID ) ) {
				$this->getWpmlPbIntegration()->register_all_strings_for_translation( $post, true );
				$source_post_element = $post_element->get_translation( $job_lang_from );
			} else {
				$source_post_element = $post_element->get_source_element();
			}

			if ( $source_post_element ) {
				$source_post_id = $source_post_element->get_id();
			}

			$job_source_is_not_post_source = $post->ID !== $source_post_id;
			$string_packages = apply_filters( 'wpml_st_get_owned_string_packages', null, 'post', $source_post_id );
			if ( null === $string_packages ) {
				$string_packages = apply_filters( 'wpml_st_get_post_string_packages', false, $source_post_id );
			}

			$translation_package['contents']['body']['translate'] = apply_filters( 'wpml_pb_should_body_be_translated', $translation_package['contents']['body']['translate'], $post );

			if ( $string_packages ) {
				foreach ( $string_packages as $package_id => $string_package ) {
					$strings             = $string_package->get_package_strings();
					$string_translations = [];

					if ( $job_source_is_not_post_source ) {
						$string_translations = $string_package->get_translated_strings( [] );
					}

					foreach ( $strings as $string ) {
						$string_value = $string_translations[ $string->name ][ $job_lang_from ]['value'] ?? $string->value;
						$field_name   = \WPML_TM_Page_Builders_Field_Wrapper::generate_field_slug( $package_id, $string->id );

						$translation_package['contents'][ $field_name ] = [
							'translate' => 1,
							'data'      => base64_encode( $string_value ),
							'wrap_tag'  => \WPML_TM_Page_Builders_Field_Wrapper::get_wrap_tag( $string ),
							'format'    => 'base64',
						];

						$translation_package['contents']['body']['translate'] = 0;
					}
				}
			}

			$translation_package = $this->add_inline_term_owned_fields( $translation_package, $job_lang_from, $job_source_is_not_post_source );
		}

		return $translation_package;
	}

	private function add_inline_term_owned_fields( array $translation_package, $job_lang_from, $job_source_is_not_post_source ) {
		foreach ( array_keys( $translation_package['contents'] ) as $field_name ) {
			if ( ! preg_match( '/^t_(\d+)$/', (string) $field_name, $match ) ) {
				continue;
			}

			$string_packages = apply_filters( 'wpml_st_get_owned_string_packages', null, 'term', (int) $match[1] );
			if ( ! is_array( $string_packages ) || ! $string_packages ) {
				continue;
			}

			foreach ( $string_packages as $package_id => $string_package ) {
				$string_translations = $job_source_is_not_post_source ? $string_package->get_translated_strings( [] ) : [];
				foreach ( $string_package->get_package_strings() as $string ) {
					$string_value = $string_translations[ $string->name ][ $job_lang_from ]['value'] ?? $string->value;
					$owned_field  = \WPML_TM_Page_Builders_Field_Wrapper::generate_field_slug( $package_id, $string->id );

					$translation_package['contents'][ $owned_field ] = [
						'translate' => 1,
						'data'      => base64_encode( $string_value ),
						'wrap_tag'  => \WPML_TM_Page_Builders_Field_Wrapper::get_wrap_tag( $string ),
						'format'    => 'base64',
					];
				}
			}
		}

		return $translation_package;
	}

	public static function drop_owned_fields_covered_in_language( array $contents, array $covered ) {
		foreach ( $covered as $term_id ) {
			$string_packages = apply_filters( 'wpml_st_get_owned_string_packages', null, 'term', (int) $term_id );
			foreach ( (array) $string_packages as $package_id => $string_package ) {
				foreach ( $string_package->get_package_strings() as $string ) {
					unset( $contents[ \WPML_TM_Page_Builders_Field_Wrapper::generate_field_slug( $package_id, $string->id ) ] );
				}
			}
		}

		return $contents;
	}

	public function adjust_translation_fields_filter( array $fields ) {
		foreach ( $fields as &$field ) {
			$widget_block_label_data = $this->get_block_widget_title_and_group( $field['title_fallback'] ?? '' );

			if ( ! empty( $widget_block_label_data['title'] ) ) {
				$field['title'] = $widget_block_label_data['title'];
				$field['group'] = $widget_block_label_data['group'];
				continue;
			}

			$wrapper = $this->create_field_wrapper( $field['field_type'] );
			$type    = $wrapper->get_string_type();
			$title   = $wrapper->get_string_title();

			if ( $title ) {
				$field['title'] = $title;
			}

			$string_package_id = $wrapper->get_package_id();

			if ( $string_package_id && isset( $field['title'] ) && Groups::isGroupLabel( $field['title'] ) ) {
				$label = $this->get_label_resolver()->resolve(
					$field['title'],
					function () use ( $wrapper, $string_package_id ) {
						return $this->get_package_kind( $wrapper, $string_package_id );
					}
				);

				$field['title'] = $label['title'];
				$field['group'] = [ self::TOP_LEVEL_GROUP_ID => \WPML_TM_Xliff_Writer::getDefaultGroupLabel() ];
				foreach ( $label['groups'] as $group => $groupLabel ) {
					$field['group'][ $group ] = $groupLabel;
				}

				$groupIds = array_keys( $label['groups'] );
				$field    = $this->set_image_url( $field, (string) end( $groupIds ) );
			}

			$field = $this->group_media_texts( $field );

			if ( isset( $field['field_wrap_tag'] ) && $field['field_wrap_tag'] ) {
				$field['title'] = isset( $field['title'] ) ? $field['title'] : '';
				$field['title'] .= ' (' . $field['field_wrap_tag'] . ')';
			}

			if ( false !== $type ) {
				switch ( $type ) {
					case self::FIELD_STYLE_AREA:
						$field['field_style'] = '1';
						break;
					case self::FIELD_STYLE_VISUAL:
						$field['field_style'] = '2';
						break;
					default:
						$field['field_style'] = '0';
						break;
				}
			}
		}

		return $fields;
	}

	private function get_block_widget_title_and_group( $title ) {
		if ( Groups::isGroupLabel( $title ) && $this->containsBlockElement( $title ) ) {
			list( $groups, $raw_title ) = Groups::parseGroupLabel( $title );
			$resolver    = $this->get_label_resolver();
			$clean_title = $resolver->toHumanTitle( $raw_title, $groups );
			$group_data  = [ self::TOP_LEVEL_GROUP_ID => \WPML_TM_Xliff_Writer::getDefaultGroupLabel() ];
			foreach ( $groups as $group ) {
				$group_data[ $group ] = $resolver->toHumanGroup( $group );
			}

			return [ 'title' => $clean_title, 'group' => $group_data ];
		}

		return false;
	}

	private function containsBlockElement( $title ) {
		return (bool) preg_match( '/block-\d+/', $title );
	}

	private function set_image_url( $field, $innerMostGroup ) {
		$patterns = array_unique( apply_filters( 'wpml_pb_image_module_patterns', [] ) );
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $innerMostGroup, $matches ) ) {
				$url = wp_get_attachment_url( (int) $matches[1] );
				if ( $url ) {
					$field['image'] = $url;
					$this->attachments[ $matches[1] ] = $field;
				}
				break;
			}
		}

		return $field;
	}

	public function group_media_texts( $field ) {
		$fieldType = Obj::propOr( '', 'field_type', $field );
		$matches   = Str::match( '/^media_(\d+)_\w+$/', $fieldType );
		if ( isset( $matches[1] ) ) {
			$attachmentId = $matches[1];
			if ( isset( $this->attachments[ $attachmentId ]['image'] ) ) {
				$field['image'] = $this->attachments[ $attachmentId ]['image'];
				$field['group'] = $this->attachments[ $attachmentId ]['group'];
			} else {
				$url = wp_get_attachment_url( $attachmentId );
				if ( $url ) {
					$field['image'] = $url;
					$field['group'] = [
						self::TOP_LEVEL_GROUP_ID => \WPML_TM_Xliff_Writer::getDefaultGroupLabel(),
						/* translators: Heading of the group that holds the texts of the media (title, caption, description, alt text) in a translation job, in the translation editor. */
						'media_' . $attachmentId => __( 'Media', 'sitepress' ),
					];
				}
			}
		}

		return $field;
	}

	public function xliffUnitPlaceholdersFilter( array $patterns, $element ) {
		$wrapper = $this->create_field_wrapper( $element->field_type );
		$name    = $wrapper->getStringName();

		if ( ! $name ) {
			return $patterns;
		}

		$package = $wrapper->get_package();
		$owner   = apply_filters( 'wpml_string_package_owner', null, $package );
		$placeholderPatterns = apply_filters( 'wpml_pb_string_placeholder_patterns', [], $name, (int) Obj::prop( 'post_id', $package ), $owner );

		if ( ! $placeholderPatterns ) {
			return $patterns;
		}

		foreach ( $placeholderPatterns as $namePattern => $placeholders ) {
			if ( preg_match( $namePattern, $name ) ) {
				$patterns = array_merge( $patterns, (array) $placeholders );
			}
		}

		return $patterns;
	}

	public function adjust_translation_job_filter( $elements ) {
		return Groups::flattenHierarchy( $elements );
	}

	public function job_layout_filter( array $layout ) {
		$string_groups = [];
		foreach ( $layout as $k => $field ) {
			$wrapper = $this->create_field_wrapper( $field );
			if ( $wrapper->is_valid() ) {
				$string_groups[ $wrapper->get_package_id() ][] = $field;
				unset( $layout[ $k ] );
			}
		}

		foreach ( $string_groups as $string_package_id => $fields ) {
			$string_package = apply_filters( 'wpml_st_get_string_package', null, $string_package_id );
			$layout[] = [
				'field_type'    => 'tm-section',
				'title'         => isset( $string_package->title ) ? $string_package->title : '',
				'fields'        => $fields,
				'empty'         => false,
				'empty_message' => '',
				'sub_title'     => '',
			];
		}

		return array_values( $layout );
	}

	public function populate_previous_translation( array $previousTranslations, array $package, $language ) {
		return $this->getPreviousTranslations()->markCompleteStringTranslationsFinished( $previousTranslations, $package, (string) $language );
	}

	private function getPreviousTranslations() {
		if ( null === $this->previousTranslations ) {
			$this->previousTranslations = new PreviousTranslations();
		}

		return $this->previousTranslations;
	}

	protected function get_label_resolver() {
		if ( ! $this->labelResolver ) {
			$this->labelResolver = new LabelResolver( null, null, new \WPML\PB\Gutenberg\ConfigLabels() );
		}

		return $this->labelResolver;
	}

	private function get_package_kind( \WPML_TM_Page_Builders_Field_Wrapper $wrapper, $packageId ) {
		if ( ! array_key_exists( $packageId, $this->packageKinds ) ) {
			$package = $wrapper->get_package();
			$this->packageKinds[ $packageId ] = is_object( $package ) && isset( $package->kind_slug ) ? (string) $package->kind_slug : null;
		}

		return $this->packageKinds[ $packageId ];
	}

	public function pro_translation_completed_action( $new_element_id, array $fields, \stdClass $job ) {
		if ( 'post' !== $job->element_type_prefix ) {
			return;
		}

		$live_strings = [];
		$used_ids     = [];
		$deleted      = [];

		foreach ( $fields as $field_id => $field ) {
			$field_slug = isset( $field['field_type'] ) ? $field['field_type'] : $field_id;
			$wrapper    = $this->create_field_wrapper( $field_slug );
			$string_id  = $wrapper->get_string_id();

			if ( ! $string_id ) {
				continue;
			}

			$package_id = (string) $wrapper->get_package_id();
			if ( ! array_key_exists( $package_id, $live_strings ) ) {
				$live_strings[ $package_id ] = $this->get_live_package_strings( $wrapper );
			}

			if ( isset( $live_strings[ $package_id ][ (int) $string_id ] ) ) {
				$used_ids[ (int) $string_id ] = true;
			} else {
				$deleted[ $field_slug ] = $package_id;
			}
		}

		$string_ids = $this->map_deleted_strings( $deleted, $live_strings, $used_ids, $job );

		foreach ( $fields as $field_id => $field ) {
			$field_slug = isset( $field['field_type'] ) ? $field['field_type'] : $field_id;
			$string_id  = array_key_exists( $field_slug, $string_ids )
				? $string_ids[ $field_slug ]
				: $this->create_field_wrapper( $field_slug )->get_string_id();

			if ( $string_id ) {
				do_action(
					'wpml_add_string_translation',
					$string_id,
					$job->language_code,
					$field['data'],
					self::TRANSLATION_COMPLETE,
					$job->translator_id,
					$job->translation_service
				);
			}
		}

		do_action( 'wpml_pb_finished_adding_string_translations', $new_element_id, $job->original_doc_id, $fields );
	}

	private function get_live_package_strings( \WPML_TM_Page_Builders_Field_Wrapper $wrapper ) {
		$package = $wrapper->get_package();

		if ( ! $package instanceof \WPML_Package ) {
			return [];
		}

		$strings = $package->get_package_strings( true );
		if ( false === $strings ) {
			return [];
		}

		$values = [];
		foreach ( $strings as $string ) {
			$values[ (int) $string->id ] = (string) $string->value;
		}

		return $values;
	}

	private function map_deleted_strings( array $deleted, array $live_strings, array $used_ids, \stdClass $job ) {
		if ( ! $deleted ) {
			return [];
		}

		$source_texts = $this->get_job_source_texts( $job );
		$string_ids   = [];

		foreach ( $deleted as $field_slug => $package_id ) {
			$matches = isset( $source_texts[ $field_slug ] )
				? array_keys( $live_strings[ $package_id ], $source_texts[ $field_slug ], true )
				: [];

			if ( ! $matches ) {
				$string_ids[ $field_slug ] = $this->lost_string( $field_slug, $package_id, $job );
				continue;
			}

			$free_matches = array_values( array_diff( $matches, array_keys( $used_ids ) ) );

			if ( ! $free_matches ) {
				$string_ids[ $field_slug ] = $this->lost_string( $field_slug, $package_id, $job );
				continue;
			}

			$string_id = $free_matches[0];

			$used_ids[ $string_id ]    = true;
			$string_ids[ $field_slug ] = $string_id;
		}

		if ( in_array( false, $string_ids, true ) && isset( $job->job_id ) ) {
			$this->jobs_with_lost_strings[ (int) $job->job_id ] = true;
		}

		return $string_ids;
	}

	private function lost_string( $field_slug, $package_id, \stdClass $job ) {
		\WPML\TM\Jobs\JobLog::add(
			'owned_string_delivery_unrecoverable',
			[
				'job_id'     => isset( $job->job_id ) ? (int) $job->job_id : 0,
				'field'      => $field_slug,
				'package_id' => $package_id,
			]
		);
		return false;
	}

	private function get_job_source_texts( \stdClass $job ) {
		$texts = [];

		foreach ( isset( $job->elements ) ? (array) $job->elements : [] as $element ) {
			if ( isset( $element->field_type, $element->field_data ) ) {
				$texts[ $element->field_type ] = 'base64' === ( $element->field_format ?? '' )
					? (string) base64_decode( $element->field_data )
					: (string) $element->field_data;
			}
		}

		return $texts;
	}

	public function applied_job_needs_update_filter( $needs_update, $job ) {
		if ( is_object( $job ) && isset( $job->job_id, $this->jobs_with_lost_strings[ (int) $job->job_id ] ) ) {
			unset( $this->jobs_with_lost_strings[ (int) $job->job_id ] );

			return 1;
		}

		return (int) $needs_update;
	}

	private function create_field_wrapper( $field_slug ) {
		if ( $this->createFieldWrapper ) {
			return call_user_func( $this->createFieldWrapper, $field_slug );
		}

		return new \WPML_TM_Page_Builders_Field_Wrapper( $field_slug );
	}

	public static function ownedStrings( $kind, $ownerId, $includeEmpty = false ) {
		$packages = apply_filters( 'wpml_st_get_owned_string_packages', null, $kind, (int) $ownerId );
		if ( null === $packages ) {
			return null;
		}
		if ( ! is_array( $packages ) ) {
			return [];
		}

		$owned = [];
		foreach ( $packages as $package_id => $package ) {
			foreach ( $package->get_package_strings() as $string ) {
				if ( ! $includeEmpty && '' === trim( (string) $string->value ) ) {
					continue;
				}
				$owned[ (int) $package_id . '-' . (int) $string->id ] = [ $package, $string ];
			}
		}
		ksort( $owned, SORT_NATURAL );

		return array_values( $owned );
	}

	public static function ownedStringsOfTerms( array $termTaxonomyIds, $includeEmpty = false ) {
		global $wpdb;

		if ( ! apply_filters( 'wpml_st_string_package_owners_supported', false ) ) {
			return null;
		}
		$ids = array_values( array_filter( array_unique( array_map( 'intval', $termTaxonomyIds ) ) ) );
		if ( ! $ids ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}icl_string_packages WHERE owner_kind = 'term' AND owner_element_id IN ( " . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ' )',
				$ids
			)
		);
		if ( ! empty( $wpdb->last_error ) ) {
			return null;
		}
		$owned    = array_fill_keys( $ids, [] );
		$packages = [];
		foreach ( (array) $rows as $row ) {
			$packages[ (int) $row->ID ] = [ (int) $row->owner_element_id, self::packageFromRow( $row ) ];
		}
		if ( ! $packages ) {
			return $owned;
		}

		$strings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, value, wrap_tag, type, title, string_package_id FROM {$wpdb->prefix}icl_strings WHERE string_package_id IN ( " . implode( ',', array_fill( 0, count( $packages ), '%d' ) ) . ' )',
				array_keys( $packages )
			)
		);
		if ( ! empty( $wpdb->last_error ) ) {
			return null;
		}
		$byTerm = [];
		foreach ( (array) $strings as $string ) {
			$packageId = (int) $string->string_package_id;
			unset( $string->string_package_id );
			if ( ! $includeEmpty && '' === trim( (string) $string->value ) ) {
				continue;
			}
			list( $termTaxonomyId, $package )                                 = $packages[ $packageId ];
			$byTerm[ $termTaxonomyId ][ $packageId . '-' . (int) $string->id ] = [ $package, $string ];
		}
		foreach ( $byTerm as $termTaxonomyId => $items ) {
			ksort( $items, SORT_NATURAL );
			$owned[ $termTaxonomyId ] = array_values( $items );
		}

		return $owned;
	}

	private static function packageFromRow( $row ) {
		if ( ! class_exists( \WPML_Package::class ) ) {
			return $row;
		}

		return method_exists( \WPML_Package::class, 'from_row' ) ? \WPML_Package::from_row( $row ) : new \WPML_Package( $row );
	}

	protected function createPostElement( $postId ) {
		return new \WPML_Post_Element( $postId, $this->sitepress );
	}

	private function getWpmlPbIntegration() {
		if ( null === $this->wpmlPbIntegration ) {
			$this->wpmlPbIntegration = \WPML\Container\make( \WPML_PB_Integration::class );
		}

		return $this->wpmlPbIntegration;
	}
}
