<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_get_primary_table_prefix() {
    global $wpdb;

    return $wpdb->prefix . 'jinsing_';
}

function construction_mgmt_get_legacy_table_prefix() {
    global $wpdb;

    return $wpdb->prefix . 'const_';
}

function construction_mgmt_table_exists($table_name) {
    global $wpdb;

    return !empty($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)));
}

function construction_mgmt_find_existing_table_name($target_table_name, $legacy_table_name = null) {
    if (construction_mgmt_table_exists($target_table_name)) {
        return $target_table_name;
    }

    if (!empty($legacy_table_name) && construction_mgmt_table_exists($legacy_table_name)) {
        return $legacy_table_name;
    }

    return null;
}

function construction_mgmt_get_table_name($table_key) {
    $tables = construction_mgmt_get_required_table_sql();
    if (!isset($tables[$table_key])) {
        return null;
    }

    $primary_table = $tables[$table_key]['table_name'];
    $legacy_table = isset($tables[$table_key]['legacy_table_name']) ? $tables[$table_key]['legacy_table_name'] : null;
    $active_table = construction_mgmt_find_existing_table_name($primary_table, $legacy_table);

    return $active_table ?: $primary_table;
}

function construction_mgmt_get_required_table_sql() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_prefix = construction_mgmt_get_primary_table_prefix();
    $legacy_table_prefix = construction_mgmt_get_legacy_table_prefix();

    return [
        'projects' => [
            'label' => 'Projects',
            'table_name' => $table_prefix . 'projects',
            'legacy_table_name' => $legacy_table_prefix . 'projects',
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
        'project_metadata' => [
            'label' => 'Project Metadata',
            'table_name' => $table_prefix . 'project_metadata',
            'legacy_table_name' => $legacy_table_prefix . 'project_metadata',
            'required_columns' => [
                'id', 'project_id', 'project_owner_id', 'project_manager_id', 'client_name',
                'location', 'budget_contingency_pct', 'quality_standard', 'contract_type',
                'currency', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}project_metadata (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL UNIQUE,
                project_owner_id BIGINT UNSIGNED,
                project_manager_id BIGINT UNSIGNED,
                client_name VARCHAR(255),
                location VARCHAR(255),
                budget_contingency_pct DECIMAL(5,2) DEFAULT 10.00,
                quality_standard VARCHAR(100),
                contract_type ENUM('fixed_price','time_materials','design_build','other') DEFAULT 'fixed_price',
                currency VARCHAR(3) DEFAULT 'USD',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY project_owner_id (project_owner_id),
                KEY project_manager_id (project_manager_id),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'users' => [
            'label' => 'Extended Users',
            'table_name' => $table_prefix . 'users',
            'required_columns' => [
                'id', 'wp_user_id', 'role_key', 'nca_category', 'phone', 'signature_url', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                wp_user_id BIGINT UNSIGNED NOT NULL,
                role_key VARCHAR(100),
                nca_category VARCHAR(20),
                phone VARCHAR(50),
                signature_url VARCHAR(500),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY wp_user_id (wp_user_id),
                KEY role_key (role_key),
                KEY nca_category (nca_category)
            ) {$charset_collate};",
        ],
        'user_roles' => [
            'label' => 'User Roles',
            'table_name' => $table_prefix . 'user_roles',
            'required_columns' => [
                'id', 'role_key', 'role_name', 'permissions_json', 'is_system', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}user_roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role_key VARCHAR(100) NOT NULL,
                role_name VARCHAR(190) NOT NULL,
                permissions_json LONGTEXT,
                is_system TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY role_key (role_key)
            ) {$charset_collate};",
        ],
        'settings' => [
            'label' => 'Settings',
            'table_name' => $table_prefix . 'settings',
            'required_columns' => [
                'id', 'setting_key', 'setting_value', 'setting_group', 'autoload', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(190) NOT NULL,
                setting_value LONGTEXT,
                setting_group VARCHAR(100),
                autoload TINYINT(1) DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY setting_key (setting_key),
                KEY setting_group (setting_group)
            ) {$charset_collate};",
        ],
        'project_milestones' => [
            'label' => 'Project Milestones',
            'table_name' => $table_prefix . 'project_milestones',
            'legacy_table_name' => $legacy_table_prefix . 'project_milestones',
            'required_columns' => [
                'id', 'project_id', 'title', 'description', 'phase', 'due_date',
                'completion_date', 'status', 'deliverables', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}project_milestones (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                description LONGTEXT,
                phase VARCHAR(100),
                due_date DATE NOT NULL,
                completion_date DATE,
                status ENUM('not_started','in_progress','on_hold','completed','at_risk') DEFAULT 'not_started',
                deliverables LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY due_date (due_date),
                KEY status (status),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'project_team' => [
            'label' => 'Project Team Members',
            'table_name' => $table_prefix . 'project_team',
            'legacy_table_name' => $legacy_table_prefix . 'project_team',
            'required_columns' => [
                'id', 'project_id', 'user_id', 'role', 'responsibility', 'assigned_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}project_team (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                role VARCHAR(100),
                responsibility TEXT,
                assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY user_id (user_id),
                UNIQUE KEY project_user (project_id, user_id),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'tasks' => [
            'label' => 'Tasks',
            'table_name' => $table_prefix . 'tasks',
            'required_columns' => [
                'id', 'project_id', 'parent_task_id', 'title', 'status', 'planned_start', 'planned_end',
                'actual_start', 'actual_end', 'assignee_user_id', 'dependency_task_id', 'percent_complete', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}tasks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                parent_task_id BIGINT UNSIGNED,
                title VARCHAR(255) NOT NULL,
                description LONGTEXT,
                status ENUM('not_started','in_progress','blocked','completed','cancelled') DEFAULT 'not_started',
                planned_start DATE,
                planned_end DATE,
                actual_start DATE,
                actual_end DATE,
                assignee_user_id BIGINT UNSIGNED,
                dependency_task_id BIGINT UNSIGNED,
                percent_complete DECIMAL(5,2) DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY assignee_user_id (assignee_user_id),
                KEY dependency_task_id (dependency_task_id),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'daily_logs' => [
            'label' => 'Daily Logs',
            'table_name' => $table_prefix . 'daily_logs',
            'required_columns' => [
                'id', 'project_id', 'log_date', 'weather_summary', 'progress_percent', 'blockers', 'notes', 'created_by', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}daily_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                log_date DATE NOT NULL,
                weather_summary VARCHAR(255),
                progress_percent DECIMAL(5,2) DEFAULT 0.00,
                blockers LONGTEXT,
                notes LONGTEXT,
                photos_json LONGTEXT,
                created_by BIGINT UNSIGNED,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY log_date (log_date),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'project_documents' => [
            'label' => 'Project Documents',
            'table_name' => $table_prefix . 'project_documents',
            'legacy_table_name' => $legacy_table_prefix . 'project_documents',
            'required_columns' => [
                'id', 'project_id', 'document_type', 'title', 'file_url', 'version',
                'status', 'created_by', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}project_documents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                document_type VARCHAR(100),
                title VARCHAR(255) NOT NULL,
                file_url VARCHAR(500),
                version VARCHAR(20) DEFAULT '1.0',
                status ENUM('draft','approved','archived') DEFAULT 'draft',
                created_by BIGINT UNSIGNED,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY document_type (document_type),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'project_objectives' => [
            'label' => 'Project Objectives',
            'table_name' => $table_prefix . 'project_objectives',
            'legacy_table_name' => $legacy_table_prefix . 'project_objectives',
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
            'legacy_table_name' => $legacy_table_prefix . 'project_expenditures',
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
        'budget_items' => [
            'label' => 'Budget Items',
            'table_name' => $table_prefix . 'budget_items',
            'required_columns' => [
                'id', 'project_id', 'cost_code', 'title', 'planned_amount', 'spent_amount', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}budget_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                cost_code VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                planned_amount DECIMAL(12,2) DEFAULT 0.00,
                spent_amount DECIMAL(12,2) DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY cost_code (cost_code),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'payments' => [
            'label' => 'Payments',
            'table_name' => $table_prefix . 'payments',
            'required_columns' => [
                'id', 'project_id', 'invoice_id', 'supplier_id', 'payment_type', 'amount', 'payment_date', 'status', 'reference_number', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED,
                invoice_id BIGINT UNSIGNED,
                supplier_id BIGINT UNSIGNED,
                payment_type ENUM('client_receipt','supplier_payment','subcontractor_payment','other') DEFAULT 'other',
                amount DECIMAL(12,2) NOT NULL,
                payment_date DATE,
                status ENUM('pending','processed','failed','reconciled') DEFAULT 'pending',
                reference_number VARCHAR(190),
                notes LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY invoice_id (invoice_id),
                KEY supplier_id (supplier_id),
                KEY payment_date (payment_date)
            ) {$charset_collate};",
        ],
        'quote_requests' => [
            'label' => 'Quote Requests',
            'table_name' => $table_prefix . 'quote_requests',
            'legacy_table_name' => $legacy_table_prefix . 'quote_requests',
            'required_columns' => [
                'id', 'project_type', 'project_scope', 'quantities_json', 'qualitative_specs',
                'contact_name', 'contact_email', 'contact_phone', 'contact_company', 'status', 'submitted_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}quote_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_type VARCHAR(100) NOT NULL,
                project_scope LONGTEXT NOT NULL,
                quantities_json LONGTEXT,
                qualitative_specs LONGTEXT,
                contact_name VARCHAR(190) NOT NULL,
                contact_email VARCHAR(190) NOT NULL,
                contact_phone VARCHAR(50) NOT NULL,
                contact_company VARCHAR(190),
                status ENUM('new','reviewing','quoted','closed') DEFAULT 'new',
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY status (status),
                KEY contact_email (contact_email)
            ) {$charset_collate};",
        ],
        'rfis' => [
            'label' => 'RFIs',
            'table_name' => $table_prefix . 'rfis',
            'legacy_table_name' => $legacy_table_prefix . 'rfis',
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
            'legacy_table_name' => $legacy_table_prefix . 'change_orders',
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
        'invoices' => [
            'label' => 'Invoices',
            'table_name' => $table_prefix . 'invoices',
            'required_columns' => [
                'id', 'project_id', 'client_name', 'invoice_number', 'invoice_date', 'due_date', 'status', 'subtotal', 'tax_total', 'grand_total', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}invoices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED,
                client_name VARCHAR(255),
                invoice_number VARCHAR(100) NOT NULL,
                invoice_date DATE,
                due_date DATE,
                status ENUM('draft','issued','partially_paid','paid','overdue','cancelled') DEFAULT 'draft',
                subtotal DECIMAL(12,2) DEFAULT 0.00,
                tax_total DECIMAL(12,2) DEFAULT 0.00,
                grand_total DECIMAL(12,2) DEFAULT 0.00,
                notes LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY invoice_number (invoice_number),
                KEY project_id (project_id),
                KEY due_date (due_date)
            ) {$charset_collate};",
        ],
        'invoice_items' => [
            'label' => 'Invoice Items',
            'table_name' => $table_prefix . 'invoice_items',
            'required_columns' => [
                'id', 'invoice_id', 'description', 'quantity', 'unit_price', 'vat_amount', 'tax_amount', 'line_total', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}invoice_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id BIGINT UNSIGNED NOT NULL,
                description TEXT NOT NULL,
                quantity DECIMAL(12,2) DEFAULT 1.00,
                unit_price DECIMAL(12,2) DEFAULT 0.00,
                vat_amount DECIMAL(12,2) DEFAULT 0.00,
                tax_amount DECIMAL(12,2) DEFAULT 0.00,
                line_total DECIMAL(12,2) DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY invoice_id (invoice_id),
                FOREIGN KEY (invoice_id) REFERENCES {$table_prefix}invoices(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'receipts' => [
            'label' => 'Receipts',
            'table_name' => $table_prefix . 'receipts',
            'required_columns' => [
                'id', 'payment_id', 'receipt_number', 'receipt_date', 'client_name', 'amount', 'payment_method', 'status', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}receipts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                payment_id BIGINT UNSIGNED,
                receipt_number VARCHAR(100) NOT NULL,
                receipt_date DATE,
                client_name VARCHAR(255),
                amount DECIMAL(12,2) DEFAULT 0.00,
                payment_method VARCHAR(50),
                status ENUM('draft','issued','cancelled') DEFAULT 'draft',
                notes LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY receipt_number (receipt_number),
                KEY payment_id (payment_id),
                KEY receipt_date (receipt_date)
            ) {$charset_collate};",
        ],
        'quotes' => [
            'label' => 'Quotes',
            'table_name' => $table_prefix . 'quotes',
            'required_columns' => [
                'id', 'project_id', 'quote_number', 'quote_date', 'client_name', 'valid_until', 'status', 'subtotal', 'tax_total', 'grand_total', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}quotes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED,
                quote_number VARCHAR(100) NOT NULL,
                quote_date DATE,
                client_name VARCHAR(255),
                valid_until DATE,
                status ENUM('draft','issued','accepted','rejected','expired') DEFAULT 'draft',
                subtotal DECIMAL(12,2) DEFAULT 0.00,
                tax_total DECIMAL(12,2) DEFAULT 0.00,
                grand_total DECIMAL(12,2) DEFAULT 0.00,
                notes LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY quote_number (quote_number),
                KEY project_id (project_id),
                KEY valid_until (valid_until)
            ) {$charset_collate};",
        ],
        'quote_items' => [
            'label' => 'Quote Items',
            'table_name' => $table_prefix . 'quote_items',
            'required_columns' => [
                'id', 'quote_id', 'description', 'quantity', 'unit_price', 'vat_amount', 'tax_amount', 'line_total', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}quote_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                quote_id BIGINT UNSIGNED NOT NULL,
                description TEXT NOT NULL,
                quantity DECIMAL(12,2) DEFAULT 1.00,
                unit_price DECIMAL(12,2) DEFAULT 0.00,
                vat_amount DECIMAL(12,2) DEFAULT 0.00,
                tax_amount DECIMAL(12,2) DEFAULT 0.00,
                line_total DECIMAL(12,2) DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY quote_id (quote_id),
                FOREIGN KEY (quote_id) REFERENCES {$table_prefix}quotes(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'suppliers' => [
            'label' => 'Suppliers',
            'table_name' => $table_prefix . 'suppliers',
            'required_columns' => [
                'id', 'name', 'kra_pin', 'contact_name', 'contact_email', 'contact_phone', 'payment_terms', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}suppliers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                kra_pin VARCHAR(50),
                contact_name VARCHAR(190),
                contact_email VARCHAR(190),
                contact_phone VARCHAR(50),
                payment_terms VARCHAR(190),
                notes LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY name (name),
                KEY kra_pin (kra_pin)
            ) {$charset_collate};",
        ],
        'purchase_requisitions' => [
            'label' => 'Purchase Requisitions',
            'table_name' => $table_prefix . 'purchase_requisitions',
            'required_columns' => [
                'id', 'project_id', 'requested_by', 'requested_for_date', 'status', 'justification', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}purchase_requisitions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED,
                requested_by BIGINT UNSIGNED,
                requested_for_date DATE,
                status ENUM('draft','submitted','approved','rejected','converted_to_po') DEFAULT 'draft',
                justification LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY requested_by (requested_by),
                KEY status (status)
            ) {$charset_collate};",
        ],
        'purchase_orders' => [
            'label' => 'Purchase Orders',
            'table_name' => $table_prefix . 'purchase_orders',
            'required_columns' => [
                'id', 'project_id', 'supplier_id', 'po_number', 'order_date', 'status', 'subtotal', 'tax_total', 'grand_total', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}purchase_orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED,
                supplier_id BIGINT UNSIGNED,
                po_number VARCHAR(100) NOT NULL,
                order_date DATE,
                expected_delivery_date DATE,
                status ENUM('draft','issued','partially_received','received','cancelled') DEFAULT 'draft',
                subtotal DECIMAL(12,2) DEFAULT 0.00,
                tax_total DECIMAL(12,2) DEFAULT 0.00,
                grand_total DECIMAL(12,2) DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY po_number (po_number),
                KEY project_id (project_id),
                KEY supplier_id (supplier_id),
                KEY status (status)
            ) {$charset_collate};",
        ],
        'po_items' => [
            'label' => 'PO Items',
            'table_name' => $table_prefix . 'po_items',
            'required_columns' => [
                'id', 'purchase_order_id', 'item_name', 'description', 'quantity', 'unit_price', 'line_total', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}po_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                purchase_order_id BIGINT UNSIGNED NOT NULL,
                item_name VARCHAR(255) NOT NULL,
                description LONGTEXT,
                quantity DECIMAL(12,2) DEFAULT 1.00,
                unit_price DECIMAL(12,2) DEFAULT 0.00,
                line_total DECIMAL(12,2) DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY purchase_order_id (purchase_order_id),
                FOREIGN KEY (purchase_order_id) REFERENCES {$table_prefix}purchase_orders(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'goods_receipts' => [
            'label' => 'Goods Receipts',
            'table_name' => $table_prefix . 'goods_receipts',
            'required_columns' => [
                'id', 'purchase_order_id', 'project_id', 'receipt_number', 'received_at', 'received_by', 'notes', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}goods_receipts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                purchase_order_id BIGINT UNSIGNED,
                project_id BIGINT UNSIGNED,
                receipt_number VARCHAR(100) NOT NULL,
                received_at DATETIME,
                received_by BIGINT UNSIGNED,
                notes LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY receipt_number (receipt_number),
                KEY purchase_order_id (purchase_order_id),
                KEY project_id (project_id)
            ) {$charset_collate};",
        ],
        'inventory_items' => [
            'label' => 'Inventory Items',
            'table_name' => $table_prefix . 'inventory_items',
            'required_columns' => [
                'id', 'item_code', 'name', 'unit', 'reorder_level', 'is_active', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}inventory_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                item_code VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                unit VARCHAR(50),
                reorder_level DECIMAL(12,2) DEFAULT 0.00,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY item_code (item_code),
                KEY name (name)
            ) {$charset_collate};",
        ],
        'stock_levels' => [
            'label' => 'Stock Levels',
            'table_name' => $table_prefix . 'stock_levels',
            'required_columns' => [
                'id', 'inventory_item_id', 'location_type', 'location_ref_id', 'quantity_on_hand', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}stock_levels (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                inventory_item_id BIGINT UNSIGNED NOT NULL,
                location_type ENUM('warehouse','site') DEFAULT 'site',
                location_ref_id BIGINT UNSIGNED,
                quantity_on_hand DECIMAL(12,2) DEFAULT 0.00,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY inventory_item_id (inventory_item_id),
                KEY location_type (location_type),
                UNIQUE KEY item_location (inventory_item_id, location_type, location_ref_id),
                FOREIGN KEY (inventory_item_id) REFERENCES {$table_prefix}inventory_items(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'workers' => [
            'label' => 'Workers',
            'table_name' => $table_prefix . 'workers',
            'required_columns' => [
                'id', 'full_name', 'national_id', 'nssf_number', 'nhif_number', 'skill_type', 'daily_rate', 'phone', 'is_active', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}workers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(255) NOT NULL,
                national_id VARCHAR(100),
                nssf_number VARCHAR(100),
                nhif_number VARCHAR(100),
                skill_type VARCHAR(150),
                daily_rate DECIMAL(12,2) DEFAULT 0.00,
                phone VARCHAR(50),
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY full_name (full_name),
                KEY skill_type (skill_type)
            ) {$charset_collate};",
        ],
        'timesheets' => [
            'label' => 'Timesheets',
            'table_name' => $table_prefix . 'timesheets',
            'required_columns' => [
                'id', 'project_id', 'task_id', 'worker_id', 'work_date', 'hours_worked', 'approved_by', 'approval_status', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}timesheets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED,
                task_id BIGINT UNSIGNED,
                worker_id BIGINT UNSIGNED NOT NULL,
                work_date DATE NOT NULL,
                hours_worked DECIMAL(6,2) DEFAULT 0.00,
                approved_by BIGINT UNSIGNED,
                approval_status ENUM('pending','approved','rejected') DEFAULT 'pending',
                notes LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY task_id (task_id),
                KEY worker_id (worker_id),
                KEY work_date (work_date),
                FOREIGN KEY (worker_id) REFERENCES {$table_prefix}workers(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'equipment' => [
            'label' => 'Equipment',
            'table_name' => $table_prefix . 'equipment',
            'required_columns' => [
                'id', 'asset_code', 'name', 'equipment_type', 'ownership_type', 'hourly_rate', 'status', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}equipment (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                asset_code VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                equipment_type VARCHAR(150),
                ownership_type ENUM('owned','leased','rented') DEFAULT 'owned',
                hourly_rate DECIMAL(12,2) DEFAULT 0.00,
                status ENUM('available','in_use','maintenance','retired') DEFAULT 'available',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY asset_code (asset_code),
                KEY status (status)
            ) {$charset_collate};",
        ],
        'equipment_usage' => [
            'label' => 'Equipment Usage',
            'table_name' => $table_prefix . 'equipment_usage',
            'required_columns' => [
                'id', 'equipment_id', 'project_id', 'task_id', 'usage_date', 'hours_used', 'fuel_consumed', 'operator_name', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}equipment_usage (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                equipment_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED,
                task_id BIGINT UNSIGNED,
                usage_date DATE,
                hours_used DECIMAL(8,2) DEFAULT 0.00,
                fuel_consumed DECIMAL(10,2) DEFAULT 0.00,
                operator_name VARCHAR(190),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY equipment_id (equipment_id),
                KEY project_id (project_id),
                KEY usage_date (usage_date),
                FOREIGN KEY (equipment_id) REFERENCES {$table_prefix}equipment(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'equipment_maintenance' => [
            'label' => 'Equipment Maintenance',
            'table_name' => $table_prefix . 'equipment_maintenance',
            'required_columns' => [
                'id', 'equipment_id', 'maintenance_type', 'scheduled_date', 'completed_date', 'status', 'cost_amount', 'notes', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}equipment_maintenance (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                equipment_id BIGINT UNSIGNED NOT NULL,
                maintenance_type VARCHAR(150),
                scheduled_date DATE,
                completed_date DATE,
                status ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
                cost_amount DECIMAL(12,2) DEFAULT 0.00,
                notes LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY equipment_id (equipment_id),
                KEY scheduled_date (scheduled_date),
                KEY status (status),
                FOREIGN KEY (equipment_id) REFERENCES {$table_prefix}equipment(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'inspection_templates' => [
            'label' => 'Inspection Templates',
            'table_name' => $table_prefix . 'inspection_templates',
            'required_columns' => [
                'id', 'name', 'category', 'checklist_json', 'is_active', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}inspection_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                category VARCHAR(150),
                checklist_json LONGTEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY name (name),
                KEY category (category)
            ) {$charset_collate};",
        ],
        'inspections' => [
            'label' => 'Inspections',
            'table_name' => $table_prefix . 'inspections',
            'required_columns' => [
                'id', 'project_id', 'template_id', 'inspection_date', 'inspector_user_id', 'result_status', 'findings', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}inspections (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED,
                template_id BIGINT UNSIGNED,
                inspection_date DATE,
                inspector_user_id BIGINT UNSIGNED,
                result_status ENUM('pass','fail','pass_with_conditions') DEFAULT 'pass',
                findings LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY template_id (template_id),
                KEY inspection_date (inspection_date)
            ) {$charset_collate};",
        ],
        'punchlist' => [
            'label' => 'Punch List',
            'table_name' => $table_prefix . 'punchlist',
            'required_columns' => [
                'id', 'project_id', 'description', 'photo_url', 'assigned_to', 'due_date', 'status', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}punchlist (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED,
                description LONGTEXT NOT NULL,
                photo_url VARCHAR(500),
                assigned_to BIGINT UNSIGNED,
                due_date DATE,
                status ENUM('open','in_progress','closed') DEFAULT 'open',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY assigned_to (assigned_to),
                KEY status (status)
            ) {$charset_collate};",
        ],
        'safety_incidents' => [
            'label' => 'Safety Incidents',
            'table_name' => $table_prefix . 'safety_incidents',
            'required_columns' => [
                'id', 'project_id', 'incident_date', 'incident_type', 'severity_level', 'description', 'root_cause', 'corrective_action', 'reported_by', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}safety_incidents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED,
                incident_date DATE,
                incident_type VARCHAR(150),
                severity_level ENUM('low','medium','high','critical') DEFAULT 'low',
                description LONGTEXT,
                root_cause LONGTEXT,
                corrective_action LONGTEXT,
                reported_by BIGINT UNSIGNED,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY incident_date (incident_date),
                KEY severity_level (severity_level)
            ) {$charset_collate};",
        ],
        'submittals' => [
            'label' => 'Submittals',
            'table_name' => $table_prefix . 'submittals',
            'required_columns' => [
                'id', 'project_id', 'title', 'submittal_type', 'status', 'review_comments', 'submitted_by', 'submitted_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}submittals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                submittal_type VARCHAR(100),
                status ENUM('draft','submitted','under_review','approved','rejected','closed') DEFAULT 'draft',
                review_comments LONGTEXT,
                document_url VARCHAR(500),
                submitted_by BIGINT UNSIGNED,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY status (status),
                FOREIGN KEY (project_id) REFERENCES {$table_prefix}projects(id) ON DELETE CASCADE
            ) {$charset_collate};",
        ],
        'mpesa_transactions' => [
            'label' => 'M-Pesa Transactions',
            'table_name' => $table_prefix . 'mpesa_transactions',
            'required_columns' => [
                'id', 'merchant_request_id', 'checkout_request_id', 'result_code', 'result_desc',
                'amount', 'mpesa_receipt_number', 'phone_number', 'transaction_date',
                'payment_status', 'raw_payload', 'created_at', 'updated_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}mpesa_transactions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                merchant_request_id VARCHAR(190),
                checkout_request_id VARCHAR(190),
                result_code INT,
                result_desc TEXT,
                amount DECIMAL(12,2) DEFAULT 0.00,
                mpesa_receipt_number VARCHAR(100),
                phone_number VARCHAR(50),
                transaction_date VARCHAR(32),
                payment_status ENUM('pending','success','failed') DEFAULT 'pending',
                raw_payload LONGTEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY merchant_request_id (merchant_request_id),
                KEY checkout_request_id (checkout_request_id),
                KEY payment_status (payment_status)
            ) {$charset_collate};",
        ],
        'audit_log' => [
            'label' => 'Audit Log',
            'table_name' => $table_prefix . 'audit_log',
            'legacy_table_name' => $legacy_table_prefix . 'audit_log',
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
            'legacy_table_name' => $legacy_table_prefix . 'rate_limit',
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
        'expenses' => [
            'label' => 'Expenses',
            'table_name' => $table_prefix . 'expenses',
            'legacy_table_name' => $legacy_table_prefix . 'project_expenditures',
            'required_columns' => [
                'id', 'project_id', 'vendor', 'amount', 'vat', 'date', 'cost_code',
                'description', 'source', 'source_id', 'created_by', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}expenses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED,
                vendor VARCHAR(255),
                amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                vat DECIMAL(12,2) DEFAULT 0.00,
                date DATE,
                cost_code VARCHAR(100),
                description TEXT,
                source ENUM('manual','ocr','timesheet_auto','mpesa','api') DEFAULT 'manual',
                source_id BIGINT UNSIGNED,
                created_by BIGINT UNSIGNED,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY project_id (project_id),
                KEY cost_code (cost_code),
                KEY source (source),
                KEY date (date)
            ) {$charset_collate};",
        ],
        'ocr_queue' => [
            'label' => 'OCR Queue',
            'table_name' => $table_prefix . 'ocr_queue',
            'required_columns' => [
                'id', 'file_path', 'file_type', 'project_id', 'processing_status',
                'extracted_json', 'processed_at', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}ocr_queue (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                file_path VARCHAR(500) NOT NULL,
                file_type ENUM('receipt','invoice','po','other') DEFAULT 'receipt',
                project_id BIGINT UNSIGNED,
                processing_status ENUM('queued','processing','completed','failed') DEFAULT 'queued',
                extracted_json LONGTEXT,
                error_message TEXT,
                queued_by BIGINT UNSIGNED,
                processed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY processing_status (processing_status),
                KEY project_id (project_id)
            ) {$charset_collate};",
        ],
        'auto_entry_logs' => [
            'label' => 'Auto Entry Logs',
            'table_name' => $table_prefix . 'auto_entry_logs',
            'required_columns' => [
                'id', 'source_type', 'source_id', 'created_entity_type',
                'created_entity_id', 'extracted_data', 'confidence_score', 'status', 'created_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}auto_entry_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_type ENUM('ocr','timesheet','mpesa','api') NOT NULL,
                source_id BIGINT UNSIGNED,
                created_entity_type VARCHAR(50),
                created_entity_id BIGINT UNSIGNED,
                extracted_data LONGTEXT,
                confidence_score DECIMAL(5,4) DEFAULT 0.0000,
                status ENUM('auto_approved','pending_review','rejected') DEFAULT 'pending_review',
                reviewed_by BIGINT UNSIGNED,
                reviewed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY source_type (source_type),
                KEY status (status),
                KEY created_entity_type (created_entity_type)
            ) {$charset_collate};",
        ],
        'docs_articles' => [
            'label' => 'Docs Articles',
            'table_name' => $table_prefix . 'docs_articles',
            'required_columns' => [
                'id', 'title', 'slug', 'content', 'excerpt', 'doc_type', 'category',
                'tags', 'author_id', 'version', 'status', 'view_count', 'helpful_count',
                'not_helpful_count', 'created_at', 'updated_at', 'published_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}docs_articles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                content LONGTEXT NOT NULL,
                excerpt TEXT,
                doc_type ENUM('guide','api','tutorial','faq','changelog') DEFAULT 'guide',
                category VARCHAR(100),
                tags LONGTEXT,
                author_id BIGINT UNSIGNED,
                version VARCHAR(20) DEFAULT '1.0',
                status ENUM('draft','published','archived') DEFAULT 'draft',
                view_count INT DEFAULT 0,
                helpful_count INT DEFAULT 0,
                not_helpful_count INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                published_at DATETIME,
                UNIQUE KEY slug (slug),
                KEY doc_type (doc_type),
                KEY status (status)
            ) {$charset_collate};",
        ],
        'docs_categories' => [
            'label' => 'Docs Categories',
            'table_name' => $table_prefix . 'docs_categories',
            'required_columns' => [
                'id', 'name', 'slug', 'description', 'parent_id', 'display_order',
            ],
            'sql' => "CREATE TABLE {$table_prefix}docs_categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                description TEXT,
                parent_id BIGINT UNSIGNED,
                display_order INT DEFAULT 0,
                UNIQUE KEY slug (slug),
                KEY parent_id (parent_id)
            ) {$charset_collate};",
        ],
        'docs_access' => [
            'label' => 'Docs Access Log',
            'table_name' => $table_prefix . 'docs_access',
            'required_columns' => [
                'id', 'user_id', 'article_id', 'access_type', 'accessed_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}docs_access (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                article_id BIGINT UNSIGNED NOT NULL,
                access_type ENUM('read','download') DEFAULT 'read',
                accessed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY user_id (user_id),
                KEY article_id (article_id)
            ) {$charset_collate};",
        ],
        'docs_search' => [
            'label' => 'Docs Search History',
            'table_name' => $table_prefix . 'docs_search',
            'required_columns' => [
                'id', 'user_id', 'search_term', 'results_count', 'ip_address', 'searched_at',
            ],
            'sql' => "CREATE TABLE {$table_prefix}docs_search (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED,
                search_term VARCHAR(255),
                results_count INT,
                ip_address VARCHAR(45),
                searched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY search_term (search_term),
                KEY user_id (user_id)
            ) {$charset_collate};",
        ],
    ];
}

function construction_mgmt_get_target_table_blueprint() {
    return [
        ['module' => 'Core / System', 'suffix' => 'users', 'label' => 'Extended Users'],
        ['module' => 'Core / System', 'suffix' => 'user_roles', 'label' => 'User Roles'],
        ['module' => 'Core / System', 'suffix' => 'audit_log', 'label' => 'Audit Log'],
        ['module' => 'Core / System', 'suffix' => 'settings', 'label' => 'Settings'],

        ['module' => 'Project Management', 'suffix' => 'projects', 'label' => 'Projects'],
        ['module' => 'Project Management', 'suffix' => 'project_team', 'label' => 'Project Team'],
        ['module' => 'Project Management', 'suffix' => 'milestones', 'label' => 'Milestones'],
        ['module' => 'Project Management', 'suffix' => 'tasks', 'label' => 'Tasks'],
        ['module' => 'Project Management', 'suffix' => 'daily_logs', 'label' => 'Daily Logs'],

        ['module' => 'Financial & Cost Management', 'suffix' => 'budget_items', 'label' => 'Budget Items'],
        ['module' => 'Financial & Cost Management', 'suffix' => 'expenses', 'label' => 'Expenses'],
        ['module' => 'Financial & Cost Management', 'suffix' => 'expense_attachments', 'label' => 'Expense Attachments'],
        ['module' => 'Financial & Cost Management', 'suffix' => 'change_orders', 'label' => 'Change Orders'],
        ['module' => 'Financial & Cost Management', 'suffix' => 'retention', 'label' => 'Retention'],
        ['module' => 'Financial & Cost Management', 'suffix' => 'payments', 'label' => 'Payments'],
        ['module' => 'Financial & Cost Management', 'suffix' => 'ocr_logs', 'label' => 'OCR Logs'],

        ['module' => 'Invoicing & Billing', 'suffix' => 'invoices', 'label' => 'Invoices'],
        ['module' => 'Invoicing & Billing', 'suffix' => 'invoice_items', 'label' => 'Invoice Items'],
        ['module' => 'Invoicing & Billing', 'suffix' => 'receipts', 'label' => 'Receipts'],
        ['module' => 'Invoicing & Billing', 'suffix' => 'quotes', 'label' => 'Quotes'],
        ['module' => 'Invoicing & Billing', 'suffix' => 'quote_items', 'label' => 'Quote Items'],
        ['module' => 'Invoicing & Billing', 'suffix' => 'credit_notes', 'label' => 'Credit Notes'],
        ['module' => 'Invoicing & Billing', 'suffix' => 'recurring_invoice_profiles', 'label' => 'Recurring Invoice Profiles'],

        ['module' => 'Procurement & Inventory', 'suffix' => 'suppliers', 'label' => 'Suppliers'],
        ['module' => 'Procurement & Inventory', 'suffix' => 'purchase_requisitions', 'label' => 'Purchase Requisitions'],
        ['module' => 'Procurement & Inventory', 'suffix' => 'purchase_orders', 'label' => 'Purchase Orders'],
        ['module' => 'Procurement & Inventory', 'suffix' => 'po_items', 'label' => 'PO Items'],
        ['module' => 'Procurement & Inventory', 'suffix' => 'goods_receipts', 'label' => 'Goods Receipts'],
        ['module' => 'Procurement & Inventory', 'suffix' => 'inventory_items', 'label' => 'Inventory Items'],
        ['module' => 'Procurement & Inventory', 'suffix' => 'stock_levels', 'label' => 'Stock Levels'],

        ['module' => 'Resource Management', 'suffix' => 'workers', 'label' => 'Workers'],
        ['module' => 'Resource Management', 'suffix' => 'timesheets', 'label' => 'Timesheets'],
        ['module' => 'Resource Management', 'suffix' => 'equipment', 'label' => 'Equipment'],
        ['module' => 'Resource Management', 'suffix' => 'equipment_usage', 'label' => 'Equipment Usage'],
        ['module' => 'Resource Management', 'suffix' => 'equipment_maintenance', 'label' => 'Equipment Maintenance'],

        ['module' => 'Quality & Safety', 'suffix' => 'inspection_templates', 'label' => 'Inspection Templates'],
        ['module' => 'Quality & Safety', 'suffix' => 'inspections', 'label' => 'Inspections'],
        ['module' => 'Quality & Safety', 'suffix' => 'punchlist', 'label' => 'Punch List'],
        ['module' => 'Quality & Safety', 'suffix' => 'safety_incidents', 'label' => 'Safety Incidents'],

        ['module' => 'RFI & Submittal', 'suffix' => 'rfis', 'label' => 'RFIs'],
        ['module' => 'RFI & Submittal', 'suffix' => 'rfi_attachments', 'label' => 'RFI Attachments'],
        ['module' => 'RFI & Submittal', 'suffix' => 'submittals', 'label' => 'Submittals'],

        ['module' => 'Integrations & External Data', 'suffix' => 'mpesa_transactions', 'label' => 'M-Pesa Transactions'],
        ['module' => 'Integrations & External Data', 'suffix' => 'kra_tax_returns', 'label' => 'KRA Tax Returns'],
        ['module' => 'Integrations & External Data', 'suffix' => 'weather_logs', 'label' => 'Weather Logs'],

        ['module' => 'AI / ML Specific', 'suffix' => 'ml_predictions', 'label' => 'ML Predictions'],
        ['module' => 'AI / ML Specific', 'suffix' => 'training_data', 'label' => 'Training Data'],

        ['module' => 'Automated Entries', 'suffix' => 'expenses', 'label' => 'Expenses'],
        ['module' => 'Automated Entries', 'suffix' => 'ocr_queue', 'label' => 'OCR Queue'],
        ['module' => 'Automated Entries', 'suffix' => 'auto_entry_logs', 'label' => 'Auto Entry Logs'],

        ['module' => 'Documentation', 'suffix' => 'docs_articles', 'label' => 'Docs Articles'],
        ['module' => 'Documentation', 'suffix' => 'docs_categories', 'label' => 'Docs Categories'],
        ['module' => 'Documentation', 'suffix' => 'docs_access', 'label' => 'Docs Access Log'],
        ['module' => 'Documentation', 'suffix' => 'docs_search', 'label' => 'Docs Search History'],
    ];
}

function construction_mgmt_get_target_table_name($suffix) {
    return construction_mgmt_get_primary_table_prefix() . $suffix;
}

function construction_mgmt_get_table_harmonization_report() {
    $required_tables = construction_mgmt_get_required_table_sql();
    $target_tables = construction_mgmt_get_target_table_blueprint();

    $implemented_mapping = [
        'users' => 'users',
        'user_roles' => 'user_roles',
        'settings' => 'settings',
        'projects' => 'projects',
        'project_team' => 'project_team',
        'project_milestones' => 'milestones',
        'tasks' => 'tasks',
        'daily_logs' => 'daily_logs',
        'budget_items' => 'budget_items',
        'project_expenditures' => 'expenses',
        'change_orders' => 'change_orders',
        'payments' => 'payments',
        'invoices' => 'invoices',
        'invoice_items' => 'invoice_items',
        'receipts' => 'receipts',
        'quotes' => 'quotes',
        'quote_items' => 'quote_items',
        'suppliers' => 'suppliers',
        'purchase_requisitions' => 'purchase_requisitions',
        'purchase_orders' => 'purchase_orders',
        'po_items' => 'po_items',
        'goods_receipts' => 'goods_receipts',
        'inventory_items' => 'inventory_items',
        'stock_levels' => 'stock_levels',
        'workers' => 'workers',
        'timesheets' => 'timesheets',
        'equipment' => 'equipment',
        'equipment_usage' => 'equipment_usage',
        'equipment_maintenance' => 'equipment_maintenance',
        'inspection_templates' => 'inspection_templates',
        'inspections' => 'inspections',
        'punchlist' => 'punchlist',
        'safety_incidents' => 'safety_incidents',
        'rfis' => 'rfis',
        'submittals' => 'submittals',
        'mpesa_transactions' => 'mpesa_transactions',
        'audit_log' => 'audit_log',
        'docs_articles' => 'docs_articles',
        'docs_categories' => 'docs_categories',
        'docs_access' => 'docs_access',
        'docs_search' => 'docs_search',
        'expenses' => 'expenses',
        'ocr_queue' => 'ocr_queue',
        'auto_entry_logs' => 'auto_entry_logs',
    ];

    $mapped_required_keys = [];
    $rows = [];
    $implemented_count = 0;
    $needs_rename_count = 0;
    $missing_count = 0;

    foreach ($target_tables as $target_table) {
        $target_suffix = $target_table['suffix'];
        $matched_key = array_search($target_suffix, $implemented_mapping, true);
        $target_table_name = construction_mgmt_get_target_table_name($target_suffix);

        if ($matched_key && isset($required_tables[$matched_key])) {
            $mapped_required_keys[] = $matched_key;
            $meta = $required_tables[$matched_key];
            $current_table_name = construction_mgmt_find_existing_table_name(
                $meta['table_name'],
                $meta['legacy_table_name'] ?? null
            );
            if ($current_table_name === $target_table_name) {
                $status = 'Aligned';
                $implemented_count++;
            } elseif (!empty($current_table_name)) {
                $status = 'Legacy Active';
                $implemented_count++;
                $needs_rename_count++;
            } else {
                $status = 'Defined in registry';
            }
        } else {
            $current_table_name = null;
            $status = 'Missing from registry';
            $missing_count++;
        }

        $rows[] = [
            'module' => $target_table['module'],
            'label' => $target_table['label'],
            'target_table_name' => $target_table_name,
            'current_key' => $matched_key ?: null,
            'current_table_name' => $current_table_name,
            'status' => $status,
        ];
    }

    $extra_registry_rows = [];
    foreach ($required_tables as $key => $meta) {
        if (in_array($key, $mapped_required_keys, true)) {
            continue;
        }

        $extra_registry_rows[] = [
            'key' => $key,
            'label' => $meta['label'],
            'table_name' => $meta['table_name'],
        ];
    }

    return [
        'target_total' => count($target_tables),
        'implemented_total' => $implemented_count,
        'missing_total' => $missing_count,
        'rename_pending_total' => $needs_rename_count,
        'extra_registry_total' => count($extra_registry_rows),
        'rows' => $rows,
        'extra_registry_rows' => $extra_registry_rows,
    ];
}

function construction_mgmt_sync_table($table_key) {
    $tables = construction_mgmt_get_required_table_sql();
    if (!isset($tables[$table_key])) {
        return false;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($tables[$table_key]['sql']);

    construction_mgmt_migrate_legacy_table($table_key);

    return true;
}

function construction_mgmt_migrate_legacy_table($table_key) {
    global $wpdb;

    $tables = construction_mgmt_get_required_table_sql();
    if (!isset($tables[$table_key])) {
        return false;
    }

    $primary_table = $tables[$table_key]['table_name'];
    $legacy_table = isset($tables[$table_key]['legacy_table_name']) ? $tables[$table_key]['legacy_table_name'] : null;

    if (empty($legacy_table) || !construction_mgmt_table_exists($legacy_table) || !construction_mgmt_table_exists($primary_table)) {
        return false;
    }

    $primary_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$primary_table}");
    if ($primary_count > 0) {
        return false;
    }

    $primary_columns = $wpdb->get_results("SHOW COLUMNS FROM {$primary_table}", ARRAY_A);
    $legacy_columns = $wpdb->get_results("SHOW COLUMNS FROM {$legacy_table}", ARRAY_A);
    if (empty($primary_columns) || empty($legacy_columns)) {
        return false;
    }

    $primary_column_names = array_column($primary_columns, 'Field');
    $legacy_column_names = array_column($legacy_columns, 'Field');
    $shared_columns = array_values(array_intersect($primary_column_names, $legacy_column_names));
    if (empty($shared_columns)) {
        return false;
    }

    $column_sql = implode(', ', array_map(static function ($column) {
        return '`' . esc_sql($column) . '`';
    }, $shared_columns));

    $wpdb->query("INSERT INTO {$primary_table} ({$column_sql}) SELECT {$column_sql} FROM {$legacy_table}");

    return true;
}

function construction_mgmt_get_required_tables_status() {
    global $wpdb;

    $required = construction_mgmt_get_required_table_sql();
    $statuses = [];

    foreach ($required as $key => $meta) {
        $table_name = $meta['table_name'];
        $legacy_table_name = isset($meta['legacy_table_name']) ? $meta['legacy_table_name'] : null;
        $active_table_name = construction_mgmt_find_existing_table_name($table_name, $legacy_table_name);
        $is_created = !empty($active_table_name);
        $needs_creation = !$is_created;
        $needs_migration = $is_created && $active_table_name === $legacy_table_name;
        $needs_fixing = false;
        $missing_columns = [];

        if ($is_created) {
            $column_rows = $wpdb->get_results("SHOW COLUMNS FROM {$active_table_name}", ARRAY_A);
            $existing_columns = array_column($column_rows, 'Field');
            $required_columns = isset($meta['required_columns']) ? $meta['required_columns'] : [];
            $missing_columns = array_values(array_diff($required_columns, $existing_columns));
            $needs_fixing = !empty($missing_columns);
        }

        $status_label = 'Created';
        if ($needs_creation) {
            $status_label = 'Needs Creating';
        } elseif ($needs_migration) {
            $status_label = $needs_fixing ? 'Legacy Needs Fixing' : 'Legacy Active';
        } elseif ($needs_fixing) {
            $status_label = 'Needs Fixing';
        }

        $statuses[$key] = [
            'key' => $key,
            'label' => $meta['label'],
            'table_name' => $table_name,
            'legacy_table_name' => $legacy_table_name,
            'active_table_name' => $active_table_name,
            'is_required' => true,
            'is_created' => $is_created,
            'needs_creation' => $needs_creation,
            'needs_migration' => $needs_migration,
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