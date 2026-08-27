<?php
/**
 * Tests for Pesepay sandbox / test-mode support.
 *
 * Run with:   php tests/test-sandbox-mode.php
 *
 * Tests exercise PesePay_Helper::get_environment_config() directly via the
 * optional $gateway parameter, so no WooCommerce runtime is required.
 *
 * @since 1.3.0
 */

// ── WordPress / WooCommerce stubs needed by PesePay_Helper ─────────────────

if (!defined('PESEPAY_SLUG')) {
    define('PESEPAY_SLUG', 'pesepay');
}

if (!function_exists('get_transient')) {
    function get_transient($key) { return false; }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) { return $value; }
}
if (!function_exists('wp_list_pluck')) {
    function wp_list_pluck($list, $field) {
        return array_map(function ($item) use ($field) { return $item[$field]; }, $list);
    }
}

// ── Load the class under test ───────────────────────────────────────────────

require_once __DIR__ . '/../includes/class-pesepay-functions.php';

// ── Mock gateway ────────────────────────────────────────────────────────────

class MockPeseGateway {
    private $options;

    public function __construct(array $options = array()) {
        $this->options = array_merge(array(
            'test_mode'            => 'no',
            'integration_key'      => '',
            'encryption_key'       => '',
            'test_integration_key' => '',
            'test_encryption_key'  => '',
            'debug'                => 'no',
        ), $options);
    }

    public function get_option($key, $default = '') {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }
}

// ── Minimal test runner ─────────────────────────────────────────────────────

class TestRunner {
    private $passed = 0;
    private $failed = 0;
    private $failures = array();

    public function assert($desc, $cond) {
        if ($cond) {
            $this->passed++;
            echo "  PASS  {$desc}\n";
        } else {
            $this->failed++;
            $this->failures[] = $desc;
            echo "  FAIL  {$desc}\n";
        }
    }

    public function assertEquals($desc, $expected, $actual) {
        if ($expected === $actual) {
            $this->assert($desc, true);
        } else {
            $this->failed++;
            $this->failures[] = $desc;
            echo "  FAIL  {$desc}\n";
            echo "        expected: " . var_export($expected, true) . "\n";
            echo "        got:      " . var_export($actual, true) . "\n";
        }
    }

    public function report() {
        echo "\n" . str_repeat('─', 60) . "\n";
        echo "Results  passed={$this->passed}  failed={$this->failed}\n";
        if ($this->failures) {
            echo "Failed tests:\n";
            foreach ($this->failures as $f) {
                echo "  ✗ {$f}\n";
            }
        }
        echo str_repeat('─', 60) . "\n";
        return $this->failed === 0;
    }
}

$t = new TestRunner();

// Known endpoint values
$LIVE_INITIATE = 'https://api.pesepay.com/api/payments-engine/v1/payments/initiate';
$LIVE_CHECK    = 'https://api.pesepay.com/api/payments-engine/v1/payments/check-payment';
$TEST_INITIATE = 'https://api.test.sandbox.pesepay.com/payments-engine/v1/payments/initiate';
$TEST_CHECK    = 'https://api.test.sandbox.pesepay.com/payments-engine/v1/payments/check-payment';

