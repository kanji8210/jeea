<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_create_project_page() {
    if (!current_user_can('manage_construction_projects') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to create projects.');
    }

    $message = '';
    $success = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['construction_mgmt_create_project_nonce'])) {
            wp_die('Security check failed');
        }
        if (!wp_verify_nonce($_POST['construction_mgmt_create_project_nonce'], 'construction_mgmt_create_project')) {
            wp_die('Security check failed');
        }

        $name = sanitize_text_field($_POST['project_name'] ?? '');
        $description = sanitize_textarea_field($_POST['project_description'] ?? '');
        $budget_total = floatval($_POST['project_budget_total'] ?? 0);
        $start_date = sanitize_text_field($_POST['project_start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['project_end_date'] ?? '');

        if (empty($name)) {
            $message = 'Project name is required.';
        } elseif (empty($start_date)) {
            $message = 'Start date is required.';
        } else {
            $project_id = construction_mgmt_create_project($name, $description, $budget_total, $start_date, $end_date, get_current_user_id());
            if ($project_id) {
                $success = true;
                $message = sprintf('Project created successfully. <a href="%s">View project</a>', esc_url(admin_url('admin.php?page=construction-mgmt-project&id=' . $project_id)));
            } else {
                $message = 'Failed to create project.';
            }
        }
    }

    ?>
    <div class="wrap">
        <h1>Create New Project</h1>

        <?php if (!empty($message)) : ?>
            <div class="notice notice-<?php echo $success ? 'success' : 'error'; ?>">
                <p><?php echo wp_kses_post($message); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" style="max-width: 600px;">
            <?php wp_nonce_field('construction_mgmt_create_project', 'construction_mgmt_create_project_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th>
                        <label for="project_name">Project Name *</label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            id="project_name" 
                            name="project_name" 
                            value="<?php echo isset($_POST['project_name']) ? esc_attr($_POST['project_name']) : ''; ?>" 
                            required 
                            class="regular-text"
                        />
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="project_description">Description</label>
                    </th>
                    <td>
                        <textarea 
                            id="project_description" 
                            name="project_description" 
                            rows="4" 
                            class="large-text"
                        ><?php echo isset($_POST['project_description']) ? esc_textarea($_POST['project_description']) : ''; ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="project_budget_total">Total Budget</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="project_budget_total" 
                            name="project_budget_total" 
                            value="<?php echo isset($_POST['project_budget_total']) ? esc_attr($_POST['project_budget_total']) : '0'; ?>" 
                            step="0.01" 
                            min="0" 
                            class="regular-text"
                        />
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="project_start_date">Start Date *</label>
                    </th>
                    <td>
                        <input 
                            type="date" 
                            id="project_start_date" 
                            name="project_start_date" 
                            value="<?php echo isset($_POST['project_start_date']) ? esc_attr($_POST['project_start_date']) : ''; ?>" 
                            required 
                            class="regular-text"
                        />
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="project_end_date">End Date</label>
                    </th>
                    <td>
                        <input 
                            type="date" 
                            id="project_end_date" 
                            name="project_end_date" 
                            value="<?php echo isset($_POST['project_end_date']) ? esc_attr($_POST['project_end_date']) : ''; ?>" 
                            class="regular-text"
                        />
                    </td>
                </tr>
            </table>

            <?php submit_button('Create Project'); ?>
        </form>
    </div>
    <?php
}
