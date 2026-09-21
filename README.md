# VisitorPass

A reception visitor-management system, distributed as one Laravel install per
customer. Supports two modes, chosen once during first-run setup and locked
afterward:

- **Company mode** — visitors check in to see a specific employee within a department.
- **Building mode** — visitors check in to see a company operating in the building (no named host).

## Requirements

- PHP 8.2+
- MySQL 8+
- Composer
- Node.js + npm (for building frontend assets)

## Installation

```bash
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
```

Edit `.env` and set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` for a MySQL
database you've created for this install.

```bash
php artisan migrate
php artisan storage:link
```

The `storage:link` step is required — without it, an admin's uploaded
logo/favicon (Admin > Settings) will 404 on every page, since public
uploads are served from `storage/app/public` via that symlink.

Then visit the site in a browser. On first load, the setup wizard walks
through choosing Company or Building mode and creating the first admin
account — no separate seed step is required.

## Running locally

```bash
php artisan serve
npm run dev
```

## Tests

```bash
php artisan test
```
