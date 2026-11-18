<?php

namespace WPML\TranslationPolicy\Settings;

use WPML\TranslationPolicy\Policy\PolicySql;
use WPML\TranslationPolicy\Policy\PolicyTable;
use WPML\TranslationPolicy\Policy\PolicyWriter;
use WPML\TranslationPolicy\Policy\Precedence;
use WPML\TranslationPolicy\Ptc\PtcConfirm;
use WPML\UIPage;

class Section {

	const SECTION_ID = 'ml-content-setup-sec-1';

	const SCRIPT = 'wpml-translation-policy-settings';

	const TYPES_NOT_OFFERED = [ 'attachment' ];

	const ALLOWED_CELL_HTML = [
		'a' => [ 'href' => true ],
		'i' => [
			'aria-label' => true,
			'class'      => true,
		],
	];

	private $sitepress;

	private $values;

	public function __construct( \SitePress $sitepress, ScopeValues $values ) {
		$this->sitepress = $sitepress;
		$this->values    = $values;
	}

	public static function create(): self {
		global $sitepress, $wpdb;

		return new self( $sitepress, new ScopeValues( new PolicySql( $wpdb ) ) );
	}

	public static function saveSettings(): SaveSettings {
		global $wpdb;

		$sql = new PolicySql( $wpdb );

		return new SaveSettings( $sql, new PolicyWriter( $wpdb, new PolicyTable( $wpdb ), $sql ), array_keys( self::create()->postTypes() ) );
	}

	public function subtitle(): string {
		return Words::subtitle();
	}

	public function postTypes(): array {
		$types = [];
		foreach ( (array) $this->sitepress->get_translatable_documents() as $slug => $postType ) {
			$isEdited = ! empty( $postType->show_ui ) || ! empty( $postType->show_in_rest );
			if ( $isEdited && ! in_array( $slug, self::TYPES_NOT_OFFERED, true ) ) {
				$types[ (string) $slug ] = isset( $postType->labels->name ) ? (string) $postType->labels->name : (string) $slug;
			}
		}

		return $types;
	}

	public function title(): string {
		return Words::title();
	}

	public function typeRows(): TypeRows {
		$types  = $this->postTypes();
		$values = [];
		foreach ( array_keys( $types ) as $slug ) {
			$values[ $slug ] = $this->values->type( $slug );
		}

		return new TypeRows( $types, $this->values->site(), $values );
	}

	public static function stateAfterSave(): array {
		$section  = self::create();
		$site     = $section->values->site();
		$typeRows = $section->typeRows();

		return [
			'summary' => $typeRows->summary(),
			'panel'   => $section->typesPanel( $typeRows ),
			'notes'   => [
				PolicyTable::COLUMN_EDITOR => $section->editorNote( $section->values->siteEditor() ),
				PolicyTable::COLUMN_METHOD => $section->note( $site[ PolicyTable::COLUMN_METHOD ] ),
			],
		];
	}

	public function render() {
		$this->enqueue();

		$site     = $this->values->site();
		$typeRows = $this->typeRows();
		?>
		<div class="wpml-section" id="<?php echo esc_attr( self::SECTION_ID ); ?>">
			<?php
			wp_print_styles( self::SCRIPT );
			?>
			<div class="wpml-section-content translation-method-content wpml-translation-policy-settings">
				<?php $this->renderEditorStep( $this->values->siteEditor() ); ?>
				<?php $this->renderMethodStep( $site[ PolicyTable::COLUMN_METHOD ] ); ?>
				<?php $this->renderTypesStep( $typeRows ); ?>

				<?php include ICL_PLUGIN_PATH . '/menu/_translation_editor_faq.php'; ?>
			</div>
		</div>
		<?php
	}

