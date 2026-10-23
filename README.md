# 📦 Mini Inventory Dashboard RESTful API

![Laravel](https://img.shields.io/badge/Laravel-v12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Sanctum](https://img.shields.io/badge/Sanctum-Auth-F55247?style=for-the-badge&logo=laravel&logoColor=white)
![OpenAPI](https://img.shields.io/badge/OpenAPI-3.1-6BA539?style=for-the-badge&logo=openapiinitiative&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

A clean, beginner-friendly RESTful API built with **Laravel 12** demonstrating production-grade architecture patterns for a portfolio project. It features token authentication via **Laravel Sanctum**, role-based authorization via **Policies**, full CRUD for Categories and Products, configurable KPI analytics, centralized JSON exception handling, and auto-generated interactive **Swagger/OpenAPI documentation**.

---

## 🏛 Why This Architecture Matters

This project was built to illustrate real-world backend best practices rather than quick hacks:

1. **Defensive Database Integrity**: Categories cannot be deleted while products still belong to them (`restrictOnDelete` + HTTP `409 Conflict`), preventing catastrophic data loss.
2. **Predictable JSON Envelope**: Every response—whether success (200, 201) or error (401, 403, 404, 409, 422, 429, 500)—follows a strict, consistent JSON shape via `ApiResponseTrait` and centralized handlers in `bootstrap/app.php`.
3. **Hardened Security**:
   - Login rate-limited to 5 requests per minute, partitioned by `email + IP` to protect shared office networks.
   - Registration is restricted strictly to administrators (`authorize()` check prevents user enumeration).
   - Sanctum tokens include expiration and single-session revocation on login.
   - Stack traces are completely stripped from production 500 error responses (`APP_DEBUG=false`).
4. **Single Source of Truth**: Business rules like the low-stock threshold are defined in `config/inventory.php` and shared across query filters and dashboard KPI calculations.

---

## 🛠 Tech Stack & Database Strategy

| Layer | Technology | Purpose |
|-------|-----------|---------|
| **Framework** | Laravel 12 (PHP 8.2+) | Modern API foundation |
| **Primary Database** | MySQL 8.0 (Laragon / Herd) | Production-style relational database for local development |
| **Testing Database** | SQLite (`:memory:`) | Blazing fast, zero-side-effect test executions with `RefreshDatabase` |
| **Authentication** | Laravel Sanctum | Bearer token issuing, expiration, and revocation |
| **Documentation** | [dedoc/scramble](https://scramble.dedoc.co/) | Zero-annotation OpenAPI 3.1 & Stoplight Elements interactive UI |
| **Response Standardization** | `ApiResponseTrait` + `JsonResource` | Uniform JSON structure across all endpoints |

---

## 👥 Roles & Seeded Accounts

The application implements Role-Based Access Control (RBAC) enforced by **Laravel Policies** (`CategoryPolicy`, `ProductPolicy`):

| Role | Permissions |
|------|-------------|
| **`admin`** | Full CRUD access to Categories and Products, can delete resources, can register new user accounts. |
| **`staff`** | Can view, create, and update Categories and Products. **Cannot** delete resources and **cannot** register users. |

The database seeder pre-populates two test accounts:

| Email | Password | Role | Description |
|-------|----------|------|-------------|
| `admin@example.com` | `password` | `admin` | Full administrator privileges |
| `staff@example.com` | `password` | `staff` | Restricted staff privileges |

---

## 📋 API Endpoints Reference

All endpoints are versioned under `/api/v1`.

### 🔐 Authentication

| Method | Endpoint | Access | Rate Limit | Description |
|--------|----------|--------|------------|-------------|
| `POST` | `/api/v1/auth/login` | Public | 5 req/min (`email+IP`) | Authenticate and obtain Bearer token |
| `POST` | `/api/v1/auth/register` | 🔒 Admin only | 5 req/min (`email+IP`) | Register new admin or staff accounts |
| `GET` | `/api/v1/auth/me` | 🔒 Authenticated | — | Retrieve current user profile |
| `POST` | `/api/v1/auth/logout` | 🔒 Authenticated | — | Revoke current token |

### 📊 Dashboard Analytics

| Method | Endpoint | Access | Description |
|--------|----------|--------|-------------|
| `GET` | `/api/v1/dashboard/stats` | 🔒 Authenticated | KPI metrics, stock valuation, and latest 5 products |

### 📁 Categories CRUD

| Method | Endpoint | Access | Description |
|--------|----------|--------|-------------|
| `GET` | `/api/v1/categories` | 🔒 Authenticated | List all categories with `products_count` |
| `POST` | `/api/v1/categories` | 🔒 Authenticated | Create a category (slug auto-generated) |
| `GET` | `/api/v1/categories/{id}` | 🔒 Authenticated | Retrieve single category with its products |
| `PUT` | `/api/v1/categories/{id}` | 🔒 Authenticated | Update category details |
| `DELETE` | `/api/v1/categories/{id}` | 🔒 Admin only | Delete category (blocked with 409 if products exist) |

### 📦 Products CRUD

| Method | Endpoint | Access | Description |
|--------|----------|--------|-------------|
| `GET` | `/api/v1/products` | 🔒 Authenticated | List paginated products with query filters |
| `POST` | `/api/v1/products` | 🔒 Authenticated | Create a new product |
| `GET` | `/api/v1/products/{id}` | 🔒 Authenticated | Retrieve single product with parent category |
| `PUT` | `/api/v1/products/{id}` | 🔒 Authenticated | Update product details or stock count |
| `DELETE` | `/api/v1/products/{id}` | 🔒 Admin only | Delete product |

---

## 🚀 Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL 8.0 (via [Laragon](https://laragon.org/) or [Laravel Herd](https://herd.laravel.com/))
- Git

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/<your-github-username>/inventory-dashboard-api.git
cd inventory-dashboard-api

# 2. Install PHP dependencies
composer install

# 3. Setup environment configuration
cp .env.example .env
php artisan key:generate
```

### Database Setup (MySQL)

Ensure MySQL is running in your local environment, then create the database and run migrations with seed data:

```bash
# 4. Create the MySQL database
mysql -u root -e "CREATE DATABASE IF NOT EXISTS inventory_dashboard;"

# 5. Run migrations and seed default data (Admin, Staff, 4 Categories, 15 Products)
php artisan migrate:fresh --seed
```

### Start the Development Server

```bash
php artisan serve
```

The API will be accessible at `http://localhost:8000`.

---

## 📄 Interactive API Documentation (Swagger / OpenAPI)

Once the local server is running, open your web browser and navigate to:

🔗 **[http://localhost:8000/docs/api](http://localhost:8000/docs/api)**

### How to test protected endpoints in the browser:
1. Under **Authentication**, select `POST /api/v1/auth/login`.
2. Click **Try it out** and submit `admin@example.com` / `password`.
3. Copy the token from the response.
4. Click the **Authorize** 🔒 button at the top right of the documentation page.
5. Paste your Bearer token and click **Apply**.
6. You can now execute and test all protected endpoints directly in the browser!

The raw OpenAPI 3.1 specification is available at `http://localhost:8000/docs/api.json`.

---

## 🧪 Running Automated Tests

Tests run against an **in-memory SQLite database**, ensuring your MySQL development database is never touched or polluted during testing.

```bash
# Run all tests
php artisan test

# Run tests with compact detail
php artisan test --filter=AuthTest
```

---

## 📦 Sample JSON Responses

### 1. Successful Authentication (`POST /api/v1/auth/login`)
```json
{
  "success": true,
  "message": "Logged in successfully.",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@example.com",
      "role": "admin",
      "created_at": "2026-10-23T17:25:52.000000Z"
    },
    "token": "2|9oP3hR6m..."
  }
}
```

### 2. Dashboard Analytics (`GET /api/v1/dashboard/stats`)
```json
{
  "success": true,
  "message": "Dashboard statistics retrieved successfully.",
  "data": {
    "total_categories": 4,
    "total_products": 15,
    "active_products": 13,
    "low_stock_count": 6,
    "total_inventory_value": 15090.17,
    "latest_products": [
      {
        "id": 15,
        "category_id": 4,
        "name": "Water Bottle",
        "sku": "SPRT-004",
        "price": "14.99",
        "stock": 200,
        "status": "active",
        "category": {
          "id": 4,
          "name": "Sports & Outdoors",
          "slug": "sports-outdoors"
        }
      }
    ]
  }
}
```

### 3. List Categories (`GET /api/v1/categories`)
```json
{
  "success": true,
  "message": "Categories retrieved successfully.",
  "data": [
    {
      "id": 1,
      "name": "Electronics",
      "slug": "electronics",
      "description": "Gadgets, devices, and electronic accessories.",
      "is_active": true,
      "products_count": 4,
      "created_at": "2026-10-23T17:25:52.000000Z"
    }
  ]
}
```

### 4. Create Category (`POST /api/v1/categories`)
*Auto-generates unique slug even if a duplicate name is passed:*
```json
{
  "success": true,
  "message": "Category created successfully.",
  "data": {
    "id": 5,
    "name": "Electronics",
    "slug": "electronics-2",
    "description": null,
    "is_active": true,
    "created_at": "2026-10-23T20:10:00.000000Z"
  }
}
```

### 5. Category Deletion Conflict (`DELETE /api/v1/categories/1` with products)
```json
{
  "success": false,
  "message": "Cannot delete category because it still contains products. Please reassign or delete the products first.",
  "errors": null
}
```

### 6. Validation Error (HTTP 422)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "sku": ["The sku has already been taken."],
    "price": ["The price field must be a number."]
  }
}
```

### 7. Unauthorized Action (HTTP 403)
```json
{
  "success": false,
  "message": "This action is unauthorized.",
  "errors": null
}
```

### 8. Rate Limit Exceeded (HTTP 429)
```json
{
  "success": false,
  "message": "Too many requests. Please slow down.",
  "errors": null
}
```

---

## 📁 Project Directory Structure

```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── AuthController.php        # Register, login, logout, me
│   │   ├── DashboardController.php   # KPI stats & aggregations
│   │   ├── CategoryController.php    # Categories resource CRUD
│   │   └── ProductController.php     # Products resource CRUD with filters
│   ├── Requests/
│   │   ├── Auth/                     # RegisterRequest, LoginRequest
│   │   ├── StoreCategoryRequest.php
│   │   ├── UpdateCategoryRequest.php
│   │   ├── StoreProductRequest.php
│   │   └── UpdateProductRequest.php
│   └── Resources/
│       ├── UserResource.php
│       ├── CategoryResource.php
│       └── ProductResource.php
├── Models/
│   ├── User.php                      # HasApiTokens, role helper (isAdmin)
│   ├── Category.php                  # Auto unique-slug lifecycle hooks
│   └── Product.php                   # Scopes, casts, relationships
├── Policies/
│   ├── CategoryPolicy.php            # Only admins can delete
│   └── ProductPolicy.php             # Only admins can delete
└── Traits/
    └── ApiResponseTrait.php          # Standard success() & error() JSON

config/
└── inventory.php                     # Low-stock threshold & valuation settings

routes/
└── api.php                           # Versioned API v1 route definitions
```

---

## 📝 License

This project is open-sourced under the [MIT License](LICENSE).
