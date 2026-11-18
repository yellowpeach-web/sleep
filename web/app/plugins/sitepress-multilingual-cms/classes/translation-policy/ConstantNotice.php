<?php

namespace WPML\TranslationPolicy;

class ConstantNotice implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	public function add_hooks(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
	}

	public function render(): void {
		$message = self::message();

		if ( '' !== $message && current_user_can( 'manage_options' ) ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	public static function message(): string {
		$state = FeatureSwitch::constantState();

		if ( FeatureSwitch::CONSTANT_INVALID === $state ) {
			return sprintf(
				/* translators: %s: the name of a PHP constant. */
				__( 'WPML: the value of %s is not valid, so WPML treats it as off. Use true or 1 to turn it on, and false or 0 to turn it off.', 'sitepress' ),
				FeatureSwitch::CONSTANT
			);
		}

		if ( FeatureSwitch::CONSTANT_OFF === $state && FeatureSwitch::isOn() ) {
			return sprintf(
				/* translators: 1: the name of a PHP constant, 2: a WPML version number. */
				__( 'WPML: %1$s is set to off, but this site switched to the new translation settings in WPML %2$s. This change cannot be turned off, so the new translation settings stay on.', 'sitepress' ),
				FeatureSwitch::CONSTANT,
				FeatureSwitch::cutoverVersion()
			);
		}

		return '';
	}
}
