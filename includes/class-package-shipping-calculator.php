<?php
/**
 * Pure calculation logic for the Package Duties shipping method.
 *
 * All methods are stateless and have no dependencies on WordPress globals,
 * making them straightforward to test independently.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Package_Shipping_Calculator {

	/**
	 * Determine whether free shipping applies for the given subtotal.
	 *
	 * @param float $subtotal  Cart subtotal (after discounts, before shipping).
	 * @param float $threshold Free-shipping threshold (inclusive).
	 * @return bool            True when the subtotal meets or exceeds the threshold.
	 */
	public static function should_free_shipping( float $subtotal, float $threshold ): bool {
		return $subtotal >= $threshold;
	}

	/**
	 * Calculate the number of packages needed for a given total quantity.
	 *
	 * Uses ceiling division so that any overflow into a new package is counted.
	 * Example: 21 items at 20 items/package → ceil(21/20) = 2 packages.
	 *
	 * @param int $total_qty        Total number of units in the cart.
	 * @param int $items_per_package Maximum units that fit in one package.
	 * @return int                  Number of packages required (minimum 1).
	 */
	public static function count_packages( int $total_qty, int $items_per_package ): int {
		if ( $total_qty <= 0 || $items_per_package <= 0 ) {
			return 0;
		}

		return (int) ceil( $total_qty / $items_per_package );
	}

	/**
	 * Compute the total shipping cost for the given number of packages.
	 *
	 * @param int   $packages         Number of packages.
	 * @param float $cost_per_package Cost per single package.
	 * @return float                  Total shipping cost.
	 */
	public static function compute_shipping_cost( int $packages, float $cost_per_package ): float {
		if ( $packages <= 0 || $cost_per_package <= 0.0 ) {
			return 0.0;
		}

		return (float) $packages * $cost_per_package;
	}

	/**
	 * Compute the duties amount based on the cart subtotal.
	 *
	 * Duties are ONLY applied when shipping is NOT free. When shipping is free
	 * (order meets or exceeds the free-shipping threshold) this method returns 0.
	 *
	 * @param float $subtotal         Cart subtotal used as the duties base.
	 * @param float $percent          Duties percentage (0–100).
	 * @param bool  $shipping_is_free Whether free shipping applies for this order.
	 * @return float                  Duties amount, or 0.0 if shipping is free.
	 */
	public static function compute_duties(
		float $subtotal,
		float $percent,
		bool $shipping_is_free
	): float {
		if ( $shipping_is_free ) {
			return 0.0;
		}

		if ( $subtotal <= 0.0 || $percent <= 0.0 ) {
			return 0.0;
		}

		return $subtotal * ( $percent / 100.0 );
	}

	/**
	 * Aggregate a full cost breakdown for a shipment.
	 *
	 * Returns an associative array so callers can display individual components
	 * (e.g. in the checkout rate label) if desired.
	 *
	 * @param int   $total_qty         Total quantity of units in the cart.
	 * @param float $subtotal          Cart subtotal.
	 * @param float $threshold         Free-shipping threshold.
	 * @param float $cost_per_package  Cost per package.
	 * @param int   $items_per_package Units per package.
	 * @param float $duties_percent    Duties percentage (0–100).
	 * @return array{
	 *   is_free: bool,
	 *   packages: int,
	 *   shipping_cost: float,
	 *   duties_cost: float,
	 *   total_cost: float,
	 * }
	 */
	public static function calculate(
		int   $total_qty,
		float $subtotal,
		float $threshold,
		float $cost_per_package,
		int   $items_per_package,
		float $duties_percent
	): array {
		$is_free      = self::should_free_shipping( $subtotal, $threshold );
		$packages     = $is_free ? 0 : self::count_packages( $total_qty, $items_per_package );
		$shipping     = self::compute_shipping_cost( $packages, $cost_per_package );
		$duties       = self::compute_duties( $subtotal, $duties_percent, $is_free );
		$total        = $shipping + $duties;

		return [
			'is_free'       => $is_free,
			'packages'      => $packages,
			'shipping_cost' => $shipping,
			'duties_cost'   => $duties,
			'total_cost'    => $total,
		];
	}
}
