<?php

class WPML_Upgrade_Chinese_Flags implements IWPML_Upgrade_Command {

	private $wpdb;

	public function __construct( array $args ) {
		$this->wpdb = $args['wpdb'];
	}

	public function run() {
		$codes = array( 'zh-hans', 'zh-hant' );
		$wpdb  = $this->wpdb;
		$flags = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, lang_code, flag FROM {$wpdb->prefix}icl_flags WHERE lang_code IN (" . implode( ', ', array_fill( 0, count( $codes ), '%s' ) ) . ')',
				$codes
			)
		);

		$written = 0;

		if ( $flags ) {
			foreach ( $flags as $flag ) {
				if ( $this->must_update( $flag ) ) {
					$this->wpdb->update(
						$this->wpdb->prefix . 'icl_flags',
						array(
							'flag' => 'zh.png',
						),
						array( 'id' => $flag->id ),
						array( '%s' ),
						array( '%d' )
					);
					++$written;
				}
			}
		}

		if ( $written ) {
			WPML_Flags::invalidate();
		}

		return true;
	}

	protected function must_update( $flag ) {
		return $flag->flag === $flag->lang_code . '.png';
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return null;
	}
}
