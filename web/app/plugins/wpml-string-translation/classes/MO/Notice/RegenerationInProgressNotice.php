<?php

namespace WPML\ST\MO\Notice;

class RegenerationInProgressNotice extends \WPML_Notice {

	const ID    = 'mo-files-regeneration';
	const GROUP = 'mo-files';

	public function __construct() {
		parent::__construct( self::ID, '', self::GROUP );

		$this->set_dismissible( false );
		$this->set_css_classes( 'warning' );
	}

	public function get_text() {
		/* translators: Notice shown while WPML is rebuilding the translation files. "This" is that rebuilding. */
		$text = __( "WPML is updating the .mo files with the translation for strings. This will take a few more moments. During this process, translation for strings is not displaying on the front-end. You can refresh this page in a minute to see if it's done.", 'wpml-string-translation' );

		return apply_filters(
			'wpml_notice_text',
			$text,
			[
				'id'    => $this->get_id(),
				'group' => $this->get_group(),
			]
		);
	}

}