	private function renderEditorStep( array $value ) {
		$isLocked = ScopeValues::STATE_LOCK === $value['state'];
		$radios   = [
			ICL_TM_TMETHOD_ATE    => PolicyTable::EDITOR_ATE,
			ICL_TM_TMETHOD_EDITOR => PolicyTable::EDITOR_CTE,
		];

		$isATEEditor               = PolicyTable::EDITOR_ATE === $value['value'];
		$isClassicEditor           = PolicyTable::EDITOR_CTE === $value['value'];
		$t_method_radio_attributes = [];
		$show_old_jobs_checkbox    = false;
		foreach ( $radios as $radio => $editor ) {
			$t_method_radio_attributes[ $radio ] = [
				'data-column'  => PolicyTable::COLUMN_EDITOR,
				'data-value'   => $editor,
				'data-initial' => $value['value'],
			];
			if ( $isLocked ) {
				$t_method_radio_attributes[ $radio ]['disabled'] = 'disabled';
			}
		}
		?>
		<div class="wpml-translation-policy-settings__step" data-scope="<?php echo esc_attr( PolicyTable::SCOPE_SITE ); ?>" data-id="">
			<h3 class="wpml-translation-policy-settings__heading">
				<span class="wpml-translation-policy-settings__number">1.</span>
				<?php echo esc_html( Words::editorStep() ); ?>
			</h3>
			<?php include ICL_PLUGIN_PATH . '/menu/_translation_editor_comparison.php'; ?>
			<?php $this->renderSiteNote( PolicyTable::COLUMN_EDITOR, $this->editorNote( $value ), $isLocked ); ?>
		</div>

		<form id="icl_doc_translation_method" name="icl_doc_translation_method" action="">
			<?php wpml_nonce_field_with_id( 'icl_doc_translation_method_nonce', '_icl_nonce', 'icl_doc_translation_method_nonce' ); ?>
			<input type="hidden" name="<?php echo esc_attr( \WPML_TM_Old_Jobs_Editor::OPTION_NAME ); ?>" value="<?php echo esc_attr( (string) get_option( \WPML_TM_Old_Jobs_Editor::OPTION_NAME, \WPML_TM_Editors::WPML ) ); ?>"/>
			<p class="buttons-wrap">
				<span class="icl_ajx_response" id="icl_ajx_response_dtm"> </span>
				<input type="submit" class="button-primary" style="display: none;" value="<?php echo esc_attr( Words::save() ); ?>"/>
			</p>
			<?php
			do_action( 'wpml_doc_translation_method_below' );
			?>
		</form>
		<?php
	}

