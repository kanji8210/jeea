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
