<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\AutomaticServiceSelection\Application;

use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\NoticeRenderInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\NoticeRequirementsInterface;

class AutomaticServiceSelectionNoticeController implements NoticeRenderInterface, NoticeRequirementsInterface {

  const OPTION_NAME = 'wpml_automatic_service_selection_failed';

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function requirementsMet(): bool {
    return $this->getMessage() !== '';
  }


  public function render() {
    echo '<div class="notice notice-error"><p>'
      . htmlspecialchars( $this->getMessage(), ENT_QUOTES, 'UTF-8' )
      . '</p></div>';
  }


  private function getMessage(): string {
    $failure = $this->options->get( self::OPTION_NAME );

    if ( ! is_array( $failure ) || ! isset( $failure['message'] ) || ! is_string( $failure['message'] ) ) {
      return '';
    }

    return $failure['message'];
  }


}
