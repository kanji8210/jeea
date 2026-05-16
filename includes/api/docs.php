<?php

if (!defined('ABSPATH')) {
    exit;
}

// ── Docs REST endpoints ────────────────────────────────────────────────────────

add_action('rest_api_init', 'jinsing_docs_endpoints');

function jinsing_docs_endpoints() {
    // GET /wp-json/jinsing/v1/docs/articles
    register_rest_route('jinsing/v1', '/docs/articles', [
        'methods'             => 'GET',
        'callback'            => 'jinsing_get_articles',
        'permission_callback' => 'jinsing_docs_permission_check',
        'args'                => [
            'page'     => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'category' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'type'     => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'enum'              => ['guide', 'api', 'tutorial', 'faq', 'changelog'],
            ],
        ],
    ]);

    // GET /wp-json/jinsing/v1/docs/articles/<slug>
    register_rest_route('jinsing/v1', '/docs/articles/(?P<slug>[a-z0-9_-]+)', [
        'methods'             => 'GET',
        'callback'            => 'jinsing_get_article',
        'permission_callback' => 'jinsing_docs_permission_check',
    ]);

    // GET /wp-json/jinsing/v1/docs/search?q=term
    register_rest_route('jinsing/v1', '/docs/search', [
        'methods'             => 'GET',
        'callback'            => 'jinsing_search_articles',
        'permission_callback' => '__return_true',
        'args'                => [
            'q' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);

    // POST /wp-json/jinsing/v1/docs/<id>/feedback
    register_rest_route('jinsing/v1', '/docs/(?P<id>\d+)/feedback', [
        'methods'             => 'POST',
        'callback'            => 'jinsing_article_feedback',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [
            'helpful' => ['type' => 'boolean', 'required' => true],
        ],
    ]);
}

function jinsing_docs_permission_check() {
    // Open to all by default; swap for is_user_logged_in() to restrict.
    return true;
}

// ── GET /docs/articles ────────────────────────────────────────────────────────

function jinsing_get_articles(WP_REST_Request $request) {
    global $wpdb;

    $page     = max(1, (int) $request->get_param('page'));
    $per_page = min(100, max(1, (int) $request->get_param('per_page')));
    $category = $request->get_param('category');
    $doc_type = $request->get_param('type');
    $offset   = ($page - 1) * $per_page;

    // Build WHERE clause safely -- collect placeholders + args separately.
    $sql  = "SELECT id, title, slug, excerpt, doc_type, category,
                    view_count, helpful_count, not_helpful_count, published_at
             FROM {$wpdb->prefix}jinsing_docs_articles
             WHERE status = 'published'";
    $args = [];

    if (!empty($category)) {
        $sql   .= ' AND category = %s';
        $args[] = $category;
    }

    if (!empty($doc_type)) {
        $sql   .= ' AND doc_type = %s';
        $args[] = $doc_type;
    }

    $sql   .= ' ORDER BY published_at DESC LIMIT %d OFFSET %d';
    $args[] = $per_page;
    $args[] = $offset;

    $articles = $wpdb->get_results($wpdb->prepare($sql, $args)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    return rest_ensure_response($articles);
}

// ── GET /docs/articles/<slug> ─────────────────────────────────────────────────

function jinsing_get_article(WP_REST_Request $request) {
    global $wpdb;

    $slug = sanitize_title($request['slug']);

    $article = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}jinsing_docs_articles
         WHERE slug = %s AND status = 'published'",
        $slug
    ));

    if (!$article) {
        return new WP_Error('not_found', 'Article not found', ['status' => 404]);
    }

    // Increment view count.
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}jinsing_docs_articles
         SET view_count = view_count + 1
         WHERE id = %d",
        $article->id
    ));

    // Log access if user is logged in.
    if (is_user_logged_in()) {
        $wpdb->insert(
            $wpdb->prefix . 'jinsing_docs_access',
            [
                'user_id'    => get_current_user_id(),
                'article_id' => (int) $article->id,
                'access_type' => 'read',
            ],
            ['%d', '%d', '%s']
        );
    }

    return rest_ensure_response($article);
}

// ── GET /docs/search?q= ───────────────────────────────────────────────────────

function jinsing_search_articles(WP_REST_Request $request) {
    global $wpdb;

    $term = sanitize_text_field($request->get_param('q'));

    if (mb_strlen($term) < 3) {
        return new WP_Error(
            'term_too_short',
            'Search term must be at least 3 characters',
            ['status' => 400]
        );
    }

    $like = '%' . $wpdb->esc_like($term) . '%';

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title, slug, excerpt, doc_type, category
         FROM {$wpdb->prefix}jinsing_docs_articles
         WHERE status = 'published'
           AND (title LIKE %s OR content LIKE %s)
         ORDER BY
             CASE WHEN title LIKE %s THEN 1 ELSE 2 END
         LIMIT 20",
        $like,
        $like,
        $like
    ));

    // Log search (authenticated users only to avoid bot noise).
    if (is_user_logged_in()) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $wpdb->insert(
            $wpdb->prefix . 'jinsing_docs_search',
            [
                'user_id'       => get_current_user_id(),
                'search_term'   => $term,
                'results_count' => count($results),
                'ip_address'    => $ip,
            ],
            ['%d', '%s', '%d', '%s']
        );
    }

    return rest_ensure_response($results);
}

// ── POST /docs/<id>/feedback ──────────────────────────────────────────────────

function jinsing_article_feedback(WP_REST_Request $request) {
    global $wpdb;

    $article_id = absint($request['id']);
    $helpful    = (bool) $request->get_param('helpful');

    $column = $helpful ? 'helpful_count' : 'not_helpful_count';

    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}jinsing_docs_articles
         SET {$column} = {$column} + 1
         WHERE id = %d AND status = 'published'",
        $article_id
    ));

    if (!$updated) {
        return new WP_Error('not_found', 'Article not found', ['status' => 404]);
    }

    return rest_ensure_response(['success' => true]);
}
