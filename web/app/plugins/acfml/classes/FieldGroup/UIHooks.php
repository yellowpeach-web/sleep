<?php

namespace ACFML\FieldGroup;

use ACFML\FieldGroup\Endpoints\CountAffectedPosts;
use ACFML\FieldGroup\Endpoints\DismissTranslateCptModal;
use ACFML\Helper\BoldNames;
use ACFML\Helper\FieldGroup;
use ACFML\Helper\Fields;
use ACFML\Helper\Resources;
use ACFML\Notice\Links;
use ACFML\Strings\Package;
use ACFML\Tools\AdminUrl;
use ACFML\Upgrade\Commands\MigrateToV2;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\FP\Wrapper;
use WPML\LIB\WP\Hooks;

class UIHooks implements \IWPML_Action {

	const SCREEN_SLUG = 'acf-field-group';

	public function add_hooks() {
		Hooks::onAction( 'admin_print_scripts' )->then( [ __CLASS__, 'addMetaBox' ] );
		Hooks::onAction( 'admin_enqueue_scripts' )->then( [ __CLASS__, 'enqueueAssets' ] );
	}

	public static function addMetaBox() {
		add_meta_box(
			'acfml-field-group-setup',
			/* translators: Title of the WPML panel on the ACF field group screen and on the post editor. Used as a name elsewhere in this plugin, so translate it the same way each time. */
			'<i class="otgs-ico-translation"></i>&nbsp;' . esc_html__( 'Multilingual Setup', 'acfml' ),
			function () {
				/* translators: Shown in the Multilingual Setup panel while its contents are fetched. Keep the three dots. */
				echo '<div id="acfml-field-group-ml-setup">' . esc_html__( 'Loading...', 'acfml' ) . '</div>';
			},
			self::SCREEN_SLUG,
			'normal',
			'high'
		);
	}

	public static function enqueueAssets() {
		if ( FieldGroup::isScreen() ) {
			Wrapper::of( self::getData() )->map( Resources::enqueueApp( 'field-group-edit' ) );
		}
	}

	private static function getData() {
		$fieldGroupId  = Obj::prop( 'ID', get_post() );
		$fieldGroupKey = Obj::prop( 'post_name', get_post() );
		$fieldGroup    = (array) acf_get_field_group( $fieldGroupId );
		$isNewGroup    = self::isNewGroup( $fieldGroupId );
		$attachedPosts = AttachedPosts::getCount( $fieldGroupId );

		$locations = Obj::propOr( [], 'location', $fieldGroup );

		$optionsPageLocation = DetectNonTranslatableLocations::classifyOptionsPageLocations( $locations );
		$detectedType        = DetectNonTranslatableLocations::getDetectedType( $fieldGroupId, $locations );
		$nonTranslatableType = DetectNonTranslatableLocations::DETECTED_OPTIONS_PAGE === $detectedType
			? null
			: $detectedType;

		return [
			'name' => 'acfmlFieldGroupEdit',
			'data' => [
				'endpoints'            => [
					'dismissTranslateCptModal' => DismissTranslateCptModal::class,
					'countAffectedPosts'       => CountAffectedPosts::class,
				],
				'fieldGroupId'         => $fieldGroupId,
				'pluginImageURI'       => ACFML_PLUGIN_URL . '/assets/img/',
				'fieldGroupMode'       => Mode::getMode( $fieldGroup ),
				'STModalData'          => self::getSTModal( $fieldGroupKey ),
				'strings'              => self::getStrings( $attachedPosts, $nonTranslatableType ),
				'hasAcfml1Tooltip'     => $isNewGroup && MigrateToV2::needsNotification(),
				'hasTranslateCptModal' => (bool) $nonTranslatableType,
				'translateCptUrl'      => DetectNonTranslatableLocations::getSettingsUrl( $nonTranslatableType ),
				'optionsPageLocation'  => $optionsPageLocation,
				'docLinks'             => self::getDocLinks(),
				'fieldPreferences'     => self::getFieldPreferences( $fieldGroupId, $fieldGroup ),
				'preferenceLabels'     => self::getPreferenceLabels(),
			],
		];
	}

