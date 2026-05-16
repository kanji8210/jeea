# JINSING Platform Blueprint

## Purpose

This document is the working product and architecture standard for JINSING.
It defines the target module set, role model, core data domains, implementation phases, and delivery priorities for the platform.

The immediate goal is to move from a basic project-tracking plugin into a permission-driven construction operations platform that supports portfolio oversight, project delivery, financial control, and field execution.

## Product Position

JINSING should be designed as a construction operations platform for multi-project delivery, with strong emphasis on:

- centralized project oversight
- role-based operational control
- document traceability
- finance and cost visibility
- field-to-office workflows
- Kenya-aware compliance readiness

## Strategic Product Layers

### Layer 1: Platform Foundation

This layer must exist before advanced modules become reliable.

- identity and access management
- company, project, and participant master data
- workflow engine for approvals and status transitions
- document storage, versioning, and audit trail
- notification and activity logging
- policy and compliance metadata

### Layer 2: Operational Core

This is the baseline MVP surface.

- project and portfolio dashboard
- document management / common data environment
- RFI management
- submittal management
- cost and financial management
- schedule and milestone management
- mobile-friendly field capture

### Layer 3: Commercial and Project Controls

- tendering and bid management
- procurement and supplier coordination
- change order control
- progress billing and payment workflows
- equipment and resource planning

### Layer 4: Strategic Intelligence

- quality and safety analytics
- risk and compliance management
- forecasting and anomaly detection
- BIM integration and 4D/5D coordination
- executive reporting and what-if analysis

## Target Role Model

JINSING should standardize on the following core roles.

### 1. System Administrator

Scope:
- full platform control
- global configuration
- role and permission management
- all company and project data

### 2. Company Executive / Board

Scope:
- read-only portfolio oversight
- executive approvals above defined thresholds
- high-level dashboards, profitability, and risk views

### 3. Project Manager

Scope:
- full project delivery control
- project budgets, schedule, RFIs, submittals, risks, team coordination
- project-level approvals

### 4. Site Supervisor / Site Engineer

Scope:
- daily logs, site progress, issues, photos, field approvals
- limited finance visibility
- strong mobile and offline workflow support

### 5. Safety & Quality Officer

Scope:
- incidents, near-misses, inspections, punch lists, quality checklists
- read-only access to schedule and documents as needed

### 6. Client / Project Owner

Scope:
- read-only project visibility
- invoice and change-order review/approval surface

### 7. Finance Officer / Accountant

Scope:
- budget control, cost capture, billing, taxes, supplier payment workflows
- no operational ownership of site execution modules

### 8. Project Administrator

Scope:
- document libraries, correspondence, logs, meeting minutes, admin support
- limited finance visibility

### 9. External Parties

This group should be split in implementation even if grouped functionally in product language.

- Subcontractor: scoped access to assigned project packages, RFIs, submittals, claims
- Supplier: scoped access to procurement, purchase orders, delivery notes, invoices

## Kenya-Specific Supporting Roles

JINSING should reserve future roles for Kenya compliance and local procurement operations.

### Kenya Compliance Officer

Scope:
- NCA registration tracking
- license renewal tracking
- KRA reporting coordination
- audit package readiness
- contractor-category validation against project size or classification

This can initially be implemented as a capability bundle assigned to System Administrator or Finance Officer until a dedicated role is needed.

### Material Supplier (Kenya Context)

In the Kenyan construction market, bulk material suppliers (e.g., a ready-mix concrete supplier)
have coordination needs that differ from general suppliers. A dedicated role can streamline
procurement by allowing them to view relevant production or delivery schedules to better
coordinate large or time-sensitive deliveries.

Scope:
- view assigned purchase orders and delivery schedules
- confirm delivery quantities and dates
- no access to project budgets, team data, or internal documents

### Public / Observer

A sign-off or public-facing role for clients, auditors, or regulatory bodies such as the NCA
who need view-only access to a final, signed-off package of project documents.

Scope:
- read-only access to approved document packages
- no access to live project data, financials, or team records
- typically scoped to a single deliverable package, not a whole project

### Collaborative Access Notes

For collaborative workflows such as RFIs and submittals, roles like Subcontractor and
External Consultant may need "Consulted" or "Informed" access granted on a per-project basis
rather than as a global capability. The permission model should support project-scoped
access tiers beyond simple read/write.

## Current Implementation vs Target

Current repo state includes these custom roles:

- Construction Director
- Construction Project Manager
- Construction Site Engineer

