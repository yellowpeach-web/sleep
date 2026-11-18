<?php
defined( 'ABSPATH' ) || exit;


$wpml_view_nonce = isset( $nonce ) ? $nonce : null;
$wpml_view_warning = isset( $warning ) ? $warning : null;
$wpml_view_message = isset( $message ) ? $message : null;
$wpml_view_translations_in_progress = isset( $translations_in_progress ) ? $translations_in_progress : null;
$wpml_view_edit_anyway_button = isset( $edit_anyway_button ) ? $edit_anyway_button : null;
$wpml_view_go_back_button = isset( $go_back_button ) ? $go_back_button : null;
$wpml_view_do_not_show_again_action = isset( $do_not_show_again_action ) ? $do_not_show_again_action : null;
$wpml_view_do_not_show_again = isset( $do_not_show_again ) ? $do_not_show_again : null;
?>
<div id="wpml-tm-post-edit-alert" class="js-wpml-tm-post-edit-alert wpml-tm-post-edit-alert wpml-tm-post-edit-alert-in-progress" style="display: none;">
    <?php echo wpml_view_raw( $wpml_view_nonce ); ?>

    <p id="icl_side_by_site">
        <?php echo wpml_view_raw( $wpml_view_warning ); ?>

        <br /><br />
        <?php echo wpml_view_raw( $wpml_view_message ); ?>

    </p>

   <?php if ( array_key_exists( 'translations_in_progress', $model ) ) : ?>
       <?php $wpml_view_display_limit = wpml_view_attr( $wpml_view_translations_in_progress, 'display_limit' ); ?>
       <?php $wpml_view_translations = wpml_view_attr( $wpml_view_translations_in_progress, 'translations' ); ?>
       <?php if ( $wpml_view_translations ) : ?>
            <table style="width:100%">
                <tr>
                    <th colspan="3" style="text-align: left;">
                       <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_translations_in_progress, 'title' ) ); ?>

                    </th>
                </tr>
                <?php $wpml_view_break = false; ?><?php $wpml_view_loop_index = 0; ?>
                <?php foreach ( is_array( $wpml_view_translations ) ? $wpml_view_translations : array() as $wpml_view_translation ) : ?><?php $wpml_view_loop_index++; ?><?php if ( $wpml_view_break ) { continue; } ?>
                    <?php if ( $wpml_view_loop_index == $wpml_view_display_limit && wpml_view_length( $wpml_view_translations ) > $wpml_view_display_limit ) : ?>
                        <tr>
                            <td colspan="3"><i><?php echo wpml_view_esc( sprintf( wpml_view_string( wpml_view_attr( $wpml_view_translations_in_progress, 'more' ) ), wpml_view_length( $wpml_view_translations ) + 1 - $wpml_view_display_limit ) ); ?></i></td>
                        </tr>
                        <?php $wpml_view_break = true; ?>
                    <?php else: ?>
                        <tr>
                            <td style="width: 5%"><?php echo wpml_view_raw( wpml_view_attr( $wpml_view_translation, 'flag' ) ); ?></td>
                            <td><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_translation, 'translator' ) ? wpml_view_attr( $wpml_view_translation, 'translator' ) : wpml_view_attr( $wpml_view_translations_in_progress, 'no_translator' ) ); ?></td>
                            <td style="text-align: right"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_translation, 'waiting_for' ) ); ?></td>
                        </tr>
                   <?php endif; ?>
                <?php endforeach; ?>
            </table>
            <?php if ( wpml_view_attr( $wpml_view_translations_in_progress, 'msg_stale_job' ) ) : ?>
			    <div class="notice inline notice-error notice-alt" style="margin: 10px 0; padding:5px">
                    <?php echo wpml_view_raw( wpml_view_attr( $wpml_view_translations_in_progress, 'msg_stale_job' ) ); ?>

                </div>
            <?php endif; ?>
       <?php endif; ?>
   <?php endif; ?>

    <p class="text-center">
        <a class="js-wpml-tm-continue button button-secondary"><?php echo wpml_view_esc( $wpml_view_edit_anyway_button ); ?></a>
        <a class="js-wpml-tm-go-back button button-secondary"><?php echo wpml_view_esc( $wpml_view_go_back_button ); ?></a>
    </p>
    <p><label><input class="do-not-show-again" type="checkbox" value="1" data-action="<?php echo wpml_view_esc( $wpml_view_do_not_show_again_action ); ?>"><?php echo wpml_view_esc( $wpml_view_do_not_show_again ); ?></label></p>
</div>
