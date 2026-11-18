<?php
defined( 'ABSPATH' ) || exit;


$wpml_view_pagination_model = isset( $pagination_model ) ? $pagination_model : null;
$wpml_view_nav_location = isset( $nav_location ) ? $nav_location : null;
?>
<?php if ( wpml_view_attr( $wpml_view_pagination_model, 'total_items' ) ) : ?>

    <h2 class="screen-reader-text"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'strings' ), 'listNavigation' ) ); ?></h2>

    <div class="tablenav-pages clearfix">

        <span class="displaying-num"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'strings' ), 'totalItemsText' ) ); ?></span>

        <?php if ( wpml_view_attr( $wpml_view_pagination_model, 'total_items' ) > wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'pagination' ), 'get_items_per_page' ) ) : ?>

        <span class="pagination-links">

            <?php $wpml_view->partial( 'table-nav-arrow.twig', array( 'url' => wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'pagination' ), 'get_first_page_url' ), 'class' => 'first-page', 'label' => wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'strings' ), 'firstPage' ) ) ); ?>

            <?php $wpml_view->partial( 'table-nav-arrow.twig', array( 'url' => wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'pagination' ), 'get_previous_page_url' ), 'class' => 'previous-page', 'label' => wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'strings' ), 'previousPage' ) ) ); ?>

            <span class="paging-input">
                <?php if ( $wpml_view_nav_location == 'top' ) : ?>
                    <label for="current-page-selector-<?php echo wpml_view_esc( $wpml_view_nav_location ); ?>" class="screen-reader-text"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'strings' ), 'currentPage' ) ); ?></label>
                    <input class="current-page" id="current-page-selector-<?php echo wpml_view_esc( $wpml_view_nav_location ); ?>" type="text" name="paged"
                           value="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'pagination' ), 'get_current_page' ) ); ?>" size="<?php echo wpml_view_esc( wpml_view_length( wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'pagination' ), 'get_total_pages' ) ) ); ?>" aria-describedby="table-paging">
                    <span class="tablenav-paging-text"> <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'strings' ), 'of' ) ); ?> <span class="total-pages"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'pagination' ), 'get_total_pages' ) ); ?></span></span>
                <?php else: ?>
                    <span class="tablenav-paging-text"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'pagination' ), 'get_current_page' ) ); ?> <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'strings' ), 'of' ) ); ?>

                        <span class="total-pages"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'pagination' ), 'get_total_pages' ) ); ?></span>
                    </span>
                <?php endif; ?>
            </span>

            <?php $wpml_view->partial( 'table-nav-arrow.twig', array( 'url' => wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'pagination' ), 'get_next_page_url' ), 'class' => 'next-page', 'label' => wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'strings' ), 'nextPage' ) ) ); ?>


            <?php $wpml_view->partial( 'table-nav-arrow.twig', array( 'url' => wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'pagination' ), 'get_last_page_url' ), 'class' => 'last-page', 'label' => wpml_view_attr( wpml_view_attr( $wpml_view_pagination_model, 'strings' ), 'lastPage' ) ) ); ?>

        </span>

        <?php endif; ?>

    </div>
<?php endif; ?>