// ════════════════════════════════════════════════════════════════════════════
echo "\n[1] Test mode disabled by default\n";
{
    $gw = new MockPeseGateway(array(
        'integration_key' => 'live-integ-key',
        'encryption_key'  => 'live-encr-key-32chars-padding!!',
    ));
    $cfg = PesePay_Helper::get_environment_config(null, $gw);
    $t->assertEquals('Default mode is live', 'live', $cfg['mode']);
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[2] Live mode uses existing live credentials and endpoints\n";
{
    $gw = new MockPeseGateway(array(
        'test_mode'       => 'no',
        'integration_key' => 'live-integration-key',
        'encryption_key'  => 'live-encryption-key-32chars!!!',
    ));
    $cfg = PesePay_Helper::get_environment_config(null, $gw);
    $t->assert('Config returned',                     $cfg !== false);
    $t->assertEquals('Mode is live',                  'live', $cfg['mode']);
    $t->assertEquals('Uses live integration key',     'live-integration-key', $cfg['integration_key']);
    $t->assertEquals('Uses live encryption key',      'live-encryption-key-32chars!!!', $cfg['encryption_key']);
    $t->assertEquals('Initiate URL is live endpoint', $LIVE_INITIATE, $cfg['initiate_url']);
    $t->assertEquals('Check URL is live endpoint',    $LIVE_CHECK, $cfg['check_url']);
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[3] Test mode uses only test credentials (not live keys)\n";
{
    $gw = new MockPeseGateway(array(
        'test_mode'            => 'yes',
        'integration_key'      => 'live-integration-key',
        'encryption_key'       => 'live-encryption-key-32chars!!!',
        'test_integration_key' => 'test-integration-key',
        'test_encryption_key'  => 'test-encryption-key-32chars!!!',
    ));
    $cfg = PesePay_Helper::get_environment_config(null, $gw);
    $t->assert('Config returned',                             $cfg !== false);
    $t->assertEquals('Mode is test',                          'test', $cfg['mode']);
    $t->assertEquals('Uses test integration key',             'test-integration-key', $cfg['integration_key']);
    $t->assertEquals('Uses test encryption key',              'test-encryption-key-32chars!!!', $cfg['encryption_key']);
    $t->assert('Integration key is not the live key',         $cfg['integration_key'] !== 'live-integration-key');
    $t->assert('Encryption key is not the live key',          $cfg['encryption_key'] !== 'live-encryption-key-32chars!!!');
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[4] Test initiation uses sandbox initiation endpoint\n";
{
    $gw = new MockPeseGateway(array(
        'test_mode'            => 'yes',
        'test_integration_key' => 'test-integration-key',
        'test_encryption_key'  => 'test-encryption-key-32chars!!!',
    ));
    $cfg = PesePay_Helper::get_environment_config(null, $gw);
    $t->assertEquals('Initiate URL is sandbox endpoint', $TEST_INITIATE, $cfg['initiate_url']);
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[5] Test status checking uses sandbox check-payment endpoint\n";
{
    $gw = new MockPeseGateway(array(
        'test_mode'            => 'yes',
        'test_integration_key' => 'test-integration-key',
        'test_encryption_key'  => 'test-encryption-key-32chars!!!',
    ));
    $cfg = PesePay_Helper::get_environment_config(null, $gw);
    $t->assertEquals('Check URL is sandbox endpoint', $TEST_CHECK, $cfg['check_url']);
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[6] Missing test integration key stops payment safely\n";
{
    $gw = new MockPeseGateway(array(
        'test_mode'            => 'yes',
        'test_integration_key' => '',
        'test_encryption_key'  => 'test-encryption-key-32chars!!!',
    ));
    $cfg = PesePay_Helper::get_environment_config(null, $gw);
    $t->assertEquals('Returns false when test integration key missing', false, $cfg);
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[7] Missing test encryption key stops payment safely\n";
{
    $gw = new MockPeseGateway(array(
        'test_mode'            => 'yes',
        'test_integration_key' => 'test-integration-key',
        'test_encryption_key'  => '',
    ));
    $cfg = PesePay_Helper::get_environment_config(null, $gw);
    $t->assertEquals('Returns false when test encryption key missing', false, $cfg);
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[8] Plugin never falls back to live credentials while test mode is enabled\n";
{
    $gw = new MockPeseGateway(array(
        'test_mode'            => 'yes',
        'integration_key'      => 'live-integration-key',
        'encryption_key'       => 'live-encryption-key-32chars!!!',
        'test_integration_key' => '',
        'test_encryption_key'  => '',
    ));
    $cfg = PesePay_Helper::get_environment_config(null, $gw);
    $t->assertEquals('Returns false, not live fallback, when test keys missing', false, $cfg);
    // Extra: confirm no live key leaked
    if ($cfg !== false) {
        $t->assert('Would not expose live integration key', $cfg['integration_key'] !== 'live-integration-key');
    } else {
        $t->assert('No live credential leaked (config is false)', true);
    }
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[9] Switching back to live mode restores existing live flow\n";
{
    $gw = new MockPeseGateway(array(
        'test_mode'            => 'no',
        'integration_key'      => 'live-integration-key',
        'encryption_key'       => 'live-encryption-key-32chars!!!',
        'test_integration_key' => 'test-integration-key',
        'test_encryption_key'  => 'test-encryption-key-32chars!!!',
    ));
    $cfg = PesePay_Helper::get_environment_config(null, $gw);
    $t->assertEquals('Mode is live after switch back',         'live', $cfg['mode']);
    $t->assertEquals('Uses live integration key after switch', 'live-integration-key', $cfg['integration_key']);
    $t->assertEquals('Uses live encryption key after switch',  'live-encryption-key-32chars!!!', $cfg['encryption_key']);
    $t->assertEquals('Uses live initiate URL after switch',    $LIVE_INITIATE, $cfg['initiate_url']);
    $t->assertEquals('Uses live check URL after switch',       $LIVE_CHECK, $cfg['check_url']);
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[10] Status check uses environment saved when payment was initiated\n";
{
    // Scenario: payment was made in test mode; merchant has since turned test mode off.
    // The status check is forced to 'test' via the mode read from order meta.
    $gw = new MockPeseGateway(array(
        'test_mode'            => 'no',  // now globally live
        'integration_key'      => 'live-integration-key',
        'encryption_key'       => 'live-encryption-key-32chars!!!',
        'test_integration_key' => 'test-integration-key',
        'test_encryption_key'  => 'test-encryption-key-32chars!!!',
    ));
    $cfg = PesePay_Helper::get_environment_config('test', $gw);
    $t->assertEquals('Mode forced to test by saved order meta',     'test', $cfg['mode']);
    $t->assertEquals('Uses sandbox check URL',                      $TEST_CHECK, $cfg['check_url']);
    $t->assertEquals('Uses test integration key',                   'test-integration-key', $cfg['integration_key']);
    $t->assert('Does not use live integration key',                  $cfg['integration_key'] !== 'live-integration-key');
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[11] Explicit live mode uses live config even when test mode is globally on\n";
{
    $gw = new MockPeseGateway(array(
        'test_mode'            => 'yes',  // globally test
        'integration_key'      => 'live-integration-key',
        'encryption_key'       => 'live-encryption-key-32chars!!!',
        'test_integration_key' => 'test-integration-key',
        'test_encryption_key'  => 'test-encryption-key-32chars!!!',
    ));
    $cfg = PesePay_Helper::get_environment_config('live', $gw);
    $t->assertEquals('Mode forced to live by saved order meta',  'live', $cfg['mode']);
    $t->assertEquals('Uses live check URL',                      $LIVE_CHECK, $cfg['check_url']);
    $t->assertEquals('Uses live integration key',                'live-integration-key', $cfg['integration_key']);
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[12] Returns false when no gateway instance available\n";
{
    $cfg = PesePay_Helper::get_environment_config(null, null);
    $t->assertEquals('Returns false without a gateway', false, $cfg);
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[13] Sandbox URL helpers return correct endpoints\n";
{
    $t->assertEquals('Sandbox initiate URL', $TEST_INITIATE, PesePay_Helper::get_sandbox_base_url('v1/payments/initiate'));
    $t->assertEquals('Sandbox check URL',    $TEST_CHECK,    PesePay_Helper::get_sandbox_base_url('v1/payments/check-payment'));
    $t->assertEquals('Sandbox base (empty)', 'https://api.test.sandbox.pesepay.com/payments-engine/', PesePay_Helper::get_sandbox_base_url());
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[14] Live base URL is unchanged\n";
{
    $t->assertEquals('Live initiate URL', $LIVE_INITIATE, PesePay_Helper::get_remote_base_url('v1/payments/initiate'));
    $t->assertEquals('Live check URL',    $LIVE_CHECK,    PesePay_Helper::get_remote_base_url('v1/payments/check-payment'));
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[15] Order meta key prefix is correct\n";
{
    $t->assertEquals('Environment meta key', '_pesepay-environment',     PesePay_Helper::meta_key_prefix('-environment'));
    $t->assertEquals('Reference meta key',   '_pesepay-reference-number', PesePay_Helper::meta_key_prefix('-reference-number'));
}

// ════════════════════════════════════════════════════════════════════════════
echo "\n[16] Live mode config returned even if test keys are also set\n";
{
    $gw = new MockPeseGateway(array(
        'test_mode'            => 'no',
        'integration_key'      => 'live-integration-key',
        'encryption_key'       => 'live-encryption-key-32chars!!!',
        'test_integration_key' => 'test-integration-key',
        'test_encryption_key'  => 'test-encryption-key-32chars!!!',
    ));
    $cfg = PesePay_Helper::get_environment_config(null, $gw);
    $t->assertEquals('Mode is live',              'live', $cfg['mode']);
    $t->assertEquals('Initiate URL is live only', $LIVE_INITIATE, $cfg['initiate_url']);
}

// ════════════════════════════════════════════════════════════════════════════

$ok = $t->report();
exit($ok ? 0 : 1);
