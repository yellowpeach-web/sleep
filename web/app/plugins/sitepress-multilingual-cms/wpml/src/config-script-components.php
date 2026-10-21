<?php


use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\EateWidget\EateWidgetDataEndpoint;
use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\GetAccountBalances\GetAccountBalancesController;
use WPML\UserInterface\Web\Core\Component\ATE\Application\Endpoint\MigrationCode\GetMigrationCodeEndpoint;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\Endpoint\GetCredits\GetCreditsController;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint\GetItemsEndpoint;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint\GetTypesEndpoint;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint\PersistOfferedTypeSinceDates;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint\UpdateTypesInclude;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint\UpdateTypesIncludeBatch;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint\DiscardPendingTranslatableOffer;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint\DismissParkedTypesOffer;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\TeaCalculationScriptController;

return [
  'wpml-tea-calculation' => [
    'dependencies' => [ 'wpml-node-modules' ],
    'dataProvider' => TeaCalculationScriptController::class,
    'endpoints'    => [
      'teacalculationtypes' => [
        'path'       => '/tea-calculation-types',
        'handler'    => GetTypesEndpoint::class,
        'method'     => 'GET',
        'capability' => WPML_CAP_MANAGE_TRANSLATIONS,
      ],
      'teacalculationupdatetypesinclude' => [
        'path'       => '/tea-calculation-update-types-include',
        'handler'    => UpdateTypesInclude::class,
        'method'     => 'POST',
        'capability' => WPML_CAP_MANAGE_TRANSLATIONS,
      ],
      'teacalculationupdatetypesincludebatch' => [
        'path'       => '/tea-calculation-update-types-include-batch',
        'handler'    => UpdateTypesIncludeBatch::class,
        'method'     => 'POST',
        'capability' => WPML_CAP_MANAGE_TRANSLATIONS,
      ],
      'teacalculationdiscardpendingoffer' => [
        'path'       => '/tea-calculation-discard-pending-offer',
        'handler'    => DiscardPendingTranslatableOffer::class,
        'method'     => 'POST',
        'capability' => WPML_CAP_MANAGE_TRANSLATIONS,
      ],
      'teacalculationdismissparkedoffer' => [
        'path'       => '/tea-calculation-dismiss-parked-offer',
        'handler'    => DismissParkedTypesOffer::class,
        'method'     => 'POST',
        'capability' => WPML_CAP_MANAGE_TRANSLATIONS,
      ],
      'teacalculationpersistoffereddefaults' => [
        'path'       => '/tea-calculation-persist-offered-defaults',
        'handler'    => PersistOfferedTypeSinceDates::class,
        'method'     => 'POST',
        'capability' => WPML_CAP_MANAGE_TRANSLATIONS,
      ],
      'teacalculationitems' => [
        'path'       => '/tea-calculation-items',
        'handler'    => GetItemsEndpoint::class,
        'method'     => 'POST',
        'capability' => WPML_CAP_MANAGE_TRANSLATIONS,
      ],
    ],
  ],
  'ate' => [
    'endpoints'    => [
      'geteatewidgetdata' => [
        'path' => '/get-eatewidget-data',
        'handler' => EateWidgetDataEndpoint::class,
        'method' => 'GET',
      ],
      'getmigrationcode' => [
        'path' => '/get-migration-code',
        'handler' => GetMigrationCodeEndpoint::class,
        'method' => 'GET',
      ],
      'getaccountbalances' => [
        'path' => '/account-balances',
        'handler' => GetAccountBalancesController::class,
        'method' => 'GET',
      ],
      'getcredits' => [
        'path'    => '/credits',
        'handler' => GetCreditsController::class,
        'method'  => 'GET',
      ],
    ],
  ],
];
