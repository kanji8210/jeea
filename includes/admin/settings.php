<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'construction_mgmt_admin_menu');

function construction_mgmt_admin_menu() {
    add_menu_page(
        'Construction Management',
        'JINSING',
        'manage_options',
        'construction-mgmt-settings',
        'construction_mgmt_settings_page',
        'dashicons-hammer',
        56
    );

    add_submenu_page(
        'construction-mgmt-settings',
        'Settings',
        'Settings',
        'manage_options',
        'construction-mgmt-settings',
        'construction_mgmt_settings_page'
    );

    add_submenu_page(
        'construction-mgmt-settings',
        'Command Center',
        'Command Center',
        'manage_construction_command_center',
        'construction-mgmt-command-center',
        'construction_mgmt_command_center_page'
    );

    add_submenu_page(
        'construction-mgmt-settings',
        'Roles Manager',
        'Roles Manager',
        'manage_options',
        'construction-mgmt-roles-manager',
        'construction_mgmt_roles_manager_page'
    );

    add_submenu_page(
        'construction-mgmt-settings',
        'DB Tools',
        'DB Tools',
        'manage_construction_db_tools',
        'construction-mgmt-db-tools',
        'construction_mgmt_db_tools_page'
    );

    add_submenu_page(
        'construction-mgmt-settings',
        'Create Project',
        'Create Project',
        'manage_construction_projects',
        'construction-mgmt-create-project',
        'construction_mgmt_create_project_page'
    );

    add_submenu_page(
        'construction-mgmt-settings',
        'Manage Projects',
        'Manage Projects',
        'manage_construction_projects',
        'construction-mgmt-project-management',
        'construction_mgmt_project_management_page'
    );
}

function construction_mgmt_settings_page() {
    if (isset($_POST['submit'])) {
        check_admin_referer('construction_mgmt_settings');
        update_option('construction_mgmt_rate_limit', intval($_POST['rate_limit']));
        update_option('construction_mgmt_jwt_secret', sanitize_text_field($_POST['jwt_secret']));
        update_option('construction_mgmt_github_memory_enabled', isset($_POST['github_memory_enabled']) ? 1 : 0);
        update_option('construction_mgmt_github_memory_repo', sanitize_text_field($_POST['github_memory_repo']));

        // Keep token optional and avoid accidental clearing when left blank.
        if (isset($_POST['github_memory_token']) && $_POST['github_memory_token'] !== '') {
            update_option('construction_mgmt_github_memory_token', sanitize_text_field($_POST['github_memory_token']));
        }

        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    $rate_limit = get_option('construction_mgmt_rate_limit', 100);
    $jwt_secret = get_option('construction_mgmt_jwt_secret', '');
    $github_memory_enabled = (int) get_option('construction_mgmt_github_memory_enabled', 0);
    $github_memory_repo = get_option('construction_mgmt_github_memory_repo', '');
    ?>
    <div class="wrap">
        <h1>Construction Management Platform - Settings</h1>
        <form method="post">
            <?php wp_nonce_field('construction_mgmt_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="rate_limit">Rate Limit (requests per hour per user)</label></th>
                    <td><input type="number" name="rate_limit" id="rate_limit" value="<?php echo esc_attr($rate_limit); ?>" class="small-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="jwt_secret">JWT Secret Key</label></th>
                    <td><input type="text" name="jwt_secret" id="jwt_secret" value="<?php echo esc_attr($jwt_secret); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row">GitHub Memory</th>
                    <td>
                        <label>
                            <input type="checkbox" name="github_memory_enabled" value="1" <?php checked($github_memory_enabled, 1); ?> />
                            Enable GitHub memory sync for audit logs
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="github_memory_repo">GitHub Repository</label></th>
                    <td>
                        <input type="text" name="github_memory_repo" id="github_memory_repo" value="<?php echo esc_attr($github_memory_repo); ?>" class="regular-text" placeholder="owner/repo" />
                        <p class="description">Example: kanji8210/jeea</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="github_memory_token">GitHub Token</label></th>
                    <td>
                        <input type="password" name="github_memory_token" id="github_memory_token" value="" class="regular-text" autocomplete="new-password" />
                        <p class="description">Leave blank to keep the current token unchanged.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
