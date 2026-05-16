<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_sanitize_supplier_data($data) {
    return [
        'name' => isset($data['name']) ? sanitize_text_field($data['name']) : '',
        'kra_pin' => isset($data['kraPin']) ? sanitize_text_field($data['kraPin']) : '',
        'contact_name' => isset($data['contactName']) ? sanitize_text_field($data['contactName']) : '',
        'contact_email' => isset($data['contactEmail']) ? sanitize_email($data['contactEmail']) : '',
        'contact_phone' => isset($data['contactPhone']) ? sanitize_text_field($data['contactPhone']) : '',
        'payment_terms' => isset($data['paymentTerms']) ? sanitize_text_field($data['paymentTerms']) : '',
        'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
    ];
}

function construction_mgmt_get_supplier($supplier_id) {
    global $wpdb;

    $supplier_id = (int) $supplier_id;
    if ($supplier_id <= 0) {
        return null;
    }

    $table = construction_mgmt_get_table_name('suppliers');
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $supplier_id),
        ARRAY_A
    );

    if (empty($row)) {
        return null;
    }

    $row['id'] = (int) $row['id'];

    return $row;
}

function construction_mgmt_get_suppliers() {
    global $wpdb;

    $table = construction_mgmt_get_table_name('suppliers');
    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY updated_at DESC, name ASC", ARRAY_A);

    if (!$rows) {
        return [];
    }

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
    }
    unset($row);

    return $rows;
}

function construction_mgmt_create_supplier($data) {
    global $wpdb;

    $supplier = construction_mgmt_sanitize_supplier_data($data);
    if ($supplier['name'] === '') {
        return new WP_Error('invalid_supplier_name', 'Supplier name is required.');
    }

    if ($supplier['contact_email'] !== '' && !is_email($supplier['contact_email'])) {
        return new WP_Error('invalid_supplier_email', 'A valid supplier email is required.');
    }

    $table = construction_mgmt_get_table_name('suppliers');
    $inserted = $wpdb->insert($table, $supplier);
    if (!$inserted) {
        return new WP_Error('supplier_create_failed', 'Unable to create supplier.');
    }

    $supplier_id = (int) $wpdb->insert_id;
    construction_mgmt_audit_log('create', 'supplier', $supplier_id, null, $supplier);

    return construction_mgmt_get_supplier($supplier_id);
}

function construction_mgmt_update_supplier($supplier_id, $data) {
    global $wpdb;

    $supplier_id = (int) $supplier_id;
    $existing = construction_mgmt_get_supplier($supplier_id);

    if (!$existing) {
        return new WP_Error('supplier_not_found', 'Supplier not found.');
    }

    $supplier = construction_mgmt_sanitize_supplier_data($data);
    if ($supplier['name'] === '') {
        return new WP_Error('invalid_supplier_name', 'Supplier name is required.');
    }

    if ($supplier['contact_email'] !== '' && !is_email($supplier['contact_email'])) {
        return new WP_Error('invalid_supplier_email', 'A valid supplier email is required.');
    }

    $table = construction_mgmt_get_table_name('suppliers');
    $updated = $wpdb->update($table, $supplier, ['id' => $supplier_id]);
    if ($updated === false) {
        return new WP_Error('supplier_update_failed', 'Unable to update supplier.');
    }

    $current = construction_mgmt_get_supplier($supplier_id);
    construction_mgmt_audit_log('update', 'supplier', $supplier_id, $existing, $current);

    return $current;
}

function construction_mgmt_delete_supplier($supplier_id) {
    global $wpdb;

    $supplier_id = (int) $supplier_id;
    $existing = construction_mgmt_get_supplier($supplier_id);

    if (!$existing) {
        return new WP_Error('supplier_not_found', 'Supplier not found.');
    }

    $table = construction_mgmt_get_table_name('suppliers');
    $deleted = $wpdb->delete($table, ['id' => $supplier_id]);
    if (!$deleted) {
        return new WP_Error('supplier_delete_failed', 'Unable to delete supplier.');
    }

    construction_mgmt_audit_log('delete', 'supplier', $supplier_id, $existing, null);

    return true;
}