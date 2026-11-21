# docTHub V2 — Local setup & deployment

## Local development (Windows + XAMPP, as used during development)

Requirements: PHP ≥ 8.1 with `pdo_mysql, mbstring, openssl, fileinfo, gd`; Composer; MySQL 8 or MariaDB ≥ 10.6; Node ≥ 20.

```bash
# 1. Database
mysql -u root -e "CREATE DATABASE docthub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                  CREATE DATABASE docthub_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Backend
cd BACK/sahtek
composer install
cp .env.example .env            # DB_DATABASE=docthub, DOCTHUB_ALLOW_UNREVIEWED_CONTENT=true for local
php artisan key:generate
php artisan migrate --seed      # reference data + SAMPLE medical content + demo accounts
php artisan storage:link
php artisan serve               # http://127.0.0.1:8000

# 3. Frontend
cd ../../frontend
npm install
cp .env.example .env            # VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
npm run dev                     # http://localhost:5173
```

On XAMPP, use `C:\xampp\php\php.exe` if `php` is not on the PATH, and start MySQL from the XAMPP control panel.

### Demo accounts (development only — created by `DemoAccountsSeeder`, never in production)
| Role | Email | Password |
|---|---|---|
| Admin | `admin@docthub.test` | `Admin12345` |
| Patient | `patient@docthub.test` | `Patient12345` |

56 fictional doctors (`is_demo = true`, last name `Demo-NN`) in Casablanca, Rabat, Marrakech, Agadir, Paris, Lyon, Brussels and Montréal, plus one *pending* doctor that must never appear publicly.

## Tests

```bash
cd BACK/sahtek && php artisan test        # uses the docthub_test database (phpunit.xml)
cd frontend && npm test && npm run typecheck && npm run build
```

## Production checklist

1. **Environment** (`BACK/sahtek/.env`)
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://api.example.com`
   - `DOCTHUB_ALLOW_UNREVIEWED_CONTENT=false` ← mandatory
   - `CORS_ALLOWED_ORIGINS=https://www.example.com`
   - `FILESYSTEM_DISK` / `DOCTHUB_PUBLIC_DISK` / `DOCTHUB_PRIVATE_DISK` (e.g. `s3` + a private bucket for documents)
   - Mail settings if notifications are enabled later.
   - Knowledge pipeline (optional): `ICD_API_CLIENT_ID`, `ICD_API_CLIENT_SECRET` (WHO ICD API account), `ICD_API_RELEASE` (empty = latest), `MEDLINEPLUS_REQUESTS_PER_MINUTE` (≤ 85), `DOCTHUB_KNOWLEDGE_TIMEOUT`, `DOCTHUB_KNOWLEDGE_RETRIES`, `DOCTHUB_KNOWLEDGE_RAW_DISK`. Secrets only in `.env`, never in git.
2. **Database**: `php artisan migrate --force` then `php artisan db:seed --force`. In production the seeder only loads reference data (languages, countries, cities, roles). Medical content is entered and **approved** by qualified reviewers in the admin.
3. **First admin**: `php artisan tinker` → `$u = App\Models\User::create([...]); $u->assignRole('admin');`
4. **Emergency numbers**: verify each country's number against an official source and tick *Emergency number checked* in Admin → Countries.
5. **Scheduler**: add the cron entry `* * * * * php /path/to/artisan schedule:run` (daily `docthub:sources:check` at 03:00 and `docthub:send-follow-ups` at 10:00). Configure a real mailer (`MAIL_*`) and `FRONTEND_URL` for the follow-up link.
6. **Caching**: `php artisan config:cache && php artisan route:cache`.
7. **Frontend**: `VITE_API_BASE_URL=https://api.example.com/api/v1 npm run build`, serve `frontend/dist` as a static site with SPA fallback to `index.html`.
8. **HTTP**: HTTPS only, HSTS, and a Content-Security-Policy. The OpenStreetMap iframe needs `frame-src https://www.openstreetmap.org`.
9. **Legal**: privacy policy, terms, data-processing register and a GDPR / local law (e.g. Morocco law 09-08) assessment before launch. The platform makes **no compliance claim**.

## Knowledge pipeline
1. `php artisan db:seed --class=KnowledgeSourceSeeder` (also run by `db:seed`; never overwrites an existing source).
2. Read [sources.md](sources.md), then set each licence to *confirmed* in Admin → Knowledge sources (or leave it blocked).
3. `php artisan knowledge:fetch icd11|medlineplus --target=… [--lang=…]`. Exit code 1 if any document failed; raw files in `storage/app/raw/`. Keep that directory on persistent storage and in backups.

## Migrating V1 data
See `MIGRATION_FROM_V1.md` — `php artisan docthub:import-v1 --country=MA [--dry-run]`.
