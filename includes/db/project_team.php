<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_assign_team_member($project_id, $user_id, $role, $responsibility = '') {
    global $wpdb;

    $project_id = (int) $project_id;
    $user_id = (int) $user_id;

    if ($project_id <= 0 || $user_id <= 0) {
        return new WP_Error('invalid_ids', 'Invalid project or user ID.');
    }

    $table = construction_mgmt_get_table_name('project_team');
    $result = $wpdb->replace($table, [
        'project_id' => $project_id,
        'user_id' => $user_id,
        'role' => sanitize_text_field($role),
        'responsibility' => sanitize_textarea_field($responsibility),
    ]);

    if ($result === false) {
        return new WP_Error('assign_failed', 'Unable to assign team member.');
    }

    return true;
}

function construction_mgmt_get_project_team($project_id) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return [];
    }

    $table = construction_mgmt_get_table_name('project_team');
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.*, u.user_login, u.user_email, u.display_name 
             FROM {$table} t
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             WHERE t.project_id = %d
             ORDER BY t.role ASC",
            $project_id
        ),
        ARRAY_A
    );

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['project_id'] = (int) $row['project_id'];
        $row['user_id'] = (int) $row['user_id'];
    }
    unset($row);

    return $rows ?: [];
}

function construction_mgmt_remove_team_member($project_id, $user_id) {
    global $wpdb;

    $project_id = (int) $project_id;
    $user_id = (int) $user_id;

    if ($project_id <= 0 || $user_id <= 0) {
        return new WP_Error('invalid_ids', 'Invalid project or user ID.');
    }

    $table = construction_mgmt_get_table_name('project_team');
    $result = $wpdb->delete($table, [
        'project_id' => $project_id,
        'user_id' => $user_id,
    ]);

    return $result !== false;
}
