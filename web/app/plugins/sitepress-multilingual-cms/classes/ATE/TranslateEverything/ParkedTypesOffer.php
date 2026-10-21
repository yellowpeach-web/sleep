<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\WP\OptionManager;

class ParkedTypesOffer {

	const OPTION_GROUP = 'setup';
	const OPTION_KEY   = 'translate-everything-parked-offer';

	const LABELS_KEY = 'translate-everything-type-labels';

	const DOOR_SETTINGS_HELPER = 'settings_helper';

	const DOOR_INLINE = 'inline';

	const DOOR_SETTINGS_FORM = 'settings_form';


	private static $option_manager = null;

	public static function record( array $modes_by_type, $door ) {
		if ( ! $modes_by_type ) {
			return;
		}

		$now = time();

		self::mutate(
			function ( array $current ) use ( $modes_by_type, $door, $now ) {
				foreach ( $modes_by_type as $type => $mode ) {
					if ( isset( $current[ $type ] ) ) {
						continue;
					}
					$current[ $type ] = array(
						'mode'      => (int) $mode,
						'parked_at' => $now,
						'door'      => (string) $door,
						'dismissed' => false,
					);
				}

				return $current;
			}
		);
	}

	public static function recordTypes( array $types, $door ) {
		if ( ! $types ) {
			return;
		}

		global $sitepress;
		$sync_settings = $sitepress
			? (array) $sitepress->get_setting( 'custom_posts_sync_option', array() )
			: array();

		$modes_by_type = array();
		foreach ( $types as $type ) {
			$modes_by_type[ $type ] = isset( $sync_settings[ $type ] )
				? (int) $sync_settings[ $type ]
				: WPML_CONTENT_TYPE_TRANSLATE;
		}

		self::record( $modes_by_type, $door );
	}

	public static function get() {
		$record = self::option_manager()->get( self::OPTION_GROUP, self::OPTION_KEY, array() );

		return is_array( $record ) ? self::normalize( $record ) : array();
	}

	public static function offered() {
		$record = self::get();
		if ( ! $record ) {
			return array();
		}

		global $sitepress;
		if ( ! $sitepress ) {
			return $record;
		}

		$translatable = array_flip( self::translatableTypes() );

		return array_intersect_key( $record, $translatable );
	}

	public static function translatableTypes() {
		global $sitepress;
		if ( ! $sitepress ) {
			return array();
		}

		$sync_settings     = (array) $sitepress->get_setting( 'custom_posts_sync_option', array() );
		$translation_modes = new \WPML_Translation_Modes();

		$translatable = array();
		foreach ( $sync_settings as $type => $mode ) {
			if ( is_string( $type ) && $translation_modes->is_translatable_mode( $mode ) ) {
				$translatable[] = $type;
			}
		}

		return $translatable;
	}

	public static function forget( array $types ) {
		if ( ! $types ) {
			return;
		}

		self::mutate(
			function ( array $current ) use ( $types ) {
				return array_diff_key( $current, array_fill_keys( $types, true ) );
			}
		);
	}

	public static function rememberLabels( array $labels_by_type ) {
		$record  = self::get();
		$kept    = self::storedLabels();
		$changed = false;
		foreach ( $labels_by_type as $type => $label ) {
			if ( isset( $record[ $type ] ) && is_string( $label ) && '' !== $label && ( $kept[ $type ] ?? null ) !== $label ) {
				$changed = true;
			}
		}
		if ( ! $changed ) {
			return;
		}

		self::option_manager()->mutate(
			self::OPTION_GROUP,
			self::LABELS_KEY,
			function ( $current ) use ( $labels_by_type, $record ) {
				$current = is_array( $current ) ? self::normalizeLabels( $current ) : array();
				foreach ( $labels_by_type as $type => $label ) {
					if ( isset( $record[ $type ] ) && is_string( $label ) && '' !== $label ) {
						$current[ $type ] = $label;
					}
				}

				return $current;
			}
		);
	}

	public static function labels() {
		$kept = self::storedLabels();
		if ( ! $kept ) {
			return array();
		}

		global $sitepress;
		if ( ! $sitepress ) {
			return $kept;
		}

		return array_intersect_key( $kept, array_flip( self::translatableTypes() ) );
	}

	private static function storedLabels() {
		$kept = self::option_manager()->get( self::OPTION_GROUP, self::LABELS_KEY, array() );

		return is_array( $kept ) ? self::normalizeLabels( $kept ) : array();
	}

	private static function normalizeLabels( array $labels ) {
		$normalized = array();
		foreach ( $labels as $type => $label ) {
			if ( is_string( $type ) && is_string( $label ) && '' !== $label ) {
				$normalized[ $type ] = $label;
			}
		}

		return $normalized;
	}

	public static function dismiss( array $types ) {
		if ( ! $types ) {
			return;
		}

		self::mutate(
			function ( array $current ) use ( $types ) {
				foreach ( $types as $type ) {
					if ( isset( $current[ $type ] ) ) {
						$current[ $type ]['dismissed'] = true;
					}
				}

				return $current;
			}
		);
	}

	public static function clear() {
		self::option_manager()->set( self::OPTION_GROUP, self::OPTION_KEY, array() );
	}

	public static function setOptionManager( $option_manager ) {
		self::$option_manager = $option_manager;
	}

	private static function mutate( callable $updater ) {
		self::option_manager()->mutate(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			function ( $current ) use ( $updater ) {
				return $updater( is_array( $current ) ? self::normalize( $current ) : array() );
			}
		);
	}

	private static function normalize( array $record ) {
		$normalized = array();

		foreach ( $record as $type => $entry ) {
			if ( ! is_string( $type ) || ! is_array( $entry ) ) {
				continue;
			}

			$normalized[ $type ] = array(
				'mode'      => isset( $entry['mode'] ) ? (int) $entry['mode'] : WPML_CONTENT_TYPE_TRANSLATE,
				'parked_at' => isset( $entry['parked_at'] ) ? (int) $entry['parked_at'] : 0,
				'door'      => isset( $entry['door'] ) ? (string) $entry['door'] : self::DOOR_SETTINGS_HELPER,
				'dismissed' => ! empty( $entry['dismissed'] ),
			);
		}

		return $normalized;
	}

	private static function option_manager() {
		if ( null === self::$option_manager ) {
			self::$option_manager = new OptionManager();
		}

		return self::$option_manager;
	}
}
