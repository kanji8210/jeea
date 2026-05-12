<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function() {
    add_submenu_page(
        'construction-mgmt-settings',
        'Documents',
        'Documents',
        'manage_construction_documents',
        'construction-mgmt-documents',
        'construction_mgmt_documents_page'
    );
});

function construction_mgmt_documents_page() {
    global $wpdb;
    
    $doc_service = construction_mgmt_get_document_service();
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    $doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    ?>
    <div class="wrap">
        <h1>Document Management</h1>
        <p>Generate and download documents (invoices, receipts, quotes, purchase orders) as PDF files.</p>

        <div style="display: flex; gap: 20px; margin-bottom: 30px;">
            <div style="flex: 1; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <h2 style="margin-top: 0;">Invoices</h2>
                <form method="post" style="display: flex; gap: 10px;">
                    <?php wp_nonce_field('construction_mgmt_generate_document'); ?>
                    <input type="hidden" name="action" value="generate_invoice" />
                    <?php
                    $invoices_table = construction_mgmt_get_table_name('invoices');
                    if (!empty($invoices_table)) {
                        $invoices = $wpdb->get_results("SELECT id, invoice_number, client_name FROM {$invoices_table} ORDER BY id DESC LIMIT 50");
                        if (!empty($invoices)) {
                            echo '<select name="doc_id" required><option value="">-- Select Invoice --</option>';
                            foreach ($invoices as $invoice) {
                                echo '<option value="' . esc_attr($invoice->id) . '">INV-' . esc_html($invoice->invoice_number) . ' (' . esc_html($invoice->client_name) . ')</option>';
                            }
                            echo '</select>';
                            echo '<button type="submit" class="button button-primary">Generate PDF</button>';
                        } else {
                            echo '<p>No invoices found.</p>';
                        }
                    } else {
                        echo '<p>Invoices table not found.</p>';
                    }
                    ?>
                </form>
            </div>

            <div style="flex: 1; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <h2 style="margin-top: 0;">Receipts</h2>
                <form method="post" style="display: flex; gap: 10px;">
                    <?php wp_nonce_field('construction_mgmt_generate_document'); ?>
                    <input type="hidden" name="action" value="generate_receipt" />
                    <?php
                    $receipts_table = construction_mgmt_get_table_name('receipts');
                    if (!empty($receipts_table)) {
                        $receipts = $wpdb->get_results("SELECT id, receipt_number, client_name FROM {$receipts_table} ORDER BY id DESC LIMIT 50");
                        if (!empty($receipts)) {
                            echo '<select name="doc_id" required><option value="">-- Select Receipt --</option>';
                            foreach ($receipts as $receipt) {
                                echo '<option value="' . esc_attr($receipt->id) . '">REC-' . esc_html($receipt->receipt_number) . ' (' . esc_html($receipt->client_name) . ')</option>';
                            }
                            echo '</select>';
                            echo '<button type="submit" class="button button-primary">Generate PDF</button>';
                        } else {
                            echo '<p>No receipts found.</p>';
                        }
                    } else {
                        echo '<p>Receipts table not found.</p>';
                    }
                    ?>
                </form>
            </div>
        </div>

        <div style="display: flex; gap: 20px; margin-bottom: 30px;">
            <div style="flex: 1; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <h2 style="margin-top: 0;">Quotations</h2>
                <form method="post" style="display: flex; gap: 10px;">
                    <?php wp_nonce_field('construction_mgmt_generate_document'); ?>
                    <input type="hidden" name="action" value="generate_quote" />
                    <?php
                    $quotes_table = construction_mgmt_get_table_name('quotes');
                    if (!empty($quotes_table)) {
                        $quotes = $wpdb->get_results("SELECT id, quote_number, client_name FROM {$quotes_table} ORDER BY id DESC LIMIT 50");
                        if (!empty($quotes)) {
                            echo '<select name="doc_id" required><option value="">-- Select Quote --</option>';
                            foreach ($quotes as $quote) {
                                echo '<option value="' . esc_attr($quote->id) . '">QT-' . esc_html($quote->quote_number) . ' (' . esc_html($quote->client_name) . ')</option>';
                            }
                            echo '</select>';
                            echo '<button type="submit" class="button button-primary">Generate PDF</button>';
                        } else {
                            echo '<p>No quotes found.</p>';
                        }
                    } else {
                        echo '<p>Quotes table not found.</p>';
                    }
                    ?>
                </form>
            </div>

            <div style="flex: 1; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <h2 style="margin-top: 0;">Purchase Orders</h2>
                <form method="post" style="display: flex; gap: 10px;">
                    <?php wp_nonce_field('construction_mgmt_generate_document'); ?>
                    <input type="hidden" name="action" value="generate_po" />
                    <?php
                    $po_table = construction_mgmt_get_table_name('purchase_orders');
                    if (!empty($po_table)) {
                        $pos = $wpdb->get_results("SELECT id, po_number FROM {$po_table} ORDER BY id DESC LIMIT 50");
                        if (!empty($pos)) {
                            echo '<select name="doc_id" required><option value="">-- Select PO --</option>';
                            foreach ($pos as $po) {
                                echo '<option value="' . esc_attr($po->id) . '">PO-' . esc_html($po->po_number) . '</option>';
                            }
                            echo '</select>';
                            echo '<button type="submit" class="button button-primary">Generate PDF</button>';
                        } else {
                            echo '<p>No purchase orders found.</p>';
                        }
                    } else {
                        echo '<p>Purchase orders table not found.</p>';
                    }
                    ?>
                </form>
            </div>
        </div>

        <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
            <h3>Note: Dompdf Library Required</h3>
            <p>To generate PDFs, the Dompdf library must be installed in your WordPress installation. Install via Composer:</p>
            <pre><code>composer require dompdf/dompdf</code></pre>
            <p>Or use the WordPress MU-plugins directory if available.</p>
        </div>
    </div>
    <?php

    if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'construction_mgmt_generate_document')) {
        $action = sanitize_text_field($_POST['action']);
        $doc_id = intval($_POST['doc_id']);

        if ($action === 'generate_invoice' && $doc_id > 0) {
            $html = $doc_service->generate_invoice_html($doc_id);
            if (!is_wp_error($html)) {
                $doc_service->download_pdf($html, 'invoice-' . $doc_id . '.pdf');
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($html->get_error_message()) . '</p></div>';
            }
        } elseif ($action === 'generate_receipt' && $doc_id > 0) {
            $html = $doc_service->generate_receipt_html($doc_id);
            if (!is_wp_error($html)) {
                $doc_service->download_pdf($html, 'receipt-' . $doc_id . '.pdf');
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($html->get_error_message()) . '</p></div>';
            }
        } elseif ($action === 'generate_quote' && $doc_id > 0) {
            $html = $doc_service->generate_quote_html($doc_id);
            if (!is_wp_error($html)) {
                $doc_service->download_pdf($html, 'quote-' . $doc_id . '.pdf');
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($html->get_error_message()) . '</p></div>';
            }
        } elseif ($action === 'generate_po' && $doc_id > 0) {
            $html = $doc_service->generate_purchase_order_html($doc_id);
            if (!is_wp_error($html)) {
                $doc_service->download_pdf($html, 'purchase-order-' . $doc_id . '.pdf');
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($html->get_error_message()) . '</p></div>';
            }
        }
    }
}