	private static function getFieldPreferences( $fieldGroupId, array $fieldGroup ) {
		if ( ! $fieldGroupId ) {
			return [];
		}

		$source      = new PreferenceSource( function_exists( 'wpml_load_core_tm' ) ? wpml_load_core_tm() : null );
		$preferences = [];

		$collect = function ( $field ) use ( &$preferences, $source, $fieldGroup ) {
			$key = Obj::propOr( '', 'key', $field );

			if ( $key && ! ModeValidity::storesNoValue( $field ) ) {
				$resolved = $source->resolve( $field, $fieldGroup );
				$name     = (string) Obj::propOr( '', 'name', $field );
				$label    = (string) Obj::propOr( '', 'label', $field );

				$preferences[ $key ] = [
					'name'       => $name,
					'label'      => esc_html( '' !== $label ? $label : $name ),
					'preference' => $resolved['stored'],
					'locked'     => (bool) $resolved['isLocked'],
				];
			}

			return $field;
		};

		Fields::iterate( (array) acf_get_fields( $fieldGroupId ), $collect, Fns::identity() );

		return $preferences;
	}

	private static function getPreferenceLabels() {
		return array_map( 'esc_html', PreferenceSource::getPreferenceLabels() );
	}

	private static function isNewGroup( $groupId ) {
		return 'auto-draft' === get_post_status( $groupId );
	}

	private static function isTeaEnabled() {
		return \WPML\Setup\Option::shouldTranslateEverything();
	}

	private static function getSTModal( $fieldGroupKey ) {
		$status = Package::create( $fieldGroupKey )->getStatus();

		switch ( $status ) {
			case Package::STATUS_ST_INACTIVE:
				return [
					/* translators: Title of the dialog shown when the WPML String Translation plugin is installed but switched off. Keep the bold tags around the plugin name. Verb phrase, imperative. */
					'title'        => BoldNames::render( __( 'Activate <b>String Translation</b>', 'acfml' ) ),
					'content'      => sprintf(
						/* translators: %1$s and %2$s will wrap the string in a <a> link html tag */
						esc_html__( 'To translate field group names and labels, please %1$sinstall and activate WPML’s String Translation add-on%2$s.', 'acfml' ),
						'<a href="' . Links::getFaqInstallST() . '" class="wpml-external-link" target="_blank">',
						'</a>'
					),
					/* translators: Button in that dialog; it opens the WordPress plugins screen. Verb phrase, imperative. */
					'okText'       => esc_html__( 'Activate now', 'acfml' ),
					/* translators: Button that closes a dialog and returns to the field group. Verb phrase, imperative. */
					'cancelText'   => esc_html__( 'Go back', 'acfml' ),
					'redirectOnOk' => admin_url( '/plugins.php' ),
					'footerText'   => null,
				];

			case Package::STATUS_NOT_REGISTERED:
				return [
					/* translators: Title of the dialog shown when the field group's labels are not registered for translation yet. Keep the bold tags. Verb phrase, imperative. */
					'title'        => BoldNames::render( __( 'Set Up <b>Field Group Translation</b>', 'acfml' ) ),
					/* translators: Body of that dialog. Keep the bold tags around the two WPML screen names. */
					'content'      => BoldNames::render( __( 'To translate field labels from the <b>Translation Management</b> dashboard, finish the <b>Multilingual Setup</b> and save your field group settings.', 'acfml' ) ),
					/* translators: Button that closes a dialog with nothing further to do. */
					'okText'       => esc_html__( 'OK', 'acfml' ),
					'cancelText'   => null,
					'redirectOnOk' => null,
					'footerText'   => null,
				];

			case Package::STATUS_NOT_TRANSLATED:
				return [
					/* translators: Title of the dialog offering to translate the names of the fields, not their values. Verb phrase, imperative. */
					'title'        => esc_html__( 'Translate Field Labels', 'acfml' ),
					/* translators: Body of that dialog. Keep the bold tags around the WPML screen name. */
					'content'      => '<p>' . BoldNames::render( __( 'To translate field labels, use the <b>Translation Management</b> dashboard.', 'acfml' ) ) . '</p>',
					/* translators: Button that opens WPML's Translation Management dashboard. Keep the bold tags around the screen name. Verb, imperative. */
					'okText'       => BoldNames::render( __( 'Translate in <b>Translation Management</b>', 'acfml' ) ),
					'cancelText'   => null,
					'redirectOnOk' => self::getLinkToTMDashboard( $fieldGroupKey ),
					'footerText'   => '<p>' . sprintf(
						/* translators: %1$s and %2$s will wrap the string in a <a> link html tag */
						esc_html__( 'Don’t want to translate field labels? %1$sLearn how to disable field label translation%2$s', 'acfml' ),
						'<a href="' . Links::getAcfmlTranslateLabels( 'excluding-field-labels-from-the-advanced-translation-editor' ) . '" class="wpml-external-link" target="_blank">',
						'</a>'
					) . '</p>',
				];

			case Package::STATUS_PARTIALLY_TRANSLATED:
				return [
					/* translators: Title of the dialog shown when some of the field group's labels are still untranslated. */
					'title'        => esc_html__( 'Field Labels Partly Translated', 'acfml' ),
					/* translators: Body of that dialog. Keep the bold tags around the WPML screen name. */
					'content'      => '<p>' . BoldNames::render( __( 'Some field labels in this group are still untranslated. To finish translating them, go to the <b>Translation Management</b> dashboard.', 'acfml' ) ) . '</p>',
					/* translators: Button that opens WPML's Translation Management dashboard. Keep the bold tags around the screen name. Verb, imperative. */
					'okText'       => BoldNames::render( __( 'Translate in <b>Translation Management</b>', 'acfml' ) ),
					/* translators: Button that closes a dialog and returns to the field group. Verb phrase, imperative. */
					'cancelText'   => esc_html__( 'Go back', 'acfml' ),
					'redirectOnOk' => self::getLinkToTMDashboard( $fieldGroupKey ),
					'footerText'   => null,
				];

			case Package::STATUS_FULLY_TRANSLATED:
			default:
				return [
					/* translators: Title of the dialog shown when every label of the field group is translated. */
					'title'        => esc_html__( 'Fields Labels Already Translated', 'acfml' ),
					/* translators: Body of that dialog. Keep the bold tags around the WPML screen name. */
					'content'      => '<p>' . BoldNames::render( __( 'You already translated all field labels in this group. To update any translations, go to the <b>Translation Management</b> dashboard.', 'acfml' ) ) . '</p>',
					/* translators: Button that opens WPML's Translation Management dashboard. Keep the bold tags around the screen name. Verb phrase, imperative. */
					'okText'       => BoldNames::render( __( 'Go to <b>Translation Management</b>', 'acfml' ) ),
					/* translators: Button that closes a dialog and returns to the field group. Verb phrase, imperative. */
					'cancelText'   => esc_html__( 'Go back', 'acfml' ),
					'redirectOnOk' => self::getLinkToTMDashboard( $fieldGroupKey ),
					'footerText'   => null,
				];
		}
	}

