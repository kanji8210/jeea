<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once CONSTRUCTION_MGMT_PATH . 'includes/documents/templates/invoice-template.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/documents/templates/receipt-template.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/documents/templates/quote-template.php';
require_once CONSTRUCTION_MGMT_PATH . 'includes/documents/templates/purchase-order-template.php';

class Construction_Mgmt_Document_Service {

    private static $instance = null;
    private $dompdf = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_dompdf();
    }

    private function init_dompdf() {
        $dompdf_path = CONSTRUCTION_MGMT_PATH . 'vendor/autoload.php';
        
        if (!file_exists($dompdf_path)) {
            // Try WordPress plugins directory
            $plugin_dompdf = WP_PLUGIN_DIR . '/dompdf/vendor/autoload.php';
            if (file_exists($plugin_dompdf)) {
                require_once $plugin_dompdf;
            } else {
                // Dompdf not available; will need to be installed via Composer
                return;
            }
        } else {
            require_once $dompdf_path;
        }
    }

    public function get_company_branding() {
        return [
            'logo_url' => get_option('construction_mgmt_doc_logo_url', ''),
            'company_name' => get_option('construction_mgmt_doc_company_name', get_bloginfo('name')),
            'company_address' => get_option('construction_mgmt_doc_company_address', ''),
            'company_phone' => get_option('construction_mgmt_doc_company_phone', ''),
            'company_email' => get_option('construction_mgmt_doc_company_email', get_bloginfo('admin_email')),
            'company_kra_pin' => get_option('construction_mgmt_doc_company_kra_pin', ''),
            'footer_text' => get_option('construction_mgmt_doc_footer_text', ''),
        ];
    }

    public function generate_invoice_html($invoice_id) {
        global $wpdb;
        
        $invoices_table = construction_mgmt_get_table_name('invoices');
        $invoice_items_table = construction_mgmt_get_table_name('invoice_items');
        
        if (empty($invoices_table) || empty($invoice_items_table)) {
            return new WP_Error('tables_missing', 'Invoice or invoice items table not found');
        }

        $invoice = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$invoices_table} WHERE id = %d", $invoice_id)
        );

        if (!$invoice) {
            return new WP_Error('invoice_not_found', 'Invoice not found');
        }

        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$invoice_items_table} WHERE invoice_id = %d", $invoice_id)
        );

        $branding = $this->get_company_branding();
        
        return construction_mgmt_render_invoice_template([
            'invoice' => $invoice,
            'items' => $items,
            'branding' => $branding,
        ]);
    }

    public function generate_receipt_html($receipt_id) {
        global $wpdb;
        
        $receipts_table = construction_mgmt_get_table_name('receipts');
        
        if (empty($receipts_table)) {
            return new WP_Error('table_missing', 'Receipts table not found');
        }

        $receipt = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$receipts_table} WHERE id = %d", $receipt_id)
        );

        if (!$receipt) {
            return new WP_Error('receipt_not_found', 'Receipt not found');
        }

        $branding = $this->get_company_branding();
        
        return construction_mgmt_render_receipt_template([
            'receipt' => $receipt,
            'branding' => $branding,
        ]);
    }

    public function generate_quote_html($quote_id) {
        global $wpdb;
        
        $quotes_table = construction_mgmt_get_table_name('quotes');
        $quote_items_table = construction_mgmt_get_table_name('quote_items');
        
        if (empty($quotes_table) || empty($quote_items_table)) {
            return new WP_Error('tables_missing', 'Quote or quote items table not found');
        }

        $quote = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$quotes_table} WHERE id = %d", $quote_id)
        );

        if (!$quote) {
            return new WP_Error('quote_not_found', 'Quote not found');
        }

        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$quote_items_table} WHERE quote_id = %d", $quote_id)
        );

        $branding = $this->get_company_branding();
        
        return construction_mgmt_render_quote_template([
            'quote' => $quote,
            'items' => $items,
            'branding' => $branding,
        ]);
    }

    public function generate_purchase_order_html($po_id) {
        global $wpdb;
        
        $po_table = construction_mgmt_get_table_name('purchase_orders');
        $po_items_table = construction_mgmt_get_table_name('po_items');
        
        if (empty($po_table) || empty($po_items_table)) {
            return new WP_Error('tables_missing', 'Purchase order or items table not found');
        }

        $po = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$po_table} WHERE id = %d", $po_id)
        );

        if (!$po) {
            return new WP_Error('po_not_found', 'Purchase order not found');
        }

        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$po_items_table} WHERE purchase_order_id = %d", $po_id)
        );

        $branding = $this->get_company_branding();
        
        return construction_mgmt_render_purchase_order_template([
            'purchase_order' => $po,
            'items' => $items,
            'branding' => $branding,
        ]);
    }

    public function download_pdf($html, $filename = 'document.pdf') {
        if (!class_exists('Dompdf\Dompdf')) {
            return new WP_Error('dompdf_missing', 'Dompdf library not installed');
        }

        try {
            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
            echo $dompdf->output();
            exit;
        } catch (Exception $e) {
            return new WP_Error('pdf_generation_failed', $e->getMessage());
        }
    }

    public function output_pdf_inline($html) {
        if (!class_exists('Dompdf\Dompdf')) {
            return new WP_Error('dompdf_missing', 'Dompdf library not installed');
        }

        try {
            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline');
            echo $dompdf->output();
            exit;
        } catch (Exception $e) {
            return new WP_Error('pdf_generation_failed', $e->getMessage());
        }
    }
}

function construction_mgmt_get_document_service() {
    return Construction_Mgmt_Document_Service::get_instance();
}
