# docTHub V2 — Architecture

## Overview

```
┌──────────────────────┐   JSON over HTTPS         ┌───────────────────────────────┐
│ frontend/ (React+TS) │ ────────────────────────▶ │ BACK/sahtek (Laravel 10)      │
│ Vite, RTK Query,     │  Authorization: Bearer    │  /api/v1/*  V2                │
│ React Router,        │  X-Locale: en|fr|ar       │  /api/*     V1 legacy (kept)  │
│ Tailwind, i18next    │                           └──────────────┬────────────────┘
└──────────────────────┘                                          │ Eloquent
                                                         ┌────────▼─────────┐
                                                         │ MySQL 8 / MariaDB │
                                                         └──────────────────┘
```

**Modular monolith**: one Laravel application organised by domain. No microservices.

## Backend layout (`BACK/sahtek/app`)

| Folder | Content |
|---|---|
| `Http/Controllers/Api/V1` | Auth, Catalog, Geo, Assessment, Doctor, Favorite, Patient |
| `Http/Controllers/Api/V1/Admin` | Admin controllers; catalog resources share `AdminCrudController` |
| `Http/Requests` | Form Requests (validation) |
| `Http/Resources` | API Resources (stable, translation-aware JSON) |
| `Http/Middleware/SetLocale` | Locale from `X-Locale` → `?lang=` → `Accept-Language` → `en` |
| `Http/Middleware/EnsureRole` | `role:admin`, `role:patient` |
| `Models` (+ `Concerns/HasTranslations`) | Eloquent models |
| `Services/Orientation` | `OrientationEngine`, `RuleEvaluator`, `QuestionFlow`, `ContentGate` |
| `Services/Ai` | `MedicalAssistant` interface, `NullMedicalAssistant`, `AiSafetyGuard` |
| `Services/Discovery/DoctorSearch` | Filtered + geographic doctor query |
| `Services/AuditLogger` | Writes `audit_logs` |
| `Policies` | `SymptomAssessmentPolicy` |
| `Console/Commands/ImportV1Data` | `php artisan docthub:import-v1` |

V1 code (`Controller.php`, V1 models, `/api/afficher-*` routes) is untouched so the V1 frontend keeps working during the transition.

## Key decisions

1. **Evolve the V1 Laravel app** instead of a new project: the V1 API stays alive, the Laravel 10 + Sanctum setup is reused, and `docthub:import-v1` reads legacy tables directly.
2. **Sanctum personal access tokens** (Bearer) instead of cookie-based SPA auth: frontend and API can live on different origins. Trade-off: a token in `localStorage` is exposed to XSS. Mitigations: no `dangerouslySetInnerHTML`, CSP in deployment, tokens revoked on logout, password change and account deletion.
3. **Polymorphic `translations` table** for catalog/medical content. Base columns hold English; `translations(locale, field, value)` overrides per locale with English fallback. UI strings live in frontend i18n files.
4. **Rule engine before AI.** Rules are data, evaluated deterministically and unit-tested. Emergency rules run first and short-circuit normal recommendations.
5. **Content review gate.** `DOCTHUB_ALLOW_UNREVIEWED_CONTENT` (default `false`; `true` only in local dev) decides whether `draft`/`in_review` rules and guidelines are used. Results built with unreviewed content are flagged and the UI shows a warning banner. **Exception:** non-rejected *emergency* rules are always evaluated, because over-triage is the safer failure mode. An admin disables one by setting it to `rejected` or `retired`.
6. **Assessments are addressed by UUID** so guests can use them without enumeration risk. Authenticated assessments are bound to the patient and protected by a policy.
7. **Distance**: Haversine formula in SQL (portable across MySQL 8 / MariaDB) with a bounding-box pre-filter on indexed lat/lng columns.
8. **Maps**: `MapView` embeds OpenStreetMap (no API key). Mapbox or Google can replace it behind the same component.
9. **Storage**: photos on the `public` disk, verification documents on the private `local` disk; both can be switched to S3 through `.env`.

## Frontend layout (`frontend/src`)

```
app/         store.ts, router.tsx, api.ts (RTK Query base: env URL, token, locale)
components/  common, layout, doctors (DoctorCard, MapView), medical (BodyMap, Disclaimer, EmergencyAlert)
features/    auth, patient, symptom-checker, orientation, doctor-discovery, favorites, admin
i18n/        i18next setup, en/fr/ar resources, RTL
types/       API types
utils/       helpers
```

- API base URL from `VITE_API_BASE_URL`; never hardcoded.
- Locale and selected country/city are kept in a Redux `preferences` slice persisted to `localStorage`.
- `<html dir="rtl">` for Arabic; layouts use logical utilities (`ms-`, `me-`, `ps-`, `text-start`).

## Symptom checker request flow

```
POST /assessments                {body_part_ids, country_id, city_id, sex?, age_years?} → uuid
GET  /assessments/{uuid}/questions   visible questions given the answers so far
POST /assessments/{uuid}/answers     upsert answers (repeat until no question left)
POST /assessments/{uuid}/evaluate    OrientationEngine → results + alerts persisted
GET  /assessments/{uuid}/results     orientation result
GET  /doctors?specialty_id=…&city_id=…&lat=…&lng=…&sort=distance
```
