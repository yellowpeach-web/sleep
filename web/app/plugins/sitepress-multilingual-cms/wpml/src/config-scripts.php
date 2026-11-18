<?php


use WPML\UserInterface\Web\Core\Component\PostHog\Application\PostHogController;
use WPML\UserInterface\Web\Core\Component\WpmlProxy\Application\WpmlProxyAutoDisableController;
use WPML\UserInterface\Web\Core\Component\WpmlProxy\Application\WpmlProxyAutoEnableController;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PostHog\DashboardSessionScriptController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\LanguagePanel\EditorLanguagePanelScriptController;

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
    'components'   => [ 'wpml-tea-calculation', 'ate', 'wpml-translation-engines' ],
    'styles'       => [ 'wpml-setup-tea' ],
    'dependencies' => [ 'wp-api-fetch' ],
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
    'dependencies' => [ 'wp-api-fetch', 'wpml-node-modules', 'wp-i18n' ],
  ],
  'posthog-dashboard-session'  => [
    'src'           => 'public/js/posthog-dashboard-session.js',
    'dependencies'  => [ 'wpml-node-modules' ],
    'prerequisites' => DashboardSessionScriptController::class,
    'dataProvider'  => DashboardSessionScriptController::class,
  ],

  'wpml-language-panel'        => [
    'src'           => 'public/js/language-panel.js',
    'dependencies'  => [
      'wp-plugins',
      'wp-editor',
      'wp-data',
      'wp-components',
      'wp-core-data',
      'wp-api-fetch',
      'wp-i18n',
      'wp-element',
      'wp-url',
      'wp-blocks',
      'wp-notices',
    ],
    'styles'        => [ 'wpml-language-panel' ],
    'prerequisites' => EditorLanguagePanelScriptController::class,
    'dataProvider'  => EditorLanguagePanelScriptController::class,
  ],
];
