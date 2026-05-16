<?php
/**
 * Single Project Public Template
 * Replaces theme single.php for post_type=jinsing_project.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! have_posts() ) {
    wp_redirect( home_url( '/projects/' ) );
    exit;
}

the_post();

$post_id    = get_the_ID();
$project_id = (int) get_post_meta( $post_id, '_jinsing_project_id', true );
$project    = $project_id ? construction_mgmt_get_project( $project_id ) : null;
$meta       = $project_id ? construction_mgmt_get_project_metadata( $project_id ) : null;
$milestones = $project_id ? construction_mgmt_get_project_milestones( $project_id ) : [];

// Completion %.
$total_ms   = count( $milestones );
$done_ms    = count( array_filter( $milestones, fn($m) => $m['status'] === 'completed' ) );
$completion = $total_ms > 0 ? round( ( $done_ms / $total_ms ) * 100 ) : 0;

// Non-financial documents.
global $wpdb;
$docs_table = construction_mgmt_get_primary_table_prefix() . 'project_documents';
$documents  = [];
if ( $project_id ) {
    $docs_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $docs_table ) );
    if ( $docs_exist ) {
        $documents = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, document_type, external_url, reference_number, issue_date, expiry_date, issued_by, file_url
                 FROM {$docs_table}
                 WHERE project_id = %d
                   AND document_type NOT IN ('invoice','payment','budget','change_order','financial')
                 ORDER BY document_type ASC, issue_date DESC",
                $project_id
            ),
            ARRAY_A
        );
    }
}

// Group documents by type.
$doc_groups = [];
foreach ( $documents as $d ) {
    $doc_groups[ $d['document_type'] ][] = $d;
}

// Status labels and colours.
$status_labels = [
    'planning'  => [ 'label' => 'Planning',   'class' => 'proj-status--planning' ],
    'active'    => [ 'label' => 'Active',      'class' => 'proj-status--active' ],
    'on_hold'   => [ 'label' => 'On Hold',     'class' => 'proj-status--hold' ],
    'completed' => [ 'label' => 'Completed',   'class' => 'proj-status--done' ],
    'archived'  => [ 'label' => 'Archived',    'class' => 'proj-status--archived' ],
];
$proj_status = $project['status'] ?? 'planning';
$status_info = $status_labels[ $proj_status ] ?? $status_labels['planning'];

// Doc type display labels.
$doc_type_labels = [
    'permit'              => 'Permits',
    'approved_plan'       => 'Approved Plans',
    'render'              => 'Renders',
    'eia'                 => 'Environmental Impact Assessment',
    'structural_report'   => 'Structural Reports',
    'survey'              => 'Survey & Title Documents',
    'client_brief'        => 'Client Brief',
    'contract'            => 'Contracts',
    'other'               => 'Other Documents',
];

// Milestone status icons.
$ms_icons = [
    'completed'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
    'in_progress' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    'not_started' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>',
    'blocked'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',
];

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( get_the_title() ); ?> | <?php bloginfo( 'name' ); ?></title>
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo esc_url( CONSTRUCTION_MGMT_URL . 'includes/public/project-public.css' ); ?>">
</head>
<body class="proj-body">

<a href="#main-content" class="proj-skip-link">Skip to content</a>

<header class="proj-site-header">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="proj-site-logo">
        <?php bloginfo( 'name' ); ?>
    </a>
    <nav class="proj-site-nav" aria-label="Site navigation">
        <a href="<?php echo esc_url( get_post_type_archive_link( 'jinsing_project' ) ); ?>">All Projects</a>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    </nav>
</header>

<main id="main-content" class="proj-main">

    <?php
    // ── Hero ──────────────────────────────────────────────────────────────────
    $thumb_url = get_the_post_thumbnail_url( $post_id, 'full' );
    ?>
    <section class="proj-hero" <?php if ( $thumb_url ) : ?>style="--hero-img: url('<?php echo esc_url( $thumb_url ); ?>')"<?php endif; ?>>
        <div class="proj-hero-overlay"></div>
        <div class="proj-hero-body">
            <span class="proj-status-badge <?php echo esc_attr( $status_info['class'] ); ?>">
                <?php echo esc_html( $status_info['label'] ); ?>
            </span>
            <h1 class="proj-hero-title"><?php the_title(); ?></h1>
            <?php if ( $meta['location'] ) : ?>
            <p class="proj-hero-location">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?php echo esc_html( $meta['location'] ); ?>
            </p>
            <?php endif; ?>
        </div>
    </section>

    <div class="proj-container">

        <?php // ── Key info strip ───────────────────────────────────────────────── ?>
        <div class="proj-infostrip">
            <?php if ( $meta['client_name'] ) : ?>
            <div class="proj-infostrip-item">
                <span class="proj-infostrip-label">Client</span>
                <span class="proj-infostrip-value"><?php echo esc_html( $meta['client_name'] ); ?></span>
            </div>
            <?php endif; ?>

            <?php if ( $project['start_date'] ) : ?>
            <div class="proj-infostrip-item">
                <span class="proj-infostrip-label">Start</span>
                <span class="proj-infostrip-value"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $project['start_date'] ) ) ); ?></span>
            </div>
            <?php endif; ?>

            <?php if ( $project['end_date'] ) : ?>
            <div class="proj-infostrip-item">
                <span class="proj-infostrip-label">Target Completion</span>
                <span class="proj-infostrip-value"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $project['end_date'] ) ) ); ?></span>
            </div>
            <?php endif; ?>

            <?php if ( $meta['contract_type'] ) : ?>
            <div class="proj-infostrip-item">
                <span class="proj-infostrip-label">Contract</span>
                <span class="proj-infostrip-value"><?php echo esc_html( ucwords( str_replace( '_', ' ', $meta['contract_type'] ) ) ); ?></span>
            </div>
            <?php endif; ?>

            <?php if ( $meta['quality_standard'] ) : ?>
            <div class="proj-infostrip-item">
                <span class="proj-infostrip-label">Quality Standard</span>
                <span class="proj-infostrip-value"><?php echo esc_html( $meta['quality_standard'] ); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="proj-layout">

            <div class="proj-col-main">

                <?php // ── Description ──────────────────────────────────────────────── ?>
                <?php if ( get_the_content() ) : ?>
                <section class="proj-card" aria-label="Project description">
                    <h2 class="proj-section-title">About This Project</h2>
                    <div class="proj-prose">
                        <?php the_content(); ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php // ── Completion tracker ───────────────────────────────────────── ?>
                <?php if ( $total_ms > 0 ) : ?>
                <section class="proj-card" aria-label="Completion tracker">
                    <h2 class="proj-section-title">Completion Tracker</h2>

                    <div class="proj-progress-row">
                        <div class="proj-progress-bar-wrap" role="progressbar" aria-valuenow="<?php echo esc_attr( $completion ); ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="proj-progress-bar" style="width:<?php echo esc_attr( $completion ); ?>%"></div>
                        </div>
                        <span class="proj-progress-pct"><?php echo esc_html( $completion ); ?>%</span>
                    </div>
                    <p class="proj-progress-sub"><?php echo esc_html( $done_ms ); ?> of <?php echo esc_html( $total_ms ); ?> milestones completed</p>

                    <ol class="proj-milestone-list">
                        <?php foreach ( $milestones as $ms ) :
                            $ms_status = $ms['status'] ?? 'not_started';
                            $icon      = $ms_icons[ $ms_status ] ?? $ms_icons['not_started'];
                            $date_str  = $ms['completion_date']
                                ? date_i18n( 'M j, Y', strtotime( $ms['completion_date'] ) )
                                : ( $ms['due_date'] ? 'Due ' . date_i18n( 'M j, Y', strtotime( $ms['due_date'] ) ) : '' );
                        ?>
                        <li class="proj-milestone proj-milestone--<?php echo esc_attr( $ms_status ); ?>">
                            <span class="proj-ms-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                            <div class="proj-ms-body">
                                <span class="proj-ms-title"><?php echo esc_html( $ms['title'] ); ?></span>
                                <?php if ( $ms['phase'] ) : ?>
                                <span class="proj-ms-phase"><?php echo esc_html( $ms['phase'] ); ?></span>
                                <?php endif; ?>
                                <?php if ( $date_str ) : ?>
                                <span class="proj-ms-date"><?php echo esc_html( $date_str ); ?></span>
                                <?php endif; ?>
                                <?php if ( $ms['deliverables'] ) : ?>
                                <p class="proj-ms-deliverables"><?php echo esc_html( $ms['deliverables'] ); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="proj-ms-status-label"><?php echo esc_html( ucwords( str_replace( '_', ' ', $ms_status ) ) ); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
                <?php endif; ?>

            </div>

            <aside class="proj-col-aside">

                <?php // ── Documents & Permits ──────────────────────────────────────── ?>
                <?php if ( ! empty( $doc_groups ) ) : ?>
                <section class="proj-card" aria-label="Permits and documents">
                    <h2 class="proj-section-title">Permits &amp; Documents</h2>

                    <?php foreach ( $doc_groups as $type => $docs ) :
                        $type_label = $doc_type_labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );
                    ?>
                    <div class="proj-docgroup">
                        <h3 class="proj-docgroup-title"><?php echo esc_html( $type_label ); ?></h3>
                        <ul class="proj-doclist">
                            <?php foreach ( $docs as $d ) :
                                $url = ! empty( $d['external_url'] ) ? $d['external_url'] : ( $d['file_url'] ?? '' );
                            ?>
                            <li class="proj-docitem">
                                <?php if ( $url ) : ?>
                                <a href="<?php echo esc_url( $url ); ?>" class="proj-docitem-link" target="_blank" rel="noopener noreferrer">
                                <?php endif; ?>
                                    <span class="proj-docitem-icon" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    </span>
                                    <div class="proj-docitem-body">
                                        <span class="proj-docitem-title"><?php echo esc_html( $d['title'] ); ?></span>
                                        <?php if ( $d['reference_number'] ) : ?>
                                        <span class="proj-docitem-ref">Ref: <?php echo esc_html( $d['reference_number'] ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( $d['issued_by'] ) : ?>
                                        <span class="proj-docitem-issued">Issued by: <?php echo esc_html( $d['issued_by'] ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( $d['expiry_date'] ) : ?>
                                        <span class="proj-docitem-expiry">Expires: <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $d['expiry_date'] ) ) ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php if ( $url ) : ?>
                                </a>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>

                <?php // ── Quick facts ──────────────────────────────────────────────── ?>
                <section class="proj-card proj-card--facts" aria-label="Project facts">
                    <h2 class="proj-section-title">Quick Facts</h2>
                    <dl class="proj-facts">
                        <div class="proj-fact">
                            <dt>Status</dt>
                            <dd><span class="proj-status-badge <?php echo esc_attr( $status_info['class'] ); ?>"><?php echo esc_html( $status_info['label'] ); ?></span></dd>
                        </div>
                        <?php if ( $total_ms > 0 ) : ?>
                        <div class="proj-fact">
                            <dt>Completion</dt>
                            <dd><?php echo esc_html( $completion ); ?>%</dd>
                        </div>
                        <?php endif; ?>
                        <?php if ( $project['start_date'] ) : ?>
                        <div class="proj-fact">
                            <dt>Started</dt>
                            <dd><?php echo esc_html( date_i18n( 'M Y', strtotime( $project['start_date'] ) ) ); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ( $project['end_date'] ) : ?>
                        <div class="proj-fact">
                            <dt>Target End</dt>
                            <dd><?php echo esc_html( date_i18n( 'M Y', strtotime( $project['end_date'] ) ) ); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ( $meta['location'] ) : ?>
                        <div class="proj-fact">
                            <dt>Location</dt>
                            <dd><?php echo esc_html( $meta['location'] ); ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </section>

            </aside>

        </div><!-- .proj-layout -->

    </div><!-- .proj-container -->

</main>

<footer class="proj-site-footer">
    <p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
</footer>

<?php wp_footer(); ?>
</body>
</html>
