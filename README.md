# Inventory Dashboard API (v2)

A production-ready REST API for inventory management, stock movement auditing, and warehouse reporting. It tracks categories and products, maintains an immutable ledger of every stock adjustment, evaluates per-product reorder thresholds, streams CSV exports, and serves a high-performance cached dashboard.

Built with Laravel 13 on PHP 8.4, Laravel Sanctum, and MySQL 8.

---

## Architecture Pipeline

The project follows a strict decoupled architecture to keep controllers slim, business rules testable, and security checks centralized:

```
┌─────────┐     ┌────────────┐     ┌─────────────┐     ┌──────────┐     ┌────────────┐     ┌──────────┐
│ Request │ ──> │ Middleware │ ──> │ FormRequest │ ──> │  Policy  │ ──> │ Controller │ ──> │ Resource │ ──> JSON
└─────────┘     └────────────┘     └─────────────┘     └──────────┘     └────────────┘     └──────────┘
```

1. **Request & Middleware (`bootstrap/app.php` & `routes/api.php`):**
   - Validates bearer token via Laravel Sanctum.
   - Enforces rate limiting on authentication attempts.
   - Intercepts all API exceptions, guaranteeing consistent JSON error formatting (`ApiResponseTrait`) with zero stack trace leakage.
2. **FormRequest (`app/Http/Requests`):**
   - Isolates validation rules and input normalization.
   - Rejects invalid payloads with standardized 422 Unprocessable Entity responses before reaching controller logic.
3. **Policy (`app/Policies`):**
   - Encapsulates role-based authorization (`admin` vs `staff`).
   - Controllers call `Gate::authorize()` rather than hardcoding `$user->role === 'admin'`.
4. **Controller (`app/Http/Controllers/Api/V1`):**
   - Acts strictly as an orchestrator.
   - Manages atomic database transactions (`DB::transaction`) for stock movements.
   - Interacts with cache layers and streams responses.
5. **Resource (`app/Http/Resources`):**
   - Decouples internal database schema from external API contracts.
   - Ensures uniform JSON responses: `{ "success": true, "message": "...", "data": ... }`.

---

## Features (Version 2.0)

- **Authentication & Roles:** Sanctum bearer tokens with expiration (24h) and single active device login. Two roles: `admin` (full management) and `staff` (operational CRUD).
- **Categories Management:** CRUD with collision-free slug generation, pagination, and sorting.
- **Products Management:** CRUD with SKU uniqueness, status flags (`active`/`inactive`), search, multi-field filtering, and sorting.
- **Soft Deletes & Safe Restoration:**
  - Soft deletes on categories and products (`deleted_at`).
  - Admin-only restoration endpoints (`POST /categories/{id}/restore`, `POST /products/{id}/restore`).
  - Integrity guard: Categories cannot be deleted while containing active products, and products cannot be restored if their parent category is soft-deleted.
- **Stock Movements Audit Ledger:**
  - Dedicated `stock_movements` table recording product ID, user ID, delta, before/after counts, reason, and timestamp.
  - Changes through the API are created atomically inside database transactions.
  - Historical audit endpoint: `GET /products/{id}/movements`.
- **Per-Product Reorder Levels:**
  - Products define an optional `reorder_level` for custom low-stock alerts.
  - Automatically falls back to `config('inventory.low_stock_threshold')` when left unspecified.
  - Dynamic evaluation in both product filtering (`/products?low_stock=1`) and dashboard metrics.
- **Streaming CSV Export:**
  - High-performance memory-efficient streaming export: `GET /reports/inventory/export` and `GET /products/export`.
  - Constant $O(1)$ memory consumption using `response()->streamDownload()` and Eloquent's lazy `cursor()`.
  - Includes UTF-8 BOM encoding for seamless Microsoft Excel and Google Sheets compatibility.
- **Database Performance Indexing:**
  - Composite indexes `(deleted_at, status)` and `(deleted_at, created_at)` on products.
  - Column indexes on `price` and `stock` on products.
  - Composite index `(deleted_at, is_active)` and index on `name` on categories.
- **Dashboard Caching & Reactive Invalidation:**
  - Cached dashboard KPI calculations (`Cache::remember('dashboard:stats', 3600, ...)`).
  - Eloquent model lifecycle hooks (`saved`, `deleted`, `restored`) on `Product` and `Category` automatically invalidate the cache upon any mutation.
- **Code Quality & CI:**
  - Laravel Pint (`composer lint`, `composer format`).
  - Larastan static analysis at Level 5 (`composer analyse`).
  - GitHub Actions CI running style checks, static analysis, security audits, and automated test coverage reporting on MySQL 8.
- **Docker Containerization:**
  - Complete Docker environment with PHP 8.4-cli and MySQL 8.0.

---

## Running with Docker

You can run the entire API and database without installing PHP, Composer, or MySQL locally:

```bash
# 1. Prepare environment and build containers
cp .env.example .env
docker compose up -d --build

# 2. Generate application key
docker compose exec app php artisan key:generate

# 3. Run database migrations and seed demo data
docker compose exec app php artisan migrate --seed
```

- API Base URL: `http://localhost:8000/api/v1`
- Interactive API Documentation: `http://localhost:8000/docs/api`

### Running Tests in Docker

```bash
# Run tests against the isolated test database (inventory_dashboard_test)
docker compose exec -e DB_HOST=db app php artisan test
```

### Stopping Docker

```bash
docker compose down
```

