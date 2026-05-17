<?php
/**
 * REST API auth endpoints for JINSING frontend login.
 * Route: POST /wp-json/jinsing/v1/auth/login
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Firebase\JWT\JWT;

add_action( 'rest_api_init', 'jinsing_register_auth_routes' );

function jinsing_register_auth_routes() {
    register_rest_route( 'jinsing/v1', '/auth/login', [
        'methods'             => 'POST',
        'callback'            => 'jinsing_handle_auth_login',
        'permission_callback' => '__return_true',
        'args'                => [
            'username' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_user',
            ],
            'password' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => static function( $value ) {
                    return is_string( $value ) ? trim( $value ) : '';
                },
            ],
        ],
    ] );
}

function jinsing_handle_auth_login( WP_REST_Request $request ) {
    if ( ! class_exists( '\\Firebase\\JWT\\JWT' ) ) {
        return new WP_Error(
            'auth_unavailable',
            'Authentication service is unavailable. Contact an administrator.',
            [ 'status' => 500 ]
        );
    }

    $username = (string) $request->get_param( 'username' );
    $password = (string) $request->get_param( 'password' );

    if ( $username === '' || $password === '' ) {
        return new WP_Error(
            'missing_credentials',
            'Username and password are required.',
            [ 'status' => 400 ]
        );
    }

    $rate_key = jinsing_auth_rate_limit_key( $username );
    $attempts = (int) get_transient( $rate_key );
    if ( $attempts >= 5 ) {
        return new WP_Error(
            'too_many_attempts',
            'Too many login attempts. Please wait 15 minutes before trying again.',
            [ 'status' => 429 ]
        );
    }

    $user = wp_authenticate( $username, $password );

    if ( is_wp_error( $user ) ) {
        set_transient( $rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );

        return new WP_Error(
            'invalid_credentials',
            'Invalid username or password.',
            [ 'status' => 401 ]
        );
    }

    if ( ! jinsing_user_has_frontend_access( $user ) ) {
        return new WP_Error(
            'insufficient_access',
            'Your account does not have permission to access this platform.',
            [ 'status' => 403 ]
        );
    }

    delete_transient( $rate_key );

    $secret = jinsing_get_jwt_secret_for_login();
    if ( $secret === '' ) {
        return new WP_Error(
            'jwt_secret_missing',
            'Authentication secret is not configured.',
            [ 'status' => 500 ]
        );
    }

    $now = time();
    $exp = $now + DAY_IN_SECONDS;

    $payload = [
        'iss'     => home_url( '/' ),
        'iat'     => $now,
        'nbf'     => $now - 5,
        'exp'     => $exp,
        'sub'     => (string) $user->ID,
        'user_id' => (int) $user->ID,
        'data'    => [
            'user' => [
                'id'    => (int) $user->ID,
                'login' => (string) $user->user_login,
            ],
        ],
    ];

    try {
        $token = JWT::encode( $payload, $secret, 'HS256' );
    } catch ( Exception $e ) {
        return new WP_Error(
            'token_generation_failed',
            'Could not generate access key.',
            [ 'status' => 500 ]
        );
    }

    return rest_ensure_response( [
        'success'     => true,
        'message'     => 'Login successful.',
        'token'       => $token,
        'expiresIn'   => DAY_IN_SECONDS,
        'expiresAt'   => gmdate( 'c', $exp ),
        'user'        => [
            'id'          => (int) $user->ID,
            'username'    => (string) $user->user_login,
            'displayName' => (string) $user->display_name,
            'roles'       => array_values( (array) $user->roles ),
        ],
        'capabilities' => [
            'readProjects' => user_can( $user, 'read_projects' ),
            'manageProjects' => user_can( $user, 'manage_construction_projects' ),
            'admin' => user_can( $user, 'manage_options' ),
        ],
    ] );
}

function jinsing_get_jwt_secret_for_login() {
    $option_secret = trim( (string) get_option( 'construction_mgmt_jwt_secret', '' ) );
    if ( $option_secret !== '' ) {
        return $option_secret;
    }

    if ( defined( 'JWT_AUTH_SECRET_KEY' ) && trim( (string) JWT_AUTH_SECRET_KEY ) !== '' ) {
        return trim( (string) JWT_AUTH_SECRET_KEY );
    }

    if ( defined( 'GRAPHQL_JWT_AUTH_SECRET_KEY' ) && trim( (string) GRAPHQL_JWT_AUTH_SECRET_KEY ) !== '' ) {
        return trim( (string) GRAPHQL_JWT_AUTH_SECRET_KEY );
    }

    if ( defined( 'AUTH_KEY' ) && trim( (string) AUTH_KEY ) !== '' ) {
        return trim( (string) AUTH_KEY );
    }

    return '';
}

function jinsing_user_has_frontend_access( WP_User $user ) {
    return user_can( $user, 'read_projects' )
        || user_can( $user, 'manage_construction_projects' )
        || user_can( $user, 'manage_options' );
}

function jinsing_auth_rate_limit_key( $username ) {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
    return 'jinsing_auth_attempts_' . md5( strtolower( $username ) . '|' . $ip );
}
