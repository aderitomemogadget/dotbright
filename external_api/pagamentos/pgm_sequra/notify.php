<?php
// Temporary IPN diagnostics: never log payloads, URLs, credentials or exception messages.
$sequra_ipn_diag_id = uniqid('sqipn_', true);
$sequra_ipn_diag_stage = 'bootstrap';
$sequra_ipn_diag_order = 0;
$sequra_ipn_diag_reserve = str_repeat(' ', 65536);
$sequra_ipn_diag_mark = function ($stage) use (&$sequra_ipn_diag_stage, &$sequra_ipn_diag_order, $sequra_ipn_diag_id) {
    $sequra_ipn_diag_stage = $stage;
    error_log('[SEQURA_IPN_DIAG] ' . json_encode(array('request'=>$sequra_ipn_diag_id, 'order'=>$sequra_ipn_diag_order, 'stage'=>$stage)));
};
register_shutdown_function(function () use (&$sequra_ipn_diag_reserve, &$sequra_ipn_diag_stage, &$sequra_ipn_diag_order, $sequra_ipn_diag_id) {
    $sequra_ipn_diag_reserve = null;
    $error = error_get_last();
    $entry = array('request'=>$sequra_ipn_diag_id, 'order'=>$sequra_ipn_diag_order, 'stage'=>$sequra_ipn_diag_stage, 'event'=>'shutdown', 'http'=>(int) http_response_code());
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR), true)) {
        $entry['event'] = 'fatal';
        $entry['error_type'] = (int) $error['type'];
        $entry['file'] = basename($error['file']);
        $entry['line'] = (int) $error['line'];
        // Fixed categories only; the original error message may contain sensitive data.
        foreach (array('Allowed memory size'=>'memory_limit', 'Maximum execution time'=>'time_limit', 'Uncaught TypeError'=>'type_error', 'Uncaught Error'=>'uncaught_error', 'Uncaught Exception'=>'uncaught_exception', 'Call to undefined function'=>'undefined_function') as $pattern=>$category) {
            if (strpos($error['message'], $pattern) !== false) { $entry['category'] = $category; break; }
        }
    }
    error_log('[SEQURA_IPN_DIAG] ' . json_encode($entry));
});
require_once __DIR__ . '/../../../core/core_files.php';
require_once __DIR__ . '/sequra_config.php';
require_once __DIR__ . '/sequra_lib.php';
require_once __DIR__ . '/sequra_ipn.php';
$sequra_ipn_diag_mark('bootstrap_complete');
header('Content-Type: application/json; charset=utf-8');
if (!sequra_is_ready()) { http_response_code(503); echo json_encode(array('ok'=>false)); exit; }
$raw = file_get_contents('php://input');
$data = sequra_ipn_data($raw);
$enc_id = sequra_ipn_enc_id($data);
$sequra_ipn_diag_order = (int) $enc_id;
$sequra_ipn_diag_mark('received');
$token = isset($data['token']) ? (string) $data['token'] : (isset($data['m_token']) ? (string) $data['m_token'] : '');
if ($enc_id <= 0 || !sequra_valid_order_token($enc_id, $token) || !sequra_verify_signature($raw)) {
    sequra_log($enc_id, '', $data, '', 'IPN_FORBIDDEN', 'Token ou assinatura inválidos.');
    http_response_code(403); echo json_encode(array('ok'=>false)); exit;
}
$provider_reference = isset($data['order_ref']) ? trim($data['order_ref']) : '';
$status = sequra_ipn_state($data);
$config = sequra_config();
$sequra_ipn_diag_mark('authenticated');
if (in_array($status, array('approved','accepted','authorized','confirmed','paid','success','ok'), true)) {
    $target_state = 'confirmed'; $paid = true; $local_state = $config['paid_state'];
} elseif (in_array($status, array('needs_review','review','on_hold'), true)) {
    $target_state = 'on_hold'; $paid = false; $local_state = $config['pending_state'];
} elseif (in_array($status, array('rejected','denied','cancelled','canceled','failed','error'), true)) {
    $sequra_ipn_diag_mark('negative_local_update_start');
    $update = sequra_update_local_order($enc_id, false, $status === 'cancelled' || $status === 'canceled' ? $config['cancelled_state'] : $config['failed_state'], $status);
    $sequra_ipn_diag_mark($update['ok'] ? 'negative_local_update_ok' : 'negative_local_update_failed');
    sequra_log($enc_id, sequra_order_reference($enc_id), $data, '', 'IPN_' . strtoupper($status), $update['ok'] ? '' : 'Falha ao atualizar encomenda.');
    echo json_encode(array('ok'=>(bool) $update['ok'])); exit;
} else {
    sequra_log($enc_id, sequra_order_reference($enc_id), $data, '', 'IPN_UNKNOWN', $status);
    http_response_code(501); echo json_encode(array('ok'=>false, 'error'=>'unknown_state')); exit;
}
$sequra_ipn_diag_mark('provider_confirmation_start');
$provider = sequra_confirm_provider_order($enc_id, $provider_reference, $target_state);
$sequra_ipn_diag_mark('provider_http_' . (int) $provider['http_code']);
if ($provider['http_code'] < 200 || $provider['http_code'] > 299) {
    sequra_log($enc_id, sequra_order_reference($enc_id), $provider['request'], $provider['response_raw'], 'IPN_PROVIDER_ERROR', 'HTTP ' . $provider['http_code'] . ' ' . $provider['curl_error']);
    http_response_code($provider['http_code'] === 409 ? 409 : 500); echo json_encode(array('ok'=>false)); exit;
}
$sequra_ipn_diag_mark('local_update_start');
$local = sequra_update_local_order($enc_id, $paid, $local_state, $status);
$sequra_ipn_diag_mark($local['ok'] ? 'local_update_ok' : 'local_update_failed');
if (!$local['ok']) { http_response_code(500); echo json_encode(array('ok'=>false)); exit; }
if ($local['became_paid'] && function_exists('enviarEmailCliente')) {
    $sequra_ipn_diag_mark('email_start');
    enviarEmailCliente($enc_id);
    $sequra_ipn_diag_mark('email_returned');
}
$sequra_ipn_diag_mark('final_log_start');
sequra_log($enc_id, sequra_order_reference($enc_id), $data, '', 'IPN_' . strtoupper($target_state), '');
$sequra_ipn_diag_mark('response_success');
echo json_encode(array('ok'=>true));
