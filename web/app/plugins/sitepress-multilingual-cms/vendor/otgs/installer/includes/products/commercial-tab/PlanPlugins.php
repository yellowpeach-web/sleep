<?php

namespace OTGS\Installer\CommercialTab;

use OTGS\Installer\AppPage;
use OTGS\Installer\OutboundLink;

class PlanPlugins {

	public static function forSubscription( array $products, $subscription_type, $upgrade_options = null ) {
		if ( empty( $subscription_type ) || empty( $products['packages'] ) ) {
			return [];
		}

		$all_products = self::allProducts( $products['packages'] );
		$current      = self::currentProduct( $all_products, $subscription_type );
		$next         = $current ? self::nextPlan( $all_products, $current ) : null;

		if ( ! $next ) {
			return [];
		}

		$downloads = [];
		foreach ( array_diff( $next['plugins'], $current['plugins'] ) as $slug ) {
			if ( isset( $products['downloads']['plugins'][ $slug ] ) ) {
				$downloads[ $slug ] = $products['downloads']['plugins'][ $slug ];
			}
		}

		if ( ! $downloads ) {
			return [];
		}

		return [
			'plan'              => empty( $next['subscription_type_text'] ) ? $next['name'] : $next['subscription_type_text'],
			'subscription_type' => (int) $next['subscription_type'],
			'url'               => self::url( $products, $current, $next, $upgrade_options ),
			'downloads'         => $downloads,
		];
	}

	private static function allProducts( array $packages ) {
		$all_products = [];

		foreach ( $packages as $package ) {
			$product_lists = [ isset( $package['products'] ) ? $package['products'] : [] ];
			if ( ! empty( $package['sub-packages'] ) ) {
				foreach ( $package['sub-packages'] as $sub_package ) {
					$product_lists[] = isset( $sub_package['products'] ) ? $sub_package['products'] : [];
				}
			}

			foreach ( $product_lists as $product_list ) {
				foreach ( $product_list as $product ) {
					if ( isset( $product['plugins'], $product['subscription_type'] ) && is_array( $product['plugins'] ) ) {
						$all_products[] = $product;
					}
				}
			}
		}

		return $all_products;
	}

	private static function currentProduct( array $all_products, $subscription_type ) {
		foreach ( $all_products as $product ) {
			$equivalent = isset( $product['subscription_type_equivalent'] ) ? $product['subscription_type_equivalent'] : '';

			$is_type       = (int) $product['subscription_type'] === (int) $subscription_type;
			$is_equivalent = '' !== (string) $equivalent && (int) $equivalent === (int) $subscription_type;

			if ( $is_type || $is_equivalent ) {
				return $product;
			}
		}

		return null;
	}

	private static function nextPlan( array $all_products, array $current ) {
		$next = null;

		foreach ( $all_products as $product ) {
			if ( empty( $product['price'] ) || ! empty( $product['deprecated'] ) ) {
				continue;
			}

			if ( array_diff( $current['plugins'], $product['plugins'] ) || ! array_diff( $product['plugins'], $current['plugins'] ) ) {
				continue;
			}

			if ( ! $next || self::isSmallerStep( $product, $next ) ) {
				$next = $product;
			}
		}

		return $next;
	}

	private static function isSmallerStep( array $candidate, array $best ) {
		$candidate_size = count( $candidate['plugins'] );
		$best_size      = count( $best['plugins'] );

		if ( $candidate_size === $best_size ) {
			return (float) $candidate['price'] < (float) $best['price'];
		}

		return $candidate_size < $best_size;
	}

	private static function url( array $products, array $current, array $next, $upgrade_options ) {
		if ( ! empty( $upgrade_options[ $next['subscription_type'] ][ $current['subscription_type'] ]['url'] ) ) {
			return $upgrade_options[ $next['subscription_type'] ][ $current['subscription_type'] ]['url'];
		}

		$product_url = isset( $products['url'] ) ? $products['url'] : '';

		return OutboundLink::to(
			AppPage::url( $product_url, 'purchase' ),
			[
				'medium'   => 'settings',
				'campaign' => 'account',
				'content'  => 'plan-plugins',
			]
		);
	}
}
