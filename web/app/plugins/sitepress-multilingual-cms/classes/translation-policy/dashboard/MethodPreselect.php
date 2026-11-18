<?php

namespace WPML\TranslationPolicy\Dashboard;

use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationMethod\TargetLanguageMethodType;
use WPML\TranslationPolicy\Policy\Policy;
use WPML\TranslationPolicy\Policy\PolicyAnswer;
use WPML\TranslationPolicy\Policy\PolicyReaderInterface;
use WPML\TranslationPolicy\Policy\PolicyTable;

class MethodPreselect implements \IWPML_Backend_Action {

	const SCRIPT_DATA_KEY = 'translationPolicyMethods';

	const DASHBOARD_METHODS = [
		PolicyTable::METHOD_PTC  => TargetLanguageMethodType::AUTOMATIC,
		PolicyTable::METHOD_SELF => 'myself',
	];

	private $reader;

	public function __construct( $reader = null ) {
		$this->reader = $reader;
	}

	public function add_hooks() {
		add_filter( 'wpml_dashboard_initial_script_data', [ $this, 'addMethods' ] );
	}

	public function addMethods( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$reader = $this->reader ?? Policy::reader();
		$site   = self::dashboardMethod( $reader->site() );

		if ( null === $site ) {
			return $data;
		}

		$types = [];
		foreach ( self::postTypes( $data ) as $postType ) {
			$method = self::dashboardMethod( $reader->forType( $postType ) );
			if ( null !== $method ) {
				$types[ $postType ] = $method;
			}
		}

		$data[ self::SCRIPT_DATA_KEY ] = [
			'site'  => $site,
			'types' => (object) $types,
		];

		return $data;
	}

	private static function dashboardMethod( PolicyAnswer $answer ) {
		$method = $answer->method();

		return is_string( $method ) && isset( self::DASHBOARD_METHODS[ $method ] ) ? self::DASHBOARD_METHODS[ $method ] : null;
	}

	private static function postTypes( array $data ): array {
		$sections = $data['itemSections'] ?? [];
		if ( ! is_array( $sections ) ) {
			return [];
		}

		$postTypes = [];
		foreach ( $sections as $section ) {
			$postType = self::postTypeOfSection( $section );
			if ( null !== $postType ) {
				$postTypes[] = $postType;
			}
		}

		return $postTypes;
	}

	private static function postTypeOfSection( $section ) {
		$kind = is_array( $section ) ? ( $section['kind'] ?? null ) : null;
		if ( ! is_array( $kind ) || 'post' !== ( $kind['id'] ?? null ) ) {
			return null;
		}

		$postType = $kind['type'] ?? null;

		return is_string( $postType ) && '' !== $postType ? $postType : null;
	}
}
