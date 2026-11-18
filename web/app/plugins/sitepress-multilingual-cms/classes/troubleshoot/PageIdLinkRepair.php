<?php

namespace WPML\Troubleshooting\PageIdLinkRepair;

use WPML\Blocks\AttributeUrls;

class Context {

	private $homes;

	private $frontPageIds;

	private $otherSitePaths;

	private $taxonomyQueryVars;

	private $resolver;

	private $pattern;

	public function __construct( array $homes, array $frontPageIds, array $taxonomyQueryVars, callable $resolver, array $otherSitePaths = [] ) {
		$this->homes             = array_values( array_unique( array_filter( array_map( 'strval', $homes ) ) ) );
		$this->frontPageIds      = [];
		foreach ( $frontPageIds as $id ) {
			if ( (int) $id > 0 ) {
				$this->frontPageIds[ (int) $id ] = true;
			}
		}
		$this->taxonomyQueryVars = array_values( array_filter( array_map( 'strval', $taxonomyQueryVars ) ) );
		$this->resolver          = $resolver;
		$this->otherSitePaths    = array_values( array_filter( array_map( function ( $path ) {
			return trim( (string) $path, '/' );
		}, $otherSitePaths ) ) );
	}

	public function isFrontPage( int $id ): bool {
		return isset( $this->frontPageIds[ $id ] );
	}

	public function isIdParameter( string $key, ?string $value ): bool {
		if ( null === $value || '' === $value ) {
			return false;
		}
		if ( in_array( $key, [ 'page_id', 'p', 'cat_ID' ], true ) ) {
			return 1 === preg_match( '/^\d+$/', $value );
		}

		return in_array( $key, $this->taxonomyQueryVars, true );
	}

	public function resolve( string $type, string $value ): ?string {
		$url = call_user_func( $this->resolver, $type, $value );

		return is_string( $url ) && '' !== $url ? $url : null;
	}

	public function pattern(): string {
		if ( null !== $this->pattern ) {
			return $this->pattern;
		}

		$absolute = [];
		$relative = [];
		foreach ( $this->homes as $home ) {
			$parts = parse_url( $home );
			if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
				continue;
			}
			$host = preg_replace( '/^www\./i', '', (string) $parts['host'] );
			$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
			$path = rtrim( isset( $parts['path'] ) ? (string) $parts['path'] : '', '/' );

			$rest = $this->restOfPath( $path );

			$absolute[ $host . $port . $path ] = 'https?://(?:www\.)?(?i:' . preg_quote( $host, '~' ) . ')' . preg_quote( $port . $path, '~' ) . $rest;
			$relative[ $path ]                 = '' === $path ? '(?=/(?!/))' . $rest : preg_quote( $path, '~' ) . $rest;
		}

		if ( ! $absolute ) {
			$this->pattern = '';

			return $this->pattern;
		}

		uksort(
			$absolute,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		uksort(
			$relative,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		$param = '[^\s"\'<>#&\\\\]+';
		$sep   = Rewriter::SEPARATOR;

		$base = '(?:' . implode( '|', $absolute ) . '|(?<![^"\'=(\s>])(?:' . implode( '|', $relative ) . '))';

		$this->pattern = '~(?P<base>' . $base . ')\?(?P<query>' . $param . '(?:' . $sep . $param . ')*)(?P<anchor>#[^\s"\'<>\\\\]*)?~';

		return $this->pattern;
	}

	private function restOfPath( string $homePath ): string {
		$prefix = trim( $homePath, '/' );
		$inside = [];
		foreach ( $this->otherSitePaths as $other ) {
			if ( '' === $prefix ) {
				$inside[] = $other;
			} elseif ( 0 === strpos( $other, $prefix . '/' ) ) {
				$inside[] = substr( $other, strlen( $prefix ) + 1 );
			}
		}

		$exclude = $inside
			? '(?!/(?:' . implode( '|', array_map( function ( $path ) {
				return preg_quote( $path, '~' );
			}, $inside ) ) . ')(?=[/?#]))'
			: '';

		return $exclude . '(?:/[^\s"\'<>?#\\\\]*)?';
	}

	public static function signaturePattern(): string {
		return '~\?page_id=\d+' . Rewriter::SEPARATOR . 'page_id=\d+~';
	}
}

class Result {

	public $text;

	public $repaired = 0;

	public $noMatch = 0;

	public $foreign = 0;

	public $unreadable = 0;

	public $seen = 0;

	public $targetMissing = 0;

	public $replacements = [];

	public function __construct( string $text ) {
		$this->text = $text;
	}

