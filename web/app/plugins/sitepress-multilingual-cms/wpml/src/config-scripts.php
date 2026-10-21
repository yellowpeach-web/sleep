<?php


use WPML\UserInterface\Web\Core\Component\PostHog\Application\PostHogController;
use WPML\UserInterface\Web\Core\Component\WpmlProxy\Application\WpmlProxyAutoDisableController;
use WPML\UserInterface\Web\Core\Component\WpmlProxy\Application\WpmlProxyAutoEnableController;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PostHog\DashboardSessionScriptController;

return [
  'wpml-node-modules'          => [
    'src'          => 'public/js/node-modules.js',
    'onlyRegister' => true,
  ],
  'wpml-posthog'               => [
    'src'           => 'public/js/wpml-posthog.js',
    'dependencies'  => [ 'wpml-node-modules' ],
    'prerequisites' => PostHogController::class,
    'dataProvider'  => PostHogController::class,
  ],
  'wpml-proxy-auto-enable'     => [
    'src'          => 'public/js/wpml-proxy-auto-enable.js',
    'dataProvider' => WpmlProxyAutoEnableController::class,
    'prerequisites' => WpmlProxyAutoEnableController::class,
  ],
  'wpml-proxy-auto-disable'     => [
    'src'          => 'public/js/wpml-proxy-auto-disable.js',
    'dataProvider' => WpmlProxyAutoDisableController::class,
    'prerequisites' => WpmlProxyAutoDisableController::class,
  ],
  'wpml-setup-tea' => [
    'src'          => 'public/js/wpml-setup-tea.js',
    'components'   => [ 'wpml-tea-calculation', 'ate' ],
    'styles'       => [ 'wpml-setup-tea' ],
    'onlyRegister' => true,
  ],
  'wpml-settings-tea' => [
    'src'          => 'public/js/wpml-settings-tea.js',
    'components'   => [ 'wpml-tea-calculation', 'ate' ],
    'styles'       => [ 'wpml-setup-tea' ],
    'onlyRegister' => true,
  ],

  'wpml-settings-flash'         => [
    'src'          => 'public/js/wpml-settings-flash.js',
    'onlyRegister' => true,
  ],

  'wpml-page-url-non-latin-note' => [
    'src'          => 'public/js/wpml-page-url-non-latin-note.js',
    'onlyRegister' => true,
  ],

  'wpml-page-url-encoded-url-note' => [
    'src'          => 'public/js/wpml-page-url-encoded-url-note.js',
    'onlyRegister' => true,
  ],

  'wpml-ai-engine-switch'       => [
    'src'          => 'public/js/wpml-ai-engine-switch.js',
    'onlyRegister' => true,
    'dependencies' => [ 'wp-api-fetch' ],
  ],
  'posthog-dashboard-session'  => [
    'src'           => 'public/js/posthog-dashboard-session.js',
    'dependencies'  => [ 'wpml-node-modules' ],
    'prerequisites' => DashboardSessionScriptController::class,
    'dataProvider'  => DashboardSessionScriptController::class,
  ],
];
