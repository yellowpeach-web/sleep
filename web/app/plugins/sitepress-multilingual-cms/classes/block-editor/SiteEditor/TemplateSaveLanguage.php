<?php

namespace WPML\BlockEditor\SiteEditor;

use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;

class TemplateSaveLanguage implements \IWPML_REST_Action, \IWPML_DIC_Action {

	const LANGUAGE_PARAM = 'wpml_language';

	const ERROR_HIDDEN_ROW = 'wpml_template_exists_in_language';

	private $sitepress;

	private $saves = [];

	private static $current = null;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		self::$current = $this;
		add_filter( 'rest_request_before_callbacks', [ $this, 'enter' ], 9, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'leave' ] );
	}

	public function enter( $response, $handler, $request ) {
		$rules = ! ( $response instanceof \WP_Error )
			&& $request instanceof \WP_REST_Request
			&& $this->isTemplatesSave( $handler );

		if ( ! $rules ) {
			$this->saves[] = false;

			return $response;
		}

		$language = $this->ruledLanguage( $request );

		$refusal = 'update_item' === $handler['callback'][1]
			? $this->refusalForHiddenRow( $request, $language )
			: null;

		if ( $refusal ) {
			$this->saves[] = false;

			return $refusal;
		}

		$this->saves[] = $language;

		return $response;
	}

	private function refusalForHiddenRow( \WP_REST_Request $request, $language ) {
		$id = $request->get_param( 'id' );
		if ( ! is_string( $id ) || '' === $id ) {
			return null;
		}

		$type = false !== strpos( (string) $request->get_route(), '/template-parts/' ) ? 'wp_template_part' : 'wp_template';

		if ( $this->customRowExists( $id, $type ) ) {
			return null;
		}

		$adminLanguage = (string) $this->sitepress->get_admin_language();

		$this->sitepress->switch_lang( $language );
		try {
			$hidden = $this->customRowExists( $id, $type );
		} finally {
			$this->sitepress->switch_lang();
		}

		if ( ! $hidden ) {
			return null;
		}

		$name = (string) $this->sitepress->get_display_language_name( $language, $adminLanguage );

		if ( 'wp_template_part' === $type ) {
			$message = sprintf(
				/* translators: %1$s and %2$s: the name of a language (e.g. English). Shown in the Site Editor when saving a template part would overwrite a customized version of it that exists in another language and is not listed in the current one. */
				__( 'A customized version of this template part already exists in %1$s. Open it in %2$s to edit it.', 'wpml' ),
				$name,
				$name
			);
		} else {
			$message = sprintf(
				/* translators: %1$s and %2$s: the name of a language (e.g. English). Shown in the Site Editor when saving a template would overwrite a customized version of it that exists in another language and is not listed in the current one. */
				__( 'A customized version of this template already exists in %1$s. Open it in %2$s to edit it.', 'wpml' ),
				$name,
				$name
			);
		}

		return new \WP_Error( self::ERROR_HIDDEN_ROW, $message, [ 'status' => 409, 'language' => $language ] );
	}

	private function customRowExists( $id, $type ) {
		$template = get_block_template( $id, $type );

		return is_object( $template ) && ! empty( $template->wp_id );
	}

	public function leave( $response ) {
		array_pop( $this->saves );

		return $response;
	}

	public static function forNewRow( $post_id ) {
		$language = self::languageForRowBeingCreated();

		return null !== $language && SiteEditorEntityTypes::isTemplateType( (string) get_post_type( (int) $post_id ) ) ? $language : null;
	}

	public static function languageForRowBeingCreated() {
		$rule = self::$current;
		if ( ! $rule || ! $rule->saves ) {
			return null;
		}

		$language = end( $rule->saves );

		return is_string( $language ) ? $language : null;
	}

	public static function useForTesting( $instance ) {
		self::$current = $instance;
	}

	private function isTemplatesSave( $handler ) {
		if ( ! is_array( $handler ) || empty( $handler['callback'] ) || ! is_array( $handler['callback'] ) ) {
			return false;
		}

		$callback = $handler['callback'];

		return isset( $callback[0], $callback[1] )
			&& $callback[0] instanceof \WP_REST_Templates_Controller
			&& in_array( $callback[1], [ 'create_item', 'update_item' ], true );
	}

	private function ruledLanguage( \WP_REST_Request $request ) {
		$sent = $this->activeOrNull( $request->get_param( self::LANGUAGE_PARAM ) );
		if ( $sent ) {
			return $sent;
		}

		$page = $this->activeOrNull( $this->refererLanguage() );
		if ( $page ) {
			return $page;
		}

		return (string) $this->sitepress->get_default_language();
	}

	private function refererLanguage() {
		$referer = wp_get_raw_referer();
		if ( ! is_string( $referer ) || '' === $referer ) {
			return null;
		}

		return preg_match( '/[?&]lang=([^&#]*)/', $referer, $match ) ? rawurldecode( $match[1] ) : null;
	}

	private function activeOrNull( $code ) {
		if ( ! is_string( $code ) || '' === $code || 'all' === $code ) {
			return null;
		}

		return $this->sitepress->is_active_language( $code ) ? $code : null;
	}
}
