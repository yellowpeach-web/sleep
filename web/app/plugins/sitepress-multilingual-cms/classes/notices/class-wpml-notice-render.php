<?php

class WPML_Notice_Render {
	private $dismiss_html_added;
	private $hide_html_added;
	private $collapse_html_added;

	public static function allowed_html() {
		$global = array(
			'id'       => true,
			'class'    => true,
			'style'    => true,
			'title'    => true,
			'role'     => true,
			'tabindex' => true,
			'data-*'   => true,
			'aria-*'   => true,
			'dir'      => true,
			'lang'     => true,
		);

		$allowed = array();
		foreach ( array(
			'p', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'mark', 'code', 'pre', 'kbd', 'samp', 'var',
			'br', 'hr', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'q', 'cite',
			'abbr', 'sup', 'sub', 'del', 'ins', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
			'fieldset', 'legend',
		) as $tag ) {
			$allowed[ $tag ] = $global;
		}

		$allowed['a']        = $global + array( 'href' => true, 'target' => true, 'rel' => true, 'name' => true, 'download' => true );
		$allowed['img']      = $global + array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'srcset' => true, 'sizes' => true, 'loading' => true );
		$allowed['label']    = $global + array( 'for' => true );
		$allowed['form']     = $global + array( 'action' => true, 'method' => true, 'name' => true );
		$allowed['input']    = $global + array( 'type' => true, 'name' => true, 'value' => true, 'placeholder' => true, 'size' => true, 'disabled' => true, 'checked' => true, 'readonly' => true, 'required' => true, 'maxlength' => true, 'min' => true, 'max' => true, 'step' => true, 'autocomplete' => true );
		$allowed['button']   = $global + array( 'type' => true, 'name' => true, 'value' => true, 'disabled' => true );
		$allowed['select']   = $global + array( 'name' => true, 'disabled' => true, 'multiple' => true );
		$allowed['option']   = $global + array( 'value' => true, 'selected' => true, 'disabled' => true );
		$allowed['textarea'] = $global + array( 'name' => true, 'rows' => true, 'cols' => true, 'placeholder' => true, 'disabled' => true, 'readonly' => true );

