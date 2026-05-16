<?php
/**
 * Public Project CPT
 *
 * Registers the `jinsing_project` custom post type.
 * Each CPT post is linked 1-to-1 with a row in jinsing_projects via post meta `_jinsing_project_id`.
 * Public single-project page is rendered via the template in this file.
 *
 * Non-budget data shown: feature image, description, location, client name, contract type,
 * project manager, start/end dates, status, milestones/completion tracker, permits & documents.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// 1. Register CPT
// ---------------------------------------------------------------------------

add_action( 'init', 'jinsing_register_project_cpt' );

function jinsing_register_project_cpt() {
    $labels = [
        'name'               => 'Projects',
        'singular_name'      => 'Project',
        'menu_name'          => 'Projects',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Project',
        'edit_item'          => 'Edit Project',
        'new_item'           => 'New Project',
        'view_item'          => 'View Project',
        'search_items'       => 'Search Projects',
        'not_found'          => 'No projects found.',
        'not_found_in_trash' => 'No projects found in Trash.',
    ];

    register_post_type( 'jinsing_project', [
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => 'construction-mgmt',
        'show_in_rest'        => false,   // We handle REST ourselves.
        'query_var'           => true,
        'rewrite'             => [ 'slug' => 'projects', 'with_front' => false ],
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 25,
        'menu_icon'           => 'dashicons-building',
        'supports'            => [ 'title', 'editor', 'thumbnail', 'revisions', 'author' ],
    ] );
}

// ---------------------------------------------------------------------------
// 2. Flush rewrite rules on activation (called from main plugin file)
// ---------------------------------------------------------------------------

function jinsing_cpt_flush_rewrite() {
    jinsing_register_project_cpt();
    flush_rewrite_rules();
}

// ---------------------------------------------------------------------------
// 3. Meta box: link CPT post to jinsing_projects row
// ---------------------------------------------------------------------------

add_action( 'add_meta_boxes', 'jinsing_project_cpt_meta_box' );

function jinsing_project_cpt_meta_box() {
    add_meta_box(
        'jinsing_project_link',
        'Link to Jinsing Project Record',
        'jinsing_project_cpt_meta_box_html',
        'jinsing_project',
        'side',
        'high'
    );
}

function jinsing_project_cpt_meta_box_html( $post ) {
    global $wpdb;
    wp_nonce_field( 'jinsing_save_project_link', 'jinsing_project_link_nonce' );

    $linked_id = (int) get_post_meta( $post->ID, '_jinsing_project_id', true );

    $table    = construction_mgmt_get_primary_table_prefix() . 'projects';
    $projects = $wpdb->get_results(
        "SELECT id, name FROM {$table} ORDER BY name ASC",
        ARRAY_A
    );
    ?>
    <label for="jinsing_project_id" style="display:block;margin-bottom:4px;font-weight:600;">
        Jinsing Project
    </label>
    <select name="jinsing_project_id" id="jinsing_project_id" style="width:100%">
        <option value="">-- Select project --</option>
        <?php foreach ( $projects as $p ) : ?>
            <option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $linked_id, (int) $p['id'] ); ?>>
                <?php echo esc_html( $p['name'] ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description" style="margin-top:6px">
        Links this public page to the project record. Budget data is never shown publicly.
    </p>
    <?php
}

add_action( 'save_post_jinsing_project', 'jinsing_save_project_cpt_meta' );

function jinsing_save_project_cpt_meta( $post_id ) {
    if ( ! isset( $_POST['jinsing_project_link_nonce'] ) ) return;
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jinsing_project_link_nonce'] ) ), 'jinsing_save_project_link' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $project_id = absint( $_POST['jinsing_project_id'] ?? 0 );
    update_post_meta( $post_id, '_jinsing_project_id', $project_id );
}

// ---------------------------------------------------------------------------
// 4. Template override: single-jinsing_project.php
//    Injects our template before WordPress looks in the theme.
// ---------------------------------------------------------------------------

add_filter( 'single_template', 'jinsing_project_cpt_template' );

function jinsing_project_cpt_template( $template ) {
    if ( is_singular( 'jinsing_project' ) ) {
        $plugin_template = CONSTRUCTION_MGMT_PATH . 'includes/public/project-single.php';
        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }
    }
    return $template;
}

add_filter( 'archive_template', 'jinsing_project_archive_template' );

function jinsing_project_archive_template( $template ) {
    if ( is_post_type_archive( 'jinsing_project' ) ) {
        $plugin_template = CONSTRUCTION_MGMT_PATH . 'includes/public/project-archive.php';
        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }
    }
    return $template;
}

// ---------------------------------------------------------------------------
// 5. REST endpoint: GET /jinsing/v1/public/projects
//    and             GET /jinsing/v1/public/projects/<post_id>
//    No authentication required — public data only.
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'jinsing_register_public_project_routes' );

function jinsing_register_public_project_routes() {
    register_rest_route( 'jinsing/v1', '/public/projects', [
        'methods'             => 'GET',
        'callback'            => 'jinsing_rest_public_projects',
        'permission_callback' => '__return_true',
        'args'                => [
            'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
            'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 12 ],
            'status'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );

    register_rest_route( 'jinsing/v1', '/public/projects/(?P<id>[0-9]+)', [
        'methods'             => 'GET',
        'callback'            => 'jinsing_rest_public_project_single',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [ 'type' => 'integer', 'minimum' => 1, 'required' => true ],
        ],
    ] );
}

/**
 * Build the safe public payload for one CPT post.
 * Budget fields (budget_total, budget_spent) are intentionally excluded.
 */
