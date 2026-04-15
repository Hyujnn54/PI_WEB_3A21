# Talent Bridge HR Platform

Talent Bridge is a Symfony-based HR and recruitment management platform designed to support multiple user roles across Front Office and Back Office workflows.

The project provides end-to-end recruitment features, including:
- Job offer publishing and moderation
- Candidate application lifecycle tracking
- Interview scheduling and feedback
- Recruitment events and registrations
- User and role management
- Admin analytics and dashboard monitoring

## Table of Contents
- Project Overview
- Core Functional Areas
- User Roles
- Tech Stack
- Project Structure
- Getting Started
- Database and Migrations
- Running the Application
- Testing
- Key Routes and Modules
- Development Notes
- Troubleshooting

## Project Overview
This application is structured around two major interaction zones:

1. Front Office
- Candidate, Recruiter, and Admin role-based home pages
- Role-specific operational modules
- Candidate-facing and recruiter-facing workflows

2. Back Office
- Admin dashboard for platform-wide supervision
- User administration
- Job offer and application oversight
- Interview monitoring and statistics
- Reporting and system controls

## Core Functional Areas

### 1) Job Offers
- Recruiters can create, edit, and manage offers
- Admin can inspect, moderate, and view statistics
- Offer status and deadlines are tracked

### 2) Job Applications
- Candidates apply to offers
- Recruiters review and update statuses
- Admin has broad visibility and management access
- Status history tracking is supported

### 3) Interviews
- Recruiters schedule and manage interviews
- Candidate and recruiter interview visibility
- Interview feedback and review workflows
- Interview statistics available for admin

### 4) Events and Registrations
- Recruitment event publishing and management
- Candidate registration/unregistration
- Recruiter and admin registration views

### 5) User Management
- Admin user listing, creation, editing, and deletion
- Role segmentation across Admin, Recruiter, Candidate
- Statistics pages for user distributions

## User Roles
- Candidate: applies to jobs, tracks applications, views interviews, manages profile
- Recruiter: manages offers, reviews applications, manages interviews/events
- Admin: oversees platform operations in front and back office areas

## Tech Stack
- Backend framework: Symfony 6.4
- Language: PHP 8.1+
- ORM and persistence: Doctrine ORM, Doctrine Migrations
- Frontend templating: Twig
- Frontend UI stack: Bootstrap, Tabler, custom CSS
- Optional charting: ApexCharts (already included in admin base layout)
- Testing: PHPUnit
- Containerized DB support: Docker Compose with PostgreSQL

## Project Structure
High-level layout:
- src/Controller: application controllers and role workflows
- src/Entity: Doctrine entities
- src/Repository: custom repositories
- templates: Twig templates grouped by module and role
- public: web root and built/static assets
- config: Symfony and bundle configuration
- migrations: Doctrine migration files
- tests: test bootstrap and test suites

## Getting Started

### Prerequisites
- PHP 8.1 or later
- Composer
- PostgreSQL (local or Docker)
- Symfony CLI optional but recommended

### Installation
1. Clone the repository
2. Install dependencies:
   - composer install
3. Configure environment variables in your local env configuration
4. Prepare the database

## Database and Migrations
The project uses Doctrine ORM and migrations.

Typical workflow:
- Create database
- Run migrations

If using Docker Compose, a PostgreSQL service is already defined in compose.yaml.

## Running the Application

### Option A: PHP Built-in Server
From project root, run:
- php -S 127.0.0.1:8000 -t public

Then open:
- http://127.0.0.1:8000

### Option B: Symfony CLI
If Symfony CLI is installed, run the standard local server command.

## Testing
Run tests with:
- php bin/phpunit

PHPUnit configuration is available in phpunit.dist.xml.

## Key Routes and Modules

### Entry and Authentication
- Root entry redirects based on authentication and role context
- Login and registration pages are provided in templates/auth

### Front Office Homes
- Candidate home
- Recruiter home
- Admin front-home

### Back Office
- Admin dashboard
- User management
- Job offers management and statistics
- Applications management and statistics
- Interviews management and statistics

## Development Notes
- Keep role-based logic explicit in controller actions
- Preserve route names when adjusting UI to avoid breaking navigation
- Prefer Twig template enhancements for UI improvements without affecting backend behavior
- Use shared CSS utilities for consistent visual design across modules

## Troubleshooting

### 1) Asset not visible
- Verify asset path under public or assets mapping
- Clear cache if needed

### 2) Route not found
- Confirm route names and imported controller annotations/attributes

### 3) Database errors
- Check DB credentials and active database service
- Ensure migrations are applied

### 4) Cache-related behavior
- Clear Symfony cache when changing configuration:
  - php bin/console cache:clear

---

This repository is configured as a professional Symfony project foundation for HR operations and can be extended with additional analytics, access-control policies, and workflow automations.
