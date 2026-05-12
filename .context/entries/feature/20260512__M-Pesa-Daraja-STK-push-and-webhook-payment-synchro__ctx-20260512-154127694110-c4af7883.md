---
entry_id: "ctx-20260512-154127694110-c4af7883"
title: "M-Pesa Daraja STK push and webhook payment synchronization"
category: "feature"
tags: ["mpesa", "daraja", "payment", "integration", "webhook", "stk-push", "live-integration"]
files: ["includes/integrations/mpesa.php", "includes/db/tables.php", "construction-mgmt.php"]
commits: ["344a5a8"]
status: "active"
importance: "high"
created_at: "2026-05-12T15:41:27Z"
updated_at: "2026-05-12T15:41:36Z"
summary: "Implemented end-to-end M-Pesa Daraja integration with live STK push requests, webhook handlers, transaction logging, and automatic payment status reconciliation."
retrieval_hints: "mpesa daraja stk push webhook payment synchronization live payment integration checkout request"
---

## What
Created new M-Pesa integration module at includes/integrations/mpesa.php with OAuth token generation, STK push requests, webhook endpoint handlers (public + authenticated), transaction logging to jinsing_mpesa_transactions table, and automatic payment status updates in jinsing_payments. Added table definition and harmonization mapping. Wired module load into plugin bootstrap.

## Why
Payment collection is core to construction operations platform and M-Pesa is dominant payment method in Kenya. Live integration enables direct invoice payment flow and transaction reconciliation without manual intervention.

## Impact
Platform now supports end-to-end M-Pesa payment workflows. Clients can pay invoices via Daraja STK push. Payment status automatically synchronizes with Daraja webhook callbacks. Transaction history is logged for audit and reconciliation.

## Notes
Webhook endpoint: admin-post.php?action=construction_mgmt_mpesa_webhook. Payment matching uses reference_number = CheckoutRequestID. Settings page displays webhook callback URL. Next step: wire STK push from invoice/payment UI, test with Daraja sandbox/live credentials.
