<?php

namespace WPML\PB\Shortcode;

use WPML\Convert\Ids;
use WPML_PB_Config_Import_Shortcode;

class AdjustIdsHooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private const RESOLUTION_RESOLVED  = 'resolved';
	private const RESOLUTION_UNCHANGED = 'unchanged';

	private $config;

	private $linkTargets;

	private $permalinks;

	private $convertedLinks = [];

	private $rerenderedTag;

	private $idSettings;

	private $linkSettings;

	public function __construct( WPML_PB_Config_Import_Shortcode $config, ?\WPML_Translate_Link_Targets $linkTargets = null, ?\WPML_Absolute_To_Permalinks $permalinks = null ) {
		$this->config      = $config;
		$this->linkTargets = $linkTargets;
		$this->permalinks  = $permalinks;
	}

	public function add_hooks() {
		add_filter( 'pre_do_shortcode_tag', [ $this, 'convertAttributeIds' ], - PHP_INT_MAX, 4 );

		foreach ( [ WPML_PB_Config_Import_Shortcode::PB_IDS_SHORTCODE_SETTING, WPML_PB_Config_Import_Shortcode::PB_LINKS_SHORTCODE_SETTING ] as $option ) {
			foreach ( [ 'add_option_', 'update_option_', 'delete_option_' ] as $action ) {
				add_action( $action . $option, [ $this, 'forgetSettings' ] );
			}
		}
	}

	public function forgetSettings() {
		$this->idSettings   = null;
		$this->linkSettings = null;
	}

	public function convertAttributeIds( $bool, $tag, $attr, $m ) {
		if ( $tag === $this->rerenderedTag ) {
			$this->rerenderedTag = null;

			return $bool;
		}

		$tagConfig     = $this->getConfig( $tag );
		$tagLinkConfig = $this->getLinkConfig( $tag );

		if ( $tagConfig || $tagLinkConfig ) {
			$convertLink = function( $url ) {
				return $this->convertLink( $url );
			};

			$m = $this->convertIdAttributes( $m, $tag );

			foreach ( (array) $tagLinkConfig as $attribute => $setting ) {
				$m = WPML_PB_Config_Import_Shortcode::replace_attribute_values( $m, $attribute, $setting, $convertLink );
			}

			$this->rerenderedTag = $tag;

			try {
				return do_shortcode_tag( $m );
			} finally {
				$this->rerenderedTag = null;
			}
		}

		return $bool;
	}

	public function hasIdAttributes( $tag ) {
		return (bool) $this->getConfig( $tag );
	}

	public function convertIdAttributes( $subject, $tag ) {
		$convertIds = fn( $ids, $type ) => Ids::convert( $ids, $type, true );

		foreach ( (array) $this->getConfig( $tag ) as $attribute => $setting ) {
			$subject = WPML_PB_Config_Import_Shortcode::replace_attribute_values( $subject, $attribute, $setting, $convertIds );
		}

		return $subject;
	}

	private function getConfig( $tag ) {
		if ( null === $this->idSettings ) {
			$this->idSettings = (array) $this->config->get_id_settings();
		}

		return isset( $this->idSettings[ $tag ] ) ? $this->idSettings[ $tag ] : null;
	}

	private function getLinkConfig( $tag ) {
		if ( null === $this->linkSettings ) {
			$this->linkSettings = (array) $this->config->get_link_settings();
		}

		return isset( $this->linkSettings[ $tag ] ) ? $this->linkSettings[ $tag ] : null;
	}

	private function convertLink( $url ) {
		$isHttp = (bool) preg_match( '#^https?://#i', $url );

		if ( ! $isHttp && ! $this->isRootRelativeWithQuery( $url ) ) {
			return $url;
		}

		$language = apply_filters( 'wpml_current_language', null );
		$key      = $language . '|' . $url;

		if ( ! isset( $this->convertedLinks[ $key ] ) ) {
			$this->convertedLinks[ $key ] = $isHttp
				? $this->resolveLink( $url, $language )
				: $this->showStickyLink( $url );
		}

		return $this->convertedLinks[ $key ];
	}

	private function resolveLink( $url, $language ) {
		$resolution = apply_filters( 'wpml_resolve_single_url', null, $url, $language );

		return $this->answersLikeAContentLink( $resolution, $url, $language )
			? $resolution['url']
			: $this->getLinkTargets()->convert_url( $url );
	}

	private function answersLikeAContentLink( $resolution, $url, $language ) {
		if ( ! isset( $resolution['url'], $resolution['resolution_state'] ) || ! is_string( $resolution['url'] ) ) {
			return false;
		}

		$isUnchanged = self::RESOLUTION_UNCHANGED === $resolution['resolution_state'];

		if (
			self::RESOLUTION_RESOLVED === $resolution['resolution_state']
			|| ( $isUnchanged && isset( $resolution['source_language'] ) && $language === $resolution['source_language'] )
		) {
			return $this->namesALinkablePost( $resolution );
		}

		return $isUnchanged
			&& empty( $resolution['source_post_id'] )
			&& ! \WPML_Same_Site_Url_Normalizer::is_same_site( $url );
	}

	private function namesALinkablePost( array $resolution ) {
		$postId = empty( $resolution['translated_post_id'] )
			? ( $resolution['source_post_id'] ?? null )
			: $resolution['translated_post_id'];

		if ( empty( $postId ) ) {
			return true;
		}

		$post = get_post( $postId );

		return $post
			&& is_post_status_viewable( get_post_status( $post ) )
			&& \WPML_Absolute_To_Permalinks::is_linkable( $post );
	}

	private function isRootRelativeWithQuery( $url ) {
		return '/' === substr( $url, 0, 1 ) && '/' !== substr( $url, 1, 1 ) && false !== strpos( $url, '?' );
	}

	private function showStickyLink( $url ) {
		$prefix = '<a href="';
		$suffix = '">removeit</a>';
		$link   = $this->getPermalinks()->convert_text( $prefix . $url . $suffix );

		if (
			! is_string( $link )
			|| 0 !== strpos( $link, $prefix )
			|| substr( $link, - strlen( $suffix ) ) !== $suffix
		) {
			return $url;
		}

		return substr( $link, strlen( $prefix ), - strlen( $suffix ) );
	}

	private function getLinkTargets() {
		if ( ! $this->linkTargets ) {
			global $sitepress;

			$this->linkTargets = new \WPML_Translate_Link_Targets( new \AbsoluteLinks(), new \WPML_Absolute_To_Permalinks( $sitepress ) );
		}

		return $this->linkTargets;
	}

	private function getPermalinks() {
		if ( ! $this->permalinks ) {
			global $sitepress;

			$this->permalinks = new \WPML_Absolute_To_Permalinks( $sitepress );
		}

		return $this->permalinks;
	}
}
