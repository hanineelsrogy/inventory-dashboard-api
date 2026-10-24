# Changelog

All notable changes to the **Inventory Dashboard API** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.0.0] - 2026-10-24

### Added
- **Docker Containerization**:
  - `Dockerfile` using official PHP 8.4-cli with required extensions (`pdo_mysql`, `mbstring`, `bcmath`, `zip`, `pcntl`), Composer 2, and non-root execution (`appuser`).
  - `docker-compose.yml` defining `app` and `db` (MySQL 8) services with named volume persistence and healthcheck verification.
  - Dedicated MySQL initialization script (`docker/mysql/init-test-db.sql`) to prepare isolated `inventory_dashboard_test` alongside development database.
  - `.dockerignore` excluding local caches, logs, vendor binaries, and environment secrets.
- **Automated Code Quality & CI**:
  - Laravel Pint integration with `composer lint` and `composer format` scripts.
  - Larastan static analysis configuration at Level 5 with `composer analyse`.
  - Continuous Integration via GitHub Actions running Pint, Larastan, Composer audit, and automated test coverage reporting on MySQL 8.
- **Soft Deletes & Lifecycle Integrity**:
  - Soft deletes for `products` and `categories` tables (`deleted_at` timestamp).
  - Admin-only restoration endpoints: `POST /api/v1/categories/{id}/restore` and `POST /api/v1/products/{id}/restore`.
  - Integrity guard ensuring categories can only be soft-deleted if all associated products are soft-deleted, and products cannot be restored if their parent category is soft-deleted.
- **Stock Movement Ledger**:
  - Immutable `stock_movements` table recording product ID, user ID, delta, before/after values, optional business reason, and timestamp.
  - Automatic ledger entry generation wrapped inside atomic database transactions (`DB::transaction`) on product creation and updates.
  - Dedicated historical audit endpoint: `GET /api/v1/products/{id}/movements`.
- **Per-Product Reorder Levels**:
  - Nullable `reorder_level` unsigned integer column on `products` table.
  - Dynamic low-stock calculation evaluating `stock < COALESCE(reorder_level, default_threshold)`.
  - Full support across product filtering (`/products?low_stock=1`) and dashboard metrics (`/dashboard/stats`).
- **Streaming CSV Exports**:
  - Memory-efficient streaming export endpoint `GET /api/v1/reports/inventory/export` and alias `GET /api/v1/products/export`.
  - Constant $O(1)$ memory consumption using `response()->streamDownload()` and Eloquent `cursor()`.
  - UTF-8 BOM encoding (`\xEF\xBB\xBF`) for immediate Microsoft Excel and spreadsheet compatibility.
- **Database Indexing & Performance**:
  - Composite indexes `(deleted_at, status)` and `(deleted_at, created_at)` on `products`.
  - Column indexes on `price` and `stock` on `products`.
  - Composite index `(deleted_at, is_active)` and `name` on `categories`.
- **Dashboard Caching & Reactive Invalidation**:
  - In-memory dashboard KPI caching via `Cache::remember('dashboard:stats', 3600, ...)`.
  - Automatic cache clearing on `saved`, `deleted`, and `restored` Eloquent lifecycle hooks on `Product` and `Category`.
- **API Request Collections**:
  - Added `inventory-api.http` supporting VS Code REST Client and JetBrains HTTP Client.
  - Added `inventory-api.postman_collection.json` (Postman v2.1 format) covering all endpoints, variables, and authentication workflows.

### Changed
- Upgraded minimum PHP requirement to PHP 8.4 across `composer.json`, `composer.lock`, Dockerfile, CI workflow, and documentation.
- Upgraded test suite from 41 tests to 63 tests covering all new features and edge cases.
- Excluded soft-deleted products from dashboard aggregate totals and inventory valuations.

---

## [1.0.0] - 2026-10-23

### Added
- Initial production release of the Inventory Dashboard API.
- Token-based API authentication via Laravel Sanctum with `admin` and `staff` role-based access control.
- Full CRUD management for `categories` with automated collision-free slug generation.
- Full CRUD management for `products` with SKU uniqueness, status flags, and pricing.
- Search, multi-field filtering (`category_id`, `status`, `low_stock`), sorting, and pagination.
- Real-time dashboard KPI summary endpoint (`GET /api/v1/dashboard/stats`).
- Standardized API JSON envelope trait (`ApiResponseTrait`) for uniform responses.
- Centralized exception and error handling in `bootstrap/app.php` preventing HTML leakage.
- Automated API documentation generation using Scramble (`/docs/api`).
- Automated feature test suite with 41 tests running against MySQL (`inventory_dashboard_test`).
