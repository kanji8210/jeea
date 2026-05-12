<?php
function construction_mgmt_audit_log($action, $entity_type, $entity_id, $old_value = null, $new_value = null) {
    global $wpdb;

    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $old_payload = is_array($old_value) ? wp_json_encode($old_value) : $old_value;
    $new_payload = is_array($new_value) ? wp_json_encode($new_value) : $new_value;

    $wpdb->insert(construction_mgmt_get_table_name('audit_log'), [
        'user_id' => get_current_user_id(),
        'action' => $action,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'old_value' => $old_payload,
        'new_value' => $new_payload,
        'ip_address' => $ip_address,
    ]);

    if (!function_exists('construction_mgmt_is_github_memory_enabled') || !construction_mgmt_is_github_memory_enabled()) {
        return;
    }

    $title = sprintf('[Audit] %s %s #%d', sanitize_text_field($action), sanitize_text_field($entity_type), (int) $entity_id);
    $content = sprintf(
        "## Construction Audit Memory\n\n- User ID: %d\n- Action: %s\n- Entity: %s\n- Entity ID: %d\n- IP: %s\n\n### Old Value\n\n```json\n%s\n```\n\n### New Value\n\n```json\n%s\n```",
        (int) get_current_user_id(),
        sanitize_text_field($action),
        sanitize_text_field($entity_type),
        (int) $entity_id,
        $ip_address !== '' ? $ip_address : 'n/a',
        is_string($old_payload) ? $old_payload : wp_json_encode($old_payload),
        is_string($new_payload) ? $new_payload : wp_json_encode($new_payload)
    );

    $result = construction_mgmt_github_memory_create_entry($title, $content, ['construction-memory', 'audit-log']);
    if (is_wp_error($result)) {
        error_log('Construction Mgmt GitHub memory sync failed: ' . $result->get_error_message());
    }
}