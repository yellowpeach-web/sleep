<?php

namespace WPML\BlockEditor\Blocks\LanguageSwitcher;

use WPML\BlockEditor\Blocks\LanguageSwitcher;
use WPML\BlockEditor\Blocks\LanguageSwitcher\Model\LanguageItem;
use WPML\BlockEditor\Blocks\LanguageSwitcher\Model\LanguageItemTemplate;
use WPML\BlockEditor\Blocks\LanguageSwitcher\Model\LanguageSwitcherTemplate;
use WPML\FP\Obj;
use WPML\LanguageSwitcher\SubmenuIds;

class Render {

	const COLOR_CLASSNAMES_STRING = 'has-text-color has-%s-color';
	const COLOR_STYLE_STRING = 'color:%s;';
	const BACKGROUND_CLASSNAMES_STRING = 'has-background has-%s-background-color';
	const BACKGROUND_STYLE_STRING = 'background-color:%s;';

	private $parser;

	private $repository;

	public function __construct( Parser $parser, Repository $repository ) {
		$this->parser     = $parser;
		$this->repository = $repository;
	}

	public function render_block( $blockAttrs, $savedHTML, $parentBlock ) {
		$model            = $this->repository->getModel();
		$languageSwitcher = $this->repository->getCurrentLanguageSwitcher( $model );

		if ( $this->hidesWhenNothingToSwitch( $languageSwitcher, $model ) ) {
			return '';
		}

		$context                  = $parentBlock->context;
		$savedHTML                = $this->removeStoredKeyboardScript( $savedHTML );
		$languageSwitcherTemplate = $this->parser->parse( $blockAttrs, $savedHTML, $parentBlock, $context );

		if ( null === $languageSwitcherTemplate ) {
			return '';
		}

		foreach ( $languageSwitcher->getLanguageItems() as $languageItem ) {
			$isCurrent         = $languageSwitcher->getCurrentLanguageCode() === $languageItem->getCode();
			$parserXPathPrefix = $isCurrent ? Parser::PATH_CURRENT_LANGUAGE_ITEM : Parser::PATH_LANGUAGE_ITEM;

			$languageSwitcherItemTemplate = $isCurrent ?
				$languageSwitcherTemplate->getCurrentLanguageItemTemplate() :
				$languageSwitcherTemplate->getLanguageItemTemplate();

			$this->createLanguageItemNode(
				$languageSwitcherItemTemplate,
				$parserXPathPrefix,
				$languageItem,
				$languageSwitcherTemplate,
				$parentBlock,
				$context,
				$blockAttrs
			);
		}

		$this->giveAriaStateHoldersARole( $languageSwitcherTemplate->getDOMXPath() );
		$this->restoreToggleKeyboardAccess( $languageSwitcherTemplate->getDOMXPath() );
		$this->restoreCurrentLanguageItemLayout( $languageSwitcherTemplate->getDOMXPath() );
		$this->drawDropdownArrow( $languageSwitcherTemplate->getDOMXPath() );
		$this->giveListsTheIdTheirToggleControls( $languageSwitcherTemplate->getDOMXPath() );
		$this->restoreScreenReaderLabels( $languageSwitcherTemplate->getDOMXPath() );

		return $this->getBodyHTML( $languageSwitcherTemplate->getDOMDocument() );
	}

	private function restoreScreenReaderLabels( \DOMXPath $domXPath ) {
		$this->addAriaLabelUnlessSet(
			$domXPath,
			'//*[@role="navigation"]',
			/* translators: Screen reader name of the language switcher on the site, and the name of the language switcher widget. */
			__( 'Language Switcher', 'sitepress' )
		);
		$this->addAriaLabelUnlessSet(
			$domXPath,
			"//*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-submenu__toggle ')]"
			. "[not(ancestor::li[contains(concat(' ', normalize-space(@class), ' '), ' open-on-click ')])]",
			__( 'Language switcher, press tab to navigate to other languages', 'sitepress' )
		);
		$this->addAriaLabelUnlessSet(
			$domXPath,
			'//ul[@data-is-drop-zone]',
			/* translators: Text read out by screen readers for the list of languages in the Language Switcher block. */
			__( 'Available languages', 'sitepress' )
		);
	}

