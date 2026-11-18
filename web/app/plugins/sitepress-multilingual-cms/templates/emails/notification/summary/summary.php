<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_jobs            = isset( $jobs ) ? $jobs : null;
$wpml_view_strings         = isset( $strings ) ? $strings : null;
$wpml_view_improve_quality = isset( $improve_quality ) ? $improve_quality : null;
$text                      = isset( $text ) ? $text : null;
$site_name                 = isset( $site_name ) ? $site_name : null;
$number_of_updates         = isset( $number_of_updates ) ? $number_of_updates : null;
$css_border_right          = '';
$css_border_left           = '';
$css_border_top            = '';
$css_border_bottom         = '';
$css_text_center           = '';
$css_vertical_align        = '';
$css_column_fixed_width    = '';
?>
<?php if ( wpml_view_attr( $wpml_view_jobs, 'completed' ) ) : ?>

    <?php $css_border = '1px solid #999999;'; ?>
    <?php $css_border_right = 'border-right:' . $css_border; ?>
    <?php $css_border_left = 'border-left:' . $css_border; ?>
    <?php $css_border_top = 'border-top:' . $css_border; ?>
    <?php $css_border_bottom = 'border-bottom:' . $css_border; ?>
    <?php $css_text_center = ' text-align: center;'; ?>
    <?php $css_vertical_align = ' vertical-align: top;'; ?>
    <?php $css_column_fixed_width = ' width: 21%;'; ?>


    <p><?php echo wpml_view_esc( sprintf( (string) $text, $site_name, $number_of_updates ) ); ?>:</p>

    <table border="0" cellpadding="5" cellspacing="0" width="100%">
        <tr style="background: #5a5a5a; color: #ffffff; font-size: 12px;">
            <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_left . $css_border_top ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'original_page' ) ); ?></td>
            <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'translation' ) ); ?></td>
            <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'translator' ) ); ?></td>
            <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom . $css_column_fixed_width ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'date' ) ); ?></td>
            <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom . $css_column_fixed_width ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'your_deadline' ) ); ?></td>
        </tr>

        <?php $wpml_view_rows = wpml_view_attr( $wpml_view_jobs, 'completed' ); foreach ( is_array( $wpml_view_rows ) ? $wpml_view_rows : array() as $job ) : ?>
            <tr style="font-size: 12px;">
                <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_left . $css_border_bottom . $css_vertical_align ); ?>"><?php if ( wpml_view_attr( wpml_view_attr( $job, 'original_page' ), 'url' ) ) : ?><a href="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $job, 'original_page' ), 'url' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $job, 'original_page' ), 'title' ) ); ?></a><?php else : ?><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $job, 'original_page' ), 'title' ) ); ?><?php endif; ?></td>
                <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom . $css_vertical_align ); ?>"><?php if ( wpml_view_attr( wpml_view_attr( $job, 'translated_page' ), 'url' ) ) : ?><a href="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $job, 'translated_page' ), 'url' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $job, 'translated_page' ), 'title' ) ); ?></a><?php else : ?><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $job, 'translated_page' ), 'title' ) ); ?><?php endif; ?></td>
                <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom . $css_vertical_align ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $job, 'translator' ) ); ?></td>
                <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom . $css_vertical_align ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $job, 'completed_date' ) ); ?></td>
                <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom . $css_vertical_align ); ?> <?php if ( wpml_view_attr( $job, 'overdue' ) ) : ?>color: #CC0000;<?php endif; ?>"><?php echo wpml_view_esc( wpml_view_attr( $job, 'deadline' ) ? wpml_view_attr( $job, 'deadline' ) : wpml_view_attr( $wpml_view_strings, 'undefined' ) ); ?></td>
            </tr>
        <?php endforeach; ?>

    </table>

<?php endif; ?>

<?php if ( wpml_view_attr( $wpml_view_jobs, 'waiting' ) ) : ?>

    <p><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'jobs_waiting' ) ); ?>:</p>

    <table border="0" cellpadding="5" cellspacing="0" width="100%">
        <tr style="background: #5a5a5a; color: #ffffff; font-size: 12px;">
            <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_left . $css_border_top ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'translation_languages' ) ); ?></td>
            <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'number_of_pages' ) ); ?></td>
            <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'number_of_strings' ) ); ?></td>
            <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'number_of_words' ) ); ?></td>
        </tr>

        <?php $wpml_view_rows = wpml_view_attr( $wpml_view_jobs, 'waiting' ); foreach ( is_array( $wpml_view_rows ) ? $wpml_view_rows : array() as $job ) : ?>
            <tr style="font-size: 12px;">
                <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_left . $css_border_bottom . $css_vertical_align ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $job, 'lang_pair' ) ); ?></td>
                <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom . $css_text_center . $css_vertical_align ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $job, 'number_of_pages' ) ); ?></td>
                <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom . $css_text_center . $css_vertical_align ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $job, 'number_of_strings' ) ); ?></td>
                <td style="<?php echo wpml_view_esc( $css_border_right . $css_border_bottom . $css_text_center . $css_vertical_align ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $job, 'number_of_words' ) ); ?></td>
            </tr>
        <?php endforeach; ?>

    </table>

<?php endif; ?>

<p><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_improve_quality, 'title' ) ); ?></p>
<ul>
    <?php $wpml_view_options = wpml_view_attr( $wpml_view_improve_quality, 'options' ); foreach ( is_array( $wpml_view_options ) ? $wpml_view_options : array() as $option ) : ?>
        <li><?php echo wpml_view_raw( sprintf( (string) wpml_view_attr( $option, 'text' ), '<a target="_blank" href="' . wpml_view_attr( $option, 'link_url' ) . '">' . wpml_view_attr( $option, 'link_text' ) . '</a>' ) ); ?></li>
    <?php endforeach; ?>
</ul>