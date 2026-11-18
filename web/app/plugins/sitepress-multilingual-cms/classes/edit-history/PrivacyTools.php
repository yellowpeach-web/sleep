<?php

namespace WPML\EditHistory;

class PrivacyTools {

	const ID = 'wpml-translation-edits';

	const PAGE = 100;

	private $data;

	public function __construct( PersonalData $data ) {
		$this->data = $data;
	}

	public function addExporter( $exporters ) {
		$exporters[ self::ID ] = [
			'exporter_friendly_name' => self::label(),
			'callback'               => [ $this, 'export' ],
		];

		return $exporters;
	}

	public function addEraser( $erasers ) {
		$erasers[ self::ID ] = [
			'eraser_friendly_name' => self::label(),
			'callback'             => [ $this, 'erase' ],
		];

		return $erasers;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		$entries = $this->data->byUser( (int) $user->ID, self::PAGE, ( max( 1, (int) $page ) - 1 ) * self::PAGE );

		return [
			'data' => array_map( [ $this, 'item' ], $entries ),
			'done' => count( $entries ) < self::PAGE,
		];
	}

	public function erase( $email ) {
		$user = get_user_by( 'email', $email );

		return [
			'items_removed'  => $user && $this->data->forgetUser( (int) $user->ID ) > 0,
			'items_retained' => false,
			'messages'       => [],
			'done'           => true,
		];
	}

	private static function label(): string {
		return __( 'WPML translation edits', 'sitepress' );
	}

	private function item( Entry $entry ): array {
		$postId = $entry->subject->id();

		return [
			'group_id'          => self::ID,
			'group_label'       => self::label(),
			'group_description' => __( 'Your saves of translated content, as WPML records them for the next translation update.', 'sitepress' ),
			'item_id'           => self::ID . '-' . $entry->id,
			'data'              => [
				[
					'name'  => __( 'Post', 'sitepress' ),
					'value' => sprintf( '%s (#%d)', get_the_title( $postId ), $postId ),
				],
				[
					'name'  => __( 'Language', 'sitepress' ),
					'value' => $entry->language,
				],
				[
					'name'  => __( 'Saved at (UTC)', 'sitepress' ),
					'value' => gmdate( 'Y-m-d H:i:s', $entry->time ),
				],
				[
					'name'  => __( 'Source', 'sitepress' ),
					'value' => $entry->source,
				],
				[
					'name'  => __( 'Editor', 'sitepress' ),
					'value' => $entry->editor,
				],
			],
		];
	}
}
