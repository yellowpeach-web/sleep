<?php
defined( 'ABSPATH' ) || exit;
?>
<p><?php echo wpml_view_esc( isset( $intro_message_1 ) ? $intro_message_1 : null ); ?></p>
<p><?php echo wpml_view_esc( isset( $intro_message_2 ) ? $intro_message_2 : null ); ?></p>
<ul>
    <?php foreach ( isset( $jobs ) && is_array( $jobs ) ? $jobs : array() as $job ) : ?>
        <?php if ( wpml_view_attr( $job, 'get_deadline_date' ) ) : ?>
            <?php
            $wpml_view_timezone = new DateTimeZone( date_default_timezone_get() );
            $wpml_view_date     = wpml_view_attr( $job, 'get_deadline_date' );
            $wpml_view_text     = (string) $wpml_view_date;
            $wpml_view_deadline = ( ctype_digit( $wpml_view_text ) || ( ! empty( $wpml_view_text ) && '-' === $wpml_view_text[0] && ctype_digit( substr( $wpml_view_text, 1 ) ) ) )
                ? new DateTime( '@' . $wpml_view_date )
                : new DateTime( $wpml_view_date, $wpml_view_timezone );
            $deadline_date      = $wpml_view_deadline->setTimezone( $wpml_view_timezone )->format( 'Y-m-d' );
            ?>
            <?php $overdue_days = wpml_view_attr( $job, 'get_number_of_days_overdue' ); ?>
            <?php $details = sprintf( (string) ( isset( $job_deadline_details ) ? $job_deadline_details : '' ), $deadline_date, $overdue_days ); ?>

            <li><?php echo wpml_view_esc( wpml_view_attr( $job, 'get_title' ) ); ?> (<?php echo wpml_view_esc( $details ); ?>)</li>
        <?php else : ?>
            <li><?php echo wpml_view_esc( wpml_view_attr( $job, 'get_title' ) ); ?></li>
        <?php endif; ?>
    <?php endforeach; ?>
</ul>
<p><?php echo wpml_view_raw( isset( $message_to_translation_jobs ) ? $message_to_translation_jobs : null ); ?></p>