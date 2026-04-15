# Symfony Technical Project Structure Guide

This document explains how this project is organized, how Symfony runs it, and what each main folder is responsible for.

## 1. Stack and Architecture Snapshot

- Framework: Symfony 6.4
- Language: PHP 8.1+
- Persistence: Doctrine ORM + Doctrine Migrations
- Templates: Twig
- Frontend assets: AssetMapper + Importmap + Stimulus/Turbo
- Testing: PHPUnit
- Domain: HR recruitment platform (Admin, Recruiter, Candidate flows)

The architecture is mostly MVC:

- Controllers in `src/Controller` orchestrate use cases.
- Entities in `src/Entity` map database tables.
- Repositories in `src/Repository` hold query logic.
- Twig templates in `templates` render HTML views.

## 2. Symfony Request Lifecycle in This Project

1. Request enters through `public/index.php`.
2. Kernel bootstraps from `src/Kernel.php` and loads bundles from `config/bundles.php`.
3. Routes are discovered from controller attributes because `config/routes.yaml` points to `src/Controller`.
4. Services are autowired/autoconfigured by `config/services.yaml`.
5. Controller action executes business logic and calls Doctrine repositories/entities.
6. Controller returns a Twig-rendered response (or redirect/JSON).

Additional runtime behavior:

- `src/EventSubscriber/SessionAccessSubscriber.php` enforces session-based access control before controller execution.

## 3. Root Folder Guide

### `assets/`

Frontend source files before serving:

- `app.js`, `bootstrap.js`: JS entry and Stimulus wiring.
- `styles/`: source CSS.
- `controllers/`: Stimulus controllers.

Used by AssetMapper (`config/packages/asset_mapper.yaml`) and Importmap (`importmap.php`).

### `bin/`

Executable entry points:

- `bin/console`: Symfony CLI command runner.
- `bin/phpunit`: test runner wrapper.

### `config/`

Main application and bundle configuration.

- `bundles.php`: enabled Symfony/third-party bundles.
- `routes.yaml`: attribute route discovery.
- `services.yaml`: service container defaults and autowiring.
- `packages/`: per-component config (framework, doctrine, security, twig, etc.).
- `routes/`: extra route loaders (for example logout route loader).

Important files:

- `config/packages/doctrine.yaml`: database + ORM mapping.
- `config/packages/security.yaml`: baseline security config.
- `config/packages/framework.yaml`: core Symfony behavior (sessions, errors, etc.).
- `config/packages/twig.yaml`: template configuration.

### `migrations/`

Doctrine migration classes. Each file records schema changes over time and is applied with Doctrine migrations commands.

### `public/`

Web root served by the PHP server.

- `index.php`: front controller (entrypoint).
- `assets/`, `css/`, `js/`, `images/`: public static files.
- `uploads/`: runtime user-uploaded files (CVs, application files).

### `src/`

Main PHP application code.

- `Kernel.php`: app kernel class.
- `Controller/`: route endpoints and web actions.
- `Entity/`: Doctrine entity classes.
- `Repository/`: Doctrine repositories.
- `Form/`: Symfony Form types and validation wiring.
- `EventSubscriber/`: request/event interception logic.
- `Command/`: custom Symfony console commands.

### `templates/`

Twig views, grouped by feature or role:

- `base.html.twig`: global base layout.
- `auth/`: login/register pages.
- `front/`: front-office pages (candidate/recruiter/admin front).
- `admin/`: back-office admin pages.
- `management/`: feature-specific management views.
- `shared/`: reusable fragments.

### `tests/`

PHPUnit bootstrap and test suites. Current structure includes bootstrap setup and is ready for additional functional/unit tests.

### `translations/`

Translation resources for i18n/localization.

### `var/`

Runtime generated files:

- `var/cache/`: Symfony cache.
- `var/log/`: log files.

### `vendor/`

Composer-managed dependencies. Do not edit manually.

## 4. Controller Layer: How Features Are Split

The controller layer is split by access zone and business module.

- `AuthController`, `LoginController`, `RegistrationController`: entry/auth/session workflows.
- `FrontOfficeController`, `FrontPortalController`, `CandidateController`, `RecruiterController`: front-office role flows.
- `BackOfficeController`, `AdminFrontController`: admin dashboard and management UI.
- `src/Controller/Management/...`: module-focused controllers by domain:
  - Event
  - Interview
  - JobApplication
  - JobOffer
  - User

This gives a mixed style:

- "Portal" controllers with broad multi-feature actions.
- "Management" namespace for modular admin/recruiter operations.

## 5. Data Model Layer (Doctrine)

Entity model follows database-first patterns in several files (snake_case class/file naming).

Key design detail:

- `Users` is the root user entity with Doctrine JOINED inheritance.
- `Candidate`, `Recruiter`, and `Admin` extend `Users`.
- Discriminator column `discr` decides actual subtype.

Domain entities include:

- job offers and offer skills
- job applications and status history
- interviews and feedback
- recruitment events and registrations
- warnings/corrections for moderation workflows

Repositories in `src/Repository` contain query helpers and are autowired where needed.

## 6. Security Model in This Codebase

Important: this project uses a hybrid approach.

- Symfony Security bundle is installed and configured.
- Authentication logic is currently implemented manually in `LoginController` using session values (`user_id`, `user_roles`, etc.).
- Access protection is enforced by `SessionAccessSubscriber` (route filtering and redirects).

This means role enforcement is partly custom (subscriber/session checks), not only firewall/authenticator based.

## 7. Forms and Validation

Symfony Form types live in `src/Form`.

- `JobApplicationType` handles candidate application form fields and custom validation (phone and cover letter).
- `ProfileType` handles editable profile fields and password update input.

Validation is a mix of:

- form constraints
- custom callback constraints
- manual request validation in controller methods (for some auth flows)

## 8. Frontend Asset Flow

This project uses modern Symfony AssetMapper instead of a Node build chain.

- JS entrypoint: `assets/app.js`
- Import map: `importmap.php`
- Asset paths: `config/packages/asset_mapper.yaml`

Twig includes assets through `importmap()` helpers from layouts.

## 9. Operations and Infrastructure Files

- `compose.yaml` + `compose.override.yaml`: container definitions (database + mail tooling).
- `.env`: local environment variables (app env, DB URL, mailer DSN, messenger DSN).
- `phpunit.dist.xml`: test configuration.
- `rh (2).sql`: SQL dump/snapshot for database state.

Note: `compose.yaml` defines a Postgres service, while current `.env` uses a MariaDB URL. Keep environment/database choice consistent per setup.

## 10. Useful Day-to-Day Commands

- Start local server:
  - `php -S 127.0.0.1:8000 -t public`
- Show routes:
  - `php bin/console debug:router`
- Run migrations:
  - `php bin/console doctrine:migrations:migrate`
- Run tests:
  - `php bin/phpunit`
- Clear cache:
  - `php bin/console cache:clear`

## 11. Technical Reading Order (Recommended)

If you want to understand this codebase quickly, read in this order:

1. `config/routes.yaml` and route attributes in `src/Controller`.
2. `src/EventSubscriber/SessionAccessSubscriber.php` and login/register controllers.
3. Core entities (`Users`, `Candidate`, `Recruiter`, `Job_offer`, `Job_application`, `Interview`).
4. Module controllers under `src/Controller/Management`.
5. Twig layouts (`templates/base.html.twig`, `templates/admin/base_admin.html.twig`) then feature templates.

This gives you a full view from HTTP entry -> auth -> business logic -> persistence -> rendering.