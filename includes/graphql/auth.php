<?php

if (!defined('ABSPATH')) {
    exit;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function construction_mgmt_get_authorization_header() {
    $headers = [];

    if (function_exists('getallheaders')) {
        $raw_headers = getallheaders();
        if (is_array($raw_headers)) {
            $headers = $raw_headers;
        }
    }

    foreach ($headers as $name => $value) {
        if (strtolower((string) $name) === 'authorization') {
            return (string) $value;
        }
    }

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return (string) $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    return '';
}

add_action('graphql_init', function() {
    $authorization = construction_mgmt_get_authorization_header();
    if ($authorization === '') {
        return;
    }

    $token = preg_replace('/^Bearer\s+/i', '', trim($authorization));
    if ($token === '') {
        return;
    }

    try {
        $secret = trim((string) get_option('construction_mgmt_jwt_secret', ''));
        if ($secret === '') {
            return;
        }

        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        if (isset($decoded->user_id)) {
            wp_set_current_user((int) $decoded->user_id);
        }
    } catch (Exception $e) {
        // Invalid token; leave request unauthenticated.
    }
});