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
    $harmonization = construction_mgmt_get_table_harmonization_report();
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
        'Aligned' => '#1d6f42',
        'Legacy Active' => '#b32d2e',
        'Legacy Needs Fixing' => '#8a4f00',
        'Defined in registry' => '#2271b1',
        'Missing from registry' => '#8a6d1f',
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

        <h2 style="margin-top: 24px;">JINSING Schema Harmonization</h2>
        <p>Target schema coverage against the requested <code>jinsing_*</code> model. This compares the current registry to the desired table catalog without renaming live tables yet.</p>

        <table class="widefat striped" style="max-width: 900px; margin-top: 16px;">
            <tbody>
                <tr>
                    <th style="width: 280px;">Target Tables</th>
                    <td><?php echo esc_html((string) $harmonization['target_total']); ?></td>
                </tr>
                <tr>
                    <th>Implemented in Current Registry</th>
                    <td><?php echo esc_html((string) $harmonization['implemented_total']); ?></td>
                </tr>
                <tr>
                    <th>Missing from Current Registry</th>
                    <td><?php echo esc_html((string) $harmonization['missing_total']); ?></td>
                </tr>
                <tr>
                    <th>Rename Pending</th>
                    <td><?php echo esc_html((string) $harmonization['rename_pending_total']); ?></td>
                </tr>
                <tr>
                    <th>Legacy / Extra Registry Tables</th>
                    <td><?php echo esc_html((string) $harmonization['extra_registry_total']); ?></td>
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
            <span class="construction-mgmt-status-badge" style="background-color: #b32d2e;">Legacy Active</span>
        </div>

        <h2 style="margin-top: 24px;">Required Table Status</h2>
        <table class="widefat striped" style="max-width: 1100px;">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Target Table</th>
                    <th>Active Table</th>
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
                            <?php if (!empty($status['active_table_name'])) : ?>
                                <code><?php echo esc_html($status['active_table_name']); ?></code>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
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

        <h2 style="margin-top: 24px;">Target Table Coverage</h2>
        <table class="widefat striped" style="max-width: 1300px;">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Target Table</th>
                    <th>Current Registry Table</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($harmonization['rows'] as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row['module']); ?></td>
                        <td><code><?php echo esc_html($row['target_table_name']); ?></code></td>
                        <td>
                            <?php if (!empty($row['current_table_name'])) : ?>
                                <code><?php echo esc_html($row['current_table_name']); ?></code>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $harmonization_badge_color = isset($badge_colors[$row['status']]) ? $badge_colors[$row['status']] : '#50575e';
                            ?>
                            <span class="construction-mgmt-status-badge" style="background-color: <?php echo esc_attr($harmonization_badge_color); ?>;">
                                <?php echo esc_html($row['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($harmonization['extra_registry_rows'])) : ?>
            <h2 style="margin-top: 24px;">Legacy / Extra Registry Tables</h2>
            <p>These are currently tracked by the plugin but do not map directly to the requested harmonized schema yet.</p>
            <table class="widefat striped" style="max-width: 1100px;">
                <thead>
                    <tr>
                        <th>Registry Key</th>
                        <th>Label</th>
                        <th>Current Table</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($harmonization['extra_registry_rows'] as $row) : ?>
                        <tr>
                            <td><code><?php echo esc_html($row['key']); ?></code></td>
                            <td><?php echo esc_html($row['label']); ?></td>
                            <td><code><?php echo esc_html($row['table_name']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
