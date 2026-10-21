<?php
class ICLMenusSync extends WPML_Menu_Sync_Functionality {
	public $menus;
	public $is_preview               = false;
	public $sync_data                = false;
	public $string_translation_links = array();
	public $operations               = array();
	private $menu_item_sync;

	public static function chip_icon_svg( $type ) {
		$aliases = array(
			'label_changed'   => 'stale',
			'url_missing'     => 'nourl',
			'label_missing'   => 'nourl',
			'options_changed' => 'options',
			'url_changed'     => 'options',
		);
		if ( isset( $aliases[ $type ] ) ) {
			$type = $aliases[ $type ];
		}
		switch ( $type ) {
			case 'add':
				return '<svg class="icl_msync_chip-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>';
			case 'mov':
				return '<svg class="icl_msync_chip-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7l4-4m0 0l4 4m-4-4v18m-4-4l4 4m0 0l4-4"/></svg>';
			case 'stale':
				return '<svg class="icl_msync_chip-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M4.93 4.93a10 10 0 1114.14 14.14 10 10 0 01-14.14-14.14z"/></svg>';
			case 'nourl':
				return '<svg class="icl_msync_chip-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101M10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1M3 3l18 18"/></svg>';
			case 'options':
				return '<svg class="icl_msync_chip-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>';
		}
		return '';
	}

	public static function chip_icon_allowed_html() {
		return array(
			'svg'  => array(
				'class'        => true,
				'width'        => true,
				'height'       => true,
				'viewbox'      => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'aria-hidden'  => true,
			),
			'path' => array(
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'd'               => true,
			),
		);
	}

	function __construct( &$sitepress, &$wpdb, &$post_translations, &$term_translations ) {
		parent::__construct( $sitepress, $wpdb, $post_translations, $term_translations );

		$this->menu_item_sync = new WPML_Menu_Item_Sync( $this->sitepress, $this->wpdb, $this->post_translations, $this->term_translations );
		$this->init_hooks();
	}

	function init_hooks() {
		add_action( 'init', array( $this, 'init' ), 20 );

		if ( isset( $_GET['updated'] ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	function init( $previous_menu = false ) {
		$this->sitepress->switch_lang( $this->sitepress->get_default_language() );

		try {
			$action = filter_input( INPUT_POST, 'action' );
			$nonce  = (string) filter_input( INPUT_POST, '_icl_nonce_menu_sync' );

			if ( $action && ! wp_verify_nonce( $nonce, '_icl_nonce_menu_sync' ) ) {
				wp_send_json_error( 'Invalid nonce' );
			}
			$this->menu_item_sync->cleanup_broken_page_items();

			if ( $action === 'icl_msync_preview' ) {
				$this->is_preview = true;
				$this->sync_data  = isset( $_POST['sync'] ) ? array_map( 'stripslashes_deep', $_POST['sync'] ) : false;
				$previous_menu    = WPML_Menu_Sync_Store::get();
			}

			if ( $previous_menu ) {
				$this->menus = $previous_menu;
			} else {
				$this->get_menus_tree();
				WPML_Menu_Sync_Store::save( $this->menus );
			}
		} finally {
			$this->sitepress->switch_lang();
		}
	}

	function get_menu_names() {
		$menu_names = array();
		global $sitepress, $wpdb;

		$menus = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT tm.term_id, tm.name FROM {$wpdb->terms} tm
                JOIN {$wpdb->term_taxonomy} tx ON tx.term_id = tm.term_id
                JOIN {$wpdb->prefix}icl_translations tr ON tr.element_id = tx.term_taxonomy_id AND tr.element_type='tax_nav_menu'
            WHERE tr.language_code=%s
        ",
				$sitepress->get_default_language()
			)
		);

		if ( $menus ) {
			foreach ( $menus as $menu ) {
				$menu_names[] = $menu->name;
			}
		}

		return $menu_names;
	}

	function get_menus_tree() {
		global $sitepress, $wpdb;

		$menus = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT tm.term_id, tm.name FROM {$wpdb->terms} tm
                JOIN {$wpdb->term_taxonomy} tx ON tx.term_id = tm.term_id
                JOIN {$wpdb->prefix}icl_translations tr ON tr.element_id = tx.term_taxonomy_id AND tr.element_type='tax_nav_menu'
            WHERE tr.language_code=%s
        ",
				$sitepress->get_default_language()
			)
		);

