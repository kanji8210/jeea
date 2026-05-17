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
        $project_manager_id = intval($_POST['project_manager_id'] ?? 0) ?: get_current_user_id();
        $site_manager_id = intval($_POST['site_manager_id'] ?? 0);
        $create_site_manager_now = !empty($_POST['create_site_manager_now']);

        if (empty($name)) {
            $message = 'Project name is required.';
        } else {
            if ($create_site_manager_now) {
                $site_manager_name = sanitize_text_field($_POST['new_site_manager_name'] ?? '');
                $site_manager_email = sanitize_email($_POST['new_site_manager_email'] ?? '');

                if ($site_manager_name === '' || $site_manager_email === '' || !is_email($site_manager_email)) {
                    $message = 'For new site manager creation, provide a valid name and email.';
                } else {
                    $existing_user_id = email_exists($site_manager_email);
                    if ($existing_user_id) {
                        $site_manager_id = (int) $existing_user_id;
                    } else {
                        $base_username = sanitize_user(strstr($site_manager_email, '@', true), true);
                        if ($base_username === '') {
                            $base_username = sanitize_user(str_replace(' ', '_', strtolower($site_manager_name)), true);
                        }

                        $username = $base_username;
                        $suffix = 1;
                        while (username_exists($username)) {
                            $username = $base_username . '_' . $suffix;
                            $suffix++;
                        }

                        $password = wp_generate_password(16, true, false);
                        $new_user_id = wp_create_user($username, $password, $site_manager_email);
                        if (is_wp_error($new_user_id)) {
                            $message = 'Could not create the site manager user.';
                        } else {
                            wp_update_user([
                                'ID' => $new_user_id,
                                'display_name' => $site_manager_name,
                                'first_name' => strstr($site_manager_name, ' ', true) ?: $site_manager_name,
                                'last_name' => strstr($site_manager_name, ' ') ? ltrim((string) strstr($site_manager_name, ' ')) : '',
                            ]);

                            $new_user = get_user_by('id', $new_user_id);
                            if ($new_user) {
                                foreach ((array) $new_user->roles as $existing_role) {
                                    if (strpos((string) $existing_role, 'construction_') === 0) {
                                        $new_user->remove_role($existing_role);
                                    }
                                }
                                $new_user->add_role('construction_site_manager');
                            }

                            $site_manager_id = (int) $new_user_id;
                        }
                    }
                }
            }

            if (empty($message)) {
            $project_id = construction_mgmt_create_project($name, $description, $budget_total, $start_date, $end_date, get_current_user_id());
            
            if ($project_id) {
                // Create metadata
                $metadata = [
                    'project_owner_id' => intval($_POST['project_owner_id'] ?? 0) ?: get_current_user_id(),
                    'project_manager_id' => $project_manager_id,
                    'client_name' => sanitize_text_field($_POST['client_name'] ?? ''),
                    'location' => sanitize_text_field($_POST['project_location'] ?? ''),
                    'budget_contingency_pct' => floatval($_POST['budget_contingency_pct'] ?? 10.0),
                    'quality_standard' => sanitize_text_field($_POST['quality_standard'] ?? ''),
                    'contract_type' => sanitize_text_field($_POST['contract_type'] ?? 'fixed_price'),
                    'currency' => sanitize_text_field($_POST['currency'] ?? 'USD'),
                ];
                
                construction_mgmt_create_project_metadata($project_id, $metadata);

                if ($project_manager_id > 0) {
                    construction_mgmt_assign_team_member($project_id, $project_manager_id, 'Project Manager', 'Default project manager assignment');
                }

                if ($site_manager_id > 0) {
                    construction_mgmt_assign_team_member($project_id, $site_manager_id, 'Site Manager', 'Initial site manager assignment');
                }
                
                $success = true;
                $message = sprintf('Project created successfully. <a href="%s">View project</a>', esc_url(admin_url('admin.php?page=construction-mgmt-project&id=' . $project_id)));
            } else {
                $message = 'Failed to create project.';
            }
            }
        }
    }

    $wp_users = get_users(['fields' => ['ID', 'user_login', 'display_name']]);
    $site_manager_candidates = get_users([
        'fields' => ['ID', 'user_login', 'display_name', 'user_email'],
        'role__in' => ['construction_site_manager', 'construction_site_engineer', 'construction_project_manager'],
    ]);

    ?>
    <div class="wrap">
        <h1>Create New Project</h1>

        <?php if (!empty($message)) : ?>
            <div class="notice notice-<?php echo $success ? 'success' : 'error'; ?>">
                <p><?php echo wp_kses_post($message); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" style="max-width: 900px;">
            <?php wp_nonce_field('construction_mgmt_create_project', 'construction_mgmt_create_project_nonce'); ?>

            <h2>Core Project Information</h2>
            <table class="form-table">
                <tr>
                    <th><label for="project_name">Project Name *</label></th>
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
                    <th><label for="project_description">Description</label></th>
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
                    <th><label for="project_start_date">Start Date</label></th>
                    <td>
                        <input 
                            type="date" 
                            id="project_start_date" 
                            name="project_start_date" 
                            value="<?php echo isset($_POST['project_start_date']) ? esc_attr($_POST['project_start_date']) : ''; ?>" 
                            class="regular-text"
                        />
                        <p class="description">Optional. You can set this later when kickoff is confirmed.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="project_end_date">End Date</label></th>
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

            <h2>Project Ownership &amp; Team</h2>
            <table class="form-table">
                <tr>
                    <th><label for="project_owner_id">Project Owner</label></th>
                    <td>
                        <select id="project_owner_id" name="project_owner_id" class="regular-text">
                            <option value="">Select owner...</option>
                            <?php foreach ($wp_users as $user) : ?>
                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($_POST['project_owner_id'] ?? 0, $user->ID); ?>>
                                    <?php echo esc_html($user->display_name ?: $user->user_login); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">The executive responsible for project delivery and success.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="project_manager_id">Project Manager</label></th>
                    <td>
                        <select id="project_manager_id" name="project_manager_id" class="regular-text">
                            <option value="">Select manager...</option>
                            <?php foreach ($wp_users as $user) : ?>
                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($_POST['project_manager_id'] ?? 0, $user->ID); ?>>
                                    <?php echo esc_html($user->display_name ?: $user->user_login); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">The day-to-day project lead.</p>
                    </td>
                </tr>
            </table>

            <h2>Budget &amp; Commercial</h2>
            <table class="form-table">
                <tr>
                    <th><label for="project_budget_total">Total Budget</label></th>
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
                        <p class="description">Optional at creation. Keep 0 to start with minimum project information.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="budget_contingency_pct">Contingency %</label></th>
                    <td>
                        <input 
                            type="number" 
                            id="budget_contingency_pct" 
                            name="budget_contingency_pct" 
                            value="<?php echo isset($_POST['budget_contingency_pct']) ? esc_attr($_POST['budget_contingency_pct']) : '10'; ?>" 
                            step="0.01" 
                            min="0" 
                            max="100"
                            class="small-text"
                        />
                        <p class="description">Reserve percentage (typically 10-20%) for unforeseen costs.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="currency">Currency</label></th>
                    <td>
                        <input 
                            type="text" 
                            id="currency" 
                            name="currency" 
                            value="<?php echo isset($_POST['currency']) ? esc_attr($_POST['currency']) : 'USD'; ?>" 
                            maxlength="3"
                            placeholder="USD"
                            class="small-text"
                        />
                        <p class="description">3-letter ISO currency code (USD, EUR, GBP, etc.).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="contract_type">Contract Type</label></th>
                    <td>
                        <select id="contract_type" name="contract_type" class="regular-text">
                            <option value="fixed_price" <?php selected($_POST['contract_type'] ?? '', 'fixed_price'); ?>>Fixed Price</option>
                            <option value="time_materials" <?php selected($_POST['contract_type'] ?? '', 'time_materials'); ?>>Time &amp; Materials</option>
                            <option value="design_build" <?php selected($_POST['contract_type'] ?? '', 'design_build'); ?>>Design &amp; Build</option>
                            <option value="other" <?php selected($_POST['contract_type'] ?? '', 'other'); ?>>Other</option>
                        </select>
                    </td>
                </tr>
            </table>

            <h2>Project Standards &amp; Location</h2>
            <table class="form-table">
                <tr>
                    <th><label for="client_name">Client / Stakeholder Name</label></th>
                    <td>
                        <input 
                            type="text" 
                            id="client_name" 
                            name="client_name" 
                            value="<?php echo isset($_POST['client_name']) ? esc_attr($_POST['client_name']) : ''; ?>" 
                            class="regular-text"
                        />
                    </td>
                </tr>
                <tr>
                    <th><label for="project_location">Project Location / Site</label></th>
                    <td>
                        <input 
                            type="text" 
                            id="project_location" 
                            name="project_location" 
                            value="<?php echo isset($_POST['project_location']) ? esc_attr($_POST['project_location']) : ''; ?>" 
                            class="regular-text"
                            placeholder="City, State or full address"
                        />
                    </td>
                </tr>
                <tr>
                    <th><label for="quality_standard">Quality / Compliance Standard</label></th>
                    <td>
                        <input 
                            type="text" 
                            id="quality_standard" 
                            name="quality_standard" 
                            value="<?php echo isset($_POST['quality_standard']) ? esc_attr($_POST['quality_standard']) : ''; ?>" 
                            class="regular-text"
                            placeholder="e.g., ISO 9001, ADA, LEED, Fire Code"
                        />
                        <p class="description">Required certifications, codes, or quality standards.</p>
                    </td>
                </tr>
            </table>

            <h2>Site Manager (Optional)</h2>
            <table class="form-table">
                <tr>
                    <th><label for="site_manager_id">Assign Existing Site Manager</label></th>
                    <td>
                        <select id="site_manager_id" name="site_manager_id" class="regular-text">
                            <option value="">Assign later...</option>
                            <?php foreach ($site_manager_candidates as $candidate) : ?>
                                <option value="<?php echo esc_attr($candidate->ID); ?>" <?php selected($_POST['site_manager_id'] ?? 0, $candidate->ID); ?>>
                                    <?php echo esc_html(($candidate->display_name ?: $candidate->user_login) . ' (' . $candidate->user_email . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Link an existing site manager now, or leave blank to add later.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Create Site Manager Now</th>
                    <td>
                        <label>
                            <input type="checkbox" name="create_site_manager_now" id="create_site_manager_now" value="1" <?php checked(!empty($_POST['create_site_manager_now'])); ?> />
                            Create a new site manager user on the fly
                        </label>
                        <div id="create_site_manager_fields" style="margin-top:10px; <?php echo !empty($_POST['create_site_manager_now']) ? '' : 'display:none;'; ?>">
                            <p>
                                <label for="new_site_manager_name">Full Name</label><br />
                                <input type="text" id="new_site_manager_name" name="new_site_manager_name" class="regular-text" value="<?php echo isset($_POST['new_site_manager_name']) ? esc_attr($_POST['new_site_manager_name']) : ''; ?>" />
                            </p>
                            <p>
                                <label for="new_site_manager_email">Email</label><br />
                                <input type="email" id="new_site_manager_email" name="new_site_manager_email" class="regular-text" value="<?php echo isset($_POST['new_site_manager_email']) ? esc_attr($_POST['new_site_manager_email']) : ''; ?>" />
                            </p>
                            <p class="description">If email already exists, that user will be linked as site manager.</p>
                        </div>
                    </td>
                </tr>
            </table>

            <?php submit_button('Create Project', 'primary', 'submit', true); ?>
        </form>

        <script>
            (function () {
                var toggle = document.getElementById('create_site_manager_now');
                var fields = document.getElementById('create_site_manager_fields');
                if (!toggle || !fields) {
                    return;
                }
                toggle.addEventListener('change', function () {
                    fields.style.display = toggle.checked ? '' : 'none';
                });
            })();
        </script>
    </div>
    <?php
}

