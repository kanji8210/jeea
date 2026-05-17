<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_project_list_page() {
    global $wpdb;
    $projects_table = construction_mgmt_get_table_name('projects');

    $projects = $wpdb->get_results("SELECT * FROM $projects_table ORDER BY updated_at DESC");

    $project_stats_map = [];
    $project_public_url_map = [];
    if (!empty($projects)) {
        foreach ($projects as $project_row) {
            $pid = (int) $project_row->id;
            $project_stats_map[$pid] = construction_mgmt_get_project_financial_stats($pid);

            // Find linked CPT post for public view URL
            $cpt_posts = get_posts( [
                'post_type'      => 'jinsing_project',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_key'       => '_jinsing_project_id',
                'meta_value'     => $pid,
                'fields'         => 'ids',
            ] );
            $project_public_url_map[$pid] = ! empty( $cpt_posts ) ? get_permalink( $cpt_posts[0] ) : '';
        }
    }
    
    ?>
    <div class="wrap">
        <h1>Manage Projects</h1>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=construction-mgmt-create-project')); ?>" class="button button-primary">
                + Create New Project
            </a>
        </p>

        <?php if (!empty($projects)) : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Status</th>
                        <th>Budget Total</th>
                        <th>Spent</th>
                        <th>Objectives</th>
                        <th>Expenditures</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project) : ?>
                        <?php $stats = $project_stats_map[(int) $project->id] ?? ['objectives_total' => 0, 'expenditures_total' => 0]; ?>
                        <tr>
                            <td><strong><?php echo esc_html($project->name); ?></strong> (ID: <?php echo esc_html($project->id); ?>)</td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $project->status))); ?></td>
                            <td><?php echo esc_html(number_format_i18n((float) $project->budget_total, 2)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((float) $project->budget_spent, 2)); ?></td>
                            <td><?php echo esc_html((string) (int) $stats['objectives_total']); ?></td>
                            <td><?php echo esc_html((string) (int) $stats['expenditures_total']); ?></td>
                            <td><?php echo esc_html($project->start_date); ?></td>
                            <td><?php echo esc_html($project->end_date ?? '--'); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=construction-mgmt-project-management&id=' . $project->id)); ?>" class="button button-primary" style="margin-right:4px;">Manage Project</a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=construction-mgmt-project-management&id=' . $project->id . '#site-manager-card')); ?>" class="button" style="margin-right:4px;">Site Manager</a>
                                <?php
                                $pub_url = $project_public_url_map[(int) $project->id] ?? '';
                                if ( $pub_url ) : ?>
                                <a href="<?php echo esc_url($pub_url); ?>" class="button" target="_blank" rel="noopener">View Project ↗</a>
                                <?php else : ?>
                                <span class="button disabled" title="No published CPT post linked yet" style="opacity:.5;cursor:default;">View Project</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><em>No projects found. <a href="<?php echo esc_url(admin_url('admin.php?page=construction-mgmt-create-project')); ?>">Create one now</a>.</em></p>
        <?php endif; ?>
    </div>
    <?php
}

