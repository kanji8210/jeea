<?php

if (!defined('ABSPATH')) {
    exit;
}

// ── Automated Entry Engine ─────────────────────────────────────────────────────

class Jinsing_AutoEntryEngine {

    // Allowed MIME types for receipt uploads.
    private static $allowed_mime_types = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    // Maximum upload size: 10 MB.
    private const MAX_UPLOAD_BYTES = 10485760;

    /**
     * Process an OCR queue item and auto-create an expense.
     *
     * @param int $ocr_queue_id
     * @return int|false  Inserted expense ID or false on failure.
     */
    public static function process_ocr_receipt( $ocr_queue_id ) {
        global $wpdb;

        $ocr_queue_id = absint( $ocr_queue_id );

        $queue_item = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}jinsing_ocr_queue WHERE id = %d",
            $ocr_queue_id
        ) );

        if ( ! $queue_item ) {
            return false;
        }

        // Mark as processing to prevent duplicate runs.
        $wpdb->update(
            $wpdb->prefix . 'jinsing_ocr_queue',
            [ 'processing_status' => 'processing' ],
            [ 'id' => $ocr_queue_id ],
            [ '%s' ],
            [ '%d' ]
        );

        $extracted = json_decode( $queue_item->extracted_json, true );

        // Validate required OCR fields exist before proceeding.
        if (
            ! is_array( $extracted ) ||
            empty( $extracted['vendor'] ) ||
            ! isset( $extracted['total_amount'] ) ||
            empty( $extracted['date'] )
        ) {
            $wpdb->update(
                $wpdb->prefix . 'jinsing_ocr_queue',
                [
                    'processing_status' => 'failed',
                    'error_message'     => 'Missing required OCR fields',
                    'processed_at'      => current_time( 'mysql' ),
                ],
                [ 'id' => $ocr_queue_id ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
            return false;
        }

        $vendor      = sanitize_text_field( $extracted['vendor'] );
        $description = isset( $extracted['description'] ) ? sanitize_text_field( $extracted['description'] ) : '';
        $amount      = (float) $extracted['total_amount'];
        $vat         = isset( $extracted['vat'] ) ? (float) $extracted['vat'] : 0.0;
        $date        = sanitize_text_field( $extracted['date'] );
        $confidence  = isset( $extracted['confidence'] ) ? (float) $extracted['confidence'] : 0.0;

        $category   = self::categorize_expense( $vendor, $description );
        $expense_id = self::create_expense_from_ocr( [
            'project_id'  => absint( $queue_item->project_id ),
            'vendor'      => $vendor,
            'amount'      => $amount,
            'vat'         => $vat,
            'date'        => $date,
            'cost_code'   => $category,
            'description' => $description,
        ] );

        if ( ! $expense_id ) {
            $wpdb->update(
                $wpdb->prefix . 'jinsing_ocr_queue',
                [
                    'processing_status' => 'failed',
                    'error_message'     => 'Could not insert expense record',
                    'processed_at'      => current_time( 'mysql' ),
                ],
                [ 'id' => $ocr_queue_id ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
            return false;
        }

        // Log the automatic entry.
        $wpdb->insert(
            $wpdb->prefix . 'jinsing_auto_entry_logs',
            [
                'source_type'         => 'ocr',
                'source_id'           => $ocr_queue_id,
                'created_entity_type' => 'expense',
                'created_entity_id'   => $expense_id,
                'extracted_data'      => wp_json_encode( $extracted ),
                'confidence_score'    => $confidence,
                'status'              => 'auto_approved',
            ],
            [ '%s', '%d', '%s', '%d', '%s', '%f', '%s' ]
        );

        // Mark queue item complete.
        $wpdb->update(
            $wpdb->prefix . 'jinsing_ocr_queue',
            [
                'processing_status' => 'completed',
                'processed_at'      => current_time( 'mysql' ),
            ],
            [ 'id' => $ocr_queue_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return $expense_id;
    }

    /**
     * Auto-create an expense entry from an approved timesheet row.
     *
     * @param int $timesheet_id
     * @return int|false  Inserted expense ID or false on failure.
     */
    public static function process_timesheet_approval( $timesheet_id ) {
        global $wpdb;

        $timesheet_id = absint( $timesheet_id );

        $timesheet = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, w.full_name AS worker_name, w.daily_rate
             FROM {$wpdb->prefix}jinsing_timesheets t
             LEFT JOIN {$wpdb->prefix}jinsing_workers w ON w.id = t.worker_id
             WHERE t.id = %d",
            $timesheet_id
        ) );

        if ( ! $timesheet ) {
            return false;
        }

        $hourly_rate = (float) self::get_worker_rate( $timesheet->worker_id );
        $hours       = (float) $timesheet->hours_worked;
        $labour_cost = round( $hourly_rate * $hours, 2 );

        $worker_name = sanitize_text_field( $timesheet->worker_name ?? 'Unknown worker' );
        $task_label  = ! empty( $timesheet->task_id ) ? 'Task #' . absint( $timesheet->task_id ) : 'General labour';

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'jinsing_expenses',
            [
                'project_id'  => absint( $timesheet->project_id ),
                'cost_code'   => 'LABOUR',
                'amount'      => $labour_cost,
                'description' => "Labour: {$worker_name} – {$task_label}",
                'date'        => $timesheet->work_date,
                'source'      => 'timesheet_auto',
                'source_id'   => $timesheet_id,
                'created_by'  => get_current_user_id(),
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%f', '%s', '%s', '%s', '%d', '%d', '%s' ]
        );

        if ( ! $inserted ) {
            return false;
        }

        $expense_id = $wpdb->insert_id;

        $wpdb->insert(
            $wpdb->prefix . 'jinsing_auto_entry_logs',
            [
                'source_type'         => 'timesheet',
                'source_id'           => $timesheet_id,
                'created_entity_type' => 'expense',
                'created_entity_id'   => $expense_id,
                'extracted_data'      => wp_json_encode( [
                    'worker_id'    => $timesheet->worker_id,
                    'worker_name'  => $worker_name,
                    'hours_worked' => $hours,
                    'hourly_rate'  => $hourly_rate,
                    'labour_cost'  => $labour_cost,
                ] ),
                'confidence_score' => 1.0,
                'status'           => 'auto_approved',
            ],
            [ '%s', '%d', '%s', '%d', '%s', '%f', '%s' ]
        );

        return $expense_id;
    }

    /**
     * Retrieve the effective hourly rate for a worker.
     * Falls back to daily_rate / 8 if no hourly_rate column exists.
     *
     * @param int $worker_id
     * @return float
     */
    public static function get_worker_rate( $worker_id ) {
        global $wpdb;

        $worker = $wpdb->get_row( $wpdb->prepare(
            "SELECT daily_rate FROM {$wpdb->prefix}jinsing_workers WHERE id = %d",
            absint( $worker_id )
        ) );

        if ( ! $worker ) {
            return 0.0;
        }

        // Return hourly equivalent (daily_rate / 8 standard work hours).
        return round( (float) $worker->daily_rate / 8, 4 );
    }

    /**
     * Rule-based expense categorization.
     *
     * @param string $vendor
     * @param string $description
     * @return string  Cost code.
     */
    private static function categorize_expense( $vendor, $description ) {
        $text = strtolower( $vendor . ' ' . $description );

        $rules = [
            'MATERIALS_CEMENT'   => [ 'cement', 'concrete', 'screed' ],
            'MATERIALS_STEEL'    => [ 'steel', 'iron', 'rebar', 'rod' ],
            'MATERIALS_TIMBER'   => [ 'timber', 'wood', 'lumber', 'plywood' ],
            'MATERIALS_TILES'    => [ 'tile', 'tiles', 'ceramic', 'granite' ],
            'EQUIPMENT_FUEL'     => [ 'fuel', 'petrol', 'diesel', 'petroleum' ],
            'EQUIPMENT_HIRE'     => [ 'hire', 'rental', 'crane', 'excavator' ],
            'LABOUR'             => [ 'labour', 'labor', 'fundi', 'casual' ],
            'TRANSPORT'          => [ 'transport', 'delivery', 'logistics', 'freight' ],
        ];

        foreach ( $rules as $code => $keywords ) {
            foreach ( $keywords as $kw ) {
                if ( strpos( $text, $kw ) !== false ) {
                    return $code;
                }
            }
        }

        return 'MATERIALS_OTHER';
    }

    /**
     * Insert an expense row sourced from OCR.
     *
     * @param array $data
     * @return int|false  Insert ID or false.
     */
    private static function create_expense_from_ocr( array $data ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'jinsing_expenses',
            [
                'project_id'  => absint( $data['project_id'] ),
                'vendor'      => sanitize_text_field( $data['vendor'] ),
                'amount'      => (float) $data['amount'],
                'vat'         => (float) $data['vat'],
                'date'        => sanitize_text_field( $data['date'] ),
                'cost_code'   => sanitize_text_field( $data['cost_code'] ),
                'description' => sanitize_text_field( $data['description'] ),
                'source'      => 'ocr',
                'created_by'  => get_current_user_id(),
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );

        return $inserted ? $wpdb->insert_id : false;
    }
}

// ── REST endpoint: POST /wp-json/jinsing/v1/auto/process-receipt ──────────────

add_action( 'rest_api_init', 'jinsing_auto_entry_endpoints' );

function jinsing_auto_entry_endpoints() {
    register_rest_route( 'jinsing/v1', '/auto/process-receipt', [
        'methods'             => 'POST',
        'callback'            => 'jinsing_auto_process_receipt',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [
            'project_id' => [
                'type'              => 'integer',
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],
        ],
    ] );

    // GET /wp-json/jinsing/v1/auto/entries -- paginated expense list
    register_rest_route( 'jinsing/v1', '/auto/entries', [
        'methods'             => 'GET',
        'callback'            => 'jinsing_get_auto_entries',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [
            'page'       => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
            'per_page'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ],
            'project_id' => [ 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ],
            'source'     => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'enum'              => [ 'manual', 'ocr', 'timesheet_auto', 'mpesa', 'api' ],
            ],
        ],
    ] );

    // POST /wp-json/jinsing/v1/auto/entries -- manual expense creation
    register_rest_route( 'jinsing/v1', '/auto/entries', [
        'methods'             => 'POST',
        'callback'            => 'jinsing_create_manual_entry',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [
            'date'        => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'description' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'amount'      => [
                'type'     => 'number',
                'required' => true,
                'minimum'  => 0,
            ],
            'cost_code'   => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'project_id'  => [
                'type'              => 'integer',
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],
        ],
    ] );
}

