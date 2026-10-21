<?php

namespace ACFML\Notice;

use WPML\FP\Obj;
use WPML\FP\Str;

class Links {

	const DOC_ACFML_MAIN             = 'https://wpml.org/documentation/translating-your-contents/acf/';

	const DOC_DIFFERENT_TRANSLATION_EDITORS = 'https://wpml.org/documentation/translating-your-contents/using-different-translation-editors-for-different-pages/';
	const DOC_TRANSLATE_POST_TYPE           = 'https://wpml.org/documentation/getting-started-guide/translating-custom-fields/';
	const FAQ_INSTALL_ST                    = 'https://wpml.org/faq/how-to-add-string-translation-to-your-site/';

	private static function generate( $link, $params = [] ) {
		$anchor = Obj::prop( 'anchor', $params );

		$utmTags = wpml_collect( [
			'utm_source'   => 'plugin',
			'utm_medium'   => 'gui',
			'utm_campaign' => 'acfml',
			] )
			->merge( $params )
			->filter( function( $value, $key ) {
				return Str::startsWith( 'utm_', $key );
			} )
			->toArray();

		return add_query_arg( $utmTags, $anchor ? $link . '#' . $anchor : $link );
	}

	public static function getAcfmlMainDoc( $params = [] ) {
		return self::generate( self::DOC_ACFML_MAIN, $params );
	}

	public static function getAcfmlMainModeTranslationDoc( $params = [] ) {
		return self::getAcfmlMainDoc( array_merge( $params, [ 'anchor' => 'using-same-fields-across-languages' ] ) );
	}

	public static function getAcfmlMainModeLocalizationDoc( $params = [] ) {
		return self::getAcfmlMainDoc( array_merge( $params, [ 'anchor' => 'using-different-fields-across-languages' ] ) );
	}

	public static function getAcfmlExpertDoc( $params = [] ) {
		return self::getAcfmlMainDoc( array_merge( $params, [ 'anchor' => 'expert-mode' ] ) );
	}
	public static function getAcfmlTranslateLabels( $anchor = '' ) {
		return self::generate( self::DOC_ACFML_MAIN, [ 'anchor' => $anchor ] );
	}

	public static function getDifferentTranslationEditorsDoc( $params = [] ) {
		return self::generate( self::DOC_DIFFERENT_TRANSLATION_EDITORS, $params );
	}

	public static function getFaqInstallST() {
		return self::generate( self::FAQ_INSTALL_ST );
	}

	public static function getDocTranslatePostType() {
		return self::generate( self::DOC_TRANSLATE_POST_TYPE );
	}
}
