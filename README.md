# Queue Management System

A Laravel queue application with public ticket collection, a waiting-room screen, staff service desks, administrator controls, ticket history, and SMS OTP password recovery.

## Local setup

Requirements: PHP 8.3+, Composer, and Node.js/npm.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan queue:make-admin
php artisan serve
```

The administrator setup command securely prompts for an account name, email, password (at least 12 characters), and optional phone number. The database seeder creates four active service desks. Visit `/` for ticket collection, `/display` for the public waiting-room screen, and `/login` for staff or administrator sign-in. When using SQLite locally, enable the `pdo_sqlite` and `sqlite3` extensions in the active PHP configuration.

Use the admin page to start a queue session, assign staff, and configure the organization and ticket prefix. Configure SMS provider credentials before enabling password recovery; provider secret values are encrypted using `APP_KEY` and must be re-entered after rotating that key.

## Production database and concurrency

SQLite is suitable for local development and automated tests only. For production, configure MySQL/MariaDB with InnoDB (or PostgreSQL), enable strict mode, run migrations, and use a persistent cache/session store. Ticket issuance and staff claims lock the singleton queue-control row and session/staff/ticket rows in a transaction; production deployments must use a database engine that provides row-level transaction locks. The SQLite test suite validates state transitions and idempotency, not cross-process locking guarantees.

Ending a session prevents new ticket issuance and claims. Waiting tickets and active called/serving assignments remain unchanged for reporting; staff may finish already-started service, and an administrator can explicitly recover an interrupted assignment. Skipped tickets either return to the waiting queue or remain skipped for administrator recall, according to the configured policy.

## Waiting-room audio

Open `/display` on the dedicated display device, choose **Enable sound**, and use **Test sound** to verify its speakers. Speech uses the browser Web Speech API; visual polling continues when audio is unavailable. The display keeps a per-tab event cursor so reconnecting does not read back old calls. For a stable display, keep that browser tab open.

## SMS recovery

Select Hubtel or Nalo on the admin page and provide the sender ID, provider credentials, and (if needed) an HTTPS endpoint override. The Nalo adapter uses its documented JSON POST interface and validates the provider success status; Hubtel uses its SMS API endpoint with HTTP Basic authentication. See the [Nalo SMS API documentation](https://nalosolutions.com/developers/sms-api-documentation/) and [Hubtel developer portal](https://developers.hubtel.com/) for current account details. Provider network errors are logged without message contents, passwords, or credentials and produce a generic recovery response. OTPs are hashed, expire after five minutes, and are limited to five verification attempts with resend and request throttles.

Use HTTPS in production, keep `APP_DEBUG=false`, protect `APP_KEY`, and limit administrator access. Configure a real outbound SMS account and verify provider-specific account settings using the admin-only test message before enabling password recovery.

## Validation

```powershell
php artisan test
vendor/bin/pint --test
npm run build
```
