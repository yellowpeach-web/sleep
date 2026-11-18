<?php

namespace WPML\Core\Component\UrlHandling\Domain\SubsiteClash;

final class SitePath {

	const STATUS_ARCHIVED    = 'archived';
	const STATUS_DEACTIVATED = 'deactivated';
	const STATUS_SPAM        = 'spam';
	const STATUS_MATURE      = 'mature';

	private $id;

	private $networkId;

	private $domain;

	private $path;

	private $url;

	private $flags;

	private $name;

	public function __construct( int $id, int $networkId, string $domain, string $path, string $url, array $flags, string $name = '' ) {
		$this->id        = $id;
		$this->networkId = $networkId;
		$this->domain    = $domain;
		$this->path      = $path;
		$this->url       = $url;
		$this->flags     = $flags;
		$this->name      = $name;
	}

	public function id(): int {
		return $this->id;
	}

	public function networkId(): int {
		return $this->networkId;
	}

	public function domain(): string {
		return $this->domain;
	}

	public function path(): string {
		return $this->path;
	}

	public function url(): string {
		return $this->url;
	}

	public function name(): string {
		return $this->name;
	}

	public function withName( string $name ): self {
		$site       = clone $this;
		$site->name = $name;

		return $site;
	}

	public function statuses(): array {
		$statuses = [];
		foreach ( [ self::STATUS_ARCHIVED, self::STATUS_DEACTIVATED, self::STATUS_SPAM, self::STATUS_MATURE ] as $status ) {
			if ( ! empty( $this->flags[ $status ] ) ) {
				$statuses[] = $status;
			}
		}

		return $statuses;
	}
}
