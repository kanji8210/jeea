<?php
add_action('graphql_before_execute', function($request) {
    $user_id = get_current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'];
    $operation = $request->query ?: '';
    $limit = get_option('construction_mgmt_rate_limit', 100);
    
    global $wpdb;
    $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}const_rate_limit 
         WHERE (user_id = %d OR ip_address = %s) 
         AND request_time > %s",
        $user_id, $ip, $hour_ago
    ));
    if ($count >= $limit) {
        throw new \GraphQL\Error\UserError('Rate limit exceeded. Try later.');
    }
    // Log this request
    $wpdb->insert($wpdb->prefix . 'const_rate_limit', [
        'ip_address' => $ip,
        'user_id' => $user_id,
        'operation_name' => substr($operation, 0, 100),
        'request_time' => current_time('mysql')
    ]);
});