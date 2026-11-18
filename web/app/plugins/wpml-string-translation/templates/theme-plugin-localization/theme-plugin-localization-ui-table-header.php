<?php
defined( 'ABSPATH' ) || exit;

$nonces             = wpml_view_attr( $model, 'nonces' );
$endpoints          = wpml_view_attr( $model, 'endpoints' );
$name               = wpml_view_attr( $model, 'name' );
$status_heading     = wpml_view_attr( $model, 'status' );
$textdomain         = wpml_view_attr( $model, 'textdomain' );
$action_heading     = wpml_view_attr( $model, 'action' );
$completed_title    = wpml_view_attr( $model, 'completed_title' );
$needs_update_title = wpml_view_attr( $model, 'needs_update_title' );
?>
<thead
    <?php foreach ( is_array( $nonces ) ? $nonces : array() as $field => $data ) : ?>
        data-<?php echo wpml_view_esc( $field ); ?>-action="<?php echo wpml_view_esc( wpml_view_attr( $data, 'action' ) ); ?>" data-<?php echo wpml_view_esc( $field ); ?>-nonce="<?php echo wpml_view_esc( wpml_view_attr( $data, 'nonce' ) ); ?>"
    <?php endforeach; ?>
    <?php foreach ( is_array( $endpoints ) ? $endpoints : array() as $field => $data ) : ?>
        data-<?php echo wpml_view_esc( $field ); ?>-endpoint="<?php echo wpml_view_esc( wpml_view_attr( $data, 'endpoint' ) ); ?>" data-<?php echo wpml_view_esc( $field ); ?>-nonce="<?php echo wpml_view_esc( wpml_view_attr( $data, 'nonce' ) ); ?>"
    <?php endforeach; ?>
>
    <tr class="table-header">
        <th class="checkbox-column">
            <input class="wpml-checkbox-native" type="checkbox">
        </th>
        <th class="name-column-heading"><?php echo wpml_view_esc( $name ); ?></th>
        <th><?php echo wpml_view_esc( $status_heading ); ?></th>
        <th><?php echo wpml_view_esc( $textdomain ); ?></th>
        <th class="action-column-heading"><?php echo wpml_view_esc( $action_heading ); ?></th>
        <th class="wpml-col-center"><span class="otgs-ico-translated" title="<?php echo wpml_view_esc( $completed_title ); ?>"></span></th>
        <th class="wpml-col-center"><span class="otgs-ico-needs-update" title="<?php echo wpml_view_esc( $needs_update_title ); ?>"></span></th>
    </tr>
</thead>
