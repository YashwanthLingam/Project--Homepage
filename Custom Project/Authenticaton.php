<?php
/*
Plugin Name: Custom Auth Plugin
*/

// Create custom tables on plugin activation
register_activation_hook( __FILE__, 'custom_auth_plugin_install' );

function custom_auth_plugin_install() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'user_tokens';

    $sql = "CREATE TABLE $table_name (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      user_id bigint(20) NOT NULL,
      token varchar(255) NOT NULL,
      expires datetime NOT NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY user_id (user_id)
    ) $charset_collate;"

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// Endpoint for user login
add_action('rest_api_init', function () {
    register_rest_route('custom-auth-plugin/v1', '/login', array(
        'methods' => 'POST',
        'callback' => 'custom_auth_plugin_login',
    ));
});

function custom_auth_plugin_login($request) {
    $parameters = $request->get_json_params();

    if (!isset($parameters['username']) || !isset($parameters['password'])) {
        return new WP_Error('invalid_params', 'Username and password are required.', array('status' => 400));
    }

    $username = sanitize_text_field($parameters['username']);
    $password = sanitize_text_field($parameters['password']);

    global $wpdb;

    // Verify user credentials
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}users WHERE user_login = %s",
        $username
    ));

    if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
        return new WP_Error('invalid_credentials', 'Invalid username or password.', array('status' => 401));
    }

    // Generate token (you can use any secure method here)
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

    // Store token in custom table
    $wpdb->insert($wpdb->prefix . 'user_tokens', array(
        'user_id' => $user->ID,
        'token' => $token,
        'expires' => $expires,
    ));

    return array(
        'token' => $token,
    );
}

// Endpoint for fetching user information
add_action('rest_api_init', function () {
    register_rest_route('custom-auth-plugin/v1', '/user-info', array(
        'methods' => 'GET',
        'callback' => 'custom_auth_plugin_user_info',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));
});

function custom_auth_plugin_user_info($request) {
    $token = $request->get_header('Authorization');

    global $wpdb;

    // Validate token
    $user_token = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}user_tokens WHERE token = %s",
        $token
    ));

    if (!$user_token) {
        return new WP_Error('invalid_token', 'Invalid token.', array('status' => 401));
    }

    // Fetch user information
    $user = get_userdata($user_token->user_id);

    if (!$user) {
        return new WP_Error('user_not_found', 'User not found.', array('status' => 404));
    }

    return array(
        'username' => $user->user_login,
        'email' => $user->user_email,
    );
}
