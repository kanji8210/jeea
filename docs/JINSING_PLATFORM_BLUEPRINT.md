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

## Kenya-Specific Supporting Role

JINSING should reserve a future role for Kenya compliance operations.

### Kenya Compliance Officer

Scope:
- NCA registration tracking
- license renewal tracking
- KRA reporting coordination
- audit package readiness
- contractor-category validation against project size or classification

This can initially be implemented as a capability bundle assigned to System Administrator or Finance Officer until a dedicated role is needed.

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
- single source of truth for project documentation

Minimum features:
- version history
- document categories
- project-linked storage
- approval workflow
- audit trail
- permission by role and package

### 3. RFI & Submittal Management

Purpose:
- formal communication and material/design approval workflow

Minimum workflow:
- draft
- submitted
- under review
- answered or approved
- closed or rejected

Required linkage:
- project
- drawing or document reference
- responsible party
- due date and SLA
- cost impact flag
- schedule impact flag

### 4. Cost & Financial Management

Purpose:
- control project and organization spending

Minimum features:
- budget vs actual
- project-linked costs
- overall company overhead costs
- change orders
- invoice and payment tracking
- cost categories and cost codes

Ledger standard:
- every financial record may link to a project
- if no project is linked, classify as company overhead
- every record should have category, type, date, amount, approval status, and source

### 5. Schedule & Work Planning

Purpose:
- manage milestones, dependencies, and execution progress

Minimum features:
- milestone plan
- baseline vs current dates
- critical task visibility
- responsibility assignment
- delay reason tracking

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

## Working Rule

Any new feature should be evaluated against this question:

Does it belong to a shared platform domain, or is it only being added as a local page feature?

If it belongs to a shared domain, model it at the data and permission layer first.