	private function renderMethodStep( array $value ) {
		$isLocked     = ScopeValues::STATE_LOCK === $value['state'];
		$chosenColumn = PolicyTable::METHOD_PTC === $value['value'] ? 't_method__classic-editor' : 't_method__ate-editor';
		$rows         = [
			[ Words::whoWritesRow(), esc_html( Words::whoWritesSelf() ), esc_html( Words::method( PolicyTable::METHOD_PTC ) ) ],
			[ Words::creditsRow(), '', $this->check() ],
			[ Words::newContentRow(), '', $this->newContentWithTranslateEverything() ],
		];
		?>
		<div class="wpml-translation-policy-settings__step" data-scope="<?php echo esc_attr( PolicyTable::SCOPE_SITE ); ?>" data-id="">
			<h3 class="wpml-translation-policy-settings__heading" id="wpml-translation-policy-method-heading">
				<span class="wpml-translation-policy-settings__number">2.</span>
				<?php echo esc_html( Words::methodStep() ); ?>
			</h3>
			<div class="wpml-section-content-inner">
				<div class="translation-method__table-container">
					<table class="t_method__table wpml-translation-policy-settings__method-table <?php echo esc_attr( $chosenColumn ); ?>" aria-labelledby="wpml-translation-policy-method-heading">
						<thead>
						<tr>
							<th></th>
							<th>
								<span class="column-heading"><?php echo esc_html( Words::method( PolicyTable::METHOD_SELF ) ); ?></span>
							</th>
							<th>
								<span class="recommendation-badge ate-recommended"><?php echo esc_html( Words::automaticPill() ); ?></span>
								<span class="column-heading"><?php echo esc_html( Words::method( PolicyTable::METHOD_PTC ) ); ?></span>
							</th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ( $rows as list( $heading, $self, $ptc ) ) : ?>
							<tr>
								<th scope="row" class="row-heading"><span><?php echo esc_html( $heading ); ?></span></th>
								<td class="check-td left"><div class="check-container"><?php echo wp_kses( $self, self::ALLOWED_CELL_HTML ); ?></div></td>
								<td class="check-td right"><div class="check-container"><?php echo wp_kses( $ptc, self::ALLOWED_CELL_HTML ); ?></div></td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<td></td>
							<?php foreach ( [ PolicyTable::METHOD_SELF => 'left', PolicyTable::METHOD_PTC => 'right' ] as $method => $side ) : ?>
								<td class="choose-method <?php echo esc_attr( $side ); ?>">
									<label class="wpml-radio wpml-radio-green">
										<input
											type="radio"
											name="wpml-translation-policy-site-method"
											value="<?php echo esc_attr( $method ); ?>"
											aria-label="<?php echo esc_attr( Words::method( $method ) ); ?>"
											data-column="<?php echo esc_attr( PolicyTable::COLUMN_METHOD ); ?>"
											data-initial="<?php echo esc_attr( $value['value'] ); ?>"
											<?php checked( $method, $value['value'] ); ?>
											<?php disabled( $isLocked ); ?>
										/>
										<span class="wpml-radio-label"></span>
									</label>
								</td>
							<?php endforeach; ?>
						</tr>
						</tbody>
					</table>
				</div>
			</div>
			<p class="description wpml-translation-policy-settings__hint">
				<?php echo wp_kses( $this->ptcHint(), self::ALLOWED_CELL_HTML ); ?>
			</p>
			<?php $this->renderSiteNote( PolicyTable::COLUMN_METHOD, $this->note( $value ), $isLocked ); ?>
		</div>
		<?php
	}

	private function check(): string {
		return '<i aria-label="' . esc_attr( Words::yes() ) . '" class="otgs-ico otgs-ico-ok"></i>';
	}

	private function newContentWithTranslateEverything(): string {
		return Words::newContentWithTranslateEverything( '<a href="' . esc_url( admin_url( UIPage::getTMDashboard() ) ) . '">', '</a>' );
	}

	private function renderTypesStep( TypeRows $typeRows ) {
		?>
		<div class="wpml-translation-policy-settings__step wpml-translation-policy-settings__types">
			<h3 class="wpml-translation-policy-settings__heading">
				<button type="button" class="wpml-translation-policy-settings__toggle" aria-expanded="false" aria-controls="wpml-translation-policy-types">
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
					<span class="wpml-translation-policy-settings__number">3.</span>
					<span class="wpml-translation-policy-settings__toggle-label"><?php echo esc_html( Words::typesStep() ); ?></span>
					<span class="wpml-translation-policy-settings__summary"><?php echo esc_html( $typeRows->summary() ); ?></span>
				</button>
			</h3>
			<div class="wpml-translation-policy-settings__panel" id="wpml-translation-policy-types" hidden>
				<?php $this->renderTypesPanel( $typeRows ); ?>
			</div>
		</div>
		<?php
	}

	public function typesPanel( TypeRows $typeRows ): string {
		ob_start();
		$this->renderTypesPanel( $typeRows );

		return (string) ob_get_clean();
	}

