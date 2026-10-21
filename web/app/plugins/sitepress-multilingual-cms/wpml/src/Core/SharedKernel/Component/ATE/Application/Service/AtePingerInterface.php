<?php

namespace WPML\Core\SharedKernel\Component\ATE\Application\Service;

interface AtePingerInterface {

  const TRIGGER_DASHBOARD_ENABLE              = 'dashboard_enable';
  const TRIGGER_DASHBOARD_DISABLE             = 'dashboard_disable';
  const TRIGGER_WIZARD_COMPLETION             = 'wizard_completion';
  const TRIGGER_RECHECK                       = 'reachability_recheck';
  const TRIGGER_LANGUAGE_ADDED                = 'language_added';
  const TRIGGER_POST_TYPE_BECAME_TRANSLATABLE = 'post_type_became_translatable';
  const TRIGGER_TEA_CUTOFF_ADJUSTED           = 'tea_cutoff_adjusted';
  const TRIGGER_TRANSLATABLE_SCOPE_RECHECK    = 'translatable_scope_recheck';


  public function notifyTeaEnabled( string $trigger ): bool;


  public function notifyTeaDisabled( string $trigger ): void;


  public function checkReachability( string $trigger );


}
