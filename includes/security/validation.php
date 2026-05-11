<?php
function construction_mgmt_sanitize_project($data) {
    return [
        'name' => sanitize_text_field($data['name']),
        'description' => wp_kses_post($data['description']),
        'status' => sanitize_text_field($data['status']),
        'budget_total' => floatval($data['budget_total']),
        'budget_spent' => floatval($data['budget_spent']),
        'start_date' => $data['start_date'] ? sanitize_text_field($data['start_date']) : null,
        'end_date' => $data['end_date'] ? sanitize_text_field($data['end_date']) : null,
        'created_by' => intval($data['created_by'])
    ];
}