	private function renderTypesPanel( TypeRows $typeRows ) {
		$listed = $typeRows->listed();
		$others = $typeRows->others();
		?>
		<p class="description"><?php echo esc_html( Words::typesIntro() ); ?></p>
		<table class="widefat wpml-translation-policy-settings__table">
			<thead>
			<tr>
				<th scope="col"><?php echo esc_html( Words::postTypeColumn() ); ?></th>
				<th scope="col"><?php echo esc_html( Words::editorColumn() ); ?></th>
				<th scope="col"><?php echo esc_html( Words::methodColumn() ); ?></th>
			</tr>
			</thead>
			<?php if ( $listed ) : ?>
				<tbody>
				<?php foreach ( $listed as $row ) : ?>
					<?php $this->renderTypeRow( $typeRows, $row ); ?>
				<?php endforeach; ?>
				</tbody>
			<?php endif; ?>
			<?php if ( $others ) : ?>
				<tbody class="wpml-translation-policy-settings__others" hidden>
				<?php foreach ( $others as $row ) : ?>
					<?php $this->renderTypeRow( $typeRows, $row ); ?>
				<?php endforeach; ?>
				</tbody>
			<?php endif; ?>
		</table>
		<?php if ( $others ) : ?>
			<p>
				<button type="button" class="button-link wpml-translation-policy-settings__show-all" aria-controls="wpml-translation-policy-types">
					<?php echo esc_html( Words::showAll( count( $others ) ) ); ?>
				</button>
			</p>
		<?php endif; ?>
		<?php
	}

	private function renderTypeRow( TypeRows $typeRows, array $row ) {
		$noteId = $row['notes'] ? 'wpml-translation-policy-' . $row['slug'] . '-note' : '';
		?>
		<tr class="<?php echo $row['notes'] ? 'has-note' : ''; ?>" data-scope="<?php echo esc_attr( PolicyTable::SCOPE_TYPE ); ?>" data-id="<?php echo esc_attr( $row['slug'] ); ?>">
			<th scope="row"><?php echo esc_html( $row['name'] ); ?></th>
			<?php foreach ( ScopeValues::COLUMNS as $column ) : ?>
				<?php $value = $row['values'][ $column ]; ?>
				<td>
					<?php if ( ScopeValues::STATE_LOCK === $value['state'] ) : ?>
						<span class="wpml-translation-policy-settings__locked">
							<span class="dashicons dashicons-lock" aria-hidden="true"></span>
							<?php echo esc_html( $this->label( $column, $value['value'] ) ); ?>
						</span>
					<?php else : ?>
						<?php $this->select( $column, $value, $row['slug'], $typeRows->siteValueFor( $column, $row['slug'] ), $row['isFollowingSite'][ $column ], $noteId ); ?>
					<?php endif; ?>
				</td>
			<?php endforeach; ?>
		</tr>
		<?php if ( $row['notes'] ) : ?>
			<tr class="wpml-translation-policy-settings__note-row">
				<td colspan="3" id="<?php echo esc_attr( $noteId ); ?>">
					<?php foreach ( $row['notes'] as $note ) : ?>
						<p class="description">
							<?php if ( $row['isLocked'] ) : ?>
								<span class="dashicons dashicons-lock" aria-hidden="true"></span>
							<?php endif; ?>
							<?php echo esc_html( $note ); ?>
						</p>
					<?php endforeach; ?>
				</td>
			</tr>
		<?php endif; ?>
		<?php
	}

	private function renderSiteNote( string $column, string $note, bool $isLocked ) {
		?>
		<p class="description wpml-translation-policy-settings__site-note" data-note-for="<?php echo esc_attr( $column ); ?>"<?php echo '' === $note ? ' hidden' : ''; ?>>
			<?php if ( $isLocked ) : ?>
				<span class="dashicons dashicons-lock" aria-hidden="true"></span>
			<?php endif; ?>
			<span class="wpml-translation-policy-settings__note-text"><?php echo esc_html( $note ); ?></span>
			<span class="wpml-translation-policy-settings__status" role="status" aria-live="polite"></span>
		</p>
		<?php
	}

	public function editorNote( array $value ): string {
		if ( ScopeValues::STATE_LOCK === $value['state'] ) {
			return $this->note( $value );
		}

		return in_array( $value['value'], [ PolicyTable::EDITOR_ATE, PolicyTable::EDITOR_CTE ], true ) ? '' : Words::siteEditorNotInTable( $this->label( PolicyTable::COLUMN_EDITOR, $value['value'] ) );
	}

