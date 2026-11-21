# docTHub

> **Your Health, Our Priority — Find the Right Doctor Near You.**

International medical **orientation** and **doctor discovery** platform. Patients describe their symptoms on a body map, answer a dynamic questionnaire, receive a rule-based orientation (possible explanations, recommended specialty, validated advice, emergency warnings) and find verified doctors near them.

⚠️ docTHub is an orientation tool, **not a diagnostic medical device**. All medical content shipped in this repository is development sample data that has **not** been clinically reviewed.

## Repository layout

| Path | What |
|---|---|
| `BACK/sahtek/` | Laravel 10 API — V2 under `/api/v1`, legacy V1 under `/api` |
| `frontend/` | V2 web app — React 19, TypeScript, Vite, Redux Toolkit (RTK Query), Tailwind 4, i18next (EN/FR/AR + RTL) |
| `front/sahtek/` | Legacy V1 React app (kept for reference) |
| `docs/` | Requirements, architecture, database, API, roadmap, V1 migration, deployment, [knowledge sources and licences](docs/sources.md) |
| `LEARN.md` | Progress checklist, decisions and lessons learned |

## Quick start

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). In short:

```bash
cd BACK/sahtek && composer install && cp .env.example .env && php artisan key:generate \
  && php artisan migrate --seed && php artisan serve
cd frontend && npm install && cp .env.example .env && npm run dev
```

Demo logins (development only): `admin@docthub.test` / `Admin12345`, `patient@docthub.test` / `Patient12345`.

## Tests and quality gate

```bash
cd BACK/sahtek && php artisan test
cd frontend && npm test
cd BACK/sahtek && php artisan knowledge:validate          # medical knowledge checks; exit code 1 on any error
```

CI (GitHub Actions, `.github/workflows/ci.yml`): API tests on MySQL 8, web app typecheck + tests + build, and a `knowledge:validate` report on the sample catalog (expected to fail until real content is reviewed; report only).

## Knowledge pipeline (raw layer)

External medical knowledge (WHO ICD-11, MedlinePlus) is downloaded **raw and untouched**, fingerprinted (SHA-256) and versioned. Nothing is downloaded until a person has confirmed the source licence in the admin: see [docs/sources.md](docs/sources.md).

```bash
cd BACK/sahtek
php artisan db:seed --class=KnowledgeSourceSeeder          # registers icd11 + medlineplus (licence: unverified)
php artisan knowledge:fetch icd11 --target=<ICD-11 code> --lang=en --lang=fr --lang=ar   # needs ICD_API_CLIENT_ID/SECRET in .env
php artisan knowledge:fetch medlineplus --target="<search term>"
```

Then structure them into the catalog as **draft** items (type chosen by you, no links, no severity):

```bash
php artisan knowledge:import icd11 --as=disease --dry-run
php artisan knowledge:import medlineplus                    # lists topics; then --only=<id> --as=symptom|disease
```

Check, release and trace:

```bash
php artisan knowledge:validate                         # quality gate (exit 1 on errors)
php artisan knowledge:export --notes="…"                # storage/app/exports/knowledge_vX.Y.json, approved content only
php artisan knowledge:history disease:<code>           # who changed what, when, old → new
```

Raw files: `storage/app/raw/{source}/{sha256}.{json|xml}`. Imported content is never published or approved automatically.