	public function changed(): bool {
		return $this->repaired > 0;
	}
}

class Rewriter {

	const SEPARATOR = '(?:&amp;|&#0*38;|\\\\u0026|&)';

	public function rewrite( string $text, Context $ctx ): Result {
		$result = new Result( $text );

		if ( false === strpos( $text, 'page_id=' ) ) {
			return $result;
		}

		if ( '' !== $ctx->pattern() ) {
			$rewritten = preg_replace_callback(
				$ctx->pattern(),
				function ( array $match ) use ( $ctx, $result ) {
					return $this->rewriteOne( $match, $ctx, $result );
				},
				$text
			);

			if ( ! is_string( $rewritten ) ) {
				$unread             = new Result( $text );
				$unread->unreadable = 1;

				return $unread;
			}
			$result->text = $rewritten;
		}

		$signatures      = preg_match_all( Context::signaturePattern(), $text );
		$result->foreign = max( 0, (int) $signatures - $result->seen );

		return $result;
	}

	private function rewriteOne( array $match, Context $ctx, Result $result ): string {
		$pieces = preg_split( '~(' . self::SEPARATOR . ')~', $match['query'], -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $pieces ) ) {
			return $match[0];
		}

		$params     = [];
		$separators = [];
		foreach ( $pieces as $i => $piece ) {
			if ( 1 === $i % 2 ) {
				$separators[] = $piece;
				continue;
			}
			$eq       = strpos( $piece, '=' );
			$params[] = [
				'raw'   => $piece,
				'key'   => false === $eq ? $piece : substr( $piece, 0, $eq ),
				'value' => false === $eq ? null : substr( $piece, $eq + 1 ),
			];
		}

		$run = 0;
		foreach ( $params as $param ) {
			if ( ! $ctx->isIdParameter( $param['key'], $param['value'] ) ) {
				break;
			}
			$run++;
		}

		if ( $run < 2 ) {
			return $match[0];
		}

		$signature = 'page_id' === $params[0]['key'] && 'page_id' === $params[1]['key'];
		if ( $signature ) {
			$result->seen++;
		}

		for ( $i = 0; $i < $run - 1; $i++ ) {
			if ( 'page_id' !== $params[ $i ]['key'] || ( ! $signature && ! $ctx->isFrontPage( (int) $params[ $i ]['value'] ) ) ) {
				$result->noMatch++;

				return $match[0];
			}
		}

		$target    = $params[ $run - 1 ];
		$permalink = $ctx->resolve( $target['key'], (string) $target['value'] );
		if ( null === $permalink ) {
			$result->targetMissing++;

			return $match[0];
		}

		$separator = $separators[0];
		$rest      = [];
		foreach ( array_slice( $params, $run ) as $param ) {
			if ( 'lang' !== $param['key'] ) {
				$rest[] = $param['raw'];
			}
		}

		$url = '&' === $separator ? $permalink : str_replace( '&', $separator, $permalink );
		if ( $rest ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : $separator ) . implode( $separator, $rest );
		}
		$url .= isset( $match['anchor'] ) ? $match['anchor'] : '';

		$result->repaired++;
		$result->replacements[ $match[0] ] = $url;

		return $url;
	}
}

class SiteContext {

	public static function build( $sitepress ): Context {
		$codes = array_keys( (array) $sitepress->get_active_languages() );

		$homes = [ \WPML_Same_Site_Url_Normalizer::normalize_url( (string) get_option( 'home' ) . '/' ) ];
		foreach ( $codes as $code ) {
			$homes[] = \WPML_Same_Site_Url_Normalizer::normalize_url( (string) $sitepress->language_url( $code ) );
			$homes[] = \WPML_Same_Site_Url_Normalizer::normalize_url( (string) apply_filters( 'wpml_permalink', home_url( '/' ), $code, false ) );
		}

		$frontPageIds = [];
		$front        = (int) get_option( 'page_on_front' );
		if ( 'page' === get_option( 'show_on_front' ) && $front > 0 ) {
			$frontPageIds[] = $front;
			foreach ( $codes as $code ) {
				$frontPageIds[] = (int) apply_filters( 'wpml_object_id', $front, 'page', false, $code );
			}
		}

		$taxonomyVars = ( new \WPML_WP_Taxonomy_Query( $sitepress->get_wp_api() ) )->get_query_vars();

		return new Context( $homes, $frontPageIds, array_values( $taxonomyVars ), self::resolver( $sitepress, $taxonomyVars ), self::otherSitePaths() );
	}

