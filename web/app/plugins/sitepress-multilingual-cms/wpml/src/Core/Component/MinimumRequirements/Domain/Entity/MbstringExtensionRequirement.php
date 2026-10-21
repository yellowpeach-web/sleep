<?php

namespace WPML\Core\Component\MinimumRequirements\Domain\Entity;

use WPML\Core\SharedKernel\Component\Server\Domain\ServerInfoInterface;

class MbstringExtensionRequirement extends RequirementBase {
  const EXTENSION_NAME = 'mbstring';

  private $serverInfo;


  public function __construct( ServerInfoInterface $serverInfo ) {
    $this->serverInfo = $serverInfo;
  }


  public function getId(): int {
    return 7;
  }


  public function getTitle(): string {
    return __( 'Multibyte String Extension', 'wpml' );
  }


  public function getMessages(): array {
    return [
      [
        'type'    => 'p',
        'message' => sprintf(
          /* translators: Text on the notice WPML shows when the server does not meet its requirements. %1$s: opening link tag to the PHP manual, %2$s: closing link tag. */
          __(
            'The %1$sMultibyte String extension%2$s is required to handle non-Latin character sets in WPML.',
            'wpml'
          ),
          '<a href="https://www.php.net/manual/en/book.mbstring.php" target="_blank">',
          '</a>'
        ),
      ],
      [
        'type'    => 'alert',
        'message' => __(
          'Contact your hosting provider to install the Multibyte String (mbstring) PHP extension.',
          'wpml'
        ),
      ]
    ];
  }


  protected function doIsValid(): bool {
    return $this->serverInfo->isExtensionLoaded( self::EXTENSION_NAME );
  }


  protected function getRequirementType(): string {
    return 'MBSTRING_EXTENSION';
  }


}
