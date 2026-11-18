<?php
defined( 'ABSPATH' ) || exit;

$strings = wpml_view_attr( $model, 'strings' );
$tooltips = wpml_view_attr( $strings, 'tooltips' );
$notification = wpml_view_attr( wpml_view_attr( $model, 'notifications' ), 'css_not_loaded' );
?>
<form id="wpml-ls-settings-form" name="wpml_ls_settings_form">

	<input type="hidden" name="wpml-ls-refresh-on-browser-back-button" id="wpml-ls-refresh-on-browser-back-button" value="no">

    <?php if ( $notification ) : ?>
        <div class="wpml-ls-message notice notice-info">
            <p><?php echo wpml_view_esc( $notification ); ?></p>
        </div>
    <?php endif; ?>

    <div id="wpml-language-switcher-options" class="js-wpml-ls-section wpml-section">
        <div class="wpml-section-header">
            <h3><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'options' ), 'section_title' ) ); ?></h3>
        </div>

        <div class="js-setting-group wpml-ls-settings-group wpml-section-content">
            <?php $wpml_view->partial( 'section-options.twig', $model ); ?>
        </div>
    </div>

    <div id="wpml-language-switcher-menus" class="js-wpml-ls-section wpml-section">
        <div class="wpml-section-header">
            <h3>
                <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'menus' ), 'section_title' ) ); ?>

            </h3>
            <?php $wpml_view->partial( 'save-notification.twig', $model ); ?>
        </div>

        <div class="js-setting-group wpml-ls-settings-group wpml-section-content">
            <?php $wpml_view->partial( 'section-menus.twig', $model ); ?>
        </div>
    </div>

    <div id="wpml-language-switcher-sidebars" class="js-wpml-ls-section wpml-section">
        <div class="wpml-section-header">
            <h3>
                <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'sidebars' ), 'section_title' ) ); ?>

            </h3>
            <?php $wpml_view->partial( 'save-notification.twig', $model ); ?>
        </div>

        <div class="js-setting-group wpml-ls-settings-group wpml-section-content">
            <?php $wpml_view->partial( 'section-sidebars.twig', $model ); ?>
        </div>
    </div>

    <div id="wpml-language-switcher-footer" class="js-wpml-ls-section wpml-section">
        <div class="wpml-section-header">
            <h3>
                <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'footer' ), 'section_title' ) ); ?>

                <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( $tooltips, 'show_in_footer' ) ) ) ); ?>
            </h3>
        </div>

        <div class="js-setting-group wpml-ls-settings-group wpml-section-content">
            <?php $wpml_view->partial( 'section-footer.twig', $model ); ?>
        </div>

    </div>

    <div id="wpml-language-switcher-post-translations" class="js-wpml-ls-section wpml-section">
        <div class="wpml-section-header">
            <h3>
                <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'post_translations' ), 'section_title' ) ); ?>

                <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( $tooltips, 'section_post_translations' ) ) ) ); ?>
            </h3>
        </div>

        <div class="js-setting-group wpml-ls-settings-group wpml-section-content">
            <?php $wpml_view->partial( 'section-post-translations.twig', $model ); ?>
        </div>
    </div>

    <div id="wpml-language-switcher-shortcode-action" class="js-wpml-ls-section wpml-section"
        <?php if ( ! wpml_view_attr( $model, 'setup_complete' ) ) : ?> style="display:none;"<?php endif; ?>>
        <div class="wpml-section-header">
            <h3>
                <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'shortcode_actions' ), 'section_title' ) ); ?>

                <?php   ?>
            </h3>
            <?php $wpml_view->partial( 'save-notification.twig', $model ); ?>
        </div>

        <div class="js-setting-group wpml-ls-settings-group wpml-section-content">
            <?php $wpml_view->partial( 'section-shortcode-action.twig', $model ); ?>
        </div>
    </div>

    <?php $wpml_view->partial( 'setup-wizard-buttons.twig', $model ); ?>

    <?php $wpml_view->partial( 'dialog-box.twig', $model ); ?>

</form>