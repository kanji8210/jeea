<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_get_project($project_id) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return null;
    }

    $table = construction_mgmt_get_table_name('projects');
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $project_id),
        ARRAY_A
    );

    if (empty($row)) {
        return null;
    }

    $row['id'] = (int) $row['id'];
    $row['budget_total'] = (float) $row['budget_total'];
    $row['budget_spent'] = (float) $row['budget_spent'];

    return $row;
}

function construction_mgmt_get_command_center_summary() {
    global $wpdb;

    $projects_table = construction_mgmt_get_table_name('projects');
    $rfis_table = construction_mgmt_get_table_name('rfis');

    $projects_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $projects_table));
    $rfis_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $rfis_table));

    if (!$projects_exists) {
        return [
            'projects_total' => 0,
            'projects_active' => 0,
            'projects_on_hold' => 0,
            'projects_planning' => 0,
            'rfis_open' => 0,
            'schema_ready' => false,
        ];
    }

    $summary = [
        'projects_total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$projects_table}"),
        'projects_active' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$projects_table} WHERE status = 'active'"),
        'projects_on_hold' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$projects_table} WHERE status = 'on_hold'"),
        'projects_planning' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$projects_table} WHERE status = 'planning'"),
        'rfis_open' => 0,
        'schema_ready' => true,
    ];

    if ($rfis_exists) {
        $summary['rfis_open'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$rfis_table} WHERE status IN ('draft', 'submitted', 'answered')"
        );
    }

    return $summary;
}

function construction_mgmt_get_command_center_projects($limit = 25) {
    global $wpdb;

    $projects_table = construction_mgmt_get_table_name('projects');
    $rfis_table = construction_mgmt_get_table_name('rfis');

    $projects_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $projects_table));
    if (!$projects_exists) {
        return [];
    }

    $safe_limit = max(1, min(100, (int) $limit));
    $rfis_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $rfis_table));

    if ($rfis_exists) {
        $sql = $wpdb->prepare(
            "SELECT
                p.id,
                p.name,
                p.status,
                p.budget_total,
                p.budget_spent,
                p.start_date,
                p.end_date,
                p.updated_at,
                COUNT(r.id) AS rfis_total,
                SUM(CASE WHEN r.status IN ('draft', 'submitted', 'answered') THEN 1 ELSE 0 END) AS rfis_open
            FROM {$projects_table} p
            LEFT JOIN {$rfis_table} r ON r.project_id = p.id
            GROUP BY p.id
            ORDER BY p.updated_at DESC
            LIMIT %d",
            $safe_limit
        );
    } else {
        $sql = $wpdb->prepare(
            "SELECT
                p.id,
                p.name,
                p.status,
                p.budget_total,
                p.budget_spent,
                p.start_date,
                p.end_date,
                p.updated_at,
                0 AS rfis_total,
                0 AS rfis_open
            FROM {$projects_table} p
            ORDER BY p.updated_at DESC
            LIMIT %d",
            $safe_limit
        );
    }

    $rows = $wpdb->get_results($sql, ARRAY_A);
    if (!$rows) {
        return [];
    }

    foreach ($rows as &$row) {
        $budget_total = (float) $row['budget_total'];
        $budget_spent = (float) $row['budget_spent'];
        $row['budget_total'] = $budget_total;
        $row['budget_spent'] = $budget_spent;
        $row['rfis_total'] = (int) $row['rfis_total'];
        $row['rfis_open'] = (int) $row['rfis_open'];
        $row['progress_percent'] = $budget_total > 0 ? round(($budget_spent / $budget_total) * 100, 1) : 0.0;
    }
    unset($row);

    return $rows;
}

function construction_mgmt_get_financial_summary() {
    global $wpdb;

    $projects_table = construction_mgmt_get_table_name('projects');
    $objectives_table = construction_mgmt_get_table_name('project_objectives');
    $expenditures_table = construction_mgmt_get_table_name('project_expenditures');

    $projects_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $projects_table));
    if (!$projects_exists) {
        return [
            'budget_total' => 0.0,
            'budget_spent' => 0.0,
            'budget_remaining' => 0.0,
            'budget_utilization_percent' => 0.0,
            'objectives_total' => 0,
            'expenditures_total' => 0,
        ];
    }

    $budget_total = (float) $wpdb->get_var("SELECT COALESCE(SUM(budget_total), 0) FROM {$projects_table}");
    $budget_spent = (float) $wpdb->get_var("SELECT COALESCE(SUM(budget_spent), 0) FROM {$projects_table}");

    $objectives_total = 0;
    $expenditures_total = 0;

    $objectives_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $objectives_table));
    if ($objectives_exists) {
        $objectives_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$objectives_table}");
    }

    $expenditures_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $expenditures_table));
    if ($expenditures_exists) {
        $expenditures_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$expenditures_table}");
    }

    $budget_remaining = $budget_total - $budget_spent;
    $budget_utilization_percent = $budget_total > 0 ? round(($budget_spent / $budget_total) * 100, 1) : 0.0;

    return [
        'budget_total' => $budget_total,
        'budget_spent' => $budget_spent,
        'budget_remaining' => $budget_remaining,
        'budget_utilization_percent' => $budget_utilization_percent,
        'objectives_total' => $objectives_total,
        'expenditures_total' => $expenditures_total,
    ];
}

function construction_mgmt_get_project_financial_stats($project_id) {
    global $wpdb;

    $project_id = (int) $project_id;
    if ($project_id <= 0) {
        return [
            'objectives_total' => 0,
            'expenditures_total' => 0,
            'expenditures_amount_total' => 0.0,
            'last_expenditure_date' => null,
        ];
    }

    $objectives_table = construction_mgmt_get_table_name('project_objectives');
    $expenditures_table = construction_mgmt_get_table_name('project_expenditures');

    $objectives_total = 0;
    $expenditures_total = 0;
    $expenditures_amount_total = 0.0;
    $last_expenditure_date = null;

    $objectives_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $objectives_table));
    if ($objectives_exists) {
        $objectives_total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$objectives_table} WHERE project_id = %d", $project_id)
        );
    }

    $expenditures_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $expenditures_table));
    if ($expenditures_exists) {
        $expenditures_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) AS expenditures_total, COALESCE(SUM(amount), 0) AS expenditures_amount_total, MAX(incurred_at) AS last_expenditure_date
                 FROM {$expenditures_table}
                 WHERE project_id = %d",
                $project_id
            ),
            ARRAY_A
        );

        if (!empty($expenditures_row)) {
            $expenditures_total = (int) $expenditures_row['expenditures_total'];
            $expenditures_amount_total = (float) $expenditures_row['expenditures_amount_total'];
            $last_expenditure_date = !empty($expenditures_row['last_expenditure_date']) ? (string) $expenditures_row['last_expenditure_date'] : null;
        }
    }

    return [
        'objectives_total' => $objectives_total,
        'expenditures_total' => $expenditures_total,
        'expenditures_amount_total' => $expenditures_amount_total,
        'last_expenditure_date' => $last_expenditure_date,
    ];
}
