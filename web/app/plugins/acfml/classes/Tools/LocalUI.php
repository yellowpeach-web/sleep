<?php

namespace ACFML\Tools;

use ACFML\Helper\BoldNames;
use ACFML\Strings\Factory;
use ACFML\Strings\Translator;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Relation;

class LocalUI {

	const NONCE = 'nonce_acfml_tools_local_settings';

	const POST_SCAN_MODE             = 'acfml-scan-mode';
	const POST_REGISTER_LOCAL_LABELS = 'acfml-register-local-labels';

	const SCAN_MODE_NONE   = 'none';
	const SCAN_MODE_ONCE   = 'once';
	const SCAN_MODE_ALWAYS = 'always';

	const REGISTER_LOCAL_LABELS_NONE = 'none';
	const REGISTER_LOCAL_LABELS_ONCE = 'once';

	public $name = 'acfml-local-settings';

	public $title = '';

	public function __construct() {
		/* translators: Title of the WPML tool that registers fields defined outside the database. "Local JSON" is ACF's own name for that storage. Verb phrase, imperative. */
		$this->title = __( 'Translate ACF Local JSON and PHP-Registered Fields', 'acfml' );
	}

	public function initialize() {
	}

	public function load() {
	}

	public function html() {
		?>
		<div class="acf-postbox-header">
			<h2 class="acf-postbox-title"><?php echo esc_html( $this->title ); ?></h2>
		</div>
		<div class="acf-postbox-inner">
			<p>
				<?php
				echo sprintf(
				/* translators: %1$s, %2$s, %3$s and %4$s are placeholders for two <a> link tags. */
					esc_html__( 'ACF allows you to %1$sregister fields via PHP%2$s or %3$ssave field settings as JSON files%4$s. You can also save post types, taxonomies, or options pages settings to JSON files.', 'acfml' ),
					'<a href="https://www.advancedcustomfields.com/resources/register-fields-via-php/" target="_blank" rel="noopener noreferrer">',
					'</a>',
					'<a href="https://www.advancedcustomfields.com/resources/local-json/" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);
				?>
			</p>
			<p>
				<?php /* translators: Line under the tool's heading; "these items" are the field groups, post types, taxonomies and options pages defined in code or in acf-json files. */ esc_html_e( 'Configure how ACF Multilingual handles translations for these items.', 'acfml' ); ?>
			</p>
			<div class="acf-fields">
				<div class="acf-field">
					<h3><?php echo BoldNames::render( /* translators: Heading of the first setting of that tool. Keep the bold tags around the name of the preference set. Verb phrase, imperative. */ __( 'Sync <b>Translation Preferences</b> for Local Fields', 'acfml' ) );  ?></h3>
					<ul class="acf-checkbox acf-bl">
						<li>
							<label>
								<input name="<?php echo esc_attr( self::POST_SCAN_MODE ); ?>" type="radio"
								       value="<?php echo esc_attr( self::SCAN_MODE_NONE ); ?>" <?php checked( self::SCAN_MODE_NONE, LocalSettings::getScanMode() ); ?> />
								<?php /* translators: First option of that setting: leave the preferences of code-defined fields alone. */ esc_html_e( 'Don’t sync translation preferences', 'acfml' ); ?>
							</label>
						</li>
						<li>
							<label>
								<input name="<?php echo esc_attr( self::POST_SCAN_MODE ); ?>" type="radio"
								       value="<?php echo esc_attr( self::SCAN_MODE_ONCE ); ?>"/>
								<?php /* translators: Second option of that setting: copy the preferences a single time. Verb phrase, imperative. */ esc_html_e( 'Sync once now', 'acfml' ); ?>
							</label>
						</li>
						<li>
							<label>
								<input name="<?php echo esc_attr( self::POST_SCAN_MODE ); ?>" type="radio"
								       value="<?php echo esc_attr( self::SCAN_MODE_ALWAYS ); ?>" <?php checked( self::SCAN_MODE_ALWAYS, LocalSettings::getScanMode() ); ?> />
								<?php /* translators: Third option of that setting: copy the preferences on every page load. Verb phrase, imperative. */ esc_html_e( 'Sync on every request (may affect performance)', 'acfml' ); ?>
							</label>
						</li>
					</ul>
				</div>

				<div class="acf-field">
					<h3><?php /* translators: Heading of the second setting of the tool, about making field names translatable. Verb phrase, imperative. */ esc_html_e( 'Register Labels for Translation', 'acfml' ); ?></h3>
					<p>
						<?php
						/* translators: Explanation under that heading. Keep "acf-json" in English: it is a folder name. */
						esc_html_e( 'Scans post types, taxonomies, options pages, and field groups stored in the "acf-json" directory, then registers their labels with WPML so you can translate them.', 'acfml' );
						?>
					</p>
					<ul class="acf-checkbox acf-bl">
						<li>
							<label>
								<input name="<?php echo esc_attr( self::POST_REGISTER_LOCAL_LABELS ); ?>" type="radio"
								       value="<?php echo esc_attr( self::REGISTER_LOCAL_LABELS_NONE ); ?>"
								       checked="checked"/>
								<?php /* translators: First option of the label-registration setting: leave the names alone. */ esc_html_e( 'Don’t register labels', 'acfml' ); ?>
							</label>
						</li>
						<li>
							<label>
								<input name="<?php echo esc_attr( self::POST_REGISTER_LOCAL_LABELS ); ?>" type="radio"
								       value="<?php echo esc_attr( self::REGISTER_LOCAL_LABELS_ONCE ); ?>"/>
								<?php /* translators: Second option of the label-registration setting: make the names translatable straight away. Verb phrase, imperative. */ esc_html_e( 'Register labels now', 'acfml' ); ?>
							</label>
						</li>
					</ul>
				</div>
			</div>

			<p class="acf-submit">
				<?php wp_nonce_field( self::NONCE, self::NONCE ); ?>
				<button type="submit" name="acfml-submit-local-tool" value="acfml-submit-local-tool"
				        class="acf-btn"><?php /* translators: Button that saves the tool's settings and runs it. Verb, imperative. */ esc_attr_e( 'Apply', 'acfml' ); ?></button>
			</p>
		</div>
		<?php
	}

	public function submit() {
		if ( ! function_exists( 'acf_current_user_can_admin' ) || ! acf_current_user_can_admin() ) {
			return;
		}

		$postData   = filter_input_array( INPUT_POST ) ?: [];
		$nonceValue = sanitize_key( Obj::prop( self::NONCE, $postData ) );
		if ( ! wp_verify_nonce( $nonceValue, self::NONCE ) ) {
			return;
		}

		$scanMode = sanitize_key( Obj::propOr( self::SCAN_MODE_NONE, self::POST_SCAN_MODE, $postData ) );
		if ( ! in_array( $scanMode, [ self::SCAN_MODE_NONE, self::SCAN_MODE_ONCE, self::SCAN_MODE_ALWAYS ], true ) ) {
			$scanMode = self::SCAN_MODE_NONE;
		}
		$registerLocalLabels = self::REGISTER_LOCAL_LABELS_ONCE === Obj::prop( self::POST_REGISTER_LOCAL_LABELS, $postData );

		$successNotice = [];

		switch ( $scanMode ) {
			case self::SCAN_MODE_NONE:
				LocalSettings::enableScanMode( false );
				/* translators: Confirmation shown after the tool ran with syncing switched off. */
				$successNotice[] = __( 'The synchronization of translation preferences is disabled.', 'acfml' );
				break;
			case self::SCAN_MODE_ONCE:
				LocalSettings::enableScanMode( false );
				/* translators: Confirmation shown after the tool copied the preferences once. */
				$successNotice[] = __( 'Translation preferences synchronized.', 'acfml' );
				break;
			case self::SCAN_MODE_ALWAYS:
				LocalSettings::enableScanMode( true );
				/* translators: Confirmation shown after the tool ran with syncing switched on. */
				$successNotice[] = __( 'The synchronization of translation preferences is enabled.', 'acfml' );
				break;
		}

		if ( $registerLocalLabels ) {
			$isLocalEnabled = acf_is_local_enabled();
			if ( ! $isLocalEnabled ) {
				acf_enable_local();
			}
			$translator                    = new Translator( new Factory() );
			$affectedItems                 = [];
			$affectedItems['group']        = $this->registerLocalItems( 'acf-field-group', [
				$translator,
				'registerGroupAndFieldsAndLayouts',
			] );
			$affectedItems['post-type']    = $this->registerLocalItems( 'acf-post-type', [ $translator, 'registerCPT' ] );
			$affectedItems['taxonomy']     = $this->registerLocalItems( 'acf-taxonomy', [
				$translator,
				'registerTaxonomy',
			] );
			$affectedItems['options-page'] = $this->registerLocalItems( 'acf-ui-options-page', [
				$translator,
				'registerOptionsPage',
			] );
			if ( ! $isLocalEnabled ) {
				acf_disable_local();
			}

			$successNotice[] = $this->getRegisteredLabelsNotice( $affectedItems );
		}

		acf_add_admin_notice( implode( ' ', $successNotice ), 'success' );
	}

	public function isLocal( $item ) {
		return Relation::propEq( 'ID', 0, $item ) && Lst::includes( Obj::prop( 'local', $item ), [ 'json', 'php' ] );
	}

	private function registerLocalItems( $itemType, $registrationMethod ) {
		return wpml_collect( acf_get_internal_post_type_posts( $itemType ) )
			->filter( [ $this, 'isLocal' ] )
			->map( $registrationMethod )
			->count();
	}

	private function getAffectedNoticeBit( $count, $type ) {
		switch ( $type ) {
			case 'group':
				/* translators: Item in the list of what the tool registered, joined with the others by "and". %s: how many field groups. */
				return sprintf( _n( '%s group', '%s groups', $count, 'acfml' ), $count );
			case 'post-type':
				/* translators: Item in the list of what the tool registered, joined with the others by "and". %s: how many post types. */
				return sprintf( _n( '%s post type', '%s post types', $count, 'acfml' ), $count );
			case 'taxonomy':
				/* translators: Item in the list of what the tool registered, joined with the others by "and". %s: how many taxonomies. */
				return sprintf( _n( '%s taxonomy', '%s taxonomies', $count, 'acfml' ), $count );
			case 'options-page':
				/* translators: Item in the list of what the tool registered, joined with the others by "and". %s: how many ACF options pages. */
				return sprintf( _n( '%s options page', '%s options pages', $count, 'acfml' ), $count );
		}

		return '';
	}

	private function getRegisteredLabelsNotice( $affectedItems ) {
		if ( 0 === array_sum( $affectedItems ) ) {
			/* translators: Result shown when the tool found nothing defined in code or in acf-json files. */
			return __( 'No local field groups, post types, taxonomies, or Options pages were found.', 'acfml' );
		}

		$successNoticeBits = wpml_collect( $affectedItems )
			->filter()
			->map( function ( $count, $type ) {
				return $this->getAffectedNoticeBit( $count, $type );
			} )
			->toArray();

		if ( count( $successNoticeBits ) < 2 ) {
			/* translators: The word joining the only two items of a list, inside the sentence saying what the tool registered. Keep the space on each side. */
			$noticeList = implode( _x( ' and ', 'Used between elements of a two elements list', 'acfml' ), $successNoticeBits );
		} else {
			$last  = array_slice( $successNoticeBits, - 1 );
			$first = implode( ', ', array_slice( $successNoticeBits, 0, - 1 ) );
			$both  = array_merge( [ $first ], $last );
			/* translators: The comma and word placed before the last item of a list of three or more, inside the sentence saying what the tool registered. Keep the trailing space. */
			$noticeList = implode( _x( ', and ', 'Used before the last element of a three or more elements list', 'acfml' ), $both );
		}

		return sprintf(
			/* translators: %s: List of registered labels. */
			__( 'Successfully registered labels for %s.', 'acfml' ),
			$noticeList
		);
	}

}