This is a valid starting point, but it is not yet enough for the target platform. The next role expansion should add:

- executive viewer
- finance officer
- project administrator
- safety and quality officer
- client owner portal role
- subcontractor
- supplier
- material supplier (Kenya context)
- public / observer (NCA and regulatory sign-off)

## Permission Design Standard

Do not model permissions only as page access. Use capability groups by domain.

### Recommended capability domains

- platform administration
- company oversight
- project administration
- document management
- RFI management
- submittal management
- finance and cost control
- procurement
- schedule management
- quality and safety
- risk and compliance
- reporting and analytics
- mobile field operations

### Permission rules

- global roles should not automatically imply write access everywhere
- project roles should support project-scoped access, not only global access
- external parties must be restricted by project, contract, or document package
- approval permissions must be separate from edit permissions
- finance visibility must be tiered: none, summary, project detail, company detail

## Core Modules and Boundaries

### 1. Project & Portfolio Dashboard

Purpose:
- unified view of project health, cost, schedule, RFIs, risks, and approvals

Minimum outputs:
- project status summary
- budget vs actual snapshot
- milestone health
- open RFIs and pending approvals
- portfolio rollup for executives

### 2. Document Management / CDE

Purpose:
- single source of truth for all project documentation from conception through handover

#### Conception Documents

Pre-construction and design-phase documents are stored as a dedicated document class,
linked to the project record. Each document can carry an external URL (e.g., a hosted
render, a Google Drive plan, a county permit portal link) alongside or instead of a
locally uploaded file.

| Document Type | Description |
|---|---|
| Architectural Renders | 3D or 2D visualisations of the completed project; stored as images or linked to an external render service |
| Approved Plans | Stamped architectural and structural drawings approved by the relevant authority |
| Building Permits | County government or NCA permit documents with permit number, issue date, and expiry date |
| Environmental Impact Assessment (EIA) | NEMA approval documents where required |
| Structural Reports | Soil test reports, structural calculations, geotechnical surveys |
| Survey / Title Documents | Land survey plans, title deed scans, mutation documents |
| Client Brief | Scope of work, project brief, or feasibility study agreed with the client |

Each conception document record stores:
- `document_type` (from the list above)
- `title` and `description`
- `file_id` (WordPress attachment ID, optional)
- `external_url` (link to render, permit portal, or cloud storage, optional)
- `reference_number` (permit number, drawing number, etc.)
- `issue_date` and `expiry_date`
- `issued_by` (authority or firm name)
- `project_id`
- `uploaded_by` and `created_at`

#### General Document Features

- version history
- document categories (conception, design, construction, handover, legal, financial)
- project-linked storage
- approval workflow
- audit trail
- permission by role and package

### 3. RFI & Submittal Management

Purpose:
- formal communication and material/design approval workflow

#### RFI Workflow

Status lifecycle: `draft` -> `submitted` -> `answered` -> `closed` (or `rejected`)

| Feature | Description |
|---|---|
| Create RFI | Question text, file attachments, urgency level, due date; assigned to architect or engineer |
| RFI Response | Answer with supporting documents; auto-notifies creator; if answer implies cost or time impact, system suggests creating a change order |
| Change Order Link | "Create Change Order" button on any answered RFI, pre-filled with description and cost estimate |
| Email Notifications | Instant email and in-app notification to the assigned person via WordPress email system |

#### Submittal Log

Status lifecycle: `draft` -> `submitted` -> `under review` -> `approved` (or `rejected`)

Tracks material submittals and shop drawing approvals. Each submittal records the
responsible submitter, reviewer, revision number, and approval date.

#### Required Linkage (both RFIs and Submittals)

- project
- drawing or document reference
- responsible party
- due date and SLA
- cost impact flag
- schedule impact flag

### 4. Cost & Financial Management

Purpose:
- control project and organization spending across the full financial lifecycle

#### Budget Setup

Budgets are defined per project using cost codes (e.g., materials, labour, equipment,
subcontractors). Can be created by uploading an Excel/CSV file or entered manually.
Stored in `jinsing_budget_items`.

#### Real-Time Cost Tracking

Every expense -- whether entered manually, created via OCR, or imported from a bank/M-Pesa
feed -- automatically deducts from the relevant cost code. The React dashboard shows live
variance per code: Budget / Spent / Remaining.

#### Automatic Expense Entry

