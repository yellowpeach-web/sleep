<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings  = isset( $strings ) ? $strings : null;
$wpml_view_settings = isset( $settings ) ? $settings : null;
?>
<?php ob_start(); ?>
<select name="notification[completed_frequency]">
    <?php $wpml_view_options = wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'completed_frequency' ), 'options' ); foreach ( is_array( $wpml_view_options ) ? $wpml_view_options : array() as $option ) : ?>
    <option value="<?php echo wpml_view_esc( wpml_view_attr( $option, 'value' ) ); ?>"<?php echo wpml_view_esc( wpml_view_attr( $option, 'checked' ) ); ?>><?php echo wpml_view_esc( wpml_view_attr( $option, 'label' ) ); ?></option>
    <?php endforeach; ?>
</select>
<?php $completed_frequency_dropdown = ob_get_clean(); ?>

<?php ob_start(); ?>
<select id="wpml_tm_job_limits_in_email" name="notification[job_limits]"<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'job_limits' ), 'disabled' ) ); ?>>
    <?php $wpml_view_options = wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'job_limits' ), 'options' ); foreach ( is_array( $wpml_view_options ) ? $wpml_view_options : array() as $option ) : ?>
    <option value="<?php echo wpml_view_esc( wpml_view_attr( $option, 'value' ) ); ?>"<?php echo wpml_view_esc( wpml_view_attr( $option, 'checked' ) ); ?>><?php echo wpml_view_esc( wpml_view_attr( $option, 'label' ) ); ?></option>
    <?php endforeach; ?>
</select>
<?php $job_limits_dropdown = ob_get_clean(); ?>

<?php ob_start(); ?>
    <input name="notification[overdue_offset]" value="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'overdue_offset' ), 'value' ) ); ?>"
           size="1"<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'overdue_offset' ), 'disabled' ) ); ?>>
<?php $overdue_offset_input = ob_get_clean(); ?>

<?php
?>
<div class="wpml-section" id="translation-notifications">
    <div class="wpml-section-header">
        <h2><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'section_title' ) ); ?></h2>
    </div>
    <div class="wpml-section-content">
        <div id="translation-notifications-translator" class="wpml-notifications-subsection">
            <h4><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'section_title_translator' ) ); ?></h4>
            <ul>
                <li>
                    <input name="notification[new-job]" type="checkbox" id="wpml_tm_notify_new_job" class="wpml-checkbox-native"
                           data-child="notification[include_xliff]||notification[job_limits]"
                           value="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'new_job' ), 'value' ) ); ?>"<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'new_job' ), 'checked' ) ); ?>>
                    <label for="wpml_tm_notify_new_job"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'label_new_job' ) ); ?></label>
                    <ul class="wpml-subsection">
                        <li>
                            <label for="wpml_tm_job_limits_in_email"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'label_job_limits' ) ); ?></label>
                            <?php echo wpml_view_raw( $job_limits_dropdown ); ?>

                        </li>
                        <li>
                            <input type="checkbox" name="notification[include_xliff]" id="wpml_tm_notify_include_xliff" class="wpml-checkbox-native"
                                   value="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'include_xliff' ), 'value' ) ); ?>"
                                    <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'include_xliff' ), 'checked' ) ); ?><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'include_xliff' ), 'disabled' ) ); ?>>
                            <label for="wpml_tm_notify_include_xliff"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'label_include_xliff' ) ); ?></label>
                        </li>
                    </ul>
                </li>
                <li>
                    <input name="notification[resigned]" type="checkbox" id="wpml_tm_notify_job_resigned"
                           class="wpml-checkbox-native"
                           value="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'resigned' ), 'value' ) ); ?>"<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'resigned' ), 'checked' ) ); ?>>
                    <label for="wpml_tm_notify_job_resigned"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'label_resigned_job' ) ); ?></label>
                </li>
            </ul>
        </div>
        <div id="translation-notifications-manager" class="wpml-notifications-subsection">
            <h4><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'section_title_manager' ) ); ?></h4>
            <ul>
                <li>
                    <label><?php echo wpml_view_raw( sprintf( (string) wpml_view_attr( $wpml_view_strings, 'label_completed_job' ), $completed_frequency_dropdown ) ); ?></label>
                </li>
                <li>
                    <input name="notification[manager_resigned]" type="checkbox" id="wpml_tm_notify_manager_resigned"
                           class="wpml-checkbox-native"
                           value="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'manager_resigned' ), 'value' ) ); ?>"<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'manager_resigned' ), 'checked' ) ); ?>>
                    <label for="wpml_tm_notify_manager_resigned"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'label_manager_resigned' ) ); ?></label>
                </li>
                <li>
                    <input name="notification[service_job_update]" type="checkbox" id="wpml_tm_notify_service_job_update"
                           class="wpml-checkbox-native"
                           value="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'service_job_update' ), 'value' ) ); ?>"<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'service_job_update' ), 'checked' ) ); ?>>
                    <label for="wpml_tm_notify_service_job_update"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'label_service_job_update' ) ); ?></label>
                </li>
                <li>
                    <input name="notification[overdue]" type="checkbox" id="wpml_tm_notify_job_overdue"
                           class="wpml-checkbox-native"
                           data-child="notification[overdue_offset]"
                           value="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'overdue' ), 'value' ) ); ?>"<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_settings, 'overdue' ), 'checked' ) ); ?>>
                    <label for="wpml_tm_notify_job_overdue">
                        <?php echo wpml_view_raw( sprintf( (string) wpml_view_attr( $wpml_view_strings, 'label_overdue_job' ), $overdue_offset_input ) ); ?>

                    </label>
                </li>
            </ul>
        </div>
        <p class="submit buttons-wrap">
            <span class="icl_ajx_response" id="icl_ajx_response_notifications"></span>
            <input type="submit" class="button-primary wpml-button base-btn" value="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'label_save' ) ); ?>"/>
        </p>
    </div>
</div>