	private static function getLinkToTMDashboard( $fieldGroupKey ) {
		return AdminUrl::getWPMLTMDashboardPackageSection( Package::FIELD_GROUP_PACKAGE_KIND_SLUG );
	}

	private static function getStrings( $attachedPosts, $nonTranslatableType ) {
		return [
			'fieldGroupMode' => [
				/* translators: Heading of the Multilingual Setup panel, above the three translation options. Verb phrase, imperative. */
				'title'                      => esc_html__( 'Select a translation option for this field group', 'acfml' ),
				'modes'                      => [
					'translation'  => [
						'label'       => esc_html( Mode::getLabel( Mode::TRANSLATION ) ),
						/* translators: First paragraph describing the "Same content in every language, translated" option. */
						'description' => '<p>' . esc_html__( 'Visitors everywhere see the same pages, in their own language. Text is translated; layout and settings stay identical.', 'acfml' ) . '</p>'
										/* translators: Second paragraph describing that same option. Keep the bold tags around the two WPML feature names. */
										. '<p>' . BoldNames::render( __( 'You translate in the WPML <b>Translation Editor</b>. This works with automatic translation and <b>Translate Everything</b>.', 'acfml' ) ) . '</p>',
					],
					'localization' => [
						'label'       => esc_html( Mode::getLabel( Mode::LOCALIZATION ) ),
						/* translators: First paragraph describing the "Each language has its own content" option. */
						'description' => '<p>' . esc_html__( 'Every language’s pages are written and maintained on their own. Fields start as a copy of the original, then go their own way.', 'acfml' ) . '</p>'
										/* translators: Second paragraph describing that same option; "them" are the language versions. */
										. '<p>' . esc_html__( 'You edit each language in the WordPress editor. WPML does not sync them afterwards, and automatic translation does not apply.', 'acfml' ) . '</p>',
					],
					'advanced'     => [
						'label'       => esc_html( Mode::getLabel( Mode::ADVANCED ) ),
						/* translators: First paragraph describing the Expert option; "Expert" is the option's own name, translated the same way in the option list. */
						'description' => '<p>' . esc_html__( 'If you are migrating a site, your existing field groups will use the Expert setup. This allows you to manually choose the translation option for each field in the group.', 'acfml' ) . '</p>'
										/* translators: %1$s and %2$s will wrap the string in a <b> html tag */
										. '<p>' . sprintf( esc_html__( 'This option is %1$snot recommended%2$s for new field groups.', 'acfml' ), '<b>', '</b>' ) . '</p>'
										/* translators: Link under the Expert option; it opens the ACFML documentation. */
										. '<p><a href="' . Links::getAcfmlExpertDoc() . '" class="wpml-external-link" target="_blank">' . esc_html__( 'Expert setup documentation', 'acfml' ) . '</a></p>',
					],
				],
				/* translators: Button under each translation option that picks it. Verb, imperative. */
				'choose'                     => esc_html__( 'Choose', 'acfml' ),
				/* translators: Badge next to the translation option WPML suggests. Lower case, as it sits inside the option card. */
				'recommended'                => esc_html__( 'recommended for this site', 'acfml' ),
				/* translators: Button that reopens the translation options after one was chosen. Verb phrase, imperative. */
				'change-option'              => esc_html__( 'Change option', 'acfml' ),
				'acfml1tooltip'              => [
					/* translators: Title of the tooltip introducing the new field group setup. */
					'title'   => esc_html__( 'A Much Simpler Way to Translate Your ACF Sites', 'acfml' ),
					/* translators: Body of the tooltip introducing the new field group setup. "ACFML" is the plugin's short name and stays in English. */
					'content' => esc_html__( 'This new release of ACFML allows you to configure multilingual sites in one-click, instead of many complex settings. Choose how to setup the translation for the fields.', 'acfml' ),
				],
				'translate-cpt'              => [
					'title'       => DetectNonTranslatableLocations::getTitle( $nonTranslatableType ),
					'description' => DetectNonTranslatableLocations::getDescription( $nonTranslatableType ),
					/* translators: Button that opens WPML's settings, in the dialog about a post type or taxonomy that cannot be translated. Verb phrase, imperative. */
					'ok'          => esc_html__( 'Go to WPML Settings', 'acfml' ),
					/* translators: Button that closes a dialog and returns to the field group. Verb phrase, imperative. */
					'cancel'      => esc_html__( 'Go back', 'acfml' ),
				],
				'translation-status-process' => self::getConfirmationStrings( $attachedPosts ),
				'options-page'               => self::getOptionsPageStrings(),
				/* translators: Link under the translation options that opens the documentation. */
				'need-help-choosing'         => esc_html__( 'Need help choosing?', 'acfml' ),
				/* translators: Link in the Multilingual Setup panel that opens the ACFML documentation. Noun. */
				'documentation'              => esc_html__( 'Documentation', 'acfml' ),
				/* translators: Link in the Multilingual Setup panel about translating the fields' names. Keep the arrow at the end. */
				'go-to-st'                   => esc_html__( 'How to translate field labels »', 'acfml' ),
				/* translators: Line in the Multilingual Setup panel saying what the chosen option covers: what is typed into the fields, not their names. */
				'labels-scope-values'        => esc_html__( 'This setup translates field values.', 'acfml' ),
				/* translators: Line in the Multilingual Setup panel about translating the fields' names. "ACF Field Group" is the type the Translation Dashboard shows for these strings; keep the bold tags around the screen name. */
				'labels-scope-labels'        => BoldNames::render( __( 'Translate field labels and dropdown choice labels separately. Find them under ACF Field Group in the <b>Translation Dashboard</b>.', 'acfml' ) ),
			],
		];
	}

