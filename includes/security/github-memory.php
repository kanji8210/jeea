<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_is_github_memory_enabled() {
    return (int) get_option('construction_mgmt_github_memory_enabled', 0) === 1;
}

function construction_mgmt_github_memory_repo_parts() {
    $repo = trim((string) get_option('construction_mgmt_github_memory_repo', ''));

    if ($repo === '' || strpos($repo, '/') === false) {
        return null;
    }

    $parts = array_map('trim', explode('/', $repo, 2));
    if (empty($parts[0]) || empty($parts[1])) {
        return null;
    }

    return [
        'owner' => $parts[0],
        'repo' => $parts[1],
    ];
}

function construction_mgmt_github_memory_create_entry($title, $content, $labels = ['construction-memory']) {
    if (!construction_mgmt_is_github_memory_enabled()) {
        return new WP_Error('github_memory_disabled', 'GitHub memory is disabled.');
    }

    $repo = construction_mgmt_github_memory_repo_parts();
    if (!$repo) {
        return new WP_Error('github_memory_invalid_repo', 'GitHub repository must be set as owner/repo.');
    }

    $token = trim((string) get_option('construction_mgmt_github_memory_token', ''));
    if ($token === '') {
        return new WP_Error('github_memory_missing_token', 'GitHub token is missing.');
    }

    $endpoint = sprintf(
        'https://api.github.com/repos/%s/%s/issues',
        rawurlencode($repo['owner']),
        rawurlencode($repo['repo'])
    );

    $response = wp_remote_post($endpoint, [
        'timeout' => 15,
        'headers' => [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'construction-mgmt-plugin',
            'X-GitHub-Api-Version' => '2022-11-28',
        ],
        'body' => wp_json_encode([
            'title' => sanitize_text_field($title),
            'body' => wp_kses_post($content),
            'labels' => array_map('sanitize_text_field', $labels),
        ]),
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code < 200 || $status_code >= 300) {
        return new WP_Error(
            'github_memory_api_error',
            isset($body['message']) ? $body['message'] : 'Unable to create GitHub memory entry.',
            ['status' => $status_code]
        );
    }

    return [
        'id' => isset($body['id']) ? (int) $body['id'] : null,
        'number' => isset($body['number']) ? (int) $body['number'] : null,
        'url' => isset($body['html_url']) ? esc_url_raw($body['html_url']) : null,
    ];
}