| Method | Description |
|---|---|
| Receipt OCR | Upload photo or PDF; extract vendor, amount, date, VAT using Google Vision or Tesseract.js; frontend upload via `uploadReceipt` mutation (see AUTO_ENTRIES.md); includes camera capture button that opens the device camera directly on mobile (`capture="environment"`) |
| Bank / M-Pesa Import | Connect to bank or M-Pesa API, or upload CSV; auto-match transactions to cost codes |
| Voice Expense (future) | "Bought 5 bags of cement at 700 KES" parsed by NLP to create an expense record |

#### Expense Categorisation

AI suggests a cost code based on vendor name and item description. User confirms or
corrects the suggestion. The model improves over time from confirmed corrections.
Current keyword rules are in `Jinsing_AutoEntryEngine::categorize_expense()`.

#### Full Feature Set

| Feature | Description |
|---|---|
| Change Order Management | Change order linked to an RFI; tracks added cost; approval workflow (PM -> client); updates budget automatically; stored in `jinsing_change_orders` |
| Retention Tracking | Monitor retention amount (e.g., 5% of each invoice); shows retention released vs held; automatically generates retention invoice when release conditions are met |
| Payment Tracking | Record payments received from client and payments made to suppliers/subcontractors; linked to invoices and purchase orders |
| Multi-Currency | Support KES, USD, UGX, and others; exchange rates updated daily via API (e.g., Central Bank of Kenya) |

#### Ledger Standard

- every financial record may link to a project
- if no project is linked, classify as company overhead
- every record must have: category, type, date, amount, approval status, and source

### 5. Schedule & Work Planning

Purpose:
- manage milestones, dependencies, and execution progress

Minimum features:
- milestone plan
- baseline vs current dates
- critical task visibility
- responsibility assignment
- delay reason tracking

### 6. Invoicing & Billing

Purpose:
- manage the full client billing cycle from invoice generation through payment collection and tax reporting

#### Invoice Generation

Invoices can be created from four sources:

| Method | Description |
|---|---|
| Progress billing | Percentage of completion derived from milestones or daily logs |
| Milestone billing | Fixed amount triggered automatically on milestone completion |
| Time & materials | Aggregated from approved timesheets and material issue records |
| Manual | Custom line items entered directly |

#### KRA-Compliant Invoice Templates

Templates include KRA PIN, VAT breakdown (16%), withholding tax where applicable, TIN,
and ETR-like sequential invoice numbering. PDFs generated via `react-pdf` and stored in
the WordPress media library.

#### Full Feature Set

| Feature | Description |
|---|---|
| Invoice Approvals | Workflow: creator -> PM -> finance -> client; every approval recorded in audit log |
| Send Invoices | Email PDF to client via `wp_mail` or SMTP; optional open-rate tracking |
| Payment Integration | "Pay Now" button linked to M-Pesa (Lipa Na M-Pesa / Pesapal) or direct bank; status updates automatically on payment confirmation |
| Invoice Status Dashboard | Views for draft, sent, viewed, paid, overdue, and partially paid; filterable by client, project, and date range |
| Credit Notes | Issue credit note against an invoice, adjust amount, maintain full audit trail |
| Recurring Invoices | Automatic generation and sending on schedule for retainer contracts or equipment rental |
| VAT / Tax Reports | Periodic VAT summary and withholding tax summary for KRA; export in iTax format (CSV/XML) for direct upload |

### 4. Procurement & Inventory Management

Purpose:
- control the full material supply chain from requisition to goods receipt and stock tracking

#### Procurement Workflow

Status lifecycle: `draft` -> `pending_approval` -> `approved` -> `po_sent` -> `partially_fulfilled` -> `closed`

| Feature | Description |
|---|---|
| Vendor/Supplier Registry | Centralised database of suppliers with contact details, KRA PIN, payment terms, and performance score |
| Purchase Requisition | Site staff creates a requisition (item, quantity, required date); approval workflow from foreman to procurement |
| Purchase Order | Auto-generated from an approved requisition; PDF sent to vendor; PO status tracked as sent, accepted, partially fulfilled, or closed |
| Goods Receipt Note (GRN) | Mobile-friendly GRN with camera capture of delivered goods; compared against PO; automatically updates inventory on confirmation |
| Supplier Invoice Matching | OCR supplier invoice and compare against PO and GRN; flag discrepancies in quantity or price |
| Material Price Tracking | Manually entered or scraped from Kenyan suppliers (e.g., Tononoka, Jumbo) to show market trends; AI predicts future price movement |

