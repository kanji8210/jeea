<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_handle_db_tools_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['construction_mgmt_db_tools_action'])) {
        return;
    }

    check_admin_referer('construction_mgmt_db_tools_action');

    $action = sanitize_text_field(wp_unslash($_POST['construction_mgmt_db_tools_action']));

    if ($action === 'sync_all') {
        construction_mgmt_create_tables();
        add_settings_error('construction_mgmt_db_tools', 'db_sync_all_success', 'All required tables were checked and fixed.', 'updated');
    }
}

function construction_mgmt_db_tools_page() {
    construction_mgmt_handle_db_tools_actions();

    $table_statuses = construction_mgmt_get_required_tables_status();
    $created_count = 0;
    $missing_count = 0;
    $needs_fixing_count = 0;

    foreach ($table_statuses as $status) {
        if ($status['is_created']) {
            $created_count++;
        }

        if ($status['needs_creation']) {
            $missing_count++;
        }

        if (!empty($status['needs_fixing'])) {
            $needs_fixing_count++;
        }
    }

    settings_errors('construction_mgmt_db_tools');

    $badge_colors = [
        'Created' => '#1d6f42',
        'Needs Creating' => '#8a6d1f',
        'Needs Fixing' => '#9e2a2b',
    ];
    ?>
    <div class="wrap">
        <h1>JINSING - DB Tools</h1>
        <p>Use this screen to verify required plugin tables and create/fix them on demand.</p>

        <style>
            .construction-mgmt-status-badge {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 999px;
                color: #fff;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.6;
            }
        </style>

        <table class="widefat striped" style="max-width: 900px; margin-top: 16px;">
            <tbody>
                <tr>
                    <th style="width: 220px;">Required Tables</th>
                    <td><?php echo esc_html((string) count($table_statuses)); ?></td>
                </tr>
                <tr>
                    <th>Created</th>
                    <td><?php echo esc_html((string) $created_count); ?></td>
                </tr>
                <tr>
                    <th>Needing Creation</th>
                    <td><?php echo esc_html((string) $missing_count); ?></td>
                </tr>
                <tr>
                    <th>Needing Fixing</th>
                    <td><?php echo esc_html((string) $needs_fixing_count); ?></td>
                </tr>
            </tbody>
        </table>

        <form method="post" style="margin-top: 16px;">
            <?php wp_nonce_field('construction_mgmt_db_tools_action'); ?>
            <input type="hidden" name="construction_mgmt_db_tools_action" value="sync_all" />
            <?php submit_button('Create / Fix All Tables', 'primary', 'submit', false); ?>
        </form>

        <div style="margin-top: 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <strong>Status Legend:</strong>
            <span class="construction-mgmt-status-badge" style="background-color: #1d6f42;">Created</span>
            <span class="construction-mgmt-status-badge" style="background-color: #8a6d1f;">Needs Creating</span>
            <span class="construction-mgmt-status-badge" style="background-color: #9e2a2b;">Needs Fixing</span>
        </div>

        <h2 style="margin-top: 24px;">Required Table Status</h2>
        <table class="widefat striped" style="max-width: 1100px;">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Table Name</th>
                    <th>Status</th>
                    <th>Missing Columns</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($table_statuses as $status) : ?>
                    <tr>
                        <td><?php echo esc_html($status['label']); ?></td>
                        <td><code><?php echo esc_html($status['table_name']); ?></code></td>
                        <td>
                            <?php
                            $badge_color = isset($badge_colors[$status['status_label']]) ? $badge_colors[$status['status_label']] : '#50575e';
                            ?>
                            <span class="construction-mgmt-status-badge" style="background-color: <?php echo esc_attr($badge_color); ?>;">
                                <?php echo esc_html($status['status_label']); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            if (!empty($status['missing_columns'])) {
                                echo esc_html(implode(', ', $status['missing_columns']));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
