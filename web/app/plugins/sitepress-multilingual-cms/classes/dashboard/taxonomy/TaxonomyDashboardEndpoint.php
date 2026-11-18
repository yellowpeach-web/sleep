<?php

namespace WPML\TM\Dashboard\Taxonomy;

use WPML\LIB\WP\User;
use WP_REST_Request;
use WP_REST_Server;

class TaxonomyDashboardEndpoint extends \WPML_REST_Base implements \IWPML_Backend_Action, \IWPML_REST_Action {
	const TERM_NAMES_PER_BUCKET = 1000;

	public function __construct() {
		parent::__construct( \WPML_REST_Base::REST_NAMESPACE );
	}

	public function add_hooks() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		$this->register_route(
			'/dashboard/taxonomies',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'getRows' ],
				'args'     => [
					'since' => [
						'type'     => 'string',
						'required' => false,
					],
					'search' => [
						'type'     => 'string',
						'required' => false,
					],
				],
			]
		);

		foreach ( [ 'terms', 'branches' ] as $read ) {
			foreach ( [ '/', '//' ] as $slash ) {
				$this->register_route(
					'/dashboard/taxonomies/(?P<taxonomy>[a-zA-Z0-9_-]+)' . $slash . $read,
					[
						'methods'  => WP_REST_Server::READABLE,
						'callback' => [ $this, 'terms' === $read ? 'getTerms' : 'getBranches' ],
					]
				);
			}
		}

		$this->register_route(
			'/dashboard/taxonomies/preview',
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'preview' ],
				'args'     => [
					'taxonomies'      => [
						'type'     => 'object',
						'required' => true,
					],
					'targetLanguages' => [
						'type'     => 'array',
						'items'    => [ 'type' => 'string' ],
						'required' => true,
					],
					'overwrite'       => [
						'type'    => 'boolean',
						'default' => false,
					],
					'detail'          => [
						'type'     => 'string',
						'enum'     => [ 'terms' ],
						'required' => false,
					],
				],
			]
		);

		$this->register_route(
			'/dashboard/taxonomies/rescan-labels',
			[
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'rescanLabels' ],
				'args'     => [
					'taxonomy' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
		);
	}

	public function get_allowed_capabilities( WP_REST_Request $request ) {
		return [ User::CAP_ADMINISTRATOR, User::CAP_MANAGE_TRANSLATIONS ];
	}

	public function getRows( WP_REST_Request $request ) {
		$since  = (string) $request->get_param( 'since' );
		$search = trim( (string) $request->get_param( 'search' ) );

		$token = TaxonomyDashboardData::stateToken();

		if ( '' === $search && '' !== $since && $since === $token ) {
			return [
				'unchanged' => true,
				'token'     => $token,
			];
		}

		$data          = ( new TaxonomyDashboardData() )->get( $search, $token );
		$data['token'] = $token;

		return $data;
	}

	public function getTerms( WP_REST_Request $request ) {
		$ids = $request->get_param( 'ids' );
		$ids = null === $ids ? $request->get_param( 'include' ) : $ids;
		$ids = is_string( $ids ) ? explode( ',', $ids ) : $ids;
		try {
			$selection = $request->get_param( 'selection' );
			$selection = is_string( $selection ) ? json_decode( $selection, true ) : $selection;
			if ( null !== $request->get_param( 'selection' ) && ! is_array( $selection ) ) {
				throw new \InvalidArgumentException( 'Invalid taxonomy selection.' );
			}
			$languages = $request->get_param( 'targetLanguages' );
			$languages = is_string( $languages ) ? explode( ',', $languages ) : $languages;

			return $this->makeDashboardData()->getTermsPage(
				(string) $request->get_param( 'taxonomy' ),
				(string) ( $request->get_param( 'mode' ) ?: 'flat' ),
				(int) ( null !== $request->get_param( 'page' ) ? $request->get_param( 'page' ) : 1 ),
				(int) ( null !== $request->get_param( 'perPage' ) ? $request->get_param( 'perPage' ) : ( null !== $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 20 ) ),
				trim( (string) $request->get_param( 'search' ) ),
				is_array( $ids ) ? $ids : [],
				$selection,
				is_array( $languages ) ? array_values( array_map( 'strval', $languages ) ) : []
			);
		} catch ( \Throwable $e ) {
			return $this->readError( $e );
		}
	}

	public function preview( WP_REST_Request $request ) {
		try {
			$taxonomies = $request->get_param( 'taxonomies' );
			if ( ! is_array( $taxonomies ) ) {
				throw new \InvalidArgumentException( 'Invalid taxonomy selection.' );
			}
			$languages = $request->get_param( 'targetLanguages' );
			$languages = is_array( $languages ) ? array_values( array_map( 'strval', $languages ) ) : [];
			$overwrite = (bool) $request->get_param( 'overwrite' );
			$withTerms = 'terms' === $request->get_param( 'detail' );
			$data      = $this->makeDashboardData();
			$plan      = new TaxonomySelectionPlan( $data );
			$boundary  = $taxonomies ? $data->getLastTermTaxonomyId() : 0;
			$answers   = [];
			foreach ( $taxonomies as $taxonomy => $selection ) {
				$taxonomy = (string) $taxonomy;
				$built    = $plan->build(
					[
						'taxonomy'        => $taxonomy,
						'targetLanguages' => $languages,
						'overwrite'       => $overwrite,
						'selection'       => $selection ?? [ 'mode' => 'all', 'ids' => [], 'branches' => [] ],
						'lastElementId'   => $boundary,
						'withTermIds'     => $withTerms,
						'withWords'       => true,
					]
				);

				$answers[ $taxonomy ] = [
					'selected'               => $built['selected'],
					'addedParents'           => $data->describeTermsRootFirst( $taxonomy, $built['addedParents'] ),
					'perLanguage'            => $built['perLanguage'],
					'sent'                   => $built['sent'],
					'alreadyTranslated'      => $built['alreadyTranslated'],
					'inFlight'               => $built['inFlight'],
					'inTranslationWithPosts' => $built['inTranslationWithPosts'],
					'held'                   => $built['held'],
					'words'                  => $built['words'],
				];
				if ( $withTerms ) {
					$answers[ $taxonomy ]['terms'] = $this->namedTermsNotSent( $data, $taxonomy, $built['termIds'] ?? [] );
				}
			}

			return [ 'taxonomies' => $answers ?: new \stdClass() ];
		} catch ( \Throwable $e ) {
			return $this->readError( $e );
		}
	}

	private function namedTermsNotSent( TaxonomyDashboardData $data, string $taxonomy, array $termIds ): array {
		$all = [];
		foreach ( $termIds as $buckets ) {
			foreach ( $buckets as $ids ) {
				$all += array_fill_keys( $ids, true );
			}
		}
		ksort( $all, SORT_NUMERIC );
		$named = $all ? $data->describeTermsRootFirst( $taxonomy, array_keys( $all ) ) : [];
		$rank  = array_flip( array_column( $named, 'ttid' ) );

		$answer = [];
		foreach ( $termIds as $language => $buckets ) {
			foreach ( $buckets as $bucket => $ids ) {
				$ids = array_values(
					array_filter(
						$ids,
						function ( int $id ) use ( $rank ): bool {
							return isset( $rank[ $id ] );
						}
					)
				);
				usort(
					$ids,
					function ( int $left, int $right ) use ( $rank ): int {
						return $rank[ $left ] <=> $rank[ $right ];
					}
				);
				$answer[ $language ][ $bucket ] = [
					'items'     => array_map(
						function ( int $id ) use ( $named, $rank ): array {
							return $named[ $rank[ $id ] ];
						},
						array_slice( $ids, 0, self::TERM_NAMES_PER_BUCKET )
					),
					'truncated' => count( $ids ) > self::TERM_NAMES_PER_BUCKET,
				];
			}
		}

		return $answer;
	}

	public function getBranches( WP_REST_Request $request ) {
		try {
			$parents = $request->get_param( 'parents' );
			if ( null !== $parents ) {
				$parents = is_array( $parents ) ? $parents : explode( ',', (string) $parents );
				$parents = array_values( array_unique( array_map( 'intval', $parents ) ) );
				if ( ! $parents || count( $parents ) > 100 ) {
					throw new \InvalidArgumentException( 'A branch read takes 1 to 100 parents.' );
				}

				return $this->makeDashboardData()->getBranches(
					(string) $request->get_param( 'taxonomy' ),
					$parents,
					(string) ( $request->get_param( 'depth' ) ?: 'children' )
				);
			}

			return $this->makeDashboardData()->getBranch(
				(string) $request->get_param( 'taxonomy' ),
				(int) $request->get_param( 'parent' )
			);
		} catch ( \Throwable $e ) {
			return $this->readError( $e );
		}
	}

	protected function makeDashboardData(): TaxonomyDashboardData {
		return new TaxonomyDashboardData();
	}

	private function readError( \Throwable $e ): \WP_Error {
		$status = $e instanceof \InvalidArgumentException ? 400 : 500;

		return new \WP_Error( 'wpml_taxonomy_term_read', $e->getMessage(), [ 'status' => $status ] );
	}

	public function rescanLabels( WP_REST_Request $request ) {
		$taxonomy = (string) $request->get_param( 'taxonomy' );

		return $this->makeLabelStatus()->rescan( $taxonomy );
	}

	protected function makeLabelStatus(): TaxonomyLabelStatus {
		return new TaxonomyLabelStatus();
	}
}
