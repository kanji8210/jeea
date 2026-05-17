<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_get_construction_roles_for_assignment() {
    if (!function_exists('wp_roles')) {
        require_once ABSPATH . 'wp-includes/capabilities.php';
    }

    $roles = wp_roles()->roles;
    $result = [];
    foreach ($roles as $role_key => $role_data) {
        if (strpos((string) $role_key, 'construction_') === 0) {
            $result[$role_key] = (string) ($role_data['name'] ?? $role_key);
        }
    }

    return $result;
}

function construction_mgmt_get_linked_wp_user_for_worker($worker_id) {
    $users = get_users([
        'number' => 1,
        'meta_key' => 'construction_worker_id',
        'meta_value' => (int) $worker_id,
    ]);

    if (empty($users)) {
        return null;
    }

    return $users[0];
}

function construction_mgmt_employees_page() {
    if (!current_user_can('manage_construction_projects') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to manage employees.');
    }

    $message = '';
    $success = false;
    $generated_credentials_note = '';

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

        if ($action === 'grant_access') {
            if (!isset($_POST['construction_mgmt_grant_access_nonce']) || !wp_verify_nonce($_POST['construction_mgmt_grant_access_nonce'], 'construction_mgmt_grant_access')) {
                wp_die('Security check failed');
            }

            $worker_id = absint($_POST['worker_id'] ?? 0);
            $role_key = sanitize_key($_POST['access_role_key'] ?? '');
            $existing_user_id = absint($_POST['existing_user_id'] ?? 0);
            $email = sanitize_email($_POST['access_email'] ?? '');
            $username = sanitize_user($_POST['access_username'] ?? '', true);
            $password_raw = (string) ($_POST['access_password'] ?? '');

            $worker = construction_mgmt_get_worker($worker_id);
            if (!$worker) {
                $message = 'Employee not found.';
            } else {
                $allowed_roles = construction_mgmt_get_construction_roles_for_assignment();
                if ($role_key === '' || !isset($allowed_roles[$role_key])) {
                    $message = 'Please select a valid construction role.';
                } else {
                    $user = null;

                    if ($existing_user_id > 0) {
                        $user = get_user_by('id', $existing_user_id);
                        if (!$user) {
                            $message = 'Selected existing user was not found.';
                        }
                    } else {
                        if (!is_email($email)) {
                            $message = 'A valid email is required to create a login account.';
                        } else {
                            $existing_by_email = get_user_by('email', $email);
                            if ($existing_by_email) {
                                $user = $existing_by_email;
                            } else {
                                if ($username === '') {
                                    $username = sanitize_user(strstr($email, '@', true), true);
                                }

                                if ($username === '') {
                                    $username = sanitize_user(str_replace(' ', '_', strtolower((string) ($worker['full_name'] ?? 'worker'))), true);
                                }

                                $base_username = $username;
                                $suffix = 1;
                                while (username_exists($username)) {
                                    $username = $base_username . '_' . $suffix;
                                    $suffix++;
                                }

                                $password = trim($password_raw) !== '' ? $password_raw : wp_generate_password(16, true, false);
                                $new_user_id = wp_create_user($username, $password, $email);
                                if (is_wp_error($new_user_id)) {
                                    $message = 'Unable to create login account for employee.';
                                } else {
                                    wp_update_user([
                                        'ID' => $new_user_id,
                                        'display_name' => $worker['full_name'] ?? $username,
                                        'first_name' => strstr((string) ($worker['full_name'] ?? ''), ' ', true) ?: (string) ($worker['full_name'] ?? ''),
                                        'last_name' => strstr((string) ($worker['full_name'] ?? ''), ' ') ? ltrim((string) strstr((string) ($worker['full_name'] ?? ''), ' ')) : '',
                                    ]);

                                    $user = get_user_by('id', $new_user_id);
                                    $generated_credentials_note = ' Username: ' . $username . ' | Temporary Password: ' . $password;
                                }
                            }
                        }
                    }

                    if ($user && empty($message)) {
                        foreach ((array) $user->roles as $existing_role) {
                            if (strpos((string) $existing_role, 'construction_') === 0) {
                                $user->remove_role($existing_role);
                            }
                        }

                        $user->add_role($role_key);
                        update_user_meta($user->ID, 'construction_worker_id', (int) $worker_id);
                        update_user_meta($user->ID, 'construction_worker_full_name', (string) ($worker['full_name'] ?? ''));

                        $message = 'Login access granted and role assigned successfully.' . $generated_credentials_note;
                        $success = true;
                    }
                }
            }
        }

        if ($action === 'disable_login') {
            if (!isset($_POST['construction_mgmt_disable_login_nonce']) || !wp_verify_nonce($_POST['construction_mgmt_disable_login_nonce'], 'construction_mgmt_disable_login')) {
                wp_die('Security check failed');
            }

            $worker_id = absint($_POST['worker_id'] ?? 0);
            $linked_user = construction_mgmt_get_linked_wp_user_for_worker($worker_id);

            if (!$linked_user) {
                $message = 'No linked login account found for this employee.';
            } else {
                foreach ((array) $linked_user->roles as $existing_role) {
                    if (strpos((string) $existing_role, 'construction_') === 0) {
                        $linked_user->remove_role($existing_role);
                    }
                }

                delete_user_meta($linked_user->ID, 'construction_worker_id');
                delete_user_meta($linked_user->ID, 'construction_worker_full_name');

                $message = 'Employee login access disabled successfully.';
                $success = true;
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
    $construction_roles = construction_mgmt_get_construction_roles_for_assignment();
    $wp_users = get_users([
        'fields' => ['ID', 'display_name', 'user_email', 'user_login'],
        'orderby' => 'display_name',
        'order' => 'ASC',
    ]);
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
                        <th>Login Access</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workers as $worker) : ?>
                        <?php
                        $linked_user = construction_mgmt_get_linked_wp_user_for_worker((int) ($worker['id'] ?? 0));
                        ?>
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
                                <?php if ($linked_user) : ?>
                                    <strong style="color:#1e7e34;">Enabled</strong><br />
                                    <span style="font-size:12px;color:#646970;">
                                        <?php echo esc_html($linked_user->user_login . ' (' . $linked_user->user_email . ')'); ?>
                                    </span>
                                <?php else : ?>
                                    <strong style="color:#8a6d3b;">Not enabled</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline-block; margin-right:6px;">
                                    <?php wp_nonce_field('construction_mgmt_toggle_employee', 'construction_mgmt_toggle_employee_nonce'); ?>
                                    <input type="hidden" name="employee_action" value="toggle_active" />
                                    <input type="hidden" name="worker_id" value="<?php echo esc_attr((string) $worker['id']); ?>" />
                                    <button type="submit" class="button"><?php echo !empty($worker['is_active']) ? 'Deactivate' : 'Activate'; ?></button>
                                </form>

                                <?php if ($linked_user) : ?>
                                    <form method="post" style="display:inline-block; margin-right:6px;">
                                        <?php wp_nonce_field('construction_mgmt_disable_login', 'construction_mgmt_disable_login_nonce'); ?>
                                        <input type="hidden" name="employee_action" value="disable_login" />
                                        <input type="hidden" name="worker_id" value="<?php echo esc_attr((string) $worker['id']); ?>" />
                                        <button type="submit" class="button" onclick="return confirm('Disable login access for this employee?');">Disable Login</button>
                                    </form>
                                <?php endif; ?>

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

        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:18px;max-width:1100px;margin:22px 0;">
            <h2 style="margin-top:0;">Grant Login Access</h2>
            <p>Assign a construction role so an employee can log in to the system.</p>

            <form method="post">
                <?php wp_nonce_field('construction_mgmt_grant_access', 'construction_mgmt_grant_access_nonce'); ?>
                <input type="hidden" name="employee_action" value="grant_access" />

                <table class="form-table">
                    <tr>
                        <th><label for="worker_id">Employee</label></th>
                        <td>
                            <select id="worker_id" name="worker_id" required class="regular-text">
                                <option value="">Select employee...</option>
                                <?php foreach ($workers as $worker) : ?>
                                    <option value="<?php echo esc_attr((string) $worker['id']); ?>">
                                        <?php echo esc_html(($worker['full_name'] ?? 'Employee') . ' (ID ' . (int) ($worker['id'] ?? 0) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="access_role_key">Role</label></th>
                        <td>
                            <select id="access_role_key" name="access_role_key" required class="regular-text">
                                <option value="">Select construction role...</option>
                                <?php foreach ($construction_roles as $role_key => $role_name) : ?>
                                    <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_name . ' (' . $role_key . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="existing_user_id">Use Existing WP User (optional)</label></th>
                        <td>
                            <select id="existing_user_id" name="existing_user_id" class="regular-text">
                                <option value="">Create new / lookup by email below...</option>
                                <?php foreach ($wp_users as $wp_user) : ?>
                                    <option value="<?php echo esc_attr((string) $wp_user->ID); ?>">
                                        <?php echo esc_html(($wp_user->display_name ?: $wp_user->user_login) . ' (' . $wp_user->user_email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="access_email">Email (for new account)</label></th>
                        <td>
                            <input type="email" id="access_email" name="access_email" class="regular-text" placeholder="employee@example.com" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="access_username">Username (optional)</label></th>
                        <td>
                            <input type="text" id="access_username" name="access_username" class="regular-text" placeholder="Auto-derived if empty" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="access_password">Temporary Password (optional)</label></th>
                        <td>
                            <input type="text" id="access_password" name="access_password" class="regular-text" autocomplete="new-password" placeholder="Auto-generated if empty" />
                        </td>
                    </tr>
                </table>

                <?php submit_button('Grant Access & Assign Role', 'secondary'); ?>
            </form>
        </div>
    </div>
    <?php
}
