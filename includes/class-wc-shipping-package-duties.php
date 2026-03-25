<?php
/**
 * WooCommerce shipping method: per-package cost with duties.
 *
 * Extends WC_Shipping_Method to provide a configurable shipping rate that:
 *  - Is available only for a single, admin-selected destination country.
 *  - Applies free shipping when the cart subtotal meets or exceeds a threshold.
 *  - Below the threshold: charges per package (ceil(qty / items_per_package))
 *    plus a percentage-based duties fee on the cart subtotal.
 *  - Above the threshold: zero cost for both shipping and duties.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Shipping_Package_Duties extends WC_Shipping_Method {

	/**
	 * Constructor: set method metadata and load saved settings.
	 *
	 * @param int $instance_id Shipping zone instance ID (0 when called as a class reference).
	 */
	public function __construct( int $instance_id = 0 ) {
		$this->id                 = 'woo_package_duties';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Package Shipping with Duties', 'woo-package-shipping' );
		$this->method_description = __(
			'Calculates shipping cost per package and applies a duties percentage on the cart subtotal. Free shipping (and no duties) when the order meets the configured threshold.',
			'woo-package-shipping'
		);
		$this->supports = [
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		];

		$this->init();
	}

	/**
	 * Load form fields and saved settings.
	 */
	private function init(): void {
		$this->init_form_fields();
		$this->init_settings();

		// $this->title is the instance label WooCommerce displays in the zone
		// methods list and at checkout. It must be set explicitly after
		// init_settings() so it reflects the saved value (or the default).
		$this->title = $this->get_option( 'title', __( 'Package Shipping + Duties', 'woo-package-shipping' ) );

		// Persist settings when saved from the admin panel.
		add_action(
			'woocommerce_update_options_shipping_' . $this->id,
			[ $this, 'process_admin_options' ]
		);
	}

	// -------------------------------------------------------------------------
	// Form fields definition
	// -------------------------------------------------------------------------

	/**
	 * Define admin settings fields for this shipping method instance.
	 */
	public function init_form_fields(): void {
		$countries = WC()->countries->get_countries();

		$this->instance_form_fields = [
			'title'             => [
				'title'       => __( 'Method title', 'woo-package-shipping' ),
				'type'        => 'text',
				'description' => __( 'Label shown to the customer at checkout.', 'woo-package-shipping' ),
				'default'     => __( 'Package Shipping + Duties', 'woo-package-shipping' ),
				'desc_tip'    => true,
			],
			'destination_country' => [
				'title'       => __( 'Destination country', 'woo-package-shipping' ),
				'type'        => 'select',
				'description' => __( 'This method will only appear when shipping to this country.', 'woo-package-shipping' ),
				'default'     => '',
				'options'     => array_merge( [ '' => __( '— Select a country —', 'woo-package-shipping' ) ], $countries ),
				'desc_tip'    => true,
			],
			'free_shipping_threshold' => [
				'title'             => __( 'Free shipping threshold (€)', 'woo-package-shipping' ),
				'type'              => 'price',
				'description'       => __( 'Orders equal to or above this subtotal get free shipping and no duties.', 'woo-package-shipping' ),
				'default'           => '1500',
				'desc_tip'          => true,
				'custom_attributes' => [ 'min' => '0', 'step' => '0.01' ],
			],
			'cost_per_package'  => [
				'title'             => __( 'Cost per package (€)', 'woo-package-shipping' ),
				'type'              => 'price',
				'description'       => __( 'Fixed shipping cost charged for each package dispatched.', 'woo-package-shipping' ),
				'default'           => '170',
				'desc_tip'          => true,
				'custom_attributes' => [ 'min' => '0', 'step' => '0.01' ],
			],
			'items_per_package' => [
				'title'             => __( 'Items per package', 'woo-package-shipping' ),
				'type'              => 'number',
				'description'       => __( 'Maximum number of items that fit in one package. From item N+1 onward a new package is used.', 'woo-package-shipping' ),
				'default'           => '20',
				'desc_tip'          => true,
				'custom_attributes' => [ 'min' => '1', 'step' => '1' ],
			],
			'duties_percent'    => [
				'title'             => __( 'Duties percentage (%)', 'woo-package-shipping' ),
				'type'              => 'number',
				'description'       => __( 'Percentage applied to the cart subtotal as duties. Only charged when shipping is not free.', 'woo-package-shipping' ),
				'default'           => '15',
				'desc_tip'          => true,
				'custom_attributes' => [ 'min' => '0', 'max' => '100', 'step' => '0.01' ],
			],
		];
	}

	// -------------------------------------------------------------------------
	// Sanitization / validation
	// -------------------------------------------------------------------------

	/**
	 * Validate and sanitize all settings before saving.
	 *
	 * Overrides the parent to add domain-specific constraints (e.g. items_per_package ≥ 1,
	 * duties percent 0–100, country in WooCommerce list).
	 */
	public function process_admin_options(): bool {
		$saved = parent::process_admin_options();

		// Re-read just-saved values so we can clamp any out-of-range inputs.
		$threshold     = (float) $this->get_option( 'free_shipping_threshold', 1500 );
		$cost          = (float) $this->get_option( 'cost_per_package', 170 );
		$items         = (int)   $this->get_option( 'items_per_package', 20 );
		$percent       = (float) $this->get_option( 'duties_percent', 15 );
		$country       = (string) $this->get_option( 'destination_country', '' );
		$valid_countries = array_keys( WC()->countries->get_countries() );

		$needs_update = false;

		if ( $threshold < 0 ) {
			$this->update_option( 'free_shipping_threshold', 0 );
			$needs_update = true;
		}

		if ( $cost < 0 ) {
			$this->update_option( 'cost_per_package', 0 );
			$needs_update = true;
		}

		if ( $items < 1 ) {
			$this->update_option( 'items_per_package', 1 );
			$needs_update = true;
		}

		if ( $percent < 0 ) {
			$this->update_option( 'duties_percent', 0 );
			$needs_update = true;
		} elseif ( $percent > 100 ) {
			$this->update_option( 'duties_percent', 100 );
			$needs_update = true;
		}

		// Reject unknown country codes.
		if ( $country !== '' && ! in_array( $country, $valid_countries, true ) ) {
			$this->update_option( 'destination_country', '' );
			$needs_update = true;
		}

		return $saved;
	}

	// -------------------------------------------------------------------------
	// Availability check
	// -------------------------------------------------------------------------

	/**
	 * Return true only when:
	 *  1. The method is enabled.
	 *  2. A destination country is configured.
	 *  3. The package destination country matches the configured country.
	 *
	 * WooCommerce also checks zone restrictions independently; this guard is an
	 * extra safety net in case the zone covers more countries than intended.
	 *
	 * @param array $package WooCommerce package array.
	 * @return bool
	 */
	public function is_available( $package ): bool {
		if ( 'yes' !== $this->get_option( 'enabled', 'yes' ) ) {
			return false;
		}

		$configured_country = (string) $this->get_option( 'destination_country', '' );
		if ( '' === $configured_country ) {
			return false;
		}

		$destination_country = isset( $package['destination']['country'] )
			? (string) $package['destination']['country']
			: '';

		return $destination_country === $configured_country;
	}

	// -------------------------------------------------------------------------
	// Rate calculation
	// -------------------------------------------------------------------------

	/**
	 * Calculate and register the shipping rate for the given package.
	 *
	 * Uses Package_Shipping_Calculator for all numeric logic; this method is
	 * responsible only for reading settings, delegating, and calling add_rate().
	 *
	 * @param array $package WooCommerce package array.
	 */
	public function calculate_shipping( $package = [] ): void {
		// Read and sanitize settings.
		$threshold     = (float) $this->get_option( 'free_shipping_threshold', 1500 );
		$cost_per_pkg  = (float) $this->get_option( 'cost_per_package', 170 );
		$items_per_pkg = max( 1, (int) $this->get_option( 'items_per_package', 20 ) );
		$duties_pct    = (float) $this->get_option( 'duties_percent', 15 );

		// Sum total quantity across all items in this package.
		$total_qty = 0;
		foreach ( $package['contents'] as $item ) {
			$total_qty += (int) $item['quantity'];
		}

		// Use the package subtotal provided by WooCommerce (after discounts, before shipping).
		// WC populates $package['contents_cost'] with the sum of line subtotals.
		$subtotal = isset( $package['contents_cost'] ) ? (float) $package['contents_cost'] : 0.0;

		// Delegate all arithmetic to the pure calculator.
		$result = Package_Shipping_Calculator::calculate(
			$total_qty,
			$subtotal,
			$threshold,
			$cost_per_pkg,
			$items_per_pkg,
			$duties_pct
		);

		$label = $this->get_rate_label( $result );

		$this->add_rate(
			[
				'id'      => $this->get_rate_id(),
				'label'   => $label,
				'cost'    => $result['total_cost'],
				'package' => $package,
			]
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a human-readable rate label that summarises the cost breakdown.
	 *
	 * @param array $result Result array from Package_Shipping_Calculator::calculate().
	 * @return string
	 */
	private function get_rate_label( array $result ): string {
		$base_title = (string) $this->get_option( 'title', __( 'Package Shipping + Duties', 'woo-package-shipping' ) );

		if ( $result['is_free'] ) {
			/* translators: %s: method title */
			return sprintf( __( '%s (Free)', 'woo-package-shipping' ), $base_title );
		}

		return sprintf(
			/* translators: 1: method title 2: number of packages 3: formatted shipping cost 4: formatted duties cost */
			__( '%1$s (%2$d pkg × %3$s + %4$s duties)', 'woo-package-shipping' ),
			$base_title,
			$result['packages'],
			wc_price( $result['shipping_cost'] ),
			wc_price( $result['duties_cost'] )
		);
	}
}
