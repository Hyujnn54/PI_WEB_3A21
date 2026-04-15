# Application Management Porting Plan (Java to Symfony Web App)

Based on the original Java desktop application (located at `E:\pidev\PI_java_web`), this document outlines the roadmap and requirements for rebuilding the **Application Management** features in our Symfony back-end.

## Routing Convention
**Important Rule**: All controllers and routes related to this module (candidates submitting applications, recruiters reviewing stats, histories) MUST use the global URL prefix `/applicationmanagement`.

## 1. Domain Entities & Database Schema

The Symfony project already incorporates the matching Doctrine entities. We need to ensure that the relational mappings perfectly handle deletions (OnDelete CASCADE) and correctly interact with the application logic.

### 1.1 `Job_application` (Job Application)
- **Properties**: `id`, `offer_id` (ManyToOne Job_offer), `candidate_id` (ManyToOne Candidate), `phone`, `cover_letter`, `cv_path`, `applied_at`, `current_status`, `is_archived`.
- **Default behavior on creation**:
  - `applied_at` = `new \DateTime()` 
  - `current_status` = `SUBMITTED`
  - `is_archived` = `false`

### 1.2 `Application_status_history` (Status History)
- **Properties**: `id`, `application_id` (ManyToOne Job_application), `status`, `changed_at`, `changed_by` (ManyToOne Users/Admin), `note`.
- **Default behavior on creation**:
  - `changed_at` = `new \DateTime()`

---

## 2. Core Business Components

### 2.1 File Management (Resumes)
- **CV Upload Service**: Create an abstraction (e.g., `FileUploaderService`) to handle PDF/Doc uploads for `cv_path`. Ensure it uploads to `public/uploads/applications` safely, hashing file names to prevent conflicts.

### 2.2 Application Service
Porting `Services\application\ApplicationService.java`. We need an `ApplicationService` class or `JobApplicationRepository` custom methods that centralize:
1. **`hasAlreadyApplied($offerId, $candidateId)`**: Prevent multiple active applications for the same candidate on the same job offer (`is_archived = false`).
2. **Archiving Logic**: An endpoint or action to toggle the `is_archived` status.

### 2.3 Status Transition & History Logging
Porting `Services\application\ApplicationStatusHistoryService.java`.
In Symfony, a Doctrine **EntityListener** or a custom domain service (`ApplicationStatusHandlerService`) should intercept status changes:
1. Receives the `Job_application`, the `$newStatus`, the `$changedBy` User object, and an optional `$note`.
2. Updates `current_status` on the `Job_application`.
3. Instantiates a new `Application_status_history` entry, links the relationships, sets `$changed_at = now`, and persists.

### 2.4 Email Notifications
Porting `EmailServiceApplication.java`.
- **Mailer Configuration**: Configure Symfony Mailer (e.g., matching the `tunisiatour0@gmail.com` Gmail SMTP credentials or standardizing with `MAILER_DSN` in `.env`).
- **Templates**: Need Twig email templates:
  - Application submission confirmation for the Candidate.
  - Status progression updates (when a Recruiter changes status to "INTERVIEW_SCHEDULED", "ACCEPTED", "REJECTED").

---

## 3. UI and Controllers

### 3.1 Front Office (Candidate Portal)
- **`FrontOfficeController.php` (or `JobOfferController::apply`)**:
  - Requires a candidate to be fully authenticated.
  - Display the application form (cover letter textarea, CV file input, phone number).
  - Validations: Candidate hasn't already applied, file is PDF (max size 5MB), text lengths.
  - Submission triggers creation -> Email.

### 3.2 Back Office / Management (Recruiter/Admin Portal)
Replacing: `AdminApplications.fxml`, `Applications.fxml`, `AdminApplicationStatistics.fxml`.
- **`BackOfficeController.php` (or a dedicated `ApplicationManagementController`)**:
  - **Grid View**: A data table listing all applications for job offers belonging to the authenticated Recruiter.
  - **Filters**: By `Job Offer`, `Status`, `Archived/Unarchived`.
  - **Actions**:
    - Download / View CV.
    - View full cover letter.
    - View Status History modal or page (`Application_status_history`).
    - Change Status (Dropdown menu + Note field modal).
    - Archive Application.
- **Statistics View**: Replicate what `ApplicationStatisticsService` provided (charts/stats of applications per offer, status distribution). Can be rendered with Chart.js injected through Symfony UX.

---

## 4. Next Implementation Steps Roadmap

1. [ ] **Forms Setup**: Create `JobApplicationType` for processing candidate form submissions (handled in Front-Office).
2. [ ] **File Uploader Configuration**: Setup the upload directory for CVs.
3. [ ] **Emailer Setup**: Setup Symfony Mailer service and the base Twig email templates.
4. [ ] **Service Layer Builder**: Implement the domain services:
   - `JobApplicationManager`
   - `StatusHistoryManager`
   - Custom constraints (e.g., `UniqueApplication` validator).
5. [ ] **Back-Office Views**: Develop the Twig grids and templates for the recruiters to browse and change statuses.
6. [ ] **Status History Implementation**: Wire up the "Change Status" form to ensure it logs directly to the database via `StatusHistoryManager`.