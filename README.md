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
- The web server user must be able to write to `.env` and to `storage/` —
  the database setup screen below writes directly to `.env`, and will show
  a permissions error on that screen if it can't.

## Installation

```bash
composer install
npm install && npm run build

cp .env.example .env
```

Then visit the site in a browser. On first load you'll land on a database
setup screen — enter your MySQL host, port, database name, username, and
password, plus the site's public URL. The app writes these into `.env`,
generates the application key, and runs migrations automatically; no SSH
or terminal access is required.

After the database step, the existing setup wizard walks through choosing
Company or Building mode and creating the first admin account — no
separate seed step is required.

Run `php artisan storage:link` once, from the server (via SSH, or your
host's terminal/cron feature) — without it, an admin's uploaded
logo/favicon (Admin > Settings) will 404 on every page, since public
uploads are served from `storage/app/public` via that symlink.

### Manual setup (alternative)

If you have SSH access and prefer to configure things yourself, edit
`.env` directly and run:

```bash
php artisan key:generate
php artisan migrate
php artisan storage:link
```

The app detects an already-migrated database and skips the database setup
screen automatically.

### Redoing the database step

Once the database step succeeds, `/install` is permanently disabled by a
marker file at `storage/app/private/installed` (not `storage/app/installed`
— Laravel's default `local` disk root is `storage/app/private`). Wrong
credentials at submit time redisplay the form immediately with no lockout.
If you need to redo the step after the fact — for example the site reports
a database error even though the marker exists — delete that marker file
and revisit the site.

## Email notifications

Configure SMTP under **Admin > Settings** — host, port, username,
password, and a from address. Once a host is set, every visitor check-in
emails the host (company mode) or the company's contact (building mode),
if that person has an email on file. Use **Send test email** on the same
screen to confirm your settings work before relying on them; if it's
wrong, check-in still succeeds silently and nothing is sent.

## Running locally

```bash
php artisan serve
npm run dev
```

## Tests

```bash
php artisan test
```