	public static function otherSitePaths(): array {
		if ( ! is_multisite() || is_subdomain_install() ) {
			return [];
		}

		$current = get_site();
		if ( ! $current instanceof \WP_Site ) {
			return [];
		}

		$paths = [];
		foreach ( get_sites( [ 'domain' => $current->domain, 'site__not_in' => [ (int) $current->blog_id ], 'number' => 0 ] ) as $site ) {
			$path = trim( (string) $site->path, '/' );
			if ( '' !== $path ) {
				$paths[] = $path;
			}
		}

		return $paths;
	}

	public static function resolver( $sitepress, array $taxonomyVars ): callable {
		return function ( string $type, string $value ) use ( $sitepress, $taxonomyVars ) {
			if ( 'page_id' === $type || 'p' === $type ) {
				$post = get_post( (int) $value );
				if ( ! $post instanceof \WP_Post || 'trash' === $post->post_status ) {
					return null;
				}

				return self::inLanguage(
					$sitepress,
					apply_filters( 'wpml_element_language_code', null, [ 'element_id' => $post->ID, 'element_type' => $post->post_type ] ),
					function () use ( $post ) {
						return get_permalink( $post );
					}
				);
			}

			if ( 'cat_ID' === $type ) {
				$term = get_term( (int) $value, 'category' );
			} else {
				$taxonomy = array_search( $type, $taxonomyVars, true );
				$term     = false === $taxonomy ? null : get_term_by( 'slug', $value, (string) $taxonomy );
			}
			if ( ! $term instanceof \WP_Term ) {
				return null;
			}

			return self::inLanguage(
				$sitepress,
				apply_filters( 'wpml_element_language_code', null, [ 'element_id' => $term->term_taxonomy_id, 'element_type' => $term->taxonomy ] ),
				function () use ( $term, $type ) {
					return 'cat_ID' === $type ? get_category_link( $term->term_id ) : get_term_link( $term );
				}
			);
		};
	}

	private static function inLanguage( $sitepress, $language, callable $read ): ?string {
		$switch = is_string( $language ) && '' !== $language ? new \WPML_Temporary_Switch_Language( $sitepress, $language ) : null;
		$url    = $read();
		if ( $switch ) {
			$switch->restore_lang();
		}

		return is_string( $url ) && '' !== $url ? $url : null;
	}
}

class Runner {

	const OPTION = 'wpml_page_id_link_repair_last';

	const STRINGS_DONE = -1;

	const SAMPLES = 20;

	private $wpdb;

	private $ctx;

	private $rewriter;

	private $followRecord;

	public function __construct( $wpdb, Context $ctx, ?Rewriter $rewriter = null, ?callable $followRecord = null ) {
		$this->wpdb         = $wpdb;
		$this->ctx          = $ctx;
		$this->rewriter     = $rewriter ?: new Rewriter();
		$this->followRecord = $followRecord ?: [ \WPML\TM\Jobs\TranslationRecord::class, 'followPostTranslation' ];
	}

	public function run( bool $dryRun, int $cursorPosts, int $cursorStrings, int $limit = 100 ): array {
		$limit  = max( 1, $limit );
		$answer = [
			'dryRun'         => $dryRun,
			'counts'         => [ 'content' => 0, 'excerpt' => 0, 'block' => 0, 'string' => 0 ],
			'rows'           => 0,
			'no_match'       => 0,
			'target_missing' => 0,
			'foreign'        => 0,
			'unreadable'     => 0,
			'samples'        => [],
			'cursorPosts'    => $cursorPosts,
			'cursorStrings'  => $cursorStrings,
			'done'           => false,
		];

		if ( ! $dryRun && 0 === $cursorPosts && 0 === $cursorStrings ) {
			update_option( self::OPTION, self::emptyRecord(), false );
		}

		if ( $cursorStrings >= 0 ) {
			$answer = $this->stringBatch( $answer, $dryRun, $cursorStrings, $limit );
		} else {
			$answer = $this->postBatch( $answer, $dryRun, $cursorPosts, $limit );
		}

		if ( ! $dryRun ) {
			$this->record( $answer );
		}

		return $answer;
	}

