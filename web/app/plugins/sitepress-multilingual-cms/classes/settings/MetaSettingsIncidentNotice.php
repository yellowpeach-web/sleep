<?php

namespace WPML\TM\Settings;

use WPML\Core\Component\CustomFieldPreferences\Domain\StoreIncidents;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\ContainerFreeServices;

class MetaSettingsIncidentNotice {

	const NOTICE_ID    = 'wpml-meta-settings-incidents';
	const NOTICE_GROUP = 'wpml-meta-settings';

	public static function addHooks() {
		add_filter( 'icl_get_extra_debug_info', array( self::class, 'addDebugInformation' ) );

		if ( is_admin() && ! wp_doing_ajax() ) {
			add_action( 'admin_init', array( self::class, 'publish' ), 20 );
		}
	}

	public static function publish() {
		if ( ! function_exists( 'wpml_get_admin_notices' ) || ! class_exists( '\WPML_Notice' ) ) {
			return;
		}

		$messages = self::messages();

		if ( $messages && self::somethingWasLost() && self::lossesResolvedByAStoreThatHoldsTheBlob() ) {
			$messages = self::messages();
		}

		if ( $messages && self::healFailedRecorded() && self::healFailuresResolvedWithNothingLeftToHeal() ) {
			$messages = self::messages();
		}

		$notices = wpml_get_admin_notices();

		if ( ! $messages ) {
			if ( $notices->get_notice( self::NOTICE_ID, self::NOTICE_GROUP ) ) {
				$notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
			}
			return;
		}

		$lost = self::somethingWasLost();

		$notice = new \WPML_Notice( self::NOTICE_ID, self::text( $messages, $lost ), self::NOTICE_GROUP );
		$notice->set_css_class_types( array( $lost ? 'error' : 'warning' ) );
		$notice->set_dismissible( true );
		$notice->set_dismissible_for_different_text( false );
		$notice->add_capability_check( array( 'manage_options' ) );
		$notices->add_notice( $notice );
	}

	public static function text( array $messages, $lost = false ) {
		$heading = $lost
			? esc_html__( 'WPML could not save your custom field translation preferences.', 'sitepress' )
			: esc_html__( 'WPML had to compensate for a problem with your custom field translation preferences.', 'sitepress' );

		$text = '<p><strong>' . $heading . '</strong></p>'
			. '<ul style="list-style-type: disc; margin-left: 2em; max-height: none; overflow: visible; overflow-wrap: anywhere;">';

		foreach ( $messages as $message ) {
			$text .= '<li>' . wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ) . '</li>';
		}

