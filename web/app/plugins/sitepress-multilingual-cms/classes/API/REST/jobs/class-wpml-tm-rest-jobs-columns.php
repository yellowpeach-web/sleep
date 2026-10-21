<?php

class WPML_TM_Rest_Jobs_Columns {
	public static function get_columns() {
		return array(
			/* translators: Column heading in the table of translation jobs: the number that stands for the piece of content the job belongs to. */
			'id'         => __( 'Reference ID', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs: the number that stands for the job itself. */
			'job_id'     => __( 'Job ID', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs, and the label of the title field in the translation editor: the title of the piece of content. */
			'title'      => __( 'Title', 'sitepress' ),
			/* translators: Name of the Languages screen: in the WPML menu, as the title of that screen, and as a column heading listing the languages of a piece of content. Plural noun. */
			'languages'  => __( 'Languages', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs: the name given to the group of jobs sent together. */
			'batch_name' => __( 'Batch name', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs; the name of the translator or of the machine follows in the cell, so it ends without a full stop. */
			'translator' => __( 'Translated by', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs: the date the job was sent to be translated. */
			'sent_date'  => __( 'Sent on', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs: the date by which the translation is due. */
			'deadline'   => __( 'Deadline', 'sitepress' ),
			/* translators: Column heading in tables of the WPML admin, above the cells that say how far something has got. Noun, singular. */
			'status'     => __( 'Status', 'sitepress' ),
		);
	}

	public static function get_sortable() {
		return array(
			/* translators: Column heading in the table of translation jobs: the number that stands for the piece of content the job belongs to. */
			'id'            => __( 'Reference ID', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs: the number that stands for the job itself. */
			'job_id'        => __( 'Job ID', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs, and the label of the title field in the translation editor: the title of the piece of content. */
			'title'         => __( 'Title', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs: the name given to the group of jobs sent together. */
			'batch_name'    => __( 'Batch name', 'sitepress' ),
			/* translators: Column heading and field label in the WPML admin, for the language of a piece of content. Noun, singular. */
			'language'      => __( 'Language', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs: the date the job was sent to be translated. */
			'sent_date'     => __( 'Sent on', 'sitepress' ),
			/* translators: Column heading in the table of translation jobs: the date by which the translation is due. */
			'deadline_date' => __( 'Deadline', 'sitepress' ),
			/* translators: Column heading in tables of the WPML admin, above the cells that say how far something has got. Noun, singular. */
			'status'        => __( 'Status', 'sitepress' ),
		);
	}

	public static function is_sortable( $column ) {
		return is_string( $column ) && array_key_exists( $column, self::get_sortable() );
	}
}
