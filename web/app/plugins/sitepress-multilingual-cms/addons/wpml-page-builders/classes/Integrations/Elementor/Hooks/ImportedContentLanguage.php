<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\User;

class ImportedContentLanguage implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const NOTICE_USER_META = 'wpml_pb_elementor_imported_content_language_notice';

	const NOTICE_CSS_CLASS = 'wpml-imported-content-language-notice';

	const KIT_IMPORT_ROUTE = '#^/elementor/v1/import-export-customization/(import|import-runner|process-media)$#';

	const MCP_PROXY_ROUTE = '#^/elementor/v1/mcp-proxy$#';

	const KIT_IMPORT_AJAX_ACTIONS = [ 'elementor_import_kit', 'elementor_import_kit__runner' ];

	const KIT_IMPORT_ACTIONS = [
		'elementor/import-export/import-kit',
		'elementor/import-export-customization/import-kit',
	];

	const TEMPLATE_UPLOAD_DIRECT_ACTION = 'elementor_library_direct_actions';

	const TEMPLATE_UPLOAD_LIBRARY_ACTION = 'direct_import_template';

	const ELEMENTOR_AJAX_ACTION = 'elementor_ajax';

	const TEMPLATE_UPLOAD_AJAX_ACTION = 'import_template';

	const TEMPLATES_ELEMENT_TYPE = 'post_elementor_library';

	private $sitepress;

	private $importDepth = 0;

	private $isKitImport = false;

	private $isLanguageChanged = false;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		Hooks::onFilter( 'wpml_new_post_language' )
			->then( function ( $args ) {
				return $this->getImportedPostLanguage( ...$args );
			} );

		Hooks::onFilter( 'wpml_create_term_lang' )
			->then( function ( $args ) {
				return $this->getImportedTermLanguage( ...$args );
			} );

		Hooks::onFilter( 'rest_request_before_callbacks', 10, 3 )
			->then( function ( $args ) {
				return $this->startRestImport( ...$args );
			} );

		Hooks::onFilter( 'rest_request_after_callbacks', 10, 3 )
			->then( function ( $args ) {
				return $this->stopRestImport( ...$args );
			} );

		Hooks::onAction( self::KIT_IMPORT_ACTIONS )
			->then( [ $this, 'startImport' ] );

		Hooks::onFilter( 'wp_register_ability_args', 10, 2 )
			->then( function ( $args ) {
				return $this->wrapAbility( ...$args );
			} );

		Hooks::onAction( 'elementor/template-library/after_save_template', 10, 2 )
			->then( function ( $args ) {
				$this->setCloudTemplateLanguage( ...$args );
			} );

		Hooks::onAction( 'shutdown' )
			->then( [ $this, 'requestNotice' ] );

		Hooks::onAction( 'admin_notices' )
			->then( [ $this, 'showNotice' ] );
	}

	public function getImportedPostLanguage( $languageCode ) {
		return $this->isImporting() ? $this->getDefaultLanguage( $languageCode ) : $languageCode;
	}

	public function getImportedTermLanguage( $languageCode ) {
		return doing_action( 'create_term' ) && $this->isImporting() ? $this->getDefaultLanguage( $languageCode ) : $languageCode;
	}

	private function getDefaultLanguage( $languageCode ) {
		$defaultLanguage = $this->sitepress->get_default_language();
		if ( $languageCode !== $defaultLanguage ) {
			$this->isLanguageChanged = true;
		}

		return $defaultLanguage;
	}

	public function startRestImport( $response, $handler, $request ) {
		if ( $this->isImportRequest( $request ) ) {
			$this->importDepth++;
		}

		return $response;
	}

	public function stopRestImport( $response, $handler, $request ) {
		if ( $this->isImportRequest( $request ) ) {
			$this->stopImport();
		}

		return $response;
	}

	public function startImport() {
		$this->isKitImport = true;
	}

	public function wrapAbility( $args, $abilityName ) {
		if ( ! $this->isElementorAbility( $abilityName ) || ! $this->hasExecuteCallback( $args ) ) {
			return $args;
		}

		$execute = $args['execute_callback'];

		$args['execute_callback'] = function ( ...$input ) use ( $execute ) {
			$this->importDepth++;
			try {
				return $execute( ...$input );
			} finally {
				$this->stopImport();
			}
		};

		return $args;
	}

	private function hasExecuteCallback( $args ) {
		return is_array( $args ) && isset( $args['execute_callback'] ) && is_callable( $args['execute_callback'] );
	}

	private function stopImport() {
		$this->importDepth = max( 0, $this->importDepth - 1 );
	}

	public function setCloudTemplateLanguage( $templateId, $templateData ) {
		if ( ! is_array( $templateData ) || 'cloud' !== ( $templateData['from_source'] ?? null ) ) {
			return;
		}

		$elementType     = self::TEMPLATES_ELEMENT_TYPE;
		$defaultLanguage = $this->sitepress->get_default_language();
		$details         = $this->sitepress->get_element_language_details( $templateId, $elementType );

		if ( ! $details || $details->language_code === $defaultLanguage || $this->hasTranslations( (int) $details->trid ) ) {
			return;
		}

		$this->sitepress->set_element_language_details( $templateId, $elementType, (int) $details->trid, $defaultLanguage );
		$this->isLanguageChanged = true;
	}

	public function requestNotice() {
		$userId = User::getCurrentId();

		if ( $this->isLanguageChanged && $userId ) {
			update_user_meta( $userId, self::NOTICE_USER_META, 1 );
		}
	}

	public function showNotice() {
		$userId = User::getCurrentId();

		if ( ! $userId || ! get_user_meta( $userId, self::NOTICE_USER_META, true ) ) {
			return;
		}

		delete_user_meta( $userId, self::NOTICE_USER_META );

		$languageName = $this->sitepress->get_display_language_name(
			$this->sitepress->get_default_language(),
			$this->sitepress->get_admin_language()
		);

		echo '<div class="notice notice-info is-dismissible ' . esc_attr( self::NOTICE_CSS_CLASS ) . '"><p>' . sprintf(
			/* translators: %s is a language name, for example "English". */
			esc_html__( 'WPML set the language of the content you imported with Elementor to %s, the default language of your site.', 'sitepress' ),
			'<strong>' . esc_html( $languageName ) . '</strong>'
		) . '</p></div>';
	}

	private function isImportRequest( $request ) {
		return $request instanceof \WP_REST_Request
			&& 'POST' === $request->get_method()
			&& $this->isImportRoute( $request->get_route() );
	}

	private function isImportRoute( $route ) {
		return preg_match( self::KIT_IMPORT_ROUTE, $route ) || preg_match( self::MCP_PROXY_ROUTE, $route );
	}

	private function isElementorAbility( $abilityName ) {
		return is_string( $abilityName ) && 0 === strpos( $abilityName, 'elementor/' );
	}

	private function isImporting() {
		return $this->importDepth > 0 || $this->isKitImport || $this->isKitImportAjax() || $this->isTemplateUploadAjax();
	}

	private function isKitImportAjax() {
		if ( ! wpml_is_ajax() ) {
			return false;
		}

		return in_array( $this->getPostValue( 'action' ), self::KIT_IMPORT_AJAX_ACTIONS, true );
	}

	private function isTemplateUploadAjax() {
		if ( ! wpml_is_ajax() ) {
			return false;
		}

		$action = $this->getPostValue( 'action' );

		if ( self::TEMPLATE_UPLOAD_DIRECT_ACTION === $action ) {
			return self::TEMPLATE_UPLOAD_LIBRARY_ACTION === $this->getPostValue( 'library_action' );
		}

		if ( self::ELEMENTOR_AJAX_ACTION !== $action ) {
			return false;
		}

		$requests = isset( $_POST['actions'] ) && is_string( $_POST['actions'] ) ? json_decode( wp_unslash( $_POST['actions'] ), true ) : null;

		if ( ! is_array( $requests ) ) {
			return false;
		}

		foreach ( $requests as $request ) {
			if ( is_array( $request ) && self::TEMPLATE_UPLOAD_AJAX_ACTION === ( $request['action'] ?? null ) ) {
				return true;
			}
		}

		return false;
	}

	private function getPostValue( $key ) {
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	private function hasTranslations( $trid ) {
		return count( (array) $this->sitepress->get_element_translations( $trid, self::TEMPLATES_ELEMENT_TYPE ) ) > 1;
	}
}
