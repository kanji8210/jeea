<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_create_project_metadata($project_id, $data) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return new WP_Error('invalid_project', 'Invalid project ID.');
    }

    $metadata = [
        'project_id' => $project_id,
        'project_owner_id' => isset($data['project_owner_id']) ? (int) $data['project_owner_id'] : null,
        'project_manager_id' => isset($data['project_manager_id']) ? (int) $data['project_manager_id'] : null,
        'client_name' => isset($data['client_name']) ? sanitize_text_field($data['client_name']) : '',
        'location' => isset($data['location']) ? sanitize_text_field($data['location']) : '',
        'budget_contingency_pct' => isset($data['budget_contingency_pct']) ? (float) $data['budget_contingency_pct'] : 10.00,
        'quality_standard' => isset($data['quality_standard']) ? sanitize_text_field($data['quality_standard']) : '',
        'contract_type' => isset($data['contract_type']) ? sanitize_text_field($data['contract_type']) : 'fixed_price',
        'currency' => isset($data['currency']) ? sanitize_text_field($data['currency']) : 'USD',
    ];

    $table = construction_mgmt_get_table_name('project_metadata');
    $result = $wpdb->replace($table, $metadata);

    if ($result === false) {
        return new WP_Error('metadata_insert_failed', 'Unable to create project metadata.');
    }

    return $wpdb->insert_id;
}

function construction_mgmt_get_project_metadata($project_id) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return null;
    }

    $table = construction_mgmt_get_table_name('project_metadata');
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE project_id = %d", $project_id),
        ARRAY_A
    );

    if (empty($row)) {
        return null;
    }

    $row['id'] = (int) $row['id'];
    $row['project_id'] = (int) $row['project_id'];
    $row['project_owner_id'] = (int) $row['project_owner_id'];
    $row['project_manager_id'] = (int) $row['project_manager_id'];
    $row['budget_contingency_pct'] = (float) $row['budget_contingency_pct'];

    return $row;
}

/**
 * Insert or update project metadata for a project. Only provided keys are updated.
 *
 * @param int   $project_id
 * @param array $data
 * @return bool|WP_Error True on success.
 */
function construction_mgmt_upsert_project_metadata($project_id, $data) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return new WP_Error('invalid_project', 'Invalid project ID.');
    }

    $allowed_contract_types = ['fixed_price', 'time_materials', 'design_build', 'other'];
    $existing = construction_mgmt_get_project_metadata($project_id);
    $table = construction_mgmt_get_table_name('project_metadata');

    $row = [
        'project_id' => $project_id,
        'project_owner_id' => $existing['project_owner_id'] ?? null,
        'project_manager_id' => $existing['project_manager_id'] ?? null,
        'client_name' => $existing['client_name'] ?? '',
        'location' => $existing['location'] ?? '',
        'budget_contingency_pct' => $existing['budget_contingency_pct'] ?? 10.00,
        'quality_standard' => $existing['quality_standard'] ?? '',
        'contract_type' => $existing['contract_type'] ?? 'fixed_price',
        'currency' => $existing['currency'] ?? 'USD',
    ];

    if (array_key_exists('project_owner_id', $data)) {
        $row['project_owner_id'] = $data['project_owner_id'] !== null ? (int) $data['project_owner_id'] : null;
    }
    if (array_key_exists('project_manager_id', $data)) {
        $row['project_manager_id'] = $data['project_manager_id'] !== null ? (int) $data['project_manager_id'] : null;
    }
    if (array_key_exists('client_name', $data)) {
        $row['client_name'] = sanitize_text_field((string) $data['client_name']);
    }
    if (array_key_exists('location', $data)) {
        $row['location'] = sanitize_text_field((string) $data['location']);
    }
    if (array_key_exists('budget_contingency_pct', $data) && $data['budget_contingency_pct'] !== null) {
        $row['budget_contingency_pct'] = max(0.0, min(100.0, (float) $data['budget_contingency_pct']));
    }
    if (array_key_exists('quality_standard', $data)) {
        $row['quality_standard'] = sanitize_text_field((string) $data['quality_standard']);
    }
    if (array_key_exists('contract_type', $data)) {
        $contract = sanitize_text_field((string) $data['contract_type']);
        if (!in_array($contract, $allowed_contract_types, true)) {
            return new WP_Error('invalid_contract_type', 'Invalid contract type.');
        }
        $row['contract_type'] = $contract;
    }
    if (array_key_exists('currency', $data)) {
        $currency = strtoupper(sanitize_text_field((string) $data['currency']));
        if ($currency !== '' && !preg_match('/^[A-Z]{3}$/', $currency)) {
            return new WP_Error('invalid_currency', 'Currency must be a 3-letter ISO code.');
        }
        $row['currency'] = $currency ?: 'USD';
    }

    $result = $wpdb->replace($table, $row);
    if ($result === false) {
        return new WP_Error('metadata_update_failed', 'Unable to save project metadata: ' . $wpdb->last_error);
    }

    return true;
}
