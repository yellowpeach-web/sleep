<?php


namespace WPML\Upgrade\Commands;

class AddAteReceiveTokenColumnToIclTranslateJob extends \WPML_Upgrade_Add_Column_To_Table {
	protected function get_table() {
		return 'icl_translate_job';
	}

	protected function get_column() {
		return 'ate_receive_token';
	}

	protected function get_column_definition() {
		return 'VARCHAR(64) NULL';
	}

	public function run_admin() {
		return (bool) $this->afterTheColumn( parent::run_admin() );
	}

	public function run_ajax() {
		return $this->afterTheColumn( parent::run_ajax() );
	}

	public function run_frontend() {
		return $this->afterTheColumn( parent::run_frontend() );
	}

	private function afterTheColumn( $result ) {
		if ( true === $result ) {
			\WPML\TM\ATE\Handshake\CronTick::reconcile();
		}

		return $result;
	}
}
