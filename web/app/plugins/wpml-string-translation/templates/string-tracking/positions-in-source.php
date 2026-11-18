<?php
defined( 'ABSPATH' ) || exit;

$positions       = wpml_view_attr( $model, 'positions' );
$highlight_color = wpml_view_attr( $model, 'highlight_color' );
$no_result_label = wpml_view_attr( $model, 'no_result_label' );
$positions_list  = is_array( $positions ) ? $positions : array();
?>
<?php if ( $positions ) : ?><div id="icl_show_source_top">
    <?php foreach ( $positions_list as $key => $position ) : ?>
        <?php $number = $key + 1; ?>
        <a href="#" onclick="icl_show_in_source(<?php echo wpml_view_esc( $key ); ?>,<?php echo wpml_view_esc( wpml_view_attr( $position, 'line' ) ); ?>)"><?php echo wpml_view_esc( $number ); ?></a><br />
    <?php endforeach; ?>
    </div>
    <div id="icl_show_source_wrap">
    <?php foreach ( $positions_list as $key => $position ) : ?>
        <div class="icl_string_track_source" id="icl_string_track_source_<?php echo wpml_view_esc( $key ); ?>"
             <?php echo $key > 0 ? 'style="display:none"' : ''; ?>>

            <?php if ( $key > 0 ) : ?>
                <script type="text/javascript">icl_show_in_source_scroll_once = '<?php echo wpml_view_esc( wpml_view_attr( $position, 'line' ) ); ?>'</script>
            <?php endif; ?>

            <div class="icl_string_track_filename"><?php echo wpml_view_esc( wpml_view_attr( $position, 'path' ) ); ?></div>

            <pre>
                <?php
                $position_lines = wpml_view_attr( $position, 'content' );

                echo '<ol>';

                foreach ( is_array( $position_lines ) ? $position_lines : array() as $line_number => $line ) {
                    echo '<li id="icl_source_line_' . wpml_view_esc( $key ) . '_' . wpml_view_esc( $line_number ) . '"';

                    if ( 0 === ( $line_number <=> ( wpml_view_attr( $position, 'line' ) - 1 ) ) ) {
                        echo 'style="background-color:' . wpml_view_esc( $highlight_color ) . '"';
                    }

                    echo '>' . wpml_view_esc( $line ) . '&nbsp;</li>';
                }

                echo '</ol>';
                ?>

            </pre>
        </div>
    </div>
    <?php endforeach; ?>
<?php else : ?>
    <?php echo wpml_view_esc( $no_result_label ); ?>

<?php endif; ?>
