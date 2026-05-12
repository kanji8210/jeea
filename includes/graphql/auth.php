<?php

if (!defined('ABSPATH')) {
    exit;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$construction_mgmt_graphql_auth_debug = [
    'header_present' => false,
    'token_present' => false,
    'authenticated' => false,
    'error' => '',
    'secret_source' => '',
    'token_alg' => '',
];

function construction_mgmt_get_authorization_header() {
    $headers = [];

    // Try getallheaders() first (most common)
    if (function_exists('getallheaders')) {
        $raw_headers = getallheaders();
        if (is_array($raw_headers)) {
            $headers = $raw_headers;
        }
    }

    // Check all headers for case-insensitive Authorization match
    foreach ($headers as $name => $value) {
        if (strtolower((string) $name) === 'authorization') {
            return (string) $value;
        }
    }

    // Try $_SERVER vars (used when getallheaders() unavailable)
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return (string) $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // Try "Authorization" without HTTP_ prefix (some configs)
    if (!empty($_SERVER['Authorization'])) {
        return (string) $_SERVER['Authorization'];
    }

    // Try GraphQL context if running via WPGraphQL
    if (function_exists('graphql_get_request_data')) {
        $graphql_headers = graphql_get_request_data()['headers'] ?? [];
        if (is_array($graphql_headers)) {
            foreach ($graphql_headers as $name => $value) {
                if (strtolower((string) $name) === 'authorization') {
                    return (string) $value;
                }
            }
        }
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

function construction_mgmt_get_jwt_algorithm($token) {
    $parts = explode('.', (string) $token);
    if (count($parts) < 2) {
        return 'HS256';
    }

    $header_b64 = strtr($parts[0], '-_', '+/');
    $padding = strlen($header_b64) % 4;
    if ($padding > 0) {
        $header_b64 .= str_repeat('=', 4 - $padding);
    }

    $decoded_header = base64_decode($header_b64, true);
    if ($decoded_header === false) {
        return 'HS256';
    }

    $json = json_decode($decoded_header, true);
    if (is_array($json) && !empty($json['alg']) && is_string($json['alg'])) {
        return $json['alg'];
    }

    return 'HS256';
}

function construction_mgmt_get_jwt_secret_candidates() {
    $candidates = [];

    $option_secret = trim((string) get_option('construction_mgmt_jwt_secret', ''));
    if ($option_secret !== '') {
        $candidates['construction_mgmt_jwt_secret'] = $option_secret;
    }

    if (defined('JWT_AUTH_SECRET_KEY') && trim((string) JWT_AUTH_SECRET_KEY) !== '') {
        $candidates['JWT_AUTH_SECRET_KEY'] = trim((string) JWT_AUTH_SECRET_KEY);
    }

    if (defined('GRAPHQL_JWT_AUTH_SECRET_KEY') && trim((string) GRAPHQL_JWT_AUTH_SECRET_KEY) !== '') {
        $candidates['GRAPHQL_JWT_AUTH_SECRET_KEY'] = trim((string) GRAPHQL_JWT_AUTH_SECRET_KEY);
    }

    if (defined('AUTH_KEY') && trim((string) AUTH_KEY) !== '') {
        $candidates['AUTH_KEY'] = trim((string) AUTH_KEY);
    }

    if (defined('SECURE_AUTH_KEY') && trim((string) SECURE_AUTH_KEY) !== '') {
        $candidates['SECURE_AUTH_KEY'] = trim((string) SECURE_AUTH_KEY);
    }

    return $candidates;
}

function construction_mgmt_set_graphql_auth_debug($updates) {
    global $construction_mgmt_graphql_auth_debug;
    if (!is_array($updates)) {
        return;
    }

    foreach ($updates as $key => $value) {
        $construction_mgmt_graphql_auth_debug[$key] = $value;
    }
}

function construction_mgmt_get_graphql_auth_debug() {
    global $construction_mgmt_graphql_auth_debug;
    return is_array($construction_mgmt_graphql_auth_debug) ? $construction_mgmt_graphql_auth_debug : [];
}

function construction_mgmt_apply_graphql_jwt_auth() {
    static $already_processed = false;
    if ($already_processed) {
        return;
    }

    $authorization = construction_mgmt_get_authorization_header();
    if ($authorization === '') {
        construction_mgmt_set_graphql_auth_debug([
            'error' => 'Authorization header missing',
        ]);
        return;
    }

    construction_mgmt_set_graphql_auth_debug([
        'header_present' => true,
    ]);

    $token = preg_replace('/^Bearer\s+/i', '', trim($authorization));
    if ($token === '') {
        construction_mgmt_set_graphql_auth_debug([
            'error' => 'Bearer token missing',
        ]);
        return;
    }

    $token_alg = construction_mgmt_get_jwt_algorithm($token);
    construction_mgmt_set_graphql_auth_debug([
        'token_present' => true,
        'token_alg' => $token_alg,
    ]);

    $secret_candidates = construction_mgmt_get_jwt_secret_candidates();
    if (empty($secret_candidates)) {
        construction_mgmt_set_graphql_auth_debug([
            'error' => 'No JWT secret candidates configured',
        ]);
        return;
    }

    foreach ($secret_candidates as $source => $secret) {
        try {
            $decoded = JWT::decode($token, new Key($secret, $token_alg));
            $user_id = construction_mgmt_extract_jwt_user_id($decoded);
            if ($user_id > 0) {
                wp_set_current_user($user_id);
                construction_mgmt_set_graphql_auth_debug([
                    'authenticated' => true,
                    'secret_source' => $source,
                    'error' => '',
                ]);
                $already_processed = true;
                return;
            }
        } catch (Exception $e) {
            // Try next secret candidate.
        }
    }

    construction_mgmt_set_graphql_auth_debug([
        'error' => 'Token could not be decoded with configured secret candidates',
    ]);
}

function construction_mgmt_apply_cors_headers() {
    if (!function_exists('graphql_get_wp_context')) {
        return;
    }

    $origin = isset($_SERVER['HTTP_ORIGIN']) ? sanitize_url((string) $_SERVER['HTTP_ORIGIN']) : '';
    if ($origin === '') {
        return;
    }

    $allowed_origins = [
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://127.0.0.1:5175',
    ];

    if (in_array($origin, $allowed_origins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, HEAD, PUT, DELETE, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
        header('Access-Control-Max-Age: 86400');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(204);
        exit;
    }
}

add_action('init', 'construction_mgmt_apply_cors_headers', 0);
add_action('init', 'construction_mgmt_apply_graphql_jwt_auth', 1);
add_action('graphql_init', 'construction_mgmt_apply_graphql_jwt_auth', 1);