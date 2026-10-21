<?php

namespace WPML\TM\ATE\Log;

use WPML\Collect\Support\Collection;

class View {

	private $logs;

	public function __construct( Collection $logs ) {
		$this->logs = $logs;
	}

	public function renderPage() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Advanced Translation Editor Error Logs', 'sitepress' ); ?></h1>
			<br>
			<table class="wp-list-table widefat fixed striped posts">
				<thead><?php $this->renderTableHeader(); ?></thead>

				<tbody id="the-list">
				<?php
				if ( $this->logs->isEmpty() ) {
					$this->renderEmptyTable();
				} else {
					$this->logs->each( [ $this, 'renderTableRow' ] );
				}
				?>
				</tbody>
				<tfoot><?php $this->renderTableHeader(); ?></tfoot>
			</table>
		</div>
		<?php
	}

	private function renderTableHeader() {
		?>
		<tr>
			<th class="date">
				<span><?php /* translators: Column heading in a table: the date something happened. */ esc_html_e( 'Date', 'sitepress' ); ?></span>
			</th>
			<th class="event">
				<span><?php /* translators: Column heading in the log of automatic translation: what happened. */ esc_html_e( 'Event', 'sitepress' ); ?></span>
			</th>
			<th class="description">
				<span><?php /* translators: Column heading and field label for the longer text that describes something. */ esc_html_e( 'Description', 'sitepress' ); ?></span>
			</th>
			<th class="wpml-job-id">
				<span><?php esc_html_e( 'WPML Job ID', 'sitepress' ); ?></span>
			</th>
			<th class="ate-job-id">
				<span><?php esc_html_e( 'ATE Job ID', 'sitepress' ); ?></span>
			</th>
			<th class="extra-data">
				<span><?php /* translators: Column heading in the log of automatic translation, above the further details of an event. */ esc_html_e( 'Extra data', 'sitepress' ); ?></span>
			</th>
		</tr>
		<?php
	}

	public function renderTableRow( Entry $entry ) {
		?>
		<tr>
			<td class="date">
				<?php echo esc_html( $entry->getFormattedDate() ); ?>
			</td>
            <td class="event">
				<?php echo esc_html( EventsTypes::getLabel( $entry->eventType ) ); ?>
            </td>
			<td class="description">
				<?php echo esc_html( $entry->description ); ?>
			</td>
			<td class="wpml-job-id">
				<?php echo esc_html( (string) $entry->wpmlJobId ); ?>
			</td>
			<td class="ate-job-id">
				<?php echo esc_html( (string) $entry->ateJobId ); ?>
			</td>
			<td class="extra-data">
				<?php echo esc_html( $entry->getExtraDataToString() ); ?>
			</td>
		</tr>
		<?php
	}

	private function renderEmptyTable() {
		?>
		<tr>
			<td colspan="6" class="title column-title has-row-actions column-primary">
				<?php /* translators: Shown in place of a table when the log holds nothing. */ esc_html_e( 'No entries', 'sitepress' ); ?>
			</td>
		</tr>
		<?php
	}
}
