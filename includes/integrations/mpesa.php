<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_nopriv_construction_mgmt_mpesa_webhook', 'construction_mgmt_mpesa_webhook_handler');
add_action('admin_post_construction_mgmt_mpesa_webhook', 'construction_mgmt_mpesa_webhook_handler');

function construction_mgmt_mpesa_is_enabled() {
    return (int) get_option('construction_mgmt_mpesa_enabled', 0) === 1;
}

function construction_mgmt_mpesa_api_base_url() {
    $environment = (string) get_option('construction_mgmt_mpesa_environment', 'sandbox');
    return $environment === 'live' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
}

function construction_mgmt_mpesa_normalize_phone($phone) {
    $digits = preg_replace('/\D+/', '', (string) $phone);

    if (strpos($digits, '254') === 0) {
        return $digits;
    }

    if (strpos($digits, '0') === 0) {
        return '254' . substr($digits, 1);
    }

    return $digits;
}

function construction_mgmt_mpesa_get_access_token() {
    $consumer_key = trim((string) get_option('construction_mgmt_mpesa_consumer_key', ''));
    $consumer_secret = trim((string) get_option('construction_mgmt_mpesa_consumer_secret', ''));

    if ($consumer_key === '' || $consumer_secret === '') {
        return new WP_Error('mpesa_credentials_missing', 'M-Pesa credentials are missing.');
    }

    $token_url = trailingslashit(construction_mgmt_mpesa_api_base_url()) . 'oauth/v1/generate?grant_type=client_credentials';
    $response = wp_remote_get($token_url, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($consumer_key . ':' . $consumer_secret),
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);

    if ($code >= 300 || empty($body['access_token'])) {
        return new WP_Error('mpesa_token_failed', 'Unable to obtain M-Pesa access token.');
    }

    return (string) $body['access_token'];
}

function construction_mgmt_mpesa_extract_metadata(array $callback_data) {
    $metadata = [];
    $items = $callback_data['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];

    if (!is_array($items)) {
        return $metadata;
    }

    foreach ($items as $item) {
        if (empty($item['Name'])) {
            continue;
        }

        $metadata[(string) $item['Name']] = $item['Value'] ?? null;
    }

    return $metadata;
}

function construction_mgmt_mpesa_log_transaction(array $row) {
    global $wpdb;

    $table = construction_mgmt_get_table_name('mpesa_transactions');
    if (empty($table)) {
        return;
    }

    $defaults = [
        'merchant_request_id' => '',
        'checkout_request_id' => '',
        'result_code' => null,
        'result_desc' => '',
        'amount' => 0,
        'mpesa_receipt_number' => '',
        'phone_number' => '',
        'transaction_date' => '',
        'payment_status' => 'pending',
        'raw_payload' => '',
        'created_at' => current_time('mysql'),
    ];

    $payload = array_merge($defaults, $row);

    $wpdb->insert($table, [
        'merchant_request_id' => sanitize_text_field((string) $payload['merchant_request_id']),
        'checkout_request_id' => sanitize_text_field((string) $payload['checkout_request_id']),
        'result_code' => is_null($payload['result_code']) ? null : (int) $payload['result_code'],
        'result_desc' => sanitize_text_field((string) $payload['result_desc']),
        'amount' => (float) $payload['amount'],
        'mpesa_receipt_number' => sanitize_text_field((string) $payload['mpesa_receipt_number']),
        'phone_number' => sanitize_text_field((string) $payload['phone_number']),
        'transaction_date' => sanitize_text_field((string) $payload['transaction_date']),
        'payment_status' => sanitize_text_field((string) $payload['payment_status']),
        'raw_payload' => wp_json_encode($payload['raw_payload']),
        'created_at' => $payload['created_at'],
    ]);
}

function construction_mgmt_mpesa_update_payment_from_webhook($checkout_request_id, $merchant_request_id, $result_code, $amount, $transaction_date) {
    global $wpdb;

    $payments_table = construction_mgmt_get_table_name('payments');
    if (empty($payments_table)) {
        return;
    }

    $status = ((int) $result_code === 0) ? 'processed' : 'failed';
    $payment_date = !empty($transaction_date) && strlen((string) $transaction_date) >= 8
        ? substr((string) $transaction_date, 0, 4) . '-' . substr((string) $transaction_date, 4, 2) . '-' . substr((string) $transaction_date, 6, 2)
        : current_time('Y-m-d');

    $where_sql = "(reference_number = %s OR reference_number = %s)";
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$payments_table}
             SET status = %s,
                 payment_date = %s,
                 amount = CASE WHEN amount IS NULL OR amount = 0 THEN %f ELSE amount END
             WHERE {$where_sql}",
            $status,
            $payment_date,
            (float) $amount,
            (string) $checkout_request_id,
            (string) $merchant_request_id
        )
    );
}

