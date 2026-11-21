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
| `docs/` | Requirements, architecture, database, API, roadmap, V1 migration, deployment |
| `LEARN.md` | Progress checklist, decisions and lessons learned |

## Quick start

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). In short:

```bash
cd BACK/sahtek && composer install && cp .env.example .env && php artisan key:generate \
  && php artisan migrate --seed && php artisan serve
cd frontend && npm install && cp .env.example .env && npm run dev
```

Demo logins (development only): `admin@docthub.test` / `Admin12345`, `patient@docthub.test` / `Patient12345`.

## Tests

```bash
cd BACK/sahtek && php artisan test
cd frontend && npm test
```
