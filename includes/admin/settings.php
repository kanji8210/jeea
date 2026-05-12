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
    $integration_fields = [
        'construction_mgmt_mpesa_enabled' => ['label' => 'Enable M-Pesa (Daraja)', 'type' => 'checkbox'],
        'construction_mgmt_mpesa_environment' => ['label' => 'M-Pesa Environment', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live']],
        'construction_mgmt_mpesa_consumer_key' => ['label' => 'Daraja Consumer Key', 'type' => 'secret'],
        'construction_mgmt_mpesa_consumer_secret' => ['label' => 'Daraja Consumer Secret', 'type' => 'secret'],
        'construction_mgmt_mpesa_shortcode' => ['label' => 'Daraja Shortcode', 'type' => 'text'],
        'construction_mgmt_mpesa_passkey' => ['label' => 'Daraja Passkey', 'type' => 'secret'],

        'construction_mgmt_kra_itax_enabled' => ['label' => 'Enable KRA iTax (Future)', 'type' => 'checkbox'],
        'construction_mgmt_kra_itax_base_url' => ['label' => 'KRA iTax Base URL', 'type' => 'url'],
        'construction_mgmt_kra_itax_client_id' => ['label' => 'KRA iTax Client ID', 'type' => 'text'],
        'construction_mgmt_kra_itax_client_secret' => ['label' => 'KRA iTax Client Secret', 'type' => 'secret'],

        'construction_mgmt_nca_enabled' => ['label' => 'Enable NCA Integration (Future)', 'type' => 'checkbox'],
        'construction_mgmt_nca_base_url' => ['label' => 'NCA Base URL', 'type' => 'url'],
        'construction_mgmt_nca_api_key' => ['label' => 'NCA API Key', 'type' => 'secret'],

        'construction_mgmt_ocr_provider' => ['label' => 'OCR Provider', 'type' => 'select', 'options' => ['none' => 'None', 'google' => 'Google OCR', 'microsoft' => 'Microsoft OCR']],
        'construction_mgmt_ocr_api_key' => ['label' => 'OCR API Key', 'type' => 'secret'],
        'construction_mgmt_ocr_endpoint' => ['label' => 'OCR Endpoint URL', 'type' => 'url'],

        'construction_mgmt_smtp_host' => ['label' => 'SMTP Host', 'type' => 'text'],
        'construction_mgmt_smtp_port' => ['label' => 'SMTP Port', 'type' => 'int'],
        'construction_mgmt_smtp_username' => ['label' => 'SMTP Username', 'type' => 'text'],
        'construction_mgmt_smtp_password' => ['label' => 'SMTP Password', 'type' => 'secret'],
        'construction_mgmt_smtp_from_email' => ['label' => 'SMTP From Email', 'type' => 'email'],
        'construction_mgmt_smtp_from_name' => ['label' => 'SMTP From Name', 'type' => 'text'],

        'construction_mgmt_accounting_provider' => ['label' => 'Accounting Provider', 'type' => 'select', 'options' => ['none' => 'None', 'odoo' => 'Odoo', 'quickbooks' => 'QuickBooks']],
        'construction_mgmt_accounting_base_url' => ['label' => 'Accounting Base URL', 'type' => 'url'],
        'construction_mgmt_accounting_client_id' => ['label' => 'Accounting Client ID', 'type' => 'text'],
        'construction_mgmt_accounting_client_secret' => ['label' => 'Accounting Client Secret', 'type' => 'secret'],

        'construction_mgmt_weather_provider' => ['label' => 'Weather Provider', 'type' => 'select', 'options' => ['none' => 'None', 'openweather' => 'OpenWeather', 'weatherapi' => 'WeatherAPI']],
        'construction_mgmt_weather_api_key' => ['label' => 'Weather API Key', 'type' => 'secret'],
        'construction_mgmt_weather_base_url' => ['label' => 'Weather API Base URL', 'type' => 'url'],
    ];

    if (isset($_POST['submit'])) {
        check_admin_referer('construction_mgmt_settings');
        update_option('construction_mgmt_rate_limit', intval($_POST['rate_limit']));
        update_option('construction_mgmt_jwt_secret', sanitize_text_field($_POST['jwt_secret']));
        update_option('construction_mgmt_github_memory_enabled', isset($_POST['github_memory_enabled']) ? 1 : 0);
        update_option('construction_mgmt_github_memory_repo', sanitize_text_field($_POST['github_memory_repo']));

        // Save document branding settings
        update_option('construction_mgmt_doc_company_name', sanitize_text_field(wp_unslash($_POST['construction_mgmt_doc_company_name'] ?? '')));
        update_option('construction_mgmt_doc_company_address', sanitize_textarea_post(wp_unslash($_POST['construction_mgmt_doc_company_address'] ?? '')));
        update_option('construction_mgmt_doc_company_phone', sanitize_text_field(wp_unslash($_POST['construction_mgmt_doc_company_phone'] ?? '')));
        update_option('construction_mgmt_doc_company_email', sanitize_email(wp_unslash($_POST['construction_mgmt_doc_company_email'] ?? '')));
        update_option('construction_mgmt_doc_company_kra_pin', sanitize_text_field(wp_unslash($_POST['construction_mgmt_doc_company_kra_pin'] ?? '')));
        update_option('construction_mgmt_doc_logo_url', esc_url_raw(wp_unslash($_POST['construction_mgmt_doc_logo_url'] ?? '')));
        update_option('construction_mgmt_doc_footer_text', sanitize_textarea_post(wp_unslash($_POST['construction_mgmt_doc_footer_text'] ?? '')));

        foreach ($integration_fields as $option_key => $meta) {
            $field_type = $meta['type'];

            if ($field_type === 'checkbox') {
                update_option($option_key, isset($_POST[$option_key]) ? 1 : 0);
                continue;
            }

            if ($field_type === 'secret') {
                if (isset($_POST[$option_key]) && $_POST[$option_key] !== '') {
                    update_option($option_key, sanitize_text_field($_POST[$option_key]));
                }
                continue;
            }

            $raw = isset($_POST[$option_key]) ? wp_unslash($_POST[$option_key]) : '';
            if ($field_type === 'url') {
                update_option($option_key, esc_url_raw($raw));
            } elseif ($field_type === 'int') {
                update_option($option_key, intval($raw));
            } elseif ($field_type === 'email') {
                update_option($option_key, sanitize_email($raw));
            } elseif ($field_type === 'select') {
                $selected = sanitize_text_field($raw);
                $allowed = isset($meta['options']) ? array_keys($meta['options']) : [];
                if (!in_array($selected, $allowed, true)) {
                    $selected = !empty($allowed) ? (string) $allowed[0] : '';
                }
                update_option($option_key, $selected);
            } else {
                update_option($option_key, sanitize_text_field($raw));
            }
        }

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
    $mpesa_callback_url = admin_url('admin-post.php?action=construction_mgmt_mpesa_webhook');
    ?>
    <div class="wrap">
        <h1>Construction Management Platform - Settings</h1>
        <form method="post">
            <?php wp_nonce_field('construction_mgmt_settings'); ?>
            <h2>Platform Settings</h2>
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

            <h2>Integrations</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">M-Pesa (Daraja)</th>
                    <td>
                        <label>
                            <input type="checkbox" name="construction_mgmt_mpesa_enabled" value="1" <?php checked((int) get_option('construction_mgmt_mpesa_enabled', 0), 1); ?> />
                            Enable Daraja payments and webhook updates
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_mpesa_environment">M-Pesa Environment</label></th>
                    <td>
                        <?php $mpesa_env = (string) get_option('construction_mgmt_mpesa_environment', 'sandbox'); ?>
                        <select name="construction_mgmt_mpesa_environment" id="construction_mgmt_mpesa_environment">
                            <option value="sandbox" <?php selected($mpesa_env, 'sandbox'); ?>>Sandbox</option>
                            <option value="live" <?php selected($mpesa_env, 'live'); ?>>Live</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_mpesa_consumer_key">Daraja Consumer Key</label></th>
                    <td><input type="password" name="construction_mgmt_mpesa_consumer_key" id="construction_mgmt_mpesa_consumer_key" value="" class="regular-text" autocomplete="new-password" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_mpesa_consumer_secret">Daraja Consumer Secret</label></th>
                    <td><input type="password" name="construction_mgmt_mpesa_consumer_secret" id="construction_mgmt_mpesa_consumer_secret" value="" class="regular-text" autocomplete="new-password" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_mpesa_shortcode">Daraja Shortcode</label></th>
                    <td><input type="text" name="construction_mgmt_mpesa_shortcode" id="construction_mgmt_mpesa_shortcode" value="<?php echo esc_attr((string) get_option('construction_mgmt_mpesa_shortcode', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_mpesa_passkey">Daraja Passkey</label></th>
                    <td>
                        <input type="password" name="construction_mgmt_mpesa_passkey" id="construction_mgmt_mpesa_passkey" value="" class="regular-text" autocomplete="new-password" />
                        <p class="description">Webhook callback URL: <?php echo esc_html($mpesa_callback_url); ?></p>
                    </td>
                </tr>

                <tr><th colspan="2"><h3 style="margin: 12px 0 0;">KRA iTax (Future)</h3></th></tr>
                <tr>
                    <th scope="row">Enable KRA iTax</th>
                    <td><label><input type="checkbox" name="construction_mgmt_kra_itax_enabled" value="1" <?php checked((int) get_option('construction_mgmt_kra_itax_enabled', 0), 1); ?> /> Enable future KRA integration</label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_kra_itax_base_url">KRA Base URL</label></th>
                    <td><input type="url" name="construction_mgmt_kra_itax_base_url" id="construction_mgmt_kra_itax_base_url" value="<?php echo esc_attr((string) get_option('construction_mgmt_kra_itax_base_url', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_kra_itax_client_id">KRA Client ID</label></th>
                    <td><input type="text" name="construction_mgmt_kra_itax_client_id" id="construction_mgmt_kra_itax_client_id" value="<?php echo esc_attr((string) get_option('construction_mgmt_kra_itax_client_id', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_kra_itax_client_secret">KRA Client Secret</label></th>
                    <td><input type="password" name="construction_mgmt_kra_itax_client_secret" id="construction_mgmt_kra_itax_client_secret" value="" class="regular-text" autocomplete="new-password" /></td>
                </tr>

                <tr><th colspan="2"><h3 style="margin: 12px 0 0;">NCA (Future)</h3></th></tr>
                <tr>
                    <th scope="row">Enable NCA</th>
                    <td><label><input type="checkbox" name="construction_mgmt_nca_enabled" value="1" <?php checked((int) get_option('construction_mgmt_nca_enabled', 0), 1); ?> /> Enable future NCA integration</label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_nca_base_url">NCA Base URL</label></th>
                    <td><input type="url" name="construction_mgmt_nca_base_url" id="construction_mgmt_nca_base_url" value="<?php echo esc_attr((string) get_option('construction_mgmt_nca_base_url', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_nca_api_key">NCA API Key</label></th>
                    <td><input type="password" name="construction_mgmt_nca_api_key" id="construction_mgmt_nca_api_key" value="" class="regular-text" autocomplete="new-password" /></td>
                </tr>

                <tr><th colspan="2"><h3 style="margin: 12px 0 0;">OCR</h3></th></tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_ocr_provider">OCR Provider</label></th>
                    <td>
                        <?php $ocr_provider = (string) get_option('construction_mgmt_ocr_provider', 'none'); ?>
                        <select name="construction_mgmt_ocr_provider" id="construction_mgmt_ocr_provider">
                            <option value="none" <?php selected($ocr_provider, 'none'); ?>>None</option>
                            <option value="google" <?php selected($ocr_provider, 'google'); ?>>Google OCR</option>
                            <option value="microsoft" <?php selected($ocr_provider, 'microsoft'); ?>>Microsoft OCR</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_ocr_api_key">OCR API Key</label></th>
                    <td><input type="password" name="construction_mgmt_ocr_api_key" id="construction_mgmt_ocr_api_key" value="" class="regular-text" autocomplete="new-password" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_ocr_endpoint">OCR Endpoint URL</label></th>
                    <td><input type="url" name="construction_mgmt_ocr_endpoint" id="construction_mgmt_ocr_endpoint" value="<?php echo esc_attr((string) get_option('construction_mgmt_ocr_endpoint', '')); ?>" class="regular-text" /></td>
                </tr>

                <tr><th colspan="2"><h3 style="margin: 12px 0 0;">Email (SMTP)</h3></th></tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_smtp_host">SMTP Host</label></th>
                    <td><input type="text" name="construction_mgmt_smtp_host" id="construction_mgmt_smtp_host" value="<?php echo esc_attr((string) get_option('construction_mgmt_smtp_host', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_smtp_port">SMTP Port</label></th>
                    <td><input type="number" name="construction_mgmt_smtp_port" id="construction_mgmt_smtp_port" value="<?php echo esc_attr((string) get_option('construction_mgmt_smtp_port', 587)); ?>" class="small-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_smtp_username">SMTP Username</label></th>
                    <td><input type="text" name="construction_mgmt_smtp_username" id="construction_mgmt_smtp_username" value="<?php echo esc_attr((string) get_option('construction_mgmt_smtp_username', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_smtp_password">SMTP Password</label></th>
                    <td><input type="password" name="construction_mgmt_smtp_password" id="construction_mgmt_smtp_password" value="" class="regular-text" autocomplete="new-password" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_smtp_from_email">SMTP From Email</label></th>
                    <td><input type="email" name="construction_mgmt_smtp_from_email" id="construction_mgmt_smtp_from_email" value="<?php echo esc_attr((string) get_option('construction_mgmt_smtp_from_email', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_smtp_from_name">SMTP From Name</label></th>
                    <td><input type="text" name="construction_mgmt_smtp_from_name" id="construction_mgmt_smtp_from_name" value="<?php echo esc_attr((string) get_option('construction_mgmt_smtp_from_name', 'JINSING')); ?>" class="regular-text" /></td>
                </tr>

                <tr><th colspan="2"><h3 style="margin: 12px 0 0;">Accounting Sync</h3></th></tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_accounting_provider">Accounting Provider</label></th>
                    <td>
                        <?php $acc_provider = (string) get_option('construction_mgmt_accounting_provider', 'none'); ?>
                        <select name="construction_mgmt_accounting_provider" id="construction_mgmt_accounting_provider">
                            <option value="none" <?php selected($acc_provider, 'none'); ?>>None</option>
                            <option value="odoo" <?php selected($acc_provider, 'odoo'); ?>>Odoo</option>
                            <option value="quickbooks" <?php selected($acc_provider, 'quickbooks'); ?>>QuickBooks</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_accounting_base_url">Accounting Base URL</label></th>
                    <td><input type="url" name="construction_mgmt_accounting_base_url" id="construction_mgmt_accounting_base_url" value="<?php echo esc_attr((string) get_option('construction_mgmt_accounting_base_url', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_accounting_client_id">Accounting Client ID</label></th>
                    <td><input type="text" name="construction_mgmt_accounting_client_id" id="construction_mgmt_accounting_client_id" value="<?php echo esc_attr((string) get_option('construction_mgmt_accounting_client_id', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_accounting_client_secret">Accounting Client Secret</label></th>
                    <td><input type="password" name="construction_mgmt_accounting_client_secret" id="construction_mgmt_accounting_client_secret" value="" class="regular-text" autocomplete="new-password" /></td>
                </tr>

                <tr><th colspan="2"><h3 style="margin: 12px 0 0;">Weather API</h3></th></tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_weather_provider">Weather Provider</label></th>
                    <td>
                        <?php $weather_provider = (string) get_option('construction_mgmt_weather_provider', 'none'); ?>
                        <select name="construction_mgmt_weather_provider" id="construction_mgmt_weather_provider">
                            <option value="none" <?php selected($weather_provider, 'none'); ?>>None</option>
                            <option value="openweather" <?php selected($weather_provider, 'openweather'); ?>>OpenWeather</option>
                            <option value="weatherapi" <?php selected($weather_provider, 'weatherapi'); ?>>WeatherAPI</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_weather_api_key">Weather API Key</label></th>
                    <td><input type="password" name="construction_mgmt_weather_api_key" id="construction_mgmt_weather_api_key" value="" class="regular-text" autocomplete="new-password" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_weather_base_url">Weather API Base URL</label></th>
                    <td><input type="url" name="construction_mgmt_weather_base_url" id="construction_mgmt_weather_base_url" value="<?php echo esc_attr((string) get_option('construction_mgmt_weather_base_url', '')); ?>" class="regular-text" /></td>
                </tr>

                <tr><th colspan="2"><h3 style="margin: 12px 0 0;">Document Branding & Templates</h3></th></tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_doc_company_name">Company Name</label></th>
                    <td><input type="text" name="construction_mgmt_doc_company_name" id="construction_mgmt_doc_company_name" value="<?php echo esc_attr((string) get_option('construction_mgmt_doc_company_name', '')); ?>" class="regular-text" placeholder="Used on invoices, receipts, quotes" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_doc_company_address">Company Address</label></th>
                    <td><textarea name="construction_mgmt_doc_company_address" id="construction_mgmt_doc_company_address" class="large-text" rows="3"><?php echo esc_textarea((string) get_option('construction_mgmt_doc_company_address', '')); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_doc_company_phone">Company Phone</label></th>
                    <td><input type="tel" name="construction_mgmt_doc_company_phone" id="construction_mgmt_doc_company_phone" value="<?php echo esc_attr((string) get_option('construction_mgmt_doc_company_phone', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_doc_company_email">Company Email</label></th>
                    <td><input type="email" name="construction_mgmt_doc_company_email" id="construction_mgmt_doc_company_email" value="<?php echo esc_attr((string) get_option('construction_mgmt_doc_company_email', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_doc_company_kra_pin">Company KRA PIN</label></th>
                    <td><input type="text" name="construction_mgmt_doc_company_kra_pin" id="construction_mgmt_doc_company_kra_pin" value="<?php echo esc_attr((string) get_option('construction_mgmt_doc_company_kra_pin', '')); ?>" class="regular-text" placeholder="P00123456789Z" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_doc_logo_url">Company Logo URL</label></th>
                    <td>
                        <input type="url" name="construction_mgmt_doc_logo_url" id="construction_mgmt_doc_logo_url" value="<?php echo esc_attr((string) get_option('construction_mgmt_doc_logo_url', '')); ?>" class="regular-text" placeholder="https://..." />
                        <p class="description">Image will be displayed on invoices, receipts, and quotes. Max height: 60px.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="construction_mgmt_doc_footer_text">Document Footer Text</label></th>
                    <td><textarea name="construction_mgmt_doc_footer_text" id="construction_mgmt_doc_footer_text" class="large-text" rows="3"><?php echo esc_textarea((string) get_option('construction_mgmt_doc_footer_text', '')); ?></textarea></td>
                </tr>
            </table>

            <p class="description">For secret fields (API keys/passwords), leaving the input blank keeps the existing saved value unchanged.</p>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
