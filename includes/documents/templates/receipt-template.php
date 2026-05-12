<?php

if (!defined('ABSPATH')) {
    exit;
}

function construction_mgmt_render_receipt_template($data) {
    $receipt = $data['receipt'];
    $branding = $data['branding'];
    
    $logo_html = !empty($branding['logo_url'])
        ? '<img src="' . esc_url($branding['logo_url']) . '" alt="Logo" style="max-height: 60px; margin-bottom: 10px;">'
        : '';

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #1a5490; padding-bottom: 20px; }
            .header h1 { margin: 0; color: #1a5490; font-size: 28px; }
            .header p { margin: 5px 0; font-size: 12px; color: #666; }
            .receipt-title { font-size: 24px; font-weight: bold; color: #1a5490; text-align: center; margin: 30px 0; }
            .details-table { width: 100%; margin-top: 20px; }
            .details-row { display: flex; margin: 10px 0; }
            .details-label { width: 40%; font-weight: bold; }
            .details-value { width: 60%; }
            .amount-box { background-color: #1a5490; color: white; padding: 20px; text-align: center; margin: 30px 0; border-radius: 5px; }
            .amount-box .label { font-size: 14px; }
            .amount-box .value { font-size: 36px; font-weight: bold; margin-top: 10px; }
            .payment-method { margin-top: 20px; padding: 10px; background-color: #f0f0f0; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 11px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            ' . $logo_html . '
            <h1>' . esc_html($branding['company_name']) . '</h1>
            <p>' . esc_html($branding['company_address']) . '</p>
            <p>Phone: ' . esc_html($branding['company_phone']) . ' | Email: ' . esc_html($branding['company_email']) . '</p>
        </div>

        <div class="receipt-title">RECEIPT</div>

        <div class="details-table">
            <div class="details-row">
                <div class="details-label">Receipt Number:</div>
                <div class="details-value">' . esc_html($receipt->receipt_number) . '</div>
            </div>
            <div class="details-row">
                <div class="details-label">Date:</div>
                <div class="details-value">' . esc_html($receipt->receipt_date) . '</div>
            </div>
            <div class="details-row">
                <div class="details-label">Received From:</div>
                <div class="details-value">' . esc_html($receipt->client_name) . '</div>
            </div>
            <div class="details-row">
                <div class="details-label">Payment Method:</div>
                <div class="details-value">' . esc_html(ucfirst(str_replace('_', ' ', $receipt->payment_method))) . '</div>
            </div>
            <div class="details-row">
                <div class="details-label">Status:</div>
                <div class="details-value">' . esc_html(ucfirst($receipt->status)) . '</div>
            </div>
        </div>

        <div class="amount-box">
            <div class="label">Amount Received</div>
            <div class="value">KES ' . number_format($receipt->amount, 2) . '</div>
        </div>

        ' . (!empty($receipt->notes) ? '<div style="margin-top: 20px; padding: 10px; background-color: #f9f9f9;"><strong>Notes:</strong><br>' . nl2br(esc_html($receipt->notes)) . '</div>' : '') . '

        <div class="footer">
            ' . esc_html($branding['footer_text']) . '
            <p style="margin-top: 10px;">Generated on ' . current_time('Y-m-d H:i:s') . '</p>
            <p>Thank you for your payment!</p>
        </div>
    </body>
    </html>';

    return $html;
}
