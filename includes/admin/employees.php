<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_employees_page() {
    if (!current_user_can('manage_construction_projects') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to manage employees.');
    }

    $message = '';
    $success = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = sanitize_text_field($_POST['employee_action'] ?? '');

        if ($action === 'create') {
            if (!isset($_POST['construction_mgmt_create_employee_nonce']) || !wp_verify_nonce($_POST['construction_mgmt_create_employee_nonce'], 'construction_mgmt_create_employee')) {
                wp_die('Security check failed');
            }

            $payload = [
                'fullName' => sanitize_text_field($_POST['full_name'] ?? ''),
                'nationalId' => sanitize_text_field($_POST['national_id'] ?? ''),
                'nssfNumber' => sanitize_text_field($_POST['nssf_number'] ?? ''),
                'nhifNumber' => sanitize_text_field($_POST['nhif_number'] ?? ''),
                'skillType' => sanitize_text_field($_POST['skill_type'] ?? ''),
                'dailyRate' => (float) ($_POST['daily_rate'] ?? 0),
                'phone' => sanitize_text_field($_POST['phone'] ?? ''),
                'isActive' => !empty($_POST['is_active']) ? 1 : 0,
            ];

            $created = construction_mgmt_create_worker($payload);
            if (is_wp_error($created)) {
                $message = $created->get_error_message();
            } else {
                $message = 'Employee added successfully.';
                $success = true;
                $_POST = [];
            }
        }

        if ($action === 'delete') {
            if (!isset($_POST['construction_mgmt_delete_employee_nonce']) || !wp_verify_nonce($_POST['construction_mgmt_delete_employee_nonce'], 'construction_mgmt_delete_employee')) {
                wp_die('Security check failed');
            }

            $worker_id = absint($_POST['worker_id'] ?? 0);
            if ($worker_id > 0) {
                $deleted = construction_mgmt_delete_worker($worker_id);
                if (is_wp_error($deleted)) {
                    $message = $deleted->get_error_message();
                } else {
                    $message = 'Employee deleted successfully.';
                    $success = true;
                }
            }
        }

        if ($action === 'toggle_active') {
            if (!isset($_POST['construction_mgmt_toggle_employee_nonce']) || !wp_verify_nonce($_POST['construction_mgmt_toggle_employee_nonce'], 'construction_mgmt_toggle_employee')) {
                wp_die('Security check failed');
            }

            $worker_id = absint($_POST['worker_id'] ?? 0);
            $existing = construction_mgmt_get_worker($worker_id);
            if (!$existing) {
                $message = 'Employee not found.';
            } else {
                $existing['isActive'] = !$existing['is_active'];
                $updated = construction_mgmt_update_worker($worker_id, [
                    'fullName' => $existing['full_name'] ?? '',
                    'nationalId' => $existing['national_id'] ?? '',
                    'nssfNumber' => $existing['nssf_number'] ?? '',
                    'nhifNumber' => $existing['nhif_number'] ?? '',
                    'skillType' => $existing['skill_type'] ?? '',
                    'dailyRate' => (float) ($existing['daily_rate'] ?? 0),
                    'phone' => $existing['phone'] ?? '',
                    'isActive' => !empty($existing['isActive']) ? 1 : 0,
                ]);

                if (is_wp_error($updated)) {
                    $message = $updated->get_error_message();
                } else {
                    $message = 'Employee status updated.';
                    $success = true;
                }
            }
        }
    }

    $workers = construction_mgmt_get_workers();
    ?>
    <div class="wrap">
        <h1>Employees</h1>
        <p>Add and manage site employees and casual workers.</p>

        <?php if (!empty($message)) : ?>
            <div class="notice notice-<?php echo $success ? 'success' : 'error'; ?>">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:18px;max-width:980px;margin:18px 0;">
            <h2 style="margin-top:0;">Add Employee</h2>
            <form method="post">
                <?php wp_nonce_field('construction_mgmt_create_employee', 'construction_mgmt_create_employee_nonce'); ?>
                <input type="hidden" name="employee_action" value="create" />

                <table class="form-table">
                    <tr>
                        <th><label for="full_name">Full Name *</label></th>
                        <td><input type="text" id="full_name" name="full_name" class="regular-text" required value="<?php echo esc_attr($_POST['full_name'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="phone">Phone</label></th>
                        <td><input type="text" id="phone" name="phone" class="regular-text" value="<?php echo esc_attr($_POST['phone'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="national_id">National ID</label></th>
                        <td><input type="text" id="national_id" name="national_id" class="regular-text" value="<?php echo esc_attr($_POST['national_id'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="skill_type">Skill Type</label></th>
                        <td><input type="text" id="skill_type" name="skill_type" class="regular-text" placeholder="Mason, Electrician, Foreman..." value="<?php echo esc_attr($_POST['skill_type'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="daily_rate">Daily Rate</label></th>
                        <td><input type="number" id="daily_rate" name="daily_rate" step="0.01" min="0" class="small-text" value="<?php echo esc_attr($_POST['daily_rate'] ?? '0'); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="nssf_number">NSSF Number</label></th>
                        <td><input type="text" id="nssf_number" name="nssf_number" class="regular-text" value="<?php echo esc_attr($_POST['nssf_number'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="nhif_number">NHIF Number</label></th>
                        <td><input type="text" id="nhif_number" name="nhif_number" class="regular-text" value="<?php echo esc_attr($_POST['nhif_number'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Active</th>
                        <td>
                            <label>
                                <?php $is_active_default = !isset($_POST['employee_action']) || !empty($_POST['is_active']); ?>
                                <input type="checkbox" name="is_active" value="1" <?php checked($is_active_default); ?> />
                                Mark employee as active
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Add Employee'); ?>
            </form>
        </div>

        <h2>Employee List</h2>
        <?php if (empty($workers)) : ?>
            <p><em>No employees found yet.</em></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:1100px;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Skill</th>
                        <th>Daily Rate</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workers as $worker) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($worker['full_name'] ?? ''); ?></strong><br />
                                <span style="color:#646970; font-size:12px;">
                                    ID: <?php echo esc_html((string) $worker['id']); ?>
                                    <?php if (!empty($worker['national_id'])) : ?>
                                        · National ID: <?php echo esc_html($worker['national_id']); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($worker['phone'] ?? '--'); ?></td>
                            <td><?php echo esc_html($worker['skill_type'] ?? '--'); ?></td>
                            <td><?php echo esc_html(number_format_i18n((float) ($worker['daily_rate'] ?? 0), 2)); ?></td>
                            <td>
                                <?php if (!empty($worker['is_active'])) : ?>
                                    <span style="color:#1e7e34;font-weight:600;">Active</span>
                                <?php else : ?>
                                    <span style="color:#8a6d3b;font-weight:600;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline-block; margin-right:6px;">
                                    <?php wp_nonce_field('construction_mgmt_toggle_employee', 'construction_mgmt_toggle_employee_nonce'); ?>
                                    <input type="hidden" name="employee_action" value="toggle_active" />
                                    <input type="hidden" name="worker_id" value="<?php echo esc_attr((string) $worker['id']); ?>" />
                                    <button type="submit" class="button"><?php echo !empty($worker['is_active']) ? 'Deactivate' : 'Activate'; ?></button>
                                </form>

                                <form method="post" style="display:inline-block;">
                                    <?php wp_nonce_field('construction_mgmt_delete_employee', 'construction_mgmt_delete_employee_nonce'); ?>
                                    <input type="hidden" name="employee_action" value="delete" />
                                    <input type="hidden" name="worker_id" value="<?php echo esc_attr((string) $worker['id']); ?>" />
                                    <button type="submit" class="button button-link-delete" onclick="return confirm('Delete this employee?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
