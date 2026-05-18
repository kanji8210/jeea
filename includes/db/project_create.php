<?php

if (!defined('ABSPATH')) {
    exit;
}

// Create a new project
function construction_mgmt_create_project($name, $description, $budget_total, $start_date, $end_date, $created_by) {
    global $wpdb;
    $table = construction_mgmt_get_table_name('projects');
    $wpdb->insert($table, [
        'name' => $name,
        'description' => $description,
        'budget_total' => $budget_total,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'created_by' => $created_by,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ]);
    return $wpdb->insert_id;
}

/**
 * Update an existing project row. Only whitelisted fields are mutable.
 *
 * @param int   $project_id
 * @param array $data Associative array of fields to update.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function construction_mgmt_update_project($project_id, $data) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return new WP_Error('invalid_project', 'Invalid project ID.');
    }

    $table = construction_mgmt_get_table_name('projects');

    $allowed_statuses = ['planning', 'active', 'on_hold', 'completed', 'archived'];
    $update = [];
    $format = [];

    if (array_key_exists('name', $data)) {
        $name = sanitize_text_field((string) $data['name']);
        if ($name === '') {
            return new WP_Error('invalid_name', 'Project name cannot be empty.');
        }
        $update['name'] = $name;
        $format[] = '%s';
    }
    if (array_key_exists('description', $data)) {
        $update['description'] = wp_kses_post((string) $data['description']);
        $format[] = '%s';
    }
    if (array_key_exists('status', $data)) {
        $status = sanitize_text_field((string) $data['status']);
        if (!in_array($status, $allowed_statuses, true)) {
            return new WP_Error('invalid_status', 'Invalid status value.');
        }
        $update['status'] = $status;
        $format[] = '%s';
    }
    if (array_key_exists('budget_total', $data) && $data['budget_total'] !== null) {
        $update['budget_total'] = (float) $data['budget_total'];
        $format[] = '%f';
    }
    if (array_key_exists('budget_spent', $data) && $data['budget_spent'] !== null) {
        $update['budget_spent'] = (float) $data['budget_spent'];
        $format[] = '%f';
    }
    if (array_key_exists('start_date', $data)) {
        $update['start_date'] = $data['start_date'] ? sanitize_text_field((string) $data['start_date']) : null;
        $format[] = '%s';
    }
    if (array_key_exists('end_date', $data)) {
        $update['end_date'] = $data['end_date'] ? sanitize_text_field((string) $data['end_date']) : null;
        $format[] = '%s';
    }

    if (empty($update)) {
        return true;
    }

    $update['updated_at'] = current_time('mysql');
    $format[] = '%s';

    $result = $wpdb->update(
        $table,
        $update,
        ['id' => $project_id],
        $format,
        ['%d']
    );

    if ($result === false) {
        return new WP_Error('project_update_failed', 'Unable to update project: ' . $wpdb->last_error);
    }

    return true;
}
