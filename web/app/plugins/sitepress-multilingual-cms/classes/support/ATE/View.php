<?php

namespace WPML\Support\ATE;

use WPML\TM\ATE\ClonedSites\SecondaryDomains;
use WPML\TM\ATE\Log\Hooks;

class View {

	private $logCount;

	private $secondaryDomains;

	public function __construct( int $logCount, SecondaryDomains $secondaryDomains ) {
		$this->logCount         = $logCount;
		$this->secondaryDomains = $secondaryDomains;
	}

	public function renderSupportSection() {
		?>
		<div class="wrap">
			<h2 id="ate-log">
				<?php esc_html_e( 'Advanced Translation Editor', 'sitepress' ); ?>
			</h2>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Hooks::SUBMENU_HANDLE ) ); ?>">
					<?php /* translators: %d: number of log entries. */ ?>
					<?php printf( esc_html__( 'Error Logs (%d)', 'sitepress' ), (int) $this->logCount ); ?>
				</a>
			</p>
			<?php
			$secondaryDomains = $this->secondaryDomains->getInfo();
			if ( $secondaryDomains ) {
				?>
				<div id="wpml-support-ate-alias-domains">
					<strong>
						<?php /* translators: Label in front of the list of other addresses the site answers on. %s: the main address of the site. */ ?>
						<?php printf( __( 'Alias domains to %s domain:', 'sitepress' ), $secondaryDomains['originalSiteUrl'] ) ?>
					</strong>
					<ul style="list-style: square; padding-left: 15px;">
						<?php foreach ( $secondaryDomains['aliasDomains'] as $aliasDomain ) { ?>
							<li>
								<?php echo $aliasDomain ?>
							</li>
						<?php } ?>
					</ul>
				</div>
				<?php
			}
			?>
		</div>
		<?php
	}
}