		return apply_filters( 'wpml_notice_allowed_html', $allowed );
	}

	public static function filter_html( $html ) {
		return wp_kses( (string) $html, self::allowed_html() );
	}

	public function render( WPML_Notice $notice ) {
		echo $this->get_html( $notice );
	}

	public function get_html( WPML_Notice $notice ) {
		$result = '';

		if ( $this->must_display_notice( $notice ) ) {
			if ( $notice->should_be_text_only() ) {
				return self::filter_html( $notice->get_text() );
			}

			$actions_html = $this->get_actions_html( $notice );

			$temp_types = $notice->get_css_class_types();
			foreach ( $temp_types as $temp_type ) {
				if ( strpos( $temp_type, 'notice-' ) === false ) {
					$temp_types[] = 'notice-' . $temp_type;
				}
				if ( strpos( $temp_type, 'notice-' ) === 0 ) {
					$temp_types[] = substr( $temp_type, strlen( 'notice-' ) );
				}
			}
			$temp_classes = $notice->get_css_classes();

			$classes = array_merge( $temp_classes, $temp_types );

			if ( $this->hide_html_added || $this->dismiss_html_added || $notice->can_be_hidden() || $notice->can_be_dismissed() ) {
				$classes[] = 'is-dismissible';
			}
			$classes[] = 'notice';
			$classes[] = 'otgs-notice';

			$classes = array_unique( $classes );

			$class = implode( ' ', $classes );

			$result .= '<div class="' . esc_attr( $class ) . '" data-id="' . esc_attr( (string) $notice->get_id() ) . '" data-group="' . esc_attr( $notice->get_group() ) . '"';
			$result .= $this->get_data_nonce_attribute();

			if ( $this->hide_html_added || $notice->can_be_hidden() ) {
				/* translators: Label of the control that folds something away: the details of a request in the job log, or a notice. Verb, imperative. */
				$result .= ' data-hide-text="' . esc_attr__( 'Hide', 'sitepress' ) . '" ';
			}
			$result .= '>';

			if ( $notice->can_be_collapsed() ) {
				$result .= $this->sanitize_and_format_text( $this->get_collapsed_html( $notice ) );
			} else {
				$result .= '<p>' . $this->sanitize_and_format_text( $notice->get_text() ) . '</p>';
			}

			$this->dismiss_html_added  = false;
			$this->hide_html_added     = false;
			$this->collapse_html_added = false;

			$result .= $actions_html;

			if ( $notice->can_be_hidden() ) {
				$result .= $this->get_hide_html();
			}

			if ( $notice->can_be_dismissed() ) {
				$result .= $this->get_dismiss_html();
			}

			if ( $notice->can_be_collapsed() ) {
				$result .= $this->get_collapse_html();
			}

			if ( $notice->get_nonce_action() ) {
				$result .= $this->add_nonce( $notice );
			}

			$result .= '</div>';
		}

		return $result;
	}

	private function add_nonce( $notice ) {
		return wp_nonce_field( $notice->get_nonce_action(), $notice->get_nonce_action(), true, false );
	}

	public function must_display_notice( WPML_Notice $notice ) {
		try {
			if ( ! $notice->is_for_current_user() || ! $notice->is_user_cap_allowed() ) {
				return false;
			}

			return $this->is_current_page_allowed( $notice ) && $this->is_allowed_by_callback( $notice );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	private function get_actions_html( WPML_Notice $notice ) {
		$actions_html = '';
		if ( $notice->get_actions() ) {
			$actions_html .= '<div class="otgs-notice-actions">';
			foreach ( $notice->get_actions() as $action ) {
				$actions_html .= $this->get_action_html( $action );
			}

			$actions_html .= '</div>';

			return $actions_html;
		}

		return $actions_html;
	}

	private function sanitize_and_format_text( $text ) {
		$text              = (string) $text;
		$backticks_pattern = '|`(.*)`|U';
		preg_match_all( $backticks_pattern, $text, $matches );

		$sanitized_notice = $text;
		if ( 2 === count( $matches ) ) {
			$matches_to_sanitize = $matches[1];

			foreach ( $matches_to_sanitize as &$match_to_sanitize ) {
				$match_to_sanitize = '<pre>' . esc_html( $match_to_sanitize ) . '</pre>';
			}
			unset( $match_to_sanitize );

			$sanitized_notice = str_replace( $matches[0], $matches_to_sanitize, $sanitized_notice );
		}

		return self::filter_html( stripslashes( $sanitized_notice ) );
	}

	private function get_hide_html( $localized_text = null ) {
		return $this->get_control_html(
			'otgs-notice-hide notice-hide',
			esc_html__( 'Hide this notice.', 'sitepress' ),
			$localized_text
		);
	}

	private function get_control_html( $class, $default_text, $localized_text = null ) {
		return '<button type="button" class="' . esc_attr( $class ) . '">'
			. '<span class="screen-reader-text">'
			. ( $localized_text ? esc_html( $localized_text ) : $default_text )
			. '</span></button>';
	}

	private function get_dismiss_html( $localized_text = null ) {
		return $this->get_control_html(
			'otgs-notice-dismiss notice-dismiss',
			esc_html__( 'Dismiss this notice.', 'sitepress' ),
			$localized_text
		);
	}

	private function get_collapse_html( $localized_text = null ) {
		return $this->get_control_html(
			'otgs-notice-collapse-hide',
			esc_html__( 'Hide this notice.', 'sitepress' ),
			$localized_text
		);
	}

	private function get_collapsed_html( WPML_Notice $notice, $localized_text = null ) {
		$content = '
			<div class="otgs-notice-collapsed-text">
				<p>%s
					%s
				</p>
			</div>
			<div class="otgs-notice-collapse-text">
				%s
			</div>
		';

		$content = sprintf(
			$content,
			self::filter_html( $notice->get_collapsed_text() ),
			$this->get_control_html(
				'otgs-notice-collapse-show notice-collapse',
				esc_html__( 'Show this notice.', 'sitepress' ),
				$localized_text
			),
			self::filter_html( $notice->get_text() )
		);

		return $content;
	}

	private function get_action_html( $action ) {
		$action_html = '';
		if ( $action->can_hide() ) {
			$action_html          .= $this->get_hide_html( $action->get_text() );
			$this->hide_html_added = true;
		} elseif ( $action->can_dismiss() ) {
			$action_html             .= $this->get_dismiss_html( $action->get_text() );
			$this->dismiss_html_added = true;
		} else {
			if ( $action->get_url() ) {
				$action_html .= $this->get_action_anchor( $action );
			} else {
				$action_html .= self::filter_html( $action->get_text() );
			}
		}

		return $action_html;
	}

	private function get_action_anchor( WPML_Notice_Action $action ) {
		$anchor_attributes = array();

		$action_url = '<a';

		$anchor_attributes['href'] = esc_url_raw( $action->get_url() );
		if ( $action->get_link_target() ) {
			$anchor_attributes['target'] = $action->get_link_target();
		}

		$action_url_classes = array( 'notice-action' );
		if ( $action->must_display_as_button() ) {
			$button_style = 'button-secondary';
			if ( is_string( $action->must_display_as_button() ) ) {
				$button_style = $action->must_display_as_button();
			}
			$action_url_classes[] = $button_style;
			$action_url_classes[] = 'notice-action-' . $button_style;
		} else {
			$action_url_classes[] = 'notice-action-link';
		}
		$anchor_attributes['class'] = implode( ' ', $action_url_classes );

		if ( $action->get_group_to_dismiss() ) {
			$anchor_attributes['data-dismiss-group'] = $action->get_group_to_dismiss();
		}
		if ( $action->get_js_callback() ) {
			$anchor_attributes['data-js-callback'] = $action->get_js_callback();
		}

		foreach ( $anchor_attributes as $name => $value ) {
			$action_url .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}

		$action_url .= $this->get_data_nonce_attribute();
		$action_url .= '>';
		$action_url .= self::filter_html( $action->get_text() );
		$action_url .= '</a>';

		return $action_url;
	}

	private function get_data_nonce_attribute() {
		return ' data-nonce="' . wp_create_nonce( WPML_Notices::NONCE_NAME ) . '"';
	}

	private function is_current_screen_allowed( WPML_Notice $notice ) {
		$allow_current_screen   = true;
		$restrict_to_screen_ids = $notice->get_restrict_to_screen_ids();
		if ( $restrict_to_screen_ids && function_exists( 'get_current_screen' ) ) {
			$screen               = get_current_screen();
			$allow_current_screen = $screen && in_array( $screen->id, $restrict_to_screen_ids, true );
		}

		return $allow_current_screen;
	}

	private function is_current_page_prefix_allowed( WPML_Notice $notice, $current_page ) {
		$restrict_to_page_prefixes = $notice->get_restrict_to_page_prefixes();
		if ( $current_page && $restrict_to_page_prefixes ) {
			$allow_current_page_prefix = false;
			foreach ( $restrict_to_page_prefixes as $restrict_to_prefix ) {
				if ( stripos( $current_page, $restrict_to_prefix ) === 0 ) {
					$allow_current_page_prefix = true;
					break;
				}
			}

			return $allow_current_page_prefix;
		}

		return true;
	}

	private function is_current_page_allowed( WPML_Notice $notice ) {
		$current_page = \WPML\SuperGlobals\Request::page();

		if ( ! $this->is_current_screen_allowed( $notice ) ) {
			return false;
		}

		if ( $current_page ) {

			$exclude_from_pages = $notice->get_exclude_from_pages();
			if ( $exclude_from_pages && in_array( $current_page, $exclude_from_pages, true ) ) {
				return false;
			}

			if ( ! $this->is_current_page_prefix_allowed( $notice, $current_page ) ) {
				return false;
			}

			$restrict_to_pages = $notice->get_restrict_to_pages();
			if ( $restrict_to_pages && ! in_array( $current_page, $restrict_to_pages, true ) ) {
				return false;
			}
		}

		return true;
	}

	private function is_allowed_by_callback( WPML_Notice $notice ) {
		$allow_by_callback = true;
		$display_callbacks = $notice->get_display_callbacks();
		if ( $display_callbacks ) {
			$allow_by_callback = false;
			foreach ( $display_callbacks as $callback ) {
				if ( ! is_callable( $callback ) ) {
					continue;
				}

				$allowed = $this->callback_takes_the_notice( $callback )
					? call_user_func( $callback, $notice )
					: call_user_func( $callback );

				if ( $allowed ) {
					$allow_by_callback = true;
					break;
				}
			}
		}

		return $allow_by_callback;
	}

	private function callback_takes_the_notice( $callback ) {
		if ( is_array( $callback ) || is_object( $callback ) ) {
			return true;
		}

		try {
			if ( false !== strpos( (string) $callback, '::' ) ) {
				list( $class, $method ) = explode( '::', (string) $callback, 2 );
				$reflection             = new ReflectionMethod( $class, $method );
			} else {
				$reflection = new ReflectionFunction( (string) $callback );
			}

			return ! $reflection->isInternal();
		} catch ( ReflectionException $exception ) {
			return false;
		}
	}
}
