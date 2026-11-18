<?php

namespace WPML\LanguageSwitcher;

class TemplatesDebugInformation {

	const SECTION = 'language switcher templates';

	const STATUS_USED              = 'used';
	const STATUS_NOT_USED_UPLOADS  = 'not used any more: it is in the uploads folder, move it into the theme';

	private $custom_templates;

	private $templates;

	public function __construct( CustomTemplates $custom_templates, \WPML_LS_Templates $templates ) {
		$this->custom_templates = $custom_templates;
		$this->templates        = $templates;
	}

	public function add( $info ) {
		if ( ! is_array( $info ) ) {
			return $info;
		}

		$info[ self::SECTION ] = $this->get();

		return $info;
	}

	public function get() {
		$custom = [];

		foreach ( $this->custom_templates->get() as $template ) {
			$custom[] = $this->entry( $template, self::STATUS_USED );
		}

		foreach ( $this->custom_templates->getUploads() as $template ) {
			$custom[] = $this->entry( $template, self::STATUS_NOT_USED_UPLOADS );
		}

		return [
			'custom templates'                              => $custom ? $custom : 'none',
			'engine of WPML\'s own templates'               => $this->core_engines(),
			'filter wpml_ls_template_paths is hooked'       => false !== has_filter( 'wpml_ls_template_paths' ),
			'filter wpml_ls_directories_to_scan is hooked'  => false !== has_filter( 'wpml_ls_directories_to_scan' ),
		];
	}

	private function entry( array $template, $status ) {
		return [
			'name'        => $template['name'],
			'slug'        => $template['slug'],
			'engine'      => $template['engine'],
			'folder'      => $this->folder_from_wp_content( $template['folder'] ),
			'in uploads'  => $template['in_uploads'],
			'status'      => $status,
			'used by'     => $template['used_by'] ? $template['used_by'] : 'no saved switcher (a shortcode or theme code may choose it)',
		];
	}

	private function core_engines() {
		$engines    = [];
		$registered = $this->templates->get_templates();

		foreach ( is_array( $registered ) ? $registered : [] as $slug => $template ) {
			if ( $template->is_core() && ! $template->is_missing() ) {
				$engines[ (string) $slug ] = $template->get_engine();
			}
		}

		ksort( $engines );

		return $engines;
	}

	private function folder_from_wp_content( $folder ) {
		$prefix = 'wp-content/';

		return 0 === strpos( $folder, $prefix ) ? substr( $folder, strlen( $prefix ) ) : $folder;
	}
}