#### Inventory Management

Each storage location (godown or site store) is tracked independently. A company may
operate multiple stores across different sites; stock levels, movements, and alerts are
maintained per location rather than pooled.

The Jinsing admin (System Administrator / Company Executive) has a cross-location
aggregate view showing total stock across all stores, flagged shortages, and pending
transfer requests without being locked to a single location.

| Feature | Description |
|---|---|
| Location Registry | Named storage locations (e.g., "Nairobi Main Godown", "Mombasa Site Store"); linked to project or company |
| Stock Levels | Track current quantity of each material (cement, steel, fuel, etc.) per location |
| Stock Movements | Every GRN increases stock; every site issuance or transfer decreases it; full movement history |
| Low Stock Alerts | Configurable minimum thresholds per material per location; notifications to procurement officer |
| Inter-store Transfers | Record material transfers between locations with approval and audit trail |
| Admin Aggregate View | Cross-location dashboard: total stock per material, combined shortages, and transfer activity across all stores |

### 5. Resource Management

Purpose:
- manage the human and equipment resources deployed across projects

#### Labour & Workers

| Feature | Description |
|---|---|
| Worker Database | Worker details: national ID, NSSF number, NHIF number, skills, daily rate, contact (see `jinsing_workers` table) |
| Daily Timesheets | Mobile or web form: worker check-in/out, task, hours worked, approved by site supervisor; stored in `jinsing_timesheets` |
| Payroll Integration | Export timesheets to payroll system or built-in wage calculator; supports casual and permanent workers |

#### Equipment

| Feature | Description |
|---|---|
| Equipment Registry | Owned and leased equipment (excavators, mixers, trucks); tracks usage hours, maintenance schedule, fuel consumption |
| Equipment Booking | Site requests equipment for specific dates; approval triggers automatic conflict check across all active bookings |

#### Subcontractors

| Feature | Description |
|---|---|
| Subcontractor Management | Contract details, scope of work, payment schedule, and performance evaluation |
| Compliance Documents | Store and track certificates: NCA registration, insurance, and other required accreditations |

### 6. Mobile Field Operations

Purpose:
- capture reliable site data under poor connectivity

Minimum features:
- daily logs
- photo capture
- issue reporting
- inspection forms
- offline-first sync strategy

## Advanced Modules

### Quality & Safety

- inspections
- punch lists
- defects
- incidents
- near-miss records
- corrective actions

### Risk & Compliance

- risk register
- permit/compliance checklist
- approval dependencies
- NCA and KRA audit readiness

### Tendering & Bid Management

- bid packages
- vendor invitations
- bid comparison
- subcontract award support

### Equipment & Resource Management

- equipment allocation
- maintenance schedule
- utilization tracking
- crew allocation

### BIM Integration

- model references
- issue linkage to model elements
- future 4D/5D integration

### Reporting & Analytics

| Feature | Description |
|---|---|
| Custom Dashboard | Drag-and-drop widgets: budget pie chart, upcoming milestones, open punch list counts, cash flow forecast |
| Financial Reports | Profit and loss by project, ageing accounts receivable, VAT summary, retention schedule; export to Excel and PDF |
| Productivity Report | Labour hours vs planned, equipment utilisation, material consumption rate |
| NCA Compliance Report | One-click report listing all projects with NCA registration, licence status, and inspection history |
| Export to iTax | Generate XML/CSV for VAT returns and withholding tax |
| AI-Generated Narrative | Monthly progress report written by GPT: "Project X is 45% complete, 10% behind schedule due to rain delays. Budget overspend of 3% mainly in steel." |

### AI & Automation

| Feature | Description |
|---|---|
| OCR Expense Entry | Upload receipt, extract amount/date/vendor/VAT; user confirms or corrects, then creates expense record (see AUTO_ENTRIES.md) |
| Cost Overrun Prediction | Model analyses current spend, progress, and historical data; shows probability of exceeding budget as Risk: High / Medium / Low |
| Schedule Delay Forecast | Predicts days behind schedule using weather data, material delays, and labour productivity trends |
| Anomaly Detection | Flags unusual expense spikes (e.g., 3x normal cement cost) and notifies the project manager |
| Auto-Categorisation | New vendor entry triggers cost-code suggestion based on vendor name and item description |
| Intelligent Change Orders | When an RFI answer contains "additional cost" or "extra work", auto-populates a change order draft |
| Material Price Forecast | Predicts cement and steel price movement over the next 3 months using historical local data |
| Voice Expense Assistant | Field user says "Bought 5 bags of cement for 3,500 bob" and the system creates an expense record automatically |

