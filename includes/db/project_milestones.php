<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_create_milestone($project_id, $data) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return new WP_Error('invalid_project', 'Invalid project ID.');
    }

    $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
    if (empty($title)) {
        return new WP_Error('missing_title', 'Milestone title is required.');
    }

    $due_date = isset($data['due_date']) ? sanitize_text_field($data['due_date']) : '';
    if (empty($due_date)) {
        return new WP_Error('missing_due_date', 'Milestone due date is required.');
    }

    $table = construction_mgmt_get_table_name('project_milestones');
    $result = $wpdb->insert($table, [
        'project_id' => $project_id,
        'title' => $title,
        'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
        'phase' => isset($data['phase']) ? sanitize_text_field($data['phase']) : '',
        'due_date' => $due_date,
        'completion_date' => isset($data['completion_date']) ? sanitize_text_field($data['completion_date']) : null,
        'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'not_started',
        'deliverables' => isset($data['deliverables']) ? sanitize_textarea_field($data['deliverables']) : '',
    ]);

    if (!$result) {
        return new WP_Error('milestone_insert_failed', 'Unable to create milestone.');
    }

    return (int) $wpdb->insert_id;
}

function construction_mgmt_get_project_milestones($project_id) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return [];
    }

    $table = construction_mgmt_get_table_name('project_milestones');
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE project_id = %d ORDER BY due_date ASC",
            $project_id
        ),
        ARRAY_A
    );

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['project_id'] = (int) $row['project_id'];
    }
    unset($row);

    return $rows ?: [];
}

function construction_mgmt_update_milestone($milestone_id, $data) {
    global $wpdb;

    $milestone_id = (int) $milestone_id;
    if ($milestone_id <= 0) {
        return new WP_Error('invalid_milestone', 'Invalid milestone ID.');
    }

    $update_data = [];

    if (isset($data['status'])) {
        $update_data['status'] = sanitize_text_field($data['status']);
    }

    if (isset($data['completion_date'])) {
        $update_data['completion_date'] = sanitize_text_field($data['completion_date']);
    }

    if (isset($data['description'])) {
        $update_data['description'] = sanitize_textarea_field($data['description']);
    }

    if (empty($update_data)) {
        return false;
    }

    $table = construction_mgmt_get_table_name('project_milestones');
    return $wpdb->update($table, $update_data, ['id' => $milestone_id]);
}
