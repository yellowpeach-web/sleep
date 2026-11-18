<?php

namespace WPML\TranslationRoles;

use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\User;
use WPML\TranslationRoles\Service\TranslationManagerCapability;
use function WPML\Container\make;

class SaveManager extends SaveUser {

	const TRANSLATION_MANAGER_INSTRUCTIONS_TEMPLATE = 'notification/translation-manager-instructions.twig';

	public function run( Collection $data ) {
		$refusal = null;

		$grantRole = Fns::tap( function ( $user ) use ( &$refusal ) {
			make( TranslationManagerCapability::class )->grant( $user->ID );
			$refusal = \WPML_TM_AMS_Synchronize_Actions::get_last_error();
		} );

		$result = function ( \WP_User $user ) use ( &$refusal ) {
			return is_wp_error( $refusal )
				? Either::left( self::getAteRefusalMessage( $user, $refusal ) )
				: Either::right( true );
		};

		return self::getUser( $data )
		           ->map( $grantRole )
		           ->map( [ self::class, 'sendInstructions' ] )
		           ->chain( $result );
	}

	private static function getAteRefusalMessage( \WP_User $manager, \WP_Error $refusal ) {
		return sprintf(
			/* translators: Error shown when a user was made a Translation Manager but the Advanced Translation Editor refused the change. %1$s: the user's display name, %2$s: the error message from the Advanced Translation Editor. */
			__( '%1$s is now a Translation Manager in WPML. The Advanced Translation Editor did not accept the change, so it does not list this user as a manager yet. Contact WPML support and include this error: %2$s', 'sitepress' ),
			$manager->display_name,
			$refusal->get_error_message()
		);
	}

	public static function sendInstructions( \WP_User $manager ) {
		$siteName            = get_option( 'blogname' );
		$translationSetupUrl = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php' );
		$adminUser           = User::getCurrent();

		$model = [
			'setup_url'       => esc_url( $translationSetupUrl ),
			'username'        => $manager->display_name,
			/* translators: Line in the email WPML sends to a new translation manager. %s: the name of the site. */
			'intro_message_1' => sprintf( __( 'You are the Translation Manager for %s. This role lets you manage everything related to translation for this site.', 'sitepress' ), $siteName ),
			'intro_message_2' => __( 'Before you can start sending content to translation, you need to complete a short setup.', 'sitepress' ),
			'setup'           => __( 'Set-up the translation', 'sitepress' ),
			/* translators: Line in the email WPML sends to a translation manager, reminding them of their login name. %1$s: the name of the site, %2$s: that login name. */
			'reminder'        => sprintf( __( '* Remember, your login name for %1$s is %2$s. If you need help with your password, use the password reset in the login page.', 'sitepress' ), $siteName, $manager->user_login ),
			'at_your_service' => __( 'At your service', 'sitepress' ),
			'admin_name'      => $adminUser->display_name,
			/* translators: Line in the email WPML sends to a new translation manager, naming the person who set it up. %s: the name of the site. */
			'admin_for_site'  => sprintf( __( 'Administrator for %s', 'sitepress' ), $siteName ),
		];

		$to      = $manager->display_name . ' <' . $manager->user_email . '>';
		$message = make( \WPML_TM_Email_Notification_View::class )->render_model(
			$model,
			self::TRANSLATION_MANAGER_INSTRUCTIONS_TEMPLATE
		);
		/* translators: Subject of the email WPML sends to a new translation manager. %s: the name of the site. */
		$subject = sprintf( __( 'You are now the Translation Manager for %s - action needed', 'sitepress' ), $siteName );

		$headers = array(
			'MIME-Version: 1.0',
			'Content-type: text/html; charset=UTF-8',
			'Reply-To: ' . $adminUser->display_name . ' <' . $adminUser->user_email . '>',
		);

		$forceDisplayName = Fns::always( $adminUser->display_name );

		$sendMail = function () use ( $to, $subject, $message, $headers ) {
			return \WPML_Mail_Sender::send( $to, $subject, $message, $headers, array(), 'translation-manager-instructions' );
		};

		Hooks::callWithFilter( $sendMail, 'wp_mail_from_name', $forceDisplayName );

		return $manager;
	}
}
