<?php
/**
 * Projects Archive Public Template
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$status_labels = [
    'planning'  => [ 'label' => 'Planning',  'class' => 'proj-status--planning' ],
    'active'    => [ 'label' => 'Active',     'class' => 'proj-status--active' ],
    'on_hold'   => [ 'label' => 'On Hold',    'class' => 'proj-status--hold' ],
    'completed' => [ 'label' => 'Completed',  'class' => 'proj-status--done' ],
    'archived'  => [ 'label' => 'Archived',   'class' => 'proj-status--archived' ],
];

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projects | <?php bloginfo( 'name' ); ?></title>
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo esc_url( CONSTRUCTION_MGMT_URL . 'includes/public/project-public.css' ); ?>">
</head>
<body class="proj-body proj-body--archive">

<a href="#main-content" class="proj-skip-link">Skip to content</a>

<header class="proj-site-header">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="proj-site-logo">
        <?php bloginfo( 'name' ); ?>
    </a>
    <nav class="proj-site-nav" aria-label="Site navigation">
        <a href="<?php echo esc_url( get_post_type_archive_link( 'jinsing_project' ) ); ?>" aria-current="page">All Projects</a>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
    </nav>
</header>

<main id="main-content" class="proj-main proj-archive-main">
    <div class="proj-container">

        <div class="proj-archive-header">
            <h1 class="proj-archive-title">Our Projects</h1>
            <p class="proj-archive-sub">A showcase of ongoing and completed construction projects.</p>
        </div>

        <?php if ( have_posts() ) : ?>
        <div class="proj-archive-grid">
            <?php while ( have_posts() ) : the_post();
                $post_id    = get_the_ID();
                $project_id = (int) get_post_meta( $post_id, '_jinsing_project_id', true );
                $project    = $project_id ? construction_mgmt_get_project( $project_id ) : null;
                $meta       = $project_id ? construction_mgmt_get_project_metadata( $project_id ) : null;
                $milestones = $project_id ? construction_mgmt_get_project_milestones( $project_id ) : [];

                $total_ms   = count( $milestones );
                $done_ms    = count( array_filter( $milestones, fn($m) => $m['status'] === 'completed' ) );
                $completion = $total_ms > 0 ? round( ( $done_ms / $total_ms ) * 100 ) : 0;

                $proj_status = $project['status'] ?? 'planning';
                $status_info = $status_labels[ $proj_status ] ?? $status_labels['planning'];
                $thumb_url   = get_the_post_thumbnail_url( $post_id, 'medium_large' );
            ?>
            <article class="proj-card proj-archive-card" aria-labelledby="proj-<?php echo esc_attr( $post_id ); ?>-title">
                <a href="<?php the_permalink(); ?>" class="proj-archive-card-img-link" tabindex="-1" aria-hidden="true">
                    <?php if ( $thumb_url ) : ?>
                    <img
                        src="<?php echo esc_url( $thumb_url ); ?>"
                        alt=""
                        class="proj-archive-card-img"
                        loading="lazy"
                        width="480" height="270"
                    >
                    <?php else : ?>
                    <div class="proj-archive-card-img proj-archive-card-img--placeholder" aria-hidden="true"></div>
                    <?php endif; ?>
                </a>
                <div class="proj-archive-card-body">
                    <div class="proj-archive-card-header">
                        <span class="proj-status-badge <?php echo esc_attr( $status_info['class'] ); ?>">
                            <?php echo esc_html( $status_info['label'] ); ?>
                        </span>
                        <?php if ( $meta['location'] ) : ?>
                        <span class="proj-archive-location">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?php echo esc_html( $meta['location'] ); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <h2 class="proj-archive-card-title" id="proj-<?php echo esc_attr( $post_id ); ?>-title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>

                    <?php if ( has_excerpt() ) : ?>
                    <p class="proj-archive-card-excerpt"><?php the_excerpt(); ?></p>
                    <?php endif; ?>

                    <?php if ( $total_ms > 0 ) : ?>
                    <div class="proj-archive-progress">
                        <div class="proj-progress-bar-wrap" role="progressbar" aria-valuenow="<?php echo esc_attr( $completion ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo esc_attr( $completion . '% complete' ); ?>">
                            <div class="proj-progress-bar" style="width:<?php echo esc_attr( $completion ); ?>%"></div>
                        </div>
                        <span class="proj-archive-pct"><?php echo esc_html( $completion ); ?>%</span>
                    </div>
                    <?php endif; ?>

                    <a href="<?php the_permalink(); ?>" class="proj-archive-card-link">
                        View project
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>
            </article>
            <?php endwhile; ?>
        </div>

        <div class="proj-pagination">
            <?php
            the_posts_pagination( [
                'mid_size'  => 2,
                'prev_text' => '&larr; Previous',
                'next_text' => 'Next &rarr;',
            ] );
            ?>
        </div>

        <?php else : ?>
        <div class="proj-empty">
            <p>No projects are currently published.</p>
        </div>
        <?php endif; ?>

    </div>
</main>

<footer class="proj-site-footer">
    <p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
</footer>

<?php wp_footer(); ?>
</body>
</html>
