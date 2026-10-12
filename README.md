# BranchWise POS

[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue.js-3.5-4FC08D?style=flat&logo=vuedotjs&logoColor=white)](https://vuejs.org)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.0-9553E9?style=flat&logo=inertia&logoColor=white)](https://inertiajs.com)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.6-3178C6?style=flat&logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

A production-quality, high-performance, multi-branch Point of Sale application built with Laravel and Vue.js, architected to run smoothly on standard shared Linux hosting (cPanel, Apache, LiteSpeed) without requiring Docker, Redis, or a persistent Node.js server process.

---

## Architecture Overview

BranchWise POS uses a **Modular Monolith** pattern:
- **Backend**: Laravel 12 (PHP 8.2+) providing strict authentication, branch-scoped authorization, database transactions, and business logic.
- **Frontend**: Vue 3 (Composition API with `<script setup lang="ts">`) powered by Inertia.js for a seamless Single-Page Application (SPA) experience without API token overhead.
- **Theme Engine**: Built with Vanilla CSS Variables (`:root` / `[data-theme="dark"]`), supporting light and dark modes alongside runtime-customizable primary accent colors configured by the business.
- **Storage & Queues**: Native database-backed sessions, cache, and queues (`SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`).
- **Target Hosting**: Standard shared hosting environments where only PHP and MySQL/MariaDB are available.

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
git clone https://github.com/dinethkandegedara/StoreFleet.git branchwise-pos
cd branchwise-pos

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

# (Optional) For MySQL:
# Update DB_CONNECTION=mysql, DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env, then:
# php artisan migrate
```

### 4. Install Frontend Dependencies & Build Assets

```bash
npm install
npm run build
```

### 5. Launch Development Server

Run the development servers:

```bash
# Terminal 1: Laravel backend
php artisan serve

# Terminal 2: Vite hot-reload (optional during active UI development)
npm run dev
```

Visit [http://localhost:8000](http://localhost:8000) in your browser.

---

## Code Quality & Automated Testing

BranchWise POS includes automated test coverage, static analysis, and code styling tools:

```bash
# Run backend test suite (in-memory SQLite, 100% isolated)
php artisan test

# Check PHP code formatting (Laravel Pint)
./vendor/bin/pint --test

# Fix PHP formatting automatically
./vendor/bin/pint

# Run frontend TypeScript type checking
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
├── branchwise-pos/              <-- Full Laravel source code (PRIVATE)
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
    ├── storage/                 <-- Symlink to ../branchwise-pos/storage/app/public
    ├── index.php                <-- Entry point pointing to ../branchwise-pos/
    ├── .htaccess                <-- Apache URL rewrite rules
    └── favicon.ico
```

### Deployment Steps:

1. **Build Assets Locally / CI**:
   Run `npm run build` on your workstation or GitHub Actions. Transfer the resulting `public/build/` directory to the server. **No Node.js runtime is required on the shared host.**

2. **Upload Backend**:
   Upload all files (except `node_modules` and `.git`) into `/home/username/branchwise-pos/`.

3. **Configure `index.php` in `public_html`**:
   Adjust paths in `public_html/index.php` to reference the private folder:
   ```php
   require __DIR__.'/../branchwise-pos/vendor/autoload.php';
   $app = require_once __DIR__.'/../branchwise-pos/bootstrap/app.php';
   ```

4. **Environment Configuration**:
   Create `/home/username/branchwise-pos/.env` with production values:
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

5. **Optimize Caches**:
   Execute via SSH (or cPanel Terminal):
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan migrate --force
   php artisan storage:link
   ```

6. **Cron Job for Background Queue & Maintenance**:
   Add a single cPanel Cron job running every minute:
   ```bash
   * * * * * php /home/username/branchwise-pos/artisan schedule:run >> /dev/null 2>&1
   ```

---

## Roadmap

- [x] **Phase 1: Application Foundation** (Laravel 12, Inertia.js 2, Vue 3, TypeScript, Custom Theme Tokens, Shared Hosting Pipeline)
- [ ] **Phase 2: Multi-Branch & Identity Architecture** (Super Admin isolation, Roles & Permissions DB engine, Branch Switching & Middleware)
- [ ] **Phase 3: Catalog & Image Optimization** (Categories, Products, Variants, WebP Processing Pipeline with EXIF stripping)
- [ ] **Phase 4: POS Terminal & Checkout Engine** (Barcode scanner listener, reactive cart, multi-method/split payments, transaction safety with idempotency keys)
- [ ] **Phase 5: Thermal Receipts & Sales History** (58mm/80mm thermal CSS printing, branch-isolated sales explorer, audit logs)
- [ ] **Phase 6: Quality Assurance & Deployment Verification** (Full integration test suite, deployment validation)