---

## Running Locally (Without Docker)

### Prerequisites
- PHP 8.4+ with extensions: `pdo_mysql`, `mbstring`, `bcmath`, `zip`, `pcntl`
- Composer 2+
- MySQL 8.0+

```bash
git clone https://github.com/hanineeeeee/inventory-dashboard-api.git
cd inventory-dashboard-api

composer install
cp .env.example .env
php artisan key:generate
```

Configure your MySQL credentials in `.env`, create two databases (`inventory_dashboard` and `inventory_dashboard_test`), then run:

```bash
php artisan migrate --seed
php artisan serve
```

### Seeded Credentials

| Role  | Email             | Password | Permissions |
|-------|-------------------|----------|-------------|
| Admin | admin@example.com | password | Full access (CRUD, soft delete, restore, user registration) |
| Staff | staff@example.com | password | Operational access (CRUD, stock intake/adjustments, CSV export) |

---

## API Endpoints

All routes live under `/api/v1`. All endpoints require `Authorization: Bearer <token>` except login.

| Method    | Path                       | Access     | Description                                           |
|-----------|----------------------------|------------|-------------------------------------------------------|
| `POST`    | `/auth/login`              | Public     | Authenticates user, revokes prior tokens, issues new token |
| `POST`    | `/auth/register`           | Admin only | Registers a new staff or admin user                   |
| `POST`    | `/auth/logout`             | Auth       | Revokes the current access token                      |
| `GET`     | `/auth/me`                 | Auth       | Returns authenticated user profile                    |
| `GET`     | `/dashboard/stats`         | Auth       | Cached dashboard KPIs (valuation, low stock, counts)  |
| `GET`     | `/categories`              | Auth       | Paginated categories list with product counts         |
| `POST`    | `/categories`              | Auth       | Creates a new category (slug auto-generated)          |
| `GET`     | `/categories/{id}`         | Auth       | Retrieves category with active products               |
| `PUT`     | `/categories/{id}`         | Auth       | Updates category details                              |
| `DELETE`  | `/categories/{id}`         | Admin only | Soft deletes category (requires products soft-deleted)|
| `POST`    | `/categories/{id}/restore` | Admin only | Restores a soft-deleted category                      |
| `GET`     | `/products`                | Auth       | Paginated products list (search, filter, sort)        |
| `POST`    | `/products`                | Auth       | Creates product & records initial stock movement      |
| `GET`     | `/products/{id}`           | Auth       | Retrieves product with category relation              |
| `PUT`     | `/products/{id}`           | Auth       | Updates product & records transactional stock delta   |
| `DELETE`  | `/products/{id}`           | Admin only | Soft deletes product                                  |
| `POST`    | `/products/{id}/restore`   | Admin only | Restores product (requires parent category active)    |
| `GET`     | `/products/{id}/movements` | Auth       | Paginated stock movement audit history                |
| `GET`     | `/reports/inventory/export`| Auth       | Streams full or filtered inventory report as CSV      |
| `GET`     | `/products/export`         | Auth       | Alias for streaming CSV export                        |

### Product Filter & Sort Options

| Query Parameter | Example Values | Purpose |
|---|---|---|
| `search` | `mouse`, `KEY-001` | Searches product name or SKU with SQL wildcard escaping |
| `category_id` | `1`, `5` | Filters products belonging to a specific category |
| `status` | `active`, `inactive` | Filters by status |
| `low_stock` | `1` | Filters products where `stock < COALESCE(reorder_level, default_threshold)` |
| `sort_by` | `price`, `stock`, `reorder_level`, `created_at` | Whitelisted column sort |
| `direction` | `asc`, `desc` | Sort direction (default `desc`) |
| `per_page` | `10`, `25`, `50` | Items per page (capped at 50) |

---

## API Testing & Request Collections

The repository provides ready-to-run API request collections covering every endpoint:

1. **`inventory-api.http`**: Plaintext HTTP file for VS Code REST Client and JetBrains HTTP Client.
2. **`inventory-api.postman_collection.json`**: Standard Postman collection (v2.1). Import into Postman and set the `baseUrl` variable to test immediately.

---

## Interactive Documentation & Screenshots

Interactive API documentation is generated by [Scramble](https://scramble.dedoc.co/) and available at `/docs/api`.

### Recommended Screenshots for Documentation:
1. **API Overview & Dashboard (`/docs/api#tag/Dashboard`):**
   Shows the dashboard statistics endpoint and standardized JSON response structure.
   *(Save to `docs/screenshots/01-dashboard-stats.png`)*
2. **Products & Stock Movements Ledger (`/docs/api#tag/Products`):**
   Shows product CRUD endpoints, the stock movements audit endpoint, and soft delete restore endpoints.
   *(Save to `docs/screenshots/02-products-movements.png`)*
3. **CSV Export & Report Endpoints (`/docs/api#tag/Reports`):**
   Shows the streaming CSV export parameters (`category_id`, `status`, `low_stock`) and content disposition headers.
   *(Save to `docs/screenshots/03-csv-export.png`)*

---

## Code Quality & Verification

```bash
# Run code style checks (Laravel Pint)
composer lint

# Automatically format code style
composer format

# Run static analysis (Larastan Level 5)
composer analyse

# Run dependency security audit
composer audit

# Run automated test suite (MySQL isolated database)
php artisan test
```

---

## License

This project is open-source software licensed under the [MIT License](LICENSE).