	private function addAriaLabelUnlessSet( \DOMXPath $domXPath, $query, $label ) {
		$elements = $domXPath->query( $query . '[not(@aria-label)]' );

		if ( ! $elements ) {
			return;
		}

		foreach ( $elements as $element ) {
			if ( $element instanceof \DOMElement ) {
				$element->setAttribute( 'aria-label', $label );
			}
		}
	}

	private function hidesWhenNothingToSwitch( Model\LanguageSwitcher $languageSwitcher, $model ) {
		if ( ! $languageSwitcher->getLanguageItems() || $languageSwitcher->hasAlternatives() ) {
			return false;
		}

		return (bool) apply_filters( 'wpml_ls_hide_when_nothing_to_switch', true, (array) $model, null );
	}

	private function restoreCurrentLanguageItemLayout( \DOMXPath $domXPath ) {
		$items = $domXPath->query(
			"//*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-submenu__toggle ')]"
			. "/span[contains(concat(' ', normalize-space(@class), ' '), ' current-language-item ')]"
		);

		if ( ! $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( ! $item instanceof \DOMElement ) {
				continue;
			}

			$this->addDisplayUnlessSet( $item, 'flex' );

			$boxes = $domXPath->query( "./div[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-item__content ')]", $item );
			if ( $boxes ) {
				foreach ( $boxes as $box ) {
					if ( $box instanceof \DOMElement ) {
						$this->addDisplayUnlessSet( $box, 'inline-block' );
					}
				}
			}
		}
	}

	private function addDisplayUnlessSet( \DOMElement $element, $display ) {
		$style = trim( $element->getAttribute( 'style' ), " \t\n\r;" );

		if ( preg_match( '/(?:^|;)\s*display\s*:/i', $style ) ) {
			return;
		}

		$element->setAttribute( 'style', 'display:' . $display . ( '' === $style ? '' : ';' . $style ) );
	}

	private function drawDropdownArrow( \DOMXPath $domXPath ) {
		$icons = $domXPath->query( "//span[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__submenu-icon ')][not(*)]" );

		if ( ! $icons ) {
			return;
		}

		foreach ( $icons as $icon ) {
			if ( ! $icon instanceof \DOMElement || ! $icon->ownerDocument ) {
				continue;
			}

			$svg = $icon->ownerDocument->createElement( 'svg' );
			foreach (
				[
					'xmlns'       => 'http://www.w3.org/2000/svg',
					'width'       => '12',
					'height'      => '12',
					'viewBox'     => '0 0 12 12',
					'fill'        => 'none',
					'aria-hidden' => 'true',
					'focusable'   => 'false',
				] as $name => $value
			) {
				$svg->setAttribute( $name, $value );
			}

			$path = $icon->ownerDocument->createElement( 'path' );
			$path->setAttribute( 'd', 'M1.50002 4L6.00002 8L10.5 4' );
			$path->setAttribute( 'stroke-width', '1.5' );

			$svg->appendChild( $path );
			$icon->appendChild( $svg );
		}
	}

	private function giveListsTheIdTheirToggleControls( \DOMXPath $domXPath ) {
		$toggles = $domXPath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-submenu__toggle ')][@aria-controls]" );

		if ( ! $toggles ) {
			return;
		}

		foreach ( $toggles as $toggle ) {
			if ( ! $toggle instanceof \DOMElement ) {
				continue;
			}

			$lists = $domXPath->query( "following-sibling::ul[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation__submenu-container ')][1]", $toggle );
			$list  = $lists ? $lists->item( 0 ) : null;

			if ( ! $list instanceof \DOMElement ) {
				continue;
			}

			$style = trim( (string) preg_replace( '/(?:^|;)\s*id\s*:[^;]*/i', '', $list->getAttribute( 'style' ) ), " \t\n\r;" );
			if ( '' === $style ) {
				$list->removeAttribute( 'style' );
			} else {
				$list->setAttribute( 'style', $style );
			}

			$id = $list->getAttribute( 'id' ) ?: $toggle->getAttribute( 'aria-controls' );
			if ( '' === $id ) {
				continue;
			}

			$unique = SubmenuIds::reserve( $id );

			$list->setAttribute( 'id', $unique );
			$toggle->setAttribute( 'aria-controls', $unique );
		}
	}