function construction_mgmt_project_management_page() {
    if (!current_user_can('manage_construction_projects') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to manage projects.');
    }

    $project_id = intval($_GET['id'] ?? 0);

    // If no project ID, show project list
    if (empty($project_id)) {
        construction_mgmt_project_list_page();
        return;
    }

    global $wpdb;
    $projects_table = construction_mgmt_get_table_name('projects');
    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE id = %d", $project_id));

    if (!$project) {
        wp_die('Project not found.');
    }

    $message = '';
    $success = false;
    $action = sanitize_text_field($_POST['action'] ?? '');

    // Handle adding objective
    if ($action === 'add_objective' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!wp_verify_nonce($_POST['construction_mgmt_objective_nonce'] ?? '', 'construction_mgmt_add_objective')) {
            wp_die('Security check failed');
        }
        $objective = sanitize_textarea_field($_POST['objective_text'] ?? '');
        if (!empty($objective)) {
            construction_mgmt_add_project_objective($project_id, $objective, get_current_user_id());
            $message = 'Objective added successfully.';
            $success = true;
        }
    }

    // Handle adding expenditure
    if ($action === 'add_expenditure' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!wp_verify_nonce($_POST['construction_mgmt_expenditure_nonce'] ?? '', 'construction_mgmt_add_expenditure')) {
            wp_die('Security check failed');
        }
        $description = sanitize_textarea_field($_POST['expenditure_description'] ?? '');
        $amount = floatval($_POST['expenditure_amount'] ?? 0);
        $incurred_at = sanitize_text_field($_POST['expenditure_date'] ?? date('Y-m-d'));

        if (!empty($description) && $amount > 0) {
            construction_mgmt_add_project_expenditure($project_id, $description, $amount, $incurred_at, get_current_user_id());
            
            // Update project budget_spent
            $wpdb->query($wpdb->prepare(
                "UPDATE $projects_table SET budget_spent = budget_spent + %f WHERE id = %d",
                $amount,
                $project_id
            ));
            
            $message = 'Expenditure recorded successfully.';
            $success = true;
        } else {
            $message = 'Description and amount are required.';
        }
    }

    // Handle assigning/changing site manager
    if ($action === 'assign_site_manager' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!wp_verify_nonce($_POST['construction_mgmt_site_manager_nonce'] ?? '', 'construction_mgmt_assign_site_manager')) {
            wp_die('Security check failed');
        }

        $site_manager_id = absint($_POST['site_manager_id'] ?? 0);
        $allowed_roles = ['construction_site_manager', 'construction_site_engineer', 'construction_project_manager'];

        if ($site_manager_id > 0) {
            $site_manager_user = get_user_by('id', $site_manager_id);
            if (!$site_manager_user) {
                $message = 'Selected site manager was not found.';
            } else {
                $has_allowed_role = false;
                foreach ((array) $site_manager_user->roles as $role_key) {
                    if (in_array($role_key, $allowed_roles, true)) {
                        $has_allowed_role = true;
                        break;
                    }
                }

                if (!$has_allowed_role && !user_can($site_manager_user, 'manage_construction_projects')) {
                    $message = 'Selected user is not eligible for site manager assignment.';
                } else {
                    $existing_team = construction_mgmt_get_project_team($project_id);
                    foreach ($existing_team as $member) {
                        if (strtolower((string) ($member['role'] ?? '')) === 'site manager') {
                            construction_mgmt_remove_team_member($project_id, (int) ($member['user_id'] ?? 0));
                        }
                    }

                    $assigned = construction_mgmt_assign_team_member($project_id, $site_manager_id, 'Site Manager', 'Primary site manager');
                    if (is_wp_error($assigned)) {
                        $message = 'Unable to assign site manager.';
                    } else {
                        $message = 'Site manager assigned successfully.';
                        $success = true;
                    }
                }
            }
        } else {
            $existing_team = construction_mgmt_get_project_team($project_id);
            foreach ($existing_team as $member) {
                if (strtolower((string) ($member['role'] ?? '')) === 'site manager') {
                    construction_mgmt_remove_team_member($project_id, (int) ($member['user_id'] ?? 0));
                }
            }
            $message = 'Site manager cleared. You can assign one later.';
            $success = true;
        }
    }

    $objectives = construction_mgmt_get_project_objectives($project_id);
    $expenditures = construction_mgmt_get_project_expenditures($project_id);
    $financial_stats = construction_mgmt_get_project_financial_stats($project_id);
    $total_expenditure = (float) $financial_stats['expenditures_amount_total'];
    $remaining_budget = (float) $project->budget_total - $total_expenditure;
    $utilization_percent = (float) $project->budget_total > 0 ? round(($total_expenditure / (float) $project->budget_total) * 100, 1) : 0.0;
    $site_manager_candidates = get_users([
        'fields' => ['ID', 'display_name', 'user_login', 'user_email', 'roles'],
        'role__in' => ['construction_site_manager', 'construction_site_engineer', 'construction_project_manager'],
        'orderby' => 'display_name',
        'order' => 'ASC',
    ]);
    $current_site_manager = null;
    foreach (construction_mgmt_get_project_team($project_id) as $member) {
        if (strtolower((string) ($member['role'] ?? '')) === 'site manager') {
            $current_site_manager = $member;
            break;
        }
    }

    // Resolve public CPT URL for this project
    $cpt_pub_posts = get_posts( [
        'post_type'      => 'jinsing_project',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'meta_key'       => '_jinsing_project_id',
        'meta_value'     => $project_id,
        'fields'         => 'ids',
    ] );
    $cpt_pub_url = ! empty( $cpt_pub_posts ) ? get_permalink( $cpt_pub_posts[0] ) : '';

    ?>
    <div class="wrap">
        <h1><?php echo esc_html($project->name); ?></h1>
        <p>
            <strong>Project ID:</strong> #<?php echo esc_html($project->id); ?> | 
            <strong>Status:</strong> <?php echo esc_html(ucwords(str_replace('_', ' ', $project->status))); ?> |
            <a href="<?php echo esc_url(admin_url('admin.php?page=construction-mgmt-project-management')); ?>">← Back to Projects</a>
            <?php if ( $cpt_pub_url ) : ?>
            &nbsp;&nbsp;
            <a href="<?php echo esc_url( $cpt_pub_url ); ?>" target="_blank" rel="noopener" class="button button-secondary" style="text-decoration:none;">View Public Page ↗</a>
            <?php endif; ?>
        </p>

        <?php if (!empty($message)) : ?>
            <div class="notice notice-<?php echo $success ? 'success' : 'error'; ?>">
                <p><?php echo wp_kses_post($message); ?></p>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 16px 0 22px; max-width: 1100px;">
            <div style="border: 1px solid #dcdcde; border-radius: 8px; background: #fff; padding: 14px;">
                <h3 style="margin: 0; font-size: 12px; font-weight: 600; color: #50575e; text-transform: uppercase; letter-spacing: .03em;">Objectives</h3>
                <div style="margin-top: 8px; font-size: 26px; font-weight: 700; color: #1d2327;"><?php echo esc_html((string) (int) $financial_stats['objectives_total']); ?></div>
            </div>
            <div style="border: 1px solid #dcdcde; border-radius: 8px; background: #fff; padding: 14px;">
                <h3 style="margin: 0; font-size: 12px; font-weight: 600; color: #50575e; text-transform: uppercase; letter-spacing: .03em;">Expenditure Entries</h3>
                <div style="margin-top: 8px; font-size: 26px; font-weight: 700; color: #1d2327;"><?php echo esc_html((string) (int) $financial_stats['expenditures_total']); ?></div>
            </div>
            <div style="border: 1px solid #dcdcde; border-radius: 8px; background: #fff; padding: 14px;">
                <h3 style="margin: 0; font-size: 12px; font-weight: 600; color: #50575e; text-transform: uppercase; letter-spacing: .03em;">Budget Utilization</h3>
                <div style="margin-top: 8px; font-size: 26px; font-weight: 700; color: #1d2327;"><?php echo esc_html(number_format_i18n($utilization_percent, 1)); ?>%</div>
            </div>
            <div style="border: 1px solid #dcdcde; border-radius: 8px; background: #fff; padding: 14px;">
                <h3 style="margin: 0; font-size: 12px; font-weight: 600; color: #50575e; text-transform: uppercase; letter-spacing: .03em;">Last Expenditure</h3>
                <div style="margin-top: 8px; font-size: 26px; font-weight: 700; color: #1d2327;"><?php echo esc_html($financial_stats['last_expenditure_date'] ?: 'N/A'); ?></div>
            </div>
        </div>

        <!-- Project Overview -->
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0;">
            <h2>Project Overview</h2>
            <p><?php echo esc_html($project->description ?? 'No description'); ?></p>
            <table>
                <tr>
                    <td><strong>Start Date:</strong></td>
                    <td><?php echo esc_html($project->start_date); ?></td>
                </tr>
                <tr>
                    <td><strong>End Date:</strong></td>
                    <td><?php echo esc_html($project->end_date ?? 'Not set'); ?></td>
                </tr>
                <tr>
                    <td><strong>Total Budget:</strong></td>
                    <td><?php echo esc_html(number_format_i18n((float) $project->budget_total, 2)); ?></td>
                </tr>
                <tr>
                    <td><strong>Total Spent:</strong></td>
                    <td><?php echo esc_html(number_format_i18n($total_expenditure, 2)); ?></td>
                </tr>
                <tr>
                    <td><strong>Remaining:</strong></td>
                    <td><?php echo esc_html(number_format_i18n($remaining_budget, 2)); ?></td>
                </tr>
            </table>
        </div>

        <div id="site-manager-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0;">
            <h2>Site Manager</h2>
            <p style="margin-top:0; color:#50575e;">Assign now or leave blank and add later as the project grows.</p>

            <?php if ($current_site_manager) : ?>
                <p>
                    <strong>Current:</strong>
                    <?php echo esc_html(($current_site_manager['display_name'] ?: $current_site_manager['user_login']) . ' (' . $current_site_manager['user_email'] . ')'); ?>
                </p>
            <?php else : ?>
                <p><em>No site manager assigned yet.</em></p>
            <?php endif; ?>

            <form method="post" style="margin-top:12px;">
                <?php wp_nonce_field('construction_mgmt_assign_site_manager', 'construction_mgmt_site_manager_nonce'); ?>
                <input type="hidden" name="action" value="assign_site_manager" />
                <select id="site_manager_id" name="site_manager_id" class="regular-text" style="min-width:360px;">
                    <option value="">Assign later / clear assignment</option>
                    <?php foreach ($site_manager_candidates as $candidate) : ?>
                        <option value="<?php echo esc_attr($candidate->ID); ?>" <?php selected((int) ($current_site_manager['user_id'] ?? 0), (int) $candidate->ID); ?>>
                            <?php echo esc_html(($candidate->display_name ?: $candidate->user_login) . ' (' . $candidate->user_email . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button('Save Site Manager', 'secondary', 'submit', false, ['style' => 'margin-left:8px;']); ?>
            </form>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <!-- Objectives Section -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <h2>Project Objectives</h2>
                
                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('construction_mgmt_add_objective', 'construction_mgmt_objective_nonce'); ?>
                    <input type="hidden" name="action" value="add_objective" />
                    <textarea 
                        name="objective_text" 
                        placeholder="Add a project objective..." 
                        rows="3" 
                        class="large-text"
                    ></textarea>
                    <?php submit_button('Add Objective', 'primary', 'submit', false); ?>
                </form>

                <?php if (!empty($objectives)) : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Objective</th>
                                <th>Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($objectives as $obj) : ?>
                                <tr>
                                    <td><?php echo esc_html($obj->objective); ?></td>
                                    <td><?php echo esc_html(substr($obj->created_at, 0, 10)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><em>No objectives added yet.</em></p>
                <?php endif; ?>
            </div>

            <!-- Expenditures Section -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <h2>Project Expenditures</h2>
                
                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('construction_mgmt_add_expenditure', 'construction_mgmt_expenditure_nonce'); ?>
                    <input type="hidden" name="action" value="add_expenditure" />
                    <textarea 
                        name="expenditure_description" 
                        placeholder="Expenditure description..." 
                        rows="2" 
                        class="large-text"
                    ></textarea>
                    <input 
                        type="number" 
                        name="expenditure_amount" 
                        placeholder="Amount" 
                        step="0.01" 
                        min="0" 
                        class="regular-text" 
                        style="margin-top: 10px; width: 100%;"
                    />
                    <input 
                        type="date" 
                        name="expenditure_date" 
                        value="<?php echo date('Y-m-d'); ?>" 
                        class="regular-text" 
                        style="margin-top: 10px; width: 100%;"
                    />
                    <?php submit_button('Add Expenditure', 'primary', 'submit', false); ?>
                </form>

                <?php if (!empty($expenditures)) : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenditures as $exp) : ?>
                                <tr>
                                    <td><?php echo esc_html($exp->description); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((float) $exp->amount, 2)); ?></td>
                                    <td><?php echo esc_html($exp->incurred_at); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="background: #f1f1f1; font-weight: bold;">
                                <td>TOTAL</td>
                                <td><?php echo esc_html(number_format_i18n($total_expenditure, 2)); ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><em>No expenditures recorded yet.</em></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
