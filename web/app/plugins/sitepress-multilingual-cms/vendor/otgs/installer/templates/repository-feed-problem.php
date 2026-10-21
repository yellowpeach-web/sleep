<?php

namespace OTGS\Installer\Templates\Repository;

class FeedProblem {

	public static function render( $model, $problem, $check_again_url ) {
		if ( ! is_array( $problem ) || empty( $problem['kind'] ) ) {
			return;
		}

		$host = parse_url( $model->productUrl, PHP_URL_HOST );
		$host = $host ? $host : $model->productUrl;

		if ( \OTGS_Installer_Products_Feed_Problem::KIND_CONNECTION === $problem['kind'] ) {
			/* translators: 1: the product's server, for example wpml.org; 2: the product name, for example WPML */
			$text = sprintf( __( 'Installer could not connect to %1$s to read the latest list of %2$s products.', 'installer' ), $host, $model->productName );
		} else {
			/* translators: 1: the product's server, for example wpml.org; 2: the product name, for example WPML */
			$text = sprintf( __( 'Installer could not read the latest list of %2$s products from %1$s.', 'installer' ), $host, $model->productName );
		}
		?>
		<tr class="otgs-installer-feed-problem">
			<td colspan="2">
				<div class="notice inline notice-warning">
					<p><?php echo esc_html( $text ); ?></p>
					<p>
						<a class="button-secondary" href="<?php echo esc_url( $check_again_url ); ?>"><?php echo esc_html( __( 'Check again', 'installer' ) ); ?></a>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}
}
