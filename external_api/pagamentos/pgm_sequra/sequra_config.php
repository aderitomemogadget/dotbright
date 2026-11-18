<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] === __FILE__) exit;

$sequra_secret_files = array();
$sequra_custom_secret_file = getenv('SEQURA_SECRETS_FILE');
if ($sequra_custom_secret_file !== false && $sequra_custom_secret_file !== '') $sequra_secret_files[] = $sequra_custom_secret_file;
$sequra_secret_files[] = dirname(__DIR__, 4) . '/private/sequra_secrets.php';
$sequra_secret_files[] = __DIR__ . '/sequra_secrets.php'; // compatibilidade com instalacoes antigas
foreach ($sequra_secret_files as $sequra_secret_file) {
    if (is_file($sequra_secret_file)) {
        require_once $sequra_secret_file;
        break;
    }
}

function sequra_conf_value($global_key, $env_key, $default = '')
{
    if (isset($GLOBALS[$global_key]) && $GLOBALS[$global_key] !== '') return $GLOBALS[$global_key];
    if (defined($global_key) && constant($global_key) !== '') return constant($global_key);
    $value = getenv($env_key);
    return ($value !== false && $value !== '') ? $value : $default;
}

function sequra_config()
{
    return array(
        'enabled' => (int) sequra_conf_value('SEQURA_ENABLED', 'SEQURA_ENABLED', '0'),
        'sandbox' => (int) sequra_conf_value('SEQURA_SANDBOX', 'SEQURA_SANDBOX', '1'),
        'merchant_id' => trim(sequra_conf_value('SEQURA_MERCHANT_ID', 'SEQURA_MERCHANT_ID', '')),
        'username' => trim(sequra_conf_value('SEQURA_USERNAME', 'SEQURA_USERNAME', '')),
        'password' => (string) sequra_conf_value('SEQURA_PASSWORD', 'SEQURA_PASSWORD', ''),
        'endpoint' => rtrim(trim(sequra_conf_value('SEQURA_ENDPOINT', 'SEQURA_ENDPOINT', '')), '/'),
        'assets_key' => (string) sequra_conf_value('SEQURA_ASSETS_KEY', 'SEQURA_ASSETS_KEY', ''),
        'product' => trim(sequra_conf_value('SEQURA_PRODUCT', 'SEQURA_PRODUCT', 'pp3')),
        'culture' => trim(sequra_conf_value('SEQURA_CULTURE', 'SEQURA_CULTURE', 'pt-PT')),
        'order_prefix' => trim(sequra_conf_value('SEQURA_ORDER_PREFIX', 'SEQURA_ORDER_PREFIX', 'DOTBRIGHT')),
        'token_secret' => (string) sequra_conf_value('SEQURA_TOKEN_SECRET', 'SEQURA_TOKEN_SECRET', ''),
        'signature_secret' => (string) sequra_conf_value('SEQURA_SIGNATURE_SECRET', 'SEQURA_SIGNATURE_SECRET', ''),
        'paid_state' => (string) sequra_conf_value('SEQURA_PAID_STATE', 'SEQURA_PAID_STATE', '13'),
        'pending_state' => (string) sequra_conf_value('SEQURA_PENDING_STATE', 'SEQURA_PENDING_STATE', '5'),
        'failed_state' => (string) sequra_conf_value('SEQURA_FAILED_STATE', 'SEQURA_FAILED_STATE', '14'),
        'cancelled_state' => (string) sequra_conf_value('SEQURA_CANCELLED_STATE', 'SEQURA_CANCELLED_STATE', '15'),
        'currency' => 'EUR'
    );
}

function sequra_config_errors($require_enabled = true)
{
    $config = sequra_config();
    $errors = array();
    if ($require_enabled && !$config['enabled']) $errors[] = 'disabled';
    foreach (array('merchant_id', 'username', 'password', 'endpoint', 'assets_key', 'token_secret') as $key) {
        if ($config[$key] === '') $errors[] = 'missing_' . $key;
    }
    if ($config['endpoint'] !== '' && stripos($config['endpoint'], 'https://') !== 0) $errors[] = 'invalid_endpoint';
    return $errors;
}

function sequra_is_ready()
{
    return count(sequra_config_errors(true)) === 0;
}

function sequra_current_client_id()
{
    if (!function_exists('cliente_logado')) return 0;
    $customer = cliente_logado();
    return is_array($customer) && isset($customer['cli_id']) ? (int) $customer['cli_id'] : 0;
}

function sequra_payment_method($method_id = 0)
{
    if (!defined('MP_TP_SEQURA')) return false;
    $db_link = db_opendb_conection($GLOBALS['DB_host'], $GLOBALS['DB_user'], $GLOBALS['DB_pass']);
    $rs = new DataObj;
    $sql = "SELECT * FROM metodos_pagamento WHERE Tipo='" . (int) MP_TP_SEQURA . "'";
    $sql .= (int) $method_id > 0 ? " AND ID='" . (int) $method_id . "'" : " AND Visivel='1'";
    $rs->db_open($db_link, $GLOBALS['DB_name'], $sql . ' ORDER BY ID ASC LIMIT 1');
    $method = $rs->EOF() ? false : $rs->db_data;
    $rs->db_close();
    return $method;
}

function sequra_method_access_allowed($method)
{
    if (!defined('MP_TP_SEQURA') || !is_array($method) || !isset($method['Tipo'], $method['Visivel']) ||
        (int) $method['Tipo'] !== (int) MP_TP_SEQURA || (int) $method['Visivel'] !== 1 ||
        !isset($method['Sequra_Modo_Teste'], $method['Sequra_Cliente_Teste'])) return false;

    $client_id = sequra_current_client_id();
    if ((int) $method['Sequra_Modo_Teste'] === 1) {
        return $client_id > 0 && (int) $method['Sequra_Cliente_Teste'] > 0 &&
            $client_id === (int) $method['Sequra_Cliente_Teste'];
    }
    if ((int) $method['Sequra_Modo_Teste'] !== 0) return false;
    // Keep the existing sandbox protection when no specific test customer is configured.
    if (!sequra_config()['sandbox']) return true;
    if ($client_id <= 0 || !function_exists('cliente_perfil')) return false;
    $profile = cliente_perfil($client_id);
    return is_array($profile) && isset($profile['Testes']) && (int) $profile['Testes'] === 1;
}

function sequra_sandbox_access_allowed()
{
    return sequra_method_access_allowed(sequra_payment_method());
}

function sequra_widget_config()
{
    $config = sequra_config();
    if (!$config['enabled'] || !sequra_sandbox_access_allowed()) return array();
    foreach (array('merchant_id', 'assets_key', 'product') as $key) {
        if ($config[$key] === '') return array();
    }
    // Only public promotional settings may be exposed to the browser.
    return array('merchant'=>$config['merchant_id'], 'assetKey'=>$config['assets_key'],
        'products'=>array($config['product']),
        'scriptUri'=>($config['sandbox'] ? 'https://sandbox.sequracdn.com' : 'https://live.sequracdn.com') . '/assets/sequra-checkout.min.js',
        'decimalSeparator'=>',', 'thousandSeparator'=>'.', 'locale'=>$config['culture'], 'currency'=>$config['currency']);
}
