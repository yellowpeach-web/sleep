<?php

namespace WPML\Core\Component\MinimumRequirements\Domain\Entity;

use WPML\Core\Component\MinimumRequirements\Domain\Value\RequirementsConfig;
use WPML\Core\SharedKernel\Component\Server\Domain\ServerInfoInterface;

class WordPressVersionRequirement extends RequirementBase {

  private $serverInfo;


  public function __construct( ServerInfoInterface $serverInfo ) {
    $this->serverInfo = $serverInfo;
  }


  public function getId(): int {
    return 6;
  }


  public function getTitle(): string {
    /* translators: Name of one requirement in the list on the notice WPML shows when the server does not meet its requirements. */
    return __( 'WordPress Version', 'wpml' );
  }


  public function getMessages(): array {
    return [
      [
        'type'    => 'p',
        'message' => sprintf(
          /* translators: Text on the notice WPML shows when the server does not meet its requirements, a complete sentence on its own; the space at the end is not part of it. %s: the lowest WordPress version WPML accepts, already in bold. */
          __(
            'Your WordPress version is outdated. WPML requires at least %s. ',
            'wpml'
          ),
          '<strong> WordPress ' . RequirementsConfig::MINIMUM_WP_VERSION
          . '</strong>'
        ),
      ],
      [
        'type'    => 'alert',
        'message' => sprintf(
          /* translators: Text on the notice WPML shows when the server does not meet its requirements. %s: the lowest WordPress version WPML accepts. */
          __(
            'Please update your WordPress installation to version %s or higher.',
            'wpml'
          ),
          RequirementsConfig::MINIMUM_WP_VERSION
        ),
      ]
    ];
  }


  protected function doIsValid(): bool {
    return version_compare(
      $this->serverInfo->getWordPressVersion(),
      RequirementsConfig::MINIMUM_WP_VERSION,
      '>='
    );
  }


  protected function getRequirementType(): string {
    return 'WORDPRESS_VERSION';
  }


}
