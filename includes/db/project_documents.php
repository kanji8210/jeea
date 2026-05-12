<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_add_project_document($project_id, $data) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return new WP_Error('invalid_project', 'Invalid project ID.');
    }

    $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
    if (empty($title)) {
        return new WP_Error('missing_title', 'Document title is required.');
    }

    $table = construction_mgmt_get_table_name('project_documents');
    $result = $wpdb->insert($table, [
        'project_id' => $project_id,
        'document_type' => isset($data['document_type']) ? sanitize_text_field($data['document_type']) : 'other',
        'title' => $title,
        'file_url' => isset($data['file_url']) ? esc_url_raw($data['file_url']) : '',
        'version' => isset($data['version']) ? sanitize_text_field($data['version']) : '1.0',
        'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'draft',
        'created_by' => get_current_user_id(),
    ]);

    if (!$result) {
        return new WP_Error('document_insert_failed', 'Unable to add document.');
    }

    return (int) $wpdb->insert_id;
}

function construction_mgmt_get_project_documents($project_id) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return [];
    }

    $table = construction_mgmt_get_table_name('project_documents');
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE project_id = %d ORDER BY created_at DESC",
            $project_id
        ),
        ARRAY_A
    );

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['project_id'] = (int) $row['project_id'];
        $row['created_by'] = (int) $row['created_by'];
    }
    unset($row);

    return $rows ?: [];
}

function construction_mgmt_update_document($document_id, $data) {
    global $wpdb;

    $document_id = (int) $document_id;
    if ($document_id <= 0) {
        return new WP_Error('invalid_document', 'Invalid document ID.');
    }

    $update_data = [];

    if (isset($data['status'])) {
        $update_data['status'] = sanitize_text_field($data['status']);
    }

    if (isset($data['version'])) {
        $update_data['version'] = sanitize_text_field($data['version']);
    }

    if (empty($update_data)) {
        return false;
    }

    $table = construction_mgmt_get_table_name('project_documents');
    return $wpdb->update($table, $update_data, ['id' => $document_id]);
}

function construction_mgmt_generate_project_charter($project_id) {
    $project = construction_mgmt_get_project($project_id);
    if (empty($project)) {
        return new WP_Error('project_not_found', 'Project not found.');
    }

    $metadata = construction_mgmt_get_project_metadata($project_id);
    $milestones = construction_mgmt_get_project_milestones($project_id);
    $team = construction_mgmt_get_project_team($project_id);

    $charter = [
        'project_id' => $project_id,
        'project_name' => $project['name'],
        'project_description' => $project['description'],
        'project_owner' => $metadata['project_owner_id'] ?? null,
        'project_manager' => $metadata['project_manager_id'] ?? null,
        'client' => $metadata['client_name'] ?? '',
        'location' => $metadata['location'] ?? '',
        'budget_total' => $project['budget_total'],
        'budget_contingency_pct' => $metadata['budget_contingency_pct'] ?? 10.0,
        'quality_standard' => $metadata['quality_standard'] ?? '',
        'contract_type' => $metadata['contract_type'] ?? 'fixed_price',
        'start_date' => $project['start_date'],
        'end_date' => $project['end_date'],
        'milestones' => $milestones,
        'team_members' => $team,
        'generated_at' => current_time('mysql'),
    ];

    return $charter;
}
