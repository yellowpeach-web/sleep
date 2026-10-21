<?php

namespace WPML\Core\Component\MinimumRequirements\Domain\Entity;

use WPML\Core\Component\MinimumRequirements\Domain\Value\RequirementsConfig;
use WPML\Core\SharedKernel\Component\Server\Domain\ServerInfoInterface;

class DatabaseVersionRequirement extends RequirementBase {

  private $serverInfo;


  public function __construct( ServerInfoInterface $serverInfo ) {
    $this->serverInfo = $serverInfo;
  }


  public function getId(): int {
    return 3;
  }


  public function getTitle(): string {
    /* translators: Name of one requirement in the list on the notice WPML shows when the server does not meet its requirements: the version of the database server. */
    return __( 'Database Version', 'wpml' );
  }


  public function getMessages(): array {
    return [
      [
        'type'    => 'p',
        'message' => sprintf(
          /* translators: Text on the notice WPML shows when the server does not meet its requirements. %1$s: opening bold tag, %2$s: closing bold tag, %3$s: the lowest MySQL version WPML accepts, already in bold, %4$s: opening bold tag, %5$s: closing bold tag, %6$s: the lowest MariaDB version WPML accepts. */
          __(
            'Your %1$sMySQL%2$s version must be at least %3$s. Alternatively, you can use %4$sMariaDb%5$s version %6$s or higher.',
            'wpml'
          ),
          '<strong>',
          '</strong>',
          '<strong>' . RequirementsConfig::MINIMUM_MYSQL_VERSION . '</strong>',
          '<strong>',
          '</strong>',
          RequirementsConfig::MINIMUM_MARIADB_VERSION
        ),
      ],
      [
        'type'    => 'alert',
        'message' => sprintf(
          /* translators: Text on the notice WPML shows when the server does not meet its requirements. %s: the lowest MySQL version WPML accepts. */
          __(
            'Contact your hosting provider to upgrade MySQL %s or higher.',
            'wpml'
          ),
          RequirementsConfig::MINIMUM_MYSQL_VERSION
        ),
      ]
    ];
  }


  protected function doIsValid(): bool {
    $dbVersion = $this->serverInfo->getDbVersion();
    if ( empty( $dbVersion ) ) {
      return false;
    }
    if ( $this->usesMariaDB( $dbVersion ) ) {
      return $this->isValidMariaDBVersion( $dbVersion );
    } else {
      return $this->isValidMySQLVersion( $dbVersion );
    }
  }


  protected function getRequirementType(): string {
    return 'DATABASE_VERSION';
  }


  private function usesMariaDB( string $version ): bool {
    return stripos( $version, 'mariadb' ) !== false;
  }


  private function isValidMariaDBVersion( string $version ): bool {
    preg_match( '/([\d.]+)-MariaDB/i', $version, $matches );
    $db_version = $matches[1] ?? '';

    if ( empty( $db_version ) ) {
      return false;
    }

    return version_compare(
      $db_version,
      RequirementsConfig::MINIMUM_MARIADB_VERSION,
      '>='
    );
  }


  private function isValidMySQLVersion( string $version ): bool {
    preg_match( '/([0-9]+\.[0-9]+(?:\.[0-9]+)?)/', $version, $matches );
    $db_version = $matches[1] ?? '';

    if ( stripos( $version, 'postgres' ) !== false ) {
      return false;
    }

    return version_compare(
      $db_version,
      RequirementsConfig::MINIMUM_MYSQL_VERSION,
      '>='
    );
  }


}
