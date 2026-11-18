<?php
defined( 'ABSPATH' ) || exit;

$section_type      = wpml_view_attr( $model, 'type' );
$dir_and_file      = wpml_view_attr( $model, 'dir_and_file' );
$component_data    = wpml_view_attr( $model, 'component_data' );
$show_active_label = wpml_view_attr( $model, 'show_active_label' );
$active_text       = wpml_view_attr( $model, 'active_text' );
$show_textdomains  = wpml_view_attr( $model, 'show_textdomains' );
$hide_textdomains  = wpml_view_attr( $model, 'hide_textdomains' );
$download_po       = wpml_view_attr( $model, 'download_po' );
$is_active         = wpml_view_attr( $component_data, 'active' );
$domains           = wpml_view_attr( $component_data, 'domains' );
$domains_list      = is_array( $domains ) ? $domains : array();

$component_name = strtr( wpml_view_string( wpml_view_attr( $component_data, 'component_name' ) ), ' ', '-' );
$component_name = function_exists( 'mb_get_info' ) ? mb_strtolower( $component_name, 'UTF-8' ) : strtolower( $component_name );

$show_domains_list = true;
?>
<?php if ( count( $domains_list ) < 2 ) : ?>
    <?php $show_domains_list = false; ?>
<?php endif; ?>
<tr
    class="item<?php echo wpml_view_esc( $is_active ? ' active' : '' ); ?>"
    data-itemcategory="<?php echo wpml_view_esc( $section_type ); ?>"
    data-themepluginitem="1"
    data-componentidformoscan="<?php echo wpml_view_esc( wpml_view_attr( $component_data, 'component_id_for_mo_scan' ) ); ?>"
    data-isactive="<?php echo $is_active ? '1' : '0'; ?>"
    <?php echo ! $is_active ? 'style="display: none"' : ''; ?>
>
    <td class="checkbox-column">
        <input class="wpml-checkbox-native" type="checkbox" name="<?php echo wpml_view_esc( $section_type ); ?>[]" id="<?php echo wpml_view_esc( $section_type ); ?>-<?php echo wpml_view_esc( wpml_view_attr( $component_data, 'id' ) ); ?>" value="<?php echo wpml_view_esc( $dir_and_file ); ?>" data-component-name="<?php echo wpml_view_esc( $component_name ); ?>">
    </td>
    <td class="name-cell">
        <label for="<?php echo wpml_view_esc( $section_type ); ?>-<?php echo wpml_view_esc( wpml_view_attr( $component_data, 'id' ) ); ?>"><?php echo $show_active_label && $is_active ? '<span class="bold-label">' . wpml_view_esc( $active_text ) . ':</span> ' : ''; ?><?php echo wpml_view_esc( wpml_view_attr( $component_data, 'component_name' ) ); ?></label>
    </td>
    <td class="icon-cell">
        <?php $icon_class = ''; ?>
        <?php $icon_status = 'not-scanned'; ?>
        <?php if ( wpml_view_attr( $component_data, 'needs_rescan' ) ) : ?>
            <?php $icon_class = ' warning-icon'; ?>
            <?php $icon_status = 'needs-update'; ?>
        <?php elseif ( wpml_view_attr( $component_data, 'completed' ) ) : ?>
            <?php $icon_class = ' checked-icon'; ?>
            <?php $icon_status = 'completed'; ?>
        <?php endif; ?>
        <div
            class="js-otgs-popover-tooltip icon<?php echo wpml_view_esc( $icon_class ); ?>"
            title="<?php echo wpml_view_esc( wpml_view_attr( $component_data, 'statusIconTitle' ) ); ?>"
            data-status="<?php echo wpml_view_esc( $icon_status ); ?>"
        >
        </div>
    </td>
    <td class="domain-label-cell">
        <?php if ( $domains ) : ?>
            <ul>
                <?php if ( $show_domains_list ) : ?>
                    <li class="toggle-list-items">
                        <a href="#" data-showtext="<?php echo wpml_view_esc( $show_textdomains ); ?>" data-hidetext="<?php echo wpml_view_esc( $hide_textdomains ); ?>">
                            <?php echo wpml_view_esc( $show_textdomains ); ?>

                        </a>
                    </li>
                <?php endif; ?>
                <?php foreach ( $domains_list as $domain_name => $domains_statistics ) : ?>
                    <li<?php echo $show_domains_list ? ' data-domainListItem="1" style="display: none"' : ''; ?>><?php if ( wpml_view_attr( $domains_statistics, 'domain_link' ) ) : ?><a title="<?php echo wpml_view_esc( wpml_view_attr( $domains_statistics, 'title_all_strings' ) ); ?>" href="<?php echo wpml_view_esc( wpml_view_attr( $domains_statistics, 'domain_link' ) ); ?>"><?php echo wpml_view_esc( $domain_name ); ?></a><?php else : ?><?php echo wpml_view_esc( $domain_name ); ?><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </td>

    <td class="wpml-create-po-file domain-info-cell action-column-cell">
        <?php if ( $domains ) : ?>
            <ul class="icon-list"<?php echo $show_domains_list ? ' data-domainListItem="1" style="display: none"' : ''; ?>>
                <?php if ( $show_domains_list ) : ?>
                    <li class="spacerListItem">-</li>
                <?php endif; ?>

                <?php foreach ( $domains_list as $domain_name => $domains_statistics ) : ?>
                    <li>
                        <?php if ( wpml_view_attr( $domains_statistics, 'translated' ) || wpml_view_attr( $domains_statistics, 'needs_update' ) ) : ?>
                            <a class="wpml_st_pop_download icon download-icon js-otgs-popover-tooltip"
                               data-file="<?php echo wpml_view_esc( $dir_and_file ); ?>" data-domain="<?php echo wpml_view_esc( $domain_name ); ?>"
                               href="#"
                               title="<?php echo wpml_view_esc( $download_po ); ?>"
                               ></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </td>

    <td class="wpml-translated domain-info-cell">
        <?php if ( $domains ) : ?>
            <ul<?php echo $show_domains_list ? ' data-domainListItem="1" style="display: none"' : ''; ?>>
                <?php if ( $show_domains_list ) : ?>
                    <li class="spacerListItem">-</li>
                <?php endif; ?>

                <?php foreach ( $domains_list as $domain_name => $domains_statistics ) : ?>
                    <li>
                        <?php echo wpml_view_esc( wpml_view_attr( $domains_statistics, 'translated' ) ); ?>

                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </td>

    <td class="wpml-needs-update domain-info-cell">
        <?php if ( $domains ) : ?>
        <ul<?php echo $show_domains_list ? ' data-domainListItem="1" style="display: none"' : ''; ?>>
            <?php if ( $show_domains_list ) : ?>
                <li class="spacerListItem">-</li>
            <?php endif; ?>

            <?php foreach ( $domains_list as $domain_name => $domains_statistics ) : ?>
                <li>
                    <?php if ( wpml_view_attr( $domains_statistics, 'needs_update' ) ) : ?>
                    <a title="<?php echo wpml_view_esc( wpml_view_attr( $domains_statistics, 'title_needs_translation' ) ); ?>" href="<?php echo wpml_view_esc( wpml_view_attr( $domains_statistics, 'needs_update_link' ) ); ?>">
                        <?php endif; ?>
                        <?php echo wpml_view_esc( wpml_view_attr( $domains_statistics, 'needs_update' ) ); ?>

                        <?php if ( wpml_view_attr( $domains_statistics, 'needs_update' ) ) : ?>
                    </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </td>

    <?php   ?>
</tr>
