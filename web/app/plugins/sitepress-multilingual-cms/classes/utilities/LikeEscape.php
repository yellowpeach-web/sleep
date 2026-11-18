<?php

namespace WPML\Utils;

class LikeEscape {

	public static function escape( $text ): string {
		return str_replace( array( '|', '%', '_' ), array( '||', '|%', '|_' ), (string) $text );
	}

	public static function contains( $text ): string {
		return '%' . self::escape( $text ) . '%';
	}

	public static function startsWith( $text ): string {
		return self::escape( $text ) . '%';
	}
}
