<?php

namespace ACFML\Notice;

use ACFML\FieldGroup\NameCollisions;
use WPML\FP\Obj;

class FieldNameCollisions {

	const NOTICE_SCREEN = 'acf-field-group';
	const NOTICE_GROUP  = 'acfml';
	const NOTICE_ID     = 'field-name-collisions';

	private $nameCollisions;

	public function __construct( NameCollisions $nameCollisions ) {
		$this->nameCollisions = $nameCollisions;
	}

	public function process( $fieldGroup ) {
		if ( ! function_exists( 'wpml_get_admin_notices' ) ) {
			return;
		}

		$notices    = wpml_get_admin_notices();
		$collisions = $this->nameCollisions->find( $fieldGroup );

		if ( ! $collisions ) {
			$notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );

			return;
		}

		$this->createNotice( $notices, $fieldGroup, $collisions );
	}

	private function createNotice( $notices, $fieldGroup, array $collisions ) {
		$notice = $notices->create_notice( self::NOTICE_ID, $this->getText( $fieldGroup, $collisions ), self::NOTICE_GROUP );
		$notice->set_dismissible( true );
		$notice->set_css_class_types( [ 'warning' ] );
		$notice->set_restrict_to_screen_ids( [ self::NOTICE_SCREEN ] );
		$notices->add_notice( $notice );
	}

	private function getText( $fieldGroup, array $collisions ) {
		$groupTitle = (string) Obj::propOr( '', 'title', $fieldGroup );

		$lead = 1 === count( $collisions )
			/* translators: %s is the title of the field group being saved. */
			? esc_html__( 'A field in "%s" has the same name as a field in another field group. WPML stores translation preferences by field name, so two fields that share a name cannot be set differently.', 'acfml' )
			/* translators: %s is the title of the field group being saved. */
			: esc_html__( 'Some fields in "%s" have the same name as fields in other field groups. WPML stores translation preferences by field name, so fields that share a name cannot be set differently.', 'acfml' );

		/* translators: Heading of the admin notice about two fields in different groups sharing a name; "here" is the field group being edited. */
		$text  = '<h2>' . esc_html__( 'What you set here may not be what your visitors get', 'acfml' ) . '</h2>';
		$text .= '<p>' . sprintf( $lead, esc_html( $groupTitle ) ) . '</p>';

		$text .= '<ul>';
		foreach ( $collisions as $collision ) {
			$text .= '<li>' . $this->getCollisionLine( $collision ) . '</li>';
		}
		$text .= '</ul>';

		return $text;
	}

	private function getCollisionLine( $collision ) {
		$line = sprintf(
			/* translators: %1$s is a field name, %2$s is the title of another field group. */
			esc_html__( 'The field %1$s also exists in "%2$s". Rename this field to give it a translation setting of its own.', 'acfml' ),
			'<code>' . esc_html( $collision['field_name'] ) . '</code>',
			esc_html( $collision['group_title'] )
		);

		if ( $collision['group_id'] ) {
			$line .= ' <a href="' . esc_url( admin_url( 'post.php?post=' . $collision['group_id'] . '&action=edit' ) ) . '">'
				/* translators: Link at the end of a line in that notice; it opens the other field group. Verb phrase, imperative. */
				. esc_html__( 'See both fields', 'acfml' )
				. '</a>';
		}

		return $line;
	}
}
