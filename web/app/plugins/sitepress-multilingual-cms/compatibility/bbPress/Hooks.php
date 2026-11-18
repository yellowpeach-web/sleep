<?php

namespace WPML\Compatibility\BBPress;

class Hooks implements \IWPML_Frontend_Action {

	const USER_PAGE_URL_FILTERS = [
		'bbp_get_user_topics_created_url',
		'bbp_get_user_replies_created_url',
		'bbp_get_user_engagements_url',
		'bbp_get_favorites_permalink',
		'bbp_get_subscriptions_permalink',
		'bbp_get_user_edit_profile_url',
	];

	const PAGINATION_BASE_FILTERS = [
		'bbp_get_topics_pagination_base',
		'bbp_get_replies_pagination_base',
	];

	public function add_hooks() {
		add_filter( 'icl_ls_languages', [ $this, 'linkToDisplayedUserPage' ] );

		if ( $this->isLanguageAUrlParameter() ) {
			$this->addUrlParameterHooks();
		}
	}

	public function moveProfileQueryToEnd( $url, $userId ) {
		if ( ! $this->hasQuery( $url ) ) {
			return $url;
		}

		return $this->moveQueryAfterAppendedPath( $url, (string) bbp_get_user_profile_url( $userId ) );
	}

	public function moveTopicQueryToEnd( $url, $replyId, $redirectTo = '' ) {
		if ( ! $this->hasQuery( $url ) ) {
			return $url;
		}

		$topicId = bbp_is_topic( $replyId ) ? bbp_get_topic_id( $replyId ) : bbp_get_reply_topic_id( $replyId );

		return $this->moveQueryAfterAppendedPath( $url, (string) bbp_get_topic_permalink( $topicId, $redirectTo ) );
	}

	public function moveListQueryToEnd( $paginationBase ) {
		if ( ! is_string( $paginationBase ) ) {
			return $paginationBase;
		}

		$pagePath = user_trailingslashit( bbp_get_paged_slug() . '/%#%/' );
		$listPath = substr( $paginationBase, 0, - strlen( $pagePath ) );

		if ( $listPath . $pagePath !== $paginationBase || false === strpos( $listPath, '?' ) ) {
			return $paginationBase;
		}

		return $this->appendPathBeforeQuery( untrailingslashit( $listPath ), $pagePath );
	}

	public function moveTopicPaginationQueryToEnd( $paginationArguments ) {
		if ( is_array( $paginationArguments ) && isset( $paginationArguments['base'] ) ) {
			$paginationArguments['base'] = $this->moveListQueryToEnd( $paginationArguments['base'] );
		}

		return $paginationArguments;
	}

	public function linkToDisplayedUserPage( $languages ) {
		if ( ! is_array( $languages ) ) {
			return $languages;
		}

		$userPageUrl = $this->getDisplayedUserPageUrl();

		if ( ! $userPageUrl ) {
			return $languages;
		}

		foreach ( array_keys( $languages ) as $code ) {
			$languages[ $code ]['url'] = apply_filters( 'wpml_permalink', $userPageUrl, $code );
		}

		return $languages;
	}

	private function isLanguageAUrlParameter(): bool {
		return WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === (int) apply_filters( 'wpml_setting', 0, 'language_negotiation_type' );
	}

	private function addUrlParameterHooks() {
		foreach ( self::USER_PAGE_URL_FILTERS as $filter ) {
			add_filter( $filter, [ $this, 'moveProfileQueryToEnd' ], 10, 2 );
		}

		foreach ( self::PAGINATION_BASE_FILTERS as $filter ) {
			add_filter( $filter, [ $this, 'moveListQueryToEnd' ] );
		}

		add_filter( 'bbp_get_topic_pagination', [ $this, 'moveTopicPaginationQueryToEnd' ] );
		add_filter( 'bbp_get_reply_url', [ $this, 'moveTopicQueryToEnd' ], 10, 3 );
	}

	private function hasQuery( $url ): bool {
		return is_string( $url ) && false !== strpos( $url, '?' );
	}

	private function moveQueryAfterAppendedPath( $url, string $baseUrl ) {
		$basePath = trailingslashit( $baseUrl );

		if ( ! is_string( $url ) || false === strpos( $baseUrl, '?' ) || 0 !== strpos( $url, $basePath ) ) {
			return $url;
		}

		$appendedParts = explode( '#', substr( $url, strlen( $basePath ) ), 2 );
		$fragment      = isset( $appendedParts[1] ) ? '#' . $appendedParts[1] : '';

		return $this->appendPathBeforeQuery( $baseUrl, $appendedParts[0] ) . $fragment;
	}

	private function appendPathBeforeQuery( string $url, string $path ): string {
		$urlParts = explode( '?', $url, 2 );

		return trailingslashit( $urlParts[0] ) . $path . ( isset( $urlParts[1] ) ? '?' . $urlParts[1] : '' );
	}

	private function getDisplayedUserPageUrl(): string {
		if ( ! bbp_is_single_user() ) {
			return '';
		}

		$userId = bbp_get_displayed_user_id();

		if ( bbp_is_single_user_edit() ) {
			return (string) bbp_get_user_profile_edit_url( $userId );
		}
		if ( bbp_is_single_user_topics() ) {
			return (string) bbp_get_user_topics_created_url( $userId );
		}
		if ( bbp_is_single_user_replies() ) {
			return (string) bbp_get_user_replies_created_url( $userId );
		}
		if ( bbp_is_single_user_engagements() ) {
			return (string) bbp_get_user_engagements_url( $userId );
		}
		if ( bbp_is_favorites() ) {
			return (string) bbp_get_favorites_permalink( $userId );
		}
		if ( bbp_is_subscriptions() ) {
			return (string) bbp_get_subscriptions_permalink( $userId );
		}

		return (string) bbp_get_user_profile_url( $userId );
	}
}
