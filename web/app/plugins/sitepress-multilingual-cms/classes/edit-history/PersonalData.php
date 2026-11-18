<?php

namespace WPML\EditHistory;

interface PersonalData {

	public function byUser( int $userId, int $limit, int $offset ): array;

	public function forgetUser( int $userId ): int;
}