## Core Data Domains

JINSING should be organized around these main entities.

### Organization domain

- companies
- business units
- users
- roles
- capabilities
- contractor profiles
- NCA compliance records

### Project domain

- projects
- project metadata
- phases
- milestones
- team assignments
- stakeholders

### Document domain

- documents
- document versions
- conception documents (renders, permits, approved plans, EIA, structural reports, survey/title, client brief)
- transmittals
- approvals
- comments

### Communication domain

- RFIs
- submittals
- correspondence
- meeting minutes
- notifications

### Financial domain

- budgets
- budget lines
- cost codes
- financial entries
- invoices
- payments
- change orders
- procurement requests
- purchase orders

### Field operations domain

- daily logs
- site photos
- inspections
- punch items
- incidents
- material requests
- timesheets

### Controls domain

- risks
- compliance requirements
- approvals
- workflow states
- audit logs

## MVP Definition

The MVP should be limited to the smallest set that creates a usable operating system for projects.

### MVP modules

- project and portfolio dashboard
- document management v1
- RFI management v1
- submittal management v1
- cost and finance v1
- schedule and milestones v1
- mobile field logs v1

### MVP roles

- system administrator
- project manager
- site supervisor / site engineer
- finance officer
- project administrator
- client / project owner

### MVP exclusions for now

- external accounting integration
- BIM model federation
- AI forecasting beyond simple trend indicators
- deep equipment telemetry
- full tender leveling engine

## Delivery Phases

### Phase 1: Platform Core

- stabilize roles and capabilities
- normalize project master data
- build shared approval and audit patterns
- harden command center as portfolio entry point

### Phase 2: Documents + Communication

- CDE foundation
- RFI workflow
- submittal workflow
- project correspondence and logs

### Phase 3: Finance + Controls

- unified finance ledger
- project cost tracking and overhead tracking
- change orders
- invoice workflows
- executive finance reporting

### Phase 4: Schedule + Field

- milestone and dependency model
- daily logs
- field issue capture
- mobile-first workflow support

### Phase 5: Compliance + Intelligence

- safety and quality controls
- risk register
- compliance workflows
- executive analytics

## Implementation Standards

### Access control

- all business actions should pass capability checks
- page access is not enough; write operations must validate permissions directly
- role names are business-friendly, capabilities are system-facing

### Data modeling

- prefer explicit status fields with controlled transitions
- every operational record should link to a project unless it is truly company-wide
- use created_by, created_at, updated_at consistently
- approval records should be separate from business records when possible

### Auditability

- high-risk actions must write audit events
- finance, approvals, role changes, and compliance updates must be traceable

### Workflow design

- approvals should support pending, approved, rejected, returned
- do not bury approval state inside free-text notes
- due dates and responsible actors should be first-class fields

### UX direction

- desktop admin for management and reporting
- mobile-first task flows for site teams
- dashboards must be role-specific, not one-size-fits-all

## Immediate Build Priorities for This Repo

1. Expand the role model from 3 construction roles to the MVP role set.
2. Introduce domain-specific capabilities instead of broad page-only access.
3. Build finance as a shared ledger that supports both project-linked costs and overall overheads.
4. Add document/CDE primitives before making RFIs and submittals more complex.
5. Separate project management views from executive and finance views.

## Next Recommended Implementation Slice

The next concrete build slice should be:

### Slice A: Role and capability expansion

- define target capabilities
- add finance officer, project administrator, client owner, executive viewer
- version the role registration so upgrades apply safely

### Slice B: Unified finance ledger

- replace project-only expenditure thinking with a general finance entry model
- support project-linked entries and company overhead entries
- add category, type, approval state, and source fields

### Slice C: Finance UI surfaces

- command center finance overview
- finance officer entry and approval screen
- project manager project-cost view
- executive read-only portfolio finance summary

## Module Documentation

Detailed implementation docs for individual modules are kept alongside this blueprint.

| Module | Document |
|---|---|
| Automated Entries (expenses, OCR, timesheets, workers, suppliers) | [AUTO_ENTRIES.md](AUTO_ENTRIES.md) |

---

## Working Rule

Any new feature should be evaluated against this question:

Does it belong to a shared platform domain, or is it only being added as a local page feature?

If it belongs to a shared domain, model it at the data and permission layer first.