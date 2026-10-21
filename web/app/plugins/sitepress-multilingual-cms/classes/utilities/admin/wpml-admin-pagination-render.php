<?php

class WPML_Admin_Pagination_Render {

	const TEMPLATE = 'pagination.twig';

	private $template;

	private $pagination;

	public function __construct( IWPML_Template_Service $template, WPML_Admin_Pagination $pagination ) {
		$this->template   = $template;
		$this->pagination = $pagination;
	}

	public function get_model() {
		return [
			'strings'     => self::get_strings( $this->pagination->get_total_items() ),
			'pagination'  => $this->pagination,
			'total_items' => $this->pagination->get_total_items(),
		];
	}

	public static function get_strings( $totalItems ) {
		return [
			/* translators: Screen reader name of the row of buttons that moves between the pages of a list. */
			'listNavigation' => __( 'Navigation', 'sitepress' ),
			/* translators: Screen reader name of the button that goes to the first page of a list. */
			'firstPage'      => __( 'First page', 'sitepress' ),
			/* translators: Screen reader name of the button that goes back one page in a list. */
			'previousPage'   => __( 'Previous page', 'sitepress' ),
			/* translators: Screen reader name of the button that goes on one page in a list. */
			'nextPage'       => __( 'Next page', 'sitepress' ),
			/* translators: Screen reader name of the button that goes to the last page of a list. */
			'lastPage'       => __( 'Last page', 'sitepress' ),
			/* translators: Screen reader name of the field that holds the number of the page being shown. */
			'currentPage'    => __( 'Current page', 'sitepress' ),
			/* translators: Word between two numbers above a list, as in "Displaying 1 of 20": the first is what is shown, the second is how many there are in all. */
			'of'             => __( 'of', 'sitepress' ),
			'totalItemsText' => sprintf(
				/* translators: How many rows a list holds, shown above it, as in "1 item" or "25 items". %s: that number. */
				_n( '%s item', '%s items', $totalItems, 'sitepress' ),
				$totalItems
			),
		];
	}

	public function paginate( $items ) {
		$total       = count( $items );
		$limit       = $this->pagination->get_items_per_page();
		$total_pages = ceil( $total / $limit );
		$page        = max( $this->pagination->get_current_page(), 1 );
		$page        = min( $page, $total_pages );
		$offset      = ( $page - 1 ) * $limit;

		if ( $offset < 0 ) {
			$offset = 0;
		}

		return array_slice( $items, (int) $offset, $limit );

	}
}
