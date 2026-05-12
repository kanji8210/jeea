<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_render_purchase_order_template($data) {
    $po = $data['purchase_order'];
    $items = $data['items'];
    $branding = $data['branding'];
    
    $logo_html = !empty($branding['logo_url'])
        ? '<img src="' . esc_url($branding['logo_url']) . '" alt="Logo" style="max-height: 60px; margin-bottom: 10px;">'
        : '';

    $items_html = '';
    if (is_array($items)) {
        $items_html = '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background-color: #f0f0f0; border-bottom: 2px solid #333;">
                    <th style="text-align: left; padding: 10px; border-bottom: 2px solid #333;">Item Description</th>
                    <th style="text-align: center; padding: 10px; border-bottom: 2px solid #333;">Qty</th>
                    <th style="text-align: right; padding: 10px; border-bottom: 2px solid #333;">Unit Price</th>
                    <th style="text-align: right; padding: 10px; border-bottom: 2px solid #333;">Tax</th>
                    <th style="text-align: right; padding: 10px; border-bottom: 2px solid #333;">Total</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($items as $item) {
            $items_html .= '<tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px; text-align: left;">' . esc_html($item->description) . '</td>
                <td style="padding: 10px; text-align: center;">' . number_format($item->quantity, 2) . '</td>
                <td style="padding: 10px; text-align: right;">KES ' . number_format($item->unit_price, 2) . '</td>
                <td style="padding: 10px; text-align: right;">KES ' . number_format($item->tax_amount, 2) . '</td>
                <td style="padding: 10px; text-align: right;">KES ' . number_format($item->line_total, 2) . '</td>
            </tr>';
        }
        
        $items_html .= '</tbody></table>';
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px; }
            .header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 30px; border-bottom: 3px solid #1a5490; padding-bottom: 20px; }
            .company-info { flex: 1; }
            .company-info h1 { margin: 0; color: #1a5490; font-size: 24px; }
            .company-info p { margin: 5px 0; font-size: 12px; color: #666; }
            .po-details { flex: 1; text-align: right; }
            .po-details h2 { margin: 0; color: #1a5490; font-size: 28px; }
            .po-details p { margin: 5px 0; font-size: 12px; }
            .supplier-info { margin-bottom: 20px; }
            .supplier-info h3 { margin-top: 0; color: #1a5490; }
            .supplier-info p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #f0f0f0; border-bottom: 2px solid #333; padding: 10px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            .totals { margin-top: 30px; width: 100%; }
            .totals-row { display: flex; justify-content: flex-end; margin: 10px 0; }
            .totals-label { width: 150px; text-align: right; font-weight: bold; padding-right: 20px; }
            .totals-value { width: 100px; text-align: right; }
            .grand-total { border-top: 2px solid #333; border-bottom: 3px solid #1a5490; padding: 10px; font-size: 16px; font-weight: bold; background-color: #f9f9f9; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 11px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="company-info">
                ' . $logo_html . '
                <h1>' . esc_html($branding['company_name']) . '</h1>
                <p>' . esc_html($branding['company_address']) . '</p>
                <p>Phone: ' . esc_html($branding['company_phone']) . '</p>
                <p>Email: ' . esc_html($branding['company_email']) . '</p>
            </div>
            <div class="po-details">
                <h2>PURCHASE ORDER</h2>
                <p><strong>PO #:</strong> ' . esc_html($po->po_number) . '</p>
                <p><strong>Date:</strong> ' . esc_html($po->order_date) . '</p>
                <p><strong>Status:</strong> ' . esc_html(ucfirst($po->status)) . '</p>
            </div>
        </div>

        <div class="supplier-info">
            <h3>Supplier Details:</h3>
            <p><strong>Supplier ID:</strong> ' . esc_html($po->supplier_id) . '</p>
        </div>

        ' . $items_html . '

        <div class="totals">
            <div class="totals-row">
                <div class="totals-label">Subtotal:</div>
                <div class="totals-value">KES ' . number_format($po->subtotal, 2) . '</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">Tax (VAT):</div>
                <div class="totals-value">KES ' . number_format($po->tax_total, 2) . '</div>
            </div>
            <div class="totals-row grand-total">
                <div class="totals-label">TOTAL ORDER VALUE:</div>
                <div class="totals-value">KES ' . number_format($po->grand_total, 2) . '</div>
            </div>
        </div>

        <div class="footer">
            ' . esc_html($branding['footer_text']) . '
            <p style="margin-top: 10px;">Generated on ' . current_time('Y-m-d H:i:s') . '</p>
            <p>Please confirm receipt and delivery of goods</p>
        </div>
    </body>
    </html>';

    return $html;
}
