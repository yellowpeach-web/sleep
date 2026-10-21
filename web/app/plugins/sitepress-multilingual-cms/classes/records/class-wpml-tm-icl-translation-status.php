<?php

use WPML\FP\Lst;
use WPML\FP\Maybe;
use WPML\FP\Just;
use WPML\FP\Nothing;
use function WPML\Container\make;
use WPML\Element\API\TranslationsRepository;

class WPML_TM_ICL_Translation_Status {
	public $wpdb;

	private $tm_records;

	private $table          = 'icl_translation_status';
	private $translation_id = 0;
	private $rid            = 0;

	private $status_result;

	public function __construct( wpdb $wpdb, WPML_TM_Records $tm_records, $id, $type = 'translation_id' ) {
		$this->wpdb       = $wpdb;
		$this->tm_records = $tm_records;
		if ( $id > 0 && Lst::includes( $type, [ 'translation_id', 'rid' ] ) ) {
			$this->{$type} = $id;
		} else {
			throw new InvalidArgumentException( 'Unknown column: ' . $type . ' or invalid id: ' . $id );
		}
	}

	public function update( $args ) {
		$this->wpdb->update(
			$this->wpdb->prefix . $this->table,
			$args,
			$this->get_args()
		);

		$this->status_result = null;
		return $this;
	}

	public function delete() {
		$this->wpdb->delete(
			$this->wpdb->prefix . $this->table,
			$this->get_args()
		);
	}

	public function rid() {
		$row = $this->get_row();

		return $row ? (int) $row->rid : 0;
	}

	public function exists() {

		return (bool) $this->rid();
	}

	public function status() {

		if ( $this->status_result === null ) {
			$status = $this->tm_records->get_preloaded_translation_status( $this->translation_id, $this->rid );
			if ( $status ) {
				$this->status_result = (int) $status->status;
			} else {
				if ( $this->translation_id ) {
					$job = TranslationsRepository::getByTranslationId( $this->translation_id );
					if ( $job ) {
						$this->status_result = (int) \WPML\FP\Obj::prop( 'status', $job );

						return $this->status_result;
					}
				}

				if ( $this->translation_id && $this->tm_records->is_translation_status_preloaded( $this->translation_id ) ) {
					$this->status_result = 0;

					return 0;
				}

				$row                 = $this->get_row();
				$this->status_result = $row ? (int) $row->status : 0;
			}
		}
		return (int) $this->status_result;
	}


	public function md5() {
		$row = $this->get_row();

		return $row ? $row->md5 : null;
	}

	public function needs_update() {
		$row = $this->get_row();

		return $row ? (bool) $row->needs_update : false;
	}

	public function translation_id() {
		$row = $this->get_row();

		return $row ? (int) $row->translation_id : 0;
	}

	public function trid() {

		return $this->tm_records->icl_translations_by_translation_id( $this->translation_id() )->trid();
	}

	public function element_id() {

		return $this->tm_records->icl_translations_by_translation_id( $this->translation_id() )->element_id();
	}

	public function translator_id() {
		$row = $this->get_row();

		return $row ? (int) $row->translator_id : 0;
	}

	public function service() {
		$row = $this->get_row();

		return $row ? (int) $row->translation_service : 0;
	}

	private function get_row() {
		$wpdb = $this->wpdb;

		if ( $this->translation_id ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT rid, translation_id, status, md5, needs_update, translator_id, translation_service
					 FROM {$wpdb->prefix}icl_translation_status
					 WHERE translation_id = %d",
					$this->translation_id
				)
			);
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rid, translation_id, status, md5, needs_update, translator_id, translation_service
				 FROM {$wpdb->prefix}icl_translation_status
				 WHERE rid = %d",
				$this->rid
			)
		);
	}

	private function get_args() {

		return $this->translation_id
			? array( 'translation_id' => $this->translation_id )
			: array( 'rid' => $this->rid );
	}

	public static function makeByRid( $id ) {
		return make( self::class, [ ':id' => $id, ':type' => 'rid' ] );
	}
}