	private function stringBatch( array $answer, bool $dryRun, int $cursor, int $limit ): array {
		$wpdb         = $this->wpdb;
		$translations = $wpdb->prefix . 'icl_string_translations';

		if ( $translations !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $translations ) ) ) ) {
			$answer['cursorStrings'] = self::STRINGS_DONE;

			return $answer;
		}

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT st.id, st.language, st.value, s.name
				FROM {$wpdb->prefix}icl_string_translations st
				LEFT JOIN {$wpdb->prefix}icl_strings s ON s.id = st.string_id
				WHERE st.id > %d AND st.value LIKE %s
				ORDER BY st.id ASC
				LIMIT %d",
				$cursor,
				'%' . $wpdb->esc_like( 'page_id=' ) . '%',
				$limit
			)
		);

		foreach ( $rows as $row ) {
			$cursor = (int) $row->id;
			$result = $this->rewriter->rewrite( (string) $row->value, $this->ctx );
			$answer = $this->countLeft( $answer, $result );
			if ( ! $result->changed() ) {
				continue;
			}

			$answer['counts']['string'] += $result->repaired;
			$answer['rows']++;
			$answer = $this->sample(
				$answer,
				[
					'type'     => 'string',
					'id'       => (int) $row->id,
					'name'     => (string) $row->name,
					'language' => (string) $row->language,
					'links'    => $result->repaired,
				]
			);

			if ( ! $dryRun ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_string_translations SET value = %s, translation_date = NOW() WHERE id = %d", $result->text, (int) $row->id ) );
				self::log( 'string', (int) $row->id, $result->repaired );
			}
		}

		$answer['cursorStrings'] = count( $rows ) < $limit ? self::STRINGS_DONE : $cursor;

		return $answer;
	}

	private function postBatch( array $answer, bool $dryRun, int $cursor, int $limit ): array {
		$wpdb = $this->wpdb;
		$like = '%' . $wpdb->esc_like( 'page_id=' ) . '%';

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_type, post_title, post_content, post_excerpt
				FROM {$wpdb->posts}
				WHERE ID > %d
					AND post_type NOT IN ('revision')
					AND post_status <> 'auto-draft'
					AND ( post_content LIKE %s OR post_excerpt LIKE %s )
				ORDER BY ID ASC
				LIMIT %d",
				$cursor,
				$like,
				$like,
				$limit
			)
		);

		foreach ( $rows as $row ) {
			$cursor  = (int) $row->ID;
			$content = $this->rewriteContent( (string) $row->post_content );
			$excerpt = $this->rewriter->rewrite( (string) $row->post_excerpt, $this->ctx );
			$answer  = $this->countLeft( $answer, $excerpt );
			$answer['no_match']       += $content['no_match'];
			$answer['target_missing'] += $content['target_missing'];
			$answer['foreign']        += $content['foreign'];
			$answer['unreadable']     += $content['unreadable'];

			$update = [];
			if ( $content['text'] !== (string) $row->post_content ) {
				$update['post_content'] = $content['text'];
			}
			if ( $excerpt->changed() ) {
				$update['post_excerpt'] = $excerpt->text;
			}
			if ( ! $update ) {
				continue;
			}

			$links                        = $content['content'] + $content['block'] + $excerpt->repaired;
			$answer['counts']['content'] += $content['content'];
			$answer['counts']['block']   += $content['block'];
			$answer['counts']['excerpt'] += $excerpt->repaired;
			$answer['rows']++;
			$answer = $this->sample(
				$answer,
				[
					'type'     => 'post',
					'id'       => (int) $row->ID,
					'title'    => (string) $row->post_title,
					'language' => (string) apply_filters( 'wpml_element_language_code', '', [ 'element_id' => (int) $row->ID, 'element_type' => (string) $row->post_type ] ),
					'links'    => $links,
				]
			);

			if ( ! $dryRun ) {
				$this->writePost( (int) $row->ID, (string) $row->post_type, $update );
				self::log( 'post', (int) $row->ID, $links );
			}
		}

		$answer['cursorPosts'] = $cursor;
		$answer['done']        = count( $rows ) < $limit;

		return $answer;
	}

	private function writePost( int $id, string $postType, array $update ): void {
		wp_save_post_revision( $id );

		$updated = $this->wpdb->update( $this->wpdb->posts, $update, [ 'ID' => $id ] );
		if ( false === $updated ) {
			return;
		}

		clean_post_cache( $id );

		$fields = [];
		if ( isset( $update['post_content'] ) ) {
			$fields['body'] = $update['post_content'];
		}
		if ( isset( $update['post_excerpt'] ) ) {
			$fields['excerpt'] = $update['post_excerpt'];
		}
		call_user_func( $this->followRecord, $id, 'post_' . $postType, $fields );
	}

	public function rewriteContent( string $content ): array {
		$out = [ 'text' => $content, 'content' => 0, 'block' => 0, 'no_match' => 0, 'target_missing' => 0, 'foreign' => 0, 'unreadable' => 0 ];
		if ( false === strpos( $content, 'page_id=' ) ) {
			return $out;
		}

		$parts = preg_split( '#(<!-- wp:\S+ \{.*?\}\s*/?-->)#s', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			$out['unreadable'] = 1;

			return $out;
		}

		$replacements = [];
		foreach ( $parts as $i => $part ) {
			if ( 1 === $i % 2 ) {
				$parts[ $i ] = $this->rewriteDelimiter( $part, $out );
				continue;
			}
			$result                 = $this->rewriter->rewrite( $part, $this->ctx );
			$parts[ $i ]            = $result->text;
			$out['content']        += $result->repaired;
			$out['no_match']       += $result->noMatch;
			$out['target_missing'] += $result->targetMissing;
			$out['foreign']        += $result->foreign;
			$out['unreadable']     += $result->unreadable;
			$replacements          += self::decodedReplacements( $result->replacements );
		}

		$out['text'] = implode( '', $parts );
		if ( $replacements ) {
			$out['text'] = AttributeUrls::replaceInDelimiters( $out['text'], $replacements );
		}

		return $out;
	}

	private function rewriteDelimiter( string $delimiter, array &$out ): string {
		if ( false === strpos( $delimiter, 'page_id=' ) ) {
			return $delimiter;
		}

		$before = $out;

		$rewritten = preg_replace_callback(
			'/"([^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+)"/s',
			function ( array $value ) use ( &$out ) {
				if ( false === strpos( $value[1], 'page_id=' ) ) {
					return $value[0];
				}
				$result                 = $this->rewriter->rewrite( AttributeUrls::decodeValue( $value[1] ), $this->ctx );
				$out['no_match']       += $result->noMatch;
				$out['target_missing'] += $result->targetMissing;
				$out['foreign']        += $result->foreign;
				$out['unreadable']     += $result->unreadable;
				if ( ! $result->changed() ) {
					return $value[0];
				}
				$encoded = AttributeUrls::encodeValue( $result->text );
				if ( null === $encoded ) {
					return $value[0];
				}
				if ( false !== strpos( $value[1], '\\/' ) ) {
					$encoded = str_replace( '/', '\\/', $encoded );
				}
				$out['block'] += $result->repaired;

				return '"' . $encoded . '"';
			},
			$delimiter
		);

		if ( ! is_string( $rewritten ) ) {
			$out = $before;
			$out['unreadable']++;

			return $delimiter;
		}

		return $rewritten;
	}

	private static function decodedReplacements( array $replacements ): array {
		$decoded = [];
		foreach ( $replacements as $from => $to ) {
			$decoded[ html_entity_decode( (string) $from, ENT_QUOTES ) ] = html_entity_decode( (string) $to, ENT_QUOTES );
		}

		return $decoded;
	}

	private function countLeft( array $answer, Result $result ): array {
		$answer['no_match']       += $result->noMatch;
		$answer['target_missing'] += $result->targetMissing;
		$answer['foreign']        += $result->foreign;
		$answer['unreadable']     += $result->unreadable;

		return $answer;
	}

	private function sample( array $answer, array $sample ): array {
		if ( count( $answer['samples'] ) < self::SAMPLES ) {
			$answer['samples'][] = $sample;
		}

		return $answer;
	}

	private function record( array $answer ): void {
		$record = get_option( self::OPTION );
		$record = is_array( $record ) && isset( $record['counts'] ) ? $record : self::emptyRecord();

		foreach ( $answer['counts'] as $field => $count ) {
			$record['counts'][ $field ] = (int) ( $record['counts'][ $field ] ?? 0 ) + (int) $count;
		}
		foreach ( [ 'rows', 'no_match', 'target_missing', 'foreign', 'unreadable' ] as $key ) {
			$record[ $key ] = (int) ( $record[ $key ] ?? 0 ) + (int) $answer[ $key ];
		}
		if ( $answer['done'] ) {
			$record['finished'] = true;
			$record['time']     = time();
		}

		update_option( self::OPTION, $record, false );
	}

	private static function emptyRecord(): array {
		return [
			'finished'       => false,
			'time'           => 0,
			'counts'         => [ 'content' => 0, 'excerpt' => 0, 'block' => 0, 'string' => 0 ],
			'rows'           => 0,
			'no_match'       => 0,
			'target_missing' => 0,
			'foreign'        => 0,
			'unreadable'     => 0,
		];
	}

	private static function log( string $kind, int $id, int $links ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( 'WPML: repaired %d page_id link(s) in %s %d (wpmldev-12192)', $links, $kind, $id ) );
		}
	}

	public static function forSite( $sitepress ): self {
		global $wpdb;

		return new self( $wpdb, SiteContext::build( $sitepress ) );
	}
}
