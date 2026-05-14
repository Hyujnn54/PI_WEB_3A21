# Talent Bridge

Talent Bridge is a recruitment management platform developed as an ESPRIT integrated project. This repository contains the Symfony web extension of the Talent Bridge platform around a shared hiring workflow: job offers, applications, interviews, events, and role-based access.

## Project Scope

- Candidate, recruiter, and administrator workflows
- Job offer publishing and application tracking
- Interview scheduling, feedback, and meeting-link generation
- Recruitment events and analytics dashboards
- AI-assisted features for screening, matching, moderation, and cover-letter support
- Notifications and communication flows across the hiring process

## Stack

- **PHP 8.2+** and **Symfony 6**
- **Doctrine ORM** for database abstraction
- **Twig** template engine
- **MySQL 8** database
- **Knp Snappy** for PDF generation
- **Bazinga Geocoder** for location services
- **Hotwired Stimulus** and **Asset Mapper** for frontend interactivity
- **Docker Compose** for containerization
- **PHPUnit** for testing
- **PHPStan** for static analysis

## Technical Highlights

- Role-based application structure across user, job offer, application, interview, and event modules
- Front Office and Back Office workflows with role-specific dashboards
- Service layer handling persistence, validation, notifications, and AI-assisted features
- Multiple external integrations for geocoding, PDF generation, and file uploads
- Event subscribers and custom doctrine extensions for audit trails and tracking
- Command-based operations for background tasks
- Comprehensive form handling with validation rules
- Email notifications for recruitment events

## Repository Layout

```
src/
  Kernel.php               Application kernel
  Command/                 CLI commands for background operations
  Controller/              Request handlers for routing
  Entity/                  Domain models (User, JobOffer, Application, Interview, Event)
  EventSubscriber/         Event listeners for doctrine and application events
  Form/                    Form types and form builders
  Repository/              Database queries and data access
  Security/                Authentication and authorization logic
  Service/                 Business logic and operations
  Support/                 Helper utilities
config/
  bundles.php              Bundle configuration
  services.yaml            Service definitions
  routes/                  Route definitions per module
  packages/                Third-party bundle configuration
migrations/                Database schema migrations
templates/
  base.html.twig           Base layout template
  front/                   Candidate and recruiter-facing templates
  back/                    Administrator dashboard templates
  emails/                  Email notification templates
  pdf/                     PDF generation templates
  shared/                  Reusable template components
tests/
  Service/                 Service layer tests
  Entity/                  Entity tests
public/
  index.php                Application entry point
  uploads/                 User-uploaded files (CVs, documents)
  css/                     Compiled stylesheets
```

## Project Context

- University project developed at ESPRIT
- Desktop phase implemented in JavaFX with Java 17
- Web phase extends the same platform using Symfony 6 and PHP 8
- Designed as a multi-role system for recruitment management
- Shared relational model across desktop and web workflows

## Core Functional Areas

### 1) Job Offers
- Recruiters can create, edit, and manage job offers
- Admin can inspect, moderate, and view statistics
- Offer status and deadlines are tracked
- Candidates can search and filter available offers

### 2) Job Applications
- Candidates apply to job offers
- Recruiters review and update application statuses
- Admin has broad visibility and management access
- Application tracking and status history are maintained

### 3) Interviews
- Recruiters schedule and manage interviews
- Candidate and recruiter interview visibility with feedback workflows
- Interview statistics and analytics available for admin
- Interview outcomes tracking

### 4) Events and Registrations
- Recruitment event publishing and management
- Candidate registration and attendance tracking
- Recruiter and admin event oversight

### 5) User Management
- Admin user listing, creation, editing, and deletion
- Role segmentation across Admin, Recruiter, and Candidate
- User profile management and statistics

## User Roles

- **Candidate**: Applies to jobs, tracks applications, views interviews, manages profile
- **Recruiter**: Manages job offers, reviews applications, schedules interviews, manages events
- **Admin**: Oversees platform operations, user management, system-wide analytics and reporting

## Getting Started

### Prerequisites
- PHP 8.2 or later
- Composer
- MySQL 8 or PostgreSQL
- Docker (optional, for containerized database)

### Installation

1. Clone the repository
2. Install dependencies:
   ```
   composer install
   ```
3. Create and configure the `.env.local` file with database credentials:
   ```
   DATABASE_URL="mysql://user:password@127.0.0.1:3306/talent_bridge"
   ```
4. Create the database and run migrations:
   ```
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

### Running the Application

**Option A: PHP Built-in Server**
```bash
php -S 127.0.0.1:8000 -t public
```
Then navigate to `http://127.0.0.1:8000`

**Option B: Symfony CLI**
```bash
symfony serve
```

**Option C: Docker Compose**
```bash
docker-compose up -d
```

## Database and Migrations

The project uses Doctrine ORM for database abstraction and Doctrine Migrations for schema versioning. Migrations are located in the `migrations/` directory and track all schema changes.

To create a new migration after modifying entities:
```bash
php bin/console doctrine:make:migration
php bin/console doctrine:migrations:migrate
```

## Testing

Run the test suite with PHPUnit:
```bash
php bin/phpunit
```

PHPUnit configuration is defined in `phpunit.dist.xml`.

## Development Notes

- Role-based logic is explicitly handled in controller actions
- Route names should be preserved when adjusting UI to maintain navigation consistency
- Frontend enhancements are preferred through Twig templates without backend behavior changes
- Service layer handles business logic, persistence, and validation
- Event subscribers track important domain events and audit trails
- Custom Doctrine extensions support enhanced entity lifecycle management

## Troubleshooting

**Asset Not Visible**
- Verify asset paths under `public/` or asset mapping configuration
- Clear Symfony cache: `php bin/console cache:clear`

**Route Not Found**
- Confirm route names and controller attribute annotations

**Database Errors**
- Check database credentials in `.env.local`
- Ensure the database service is running
- Verify all migrations have been applied: `php bin/console doctrine:migrations:status`

**Cache-Related Issues**
- Clear cache when changing configuration: `php bin/console cache:clear`
- Warm up cache for production: `php bin/console cache:warmup`

## Notes

- The web phase complements the desktop application (Java/JavaFX) around a shared recruitment management domain
- This repository is part of the broader Talent Bridge platform, which includes both desktop and web applications
- Architecture allows for shared business logic and consistent user workflows across multiple interfaces
