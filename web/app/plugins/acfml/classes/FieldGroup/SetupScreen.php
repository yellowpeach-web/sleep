<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\FieldGroup;
use ACFML\Notice\Links;
use ACFML\Tools\AdminUrl;

class SetupScreen implements \IWPML_Backend_Action {

	const PAGE_SLUG   = 'acfml-translation-setup';
	const PARENT_SLUG = 'edit.php?post_type=acf-field-group';

	const MENU_PRIORITY = 20;

	const FALLBACK_CAPABILITY = 'manage_options';

	const CONFIRM_INPUT = 'acfml_confirm';
	const CONFIRM_APPLY = 'apply';

	const APPLIED_INPUT = 'acfml_applied';

	public function add_hooks() {
		add_action( 'admin_menu', [ $this, 'registerPage' ], self::MENU_PRIORITY );
	}

	public function registerPage() {
		add_submenu_page(
			self::PARENT_SLUG,
			/* translators: Title of the screen that lists every field group and its translation setup, used as the browser page title and as the heading. */
			__( 'ACF translation setup', 'acfml' ),
			/* translators: Menu item under WPML that opens the ACF translation setup screen. */
			__( 'Translation setup', 'acfml' ),
			self::getCapability(),
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	public static function getUrl( array $args = [] ) {
		return add_query_arg(
			array_merge(
				[
					'post_type' => FieldGroup::CPT,
					'page'      => self::PAGE_SLUG,
				],
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	public static function getCapability() {
		if ( ! function_exists( 'acf_get_setting' ) ) {
			return self::FALLBACK_CAPABILITY;
		}

		$capability = acf_get_setting( 'capability' );

		return is_string( $capability ) && '' !== $capability ? $capability : self::FALLBACK_CAPABILITY;
	}

	public function render() {
		if ( ! current_user_can( self::getCapability() ) ) {
			$this->refuse();

			return;
		}

		$rows = SetupInventory::get();

		if ( self::CONFIRM_APPLY === self::getRequestedValue( self::CONFIRM_INPUT ) ) {
			$this->renderConfirmation( SetupInventory::countBulkTargets( $rows ) );

			return;
		}

		$this->renderTable( $rows );
	}

	protected function refuse() {
		wp_die(
			/* translators: Error page shown when the logged-in user may not open the ACF translation setup screen. */
			esc_html__( 'You are not allowed to review the translation setup of ACF field groups.', 'acfml' ),
			'',
			[ 'response' => 403 ]
		);
	}

	private function renderTable( array $rows ) {
		$unconfigured = SetupInventory::countUnconfigured( $rows );
		$bulkTargets  = SetupInventory::countBulkTargets( $rows );
		$localPending = SetupInventory::countUnconfiguredLocal( $rows );
		$syncPending  = SetupInventory::countUnconfiguredPendingSync( $rows );
		$applied      = (int) self::getRequestedValue( self::APPLIED_INPUT );
		?>
		<div class="wrap acfml-setup">
			<h1><?php echo /* translators: Title of the screen that lists every field group and its translation setup, used as the browser page title and as the heading. */ esc_html__( 'ACF translation setup', 'acfml' ); ?></h1>
			<?php $this->renderStyles(); ?>

			<?php if ( $applied > 0 ) : ?>
				<div class="notice notice-success">
					<p><?php echo esc_html( self::getAppliedNotice( $applied ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php $summary = self::getSummary( count( $rows ), $unconfigured ); ?>
			<?php if ( '' !== $summary ) : ?>
				<p class="acfml-setup__summary"><?php echo esc_html( $summary ); ?></p>
			<?php endif; ?>

			<?php if ( $localPending > 0 ) : ?>
				<p class="acfml-setup__note"><?php echo esc_html( self::getLocalPendingNote( $localPending ) ); ?></p>
			<?php endif; ?>

			<?php if ( $syncPending > 0 ) : ?>
				<p class="acfml-setup__note"><?php echo esc_html( self::getSyncPendingNote( $syncPending ) ); ?></p>
			<?php endif; ?>

			<?php if ( $bulkTargets > 0 ) : ?>
				<p class="acfml-setup__bulk">
					<a class="button button-primary" href="<?php echo esc_url( self::getUrl( [ self::CONFIRM_INPUT => self::CONFIRM_APPLY ] ) ); ?>">
						<?php echo esc_html( self::getBulkLabel( $bulkTargets ) ); ?>
					</a>
				</p>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped acfml-setup__table">
				<thead>
				<tr>
					<th scope="col"><?php echo /* translators: Column heading naming the field group of a row, on the translation setup screen. Noun, singular. */ esc_html__( 'Field group', 'acfml' ); ?></th>
					<th scope="col"><?php echo /* translators: Column heading for how a field group's translation is set up, on the translation setup screen. Noun. */ esc_html__( 'Setup', 'acfml' ); ?></th>
					<th scope="col" class="acfml-setup__count"><?php echo /* translators: Label of the count of ACF fields on the setup notice, and the column heading for that count on the translation setup screen. Noun, plural. */ esc_html__( 'Fields', 'acfml' ); ?></th>
					<th scope="col" class="acfml-setup__actions"><span class="screen-reader-text"><?php echo /* translators: Accessible label (screen readers) of the column holding the links of each row on the translation setup screen. Noun, plural. */ esc_html__( 'Actions', 'acfml' ); ?></span></th>
				</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr>
						<td colspan="4"><?php echo /* translators: Shown in place of the table on the translation setup screen when no field group exists. */ esc_html__( 'This site has no ACF field groups yet.', 'acfml' ); ?></td>
					</tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php $this->renderRow( $row ); ?>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function renderRow( array $row ) {
		$editLink = SetupInventory::isReadOnlyRow( $row ) ? '' : (string) get_edit_post_link( $row['id'] );
		?>
		<tr>
			<td>
				<?php if ( $editLink ) : ?>
					<a href="<?php echo esc_url( $editLink ); ?>"><strong><?php echo esc_html( $row['title'] ); ?></strong></a>
				<?php else : ?>
					<strong><?php echo esc_html( $row['title'] ); ?></strong>
				<?php endif; ?>
				<?php if ( ! empty( $row['source'] ) ) : ?>
					<span class="acfml-setup__note"><?php echo esc_html( self::getSourceLabel( $row['source'] ) ); ?></span>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( SetupInventory::SETUP_UNCONFIGURED === $row['setupKind'] ) : ?>
					<span class="acfml-setup__unconfigured"><?php echo esc_html( self::getSetupLabel( $row ) ); ?></span>
				<?php else : ?>
					<?php echo esc_html( self::getSetupLabel( $row ) ); ?>
				<?php endif; ?>
				<?php if ( $row['manualCount'] > 0 ) : ?>
					<span class="acfml-setup__note"><?php echo esc_html( self::getManualLabel( $row['manualCount'] ) ); ?></span>
				<?php endif; ?>
				<?php if ( $row['needsCodePreferences'] ) : ?>
					<span class="acfml-setup__alert">
						<span aria-hidden="true">&#9888;</span>
						<span class="screen-reader-text"><?php echo /* translators: Text read out by screen readers in place of the warning triangle, before the warning that follows it. Keep the colon. */ esc_html__( 'Alert:', 'acfml' ); ?></span>
						<?php echo wp_kses( self::getCodePreferencesGuidance(), [ 'code' => [] ] ); ?>
						<a href="<?php echo esc_url( Links::getAcfmlMainDoc() ); ?>" target="_blank" rel="noopener noreferrer"><?php echo /* translators: Link at the end of a warning on the translation setup screen; it opens the ACFML documentation. "That" is what the warning asks for. */ esc_html__( 'How to do that', 'acfml' ); ?></a>
					</span>
				<?php endif; ?>
			</td>
			<td class="acfml-setup__count"><?php echo esc_html( (string) $row['fieldCount'] ); ?></td>
			<td class="acfml-setup__actions">
				<?php if ( $editLink ) : ?>
					<a href="<?php echo esc_url( $editLink ); ?>"><?php echo /* translators: Link in a row of the translation setup screen; it opens that field group in ACF. Verb phrase, imperative. */ esc_html__( 'View fields', 'acfml' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $row['isOptionsPage'] ) ) : ?>
					<a href="<?php echo esc_url( AdminUrl::getTranslationDashboard() ); ?>"><?php echo /* translators: Link in a row of the translation setup screen; it opens WPML's Translation Dashboard. Verb phrase, imperative. */ esc_html__( 'Open Dashboard', 'acfml' ); ?></a>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function renderConfirmation( $bulkTargets ) {
		?>
		<div class="wrap acfml-setup">
			<h1><?php echo /* translators: Heading and button of the screen that gives every field the translation preference WPML recommends. Verb phrase, imperative. */ esc_html__( 'Apply the recommended setup', 'acfml' ); ?></h1>
			<?php $this->renderStyles(); ?>

			<?php if ( $bulkTargets < 1 ) : ?>
				<p><?php echo /* translators: Shown on the confirmation screen when no field group is left to set up. */ esc_html__( 'Every field group stored in this site’s database already has a translation setup.', 'acfml' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( self::getUrl() ); ?>"><?php echo /* translators: Button that returns to the translation setup screen. Verb phrase, imperative. */ esc_html__( 'Back to the field groups', 'acfml' ); ?></a></p>
			<?php else : ?>
				<div class="notice notice-warning inline">
					<p><strong><?php echo esc_html( self::getConfirmationLead( $bulkTargets ) ); ?></strong></p>
					<p><?php echo /* translators: Warning on the screen that confirms applying the recommended setup; "those groups" are the field groups counted in the line above. */ esc_html__( 'Every field in those groups then gets the translation preference WPML recommends for its field type. A preference that was set by hand in one of them is replaced.', 'acfml' ); ?></p>
					<p><?php echo /* translators: Second warning on the screen that confirms applying the recommended setup. Keep "acf-json" in English: it is a folder name. */ esc_html__( 'Field groups that come from your theme, a plugin’s code, or an acf-json file are not changed. They cannot be saved from here.', 'acfml' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( PreflightHooks::APPLY_ACTION ); ?>"/>
					<input type="hidden" name="<?php echo esc_attr( PreflightHooks::RETURN_INPUT ); ?>" value="<?php echo esc_attr( PreflightHooks::RETURN_SETUP_SCREEN ); ?>"/>
					<?php wp_nonce_field( PreflightHooks::APPLY_NONCE, PreflightHooks::APPLY_NONCE ); ?>
					<p>
						<button type="submit" class="button button-primary"><?php echo /* translators: Heading and button of the screen that gives every field the translation preference WPML recommends. Verb phrase, imperative. */ esc_html__( 'Apply the recommended setup', 'acfml' ); ?></button>
						<a class="button" href="<?php echo esc_url( self::getUrl() ); ?>"><?php echo /* translators: Button that leaves the confirmation screen without applying the recommended setup. Verb, imperative: the action, not the state "cancelled". */ esc_html__( 'Cancel', 'acfml' ); ?></a>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private function renderStyles() {
		?>
		<style>
			.acfml-setup__summary { margin: 8px 0 4px; }
			.acfml-setup__note { color: #646970; display: block; margin-top: 2px; }
			.acfml-setup__unconfigured { color: #b32d2e; font-weight: 600; }
			.acfml-setup__alert { color: #8a6100; display: block; margin-top: 6px; }
			.acfml-setup__table .acfml-setup__count { text-align: right; width: 6em; }
			.acfml-setup__table .acfml-setup__actions { width: 16em; }
			.acfml-setup__actions a + a { margin-left: 12px; }
		</style>
		<?php
	}

	public static function getSummary( $total, $unconfigured ) {
		if ( $total < 1 ) {
			return '';
		}

		$groups = sprintf(
			/* translators: %d is the number of ACF field groups on the site. */
			_n( '%d field group.', '%d field groups.', $total, 'acfml' ),
			$total
		);

		if ( $unconfigured < 1 ) {
			/* translators: Second half of the summary line on the translation setup screen; the first half counts the field groups, and "them" are those groups. */
			return $groups . ' ' . __( 'All of them have a translation setup.', 'acfml' );
		}

		$pending = sprintf(
			/* translators: %d is the number of ACF field groups with no translation setup. */
			_n( '%d is not configured.', '%d are not configured.', $unconfigured, 'acfml' ),
			$unconfigured
		);

		return $groups . ' ' . $pending;
	}

	public static function getBulkLabel( $bulkTargets ) {
		return sprintf(
			/* translators: %d is the number of ACF field groups the action would configure. */
			_n(
				'Apply recommended setup to the %d unconfigured group',
				'Apply recommended setup to all %d unconfigured groups',
				$bulkTargets,
				'acfml'
			),
			$bulkTargets
		);
	}

	public static function getConfirmationLead( $bulkTargets ) {
		return sprintf(
			/* translators: %1$d is a number of field groups, %2$s the name of the recommended setup. */
			_n(
				'This sets %1$d field group to “%2$s”.',
				'This sets %1$d field groups to “%2$s”.',
				$bulkTargets,
				'acfml'
			),
			$bulkTargets,
			Mode::getLabel( Mode::TRANSLATION )
		);
	}

	public static function getLocalPendingNote( $localPending ) {
		return sprintf(
			/* translators: %d is the number of code defined field groups with no translation setup. */
			_n(
				'%d field group is defined in code, so it cannot be configured from here.',
				'%d field groups are defined in code, so they cannot be configured from here.',
				$localPending,
				'acfml'
			),
			$localPending
		);
	}

	public static function getSetupLabel( array $row ) {
		switch ( $row['setupKind'] ) {
			case SetupInventory::SETUP_UNCONFIGURED:
				return empty( $row['isLocal'] )
					/* translators: Value in the Setup column of the translation setup screen, for a field group with no translation option chosen yet. */
					? __( 'Not configured', 'acfml' )
					/* translators: Value in the Setup column of the translation setup screen, for a field group with no translation option chosen that a theme or plugin defines in code. */
					: __( 'Not configured (defined in code)', 'acfml' );
			case SetupInventory::SETUP_CODE_PREFERENCES:
				/* translators: Value in the Setup column of the translation setup screen, for a field group defined in code whose fields carry their own translation preferences. */
				return __( 'Set for each field (defined in code)', 'acfml' );
			case SetupInventory::SETUP_WPML_SETTINGS:
				/* translators: Value in the Setup column of the translation setup screen, for a field group defined in code whose fields are set on WPML > Settings > Custom Fields Translation. */
				return __( 'Set for each field in WPML Settings (defined in code)', 'acfml' );
			default:
				return Mode::getLabel( $row['mode'] );
		}
	}

	public static function getManualLabel( $manualCount ) {
		return sprintf(
			/* translators: %d is the number of fields whose preference differs from the group's own setup. */
			_n( '%d field set manually', '%d fields set manually', $manualCount, 'acfml' ),
			$manualCount
		);
	}

	public static function getCodePreferencesGuidance() {
		return sprintf(
			/* translators: %s is the name of an ACF field setting, shown as code. */
			esc_html__( 'Nothing decides how these fields are translated. Set %s on the fields in the code that registers this group.', 'acfml' ),
			'<code>wpml_cf_preferences</code>'
		);
	}

	public static function getSourceLabel( $source ) {
		switch ( $source ) {
			case SetupInventory::SOURCE_JSON:
				/* translators: Second half of a sentence naming where a field group comes from; it follows the group's name. Keep "acf-json" in English: it is a folder name. */
				return __( 'from an acf-json file', 'acfml' );
			case SetupInventory::SOURCE_JSON_NEWER:
				/* translators: Second half of a sentence naming where a field group comes from; it follows the group's name. Keep "acf-json" in English: it is a folder name. */
				return __( 'from an acf-json file that is newer than this site’s copy', 'acfml' );
			default:
				/* translators: Second half of a sentence naming where a field group comes from; it follows the group's name. */
				return __( 'from your theme or a plugin’s code', 'acfml' );
		}
	}

	public static function getAppliedNotice( $applied ) {
		return sprintf(
			/* translators: %d is the number of ACF field groups that were just configured. */
			_n(
				'%d field group now uses the recommended setup.',
				'%d field groups now use the recommended setup.',
				$applied,
				'acfml'
			),
			$applied
		);
	}

	public static function getSyncPendingNote( $syncPending ) {
		return sprintf(
			/* translators: %d is the number of field groups whose acf-json file is newer than the database copy. */
			_n(
				'%d field group has a newer acf-json file waiting to be synced, so that file decides its setup.',
				'%d field groups have a newer acf-json file waiting to be synced, so those files decide their setup.',
				$syncPending,
				'acfml'
			),
			$syncPending
		);
	}

	private static function getRequestedValue( $key ) {
		return isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : '';
	}
}
