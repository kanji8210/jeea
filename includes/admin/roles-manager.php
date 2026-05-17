<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_is_core_wp_role($role_key) {
    $core_roles = [
        'administrator',
        'editor',
        'author',
        'contributor',
        'subscriber',
    ];

    return in_array($role_key, $core_roles, true);
}

function construction_mgmt_is_editable_construction_role($role_key) {
    if (strpos($role_key, 'construction_') === 0) {
        return true;
    }

    return false;
}

function construction_mgmt_is_lower_assignable_role($role_key) {
    if (!construction_mgmt_is_editable_construction_role($role_key)) {
        return false;
    }

    // Keep platform owner role restricted from this quick-create flow.
    return $role_key !== 'construction_director';
}

function construction_mgmt_handle_roles_manager_actions() {
    if (!current_user_can('manage_construction_roles') && !current_user_can('manage_options')) {
        return;
    }

    if (empty($_POST['construction_mgmt_roles_action'])) {
        return;
    }

    check_admin_referer('construction_mgmt_roles_action');

    $action = sanitize_text_field(wp_unslash($_POST['construction_mgmt_roles_action']));
    $selected_caps = isset($_POST['role_caps']) && is_array($_POST['role_caps'])
        ? array_map('sanitize_key', wp_unslash($_POST['role_caps']))
        : [];

    $allowed_caps = construction_mgmt_get_role_capabilities();
    $caps_payload = ['read' => true];

    foreach ($selected_caps as $capability) {
        if (in_array($capability, $allowed_caps, true)) {
            $caps_payload[$capability] = true;
        }
    }

    if ($action === 'create') {
        $name_raw = isset($_POST['new_role_name']) ? sanitize_text_field(wp_unslash($_POST['new_role_name'])) : '';

        if ($name_raw === '') {
            add_settings_error('construction_mgmt_roles', 'create_role_name_missing', 'Role name is required.', 'error');
            return;
        }

        $base_slug = sanitize_key($name_raw);
        if ($base_slug === '') {
            add_settings_error('construction_mgmt_roles', 'create_role_slug_invalid', 'Role name produced an invalid role key.', 'error');
            return;
        }

        $role_key = 'construction_custom_' . substr($base_slug, 0, 40);
        if (get_role($role_key)) {
            add_settings_error('construction_mgmt_roles', 'create_role_exists', 'A role with this name already exists.', 'error');
            return;
        }

        add_role($role_key, $name_raw, $caps_payload);
        add_settings_error('construction_mgmt_roles', 'create_role_success', 'Role created successfully.', 'updated');
        return;
    }

    if ($action === 'update') {
        $role_key = isset($_POST['role_key']) ? sanitize_key(wp_unslash($_POST['role_key'])) : '';
        $role_name = isset($_POST['role_name']) ? sanitize_text_field(wp_unslash($_POST['role_name'])) : '';

        if (!$role_key || $role_name === '') {
            add_settings_error('construction_mgmt_roles', 'update_role_invalid', 'Role key and role name are required.', 'error');
            return;
        }

        if (!construction_mgmt_is_editable_construction_role($role_key)) {
            add_settings_error('construction_mgmt_roles', 'update_role_not_allowed', 'Only construction roles can be edited here.', 'error');
            return;
        }

        remove_role($role_key);
        add_role($role_key, $role_name, $caps_payload);
        add_settings_error('construction_mgmt_roles', 'update_role_success', 'Role updated successfully.', 'updated');
        return;
    }

    if ($action === 'delete') {
        $role_key = isset($_POST['role_key']) ? sanitize_key(wp_unslash($_POST['role_key'])) : '';

        if (!$role_key) {
            add_settings_error('construction_mgmt_roles', 'delete_role_invalid', 'Role key is required.', 'error');
            return;
        }

        if (construction_mgmt_is_core_wp_role($role_key) || !construction_mgmt_is_editable_construction_role($role_key)) {
            add_settings_error('construction_mgmt_roles', 'delete_role_not_allowed', 'This role cannot be deleted from this screen.', 'error');
            return;
        }

        remove_role($role_key);
        add_settings_error('construction_mgmt_roles', 'delete_role_success', 'Role deleted successfully.', 'updated');
        return;
    }

    if ($action === 'assign_user_role') {
        $user_id = isset($_POST['assign_user_id']) ? absint($_POST['assign_user_id']) : 0;
        $target_role = isset($_POST['assign_role_key']) ? sanitize_key(wp_unslash($_POST['assign_role_key'])) : '';

        if ($user_id <= 0 || $target_role === '') {
            add_settings_error('construction_mgmt_roles', 'assign_role_invalid', 'User and role are required.', 'error');
            return;
        }

        if (!construction_mgmt_is_editable_construction_role($target_role) || !get_role($target_role)) {
            add_settings_error('construction_mgmt_roles', 'assign_role_not_allowed', 'Selected role is invalid for assignment.', 'error');
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            add_settings_error('construction_mgmt_roles', 'assign_role_user_missing', 'Selected user was not found.', 'error');
            return;
        }

        // Replace only existing construction roles, keep non-construction roles intact.
        foreach ((array) $user->roles as $existing_role) {
            if (construction_mgmt_is_editable_construction_role($existing_role)) {
                $user->remove_role($existing_role);
            }
        }

        $user->add_role($target_role);
        add_settings_error('construction_mgmt_roles', 'assign_role_success', 'Construction role assigned successfully.', 'updated');
        return;
    }

    if ($action === 'create_lower_user') {
        $display_name = isset($_POST['new_user_display_name']) ? sanitize_text_field(wp_unslash($_POST['new_user_display_name'])) : '';
        $email = isset($_POST['new_user_email']) ? sanitize_email(wp_unslash($_POST['new_user_email'])) : '';
        $username = isset($_POST['new_user_username']) ? sanitize_user(wp_unslash($_POST['new_user_username']), true) : '';
        $password_raw = isset($_POST['new_user_password']) ? (string) wp_unslash($_POST['new_user_password']) : '';
        $target_role = isset($_POST['new_user_role']) ? sanitize_key(wp_unslash($_POST['new_user_role'])) : '';

        if ($display_name === '' || $email === '' || $target_role === '') {
            add_settings_error('construction_mgmt_roles', 'create_user_required', 'Display name, email, and role are required.', 'error');
            return;
        }

        if (!is_email($email)) {
            add_settings_error('construction_mgmt_roles', 'create_user_email_invalid', 'Please provide a valid email address.', 'error');
            return;
        }

        if (!construction_mgmt_is_lower_assignable_role($target_role) || !get_role($target_role)) {
            add_settings_error('construction_mgmt_roles', 'create_user_role_invalid', 'Selected role is not allowed.', 'error');
            return;
        }

        if ($username === '') {
            $username = sanitize_user(strstr($email, '@', true), true);
        }

        if ($username === '') {
            add_settings_error('construction_mgmt_roles', 'create_user_username_invalid', 'Could not derive a valid username from the provided details.', 'error');
            return;
        }

        if (email_exists($email)) {
            add_settings_error('construction_mgmt_roles', 'create_user_email_exists', 'A user with this email already exists.', 'error');
            return;
        }

        $base_username = $username;
        $suffix = 1;
        while (username_exists($username)) {
            $username = $base_username . '_' . $suffix;
            $suffix++;
        }

        $password = trim($password_raw) !== '' ? $password_raw : wp_generate_password(16, true, false);
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            add_settings_error('construction_mgmt_roles', 'create_user_failed', 'User creation failed. Please try again.', 'error');
            return;
        }

        wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name,
            'first_name' => strstr($display_name, ' ', true) ?: $display_name,
            'last_name' => strstr($display_name, ' ') ? ltrim((string) strstr($display_name, ' ')) : '',
        ]);

        $user = get_user_by('id', $user_id);
        if ($user) {
            foreach ((array) $user->roles as $existing_role) {
                if (construction_mgmt_is_editable_construction_role($existing_role)) {
                    $user->remove_role($existing_role);
                }
            }
            $user->add_role($target_role);
        }

        add_settings_error(
            'construction_mgmt_roles',
            'create_user_success',
            'User created and role assigned successfully. Username: ' . esc_html($username),
            'updated'
        );
    }
}

