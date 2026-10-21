<?php

namespace WPML\TM\Jobs;

use WPML\TM\API\Job\Map;
use WPML\TM\Editor\OpenLock;

class BaselineJobCreator {

	const TRIGGER_SEND            = 'send';
	const TRIGGER_ORIGINAL_UPDATE = 'original_update';
	const TRIGGER_EDITOR_OPEN     = 'editor_open';

	private $sitepress;

	private $tm_records;

	private $action_helper;

	public function __construct( \SitePress $sitepress, \WPML_TM_Records $tm_records, \WPML_TM_Action_Helper $action_helper ) {
		$this->sitepress     = $sitepress;
		$this->tm_records    = $tm_records;
		$this->action_helper = $action_helper;
	}

	public static function build() {
		global $sitepress;

		return new self( $sitepress, wpml_tm_get_records(), new \WPML_TM_Action_Helper() );
	}

	public function create_missing( $post_id, $trigger ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$element_type = 'post_' . $post->post_type;
		$trid         = $this->sitepress->get_element_trid( $post_id, $element_type );
		if ( ! $trid ) {
			return;
		}

		$translations = (array) $this->sitepress->get_element_translations( $trid, $element_type, false, true );

		$original_id = null;
		foreach ( $translations as $translation ) {
			if ( ! empty( $translation->original ) ) {
				$original_id = $translation->element_id;
				break;
			}
		}

		if ( ! $original_id ) {
			return;
		}

		foreach ( $translations as $translation ) {
			if ( ! empty( $translation->original ) || empty( $translation->translation_id ) ) {
				continue;
			}

			$this->create_for_translation( $original_id, $translation, $trid, $trigger );
		}
	}

	private function create_for_translation( $original_id, $translation, $trid, $trigger ) {
		$status = $this->tm_records->icl_translation_status_by_translation_id( $translation->translation_id );

		if ( $status->exists() ) {
			if ( (int) $status->status() !== ICL_TM_COMPLETE || $status->needs_update() ) {
				return;
			}

			if ( Map::fromRid( $status->rid() ) ) {
				return;
			}
		}

		$lock = ( new OpenLock() )->acquire(
			[
				'trid'          => $trid,
				'language_code' => isset( $translation->language_code ) ? $translation->language_code : '',
			]
		);

		try {
			$this->create_in_section( $original_id, $translation, $trid, $trigger );
		} finally {
			if ( $lock ) {
				$lock->release();
			}
		}
	}

	private function create_in_section( $original_id, $translation, $trid, $trigger ) {
		$row = $this->read_status_row( $translation->translation_id );

		if ( $row ) {
			if ( (int) $row->status !== ICL_TM_COMPLETE || (int) $row->needs_update ) {
				return;
			}

			$rid = (int) $row->rid;
		} else {
			$rid = $this->bootstrap_status_row( $original_id, $translation );
		}

		if ( ! $rid || Map::jobIdFromDB( $rid ) ) {
			return;
		}

		$package = $this->action_helper->create_translation_package( $original_id );
		// translate, nobody to notify. The default would mail translators: with
		$job_id = $this->action_helper->add_translation_job( $rid, 0, $package, [], null, false, false );

		if ( ! $job_id ) {
			return;
		}

		wpml_tm_load_old_jobs_editor()->set( $job_id, \WPML_TM_Editors::WP );

		do_action( 'wpml_save_job_fields_from_post', $job_id );

		JobLog::add( 'baseline_job_created', [
			'trid'     => (int) $trid,
			'language' => isset( $translation->language_code ) ? $translation->language_code : '',
			'rid'      => (int) $rid,
			'job_id'   => (int) $job_id,
			'trigger'  => $trigger,
		] );
	}

	private function read_status_row( $translation_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rid, status, needs_update FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d",
				$translation_id
			)
		);

		return is_object( $row ) ? $row : null;
	}

	private function bootstrap_status_row( $original_id, $translation ) {
		if ( empty( $translation->element_id ) ) {
			return null;
		}

		$translated_post = get_post( $translation->element_id );
		if ( ! $translated_post || 'publish' !== $translated_post->post_status ) {
			return null;
		}

		if ( wpml_get_post_status_helper()->is_duplicate( $translation->element_id ) ) {
			return null;
		}

		list( $rid ) = $this->action_helper->get_tm_instance()->update_translation_status( [
			'translation_id'      => $translation->translation_id,
			'status'              => ICL_TM_COMPLETE,
			'translator_id'       => 0,
			'needs_update'        => 0,
			'md5'                 => $this->action_helper->post_md5( $original_id ),
			'translation_service' => 'local',
		] );

		return $rid ?: null;
	}
}