function jinsing_build_public_project_payload( WP_Post $post ) {
    global $wpdb;

    $project_id = (int) get_post_meta( $post->ID, '_jinsing_project_id', true );
    $project    = $project_id ? construction_mgmt_get_project( $project_id ) : null;
    $meta       = $project_id ? construction_mgmt_get_project_metadata( $project_id ) : null;

    // Milestones.
    $milestones = $project_id ? construction_mgmt_get_project_milestones( $project_id ) : [];
    $milestones_safe = array_map( function( $m ) {
        return [
            'id'              => (int) $m['id'],
            'title'           => $m['title'],
            'phase'           => $m['phase'],
            'due_date'        => $m['due_date'],
            'completion_date' => $m['completion_date'],
            'status'          => $m['status'],
            'deliverables'    => $m['deliverables'],
        ];
    }, $milestones );

    // Completion percentage derived from milestones.
    $total_ms    = count( $milestones );
    $done_ms     = count( array_filter( $milestones, fn($m) => $m['status'] === 'completed' ) );
    $completion  = $total_ms > 0 ? round( ( $done_ms / $total_ms ) * 100 ) : 0;

    // Conception documents (permits, plans, renders) -- exclude budget-tagged docs.
    $docs_table = construction_mgmt_get_primary_table_prefix() . 'project_documents';
    $docs_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $docs_table ) );
    $documents  = [];
    if ( $docs_exist && $project_id ) {
        $raw_docs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, document_type, external_url, reference_number, issue_date, expiry_date, issued_by, file_url
                 FROM {$docs_table}
                 WHERE project_id = %d
                   AND document_type NOT IN ('invoice','payment','budget','change_order','financial')
                 ORDER BY document_type, issue_date DESC",
                $project_id
            ),
            ARRAY_A
        );
        foreach ( $raw_docs as $d ) {
            $documents[] = [
                'id'               => (int) $d['id'],
                'title'            => $d['title'],
                'document_type'    => $d['document_type'],
                'reference_number' => $d['reference_number'],
                'issue_date'       => $d['issue_date'],
                'expiry_date'      => $d['expiry_date'],
                'issued_by'        => $d['issued_by'],
                'url'              => ! empty( $d['external_url'] ) ? esc_url( $d['external_url'] ) : ( ! empty( $d['file_url'] ) ? esc_url( $d['file_url'] ) : null ),
            ];
        }
    }

    // Feature image.
    $thumb_id  = get_post_thumbnail_id( $post->ID );
    $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : null;

    return [
        'post_id'         => $post->ID,
        'slug'            => $post->post_name,
        'permalink'       => get_permalink( $post->ID ),
        'title'           => get_the_title( $post ),
        'excerpt'         => get_the_excerpt( $post ),
        'feature_image'   => $thumb_url,
        'status'          => $project['status'] ?? 'planning',
        'start_date'      => $project['start_date'] ?? null,
        'end_date'        => $project['end_date'] ?? null,
        'location'        => $meta['location'] ?? null,
        'client_name'     => $meta['client_name'] ?? null,
        'contract_type'   => $meta['contract_type'] ?? null,
        'quality_standard'=> $meta['quality_standard'] ?? null,
        'completion_pct'  => $completion,
        'milestones'      => $milestones_safe,
        'documents'       => $documents,
    ];
}

function jinsing_rest_public_projects( WP_REST_Request $request ) {
    $page     = max( 1, (int) $request->get_param( 'page' ) );
    $per_page = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ) );
    $status   = sanitize_text_field( $request->get_param( 'status' ) ?: '' );

    $args = [
        'post_type'      => 'jinsing_project',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    if ( $status ) {
        $args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            [
                'key'     => '_jinsing_project_status',
                'value'   => $status,
                'compare' => '=',
            ],
        ];
    }

    $query = new WP_Query( $args );
    $items = [];
    foreach ( $query->posts as $post ) {
        $items[] = jinsing_build_public_project_payload( $post );
    }

    return rest_ensure_response( [
        'items'       => $items,
        'total'       => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'page'        => $page,
        'per_page'    => $per_page,
    ] );
}

function jinsing_rest_public_project_single( WP_REST_Request $request ) {
    $id   = absint( $request->get_param( 'id' ) );
    $post = get_post( $id );

    if ( ! $post || $post->post_type !== 'jinsing_project' || $post->post_status !== 'publish' ) {
        return new WP_Error( 'not_found', 'Project not found.', [ 'status' => 404 ] );
    }

    return rest_ensure_response( jinsing_build_public_project_payload( $post ) );
}
