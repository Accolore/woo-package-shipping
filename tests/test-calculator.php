<?php
/**
 * Standalone test script for Package_Shipping_Calculator.
 *
 * Run from the plugin root:
 *   G:\xampp\php\php.exe tests/test-calculator.php
 */

define( 'ABSPATH', true );
require __DIR__ . '/../includes/class-package-shipping-calculator.php';

$passed = 0;
$failed = 0;

/**
 * Simple assertion helper.
 *
 * @param bool   $condition
 * @param string $label
 * @param mixed  $expected
 * @param mixed  $actual
 */
function assert_equal( bool $condition, string $label, $expected = null, $actual = null ): void {
	global $passed, $failed;
	if ( $condition ) {
		echo "[PASS] {$label}" . PHP_EOL;
		$passed++;
	} else {
		echo "[FAIL] {$label} — expected: " . var_export( $expected, true ) . ', got: ' . var_export( $actual, true ) . PHP_EOL;
		$failed++;
	}
}

// ---------------------------------------------------------------------------
// Test: should_free_shipping
// ---------------------------------------------------------------------------
assert_equal( Package_Shipping_Calculator::should_free_shipping( 1500.0, 1500.0 ) === true,  'Free shipping at exact threshold', true, true );
assert_equal( Package_Shipping_Calculator::should_free_shipping( 1501.0, 1500.0 ) === true,  'Free shipping above threshold', true, true );
assert_equal( Package_Shipping_Calculator::should_free_shipping( 1499.99, 1500.0 ) === false, 'No free shipping below threshold', false, false );

// ---------------------------------------------------------------------------
// Test: count_packages
// ---------------------------------------------------------------------------
assert_equal( Package_Shipping_Calculator::count_packages( 1,  20 ) === 1, '1 item → 1 package',   1, Package_Shipping_Calculator::count_packages( 1,  20 ) );
assert_equal( Package_Shipping_Calculator::count_packages( 19, 20 ) === 1, '19 items → 1 package', 1, Package_Shipping_Calculator::count_packages( 19, 20 ) );
assert_equal( Package_Shipping_Calculator::count_packages( 20, 20 ) === 1, '20 items → 1 package', 1, Package_Shipping_Calculator::count_packages( 20, 20 ) );
assert_equal( Package_Shipping_Calculator::count_packages( 21, 20 ) === 2, '21 items → 2 packages',2, Package_Shipping_Calculator::count_packages( 21, 20 ) );
assert_equal( Package_Shipping_Calculator::count_packages( 40, 20 ) === 2, '40 items → 2 packages',2, Package_Shipping_Calculator::count_packages( 40, 20 ) );
assert_equal( Package_Shipping_Calculator::count_packages( 41, 20 ) === 3, '41 items → 3 packages',3, Package_Shipping_Calculator::count_packages( 41, 20 ) );
assert_equal( Package_Shipping_Calculator::count_packages( 0,  20 ) === 0, '0 items → 0 packages', 0, Package_Shipping_Calculator::count_packages( 0,  20 ) );

// ---------------------------------------------------------------------------
// Test: compute_shipping_cost
// ---------------------------------------------------------------------------
assert_equal( Package_Shipping_Calculator::compute_shipping_cost( 1, 170.0 ) === 170.0, '1 package × 170 = 170', 170.0, Package_Shipping_Calculator::compute_shipping_cost( 1, 170.0 ) );
assert_equal( Package_Shipping_Calculator::compute_shipping_cost( 2, 170.0 ) === 340.0, '2 packages × 170 = 340', 340.0, Package_Shipping_Calculator::compute_shipping_cost( 2, 170.0 ) );
assert_equal( Package_Shipping_Calculator::compute_shipping_cost( 0, 170.0 ) === 0.0,   '0 packages = 0 cost',    0.0,   Package_Shipping_Calculator::compute_shipping_cost( 0, 170.0 ) );

// ---------------------------------------------------------------------------
// Test: compute_duties
// ---------------------------------------------------------------------------
assert_equal( Package_Shipping_Calculator::compute_duties( 1000.0, 15.0, false ) === 150.0, 'Duties 15% on 1000 = 150', 150.0, Package_Shipping_Calculator::compute_duties( 1000.0, 15.0, false ) );
assert_equal( Package_Shipping_Calculator::compute_duties( 1000.0, 15.0, true )  === 0.0,   'Duties = 0 when free shipping', 0.0, Package_Shipping_Calculator::compute_duties( 1000.0, 15.0, true ) );
assert_equal( Package_Shipping_Calculator::compute_duties( 0.0,    15.0, false ) === 0.0,   'Duties = 0 on zero subtotal', 0.0, Package_Shipping_Calculator::compute_duties( 0.0, 15.0, false ) );

// ---------------------------------------------------------------------------
// Test: full calculate() — typical scenarios
// ---------------------------------------------------------------------------

// Scenario A: 20 items, 1000 subtotal (below 1500 threshold)
$r = Package_Shipping_Calculator::calculate( 20, 1000.0, 1500.0, 170.0, 20, 15.0 );
assert_equal( $r['is_free']       === false, 'Scenario A: not free',           false, $r['is_free'] );
assert_equal( $r['packages']      === 1,     'Scenario A: 1 package',          1,     $r['packages'] );
assert_equal( $r['shipping_cost'] === 170.0, 'Scenario A: shipping = 170',     170.0, $r['shipping_cost'] );
assert_equal( $r['duties_cost']   === 150.0, 'Scenario A: duties = 150',       150.0, $r['duties_cost'] );
assert_equal( $r['total_cost']    === 320.0, 'Scenario A: total = 320',        320.0, $r['total_cost'] );

// Scenario B: 21 items, 1000 subtotal (below threshold → 2 packages)
$r = Package_Shipping_Calculator::calculate( 21, 1000.0, 1500.0, 170.0, 20, 15.0 );
assert_equal( $r['packages']      === 2,     'Scenario B: 2 packages',   2,     $r['packages'] );
assert_equal( $r['shipping_cost'] === 340.0, 'Scenario B: shipping=340', 340.0, $r['shipping_cost'] );
assert_equal( $r['duties_cost']   === 150.0, 'Scenario B: duties=150',   150.0, $r['duties_cost'] );
assert_equal( $r['total_cost']    === 490.0, 'Scenario B: total=490',    490.0, $r['total_cost'] );

// Scenario C: subtotal at threshold (free — no cost, no duties)
$r = Package_Shipping_Calculator::calculate( 50, 1500.0, 1500.0, 170.0, 20, 15.0 );
assert_equal( $r['is_free']     === true, 'Scenario C: free at threshold', true, $r['is_free'] );
assert_equal( $r['packages']    === 0,    'Scenario C: 0 packages',        0,    $r['packages'] );
assert_equal( $r['total_cost']  === 0.0,  'Scenario C: total = 0',         0.0,  $r['total_cost'] );
assert_equal( $r['duties_cost'] === 0.0,  'Scenario C: duties = 0',        0.0,  $r['duties_cost'] );

// Scenario D: subtotal above threshold
$r = Package_Shipping_Calculator::calculate( 100, 2000.0, 1500.0, 170.0, 20, 15.0 );
assert_equal( $r['is_free']    === true, 'Scenario D: free above threshold', true, $r['is_free'] );
assert_equal( $r['total_cost'] === 0.0,  'Scenario D: total = 0',           0.0,  $r['total_cost'] );

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo PHP_EOL . "Results: {$passed} passed, {$failed} failed." . PHP_EOL;
exit( $failed > 0 ? 1 : 0 );
