<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_command_center_page() {
    if (!current_user_can('manage_construction_command_center') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to access the JINSING Command Center.');
    }

    $summary = construction_mgmt_get_command_center_summary();
    $financial_summary = construction_mgmt_get_financial_summary();
    $projects = construction_mgmt_get_command_center_projects(30);

    ?>
    <div class="wrap">
        <h1>JINSING Command Center</h1>
        <p>Centralized monitoring for concurrent construction projects.</p>

        <?php if (current_user_can('manage_construction_projects')) : ?>
            <div style="margin: 20px 0;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=construction-mgmt-create-project')); ?>" class="button button-primary">
                    + Create New Project
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=construction-mgmt-project-management')); ?>" class="button">
                    Manage Projects
                </a>
            </div>
        <?php endif; ?>

        <?php if (empty($summary['schema_ready'])) : ?>
            <div class="notice notice-warning">
                <p>
                    Required project tables are missing.
                    <a href="<?php echo esc_url(admin_url('admin.php?page=construction-mgmt-db-tools')); ?>">Open DB Tools</a>
                    and run "Create / Fix All Tables".
                </p>
            </div>
        <?php endif; ?>

        <style>
            .construction-mgmt-cc-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 12px;
                margin: 16px 0 22px;
                max-width: 1100px;
            }

            .construction-mgmt-cc-card {
                border: 1px solid #dcdcde;
                border-radius: 8px;
                background: #fff;
                padding: 14px;
            }

            .construction-mgmt-cc-card h3 {
                margin: 0;
                font-size: 12px;
                font-weight: 600;
                color: #50575e;
                text-transform: uppercase;
                letter-spacing: .03em;
            }

            .construction-mgmt-cc-card .value {
                margin-top: 8px;
                font-size: 26px;
                font-weight: 700;
                color: #1d2327;
            }

            .construction-mgmt-cc-status {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                color: #fff;
            }

            .construction-mgmt-cc-status-planning { background: #3858e9; }
            .construction-mgmt-cc-status-active { background: #1d6f42; }
            .construction-mgmt-cc-status-on_hold { background: #8a6d1f; }
            .construction-mgmt-cc-status-completed { background: #2271b1; }
            .construction-mgmt-cc-status-archived { background: #50575e; }
        </style>

        <div class="construction-mgmt-cc-grid">
            <div class="construction-mgmt-cc-card">
                <h3>Total Projects</h3>
                <div class="value"><?php echo esc_html((string) $summary['projects_total']); ?></div>
            </div>
            <div class="construction-mgmt-cc-card">
                <h3>Active</h3>
                <div class="value"><?php echo esc_html((string) $summary['projects_active']); ?></div>
            </div>
            <div class="construction-mgmt-cc-card">
                <h3>Planning</h3>
                <div class="value"><?php echo esc_html((string) $summary['projects_planning']); ?></div>
            </div>
            <div class="construction-mgmt-cc-card">
                <h3>On Hold</h3>
                <div class="value"><?php echo esc_html((string) $summary['projects_on_hold']); ?></div>
            </div>
            <div class="construction-mgmt-cc-card">
                <h3>Open RFIs</h3>
                <div class="value"><?php echo esc_html((string) $summary['rfis_open']); ?></div>
            </div>
            <div class="construction-mgmt-cc-card">
                <h3>Budget Total</h3>
                <div class="value"><?php echo esc_html(number_format_i18n((float) $financial_summary['budget_total'], 2)); ?></div>
            </div>
            <div class="construction-mgmt-cc-card">
                <h3>Budget Spent</h3>
                <div class="value"><?php echo esc_html(number_format_i18n((float) $financial_summary['budget_spent'], 2)); ?></div>
            </div>
            <div class="construction-mgmt-cc-card">
                <h3>Budget Remaining</h3>
                <div class="value"><?php echo esc_html(number_format_i18n((float) $financial_summary['budget_remaining'], 2)); ?></div>
            </div>
            <div class="construction-mgmt-cc-card">
                <h3>Budget Utilization</h3>
                <div class="value"><?php echo esc_html(number_format_i18n((float) $financial_summary['budget_utilization_percent'], 1)); ?>%</div>
            </div>
            <div class="construction-mgmt-cc-card">
                <h3>Total Objectives</h3>
                <div class="value"><?php echo esc_html((string) $financial_summary['objectives_total']); ?></div>
            </div>
            <div class="construction-mgmt-cc-card">
                <h3>Total Expenditures</h3>
                <div class="value"><?php echo esc_html((string) $financial_summary['expenditures_total']); ?></div>
            </div>
        </div>

        <h2>Project Operations View</h2>
        <table class="widefat striped" style="max-width: 1300px;">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Budget Total</th>
                    <th>Budget Spent</th>
                    <th>Progress</th>
                    <th>Open RFIs</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)) : ?>
                    <tr>
                        <td colspan="7">No projects found yet. Create projects to activate the command center view.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($projects as $project) : ?>
                        <?php
                        $status_key = sanitize_html_class((string) $project['status']);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($project['name']); ?></strong><br />
                                <small>#<?php echo esc_html((string) $project['id']); ?></small>
                            </td>
                            <td>
                                <span class="construction-mgmt-cc-status construction-mgmt-cc-status-<?php echo esc_attr($status_key); ?>">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', (string) $project['status']))); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(number_format_i18n((float) $project['budget_total'], 2)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((float) $project['budget_spent'], 2)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((float) $project['progress_percent'], 1)); ?>%</td>
                            <td><?php echo esc_html((string) $project['rfis_open']); ?></td>
                            <td><?php echo esc_html((string) $project['updated_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