	private function select( string $column, array $value, string $postType, string $siteValue, bool $isFollowingSite, string $noteId ) {
		$id = 'wpml-translation-policy-' . $postType . '-' . $column;
		?>
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( PolicyTable::COLUMN_EDITOR === $column ? Words::editorColumn() : Words::methodColumn() ); ?></label>
		<select
			id="<?php echo esc_attr( $id ); ?>"
			class="<?php echo $isFollowingSite ? 'is-following-site' : ''; ?>"
			data-column="<?php echo esc_attr( $column ); ?>"
			data-initial="<?php echo esc_attr( $value['value'] ); ?>"
			data-follows-site="<?php echo $isFollowingSite ? '1' : '0'; ?>"
			<?php if ( '' !== $noteId ) : ?>
				aria-describedby="<?php echo esc_attr( $noteId ); ?>"
			<?php endif; ?>
		>
			<?php foreach ( $this->options( $column, $value, $postType, $siteValue ) as $option => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( (string) $option, $value['value'] ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function options( string $column, array $value, string $postType, $siteValue ): array {
		$options = [];
		if ( null !== $siteValue && ScopeValues::STATE_DEFAULT !== $value['state'] ) {
			$typeValue = Precedence::offersValue( $column, $siteValue, $postType ) ? $siteValue : ScopeValues::fallback( $column );

			$options[ ScopeValues::SAME_AS_SITE ] = Words::sameAsSite( $this->label( $column, $typeValue ) );
		}
		foreach ( PolicyTable::COLUMN_EDITOR === $column ? PolicyTable::EDITORS : PolicyTable::METHODS as $option ) {
			if ( PolicyTable::EDITOR_WPML === $option && $option !== $value['value'] ) {
				continue;
			}
			if ( Precedence::offersValue( $column, $option, $postType ) ) {
				$options[ $option ] = $this->label( $column, $option );
			}
		}

		return $options;
	}

	public function ptcHint(): string {
		return Words::ptcTranslateEverythingHint(
			'<a href="' . esc_url( admin_url( UIPage::getTMDashboard() ) ) . '">',
			'</a>'
		);
	}

	public function note( array $value ): string {
		switch ( $value['state'] ) {
			case ScopeValues::STATE_LOCK:
				return Words::lock( $value['source'], $value['reason'] );
			case ScopeValues::STATE_DEFAULT:
				if ( '' !== $value['source'] ) {
					return Words::pluginDefault();
				}

				return PolicyTable::REASON_MIGRATION === $value['reason'] ? Words::earlierDefault() : Words::siteDefault();
		}

		return '';
	}

	private function label( string $column, string $value ): string {
		return PolicyTable::COLUMN_EDITOR === $column ? Words::editor( $value ) : Words::method( $value );
	}

	private function enqueue() {
		wp_enqueue_style( self::SCRIPT, ICL_PLUGIN_URL . '/res/css/translation-policy-settings.css', [], ICL_SITEPRESS_SCRIPT_VERSION );
		wp_enqueue_script( self::SCRIPT, ICL_PLUGIN_URL . '/res/js/translation-policy-settings.js', [], ICL_SITEPRESS_SCRIPT_VERSION, true );
		wp_localize_script(
			self::SCRIPT,
			'wpmlTranslationPolicySettings',
			[
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'action'     => SaveEndpoint::ACTION,
				'nonce'      => wp_create_nonce( SaveEndpoint::NONCE ),
				'methodPtc'  => PolicyTable::METHOD_PTC,
				'fields'     => [
					'nonce' => SaveEndpoint::FIELD_NONCE,
					'items' => SaveEndpoint::FIELD_ITEMS,
				],
				'words'      => [
					'saving'   => Words::saving(),
					'notSaved' => Words::notSaved(),
				],
				'ptcConfirm' => [
					'url'       => rest_url( PtcConfirm::REST_NAMESPACE . PtcConfirm::ROUTE ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
					'words'     => PtcConfirm::dialogWords(),
				],
			]
		);
	}
}