function construction_mgmt_roles_manager_page() {
    if (!current_user_can('manage_construction_roles') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to manage construction roles.');
    }

    construction_mgmt_handle_roles_manager_actions();

    if (!function_exists('wp_roles')) {
        require_once ABSPATH . 'wp-includes/capabilities.php';
    }

    $editable_roles = [];
    foreach (wp_roles()->roles as $role_key => $role_data) {
        if (construction_mgmt_is_editable_construction_role($role_key)) {
            $editable_roles[$role_key] = $role_data;
        }
    }

    $available_caps = construction_mgmt_get_role_capabilities();
    $users = get_users([
        'orderby' => 'display_name',
        'order' => 'ASC',
    ]);

    $construction_role_keys = array_keys($editable_roles);
    $lower_roles = array_filter(
        $editable_roles,
        static function($role_data, $role_key) {
            return construction_mgmt_is_lower_assignable_role($role_key);
        },
        ARRAY_FILTER_USE_BOTH
    );
    $assigned_users = [];
    if (!empty($construction_role_keys)) {
        $assigned_users = get_users([
            'role__in' => $construction_role_keys,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
    }

    settings_errors('construction_mgmt_roles');
    ?>
    <div class="wrap">
        <h1>JINSING Roles Manager</h1>
        <p>Create and edit construction-specific roles from the admin menu.</p>

        <h2>Create Role</h2>
        <form method="post" style="max-width: 900px; margin-bottom: 28px;">
            <?php wp_nonce_field('construction_mgmt_roles_action'); ?>
            <input type="hidden" name="construction_mgmt_roles_action" value="create" />
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="new_role_name">Role Name</label></th>
                    <td><input type="text" name="new_role_name" id="new_role_name" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row">Capabilities</th>
                    <td>
                        <?php foreach ($available_caps as $capability) : ?>
                            <label style="display: inline-block; margin-right: 14px; margin-bottom: 8px;">
                                <input type="checkbox" name="role_caps[]" value="<?php echo esc_attr($capability); ?>" />
                                <?php echo esc_html($capability); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button('Create Role', 'primary', 'submit', false); ?>
        </form>

        <h2>Create User (Lower Access)</h2>
        <form method="post" style="max-width: 900px; margin-bottom: 28px;">
            <?php wp_nonce_field('construction_mgmt_roles_action'); ?>
            <input type="hidden" name="construction_mgmt_roles_action" value="create_lower_user" />
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="new_user_display_name">Display Name</label></th>
                    <td><input type="text" name="new_user_display_name" id="new_user_display_name" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="new_user_email">Email</label></th>
                    <td><input type="email" name="new_user_email" id="new_user_email" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="new_user_username">Username (optional)</label></th>
                    <td>
                        <input type="text" name="new_user_username" id="new_user_username" class="regular-text" />
                        <p class="description">Leave blank to auto-generate from email.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="new_user_password">Password (optional)</label></th>
                    <td>
                        <input type="text" name="new_user_password" id="new_user_password" class="regular-text" autocomplete="new-password" />
                        <p class="description">Leave blank to auto-generate a secure password.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="new_user_role">Construction Role</label></th>
                    <td>
                        <select name="new_user_role" id="new_user_role" required>
                            <option value="">Select role</option>
                            <?php foreach ($lower_roles as $role_key => $role_data) : ?>
                                <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_data['name']); ?> (<?php echo esc_html($role_key); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Create User', 'secondary', 'submit', false); ?>
        </form>

        <h2>Assign Role To User</h2>
        <form method="post" style="max-width: 900px; margin-bottom: 28px;">
            <?php wp_nonce_field('construction_mgmt_roles_action'); ?>
            <input type="hidden" name="construction_mgmt_roles_action" value="assign_user_role" />
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="construction_mgmt_user_filter">Quick User Search</label></th>
                    <td>
                        <input
                            type="text"
                            id="construction_mgmt_user_filter"
                            class="regular-text"
                            placeholder="Type name or email to filter users"
                        />
                        <p class="description">Filters both the assignment dropdown and assignment list below.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="assign_user_id">User</label></th>
                    <td>
                        <select name="assign_user_id" id="assign_user_id" required>
                            <option value="">Select user</option>
                            <?php foreach ($users as $user) : ?>
                                <option
                                    value="<?php echo esc_attr((string) $user->ID); ?>"
                                    data-filter-text="<?php echo esc_attr(strtolower($user->display_name . ' ' . $user->user_email)); ?>"
                                >
                                    <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="assign_role_key">Construction Role</label></th>
                    <td>
                        <select name="assign_role_key" id="assign_role_key" required>
                            <option value="">Select role</option>
                            <?php foreach ($editable_roles as $role_key => $role_data) : ?>
                                <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_data['name']); ?> (<?php echo esc_html($role_key); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Assign Role', 'secondary', 'submit', false); ?>
        </form>

        <h2>Current Construction Role Assignments</h2>
        <?php if (empty($assigned_users)) : ?>
            <p>No users currently have construction roles.</p>
        <?php else : ?>
            <table id="construction_mgmt_assigned_users_table" class="widefat striped" style="max-width: 1000px; margin-bottom: 28px;">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Construction Roles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assigned_users as $user) : ?>
                        <?php
                        $user_construction_roles = [];
                        foreach ((array) $user->roles as $role_key) {
                            if (construction_mgmt_is_editable_construction_role($role_key) && isset($editable_roles[$role_key])) {
                                $user_construction_roles[] = $editable_roles[$role_key]['name'] . ' (' . $role_key . ')';
                            }
                        }

                        $row_filter_text = strtolower($user->display_name . ' ' . $user->user_email . ' ' . implode(' ', $user_construction_roles));
                        ?>
                        <tr data-filter-text="<?php echo esc_attr($row_filter_text); ?>">
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(empty($user_construction_roles) ? '-' : implode(', ', $user_construction_roles)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <script>
            (function () {
                var filterInput = document.getElementById('construction_mgmt_user_filter');
                var userSelect = document.getElementById('assign_user_id');
                var table = document.getElementById('construction_mgmt_assigned_users_table');

                if (!filterInput) {
                    return;
                }

                var filterUsers = function () {
                    var query = (filterInput.value || '').toLowerCase().trim();

                    if (userSelect) {
                        var options = userSelect.querySelectorAll('option[data-filter-text]');
                        options.forEach(function (option) {
                            var haystack = option.getAttribute('data-filter-text') || '';
                            option.hidden = query !== '' && haystack.indexOf(query) === -1;
                        });
                    }

                    if (table) {
                        var rows = table.querySelectorAll('tbody tr[data-filter-text]');
                        rows.forEach(function (row) {
                            var haystack = row.getAttribute('data-filter-text') || '';
                            row.style.display = query !== '' && haystack.indexOf(query) === -1 ? 'none' : '';
                        });
                    }
                };

                filterInput.addEventListener('input', filterUsers);
            })();
        </script>

        <h2>Edit Existing Construction Roles</h2>
        <?php if (empty($editable_roles)) : ?>
            <p>No construction roles found yet.</p>
        <?php else : ?>
            <?php foreach ($editable_roles as $role_key => $role_data) : ?>
                <?php
                $role_caps = isset($role_data['capabilities']) ? array_keys(array_filter($role_data['capabilities'])) : [];
                ?>
                <form method="post" class="postbox" style="padding: 12px 16px; margin-bottom: 16px; max-width: 1000px;">
                    <?php wp_nonce_field('construction_mgmt_roles_action'); ?>
                    <input type="hidden" name="construction_mgmt_roles_action" value="update" />
                    <input type="hidden" name="role_key" value="<?php echo esc_attr($role_key); ?>" />

                    <p style="margin: 0 0 12px;">
                        <strong><?php echo esc_html($role_key); ?></strong>
                    </p>

                    <p>
                        <label>
                            Role Name:
                            <input type="text" name="role_name" value="<?php echo esc_attr($role_data['name']); ?>" class="regular-text" required />
                        </label>
                    </p>

                    <p style="margin: 0 0 8px;"><strong>Capabilities</strong></p>
                    <p>
                        <?php foreach ($available_caps as $capability) : ?>
                            <label style="display: inline-block; margin-right: 14px; margin-bottom: 8px;">
                                <input
                                    type="checkbox"
                                    name="role_caps[]"
                                    value="<?php echo esc_attr($capability); ?>"
                                    <?php checked(in_array($capability, $role_caps, true)); ?>
                                />
                                <?php echo esc_html($capability); ?>
                            </label>
                        <?php endforeach; ?>
                    </p>

                    <p style="margin-top: 12px; display: flex; gap: 8px;">
                        <?php submit_button('Save Changes', 'primary', 'submit', false); ?>
                    </p>
                </form>

                <?php if (strpos($role_key, 'construction_custom_') === 0) : ?>
                    <form method="post" style="margin-top: -8px; margin-bottom: 18px; max-width: 1000px;">
                        <?php wp_nonce_field('construction_mgmt_roles_action'); ?>
                        <input type="hidden" name="construction_mgmt_roles_action" value="delete" />
                        <input type="hidden" name="role_key" value="<?php echo esc_attr($role_key); ?>" />
                        <?php submit_button('Delete Custom Role', 'delete', 'submit', false, ['onclick' => "return confirm('Delete this custom role?');"]); ?>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}