	private function removeStoredKeyboardScript( $savedHTML ) {
		if ( ! is_string( $savedHTML ) || false === strpos( $savedHTML, 'wpmlLSBlockKeyboard' ) ) {
			return $savedHTML;
		}

		$savedHTML = (string) preg_replace(
			'~<script\b[^>]*>(?:(?!</script>).)*?\bwpmlLSBlockKeyboard\b(?:(?!</script>).)*</script>~is',
			'',
			$savedHTML
		);

		return (string) preg_replace(
			'~\s*\(function\(\)\s*\{\s*if\s*\(\s*typeof\s+window\.wpmlLSBlockKeyboard\s*===.*?\}\)\(\);\s*~s',
			'',
			$savedHTML
		);
	}

	private function restoreToggleKeyboardAccess( \DOMXPath $domXPath ) {
		$toggles = $domXPath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' wp-block-navigation-submenu__toggle ')]" );

		if ( ! $toggles ) {
			return;
		}

		foreach ( $toggles as $toggle ) {
			if ( ! $toggle instanceof \DOMElement ) {
				continue;
			}

			if ( ! $toggle->hasAttribute( 'tabindex' ) ) {
				$toggle->setAttribute( 'tabindex', '0' );
			}

			if ( ! $toggle->hasAttribute( 'aria-haspopup' ) ) {
				$toggle->setAttribute( 'aria-haspopup', 'true' );
			}
		}
	}

	private function giveAriaStateHoldersARole( \DOMXPath $domXPath ) {
		$holders = $domXPath->query( '//*[@aria-expanded or @aria-haspopup][not(@role)]' );

		if ( ! $holders ) {
			return;
		}

		foreach ( $holders as $holder ) {
			if ( $holder instanceof \DOMElement && ! in_array( $holder->tagName, [ 'button', 'a' ], true ) ) {
				$holder->setAttribute( 'role', 'button' );
			}
		}
	}

	private function createLanguageItemNode(
		LanguageItemTemplate $languageItemTemplate,
		$XPathPrefix,
		LanguageItem $languageItem,
		LanguageSwitcherTemplate $languageSwitcherTemplate,
		$sourceBlock,
		$context,
		$blockAttrs
	) {
		$template  = $languageItemTemplate->getTemplate();
		$container = $languageItemTemplate->getContainer();
		if ( empty( $template ) || empty( $container ) ) {
			return null;
		}
		$newLanguageItem = $template->cloneNode( true );
		$container->appendChild( $newLanguageItem );


		$linkQuery  = $languageSwitcherTemplate->getDOMXPath()->query( $XPathPrefix . '/' . Parser::PATH_ITEM_LINK );
		$textTarget = &$newLanguageItem;
		if ( $linkQuery->length > 0 ) {
			$link = $linkQuery->item( $linkQuery->length - 1 );
			$link->setAttribute( 'href', $languageItem->getUrl() );
			/* translators: Screen reader name of an item of the language switcher. %s: the name of the language it switches to. */
			$ariaLabel = sprintf( __( 'Switch to %s', 'sitepress' ), $languageItem->getNativeName() );
			$link->setAttribute( 'aria-label', $ariaLabel );
			$textTarget = $link;
		}

		if ( $languageItemTemplate->getLabelTemplate() ) {
			$labelQuery = $languageSwitcherTemplate->getDOMXPath()->query( $XPathPrefix . '/' . Parser::PATH_ITEM_LABEL );
			if ( $labelQuery->length > 0 ) {
				$label = $labelQuery->item( $labelQuery->length - 1 );
				if ( $label ) {
					$label->textContent = $languageItemTemplate->getLabelTemplate()->getDisplayName( $languageItem );
				}
			} else {
				$textTarget->textContent = $languageItemTemplate->getLabelTemplate()->getDisplayName( $languageItem );
			}
		}

		$flagQuery = $languageSwitcherTemplate->getDOMXPath()->query( $XPathPrefix . '/' . Parser::PATH_ITEM_FLAG_URL );
		if ( $flagQuery->length > 0 ) {
			$flag       = $flagQuery->item( $flagQuery->length - 1 );
			$flagWidth  = ! empty( $blockAttrs['flagWidth'] ) ? $blockAttrs['flagWidth'] : 18;
			$flagHeight = ! empty( $blockAttrs['flagHeight'] ) ? $blockAttrs['flagHeight'] : 12;

			if ( $flag ) {
				$flag->setAttribute( 'src', $languageItem->getFlagUrl() );
				$flag->setAttribute( 'width', $flagWidth );
				$flag->setAttribute( 'height', $flagHeight );
			}
		}

		if ( isset( $sourceBlock->attributes['layoutOpenOnClick'] ) ) {
			$dropdownFirstItemQuery = $languageSwitcherTemplate->getDOMXPath()->query( $XPathPrefix . "/ancestor::li" );
			if ( $dropdownFirstItemQuery->length > 0 ) {
				$dpFirstItem = $dropdownFirstItemQuery->item( $dropdownFirstItemQuery->length - 1 );

				if ( $dpFirstItem ) {
					$dpFirstItem->setAttribute(
						'onclick',
						"(function(li){
						var toggle = li.children[0];
						if (toggle.getAttribute('aria-expanded') === 'true') {
							if (li.wpmlLsClose) { li.wpmlLsClose(); } else { toggle.setAttribute('aria-expanded', 'false'); }
							return;
						}
						toggle.setAttribute('aria-expanded', 'true');
						var onDocumentClick = function(event){ if (!li.contains(event.target)) { li.wpmlLsClose(); } };
						var onKeyDown = function(event){ if (event.key === 'Escape' || event.key === 'Esc') { li.wpmlLsClose(); toggle.focus(); } };
						li.wpmlLsClose = function(){
							toggle.setAttribute('aria-expanded', 'false');
							document.removeEventListener('click', onDocumentClick);
							document.removeEventListener('keydown', onKeyDown);
							li.wpmlLsClose = null;
						};
						setTimeout(function(){ if (li.wpmlLsClose) { document.addEventListener('click', onDocumentClick); document.addEventListener('keydown', onKeyDown); } }, 0);
					})(this);"
					);
				}
			}
		}

		if ( $sourceBlock->name === LanguageSwitcher::BLOCK_NAVIGATION_LANGUAGE_SWITCHER ) {
			if ( $XPathPrefix === Parser::PATH_CURRENT_LANGUAGE_ITEM ) {
				$this->maybeApplyColorsForLanguageItems( $languageSwitcherTemplate, $XPathPrefix, $context, true );
			}

			if ( $XPathPrefix === Parser::PATH_LANGUAGE_ITEM ) {
				$this->maybeApplyColorsForLanguageItems( $languageSwitcherTemplate, $XPathPrefix, $context, false );
			}
		}

		return $newLanguageItem;
	}

	private function maybeApplyColorsForLanguageItems( $languageSwitcherTemplate, $XPathPrefix, $context, $isCurrentLanguageItem ) {
		$langItemQuery     = $languageSwitcherTemplate->getDOMXPath()->query( $XPathPrefix );
		$langItemSpanQuery = $languageSwitcherTemplate->getDOMXPath()->query( $XPathPrefix . "//span[@data-wpml='label']" );

		if ( $langItemQuery->length > 0 ) {
			$langItem     = $langItemQuery->item( $langItemQuery->length - 1 );
			$langItemSpan = $langItemSpanQuery->item( $langItemSpanQuery->length - 1 );

			if ( $langItem && $langItemSpan ) {
				if ( $isCurrentLanguageItem ) {
					$this->maybeApplyColorsForCurrentLanguageItem( $langItem, $langItemSpan, $context );
				} else {
					$this->maybeApplyColorsForLanguageItem( $langItem, $langItemSpan, $context );
				}
			}
		}
	}

	private function maybeApplyColorsForCurrentLanguageItem( $langItem, $langItemSpan, $context ) {
		$namedTextColor        = Obj::propOr( null, 'textColor', $context );
		$namedBackgroundColor  = Obj::propOr( null, 'backgroundColor', $context );
		$customTextColor       = Obj::propOr( null, 'customTextColor', $context );
		$customBackgroundColor = Obj::propOr( null, 'customBackgroundColor', $context );

		if ( isset( $namedTextColor ) ) {
			$this->appendAttributeValueToDOMElement( $langItemSpan, 'class', ' ' . sprintf( self::COLOR_CLASSNAMES_STRING, $namedTextColor ) );
		} elseif ( isset( $customTextColor ) ) {
			$this->appendAttributeValueToDOMElement( $langItemSpan, 'style', sprintf( self::COLOR_STYLE_STRING, $customTextColor ) );
		}

		if ( isset( $namedBackgroundColor ) ) {
			$this->appendAttributeValueToDOMElement( $langItem, 'class', ' ' . sprintf( self::BACKGROUND_CLASSNAMES_STRING, $namedBackgroundColor ) );
		} elseif ( isset( $customBackgroundColor ) ) {
			$this->appendAttributeValueToDOMElement( $langItem, 'style', sprintf( self::BACKGROUND_STYLE_STRING, $customBackgroundColor ) );
		}
	}

	private function maybeApplyColorsForLanguageItem( $langItem, $langItemSpan, $context ) {
		$namedOverlayTextColor        = Obj::propOr( null, 'overlayTextColor', $context );
		$namedOverlayBackgroundColor  = Obj::propOr( null, 'overlayBackgroundColor', $context );
		$customOverlayTextColor       = Obj::propOr( null, 'customOverlayTextColor', $context );
		$customOverlayBackgroundColor = Obj::propOr( null, 'customOverlayBackgroundColor', $context );

		if ( isset( $namedOverlayTextColor ) ) {
			$this->appendAttributeValueToDOMElement( $langItemSpan, 'class', ' ' . sprintf( self::COLOR_CLASSNAMES_STRING, $namedOverlayTextColor ) );
		} elseif ( isset( $customOverlayTextColor ) ) {
			$this->appendAttributeValueToDOMElement( $langItemSpan, 'style', sprintf( self::COLOR_STYLE_STRING, $customOverlayTextColor ) );
		}

		if ( isset( $namedOverlayBackgroundColor ) ) {
			$this->appendAttributeValueToDOMElement( $langItem, 'class', ' ' . sprintf( self::BACKGROUND_CLASSNAMES_STRING, $namedOverlayBackgroundColor ) );
		} elseif ( isset( $customOverlayBackgroundColor ) ) {
			$this->appendAttributeValueToDOMElement( $langItem, 'style', sprintf( self::BACKGROUND_STYLE_STRING, $customOverlayBackgroundColor ) );
		}
	}

	private function appendAttributeValueToDOMElement( $element, $attribute, $value ) {
		$currentElementAttributeValue = $this->getDOMElementCurrentAttributeValue( $element, $attribute );
		$element->setAttribute( $attribute, $currentElementAttributeValue . $value );
	}

	private function getDOMElementCurrentAttributeValue( $element, $attribute ) {
		return $element->getAttribute( $attribute );
	}

	private function getBodyHTML( $DOMDocument ) {
		$html = $DOMDocument->saveHTML();

		if ( ! $html ) {
			return '';
		}

		$start = strpos( $html, '<body>' );
		$end   = strpos( $html, '</body>' );

		return substr( $html, $start + strlen( '<body>' ), $end - $start - strlen( '<body>' ) ) ?: '';
	}
}