		return $text . '</ul>';
	}

	public static function plainText( array $messages ) {
		return wp_strip_all_tags( implode( ' | ', $messages ) );
	}

	public static function addDebugInformation( $info ) {
		$messages = self::messages();
		if ( $messages && is_array( $info ) ) {
			$info['custom field preference store'] = self::plainText( $messages );
		}

		return $info;
	}

	public static function messages() {
		$messages = array();

		foreach ( ContainerFreeServices::incidents()->all() as $entry ) {
			$message = self::message(
				isset( $entry['code'] ) ? (string) $entry['code'] : '',
				isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array()
			);
			if ( '' !== $message ) {
				$messages[] = $message;
			}
		}

		return $messages;
	}

	private static function lossesResolvedByAStoreThatHoldsTheBlob() {
		if (
			class_exists( '\WPML_Settings_Failsafe_Loader' )
			&& \WPML_Settings_Failsafe_Loader::isUnrecoverable()
		) {
			return false;
		}

		return ContainerFreeServices::migrate()->resolveLossesIfStoreHoldsTheBlob();
	}

	private static function healFailuresResolvedWithNothingLeftToHeal() {
		if (
			class_exists( '\WPML_Settings_Failsafe_Loader' )
			&& \WPML_Settings_Failsafe_Loader::isUnrecoverable()
		) {
			return false;
		}

		return ContainerFreeServices::migrate()->resolveHealFailuresIfNothingLeftToHeal();
	}

	public static function healFailedRecorded() {
		foreach ( ContainerFreeServices::incidents()->all() as $entry ) {
			if ( isset( $entry['code'] ) && StoreIncidents::BLOB_HEAL_FAILED === (string) $entry['code'] ) {
				return true;
			}
		}

		return false;
	}

	public static function somethingWasLost() {
		$lossCodes = array( StoreIncidents::UPGRADE_PENDING, StoreIncidents::WRITE_FAILED );

		foreach ( ContainerFreeServices::incidents()->all() as $entry ) {
			if ( isset( $entry['code'] ) && in_array( (string) $entry['code'], $lossCodes, true ) ) {
				return true;
			}
		}

		return false;
	}

	public static function message( $code, array $context ) {
		switch ( $code ) {
			case StoreIncidents::DELETION_REFUSED:
				return sprintf(
					/* translators: Notice shown when WPML turned down a save that would have wiped out the settings of the fields. %1$d: how many settings the save would have removed, %2$d: how many are stored, %3$s: a link whose text is the path to the settings screen. */
					__( 'WPML refused a save that would have removed %1$d of your %2$d custom field translation preferences at once. Nothing was removed. Something on this site is saving WPML settings with an incomplete list of custom fields. Deactivate recently added plugins one at a time to find it. Then check your preferences on %3$s.', 'sitepress' ),
					isset( $context['deletions'] ) ? (int) $context['deletions'] : 0,
					isset( $context['stored'] ) ? (int) $context['stored'] : 0,
					self::customFieldsSettingsLink()
				);

			case StoreIncidents::BLOB_HEALED:
				$restored = isset( $context['count'] ) ? (int) $context['count'] : 0;

				return sprintf(
					/* translators: %d: number of custom field translation preferences restored. */
					_n(
						'Something saved WPML settings without your custom field translation preferences. WPML put %d preference back, so nothing was lost. If this keeps happening, deactivate recently added plugins one at a time to find what saves WPML settings incompletely.',
						'Something saved WPML settings without your custom field translation preferences. WPML put all %d of them back, so nothing was lost. If this keeps happening, deactivate recently added plugins one at a time to find what saves WPML settings incompletely.',
						$restored,
						'sitepress'
					),
					$restored
				);

			case StoreIncidents::BLOB_HEAL_FAILED:
				return sprintf(
					/* translators: %s: a link reading "WPML debug information". */
					__( 'Something saved WPML settings without your custom field translation preferences. WPML could not put them back, so it paused moving them to their new storage. Reload this page to let WPML try again. If this message stays, send your %s to WPML support.', 'sitepress' ),
					self::debugInformationLink()
				);

			case StoreIncidents::MODE_QUARANTINED:
				$ignored = isset( $context['count'] ) ? (int) $context['count'] : 0;

				return sprintf(
					/* translators: %1$d: how many stored preferences WPML cannot read, %2$s: a link whose text is the path to the settings screen. */
					_n(
						'WPML is ignoring %1$d of your custom field translation preferences: it is stored with a setting WPML does not recognize. That field is translated the default way until you set it again, and nothing in your database was changed. Set it again on %2$s.',
						'WPML is ignoring %1$d of your custom field translation preferences: they are stored with settings WPML does not recognize. Those fields are translated the default way until you set them again, and nothing in your database was changed. Set them again on %2$s.',
						$ignored,
						'sitepress'
					),
					$ignored,
					self::customFieldsSettingsLink()
				);

			case StoreIncidents::UPGRADE_PENDING:
				return sprintf(
					/* translators: %s: a link reading "WPML debug information". */
					__( 'WPML could not save your custom field translation preferences. Your database is missing an update WPML needs. Reload any WPML admin page to let WPML finish the update, then save your preferences again. If this message stays, send your %s to WPML support.', 'sitepress' ),
					self::debugInformationLink()
				);

			case StoreIncidents::WRITE_FAILED:
				return sprintf(
					/* translators: %s: a link reading "WPML debug information". */
					__( 'WPML could not save your custom field translation preferences. Your database refused the change. Reload this page and save your preferences again. If this message stays, send your %s to WPML support.', 'sitepress' ),
					self::debugInformationLink()
				);

			default:
				return '';
		}
	}

	private static function customFieldsSettingsLink() {
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=tm/menu/settings&section=custom-fields#ml-content-setup-sec-cf' ) ),
			esc_html__( 'WPML > Settings > Custom Fields Translation', 'sitepress' )
		);
	}

	private static function debugInformationLink() {
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . WPML_PLUGIN_FOLDER . '/menu/support.php&tool=system-check' ) ),
			esc_html__( 'WPML debug information', 'sitepress' )
		);
	}
}
