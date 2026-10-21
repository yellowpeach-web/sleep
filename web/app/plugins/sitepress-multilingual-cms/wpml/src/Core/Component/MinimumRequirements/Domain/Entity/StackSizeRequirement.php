<?php

namespace WPML\Core\Component\MinimumRequirements\Domain\Entity;

use WPML\Core\Component\MinimumRequirements\Domain\Value\RequirementsConfig;
use WPML\Core\SharedKernel\Component\Server\Domain\ServerInfoInterface;
use WPML\Core\SharedKernel\Component\Server\Domain\Service\ByteSizeConverter;

class StackSizeRequirement extends RequirementBase {

  private $serverInfo;

  private $byteSizeConverter;

  const BYTES_PER_KB = 1024;


  public function __construct(
    ServerInfoInterface $serverInfo, ByteSizeConverter $byteSizeConverter
  ) {
    $this->serverInfo        = $serverInfo;
    $this->byteSizeConverter = $byteSizeConverter;
  }


  public function getId(): int {
    return 10;
  }


  public function getTitle(): string {
    return __( 'PHP Stack Size', 'wpml' );
  }


  public function getMessages(): array {
    return [
      [
        'type'    => 'p',
        'message' => sprintf(
          /* translators: Text on the notice WPML shows when the server does not meet its requirements. %s: the smallest stack size WPML accepts, in kilobytes. */
          __(
            'WPML requires at least %s KB of PHP stack size on PHP 8.3 or higher.',
            'wpml'
          ),
          RequirementsConfig::MINIMUM_AVAILABLE_STACK_SIZE
        ),
      ],
      [
        'type'    => 'p',
        'message' => sprintf(
          /* translators: Text on the notice WPML shows when the server does not meet its requirements, introducing a block of code to copy. %1$s: opening bold tag, %2$s: closing bold tag. php.ini is a file name and stays as it is. */
          __(
            'Add this to your %1$sphp.ini%2$s and restart your web server to set the required stack size:',
            'wpml'
          ),
          '<strong>',
          '</strong>'
        ),
      ],
      [
        'type'    => 'code',
        'message' => "; Stack Size Configuration\nzend.max_allowed_stack_size = "
                     . RequirementsConfig::MINIMUM_MAX_STACK_SIZE
                     . "K\nzend.reserved_stack_size = "
                     . RequirementsConfig::MINIMUM_RESERVED_STACK_SIZE . "K",
      ]
    ];
  }


  protected function getRequirementType(): string {
    return 'STACK_SIZE';
  }


  protected function doIsValid(): bool {
    if ( version_compare( $this->serverInfo->getPhpVersion(), '8.3', '<' ) ) {
      return true;
    }

    $availableStack = $this->calculateAvailableStackSize();

    return $availableStack >= RequirementsConfig::MINIMUM_AVAILABLE_STACK_SIZE
                              * self::BYTES_PER_KB;
  }


  private function calculateAvailableStackSize(): int {
    return $this->getMaxAllowedStackSize() - $this->getReservedStackSize();
  }


  private function getMaxAllowedStackSize(): int {
    $value = $this->serverInfo->getIniGet( 'zend.max_allowed_stack_size' );

    if ( ! $value ) {
      $value = 0;
    }

    $valueInBytes = $this->byteSizeConverter->toBytes( $value );
    if ( $valueInBytes === - 1 || $valueInBytes === 0 ) {
      return PHP_INT_MAX;
    } else {
      return $valueInBytes;
    }
  }


  private function getReservedStackSize(): int {
    $value = $this->serverInfo->getIniGet( 'zend.reserved_stack_size' );

    if ( ! $value ) {
      $value = 0;
    }

    return max( 0, $this->byteSizeConverter->toBytes( $value ) );
  }


}
