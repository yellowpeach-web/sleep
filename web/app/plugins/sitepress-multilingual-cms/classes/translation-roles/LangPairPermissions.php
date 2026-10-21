<?php

namespace WPML\TranslationRoles;

use WPML\LIB\WP\User;

class LangPairPermissions {

	private $is_translator;

	private $self_translator;

	/**
	 * @param callable(array):bool|null $is_translator Resolves the current
	 *        user's translator capability stack for the given pair args.
	 *        Defaults to the blog-translators check — the same one the
	 *        `wpml_is_translator` filter wraps — so it holds even on requests
	 *        (e.g. the duplicate AJAX) where that filter is not registered.
	 *        On a Blog license Translation Management is not loaded, so no
	 *        translator pairs can have been assigned and there is nothing to
	 *        be bound by: every pair is allowed, as the sibling Translate
	 *        control already is there (wpmldev-3902).
	 * @param SelfTranslator|null       $self_translator
	 */
	public function __construct( ?callable $is_translator = null, ?SelfTranslator $self_translator = null ) {
		$this->is_translator   = $is_translator ?: function ( array $args ) {
			if ( ! \WPML\Plugins::isTMLoadedForRequest() ) {
				return true;
			}

			return (bool) \wpml_tm_load_blog_translators()->is_translator( get_current_user_id(), $args );
		};
		$this->self_translator = $self_translator ?: new SelfTranslator();
	}

	public function is_allowed( $lang_from, $lang_to, $post_id = 0 ) {
		$allowed = (bool) call_user_func(
			$this->is_translator,
			[
				'lang_from'      => $lang_from,
				'lang_to'        => $lang_to,
				'admin_override' => User::isAdministrator(),
				'post_id'        => (int) $post_id,
			]
		);

		if ( ! $allowed ) {
			$allowed = $this->self_translator->can_translate_pair( $lang_from, $lang_to );
		}

		return $allowed;
	}
}