	private static function getConfirmationStrings( $attachedPosts ) {
		return array_merge(
			[
				/* translators: Title of the dialog listing what saving the field group will change. Verb phrase, imperative. */
				'title'               => esc_html__( 'Review these translation setting changes', 'acfml' ),
				/* translators: Line above the list of changes in that dialog; the list follows the colon. */
				'changes-intro'       => esc_html__( 'Saving this field group makes these changes:', 'acfml' ),
				/* translators: %1$s is a field label, %2$s the preference it had, %3$s the preference it gets. Keep the quotation marks your language uses around the two values. */
				'change-row'          => esc_html__( '%1$s: from “%2$s” to “%3$s”', 'acfml' ),
				/* translators: Line in that dialog shown when the field group moves to the Expert option. */
				'mode-change-expert'  => esc_html__( 'Switching to the field-by-field setup keeps every field\'s current setting. From here you set each field yourself.', 'acfml' ),
				/* translators: Line in that dialog about content translated from now on. */
				'new-translations'    => esc_html__( 'New translations use the new settings automatically.', 'acfml' ),
				/* translators: Shown in a change row in place of the old value, when the field had no translation preference before. */
				'from-not-set'        => esc_html__( 'Not set', 'acfml' ),
				'existing-cost'       => self::getExistingCostString(),
				/* translators: Line in that dialog when no translated post uses the field group. */
				'existing-none'       => esc_html__( 'No posts with translations use this field group yet, so nothing has to be sent again.', 'acfml' ),
				/* translators: Line in that dialog when no translated category, tag or other term uses the field group. */
				'existing-none-terms' => esc_html__( 'No terms with translations use this field group yet, so nothing has to be sent again.', 'acfml' ),
				/* translators: Line in that dialog when neither a translated post nor a translated term uses the field group. */
				'existing-none-both'  => esc_html__( 'No posts or terms with translations use this field group yet, so nothing has to be sent again.', 'acfml' ),
				/* translators: Label of the option that leaves existing translations alone. Verb phrase, imperative. */
				'choice-new'          => esc_html__( 'Only apply to new translations', 'acfml' ),
				/* translators: Shown in that dialog while the affected posts are being counted. Keep the three dots. */
				'counting'            => esc_html__( 'Checking how much content this affects...', 'acfml' ),
				'description'         => AttachedPosts::getProcessConfirmationMessage( $attachedPosts ),
				'translatable-impact' => AttachedPosts::getTranslatableImpactMessage( $attachedPosts ),
				/* translators: Button that confirms the dialog and saves the field group. Verb phrase, imperative. */
				'yes'                 => esc_html__( 'Save changes', 'acfml' ),
				/* translators: Button that closes the dialog without saving the field group. */
				'no'                  => esc_html__( 'No, go back', 'acfml' ),
			],
			self::getCountedConfirmationStrings()
		);
	}