function construction_mgmt_mpesa_stk_push($phone_number, $amount, $account_reference, $transaction_desc, $project_id = 0, $invoice_id = 0) {
    if (!construction_mgmt_mpesa_is_enabled()) {
        return new WP_Error('mpesa_disabled', 'M-Pesa integration is disabled.');
    }

    $shortcode = trim((string) get_option('construction_mgmt_mpesa_shortcode', ''));
    $passkey = trim((string) get_option('construction_mgmt_mpesa_passkey', ''));

    if ($shortcode === '' || $passkey === '') {
        return new WP_Error('mpesa_shortcode_missing', 'M-Pesa shortcode or passkey is missing.');
    }

    $token = construction_mgmt_mpesa_get_access_token();
    if (is_wp_error($token)) {
        return $token;
    }

    $timestamp = gmdate('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);
    $stk_url = trailingslashit(construction_mgmt_mpesa_api_base_url()) . 'mpesa/stkpush/v1/processrequest';
    $callback_url = admin_url('admin-post.php?action=construction_mgmt_mpesa_webhook');

    $payload = [
        'BusinessShortCode' => $shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => (int) round((float) $amount),
        'PartyA' => construction_mgmt_mpesa_normalize_phone($phone_number),
        'PartyB' => $shortcode,
        'PhoneNumber' => construction_mgmt_mpesa_normalize_phone($phone_number),
        'CallBackURL' => $callback_url,
        'AccountReference' => sanitize_text_field((string) $account_reference),
        'TransactionDesc' => sanitize_text_field((string) $transaction_desc),
    ];

    $response = wp_remote_post($stk_url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);

    if ($code >= 300 || empty($body['CheckoutRequestID'])) {
        return new WP_Error('mpesa_stk_failed', 'M-Pesa STK push failed.');
    }

    construction_mgmt_mpesa_log_transaction([
        'merchant_request_id' => $body['MerchantRequestID'] ?? '',
        'checkout_request_id' => $body['CheckoutRequestID'] ?? '',
        'result_code' => $body['ResponseCode'] ?? null,
        'result_desc' => $body['ResponseDescription'] ?? '',
        'amount' => (float) $amount,
        'phone_number' => construction_mgmt_mpesa_normalize_phone($phone_number),
        'payment_status' => 'pending',
        'raw_payload' => $body,
    ]);

    $payments_table = construction_mgmt_get_table_name('payments');
    if (!empty($payments_table)) {
        global $wpdb;
        $wpdb->insert($payments_table, [
            'project_id' => (int) $project_id > 0 ? (int) $project_id : null,
            'invoice_id' => (int) $invoice_id > 0 ? (int) $invoice_id : null,
            'payment_type' => 'client_receipt',
            'amount' => (float) $amount,
            'payment_date' => current_time('Y-m-d'),
            'status' => 'pending',
            'reference_number' => sanitize_text_field((string) ($body['CheckoutRequestID'] ?? '')),
            'notes' => 'M-Pesa STK push initiated',
            'created_at' => current_time('mysql'),
        ]);
    }

    return $body;
}

function construction_mgmt_mpesa_webhook_handler() {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string) $raw, true);

    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $callback = $payload['Body']['stkCallback'] ?? [];
    $metadata = construction_mgmt_mpesa_extract_metadata($payload);

    $merchant_request_id = sanitize_text_field((string) ($callback['MerchantRequestID'] ?? ''));
    $checkout_request_id = sanitize_text_field((string) ($callback['CheckoutRequestID'] ?? ''));
    $result_code = isset($callback['ResultCode']) ? (int) $callback['ResultCode'] : null;
    $result_desc = sanitize_text_field((string) ($callback['ResultDesc'] ?? ''));

    $amount = isset($metadata['Amount']) ? (float) $metadata['Amount'] : 0;
    $receipt_number = sanitize_text_field((string) ($metadata['MpesaReceiptNumber'] ?? ''));
    $phone_number = sanitize_text_field((string) ($metadata['PhoneNumber'] ?? ''));
    $transaction_date = sanitize_text_field((string) ($metadata['TransactionDate'] ?? ''));

    $payment_status = ((int) $result_code === 0) ? 'success' : 'failed';

    construction_mgmt_mpesa_log_transaction([
        'merchant_request_id' => $merchant_request_id,
        'checkout_request_id' => $checkout_request_id,
        'result_code' => $result_code,
        'result_desc' => $result_desc,
        'amount' => $amount,
        'mpesa_receipt_number' => $receipt_number,
        'phone_number' => $phone_number,
        'transaction_date' => $transaction_date,
        'payment_status' => $payment_status,
        'raw_payload' => $payload,
    ]);

    construction_mgmt_mpesa_update_payment_from_webhook(
        $checkout_request_id,
        $merchant_request_id,
        $result_code,
        $amount,
        $transaction_date
    );

    status_header(200);
    header('Content-Type: application/json');
    echo wp_json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}
