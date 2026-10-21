<?php

namespace WPML\Support\TmJobs;

use WPML\TM\Jobs\Log\Hooks;

class View {

	private $logCount;

	public function __construct( int $logCount ) {
		$this->logCount = $logCount;
	}

	public function renderSupportSection() {
		?>
		<div class="wrap">
			<h2 id="tmjobs-log">
				<?php /* translators: Heading of the section about the Translation Management add-on on the Support screen. */ esc_html_e( 'Translation Management', 'sitepress' ); ?>
			</h2>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Hooks::SUBMENU_HANDLE ) ); ?>">
					<?php /* translators: %d: number of log entries. */ ?>
					<?php printf( /* translators: Link that opens the logs of the Translation Management add-on. %d: how many logs there are. */ esc_html__( 'Logs (%d)', 'sitepress' ), (int) $this->logCount ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