	private static function getExistingCostString(): string {
		if ( self::isTeaEnabled() ) {
			/* translators: Line in that dialog about the posts that already have translations; "them" are those posts. */
			return esc_html__( 'Updating them sends them for translation again, which may use translation words.', 'acfml' );
		}

		/* translators: Line in that dialog about the posts that already have translations, when WPML does not translate content automatically; "them" and "their" are those posts. */
		return esc_html__( 'Updating them marks their translations as needing an update. WPML does not send them for translation. You can send them from the Translation Dashboard.', 'acfml' );
	}

	private static function getCountedConfirmationStrings() {
		return [
			'mode-change'                  => esc_html(
				/* translators: %d is the number of fields in the field group. */
				_n(
					'Switching the setup applies the recommended value to the %d field in this group.',
					'Switching the setup applies the recommended value to all %d fields in this group.',
					2,
					'acfml'
				)
			),
			'mode-change-1'                => esc_html(
				/* translators: %d is the number of fields in the field group. */
				_n(
					'Switching the setup applies the recommended value to the %d field in this group.',
					'Switching the setup applies the recommended value to all %d fields in this group.',
					1,
					'acfml'
				)
			),
			'existing-lead'                => esc_html(
				/* translators: %d is the number of posts that already have translations. */
				_n(
					'%d post already has translations that used the old settings.',
					'%d posts already have translations that used the old settings.',
					2,
					'acfml'
				)
			),
			'existing-lead-1'              => esc_html(
				/* translators: %d is the number of posts that already have translations. */
				_n(
					'%d post already has translations that used the old settings.',
					'%d posts already have translations that used the old settings.',
					1,
					'acfml'
				)
			),
			'existing-lead-terms'          => esc_html(
				/* translators: %d is the number of terms that already have translations. */
				_n(
					'%d term already has translations that used the old settings.',
					'%d terms already have translations that used the old settings.',
					2,
					'acfml'
				)
			),
			'existing-lead-terms-1'        => esc_html(
				/* translators: %d is the number of terms that already have translations. */
				_n(
					'%d term already has translations that used the old settings.',
					'%d terms already have translations that used the old settings.',
					1,
					'acfml'
				)
			),
			'existing-lead-untranslated'   => esc_html(
				/* translators: %d is the number of posts using this field group. */
				_n(
					'%d post uses this field group.',
					'%d posts use this field group.',
					2,
					'acfml'
				)
			),
			'existing-lead-untranslated-1' => esc_html(
				/* translators: %d is the number of posts using this field group. */
				_n(
					'%d post uses this field group.',
					'%d posts use this field group.',
					1,
					'acfml'
				)
			),
			'existing-hand-translated'     => esc_html(
				/* translators: %d is the number of posts with translations that have their own content in each language. */
				_n(
					'%d post is translated by hand, so WPML does not send it for translation.',
					'%d posts are translated by hand, so WPML does not send them for translation.',
					2,
					'acfml'
				)
			),
			'existing-hand-translated-1'   => esc_html(
				/* translators: %d is the number of posts with translations that have their own content in each language. */
				_n(
					'%d post is translated by hand, so WPML does not send it for translation.',
					'%d posts are translated by hand, so WPML does not send them for translation.',
					1,
					'acfml'
				)
			),
			'choice-existing'              => esc_html(
				/* translators: %d is the number of posts that already have translations. */
				_n(
					'Also update the %d existing post',
					'Also update the %d existing posts',
					2,
					'acfml'
				)
			),
			'choice-existing-1'            => esc_html(
				/* translators: %d is the number of posts that already have translations. */
				_n(
					'Also update the %d existing post',
					'Also update the %d existing posts',
					1,
					'acfml'
				)
			),
			'choice-existing-terms'        => esc_html(
				/* translators: %d is the number of terms that already have translations. */
				_n(
					'Also update the %d existing term',
					'Also update the %d existing terms',
					2,
					'acfml'
				)
			),
			'choice-existing-terms-1'      => esc_html(
				/* translators: %d is the number of terms that already have translations. */
				_n(
					'Also update the %d existing term',
					'Also update the %d existing terms',
					1,
					'acfml'
				)
			),
			/* translators: %1$d is a number of posts, %2$d a number of terms. */
			'choice-existing-both'         => esc_html__( 'Also update the %1$d existing posts and %2$d terms', 'acfml' ),
			/* translators: %1$d is a number of posts, %2$d a number of terms. */
			'choice-existing-both-1-n'     => esc_html__( 'Also update the %1$d existing post and %2$d terms', 'acfml' ),
			/* translators: %1$d is a number of posts, %2$d a number of terms. */
			'choice-existing-both-n-1'     => esc_html__( 'Also update the %1$d existing posts and %2$d term', 'acfml' ),
			/* translators: %1$d is a number of posts, %2$d a number of terms. */
			'choice-existing-both-1-1'     => esc_html__( 'Also update the %1$d existing post and %2$d term', 'acfml' ),
		];
	}

