<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_from = isset( $from ) ? $from : null;
$wpml_view_to = isset( $to ) ? $to : null;
$wpml_view_total_items = isset( $total_items ) ? $total_items : null;
$wpml_view_current_page = isset( $current_page ) ? $current_page : null;
$wpml_view_total_pages = isset( $total_pages ) ? $total_pages : null;
$wpml_view_paginate_links = isset( $paginate_links ) ? $paginate_links : null;
$wpml_view_select = isset( $select ) ? $select : null;
$wpml_view_select_value = isset( $select_value ) ? $select_value : null;
?>
<div class="wpml_tm_pagination_container wpml-pagination">
    <div class="otgs-notice otgs-notice__errors error inline hidden">
        <p></p>
    </div>

    <div class="tablenav">
        <div class="tablenav-pages">
            <span class="spinner"></span>

            <span class="displaying-num">
                <?php if ( $wpml_view_total_items > 0 ) : ?>
                    <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'displaying' ) ); ?> <?php echo wpml_view_esc( $wpml_view_from ); ?>–<?php echo wpml_view_esc( $wpml_view_to ); ?>

                <?php else: ?>
                    <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'nothing_found' ) ); ?>

                <?php endif; ?>
            </span>

            <?php if ( $wpml_view_current_page != -1 ) : ?>
                <?php if ( $wpml_view_paginate_links ) : ?>
                    <?php if ( $wpml_view_current_page > 1 ) : ?>
                        <a class="prev page-numbers" href="#">«</a>
                    <?php endif; ?>

                    <?php foreach ( is_array( $wpml_view_paginate_links ) ? $wpml_view_paginate_links : array() as $wpml_view_link ) : ?>
                        <?php if ( wpml_view_attr( $wpml_view_link, 'class' ) == 'current' ) : ?>
                            <span aria-current="page" class="page-numbers current"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_link, 'number' ) ); ?></span>
                        <?php endif; ?>
                        <?php if ( wpml_view_attr( $wpml_view_link, 'class' ) == '' ) : ?>
                            <a class="page-numbers" href="#"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_link, 'number' ) ); ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ( $wpml_view_total_items > $wpml_view_to ) : ?>
                        <span class="page-numbers dots">&#8230;</span>
                    <?php endif; ?>

                    <?php if ( $wpml_view_current_page < $wpml_view_total_pages ) : ?>
                        <a class="next page-numbers" href="#">»</a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>


            <?php if ( $wpml_view_total_items > wpml_view_attr( $wpml_view_select, 0 ) ) : ?>
                <select name="wpml_tm_per_page">
                    <?php foreach ( is_array( $wpml_view_select ) ? $wpml_view_select : array() as $wpml_view_option ) : ?>
                        <?php $wpml_view_selected = ''; ?>
                        <?php if ( $wpml_view_option == $wpml_view_select_value ) : ?>
                            <?php $wpml_view_selected = 'selected'; ?>
                        <?php endif; ?>
                        <option value="<?php echo wpml_view_esc( $wpml_view_option ); ?>" <?php echo wpml_view_esc( $wpml_view_selected ); ?>><?php echo wpml_view_esc( $wpml_view_option ); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php if ( $wpml_view_current_page != -1 && $wpml_view_total_items > $wpml_view_select_value ) : ?>
                <a class="display-all" href="#"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'display_all' ) ); ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>
