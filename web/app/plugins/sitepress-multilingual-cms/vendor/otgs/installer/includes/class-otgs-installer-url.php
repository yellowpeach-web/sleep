<?php

class OTGS_Installer_Url {

	public static function build( array $parts ) {
		if ( isset( $parts['path'] ) && '' !== $parts['path'] && '/' !== substr( (string) $parts['path'], 0, 1 ) ) {
			$parts['path'] = '/' . $parts['path'];
		}

		$url = '';

		if ( ! empty( $parts['scheme'] ) ) {
			$url .= $parts['scheme'] . '://';
		}

		if ( ! empty( $parts['user'] ) ) {
			$url .= $parts['user'];
			if ( isset( $parts['pass'] ) ) {
				$url .= ':' . $parts['pass'];
			}
			$url .= '@';
		}

		if ( ! empty( $parts['host'] ) ) {
			$url .= $parts['host'];
		}

		if ( ! empty( $parts['port'] ) ) {
			$url .= ':' . $parts['port'];
		}

		if ( ! empty( $parts['path'] ) ) {
			$url .= $parts['path'];
		}

		if ( ! empty( $parts['query'] ) && is_string( $parts['query'] ) ) {
			$url .= '?' . $parts['query'];
		}

		if ( ! empty( $parts['fragment'] ) ) {
			$url .= '#' . $parts['fragment'];
		}

		return $url;
	}
}
