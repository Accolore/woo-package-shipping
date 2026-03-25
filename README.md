# Woo Package Shipping

A WooCommerce shipping method that calculates costs based on the number of packages required and applies a configurable duties percentage on the cart subtotal. The method is restricted to a single, admin-selected destination country and supports a free-shipping threshold above which both shipping and duties are waived.

---

## Requirements

| Dependency      | Minimum version |
|-----------------|-----------------|
| PHP             | 7.4             |
| WordPress       | 5.9             |
| WooCommerce     | 7.0             |

---

## Installation

1. Copy the `woo-package-shipping` folder into `wp-content/plugins/`.
2. In the WordPress admin go to **Plugins** and activate **Woo Package Shipping**.
3. Navigate to **WooCommerce → Settings → Shipping** and create or open a shipping zone.
4. Click **Add shipping method**, select **Package Shipping with Duties** and click **Add shipping method**.
5. Click the method name to open its settings and configure the fields described below.

---

## Configuration

All settings are per shipping-zone instance and are available in the method's settings panel.

| Field | Default | Description |
|-------|---------|-------------|
| **Method title** | Package Shipping + Duties | Label displayed to the customer at checkout. |
| **Destination country** | *(none)* | The method is shown **only** when the shipping address matches this country. Must be selected; leaving it empty disables the method. |
| **Free shipping threshold (€)** | 1500.00 | Orders whose subtotal (after discounts, before shipping) meets or exceeds this amount qualify for free shipping. Both the per-package cost and duties are waived. |
| **Cost per package (€)** | 170.00 | Fixed shipping cost charged per package dispatched. |
| **Items per package** | 20 | Maximum number of units that fit in one package. The 21st item starts a new package. Must be ≥ 1. |
| **Duties percentage (%)** | 15 | Percentage of the cart subtotal added as duties. Applied only when free shipping does not apply. Must be between 0 and 100. |

---

## Pricing logic

### When the order is below the free-shipping threshold

```
packages      = ceil( total_quantity / items_per_package )
shipping_cost = packages × cost_per_package
duties_cost   = subtotal × ( duties_percent / 100 )
total         = shipping_cost + duties_cost
```

**Examples** (defaults: 170 €/pkg, 20 items/pkg, 15% duties, threshold 1500 €):

| Cart qty | Subtotal | Packages | Shipping | Duties (15%) | Total |
|----------|----------|----------|----------|--------------|-------|
| 1        | 200 €    | 1        | 170 €    | 30 €         | 200 € |
| 20       | 1 000 €  | 1        | 170 €    | 150 €        | 320 € |
| 21       | 1 000 €  | 2        | 340 €    | 150 €        | 490 € |
| 40       | 1 200 €  | 2        | 340 €    | 180 €        | 520 € |
| 41       | 1 200 €  | 3        | 510 €    | 180 €        | 690 € |

### When the order meets or exceeds the free-shipping threshold

```
shipping_cost = 0
duties_cost   = 0
total         = 0
```

The checkout label reads: `<Method title> (Free)`.

---

## Checkout rate label

When shipping is not free, the label shown at checkout summarises the breakdown:

```
Package Shipping + Duties (2 pkg × €340.00 + €150.00 duties)
```

This lets the customer see exactly how many packages are being dispatched and what the duties component is.

---

## File structure

```
woo-package-shipping/
├── woo-package-shipping.php                       ← Plugin entry point
├── includes/
│   ├── class-package-shipping-calculator.php      ← Pure calculation logic (no WP dependencies)
│   └── class-wc-shipping-package-duties.php       ← WC_Shipping_Method implementation
├── tests/
│   └── test-calculator.php                        ← Standalone CLI test suite
├── plan.md                                        ← Design plan and architecture notes
└── docs.md                                        ← Original requirements
```

### `woo-package-shipping.php`

Plugin entry point. Declares HPOS compatibility, checks that WooCommerce is active, loads the two class files, and registers the shipping method via the `woocommerce_shipping_methods` filter.

### `includes/class-package-shipping-calculator.php`

Stateless utility class with no WordPress or WooCommerce dependencies. All arithmetic lives here to keep it easily testable in isolation.

| Method | Description |
|--------|-------------|
| `should_free_shipping( $subtotal, $threshold )` | Returns `true` when `$subtotal >= $threshold`. |
| `count_packages( $total_qty, $items_per_package )` | Returns `ceil( $total_qty / $items_per_package )`. Returns `0` if either argument is ≤ 0. |
| `compute_shipping_cost( $packages, $cost_per_package )` | Returns `$packages × $cost_per_package`. Returns `0.0` if either argument is ≤ 0. |
| `compute_duties( $subtotal, $percent, $shipping_is_free )` | Returns `$subtotal × ($percent / 100)`. Returns `0.0` when `$shipping_is_free` is `true`. |
| `calculate( $total_qty, $subtotal, $threshold, $cost_per_package, $items_per_package, $duties_percent )` | Aggregates all of the above and returns an array with keys `is_free`, `packages`, `shipping_cost`, `duties_cost`, `total_cost`. |

### `includes/class-wc-shipping-package-duties.php`

Extends `WC_Shipping_Method`. Responsibilities:

- **`init_form_fields()`** — Defines the admin settings fields listed in the Configuration section.
- **`process_admin_options()`** — Overrides the parent to clamp values out of range and reject unknown country codes after saving.
- **`is_available( $package )`** — Returns `true` only when the method is enabled, a country is configured, and the package destination matches.
- **`calculate_shipping( $package )`** — Reads settings, sums cart quantities from `$package['contents']`, reads the subtotal from `$package['contents_cost']`, delegates arithmetic to `Package_Shipping_Calculator::calculate()`, then calls `add_rate()`.

---

## Running the tests

The test suite is a self-contained CLI script with no external dependencies:

```bash
G:\xampp\php\php.exe tests/test-calculator.php
```

Expected output (31 assertions):

```
[PASS] Free shipping at exact threshold
[PASS] Free shipping above threshold
...
Results: 31 passed, 0 failed.
```

---

## Security notes

- All option values are read through WooCommerce's own `get_option()` / `update_option()` API; no raw `$_POST` access occurs in the shipping logic.
- Output is escaped with `esc_html()` where applicable.
- The destination country is validated against WooCommerce's full country list before being persisted.
- No custom AJAX endpoints or database tables are introduced.

---

## License

GPL-2.0-or-later — see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
