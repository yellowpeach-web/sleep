<?php

namespace WPML\Compatibility\Divi\ThemeBuilderRepair;

class LayoutLinks {

	const KINDS = [ 'header', 'body', 'footer' ];

	const ACTION_LINK = 'link';
	const ACTION_SKIP = 'skip';

	const REASON_EXISTS            = 'translation-exists';
	const REASON_GROUP             = 'translation-group';
	const REASON_OTHER_LANGUAGE    = 'other-language';
	const REASON_NO_ORIGINAL_ROW   = 'original-without-language';
	const REASON_NOT_TRANSLATABLE  = 'not-translatable';
	const REASON_MISSING           = 'missing';

	private $store;

	public function __construct( Store $store ) {
		$this->store = $store;
	}

	public static function countToLink( array $links ) {
		return count(
			array_filter(
				$links,
				function ( $link ) {
					return self::ACTION_LINK === $link['action'];
				}
			)
		);
	}

	public function plan( array $keep, array $sources ) {
		if ( ! $sources ) {
			return [];
		}

		$defaultLanguage = $this->store->defaultLanguage();
		$keptLanguage    = null === $keep['language'] ? $defaultLanguage : $keep['language'];

		$keptTemplates = [];
		foreach ( $this->store->templates( $keep['id'] ) as $template ) {
			$keptTemplates[ $template['id'] ] = $this->store->templateFacts( $template['id'] );
		}

		$links   = [];
		$planned = [];
		$taken   = [];
		foreach ( $sources as $source ) {
			$language = $source['language'];
			if ( null === $language || $language === $keptLanguage ) {
				continue;
			}

			foreach ( $this->store->templates( $source['id'] ) as $template ) {
				$facts = $this->store->templateFacts( $template['id'] );
				$match = $this->match( $facts, $keptTemplates );
				if ( ! $match ) {
					continue;
				}

				foreach ( self::KINDS as $kind ) {
					$layout   = $facts['layouts'][ $kind ];
					$original = $match['layouts'][ $kind ];
					if ( ! $layout || ! $original || $layout === $original || isset( $planned[ $layout ] ) ) {
						continue;
					}

					$link = $this->decide( $kind, $layout, $original, $language, $taken );
					if ( null === $link ) {
						continue;
					}

					$planned[ $layout ] = true;
					$links[]            = [
						'template' => $template['id'],
						'kind'     => $kind,
						'layout'   => $layout,
						'original' => $original,
						'language' => $language,
						'action'   => $link['action'],
						'reason'   => $link['reason'],
					];
				}
			}
		}

		return $links;
	}

	public function apply( array $links ) {
		$linked = [];
		$done   = [];
		foreach ( $links as $link ) {
			if ( self::ACTION_LINK !== $link['action'] ) {
				continue;
			}

			$postType    = self::postType( $link['kind'] );
			$originalRow = $this->store->layoutRow( $link['original'], $postType );
			if ( null === $originalRow || null === $originalRow['trid'] || null === $originalRow['language'] ) {
				continue;
			}

			if ( $this->store->link( $link['layout'], $postType, $originalRow['trid'], $link['language'], $originalRow['language'] ) ) {
				$linked[] = $link['layout'];
				$done[]   = $link;
				$this->store->log(
					sprintf(
						'%s layout %d linked as the %s translation of layout %d (template %d).',
						$link['kind'],
						$link['layout'],
						$link['language'],
						$link['original'],
						$link['template']
					)
				);
			}
		}

		if ( $done ) {
			$this->store->writeLinks( $done );
		}

		return $linked;
	}

	public static function postType( $kind ) {
		return 'et_' . $kind . '_layout';
	}

	private function decide( $kind, $layout, $original, $language, array &$taken ) {
		$postType = self::postType( $kind );
		if ( ! $this->store->isTranslatable( $postType ) ) {
			return self::skip( self::REASON_NOT_TRANSLATABLE );
		}

		$originalRow = $this->store->layoutRow( $original, $postType );
		$layoutRow   = $this->store->layoutRow( $layout, $postType );
		if ( null === $originalRow || null === $layoutRow ) {
			return self::skip( self::REASON_MISSING );
		}
		if ( null === $originalRow['trid'] || null === $originalRow['language'] ) {
			return self::skip( self::REASON_NO_ORIGINAL_ROW );
		}
		if ( $layoutRow['trid'] === $originalRow['trid'] ) {
			return null;
		}
		if ( $originalRow['language'] === $language || ( null !== $layoutRow['language'] && $layoutRow['language'] !== $language ) ) {
			return self::skip( self::REASON_OTHER_LANGUAGE );
		}
		if ( $layoutRow['members'] > 1 ) {
			return self::skip( self::REASON_GROUP );
		}

		$slot = $originalRow['trid'] . ':' . $language;
		if ( isset( $taken[ $slot ] ) || $this->store->translationIn( $originalRow['trid'], $postType, $language ) ) {
			return self::skip( self::REASON_EXISTS );
		}
		$taken[ $slot ] = $layout;

		return [
			'action' => self::ACTION_LINK,
			'reason' => '',
		];
	}

	private function match( array $facts, array $keptTemplates ) {
		if ( null !== $facts['trid'] ) {
			foreach ( $keptTemplates as $kept ) {
				if ( $kept['trid'] === $facts['trid'] ) {
					return $kept;
				}
			}
		}

		$same = [];
		foreach ( $keptTemplates as $kept ) {
			if ( $kept['default'] === $facts['default'] && $kept['conditions'] === $facts['conditions'] ) {
				$same[] = $kept;
			}
		}

		return 1 === count( $same ) ? $same[0] : null;
	}

	private static function skip( $reason ) {
		return [
			'action' => self::ACTION_SKIP,
			'reason' => $reason,
		];
	}
}
