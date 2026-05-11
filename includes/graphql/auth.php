<?php

if (!defined('ABSPATH')) {
    exit;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

add_action('graphql_init', function() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        try {
            $secret = get_option('construction_mgmt_jwt_secret');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            if (isset($decoded->user_id)) {
                wp_set_current_user($decoded->user_id);
            }
        } catch (Exception $e) {
            // Invalid token – do nothing
        }
    }
});