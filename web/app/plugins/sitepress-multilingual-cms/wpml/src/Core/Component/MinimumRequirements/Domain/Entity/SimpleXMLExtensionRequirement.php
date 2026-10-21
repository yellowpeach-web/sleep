<?php

namespace WPML\Core\Component\MinimumRequirements\Domain\Entity;

use WPML\Core\Component\MinimumRequirements\Domain\Value\RequirementsConfig;
use WPML\Core\SharedKernel\Component\Server\Domain\ServerInfoInterface;

class SimpleXMLExtensionRequirement extends RequirementBase {
  const EXTENSION_NAME = 'simplexml';

  private $serverInfo;


  public function __construct( ServerInfoInterface $serverInfo ) {
    $this->serverInfo = $serverInfo;
  }


  public function getId(): int {
    return 5;
  }


  public function getTitle(): string {
    /* translators: Name of one requirement in the list on the notice WPML shows when the server does not meet its requirements: a PHP extension. Keep the extension name as it is. */
    return __( 'SimpleXML Extension', 'wpml' );
  }


  public function getMessages(): array {
    return [
      [
        'type'    => 'p',
        'message' => sprintf(
          /* translators: Text on the notice WPML shows when the server does not meet its requirements. %1$s and %2$s open and close a link to the PHP manual; %3$s and %4$s open and close a link to the WPML documentation. */
          __(
            'The %1$sSimpleXML extension%2$s is required to use %3$sXLIFF files%4$s in WPML. The libxml PHP Module must also be version 2.7.8 or higher.',
            'wpml'
          ),
          '<a  href="https://www.php.net/manual/en/book.simplexml.php" target="_blank">',
          '</a>',
          '<a href="' . self::catToolsDocUrl() . '" target="_blank">',
          '</a>',
          RequirementsConfig::MINIMUM_SIMPLEXML_VERSION
        ),
      ],
      [
        'type'    => 'alert',
        'message' => __(
          'Contact your hosting provider to install the SimpleXML PHP extension.',
          'wpml'
        ),
      ]
    ];
  }


  protected function doIsValid(): bool {
    return $this->serverInfo->isExtensionLoaded( self::EXTENSION_NAME );
  }


  protected function getRequirementType(): string {
    return 'SIMPLEXML_EXTENSION';
  }


  private static function catToolsDocUrl() {
    $url = 'https://wpml.org/documentation/translating-your-contents/using-desktop-cat-tools';

    if ( class_exists( '\WPML\OutboundLinks\OutboundLinks' ) ) {
      return \WPML\OutboundLinks\OutboundLinks::to(
        $url,
        [ 'medium' => 'notice', 'campaign' => 'requirements' ]
      );
    }

    return $url;
  }


}
