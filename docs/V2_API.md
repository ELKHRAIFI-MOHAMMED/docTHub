# docTHub V2 — REST API

Base URL: `/api/v1` · JSON only · Laravel 10 + Sanctum.

## Conventions

| Topic | Rule |
|---|---|
| Auth | `Authorization: Bearer <token>` (from `/auth/login` or `/auth/register`). |
| Language | `X-Locale: en|fr|ar` (or `?lang=`, or `Accept-Language`). Response header `Content-Language`. Translatable content falls back to English. |
| Success | Single resource: `{"data": {...}}`. Lists: `{"data": [...], "links": {...}, "meta": {"current_page", "last_page", "per_page", "total"}}`. |
| Errors | `{"message": "..."}`; validation (422): `{"message", "errors": {"field": ["..."]}}`. |
| Status codes | 200, 201 created, 204 deleted, 401 unauthenticated, 403 forbidden / disabled account, 404, 409 state conflict, 422 validation, 429 rate limited. |
| Rate limits | global 120/min; `auth` 10/min per IP and 5/min per email+IP; assessment writes 60/min. |

## Authentication

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/auth/register` | – | Patient registration. Body: `first_name, last_name, email, phone?, password, password_confirmation, date_of_birth?, sex?, country_id, city_id?, preferred_language, consents{terms,privacy,health_data}` (all three must be accepted). → 201 `{data:{token, token_type, user}}` |
| POST | `/auth/login` | – | `email, password` → `{data:{token, user}}` |
| POST | `/auth/logout` | token | Revokes the current token |
| GET | `/auth/me` | token | Current user with roles and patient profile |

## Reference data (public)

| Method | Path | Notes |
|---|---|---|
| GET | `/languages` | Active languages (`direction` = ltr/rtl) |
| GET | `/countries` | Active countries, with `emergency_number` and `emergency_number_verified` |
| GET | `/cities?country_id=` | Required `country_id` |
| GET | `/body-parts?view=front|back` | Published only |
| GET | `/specialties` | Published only |
| GET | `/symptoms?q=` | Published only |
| GET | `/diseases?specialty_id=&body_part_id=` | Published **and** usable (approved, or any review status when unreviewed content is allowed) |

## Symptom checker

Guests may use it. If the caller is an authenticated patient, the assessment is bound to them and only they (or an admin) can access it (policy `SymptomAssessmentPolicy`). Assessments are addressed by UUID.

| Method | Path | Notes |
|---|---|---|
| POST | `/assessments` | `body_part_ids[] (1–10, published), country_id?, city_id?, sex?, age_years?` → 201 assessment summary. Patient profile values are used as defaults. |
| GET | `/assessments/{uuid}/questions` | `{data:{assessment_id, status, questions[], complete, next_question_id, emergency_detected}}`. Only questions whose display conditions are met are returned. |
| POST | `/assessments/{uuid}/answers` | `answers[]: {question_id, option_codes[]? , value?}`. Validated against the question type, options and min/max. Answers made irrelevant by a change are removed. 409 if already evaluated. Returns the same payload as `/questions`; **`emergency_detected: true` means stop and evaluate now.** |
| POST | `/assessments/{uuid}/evaluate` | Runs the orientation engine. 422 if required questions are missing **unless** an emergency was detected. Returns the result (below). |
| GET | `/assessments/{uuid}/results` | 409 if not evaluated yet. |

Result payload:

```json
{
  "data": {
    "id": "uuid", "status": "completed", "engine_version": "1.0.0",
    "body_parts": [...], "summary": [{"question": "...", "answer": "..."}],
    "emergency": null | {"severity": "critical|urgent", "messages": ["..."], "emergency_number": "112",
                          "emergency_number_verified": false, "country": "France"},
    "conditions": [{"disease": {...}, "rank": 1, "match_strength": "strong|moderate|weak"}],
    "specialties": [{...}],          // first = recommended
    "guidelines": [{"title", "content", "source_name", "review_status", "is_sample", ...}],
    "uses_unreviewed_content": true, "unreviewed_notice": "...", "disclaimer": "..."
  }
}
```

When `emergency` is not null, `conditions`, `specialties` and `guidelines` are intentionally empty.

### Follow-up (ground truth)
| Method | Path | Notes |
|---|---|---|
| GET | `/assessments/{uuid}/outcome` | Current outcome (`collected: false` if none) |
| PUT | `/assessments/{uuid}/outcome` | `consulted (bool, required), consulted_specialty_id?, diagnosis_disease_id?, diagnosis_icd11? (e.g. BA40.Z), went_to_emergency?, helpfulness? (1–5)`. 409 if not evaluated. `consulted=false` clears the clinical fields. |

The result payload also contains `case_summary: {paragraphs[], text, generated_by: "templates"}` and `follow_up: {collected, available}`.

## Doctor discovery (public; `is_favorite` filled when the caller is a patient)

| Method | Path | Notes |
|---|---|---|
| GET | `/doctors` | Filters: `q, specialty_id, country_id, city_id, language (code), max_fee, lat, lng, radius_km (≤200), sort=relevance|distance|experience|fee|name, page, per_page (≤50)`. **A location is required**: `country_id`, `city_id` or `lat`+`lng` (422 otherwise). Only `published` + `verified` doctors. `meta.distance_basis` = `coordinates` (distance computed) or `none` (no distance shown). |
| GET | `/doctors/nearby?lat=&lng=&radius_km=&specialty_id=` | Coordinates required; sorted by distance |
| GET | `/doctors/specialty/{id}` | Same filters as `/doctors` |
| GET | `/doctors/{id or slug}` | Detailed profile with locations. 404 if not publicly visible (admins can see any). |
| GET | `/doctors/countries` | Countries with at least one public doctor |
| GET | `/doctors/cities?country_id=` | Cities with at least one public doctor |

## Patient (`auth:sanctum` + `role:patient`)

| Method | Path | Notes |
|---|---|---|
| GET / PUT | `/patient/profile` | Profile + preferences (`preferred_language, share_location, email_notifications`). City must belong to country. |
| GET | `/patient/assessments` | History (paginated) |
| GET | `/patient/favorites` | Favourite doctors (paginated) |
| POST / DELETE | `/favorites/{doctorId}` | Idempotent. Only public doctors can be added. |
| GET | `/patient/export` | Data export (JSON) |
| DELETE | `/patient/account` | Body `password`. Anonymises and soft-deletes; assessments are kept de-identified. |

## Administration (`auth:sanctum` + `role:admin`) — prefix `/admin`

Generic CRUD for `specialties`, `body-parts`, `symptoms`, `diseases`, `guidelines`, `questions`, `rules`, `countries`, `cities`, `languages`, `doctors`:

| Method | Path | Notes |
|---|---|---|
| GET | `/admin/{resource}?q=&status=&sort=-field&per_page=&trashed=1&<filter>=` | Paginated |
| POST | `/admin/{resource}` | Create (201). Translatable resources accept `translations: {"fr": {"name": "..."}, "ar": {...}}` |
| GET / PUT / DELETE | `/admin/{resource}/{id}` | Delete returns 409 if the record is still referenced |
| PATCH | `/admin/{resource}/{id}/status` | `{status}` — `draft/published/archived`; for `rules` the review workflow `draft/in_review/approved/rejected/retired` |

Resource-specific payloads:
- `diseases`: `symptoms: [{id, weight}]`, `body_parts: [{id, weight}]`; `source_name` required to approve.
- `questions`: `options: [{code, label, score?, symptom_id?, translations?}]`, `conditions: [{depends_on_question_id, operator, value}]`.
- `rules`: `body_part_id?, question_id?, operator, expected_value, conditions: [{question|field, operator, value}], result_type, disease_id|specialty_id|guideline_id|emergency_severity, priority, weight, message, source_name`. At least one criterion is required. Editing the logic of an approved rule bumps `version` and resets it to `in_review`.
- `guidelines`: approving requires `reviewed_by_name`; editing content bumps `version` and sends it back to `in_review`.
- `doctors`: `specialties: [{id, is_primary}]`, `language_ids[]`, `locations: [{country_id, city_id, address, latitude, longitude, ...}]`.

Also `knowledge-sources` (licence required; `code`, `license_status` = `unverified|confirmed|unclear`, `license_checked_at` filled when confirmed, `attribution`) and `source-documents`. `POST /admin/source-documents/{id}/fetch` (409 for documents of the raw pipeline, refreshed by `php artisan knowledge:fetch`) downloads the document (public http(s) only, redirects re-checked), stores its fingerprint and returns `meta: {status, changed, sent_to_review}`.

Analytics:

| Method | Path | Notes |
|---|---|---|
| GET | `/admin/analytics?days=90` | Concordance, diagnosis among shown possibilities, emergency rate and by rule, response rate, usefulness, content coverage, versions |
| GET | `/admin/analytics/export?days=365` | Pseudonymised JSON Lines, consented patients only, audit-logged |

Doctor verification and files:

| Method | Path | Notes |
|---|---|---|
| PATCH | `/admin/doctors/{id}/verification` | `verification_status = pending|verified|rejected|suspended`. Verifying a non-demo doctor requires an approved document. |
| POST | `/admin/doctors/{id}/photo` | multipart `photo` (jpeg/png/webp, ≤2 MB, 100–4000 px) → public disk |
| POST | `/admin/doctors/{id}/documents` | multipart `type, file` (pdf/jpeg/png, ≤5 MB) → **private** disk |
| PATCH | `/admin/doctors/{id}/documents/{doc}` | `status = pending|approved|rejected`, `notes` |
| GET | `/admin/doctors/{id}/documents/{doc}/download` | Audit-logged |

Patients, dashboard, audit:

| Method | Path | Notes |
|---|---|---|
| GET | `/admin/patients?q=&country_id=` | Account data + counts (no assessment content) |
| GET | `/admin/patients/{id}` | Audit-logged view |
| PATCH | `/admin/patients/{id}/active` | `{is_active}`; deactivation revokes tokens |
| GET | `/admin/dashboard` | Counts, assessments per day (30 d), top specialties, emergency alerts |
| GET | `/admin/audit-logs?action=&user_id=` | Audit trail |

## Legacy V1 (unchanged)
`GET /api/afficher-partie_corp`, `/api/afficher-malade/{idp}/{idg}`, `/api/afficher-consigne/{idm}`, `/api/afficher-symptome/{idm}`, `/api/afficher-medcin/{ids}`.
