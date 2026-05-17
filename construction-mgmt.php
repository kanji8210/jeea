<?php
/**
 * Plugin Name: Construction Management Platform
 * Description: Custom tables, GraphQL security, business logic for headless construction project tracking.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: construction-mgmt
 */

if (!defined('ABSPATH')) exit;

// Constants
define('CONSTRUCTION_MGMT_VERSION', '1.0.0');
define('CONSTRUCTION_MGMT_PATH', plugin_dir_path(__FILE__));
define('CONSTRUCTION_MGMT_URL', plugin_dir_url(__FILE__));

// Include core modules
require_once CONSTRUCTION_MGMT_PATH . 'includes/admin/settings.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/admin/command-center.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/admin/roles-manager.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/admin/db-tools.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/admin/create-project.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/admin/project-management.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/admin/employees.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/admin/documents.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/tables.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/projects.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/project_create.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/project_metadata.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/project_milestones.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/project_team.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/project_documents.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/project_objectives.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/project_expenditures.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/suppliers.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/workers.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/quote_requests.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/rfis.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/db/costs.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/graphql/auth.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/graphql/permissions.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/graphql/rate-limit.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/graphql/schema.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/security/roles.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/security/validation.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/security/logging.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/security/github-memory.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/integrations/mpesa.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/documents/document-service.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/api/register.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/api/auth.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/api/docs.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/api/auto-entries.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/public/project-cpt.php';

// Activation / deactivation hooks
register_activation_hook(__FILE__, 'construction_mgmt_activate');
register_deactivation_hook(__FILE__, 'construction_mgmt_deactivate');

function construction_mgmt_activate() {
    construction_mgmt_create_tables();
    construction_mgmt_register_roles();
    jinsing_cpt_flush_rewrite();

    // Set default options
    add_option('construction_mgmt_rate_limit', 100);
    add_option('construction_mgmt_jwt_secret', wp_generate_password(64, true, true));
    add_option('construction_mgmt_github_memory_enabled', 0);
    add_option('construction_mgmt_github_memory_repo', '');
    add_option('construction_mgmt_github_memory_token', '');
}

function construction_mgmt_deactivate() {
    // Optional: flush cache, remove scheduled jobs
}