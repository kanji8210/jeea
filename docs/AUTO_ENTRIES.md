# Automated Entries Module

## Purpose

The Automated Entries module reduces manual cost capture by sourcing financial entries
from multiple inputs: receipt OCR scan, approved timesheets, and direct manual entry.
All entries land in a unified `jinsing_expenses` table with a source and confidence score,
giving Finance Officers a single ledger regardless of how a cost arrived.

## Entry Sources

| Source | Value | How it is created |
|---|---|---|
| `manual` | User-entered form in Expenses tab | POST /jinsing/v1/auto/entries |
| `ocr` | Receipt photo or PDF scanned | POST /jinsing/v1/auto/process-receipt |
| `timesheet_auto` | Approved worker timesheet | `jinsing_timesheet_approved` hook |
| `mpesa` | M-Pesa payment confirmation | Reserved for future integration |
| `api` | External push via REST | Reserved for future integration |

## Database Tables

### `jinsing_expenses`

Primary cost ledger. Every automated entry creates a row here.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary key |
| `project_id` | BIGINT UNSIGNED | Optional; NULL = company overhead |
| `vendor` | VARCHAR(255) | Merchant or payee |
| `amount` | DECIMAL(12,2) | Net amount (KES) |
| `vat` | DECIMAL(12,2) | VAT portion extracted by OCR if available |
| `date` | DATE | Transaction date |
| `cost_code` | VARCHAR(100) | See cost code table below |
| `description` | TEXT | Free text |
| `source` | ENUM | `manual`, `ocr`, `timesheet_auto`, `mpesa`, `api` |
| `source_id` | BIGINT UNSIGNED | ID of source record (queue row, timesheet, etc.) |
| `created_by` | BIGINT UNSIGNED | WP user ID |
| `created_at` | DATETIME | Auto-set on insert |

### `jinsing_ocr_queue`

Holds uploaded receipt files before and during OCR processing.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary key |
| `file_path` | VARCHAR(500) | Absolute server path (wp-uploads) |
| `file_type` | ENUM | `receipt`, `invoice`, `po`, `other` |
| `project_id` | BIGINT UNSIGNED | Inherited from upload context |
| `processing_status` | ENUM | `queued`, `processing`, `completed`, `failed` |
| `extracted_json` | LONGTEXT | Raw OCR output stored as JSON |
| `error_message` | TEXT | Set on failure |
| `queued_by` | BIGINT UNSIGNED | WP user ID |
| `processed_at` | DATETIME | Set when OCR completes |
| `created_at` | DATETIME | Auto-set on insert |

### `jinsing_auto_entry_logs`

Audit trail linking each automated entry to its originating action.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED | Primary key |
| `trigger_type` | VARCHAR(100) | `ocr_receipt`, `timesheet_approval`, etc. |
| `trigger_id` | BIGINT UNSIGNED | ID of the triggering record |
| `created_entity_type` | VARCHAR(100) | Always `expense` currently |
| `created_entity_id` | BIGINT UNSIGNED | ID of the created `jinsing_expenses` row |
| `metadata` | LONGTEXT | JSON payload with rates, hours, confidence info |
| `confidence_score` | DECIMAL(5,4) | 0.0 - 1.0; 1.0 = human-confirmed |
| `status` | VARCHAR(50) | `auto_approved`, `pending_review`, `rejected` |
| `created_at` | DATETIME | Auto-set on insert |

## REST API

Base path: `/wp-json/jinsing/v1`

All endpoints require `is_user_logged_in`. Pass the WPGraphQL JWT in the
`Authorization: Bearer <token>` header, or rely on WP cookie auth for admin sessions.

### GET /auto/entries

Returns paginated expenses joined with their auto-entry log row.

**Query parameters:**

| Param | Type | Default | Description |
|---|---|---|---|
| `page` | integer | 1 | Page number |
| `per_page` | integer | 50 | Max 100 |
| `project_id` | integer | - | Filter by project |
| `source` | string | - | Filter by source enum value |

**Response:** Array of expense rows with `auto_status` and `confidence_score` appended.

### POST /auto/entries

Creates a manual expense entry.

**Body (JSON):**

| Field | Type | Required | Description |
|---|---|---|---|
| `date` | string | Yes | ISO date `YYYY-MM-DD` |
| `description` | string | Yes | Free text |
| `amount` | number | Yes | KES amount, must be >= 0 |
| `cost_code` | string | No | Defaults to `MATERIALS_OTHER` |
| `project_id` | integer | No | Links to project; omit for overhead |

**Response:** The created expense row.

### POST /auto/process-receipt