function jinsing_auto_process_receipt( WP_REST_Request $request ) {
    $files = $request->get_file_params();

    if ( empty( $files['receipt'] ) ) {
        return new WP_Error( 'no_file', 'No receipt uploaded.', [ 'status' => 400 ] );
    }

    $file       = $files['receipt'];
    $project_id = absint( $request->get_param( 'project_id' ) );

    // Validate file size.
    if ( $file['size'] > Jinsing_AutoEntryEngine::MAX_UPLOAD_BYTES ) {
        return new WP_Error( 'file_too_large', 'Receipt file exceeds 10 MB limit.', [ 'status' => 400 ] );
    }

    // Validate MIME type using WordPress finfo-based check (not just extension).
    $allowed_types = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    $file_info  = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
    $mime_type  = $file_info['type'] ?? '';

    if ( ! in_array( $mime_type, $allowed_types, true ) ) {
        return new WP_Error(
            'invalid_type',
            'Unsupported file type. Allowed: JPEG, PNG, WebP, PDF.',
            [ 'status' => 415 ]
        );
    }

    // Read file contents safely.
    $file_contents = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    if ( $file_contents === false ) {
        return new WP_Error( 'read_error', 'Could not read uploaded file.', [ 'status' => 500 ] );
    }

    $upload = wp_upload_bits( sanitize_file_name( $file['name'] ), null, $file_contents );

    if ( ! empty( $upload['error'] ) ) {
        return new WP_Error( 'upload_error', $upload['error'], [ 'status' => 500 ] );
    }

    // Enqueue for OCR processing.
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'jinsing_ocr_queue',
        [
            'file_path'         => $upload['file'],
            'file_type'         => 'receipt',
            'project_id'        => $project_id,
            'processing_status' => 'queued',
            'queued_by'         => get_current_user_id(),
            'created_at'        => current_time( 'mysql' ),
        ],
        [ '%s', '%s', '%d', '%s', '%d', '%s' ]
    );

    $queue_id = (int) $wpdb->insert_id;

    // Process synchronously for now; swap for wp_schedule_single_event() to go async.
    $expense_id = Jinsing_AutoEntryEngine::process_ocr_receipt( $queue_id );

    return rest_ensure_response( [
        'success'    => true,
        'message'    => $expense_id ? 'Receipt processed successfully.' : 'Receipt queued - OCR extraction pending.',
        'queue_id'   => $queue_id,
        'expense_id' => $expense_id ?: null,
    ] );
}

