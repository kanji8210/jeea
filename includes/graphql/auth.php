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

function construction_mgmt_extract_jwt_user_id($decoded) {
    if (is_object($decoded)) {
        if (isset($decoded->user_id) && (int) $decoded->user_id > 0) {
            return (int) $decoded->user_id;
        }

        if (isset($decoded->sub) && is_numeric($decoded->sub) && (int) $decoded->sub > 0) {
            return (int) $decoded->sub;
        }

        if (isset($decoded->data) && is_object($decoded->data)) {
            if (isset($decoded->data->user) && is_object($decoded->data->user)) {
                if (isset($decoded->data->user->id) && (int) $decoded->data->user->id > 0) {
                    return (int) $decoded->data->user->id;
                }
            }
        }
    }

    return 0;
}

function construction_mgmt_apply_graphql_jwt_auth() {
    static $already_processed = false;
    if ($already_processed) {
        return;
    }

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
        $user_id = construction_mgmt_extract_jwt_user_id($decoded);
        if ($user_id > 0) {
            wp_set_current_user($user_id);
            $already_processed = true;
        }
    } catch (Exception $e) {
        // Invalid token; leave request unauthenticated.
    }
}

add_action('init', 'construction_mgmt_apply_graphql_jwt_auth', 1);
add_action('graphql_init', 'construction_mgmt_apply_graphql_jwt_auth', 1);