	private static function getOptionsPageStrings() {
		$dashboardLink = '<a href="' . AdminUrl::getTranslationDashboard() . '">';

		return [
			/* translators: First sentence of the notice on a field group attached to an ACF options page. "Options page" is ACF's own name for that screen. */
			'notice' => '<b>' . esc_html__( 'This field group is shown on an Options page.', 'acfml' ) . '</b> '
						/* translators: Second sentence of that notice; "That" is the content on an options page. */
						. esc_html__( 'That is usually site-wide content like your header, footer, or global blocks.', 'acfml' ) . ' '
						. sprintf(
							/* translators: %1$s and %2$s will wrap the string in a <a> link html tag */
							esc_html__( 'To translate that content, use the %1$sWPML Translation Dashboard%2$s.', 'acfml' ),
							$dashboardLink,
							'</a>'
						) . ' '
						/* translators: Last sentence of that notice; "the options below" are the translation options of the panel. */
						. esc_html__( 'The options below still decide how each field behaves: translated, copied to every language, or kept separately per language.', 'acfml' ),
			/* translators: First sentence of the notice on a field group used both on an ACF options page and elsewhere. */
			'mixed'  => esc_html__( 'This field group also appears on an Options page.', 'acfml' ) . ' '
						. sprintf(
							/* translators: %1$s and %2$s will wrap the string in a <a> link html tag */
							esc_html__( 'That content is translated in the %1$sWPML Translation Dashboard%2$s, and the option you choose here decides how each of its fields behaves.', 'acfml' ),
							$dashboardLink,
							'</a>'
						),
		];
	}

	private static function getDocLinks() {
		return [
			'main'         => Links::getAcfmlMainDoc(),
			'translation'  => Links::getAcfmlMainModeTranslationDoc(),
			'localization' => Links::getAcfmlMainModeLocalizationDoc(),
			'advanced'     => Links::getAcfmlExpertDoc(),
		];
	}
}