function jinsing_get_auto_entries( WP_REST_Request $request ) {
    global $wpdb;

    $page       = max( 1, (int) $request->get_param( 'page' ) );
    $per_page   = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
    $project_id = absint( $request->get_param( 'project_id' ) );
    $source     = $request->get_param( 'source' );
    $offset     = ( $page - 1 ) * $per_page;

    $sql  = "SELECT e.*, l.status AS auto_status, l.confidence_score
             FROM {$wpdb->prefix}jinsing_expenses e
             LEFT JOIN {$wpdb->prefix}jinsing_auto_entry_logs l
                    ON l.created_entity_type = 'expense' AND l.created_entity_id = e.id
             WHERE 1=1";
    $args = [];

    if ( $project_id > 0 ) {
        $sql   .= ' AND e.project_id = %d';
        $args[] = $project_id;
    }

    if ( ! empty( $source ) ) {
        $sql   .= ' AND e.source = %s';
        $args[] = $source;
    }

    $sql   .= ' ORDER BY e.created_at DESC LIMIT %d OFFSET %d';
    $args[] = $per_page;
    $args[] = $offset;

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    return rest_ensure_response( $rows );
}

function jinsing_create_manual_entry( WP_REST_Request $request ) {
    global $wpdb;

    $date        = sanitize_text_field( $request->get_param( 'date' ) );
    $description = sanitize_text_field( $request->get_param( 'description' ) );
    $amount      = (float) $request->get_param( 'amount' );
    $cost_code   = sanitize_text_field( $request->get_param( 'cost_code' ) ?: 'MATERIALS_OTHER' );
    $project_id  = absint( $request->get_param( 'project_id' ) );

    // Validate date format.
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return new WP_Error( 'invalid_date', 'Date must be YYYY-MM-DD.', [ 'status' => 400 ] );
    }

    if ( empty( $description ) ) {
        return new WP_Error( 'missing_description', 'Description is required.', [ 'status' => 400 ] );
    }

    if ( $amount < 0 ) {
        return new WP_Error( 'invalid_amount', 'Amount must be non-negative.', [ 'status' => 400 ] );
    }

    $allowed_cost_codes = [
        'LABOUR', 'MATERIALS_CEMENT', 'MATERIALS_STEEL', 'MATERIALS_TIMBER',
        'MATERIALS_TILES', 'EQUIPMENT_FUEL', 'EQUIPMENT_HIRE', 'TRANSPORT', 'MATERIALS_OTHER',
    ];
    if ( ! in_array( $cost_code, $allowed_cost_codes, true ) ) {
        $cost_code = 'MATERIALS_OTHER';
    }

    $data   = [
        'description' => $description,
        'amount'      => $amount,
        'date'        => $date,
        'cost_code'   => $cost_code,
        'source'      => 'manual',
        'created_by'  => get_current_user_id(),
        'created_at'  => current_time( 'mysql' ),
    ];
    $format = [ '%s', '%f', '%s', '%s', '%s', '%d', '%s' ];

    if ( $project_id > 0 ) {
        $data['project_id'] = $project_id;
        array_unshift( $format, '%d' );
    }

    $inserted = $wpdb->insert( $wpdb->prefix . 'jinsing_expenses', $data, $format );

    if ( ! $inserted ) {
        return new WP_Error( 'db_error', 'Failed to create expense.', [ 'status' => 500 ] );
    }

    $expense_id = (int) $wpdb->insert_id;

    // Return same shape as GET /auto/entries rows.
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT e.*, NULL AS auto_status, NULL AS confidence_score
             FROM {$wpdb->prefix}jinsing_expenses e
             WHERE e.id = %d",
            $expense_id
        ),
        ARRAY_A
    );

    return rest_ensure_response( $row );
}

// ── Hook: auto-create expense on timesheet approval ───────────────────────────

add_action( 'jinsing_timesheet_approved', 'jinsing_handle_timesheet_approved' );

function jinsing_handle_timesheet_approved( $timesheet_id ) {
    Jinsing_AutoEntryEngine::process_timesheet_approval( absint( $timesheet_id ) );
}
