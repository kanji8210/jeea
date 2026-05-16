<?php
/**
 * REST API endpoint for JINSING platform access registration.
 * Route: POST /wp-json/jinsing/v1/register
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', 'jinsing_register_rest_routes' );

function jinsing_register_rest_routes() {
    register_rest_route( 'jinsing/v1', '/register', [
        'methods'             => 'POST',
        'callback'            => 'jinsing_handle_registration',
        'permission_callback' => '__return_true',
        'args'                => [
            'fullName' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function( $value ) {
                    return ! empty( trim( $value ) ) && mb_strlen( trim( $value ) ) >= 2;
                },
            ],
            'email' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => 'is_email',
            ],
            'role' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function( $value ) {
                    $allowed = [
                        'Contractor',
                        'Developer',
                        'Project Manager',
                        'Architect / Engineer',
                        'Site Manager',
                        'Systems Team',
                        'Other',
                    ];
                    return in_array( $value, $allowed, true );
                },
            ],
            'phone' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'company' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'heardFrom' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ] );
}

function jinsing_handle_registration( WP_REST_Request $request ) {
    $full_name  = $request->get_param( 'fullName' );
    $email      = $request->get_param( 'email' );
    $role       = $request->get_param( 'role' );
    $phone      = $request->get_param( 'phone' ) ?? '';
    $company    = $request->get_param( 'company' ) ?? '';
    $heard_from = $request->get_param( 'heardFrom' ) ?? '';

    // Reject if email already registered.
    if ( email_exists( $email ) ) {
        return new WP_Error(
            'jinsing_email_exists',
            'An account with this email address already exists. Please use the login link to access the platform.',
            [ 'status' => 409 ]
        );
    }

    // Generate a secure random password.
    $password = wp_generate_password( 16, false );

    // Derive a username from email prefix, ensure uniqueness.
    $username_base = sanitize_user( strstr( $email, '@', true ), true );
    $username      = $username_base;
    $counter       = 1;
    while ( username_exists( $username ) ) {
        $username = $username_base . '_' . $counter;
        $counter++;
    }

    $user_id = wp_create_user( $username, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        return new WP_Error(
            'jinsing_create_failed',
            'Account creation failed. Please try again or contact the platform team.',
            [ 'status' => 500 ]
        );
    }

    // Set display name and additional meta.
    wp_update_user( [
        'ID'           => $user_id,
        'display_name' => $full_name,
        'first_name'   => strstr( $full_name, ' ', true ) ?: $full_name,
        'last_name'    => strstr( $full_name, ' ' ) ? ltrim( strstr( $full_name, ' ' ) ) : '',
    ] );

    update_user_meta( $user_id, 'jinsing_role',       $role );
    update_user_meta( $user_id, 'jinsing_phone',      $phone );
    update_user_meta( $user_id, 'jinsing_company',    $company );
    update_user_meta( $user_id, 'jinsing_heard_from', $heard_from );
    update_user_meta( $user_id, 'jinsing_registered', current_time( 'mysql' ) );

    // Assign WordPress role.
    $user = new WP_User( $user_id );
    $user->set_role( 'subscriber' );

    // Send welcome email.
    jinsing_send_welcome_email( $email, $full_name, $username, $password, $role );

    return new WP_REST_Response( [
        'success' => true,
        'message' => 'Access provisioned. Check your email for onboarding instructions and login credentials.',
        'user_id' => $user_id,
    ], 201 );
}

function jinsing_send_welcome_email( $email, $full_name, $username, $password, $role ) {
    $site_name = get_bloginfo( 'name' );
    $login_url = wp_login_url();
    $docs_url  = home_url( '/' );

    $subject = "Your Jinsing Platform Access - {$site_name}";

    $body  = "Hello {$full_name},\n\n";
    $body .= "Your JINSING platform account has been provisioned.\n\n";
    $body .= "--- Your Credentials ---\n";
    $body .= "Username: {$username}\n";
    $body .= "Password: {$password}\n";
    $body .= "Role:     {$role}\n\n";
    $body .= "--- Next Steps ---\n";
    $body .= "1. Log in at: {$login_url}\n";
    $body .= "2. Browse the documentation at: {$docs_url}\n";
    $body .= "3. Explore the demo environment from the dashboard.\n\n";
    $body .= "Please change your password after your first login.\n\n";
    $body .= "If you have questions, reply to this email or contact the platform team.\n\n";
    $body .= "- Jinsing Platform Team\n";

    $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

    wp_mail( $email, $subject, $body, $headers );

    // Notify platform admin.
    $admin_email = get_option( 'admin_email' );
    if ( $admin_email ) {
        $admin_subject = "New JINSING platform registration: {$full_name} ({$role})";
        $admin_body    = "A new user registered on the JINSING platform.\n\n";
        $admin_body   .= "Name:     {$full_name}\n";
        $admin_body   .= "Email:    {$email}\n";
        $admin_body   .= "Username: {$username}\n";
        $admin_body   .= "Role:     {$role}\n";
        $admin_body   .= "Time:     " . current_time( 'mysql' ) . "\n";
        wp_mail( $admin_email, $admin_subject, $admin_body, $headers );
    }
}