		if ( $menus ) {
			foreach ( $menus as $menu ) {
				$this->menus[ $menu->term_id ] = array(
					'name'         => $menu->name,
					'items'        => $this->get_menu_items( $menu->term_id, true ),
					'translations' => $this->get_menu_translations( $menu->term_id ),
				);
			}

			$this->add_ghost_entries();
			$this->set_new_menu_order();
		}
	}

	private function get_menu_options( $menu_id ) {
		$menu_options = get_option( 'nav_menu_options' );
		$options      = array(
			'auto_add' => isset( $menu_options['auto_add'] ) && in_array(
				$menu_id,
				$menu_options['auto_add']
			),
		);

		return $options;
	}

	public function add_ghost_entries() {
		if ( is_array( $this->menus ) ) {
			foreach ( $this->menus as $menu_id => $menu ) {
				if ( ! is_array( $menu['translations'] ) ) {
					continue;
				}
				foreach ( $menu['translations'] as $language => $tmenu ) {
					if ( ! empty( $tmenu ) ) {
						$valid_items = array_filter(
							$this->menus[ $menu_id ]['items'],
							function ( $item ) use ( $language ) {
								return $item && isset( $item['translations'][ $language ]['ID'] );
							}
						);

						foreach ( $tmenu['items'] as $titem ) {
							$exists = false;
							foreach ( $valid_items as $item ) {
								if ( (int) $item['translations'][ $language ]['ID'] === (int) $titem['ID'] ) {
									$exists = true;
								}
							}
							if ( ! $exists ) {
								$this->menus[ $menu_id ]['translations'][ $language ]['deleted_items'][] = array(
									'ID'         => $titem['ID'],
									'title'      => $titem['title'],
									'menu_order' => $titem['menu_order'],
								);
							}
						}
					}
				}
			}
		}
	}

	public function set_new_menu_order() {
		if ( ! is_array( $this->menus ) ) {
			return;
		}

		foreach ( $this->menus as $menu_id => $menu ) {
			$menu_index_by_lang = array();
			foreach ( $menu['items'] as $item_id => $item ) {
				$valid_translations = array_filter(
					$item['translations'],
					function ( $item ) {
						return $item && $item['ID'];
					}
				);
				foreach ( $valid_translations as $language => $item_translation ) {
					$new_menu_order                  = empty( $menu_index_by_lang[ $language ] ) ? 1 : $menu_index_by_lang[ $language ] + 1;
					$menu_index_by_lang[ $language ] = $new_menu_order;

					$this->menus[ $menu_id ]['items'][ $item_id ]['translations'][ $language ]['menu_order_new'] = $new_menu_order;
				}
			}
		}
	}

	public function unknown_menu_ids( array $data ) {
		$known   = array_map( 'strval', array_keys( (array) $this->menus ) );
		$unknown = array();

		foreach ( $data as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			foreach ( array_keys( $section ) as $menu_id ) {
				$menu_id = (string) $menu_id;
				if ( ! in_array( $menu_id, $known, true ) ) {
					$unknown[ $menu_id ] = $menu_id;
				}
			}
		}

		return array_values( $unknown );
	}

	function do_sync( array $data ) {

		$this->menus = isset( $this->menus ) ? $this->menus : array();
		$this->menus = empty( $data['menu_translation'] ) ? $this->menus : $this->menu_item_sync->sync_menu_translations(
			$data['menu_translation'],
			$this->menus
		);
		if ( ! empty( $data['options_changed'] ) ) {
			$this->menu_item_sync->sync_menu_options( $data['options_changed'] );
		}
		if ( ! empty( $data['del'] ) ) {
			$this->menu_item_sync->sync_deleted_menus( $data['del'] );
		}
		$this->menus = empty( $data['mov'] ) ? $this->menus : $this->menu_item_sync->sync_moved_items(
			$data['mov'],
			$this->menus
		);
		$this->menus = empty( $data['add'] ) ? $this->menus : $this->menu_item_sync->sync_added_items(
			$data['add'],
			$this->menus
		);
		if ( ! empty( $data['label_changed'] ) ) {
			$this->menu_item_sync->sync_caption( $data['label_changed'] );
		}
		if ( ! empty( $data['url_changed'] ) ) {
			$this->menu_item_sync->sync_urls( $data['url_changed'] );
		}
		if ( ! empty( $data['label_missing'] ) ) {
			$this->menu_item_sync->sync_missing_captions( $data['label_missing'] );
		}
		if ( ! empty( $data['url_missing'] ) ) {
			$this->menu_item_sync->sync_urls_to_add( $data['url_missing'] );
		}

		$this->menu_item_sync->sync_custom_fields( $this->menus );

		$this->menus = isset( $this->menus ) ? $this->menu_item_sync->sync_menu_order( $this->menus ) : $this->menus;
		$this->menu_item_sync->cleanup_broken_page_items();

		wp_cache_delete( 'last_changed', 'terms' );

		return $this->menus;
	}

	function render_items_tree_default( $menu_id, $parent = 0, $depth = 0 ) {
		global $sitepress;

		$active_language_codes = array_keys( $sitepress->get_active_languages() );
		$need_sync             = 0;
		$default_language      = $sitepress->get_default_language();
		foreach ( $this->menus[ $menu_id ]['items'] as $item ) {

			static $d2_items = array();
			$deleted_items   = array();
			if ( isset( $this->menus[ $menu_id ]['translations'] ) && is_array( $this->menus[ $menu_id ]['translations'] ) ) {
				foreach ( $this->menus[ $menu_id ]['translations'] as $language => $tmenu ) {

					if ( ! isset( $d2_items[ $menu_id ][ $language ] ) ) {
						$d2_items[ $menu_id ][ $language ] = array();
					}

					if ( ! empty( $this->menus[ $menu_id ]['translations'][ $language ]['deleted_items'] ) ) {
						foreach ( $this->menus[ $menu_id ]['translations'][ $language ]['deleted_items'] as $deleted_item ) {
							if ( ! in_array(
								$deleted_item['ID'],
								$d2_items[ $menu_id ][ $language ]
							) && $deleted_item['menu_order'] > count( $this->menus[ $menu_id ]['items'] )
							) {
								$deleted_items[ $language ][]        = $deleted_item;
								$d2_items[ $menu_id ][ $language ][] = $deleted_item['ID'];
							}
						}
					}
				}
			}
			if ( $deleted_items ) {
				?>
				<tr>
					<td>&nbsp;</td>
					<?php
					foreach ( $sitepress->get_active_languages() as $language ) :
						if ( $language['code'] == $default_language ) {
							continue;
						}
						?>
						<td>
							<?php if ( isset( $deleted_items[ $language['code'] ] ) ) : ?>
								<?php ++$need_sync; ?>
								<?php foreach ( $deleted_items[ $language['code'] ] as $deleted_item ) : ?>
									<?php echo str_repeat( ' - ', $depth ); ?><span
										class="icl_msync_item icl_msync_del"><?php echo esc_html( $deleted_item['title'] ); ?></span>
									<?php
									?>
									<input type="hidden"
											name="sync[del][<?php echo esc_attr( $menu_id ); ?>][<?php echo esc_attr( $deleted_item['ID'] ); ?>][<?php echo esc_attr( $language['code'] ); ?>]"
											value="<?php echo esc_attr( $deleted_item['title'] ); ?>"/>
									<?php
									$this->operations['del'] = empty( $this->operations['del'] ) ? 1
										: $this->operations['del']++;
									?>
									<br/>
								<?php endforeach; ?>
							<?php else : ?>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
				<?php
			}

			static $mo_added = array();
			$deleted_items   = array();
			if ( isset( $this->menus[ $menu_id ]['translations'] ) && is_array( $this->menus[ $menu_id ]['translations'] ) ) {
				foreach ( $this->menus[ $menu_id ]['translations'] as $language => $tmenu ) {

					if ( ! isset( $mo_added[ $menu_id ][ $language ] ) ) {
						$mo_added[ $menu_id ][ $language ] = array();
					}

					if ( ! empty( $this->menus[ $menu_id ]['translations'][ $language ]['deleted_items'] ) ) {
						foreach ( $this->menus[ $menu_id ]['translations'][ $language ]['deleted_items'] as $deleted_item ) {

							if ( ! in_array(
								$item['menu_order'],
								$mo_added[ $menu_id ][ $language ]
							) && $deleted_item['menu_order'] == $item['menu_order']
							) {
								$deleted_items[ $language ]          = $deleted_item;
								$mo_added[ $menu_id ][ $language ][] = $item['menu_order'];
								++$need_sync;
							}
						}
					}
				}
			}

			$this->render_deleted_items( $deleted_items, $need_sync, $depth, $menu_id );

			if ( $item['parent'] == $parent ) {
				$row_state = '';
				foreach ( $active_language_codes as $row_state_lang ) {
					if ( $row_state_lang === $default_language ) {
						continue;
					}
					if ( ! isset( $item['translations'][ $row_state_lang ] ) ) {
						continue;
					}
					$row_state_item = $item['translations'][ $row_state_lang ];
					if ( ! empty( $row_state_item['ID'] ) ) {
						if ( $row_state_item['menu_order'] != $row_state_item['menu_order_new']
							|| $row_state_item['depth'] != $item['depth'] ) {
							$row_state = 'mov';
							break;
						}
					} elseif ( $row_state_item && 'custom' === $row_state_item['object_type'] ) {
						if ( '' === $row_state ) {
							$row_state = 'add';
						}
					} elseif ( ! empty( $row_state_item['object_id'] ) ) {
						if ( empty( $row_state_item['parent_not_translated'] )
							&& ! icl_object_id( $item['ID'], 'nav_menu_item', false, (string) $row_state_lang ) ) {
							if ( '' === $row_state ) {
								$row_state = 'add';
							}
						}
					} elseif ( $row_state_item && 'post_type_archive' === $row_state_item['object_type'] ) {
						if ( '' === $row_state ) {
							$row_state = 'add';
						}
					}
				}
				?>
				<tr>
					<td>
					<?php
						echo esc_html( str_repeat( ' - ', $depth ) . $item['title'] );
					if ( 'add' === $row_state ) {
						/* translators: Word in a small badge on the menu synchronization screen: this menu item was added. Past participle used as a state. */
						echo ' <span class="icl_msync_source_state icl_msync_source_state_add">' . wp_kses( self::chip_icon_svg( 'add' ), self::chip_icon_allowed_html() ) . esc_html__( 'Added', 'sitepress' ) . '</span>';
					} elseif ( 'mov' === $row_state ) {
						/* translators: Word in a small badge on the menu synchronization screen: this menu item was moved to another place in the menu. Past participle used as a state. */
						echo ' <span class="icl_msync_source_state icl_msync_source_state_mov">' . wp_kses( self::chip_icon_svg( 'mov' ), self::chip_icon_allowed_html() ) . esc_html__( 'Moved', 'sitepress' ) . '</span>';
					}
					?>
						</td>
					<?php
					foreach ( $active_language_codes as $lang_code ) {
						if ( $lang_code === $default_language ) {
							continue;
						}
						?>
						<td>
							<?php
							$item_translation = $item['translations'][ $lang_code ];
							$item_id          = $item['ID'];
							echo str_repeat( ' - ', $depth );
							++$need_sync;
							if ( ! empty( $item_translation['ID'] ) ) {
								$item_sync_needed = false;
								if ( $item_translation['menu_order'] != $item_translation['menu_order_new'] || $item_translation['depth'] != $item['depth'] ) {
									echo '<span class="icl_msync_item icl_msync_mov">' . self::chip_icon_svg( 'mov' ) . esc_html( $item_translation['title'] ) . '</span>';
									echo '<input type="hidden" name="sync[mov][' . esc_attr( (string) $menu_id ) . '][' . esc_attr( (string) $item['ID'] ) . '][' . esc_attr( (string) $lang_code ) . '][' . esc_attr( (string) $item_translation['menu_order_new'] ) . ']" value="' . esc_attr( (string) $item_translation['title'] ) . '" />';
									$this->operations['mov'] = empty( $this->operations['mov'] ) ? 1
										: $this->operations['mov']++;

									$item_sync_needed = true;
								}
								if ( $item_translation['label_missing'] ) {
									$this->index_changed(
										'label_missing',
										$item_id,
										$item_translation['title'],
										$menu_id,
										$lang_code
									);
									$item_sync_needed = true;
								}
								if ( $item_translation['label_changed'] ) {
									$this->index_changed(
										'label_changed',
										$item_id,
										$item_translation['title'],
										$menu_id,
										$lang_code
									);
									$item_sync_needed = true;
								}
								if ( $item_translation['url_missing'] ) {
									$this->index_changed(
										'url_missing',
										$item_id,
										$item_translation['url'],
										$menu_id,
										$lang_code
									);
									$item_sync_needed = true;
								}
								if ( $item_translation['url_changed'] ) {
									$this->index_changed(
										'url_changed',
										$item_id,
										$item_translation['url'],
										$menu_id,
										$lang_code
									);
									$item_sync_needed = true;
								}
								if ( ! $item_sync_needed ) {
									--$need_sync;
									echo esc_html( $item_translation['title'] );
								}
							} elseif ( $item_translation && 'custom' === $item_translation['object_type'] ) {
								echo '<span class="icl_msync_item icl_msync_add">' . self::chip_icon_svg( 'add' ) . esc_html( $item_translation['title'] ) . ' @' . esc_html( (string) $lang_code ) . '</span>';
								echo '<input type="hidden" name="sync[add][' . esc_attr( $menu_id ) . '][' . esc_attr( $item['ID'] ) . '][' . esc_attr( (string) $lang_code ) . ']" value="' . esc_attr( $item_translation['title'] . ' @' . $lang_code ) . '" />';
								$this->incOperation( 'add' );
							} elseif ( ! empty( $item_translation['object_id'] ) ) {
								if ( ! empty( $item_translation['object_not_public'] ) ) {
									/* translators: Status of a menu item on the WP Menus Sync screen: the page is translated, but the translation is a draft, scheduled or private, so it is not added to the menu yet. */
									echo '<i class="inactive">' . esc_html__( 'Translation not published', 'sitepress' ) . '</i>';
									--$need_sync;
								} elseif ( $item_translation['parent_not_translated'] ) {
									echo '<span class="icl_msync_item icl_msync_not">' . esc_html( $item_translation['title'] ) . '</span>';
									$this->operations['not'] = empty( $this->operations['not'] ) ? 1
										: $this->operations['not']++;
								} elseif ( ! icl_object_id( $item['ID'], 'nav_menu_item', false, (string) $lang_code ) ) {
									echo '<span class="icl_msync_item icl_msync_add">' . self::chip_icon_svg( 'add' ) . esc_html( $item_translation['title'] ) . '</span>';
									echo '<input type="hidden" name="sync[add][' . esc_attr( $menu_id ) . '][' . esc_attr( $item['ID'] ) . '][' . esc_attr( (string) $lang_code ) . ']" value="' . esc_attr( $item_translation['title'] ) . '" />';
									$this->incOperation( 'add' );
								} else {
									--$need_sync;
								}
							} elseif ( $item_translation && 'post_type_archive' === $item_translation['object_type'] ) {
								echo '<span class="icl_msync_item icl_msync_add">' . self::chip_icon_svg( 'add' ) . esc_html( $item_translation['title'] ) . ' @' . esc_html( (string) $lang_code ) . '</span>';
								echo '<input type="hidden" name="sync[add][' . esc_attr( $menu_id ) . '][' . esc_attr( $item['ID'] ) . '][' . esc_attr( (string) $lang_code ) . ']" value="' . esc_attr( $item_translation['title'] . ' @' . $lang_code ) . '" />';
								$this->incOperation( 'add' );
							} else {
								/* translators: Status of a piece of content: it has no translation in that language yet. */
								echo '<i class="inactive">' . esc_html__( 'Not translated', 'sitepress' ) . '</i>';
								--$need_sync;
							}
							?>
						</td>
					<?php } ?>
				</tr>
				<?php

				if ( $this->_item_has_children( $menu_id, $item['ID'] ) ) {
					$need_sync += $this->render_items_tree_default( $menu_id, $item['ID'], $depth + 1 );
				}
			}
		}

		if ( $depth == 0 ) {
			$this->render_option_update( $active_language_codes, $default_language, $menu_id, $need_sync );
		}

		return $need_sync;
	}

	private function render_option_update( $active_language_codes, $default_language, $menu_id, &$need_sync ) {

		?>
		<tr>
		<?php
		foreach ( $active_language_codes as $lang_code ) {
			?>
			<td>
			<?php
			if ( $lang_code === $default_language ) {
				esc_html_e( 'Menu Option: auto_add', 'sitepress' );
				continue;
			}
			$menu_options  = $this->get_menu_options( $menu_id );
			$translated_id = $this->get_translated_menu( $menu_id, $lang_code );
			$change        = false;
			if ( ! isset( $translated_id['id'] ) || $menu_options != $this->get_menu_options( $translated_id['id'] ) ) {
				++$need_sync;
				$change = true;
			}
			if ( $change ) {
				$this->index_changed(
					'options_changed',
					'auto_add',
					$menu_options['auto_add'],
					$menu_id,
					$lang_code,
					$change
				);
			} else {
				echo (int) $menu_options['auto_add'];
			}
		}
		?>
		</td>
		<?php
	}

	private function render_deleted_items( $deleted_items, &$need_sync, $depth, $menu_id ) {
		global $sitepress;

		if ( $deleted_items ) {
			?>
			<tr>
				<td>&nbsp;</td>
				<?php
				foreach ( $sitepress->get_active_languages() as $language ) :
					if ( $language['code'] === $sitepress->get_default_language() ) {
						continue;
					}
					?>
					<td>
						<?php if ( isset( $deleted_items[ $language['code'] ] ) ) : ?>
							<?php ++$need_sync; ?>
							<?php echo str_repeat( ' - ', $depth ); ?><span
								class="icl_msync_item icl_msync_del"><?php echo esc_html( $deleted_items[ $language['code'] ]['title'] ); ?></span>
							<?php  ?>
							<input type="hidden"
									name="sync[del][<?php echo esc_attr( $menu_id ); ?>][<?php echo esc_attr( $deleted_items[ $language['code'] ]['ID'] ); ?>][<?php echo esc_attr( $language['code'] ); ?>]"
									value="<?php echo esc_attr( $deleted_items[ $language['code'] ]['title'] ); ?>"/>
							<?php
							$this->operations['del'] = empty( $this->operations['del'] ) ? 1
								: $this->operations['del']++;
							?>
						<?php else : ?>
						<?php endif; ?>
					</td>
				<?php endforeach; ?>
			</tr>
			<?php
		}
	}

	private function index_changed( $index, $item_id, $item_translation, $menu_id, $lang_code, $change = true ) {
		$this->string_translation_links[ $this->menus[ $menu_id ]['name'] ] = 1;

		$additional_class = $change ? 'icl_msync_' . $index : '';
		$icon = $change ? self::chip_icon_svg( $index ) : '';
		echo '<span class="icl_msync_item ' . esc_attr( $additional_class ) . '">'
			. $icon
			. ( ! $item_translation ? 0 : esc_html( $item_translation ) )
			. '</span>'
			. '<input type="hidden" name="sync[' . esc_attr( $index ) . '][' . esc_attr( $menu_id ) . '][' . esc_attr( $item_id ) . '][' . esc_attr( $lang_code ) . ']" value="'
			. esc_attr( $item_translation ) . '" />';
		if ( $change ) {
			$this->operations[ $index ] = empty( $this->operations[ $index ] ) ? 1 : $this->operations[ $index ]++;
		}
	}

	function _item_has_children( $menu_id, $item_id ) {
		$has = false;
		foreach ( $this->menus[ $menu_id ]['items'] as $item ) {
			if ( $item['parent'] == $item_id ) {
				$has = true;
			}
		}

		return $has;
	}

	function get_item_depth( $menu_id, $item_id ) {
		$items = isset( $this->menus[ $menu_id ]['items'] ) ? $this->menus[ $menu_id ]['items'] : array();

		return WPML_Menu_Hierarchy_Guard::depth_in_item_set( $items, $item_id, $menu_id );
	}

	function admin_notices() {
		echo '<div class="updated"><p>' . esc_html__( 'Menu(s) syncing complete.', 'sitepress' ) . '</p></div>';
	}

	public function render_circular_items_notice() {
		$item_ids = WPML_Menu_Hierarchy_Guard::get_broken_items();
		if ( ! $item_ids ) {
			return;
		}
		?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: comma-separated list of menu item ids */
						__( 'Some menu items point to each other as parents (menu item IDs: %s). WPML shows them without hierarchy and does not sync their parent. Please edit these menu items, re-save their correct parents and reload this page.', 'sitepress' ),
						implode( ', ', $item_ids )
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	public function display_menu_links_to_string_translation() {
		$menu_links_data = $this->get_links_for_menu_strings_translation();

		if ( count( $menu_links_data ) === 0 ) {
			return;
		}

		$strings_url = add_query_arg( 'tab', 'strings', add_query_arg( 'page', urlencode( WPML_TM_FOLDER . '/menu/main.php' ), 'admin.php' ) );
		?>
		<p>
			<?php
			printf(
				/* translators: Notice on the menu synchronization screen. %1$s: the opening tag of a link to the String Translation screen, %2$s: its closing tag. "WPML Translation Dashboard -> Other texts (Strings)" and "Translations -> Strings" are the names of screens as they appear in the WPML menu. */
				esc_html__( 'Your menu includes custom items, which may not be synchronized. Translate the menu items from the WPML Translation Dashboard -> Other texts (Strings) or manually from the %1$sTranslations -> Strings%2$s page.', 'sitepress' ),
				'<a href="' . esc_url( $strings_url ) . '">',
				'</a>'
			);
			?>
			<br>
			<?php /* translators: Second line of that notice. "This" is the synchronization the reader is about to run. */ esc_html_e( "When you synchronize the menu, you're also running the menu sync operation. This will use the strings that you've translated to update the menus.", 'sitepress' ); ?>
		</p>
		<?php
	}

	public function get_links_for_menu_strings_translation() {
		$menu_links = array();

		$wpml_st_folder = $this->sitepress->get_wp_api()->constant( 'WPML_ST_FOLDER' );

		if ( $wpml_st_folder ) {
			$wpml_st_contexts = icl_st_get_contexts( false );
			$wpml_st_contexts = wp_list_pluck( $wpml_st_contexts, 'context' );
			$menu_names       = $this->get_menu_names();

			foreach ( $menu_names as $k => $menu_name ) {
				if ( ! in_array( $menu_name . WPML_Menu_Sync_Functionality::STRING_CONTEXT_SUFFIX, $wpml_st_contexts, true ) ) {
					unset( $menu_names[ $k ] );
				}
			}

			if ( ! empty( $menu_names ) ) {
				$tm_url = add_query_arg( 'page', urlencode( WPML_TM_FOLDER . '/menu/main.php' ), 'admin.php' );

				foreach ( $menu_names as $menu_name ) {
					$menu_links[ $menu_name ] = $tm_url;
				}
			}
		}

		$response = array();
		if ( $menu_links ) {
			$response = array(
				/* translators: Label in front of the list of languages on the menu synchronization screen; the language names follow the colon. */
				'label' => esc_html__( 'Translate menu strings and URLs for:', 'sitepress' ),
				'items' => $menu_links,
			);
		}

		return $response;
	}

	private function incOperation( $mode ) {
		$this->operations[ $mode ] = empty( $this->operations[ $mode ] ) ? 1 : $this->operations[ $mode ]++;
	}
}