Uploads a receipt image or PDF, runs synchronous OCR, and creates an expense row.

**Body (multipart/form-data):**

| Field | Type | Required | Description |
|---|---|---|---|
| `receipt` | file | Yes | JPEG, PNG, WebP, or PDF. Max 10 MB. |
| `project_id` | integer | No | Project context for the expense |

**Response:**

```json
{
  "success": true,
  "message": "Receipt processed successfully.",
  "queue_id": 42,
  "expense_id": 107
}
```

If OCR has not yet completed synchronously, `expense_id` will be `null` and
`message` will indicate the receipt is queued.

## Cost Code Reference

Keyword rules applied during auto-categorization (`Jinsing_AutoEntryEngine::categorize_expense`):

| Code | Keywords matched |
|---|---|
| `MATERIALS_CEMENT` | cement, concrete, screed |
| `MATERIALS_STEEL` | steel, iron, rebar, rod |
| `MATERIALS_TIMBER` | timber, wood, lumber, plywood |
| `MATERIALS_TILES` | tile, tiles, ceramic, granite |
| `EQUIPMENT_FUEL` | fuel, petrol, diesel, petroleum |
| `EQUIPMENT_HIRE` | hire, rental, crane, excavator |
| `LABOUR` | labour, labor, fundi, casual |
| `TRANSPORT` | transport, delivery, logistics, freight |
| `MATERIALS_OTHER` | Default (no keyword match) |

## Timesheet Auto-Entry

When a timesheet is approved, WordPress fires the action:

```php
do_action('jinsing_timesheet_approved', $timesheet_id);
```

The handler `jinsing_handle_timesheet_approved()` calls
`Jinsing_AutoEntryEngine::process_timesheet_approval($timesheet_id)`.

This method:
1. Joins the timesheet row with the worker record
2. Calculates `labour_cost = hours_worked * (daily_rate / 8)`
3. Inserts a row into `jinsing_expenses` with `source = 'timesheet_auto'`
4. Logs to `jinsing_auto_entry_logs` with `confidence_score = 1.0` and `status = auto_approved`

## GraphQL (Workers and Suppliers)

Workers and Suppliers are managed via WPGraphQL. The React front-end uses Apollo Client.

### Worker type fields

`id`, `fullName`, `nationalId`, `nssfNumber`, `nhifNumber`, `skillType`,
`dailyRate`, `phone`, `isActive`, `createdAt`, `updatedAt`

### Supplier type fields

`id`, `name`, `kraPin`, `contactName`, `contactEmail`, `contactPhone`,
`paymentTerms`, `createdAt`, `updatedAt`

### Mutations

All mutations require the `manage_construction_projects` capability.

- `createWorker`, `updateWorker`, `deleteWorker`
- `createSupplier`, `updateSupplier`, `deleteSupplier`

## React Front-End (AutoEntriesPage)

File: `jinsing/src/pages/AutoEntriesPage.jsx`

Four tabs:

| Tab | Purpose |
|---|---|
| Expenses | View all entries; inline manual-add form |
| Receipt Scan | Drag-and-drop receipt upload with OCR flow |
| Workers | Worker list; create/deactivate via GraphQL |
| Suppliers | Supplier list; create via GraphQL |

Apollo Client is configured in `jinsing/src/lib/apollo.js` using the JWT stored
under `localStorage` key `jeea_graphql_jwt`.

REST calls use `apiFetch()` with a WP nonce sourced from `window.jinsing?.nonce`.

## Permissions Summary

| Action | Required capability |
|---|---|
| View entries (REST GET) | `is_user_logged_in` |
| Create manual entry (REST POST) | `is_user_logged_in` |
| Upload receipt (REST POST) | `is_user_logged_in` |
| Create / update / delete worker | `manage_construction_projects` |
| Create / update / delete supplier | `manage_construction_projects` |

## File Locations

| File | Role |
|---|---|
| `jeea/includes/api/auto-entries.php` | REST endpoints + `Jinsing_AutoEntryEngine` class |
| `jeea/includes/db/tables.php` | `jinsing_expenses`, `jinsing_ocr_queue`, `jinsing_auto_entry_logs` table definitions |
| `jeea/includes/db/workers.php` | Worker CRUD functions |
| `jeea/includes/db/suppliers.php` | Supplier CRUD functions |
| `jeea/includes/graphql/schema.php` | Worker + Supplier GraphQL types, queries, mutations |
| `jinsing/src/pages/AutoEntriesPage.jsx` | React 4-tab UI |
| `jinsing/src/styles.css` | CSS for tab bar, receipt upload, flow steps |
