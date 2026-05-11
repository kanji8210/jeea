<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_get_required_table_sql() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_prefix = $wpdb->prefix . 'const_';

    return [
        'projects' => [
            'label' => 'Projects',
            'table_name' => $table_prefix . 'projects',
            'required_columns' => [
                'id', 'name', 'description', 'status', 'budget_total', 'budget_spent',
                'start_date', 'end_date', 'created_by', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}projects (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description LONGTEXT,
                status ENUM('planning','active','on_hold','completed','archived') DEFAULT 'planning',
                budget_total DECIMAL(12,2) DEFAULT 0.00,
                budget_spent DECIMAL(12,2) DEFAULT 0.00,
                start_date DATE,
                end_date DATE,
                created_by BIGINT UNSIGNED,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY status (status),
                KEY created_by (created_by)
            ) {$charset_collate};",
        ],
        'project_objectives' => [
            'label' => 'Project Objectives',
            'table_name' => $table_prefix . 'project_objectives',
            'required_columns' => [
                'id', 'project_id', 'objective', 'created_by', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}project_objectives (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                objective TEXT NOT NULL,
                created_by BIGINT UNSIGNED,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'project_expenditures' => [
            'label' => 'Project Expenditures',
            'table_name' => $table_prefix . 'project_expenditures',
            'required_columns' => [
                'id', 'project_id', 'description', 'amount', 'incurred_at', 'created_by', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}project_expenditures (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                description TEXT NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                incurred_at DATE,
                created_by BIGINT UNSIGNED,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'rfis' => [
            'label' => 'RFIs',
            'table_name' => $table_prefix . 'rfis',
            'required_columns' => [
                'id', 'project_id', 'title', 'question', 'answer', 'status',
                'assigned_to', 'due_date', 'created_by', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}rfis (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                question TEXT,
                answer TEXT,
                status ENUM('draft','submitted','answered','closed') DEFAULT 'draft',
                assigned_to BIGINT UNSIGNED,
                due_date DATE,
                created_by BIGINT UNSIGNED,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY status (status),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'change_orders' => [
            'label' => 'Change Orders',
            'table_name' => $table_prefix . 'change_orders',
            'required_columns' => [
                'id', 'project_id', 'rfi_id', 'description', 'amount_delta',
                'status', 'approved_by', 'approved_at', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}change_orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                rfi_id BIGINT UNSIGNED,
                description TEXT,
                amount_delta DECIMAL(12,2) NOT NULL,
                status ENUM('pending','approved','rejected') DEFAULT 'pending',
                approved_by BIGINT UNSIGNED,
                approved_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY rfi_id (rfi_id),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE,
                FOREIGN KEY (rfi_id) REFERENCES {$table_prefix}rfis(id) ON DELETE SET NULL
            ) {$charset_collate};",
        ],
        'audit_log' => [
            'label' => 'Audit Log',
            'table_name' => $table_prefix . 'audit_log',
            'required_columns' => [
                'id', 'user_id', 'action', 'entity_type', 'entity_id',
                'old_value', 'new_value', 'ip_address', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}audit_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED,
                action VARCHAR(100) NOT NULL,
                entity_type VARCHAR(50),
                entity_id BIGINT UNSIGNED,
                old_value TEXT,
                new_value TEXT,
                ip_address VARCHAR(45),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY user_id (user_id),
                KEY action (action),
                KEY entity (entity_type, entity_id)
            ) {$charset_collate};",
        ],
        'rate_limit' => [
            'label' => 'Rate Limit',
            'table_name' => $table_prefix . 'rate_limit',
            'required_columns' => [
                'id', 'ip_address', 'user_id', 'operation_name', 'request_time',
            ],
            'sql' => "CREATE TABLE {$table_prefix}rate_limit (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                user_id BIGINT UNSIGNED,
                operation_name VARCHAR(100),
                request_time DATETIME NOT NULL,
                INDEX (ip_address, operation_name),
                INDEX (user_id, operation_name)
            ) {$charset_collate};",
        ],
    ];
}

function construction_mgmt_sync_table($table_key) {
    $tables = construction_mgmt_get_required_table_sql();
    if (!isset($tables[$table_key])) {
        return false;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($tables[$table_key]['sql']);

    return true;
}

function construction_mgmt_get_required_tables_status() {
    global $wpdb;

    $required = construction_mgmt_get_required_table_sql();
    $statuses = [];

    foreach ($required as $key => $meta) {
        $table_name = $meta['table_name'];
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        $is_created = !empty($exists);
        $needs_creation = !$is_created;
        $needs_fixing = false;
        $missing_columns = [];

        if ($is_created) {
            $column_rows = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
            $existing_columns = array_column($column_rows, 'Field');
            $required_columns = isset($meta['required_columns']) ? $meta['required_columns'] : [];
            $missing_columns = array_values(array_diff($required_columns, $existing_columns));
            $needs_fixing = !empty($missing_columns);
        }

        $status_label = 'Created';
        if ($needs_creation) {
            $status_label = 'Needs Creating';
        } elseif ($needs_fixing) {
            $status_label = 'Needs Fixing';
        }

        $statuses[$key] = [
            'key' => $key,
            'label' => $meta['label'],
            'table_name' => $table_name,
            'is_required' => true,
            'is_created' => $is_created,
            'needs_creation' => $needs_creation,
            'needs_fixing' => $needs_fixing,
            'missing_columns' => $missing_columns,
            'status_label' => $status_label,
        ];
    }

    return $statuses;
}

function construction_mgmt_create_tables() {
    $required = construction_mgmt_get_required_table_sql();
    foreach (array_keys($required) as $table_key) {
        construction_mgmt_sync_table($table_key);
    }
}