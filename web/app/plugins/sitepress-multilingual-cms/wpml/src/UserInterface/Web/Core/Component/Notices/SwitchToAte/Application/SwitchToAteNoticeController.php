<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\SwitchToAte\Application;

use WPML\Core\Component\Communication\Application\Query\DismissedNoticesQuery;
use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\SharedKernel\Component\User\Application\Query\UserQueryInterface;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeNoticeDataProvider;
use WPML\UserInterface\Web\Core\SharedKernel\Config\NoticeRenderInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\NoticeRequirementsInterface;

class SwitchToAteNoticeController implements NoticeRenderInterface, NoticeRequirementsInterface {

  private $dismissedNoticesQuery;

  private $userQuery;

  private $settingsRepository;

  private $teaUpgradeNotice;

  private $noticeId;


  public function __construct(
    DismissedNoticesQuery $dismissedNoticesQuery,
    UserQueryInterface $userQuery,
    SettingsRepository $settingsRepository,
    TeaUpgradeNoticeDataProvider $teaUpgradeNotice
  ) {
    $this->dismissedNoticesQuery = $dismissedNoticesQuery;
    $this->userQuery = $userQuery;
    $this->settingsRepository = $settingsRepository;
    $this->teaUpgradeNotice = $teaUpgradeNotice;
    $this->noticeId = 'switch-to-ate-notice';
  }


  public function render() {
    echo <<<HTML
      <div class="wpml-notice-ate-banner-wrapper" id="wpml-switch-to-ate-notice"></div>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          const noticeRoot = document.getElementById('wpml-switch-to-ate-notice');
          // The banner is rendered hidden in the admin-notices area, then moved
          // into the dashboard content and revealed. The legacy TM dashboard
          // wraps content in `.icl_tm_wrap`; the revamped dashboard
          // (wpmldev-6798) replaced it with the React root `#wpml-dashboard`
          // inside the standard `.wrap`. Fall back to `.wrap` so the banner
          // still shows there. `.wrap` sits outside the React root, so React
          // reconciliation won't strip the relocated node.
          const wrapper = document.querySelector('.icl_tm_wrap')
            || ( document.getElementById('wpml-dashboard') ? document.querySelector('.wrap') : null );
          if ( wrapper && noticeRoot ) {
            wrapper.prepend(noticeRoot);
            noticeRoot.style.display = 'block';
          }
        } );
      </script>
HTML;
  }


  public function requirementsMet() : bool {
    return $this->isAteDisabled()
      && $this->noticeIsVisibleToCurrentUser()
      && ! $this->teaUpgradeNotice->isEligible();
  }


  private function noticeIsVisibleToCurrentUser() : bool {
    $user = $this->userQuery->getCurrent();

    if ( ! $user ) {
      return false;
    }

    return empty( $this->dismissedNoticesQuery->getDismissedByUser( $user->getId(), [ $this->noticeId ] ) );
  }


  private function isAteDisabled() : bool {
    $translationEditor = $this->settingsRepository->getSettings()->getTranslationEditor();

    return $translationEditor && $translationEditor->getValue() !== 'ATE';
  }


}
