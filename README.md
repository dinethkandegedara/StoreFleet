# StoreFleet POS

[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue.js-3.5-4FC08D?style=flat&logo=vuedotjs&logoColor=white)](https://vuejs.org)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.0-9553E9?style=flat&logo=inertia&logoColor=white)](https://inertiajs.com)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.6-3178C6?style=flat&logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

A production-quality, high-performance, multi-branch Point of Sale application built with Laravel and Vue.js, architected to run smoothly on standard shared Linux hosting (cPanel, Apache, LiteSpeed) without requiring Docker, Redis, or a persistent Node.js server process.

---

## Architecture Overview

StoreFleet uses a **Modular Monolith** pattern:
- **Backend**: Laravel 12 (PHP 8.2+) providing strict authentication, branch-scoped authorization, database transactions, and business logic.
- **Frontend**: Vue 3 (Composition API with `<script setup lang="ts">`) powered by Inertia.js for a seamless Single-Page Application (SPA) experience without API token overhead.
- **Theme Engine**: Built with Vanilla CSS Variables (`:root` / `[data-theme="dark"]`), supporting light and dark modes alongside runtime-customizable primary accent colors configured by the business.
- **Storage & Queues**: Native database-backed sessions, cache, and queues (`SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`).
- **Target Hosting**: Standard shared hosting environments where only PHP and MySQL/MariaDB are available.

---

## Identity Model & Trust Boundaries

StoreFleet strictly enforces three separate identity layers:

1. **Super Admin**: The privileged platform operator who provisions and configures the application installation.
   - Hardened with server-enforced middleware (`super_admin`), database-level isolation (`is_super_admin` guarded from mass assignment).
   - Mandatory Two-Factor Authentication (TOTP / RFC 6238) with encrypted secrets and single-use emergency recovery codes.
   - Dedicated audit logging on `/admin/audit-logs`.
2. **Business Owner**: The administrator of the tenant business and its branches.
3. **Operational Users**: Branch managers, cashiers, and custom-assigned operators. Cannot assign or access platform-level privileges.

---

## Super Admin Initial Provisioning (CLI)

To prevent privilege escalation, **there is NO public registration endpoint or URL** that creates a Super Admin. The initial privileged operator must be provisioned via the secure Artisan CLI:

```bash
# Interactive mode (prompts for name, email, and password securely hidden):
php artisan storefleet:super-admin:provision

# Non-interactive mode (for automated server deployments):
php artisan storefleet:super-admin:provision --name="Operator" --email="admin@storefleet.test" --password="SuperSecurePassword123!"
```

### Security Properties of the Provisioning Command:
- **Strict Validation**: Validates RFC email format and enforces strong password criteria (min 8 chars, mixed case, numbers, symbols).
- **Mandatory 2FA Enrollment**: Generates an encrypted RFC 6238 TOTP secret, displays an `otpauth://` URI to import into Google Authenticator / 1Password / Apple Keychain, and prints 8 single-use emergency recovery codes.
- **Re-Execution Guard**: Refuses repeat execution if a Super Admin account already exists (requires explicit `--force` flag).
- **Zero Secret Leakage**: Passwords and secrets are never logged to log files or audit tables.
- **Shared Hosting Compatible**: Can be executed via SSH or cPanel Terminal directly on shared hosting accounts.

---

## Prerequisites

- **PHP**: `^8.2 || ^8.3 || ^8.5` with extensions: `bcmath`, `ctype`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `pdo_sqlite` / `pdo_mysql`, `tokenizer`, `xml`, `gd`.
- **Composer**: `^2.2`
- **Node.js**: `^20.0 || ^22.0`
- **NPM**: `^10.0`
- **Database**: SQLite (for dev/testing) or MySQL 8.0+ / MariaDB 10.4+ (for production).

---

## Local Setup Guide

### 1. Clone & Configure Environment

```bash
git clone https://github.com/dinethkandegedara/StoreFleet.git storefleet
cd storefleet

# Copy environment template
cp .env.example .env
```

### 2. Install PHP Dependencies

```bash
composer install
php artisan key:generate
```

### 3. Setup Database & Run Migrations

```bash
# For SQLite (default for development):
touch database/database.sqlite
php artisan migrate

# Provision the Super Admin operator:
php artisan storefleet:super-admin:provision
```

### 4. Install Frontend Dependencies & Build Assets

```bash
npm install
npm run build
```

### 5. Launch Development Server

```bash
# Terminal 1: Laravel backend
php artisan serve

# Terminal 2: Vite hot-reload (optional during active UI development)
npm run dev
```

Visit [http://localhost:8000/login](http://localhost:8000/login) to sign in.

---

## Code Quality & Automated Testing

StoreFleet includes automated test coverage, static analysis, and code styling tools:

```bash
# Run backend test suite (29 tests, 158 assertions, 100% passing)
php artisan test

# Check PHP code formatting (Laravel Pint)
./vendor/bin/pint --test

# Fix PHP formatting automatically
./vendor/bin/pint

# Run frontend TypeScript type checking (vue-tsc)
npm run type-check

# Compile production frontend bundle
npm run build
```

---

## Shared Hosting Deployment Strategy

Shared hosting accounts (e.g. cPanel, DirectAdmin) typically grant access to a user home directory (`/home/username/`) and a public web root (`/home/username/public_html/`).

### Directory Separation (Security Hardening)

Do **not** upload the entire Laravel repository inside `public_html`. Keep application code private:

```
/home/username/
├── storefleet/                  <-- Full Laravel source code (PRIVATE)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── storage/
│   ├── vendor/
│   ├── .env                    <-- Environment secrets safely outside webroot
│   └── artisan
│
└── public_html/                 <-- Web Document Root
    ├── build/                   <-- Precompiled Vite assets (manifest.json, js, css)
    ├── storage/                 <-- Symlink to ../storefleet/storage/app/public
    ├── index.php                <-- Entry point pointing to ../storefleet/
    ├── .htaccess                <-- Apache URL rewrite rules
    └── favicon.ico
```

### Deployment Steps:

1. **Build Assets Locally / CI**:
   Run `npm run build` on your workstation or GitHub Actions. Transfer the resulting `public/build/` directory to the server. **No Node.js runtime is required on the shared host.**

2. **Upload Backend**:
   Upload all files (except `node_modules` and `.git`) into `/home/username/storefleet/`.

3. **Configure `index.php` in `public_html`**:
   Adjust paths in `public_html/index.php` to reference the private folder:
   ```php
   require __DIR__.'/../storefleet/vendor/autoload.php';
   $app = require_once __DIR__.'/../storefleet/bootstrap/app.php';
   ```

4. **Environment Configuration**:
   Create `/home/username/storefleet/.env` with production values:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   LOG_LEVEL=warning

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_DATABASE=username_pos
   DB_USERNAME=username_posuser
   DB_PASSWORD=SecurePasswordHere

   SESSION_DRIVER=database
   QUEUE_CONNECTION=database
   CACHE_STORE=database
   ```

5. **Optimize Caches & Provision Operator**:
   Execute via SSH (or cPanel Terminal):
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan migrate --force
   php artisan storage:link
   php artisan storefleet:super-admin:provision
   ```

6. **Cron Job for Background Queue & Maintenance**:
   Add a single cPanel Cron job running every minute:
   ```bash
   * * * * * php /home/username/storefleet/artisan schedule:run >> /dev/null 2>&1
   ```

---

---

## Role-Based Access Control (RBAC) & Multi-Branch Architecture

StoreFleet implements a database-driven, multi-branch Role-Based Access Control (RBAC) engine designed specifically for POS workloads on shared hosting:

### Key Capabilities:
- **Granular Permission Catalog**: Operations are grouped by module (`roles`, `branches`, `users`, `catalog`, `pos`, `sales`, `inventory`), resource, and action (`view`, `create`, `update`, `delete`, `manage`, `approve`).
- **Extensible Module Registration**: Future modules (e.g., inventory transfers, supplier purchase orders) register permissions at runtime via `PermissionRegistrar::registerModulePermissions()` without rewriting core RBAC tables.
- **Multi-Branch Scoping**: Staff can be assigned roles globally (`branch_id = null`) or scoped to specific branches (`branch_id = X`). A user can hold different roles across different branches (e.g. Branch Manager at Store A, Cashier at Store B).
- **Privilege Escalation Prevention**: Administrators can only delegate permissions they personally hold. Attempts to grant unauthorized capabilities are rejected and logged to the audit log.
- **Essential Access Protection**: System roles (`business-owner`, `branch-manager`, `cashier`) cannot be deleted or deactivated. The system strictly refuses to revoke the last active Business Owner.
- **Atomic Cache Invalidation**: Permission caches are keyed with an atomic RBAC version counter (`storefleet_rbac_version`), ensuring instant cache invalidation on standard database/file cache drivers without Redis.
- **Audit Logging**: Role creations, updates, duplications, status toggles, and user assignments are recorded in `audit_logs`.

---

## Roadmap

- [x] **Phase 1: Application Foundation** (Laravel 12, Inertia.js 2, Vue 3, TypeScript, Custom Theme Tokens, Shared Hosting Pipeline)
- [x] **Phase 2: Global Design System & Application Shell** (Tailwind-free Vanilla CSS tokens, AppLayout, PosLayout, reusable form controls & alerts)
- [x] **Phase 3: Authentication & Super Admin Provisioning** (Session-based auth, mandatory TOTP 2FA, rate-limiting, audit logs, CLI provisioning)
- [x] **Phase 4: Multi-Branch & Identity Architecture (RBAC)** (Database roles & permissions, branch scoping, delegation validation, UI matrix editor)
- [ ] **Phase 5: Catalog & Image Optimization** (Categories, Products, Variants, WebP Processing Pipeline with EXIF stripping)
- [ ] **Phase 6: POS Terminal & Checkout Engine** (Barcode scanner listener, reactive cart, multi-method/split payments, transaction safety with idempotency keys)
- [ ] **Phase 7: Thermal Receipts & Sales History** (58mm/80mm thermal CSS printing, branch-isolated sales explorer)
- [ ] **Phase 8: Quality Assurance & Deployment Verification** (Full integration test suite, deployment validation)

