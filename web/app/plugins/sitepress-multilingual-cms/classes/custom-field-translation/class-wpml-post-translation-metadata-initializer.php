<?php

class WPML_Post_Translation_Metadata_Initializer {
	private static $suspension_depth = 0;

	private $sitepress;

	private $wpdb;

	private $copy_once_custom_field;

	public function __construct(
		SitePress $sitepress,
		wpdb $wpdb,
		WPML_Copy_Once_Custom_Field $copy_once_custom_field
	) {
		$this->sitepress              = $sitepress;
		$this->wpdb                   = $wpdb;
		$this->copy_once_custom_field = $copy_once_custom_field;
	}

	public static function suspend() {
		++self::$suspension_depth;
	}

	public static function resume() {
		self::$suspension_depth = max( 0, self::$suspension_depth - 1 );
	}

	public static function is_suspended() {
		return self::$suspension_depth > 0;
	}

	public function get_source_post_id( $target_post_id, $element_type ) {
		$wpdb = $this->wpdb;

		$target_post_id = (int) $target_post_id;
		$element_type   = (string) $element_type;

		if ( ! $target_post_id || 0 !== strpos( $element_type, 'post_' ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT source_translation.element_id
				 FROM {$wpdb->prefix}icl_translations AS target_translation
				 INNER JOIN {$wpdb->prefix}icl_translations AS source_translation
				 	ON source_translation.trid = target_translation.trid
				 	AND source_translation.element_type = target_translation.element_type
				 	AND source_translation.language_code = target_translation.source_language_code
				 WHERE target_translation.element_id = %d
				 	AND target_translation.element_type = %s
				 LIMIT 1",
				$target_post_id,
				$element_type
			)
		);
	}

	public function initialize_if_source_changed( $target_post_id, $element_type, $previous_source_post_id ) {
		$target_post_id          = (int) $target_post_id;
		$source_post_id          = $this->get_source_post_id( $target_post_id, $element_type );
		$previous_source_post_id = (int) $previous_source_post_id;

		if (
			! $source_post_id
			|| $source_post_id === $previous_source_post_id
			|| $source_post_id === $target_post_id
			|| ! get_post( $source_post_id )
			|| ! get_post( $target_post_id )
		) {
			return false;
		}

		$this->sitepress->copy_custom_fields( $source_post_id, $target_post_id );
		$this->copy_once_custom_field->copy_from( $source_post_id, $target_post_id );

		return true;
	}
}
