<?php

if (!defined('ABSPATH')) {
    exit;
}

// Create a new project objective
function construction_mgmt_add_project_objective($project_id, $objective, $created_by) {
    global $wpdb;
    $table = construction_mgmt_get_table_name('project_objectives');
    $wpdb->insert($table, [
        'project_id' => $project_id,
        'objective' => $objective,
        'created_by' => $created_by,
        'created_at' => current_time('mysql'),
    ]);
    return $wpdb->insert_id;
}

// Get objectives for a project
function construction_mgmt_get_project_objectives($project_id) {
    global $wpdb;
    $table = construction_mgmt_get_table_name('project_objectives');
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE project_id = %d ORDER BY created_at ASC",
        $project_id
    ));
}
