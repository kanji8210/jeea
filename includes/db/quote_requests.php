<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_sanitize_quote_request($data) {
    $quantities = [];

    if (!empty($data['quantities']) && is_array($data['quantities'])) {
        foreach ($data['quantities'] as $quantity) {
            $item = isset($quantity['item']) ? sanitize_text_field($quantity['item']) : '';
            $amount = isset($quantity['amount']) ? sanitize_text_field($quantity['amount']) : '';
            $unit = isset($quantity['unit']) ? sanitize_text_field($quantity['unit']) : '';

            if ($item === '' && $amount === '' && $unit === '') {
                continue;
            }

            $quantities[] = [
                'item' => $item,
                'amount' => $amount,
                'unit' => $unit,
            ];
        }
    }

    return [
        'project_type' => isset($data['projectType']) ? sanitize_text_field($data['projectType']) : '',
        'project_scope' => isset($data['projectScope']) ? sanitize_textarea_field($data['projectScope']) : '',
        'quantities' => $quantities,
        'qualitative_specs' => isset($data['qualitativeSpecs']) ? sanitize_textarea_field($data['qualitativeSpecs']) : '',
        'contact_name' => isset($data['contactName']) ? sanitize_text_field($data['contactName']) : '',
        'contact_email' => isset($data['contactEmail']) ? sanitize_email($data['contactEmail']) : '',
        'contact_phone' => isset($data['contactPhone']) ? sanitize_text_field($data['contactPhone']) : '',
        'contact_company' => isset($data['contactCompany']) ? sanitize_text_field($data['contactCompany']) : '',
    ];
}

function construction_mgmt_create_quote_request($data) {
    global $wpdb;

    $request = construction_mgmt_sanitize_quote_request($data);

    if (
        $request['project_type'] === '' ||
        $request['project_scope'] === '' ||
        empty($request['quantities']) ||
        $request['qualitative_specs'] === '' ||
        $request['contact_name'] === '' ||
        $request['contact_email'] === '' ||
        $request['contact_phone'] === ''
    ) {
        return new WP_Error('invalid_quote_request', 'Missing required quote request fields.');
    }

    if (!is_email($request['contact_email'])) {
        return new WP_Error('invalid_quote_email', 'A valid contact email is required.');
    }

    $table = construction_mgmt_get_table_name('quote_requests');
    $inserted = $wpdb->insert($table, [
        'project_type' => $request['project_type'],
        'project_scope' => $request['project_scope'],
        'quantities_json' => wp_json_encode($request['quantities']),
        'qualitative_specs' => $request['qualitative_specs'],
        'contact_name' => $request['contact_name'],
        'contact_email' => $request['contact_email'],
        'contact_phone' => $request['contact_phone'],
        'contact_company' => $request['contact_company'],
        'status' => 'new',
        'submitted_at' => current_time('mysql'),
    ]);

    if (!$inserted) {
        return new WP_Error('quote_request_insert_failed', 'Unable to store quote request.');
    }

    $request_id = (int) $wpdb->insert_id;
    construction_mgmt_audit_log('submit', 'quote_request', $request_id, null, $request);
    construction_mgmt_notify_quote_request($request_id, $request);

    return $request_id;
}

function construction_mgmt_get_quote_request($request_id) {
    global $wpdb;

    $request_id = (int) $request_id;
    if ($request_id <= 0) {
        return null;
    }

    $table = construction_mgmt_get_table_name('quote_requests');
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $request_id),
        ARRAY_A
    );

    if (empty($row)) {
        return null;
    }

    $row['id'] = (int) $row['id'];
    $row['quantities'] = !empty($row['quantities_json']) ? json_decode($row['quantities_json'], true) : [];

    return $row;
}

function construction_mgmt_notify_quote_request($request_id, $request) {
    $admin_email = get_option('admin_email');
    if (!$admin_email || !is_email($admin_email)) {
        return false;
    }

    $lines = [];
    $lines[] = 'New quote request submitted.';
    $lines[] = '';
    $lines[] = 'Request ID: ' . (int) $request_id;
    $lines[] = 'Project type: ' . $request['project_type'];
    $lines[] = 'Project scope: ' . $request['project_scope'];
    $lines[] = 'Qualitative specs: ' . $request['qualitative_specs'];
    $lines[] = 'Contact name: ' . $request['contact_name'];
    $lines[] = 'Contact email: ' . $request['contact_email'];
    $lines[] = 'Contact phone: ' . $request['contact_phone'];
    $lines[] = 'Company: ' . ($request['contact_company'] !== '' ? $request['contact_company'] : 'n/a');
    $lines[] = '';
    $lines[] = 'Quantities:';

    foreach ($request['quantities'] as $quantity) {
        $lines[] = sprintf(
            '- %s | %s | %s',
            $quantity['item'],
            $quantity['amount'],
            $quantity['unit']
        );
    }

    return wp_mail(
        $admin_email,
        sprintf('New construction quote request #%d', (int) $request_id),
        implode("\n", $lines)
    );
}