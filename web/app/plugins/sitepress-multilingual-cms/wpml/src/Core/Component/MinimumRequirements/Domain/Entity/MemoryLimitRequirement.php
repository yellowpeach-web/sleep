<?php

namespace WPML\Core\Component\MinimumRequirements\Domain\Entity;

use WPML\Core\Component\MinimumRequirements\Domain\Value\RequirementsConfig;
use WPML\Core\SharedKernel\Component\Server\Domain\ServerInfoInterface;
use WPML\Core\SharedKernel\Component\Server\Domain\Service\ByteSizeConverter;

class MemoryLimitRequirement extends RequirementBase {

  private $serverInfo;

  private $converter;


  public function __construct(
    ServerInfoInterface $server_info, ByteSizeConverter $byte_size_converter
  ) {
    $this->serverInfo = $server_info;
    $this->converter  = $byte_size_converter;
  }


  public function getId(): int {
    return 1;
  }


  public function getTitle(): string {
    /* translators: Name of one requirement on the notice WPML shows when the server does not meet its requirements, and a row label in the system information table on WPML → Support: how much memory PHP may use. */
    return __( 'Memory limit', 'wpml' );
  }


  public function getMessages(): array {
    $messages = [
      [
        'type'    => 'p',
        'message' => $this->getCurrentLimitMessage()
      ],
    ];

    $restriction = $this->getServerRestrictionMessage();
    if ( $restriction !== '' ) {
      $messages[] = [
        'type'    => 'p',
        'message' => $restriction
      ];
    }

    return array_merge( $messages, [
      [
        'type'    => 'p',
        'message' => sprintf(
          /* translators: Text on the notice WPML shows when the server does not meet its requirements, introducing a block of code to copy. %1$s: opening bold tag, %2$s: closing bold tag. wp-config.php is a file name and stays as it is. */
          __(
            'To increase the memory limit, add this to the top of your %1$swp-config.php%2$s file:',
            'wpml'
          ),
          '<strong>',
          '</strong>'
        )
      ],
      [
        'type'    => 'code',
        'message' => "/** Memory Limit */\ndefine( 'WP_MEMORY_LIMIT', '"
                     . RequirementsConfig::MINIMUM_MEMORY
                     . "' );\ndefine( 'WP_MAX_MEMORY_LIMIT', '"
                     . RequirementsConfig::WP_MAX_MEMORY_LIMIT . "' );",
      ]
    ] );
  }


  private function getServerRestrictionMessage(): string {
    if ( ! $this->serverInfo->isIniGetAllBlocked() ) {
      return '';
    }

    if ( $this->getOriginalPHPMemoryLimit() === '' ) {
      return '';
    }

    /* translators: Extra line on the notice WPML shows when the server does not meet its requirements, when the server also blocks the PHP function WPML reads its settings with. ini_get_all() is a PHP function name and disable_functions is a PHP setting name: both stay as they are. */
    return __(
      'Your server blocks the PHP function ini_get_all(). WPML uses that function to read this setting. WPML read the value another way instead. If the number above looks wrong, ask your host to remove ini_get_all() from the disable_functions list.',
      'wpml'
    );
  }


  private function getCurrentLimitMessage(): string {
    $originalLimit = $this->getOriginalPHPMemoryLimit();
    $minimum       = '<strong>' . RequirementsConfig::MINIMUM_MEMORY . '</strong>';

    if ( $originalLimit === '' ) {
      return sprintf(
        /* translators: Text on the notice WPML shows when it cannot read the server's PHP memory limit. %s: the lowest memory limit WPML accepts, in bold. */
        __(
          'WPML could not read your PHP memory limit. Your server blocks the PHP functions that report it. WPML requires at least %s to function properly.',
          'wpml'
        ),
        $minimum
      );
    }

    return sprintf(
      /* translators: Text on the notice WPML shows when the server does not meet its requirements. %1$s: the memory limit the server allows now, in bold, %2$s: the lowest memory limit WPML accepts, in bold. */
      __(
        'Your PHP memory limit is currently %1$s.  WPML requires at least %2$s to function properly.',
        'wpml'
      ),
      '<strong>' .
      $this->converter->toBytes( $originalLimit )
      / ( 1024 * 1024 ) . 'M</strong>',
      $minimum
    );
  }


  protected function doIsValid(): bool {
    if ( $this->isMemoryLimitValid( $this->getOriginalPHPMemoryLimit() ) ) {
      return true;
    }

    return $this->isMemoryLimitValid( $this->getWPMaxMemoryLimit() )
           && $this->isMemoryLimitValid( $this->getWPMemoryLimit() );
  }


  protected function getRequirementType(): string {
    return 'MEMORY_LIMIT';
  }


  private function getWPMemoryLimit() {
    return $this->serverInfo->getConstant( 'WP_MEMORY_LIMIT', '40M' );
  }


  private function getOriginalPHPMemoryLimit(): string {
    return (string) $this->serverInfo->getOriginalIniGet( 'memory_limit' );
  }


  private function getWPMaxMemoryLimit() {
    return $this->serverInfo->getConstant(
      'WP_MAX_MEMORY_LIMIT',
      '256M'
    );
  }


  private function isMemoryLimitValid( $memoryLimit ): bool {
    if ( ! is_string( $memoryLimit ) && ! is_int( $memoryLimit ) ) {
      return false;
    }

    if ( (int) $memoryLimit === - 1 ) {
      return true;
    }

    return $this->converter->toBytes( $memoryLimit )
           >= $this->converter->toBytes( RequirementsConfig::MINIMUM_MEMORY );
  }


}
