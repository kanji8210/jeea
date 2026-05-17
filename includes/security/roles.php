<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'construction_mgmt_maybe_register_roles');

function construction_mgmt_get_role_capabilities() {
    return [
        'read_projects',
        'create_projects',
        'edit_projects',
        'delete_projects',
        'read_rfis',
        'create_rfis',
        'edit_rfis',
        'delete_rfis',
        'manage_construction_command_center',
        'manage_construction_projects',
        'manage_construction_settings',
        'manage_construction_db_tools',
        'manage_construction_roles',
    ];
}

function construction_mgmt_register_roles() {
    $all_caps = array_fill_keys(construction_mgmt_get_role_capabilities(), true);

    // Full platform owner role.
    add_role('construction_director', 'Construction Director', array_merge([
        'read' => true,
        'upload_files' => true,
    ], $all_caps));

    // Project managers can run operations, but not plugin settings/db tools.
    add_role('construction_project_manager', 'Construction Project Manager', [
        'read' => true,
        'read_projects' => true,
        'create_projects' => true,
        'edit_projects' => true,
        'read_rfis' => true,
        'create_rfis' => true,
        'edit_rfis' => true,
        'manage_construction_command_center' => true,
        'manage_construction_projects' => true,
    ]);

    // Site engineers can work on RFIs and view project state.
    add_role('construction_site_manager', 'Construction Site Manager', [
        'read' => true,
        'read_projects' => true,
        'edit_projects' => true,
        'read_rfis' => true,
        'create_rfis' => true,
        'edit_rfis' => true,
        'manage_construction_projects' => true,
    ]);

    // Site engineers can work on RFIs and view project state.
    add_role('construction_site_engineer', 'Construction Site Engineer', [
        'read' => true,
        'read_projects' => true,
        'read_rfis' => true,
        'create_rfis' => true,
        'edit_rfis' => true,
    ]);

    // Ensure administrators have all plugin capabilities.
    $admin_role = get_role('administrator');
    if ($admin_role) {
        foreach ($all_caps as $capability => $grant) {
            if ($grant) {
                $admin_role->add_cap($capability, true);
            }
        }
    }

    update_option('construction_mgmt_roles_version', '2');
}

function construction_mgmt_maybe_register_roles() {
    $roles_version = get_option('construction_mgmt_roles_version', '0');
    if ($roles_version !== '2') {
        construction_mgmt_register_roles();
    }
}
