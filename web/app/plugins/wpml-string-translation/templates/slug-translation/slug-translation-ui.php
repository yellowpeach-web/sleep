<?php
defined( 'ABSPATH' ) || exit;

$strings           = wpml_view_attr( $model, 'strings' );
$css_class_wrapper = wpml_view_attr( $model, 'css_class_wrapper' );
$slugs             = wpml_view_attr( $model, 'slugs' );
$archive           = wpml_view_attr( $model, 'archive' );
$archive_strings   = wpml_view_attr( $archive, 'strings' );
$archive_slugs     = wpml_view_attr( $archive, 'slugs' );
?>
<div class="icl_slug_translation_choice <?php echo wpml_view_esc( $css_class_wrapper ); ?>">
    <p>
        <a href="#" class="js-toggle-slugs-table"><?php echo wpml_view_esc( wpml_view_attr( $strings, 'toggle_slugs_table' ) ); ?><span class="otgs-ico-caret-down"></span></a>
    </p>

    <table class="js-custom-type-slugs" style="display:none;">
    <?php if ( $archive ) : ?>
        <tr><th colspan="2" scope="colgroup"><?php echo wpml_view_esc( wpml_view_attr( $archive_strings, 'slug_heading' ) ); ?></th></tr>
    <?php endif; ?>
    <?php foreach ( is_array( $slugs ) ? $slugs : array() as $slug ) : ?>

        <tr<?php echo wpml_view_attr( $slug, 'is_original' ) ? ' style="display:none;"' : ''; ?>>
        <td>
            <label for="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'input_id' ) ); ?>">
                <?php echo wpml_view_raw( wpml_view_attr( $slug, 'language_flag' ) ); ?> <?php echo wpml_view_esc( wpml_view_attr( $slug, 'language_name' ) ); ?>

            </label>
        </td>
        <td>
            <input id="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'input_id' ) ); ?>" class="js-translate-slug" name="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'input_id' ) ); ?>"
                   type="text" value="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'value' ) ); ?>" placeholder="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'placeholder' ) ); ?>"
                   data-lang="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'language_code' ) ); ?>" />

            <?php if ( wpml_view_attr( $slug, 'status_is_incomplete' ) ) : ?>
                <em class="icl_st_slug_tr_warn"><?php echo wpml_view_esc( wpml_view_attr( $strings, 'slug_status_incomplete' ) ); ?></em>
            <?php endif; ?>
        </td>
        </tr>

        <?php if ( wpml_view_attr( $slug, 'is_original' ) ) : ?>
            <tr>
                <td>
                    <?php echo wpml_view_raw( wpml_view_attr( $slug, 'language_selector' ) ); ?>

                    <label for="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'input_id' ) ); ?>">
                        <em><?php echo wpml_view_esc( wpml_view_attr( $strings, 'original_label' ) ); ?></em>
                    </label>
                </td>
                <td>
                    <input disabled="disabled" class="disabled" id="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'input_id' ) ); ?>"
                           name="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'input_id' ) ); ?>" type="text" value="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'value' ) ); ?>"/>
                </td>
            </tr>
        <?php endif; ?>

    <?php endforeach; ?>

    <?php if ( $archive ) : ?>
        <tr><th colspan="2" scope="colgroup"><?php echo wpml_view_esc( wpml_view_attr( $archive_strings, 'archive_heading' ) ); ?></th></tr>
        <?php foreach ( is_array( $archive_slugs ) ? $archive_slugs : array() as $slug ) : ?>
            <?php if ( wpml_view_attr( $slug, 'is_original' ) ) : ?>
                <tr class="js-archive-slug-original">
                    <td>
                        <?php echo wpml_view_raw( wpml_view_attr( $slug, 'language_flag' ) ); ?> <?php echo wpml_view_esc( wpml_view_attr( $slug, 'language_name' ) ); ?> <em><?php echo wpml_view_esc( wpml_view_attr( $strings, 'original_label' ) ); ?></em>
                    </td>
                    <td>
                        <input disabled="disabled" class="disabled" type="text" value="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'value' ) ); ?>"
                               aria-label="<?php echo wpml_view_esc( wpml_view_attr( $archive_strings, 'archive_heading' ) ); ?> <?php echo wpml_view_esc( wpml_view_attr( $strings, 'original_label' ) ); ?>"/>
                    </td>
                </tr>
            <?php endif; ?>
            <tr<?php echo wpml_view_attr( $slug, 'is_original' ) ? ' style="display:none;"' : ''; ?>>
                <td>
                    <label for="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'input_id' ) ); ?>">
                        <?php echo wpml_view_raw( wpml_view_attr( $slug, 'language_flag' ) ); ?> <?php echo wpml_view_esc( wpml_view_attr( $slug, 'language_name' ) ); ?>

                    </label>
                </td>
                <td>
                    <input id="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'input_id' ) ); ?>" class="js-translate-slug" name="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'input_id' ) ); ?>"
                           type="text" value="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'value' ) ); ?>" placeholder="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'placeholder' ) ); ?>"
                           data-lang="<?php echo wpml_view_esc( wpml_view_attr( $slug, 'language_code' ) ); ?>" />

                    <?php if ( wpml_view_attr( $slug, 'status_is_incomplete' ) ) : ?>
                        <em class="icl_st_slug_tr_warn"><?php echo wpml_view_esc( wpml_view_attr( $strings, 'slug_status_incomplete' ) ); ?></em>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>

    </table>
</div><?php  ?>
