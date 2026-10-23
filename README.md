# Inventory Dashboard API

A REST API for tracking a small shop's stock. You can manage categories and products, and a dashboard endpoint gives you the numbers a shop owner usually wants: how many products there are, which ones are running low, and how much the stock is worth.

It is built with Laravel and uses token authentication with two roles, `admin` and `staff`.

I made this project to learn how to structure a backend properly instead of putting everything in controllers. Most of the work went into the parts around the CRUD: validation, permissions, error responses, and tests.

## What it does

- Log in and get a bearer token (Sanctum). Tokens expire.
- Manage **categories** and **products** (create, read, update, delete).
- Search products by name or SKU, filter them, sort them, and paginate the results.
- See dashboard numbers: total categories, total products, active products, low-stock count, total inventory value, and the latest products.
- Two roles: staff can add and edit, only admins can delete or create new users.
- Every response, including errors, has the same JSON shape.
- API documentation is generated from the code and available at `/docs/api`.

## Built with

- Laravel 12 on PHP 8.3+
- Laravel Sanctum for authentication
- MySQL 8 (development and tests)
- Scramble for the API docs

## Running it locally

```bash
git clone https://github.com/hanineeeeee/inventory-dashboard-api.git
cd inventory-dashboard-api

composer install
cp .env.example .env
php artisan key:generate
```

Create two empty MySQL databases: `inventory_dashboard` for development and `inventory_dashboard_test` for the tests (see [Tests](#tests)). Put the first one's name and your credentials in `.env`, then:

```bash
php artisan migrate --seed
php artisan serve
```

The API runs at `http://localhost:8000/api/v1`. The seeder creates two users for local use:

| Role  | Email               | Password   |
|-------|---------------------|------------|
| Admin | admin@example.com   | password   |
| Staff | staff@example.com   | password   |

### Trying it out

Log in:

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

Use the token from the response:

```bash
curl http://localhost:8000/api/v1/products?search=mouse&sort_by=price \
  -H "Authorization: Bearer YOUR_TOKEN"
```

You can also open `/docs/api` in the browser and try the endpoints from there.

## Endpoints

Everything lives under `/api/v1`. All routes need a token except `login`.

| Method    | Path               | Notes                                   |
|-----------|--------------------|-----------------------------------------|
| POST      | /auth/login        | Returns a token                         |
| POST      | /auth/register     | Admin only. There is no public sign-up  |
| POST      | /auth/logout       | Revokes the current token               |
| GET       | /auth/me           | The logged-in user                      |
| GET       | /dashboard/stats   | Dashboard numbers                       |
| GET       | /categories        | Paginated, with a product count         |
| POST      | /categories        |                                         |
| GET       | /categories/{id}   | Includes its products                   |
| PUT/PATCH | /categories/{id}   |                                         |
| DELETE    | /categories/{id}   | Admin only, and the category must be empty |
| GET       | /products          | Search, filter, sort, paginate          |
| POST      | /products          |                                         |
| GET       | /products/{id}     | Includes its category                   |
| PUT/PATCH | /products/{id}     |                                         |
| DELETE    | /products/{id}     | Admin only                              |

### Product list options

| Parameter     | What it does                                                    |
|---------------|-----------------------------------------------------------------|
| `search`      | Matches product name or SKU                                     |
| `category_id` | Products in one category                                        |
| `status`      | `active` or `inactive`                                          |
| `low_stock`   | `1` shows only products below the low-stock threshold           |
| `sort_by`     | `id`, `name`, `sku`, `price`, `stock`, `status`, `created_at`   |
| `direction`   | `asc` or `desc` (default `desc`)                                |
| `per_page`    | 1 to 50 (default 10)                                            |

The categories list supports `sort_by` (including `products_count`), `direction`, and `per_page` the same way.

## Responses

Success:

```json
{
  "success": true,
  "message": "Product created successfully.",
  "data": { "id": 1, "name": "Wireless Mouse", "sku": "ELEC-001", "price": "29.99", "stock": 50 }
}
```

Lists also include `meta` and `links` for pagination. Errors:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "sku": ["The sku has already been taken."] }
}
```

| Status | Meaning                                              |
|--------|------------------------------------------------------|
| 401    | No token, a bad token, or an expired token           |
| 403    | Logged in, but not allowed to do this                |
| 404    | The record or the route does not exist               |
| 409    | The category still has products                      |
| 422    | Validation failed                                    |
| 429    | Too many login attempts                              |
| 500    | Unexpected error (details hidden unless `APP_DEBUG=true`) |

## Decisions I made

**Permissions live in policies.** Controllers call the policy instead of checking `$user->role` themselves, so the rules are in one place per model.

**A category with products cannot be deleted.** At first, deleting a category also deleted all its products through `cascadeOnDelete`. That seemed dangerous, so the foreign key now uses `restrictOnDelete`, and the API returns a clear 409 message.

**Slugs are generated in the model.** Two categories with the same name used to crash the app because of the unique index. Now the second one becomes `name-2`, and it works the same wherever a category is created.

**Business settings are in config, not scattered in code.** The low-stock threshold was hard-coded as `5` in two places. It now lives in `config/inventory.php` and both the filter and the dashboard read from it.

**Inactive products are not counted in inventory value by default.** I decided a discontinued product should not count as stock the shop can sell. This can be changed with an environment variable (see below).

**Login attempts are limited by email and IP together**, so one shared network does not lock everyone out.

**Errors are handled in one place** (`bootstrap/app.php`), so the API never returns an HTML page or a stack trace, whatever goes wrong.

## Settings

Set these in `.env`:

| Variable                                | Default | Meaning                                              |
|-----------------------------------------|---------|------------------------------------------------------|
| `SANCTUM_EXPIRATION`                    | 1440    | How long a token lasts, in minutes                   |
| `LOW_STOCK_THRESHOLD`                   | 5       | A product is "low" when its stock is below this      |
| `INVENTORY_VALUATION_INCLUDES_INACTIVE` | false   | Count inactive products in total inventory value     |

## Tests

```bash
php artisan test
```

The tests run on MySQL, the same database as development, so queries behave the same way in both. They use a separate database (`inventory_dashboard_test`) because every test run wipes its tables. Never point the tests at your development database. They cover login and roles, rate limiting, category and product CRUD, filters, sorting, search with special characters, validation errors, the error response format, and the dashboard numbers. GitHub Actions runs them on every push.

## Folder structure

```
app/
  Http/Controllers/Api/V1/   Auth, Category, Product, Dashboard
  Http/Requests/             Validation rules per endpoint
  Http/Resources/            How each model is turned into JSON
  Models/
  Policies/                  Who is allowed to do what
  Traits/ApiResponseTrait.php
bootstrap/app.php            Error handling
config/inventory.php         Business settings
routes/api.php
tests/Feature/
```

## Known limitations and next steps

- Stock is a single number on the product. There is no history of stock changes, so you cannot see who changed what.
- Deleting is permanent. Soft deletes would be safer.
- Logging in revokes all earlier tokens, which means logging in on a second device logs out the first.
- There is no frontend. This is only the API.

## License

MIT