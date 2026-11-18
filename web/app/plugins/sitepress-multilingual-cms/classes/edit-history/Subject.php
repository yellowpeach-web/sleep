<?php

namespace WPML\EditHistory;

class Subject {

	const POST = 'post';

	private $kind;

	private $id;

	public function __construct( string $kind, int $id ) {
		$this->kind = $kind;
		$this->id   = $id;
	}

	public static function post( int $postId ): self {
		return new self( self::POST, $postId );
	}

	public function kind(): string {
		return $this->kind;
	}

	public function id(): int {
		return $this->id;
	}
}
