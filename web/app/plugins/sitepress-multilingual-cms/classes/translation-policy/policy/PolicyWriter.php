<?php

namespace WPML\TranslationPolicy\Policy;

class PolicyWriter {

	private $wpdb;

	private $table;

	private $sql;

	public function __construct( \wpdb $wpdb, PolicyTable $table, PolicySql $sql ) {
		$this->wpdb  = $wpdb;
		$this->table = $table;
		$this->sql   = $sql;
	}

	public function set( string $scope, string $scopeId, $editor, $method, string $strength, string $source = '', string $reason = '' ): bool {
		$scopeId = $this->scopeId( $scope, $scopeId );
		$this->assertIn( 'strength', $strength, PolicyTable::STRENGTHS );
		if ( null === $editor && null === $method ) {
			throw new \InvalidArgumentException( 'A policy row needs an editor or a method.' );
		}
		if ( null !== $editor ) {
			$this->assertIn( 'editor', $editor, PolicyTable::EDITORS );
		}
		if ( null !== $method ) {
			$this->assertIn( 'method', $method, PolicyTable::METHODS );
		}

		$key    = [
			'scope'    => $scope,
			'scope_id' => $scopeId,
			'strength' => $strength,
			'source'   => $source,
		];
		$values = [
			'editor'     => $editor,
			'method'     => $method,
			'reason'     => $reason,
			'updated_at' => current_time( 'mysql', true ),
		];

		$rowId = PolicyTable::STRENGTH_CHOICE === $strength
			? $this->sql->choiceRowId( $scope, $scopeId )
			: $this->sql->rowId( $scope, $scopeId, $strength, $source );

		$result = $rowId
			? $this->wpdb->update( $this->table->name(), $values + [ 'source' => $source ], [ 'id' => $rowId ] )
			: $this->wpdb->insert( $this->table->name(), $key + $values );

		Policy::resetRequestCache();

		return false !== $result;
	}

	public function remove( string $scope, string $scopeId, string $strength, string $source = '' ): bool {
		$result = $this->wpdb->delete(
			$this->table->name(),
			[
				'scope'    => $scope,
				'scope_id' => $this->scopeId( $scope, $scopeId ),
				'strength' => $strength,
				'source'   => $source,
			],
			[ '%s', '%s', '%s', '%s' ]
		);

		Policy::resetRequestCache();

		return false !== $result;
	}

	private function scopeId( string $scope, string $scopeId ): string {
		$this->assertIn( 'scope', $scope, PolicyTable::SCOPES );
		if ( PolicyTable::SCOPE_SITE === $scope ) {
			return '';
		}
		if ( '' === $scopeId || ( PolicyTable::SCOPE_POST === $scope && ( ! ctype_digit( $scopeId ) || 0 === (int) $scopeId ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid scope id "%s" for the scope "%s".', esc_html( $scopeId ), esc_html( $scope ) ) );
		}

		return PolicyTable::SCOPE_POST === $scope ? (string) (int) $scopeId : $scopeId;
	}

	private function assertIn( string $name, string $value, array $allowed ) {
		if ( ! in_array( $value, $allowed, true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid policy %s "%s".', esc_html( $name ), esc_html( $value ) ) );
		}
	}
}
