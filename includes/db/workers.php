<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_sanitize_worker_data($data) {
    return [
        'full_name' => isset($data['fullName']) ? sanitize_text_field($data['fullName']) : '',
        'national_id' => isset($data['nationalId']) ? sanitize_text_field($data['nationalId']) : '',
        'nssf_number' => isset($data['nssfNumber']) ? sanitize_text_field($data['nssfNumber']) : '',
        'nhif_number' => isset($data['nhifNumber']) ? sanitize_text_field($data['nhifNumber']) : '',
        'skill_type' => isset($data['skillType']) ? sanitize_text_field($data['skillType']) : '',
        'daily_rate' => isset($data['dailyRate']) ? (float) $data['dailyRate'] : 0.0,
        'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
        'is_active' => !empty($data['isActive']) ? 1 : 0,
    ];
}

function construction_mgmt_get_worker($worker_id) {
    global $wpdb;

    $worker_id = (int) $worker_id;
    if ($worker_id <= 0) {
        return null;
    }

    $table = construction_mgmt_get_table_name('workers');
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $worker_id),
        ARRAY_A
    );

    if (empty($row)) {
        return null;
    }

    $row['id'] = (int) $row['id'];
    $row['daily_rate'] = (float) $row['daily_rate'];
    $row['is_active'] = !empty($row['is_active']);

    return $row;
}

function construction_mgmt_get_workers() {
    global $wpdb;

    $table = construction_mgmt_get_table_name('workers');
    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY updated_at DESC, full_name ASC", ARRAY_A);

    if (!$rows) {
        return [];
    }

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['daily_rate'] = (float) $row['daily_rate'];
        $row['is_active'] = !empty($row['is_active']);
    }
    unset($row);

    return $rows;
}

function construction_mgmt_create_worker($data) {
    global $wpdb;

    $worker = construction_mgmt_sanitize_worker_data($data);
    if ($worker['full_name'] === '') {
        return new WP_Error('invalid_worker_name', 'Worker full name is required.');
    }

    $table = construction_mgmt_get_table_name('workers');
    $inserted = $wpdb->insert($table, $worker);
    if (!$inserted) {
        return new WP_Error('worker_create_failed', 'Unable to create worker.');
    }

    $worker_id = (int) $wpdb->insert_id;
    construction_mgmt_audit_log('create', 'worker', $worker_id, null, $worker);

    return construction_mgmt_get_worker($worker_id);
}

function construction_mgmt_update_worker($worker_id, $data) {
    global $wpdb;

    $worker_id = (int) $worker_id;
    $existing = construction_mgmt_get_worker($worker_id);

    if (!$existing) {
        return new WP_Error('worker_not_found', 'Worker not found.');
    }

    $worker = construction_mgmt_sanitize_worker_data($data);
    if ($worker['full_name'] === '') {
        return new WP_Error('invalid_worker_name', 'Worker full name is required.');
    }

    $table = construction_mgmt_get_table_name('workers');
    $updated = $wpdb->update($table, $worker, ['id' => $worker_id]);
    if ($updated === false) {
        return new WP_Error('worker_update_failed', 'Unable to update worker.');
    }

    $current = construction_mgmt_get_worker($worker_id);
    construction_mgmt_audit_log('update', 'worker', $worker_id, $existing, $current);

    return $current;
}

function construction_mgmt_delete_worker($worker_id) {
    global $wpdb;

    $worker_id = (int) $worker_id;
    $existing = construction_mgmt_get_worker($worker_id);

    if (!$existing) {
        return new WP_Error('worker_not_found', 'Worker not found.');
    }

    $table = construction_mgmt_get_table_name('workers');
    $deleted = $wpdb->delete($table, ['id' => $worker_id]);
    if (!$deleted) {
        return new WP_Error('worker_delete_failed', 'Unable to delete worker.');
    }

    construction_mgmt_audit_log('delete', 'worker', $worker_id, $existing, null);

    return true;
}