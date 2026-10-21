<?php

namespace OTGS\Installer\Templates\Repository;

class EndUsers {
	public static function render( $withProduct, $model ) {
		$withSiteName     = function ( $str ) use ( $model ) {
			$siteUrl = $model->productName === 'WPML' ? 'wpml.org' : 'toolset.com';

			return sprintf( $str, $siteUrl );
		};
		$withProductTwice = function ( $str ) use ( $model ) {
			return sprintf( $str, $model->productName, $model->productName );
		};

		// translators: %s replaced with wpml.org or toolset.com
		$accountQuestion        = $withSiteName( __( 'Do you have an account on %s?', 'installer' ) );
		$extendInfo             = __( 'Great! You just need to extend your subscription.', 'installer' );
		// translators: %s replaced with wpml.org or toolset.com
		$extendAlternative      = $withProduct( __( 'You have a %s account. You just need to extend your subscription.', 'installer' ) );
		$extendButton           = __( 'Extend Subscription', 'installer' );
		// translators: %s replaced with wpml.org or toolset.com
		$discountQuestion       = $withProduct( __( 'OK. You need to set up renewal for %s.', 'installer' ) );
		// translators: %1$s and %2$s replaced with wpml.org or toolset.com
		$discountAlternative    = $withProductTwice( __( 'You do not have a %1$s account yet. You need to set up a renewal for %2$s.', 'installer' ) );
		// translators: %s replaced with wpml.org or toolset.com
		$discountButton         = $withProduct( __( 'Set Up Renewal For %s', 'installer' ) );
		$findAccountInfo        = __( 'No worries. We can check that for you.', 'installer' );
		$findAccountButton      = __( 'Check', 'installer' );
		$findAccountPlaceholder = __( 'Your Email Address', 'installer' );
		$accountUrl = Url::append( $model->productUrl, '/account' );
		$renewalUrl = Url::withHost( $model->endUserRenewalUrl );

		if ( $renewalUrl ): ?>
            <div class="js-question">
                <p><?php echo esc_html( $accountQuestion ); ?></p>
                <a class="js-yes-button button" href="#" role="button">
					<?php esc_html_e( 'Yes', 'installer' ); ?>
                </a>
                <a class="js-no-button button" href="#" role="button">
					<?php esc_html_e( 'No', 'installer' ); ?>
                </a>
                <a class="js-dont-know button" href="#" role="button">
					<?php esc_html_e( "I'm not sure", 'installer' ); ?>
                </a>
            </div>

            <div class="js-yes-section" style="display: none;">
                <p data-alternative="<?php echo esc_html( $extendAlternative ); ?>">
					<?php echo esc_html( $extendInfo ); ?>
                </p>
				<?php self::renderLinkButton( $accountUrl, $extendButton ); ?>
            </div>

            <div class="js-no-section" style="display: none;">
                <p data-alternative="<?php echo esc_html( $discountAlternative ); ?>">
					<?php echo esc_html( $discountQuestion ); ?>
                </p>
				<?php self::renderLinkButton( $renewalUrl . '&token=' . $model->siteKey, $discountButton ); ?>
            </div>

            <div class="js-find-account-section" style="display: none;">
                <p><?php echo esc_html( $findAccountInfo ); ?></p>
                <div class="otgs-installer-notice-status-item-wrapper">
                    <input type="text"
                           placeholder="<?php echo esc_attr( $findAccountPlaceholder ); ?>"/>
                    <a class="js-find-account button btn-disabled"
                       href="#"
                       role="button"
                       data-repository="<?php echo $model->repoId ?>"
                       data-nonce="<?php echo $model->findAccountNonce ?>"
                    >
						<?php echo esc_html( $findAccountButton ); ?>
                    </a>
                </div>
            </div>
		<?php elseif ( $accountUrl ) : ?>
            <div class="js-yes-section">
				<?php self::renderLinkButton( $accountUrl, $extendButton ); ?>
            </div>
		<?php
		endif;
	}

	private static function renderLinkButton( $url, $label ) {
		if ( ! $url ) {
			return;
		}
		?>
        <a class="button button-primary" href="<?php echo esc_url( $url ); ?>">
			<?php echo esc_html( $label ); ?>
        </a>
		<?php
	}
}