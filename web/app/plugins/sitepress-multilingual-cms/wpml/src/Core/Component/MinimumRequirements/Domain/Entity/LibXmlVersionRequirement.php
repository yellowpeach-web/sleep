<?php

namespace WPML\Core\Component\MinimumRequirements\Domain\Entity;

use WPML\Core\Component\MinimumRequirements\Domain\Value\RequirementsConfig;
use WPML\Core\SharedKernel\Component\Server\Domain\ServerInfoInterface;

class LibXmlVersionRequirement extends RequirementBase {
  const EXTENSION_NAME = 'libxml';

  private $serverInfo;


  public function __construct( ServerInfoInterface $serverInfo ) {
    $this->serverInfo = $serverInfo;
  }


  public function getId(): int {
    return 9;
  }


  public function getTitle(): string {
    /* translators: Name of one requirement in the list on the notice WPML shows when the server does not meet its requirements: a part of PHP. Keep the module name as it is; it is lower case in PHP itself. */
    return __( 'libxml PHP Module', 'wpml' );
  }


  public function getMessages(): array {
    return [
      [
        'type'    => 'p',
        'message' => sprintf(
          /* translators: Text on the notice WPML shows when the server does not meet its requirements. %s: the lowest libxml version WPML accepts. */
          __(
            'WPML requires libxml PHP Module version %s or higher to process XML correctly using SimpleXML.',
            'wpml'
          ),
          RequirementsConfig::MINIMUM_LIBXML_VERSION
        ),
      ],
      [
        'type'    => 'alert',
        'message' => __(
          'Contact your hosting provider to upgrade the libxml PHP Module.',
          'wpml'
        ),
      ]
    ];
  }


  protected function doIsValid(): bool {
    return $this->serverInfo->isExtensionLoaded( self::EXTENSION_NAME );
  }


  protected function getRequirementType(): string {
    return 'LIBXML_VERSION';
  }


}
