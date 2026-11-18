<?php

namespace WPML\Compatibility\Divi\ThemeBuilderRepair;

class Command {

	public function __invoke( $args, $assocArgs ) {
		$dryRun = isset( $assocArgs['dry-run'] );
		$answer = $this->runner()->run( $dryRun );

		if ( ! $answer['todo'] ) {
			\WP_CLI::success( 'The site has one Theme Builder for all languages. Nothing to repair.' );
			return;
		}

		if ( $answer['keep'] ) {
			\WP_CLI::log( 'Theme Builder that stays: ' . self::post( $answer['keep'] ) );
		}
		foreach ( $answer['unpublish'] as $post ) {
			\WP_CLI::log( ( $dryRun ? 'To unpublish: ' : 'Unpublished: ' ) . self::post( $post ) . ', templates: ' . $post['templates'] );
		}
		foreach ( $answer['keptTemplates'] as $template ) {
			\WP_CLI::log( 'Template that stays: ' . self::template( $template ) );
		}
		foreach ( $answer['otherTemplates'] as $template ) {
			\WP_CLI::log( 'Template only an unpublished Theme Builder lists: ' . self::template( $template ) );
		}
		foreach ( $answer['links'] as $link ) {
			\WP_CLI::log( self::link( $link, $dryRun ) );
		}
		if ( ! $dryRun && LayoutLinks::countToLink( $answer['links'] ) > 0 ) {
			\WP_CLI::log( 'Layouts linked as translations: ' . $answer['linked'] );
		}
		if ( $answer['languageRows'] > 0 ) {
			\WP_CLI::log( ( $dryRun ? 'Language records to delete: ' : 'Language records deleted: ' ) . ( $dryRun ? $answer['languageRows'] : $answer['rowsDeleted'] ) );
		}
		if ( $answer['settings'] ) {
			\WP_CLI::log( $dryRun ? 'Divi Theme Builder and Divi Templates will be set back to Not translatable.' : 'Divi Theme Builder and Divi Templates are set back to Not translatable.' );
		}

		if ( $dryRun ) {
			\WP_CLI::success( 'Dry run: nothing was written.' );
			return;
		}

		\WP_CLI::success( sprintf( 'Repaired. %d Theme Builder(s) moved to draft.', $answer['unpublished'] ) );
	}

	protected function runner() {
		global $wpdb, $sitepress;

		return Runner::forSite( $wpdb, $sitepress );
	}

	private static function post( array $post ) {
		return sprintf( 'post %d "%s" (language %s, %s)', $post['id'], $post['title'], null === $post['language'] ? 'none' : $post['language'], $post['date'] );
	}

	private static function template( array $template ) {
		return sprintf( 'post %d "%s"', $template['id'], $template['title'] );
	}

	private static function link( array $link, $dryRun ) {
		$what = sprintf( 'post %d (%s layout of template %d)', $link['layout'], $link['kind'], $link['template'] );

		if ( LayoutLinks::ACTION_LINK === $link['action'] ) {
			return sprintf( '%s%s as the %s translation of post %d', $dryRun ? 'Layout to link: ' : 'Linked: ', $what, $link['language'], $link['original'] );
		}

		$reasons = [
			LayoutLinks::REASON_EXISTS           => sprintf( 'post %d already has a %s translation', $link['original'], $link['language'] ),
			LayoutLinks::REASON_GROUP            => 'it already belongs to a translation group',
			LayoutLinks::REASON_OTHER_LANGUAGE   => sprintf( 'its language and the language of post %d do not allow it', $link['original'] ),
			LayoutLinks::REASON_NO_ORIGINAL_ROW  => sprintf( 'post %d has no language of its own', $link['original'] ),
			LayoutLinks::REASON_NOT_TRANSLATABLE => 'the layout type is not translatable',
			LayoutLinks::REASON_MISSING          => 'the layout no longer exists',
		];

		return sprintf( 'Layout not linked: %s, %s', $what, isset( $reasons[ $link['reason'] ] ) ? $reasons[ $link['reason'] ] : $link['reason'] );
	}
}
