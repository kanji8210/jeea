<?php

if (!defined('ABSPATH')) {
    exit;
}

// Add a new expenditure to a project
function construction_mgmt_add_project_expenditure($project_id, $description, $amount, $incurred_at, $created_by) {
    global $wpdb;
    $table = construction_mgmt_get_table_name('project_expenditures');
    $wpdb->insert($table, [
        'project_id' => $project_id,
        'description' => $description,
        'amount' => $amount,
        'incurred_at' => $incurred_at,
        'created_by' => $created_by,
        'created_at' => current_time('mysql'),
    ]);
    return $wpdb->insert_id;
}

// Get expenditures for a project
function construction_mgmt_get_project_expenditures($project_id) {
    global $wpdb;
    $table = construction_mgmt_get_table_name('project_expenditures');
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE project_id = %d ORDER BY incurred_at ASC, created_at ASC",
        $project_id
    ));
}
