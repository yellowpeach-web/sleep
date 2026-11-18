<?php
namespace WPML\TranslationRoles\Service;

use WPML\LIB\WP\User;
use function WPML\Container\make;

class AdministratorRoleManager {

	const USER_METAKEY_INITIALIZED = '_wpml_initialized_administrator_role';

	public function verifyCurrentUser() {
		$user = User::getCurrent();
		return $this->verifyUser( $user );
	}
	public function verifyUserId( $userId, $skipRoleCheck = false )
	{
		$user = User::get($userId);
		return $this->verifyUser( $user, $skipRoleCheck );
	}

	public function initializeAllAdministrators() {
		SyncBatch::run(
			function () {
				$administrators = get_users( [ 'role' => 'administrator' ] );
				foreach ( $administrators as $administrator ) {
					$this->initializeAdministrator( $administrator->ID );
				}
			}
		);
	}

	private function verifyUser( $user, $skipRoleCheck = false ) {
		if (
			! $user ||
			( ! $user->has_cap( User::CAP_ADMINISTRATOR ) && ! $skipRoleCheck ) ||
			User::getMetaSingle( $user->ID, self::USER_METAKEY_INITIALIZED )
		) {
			return false;
		}
		$this->initializeAdministrator( $user->ID );
		return true;
	}

	private function initializeAdministrator( $user_id ) {
		User::updateMeta( $user_id, self::USER_METAKEY_INITIALIZED, true );

		make( TranslatorCapability::class )->grant( (int) $user_id, [], TranslatorCapability::PAIRS_ALL );
	}

}
