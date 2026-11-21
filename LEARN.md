# LEARN.md — docTHub V2 engineering journal

Living document: technical concepts, architecture decisions, progress checklist and lessons learned.
Related docs: `docs/V2_REQUIREMENTS.md`, `docs/V2_ARCHITECTURE.md`, `docs/V2_DATABASE.md`, `docs/V2_API.md`, `docs/V2_ROADMAP.md`, `docs/MIGRATION_FROM_V1.md`, `docs/DEPLOYMENT.md`.

---

## 1. Progress checklist

Legend: ✅ done (UI + API + DB + validation + permissions + tests) · 🟡 partial · ⬜ not started · ⏸ deferred (needs decision)

### Phase 0 — Audit
- ✅ V1 inspected (Laravel 10 API + CRA React 18 frontend), 13 problems documented (`MIGRATION_FROM_V1.md`)
- ✅ V2 requirements, architecture, database, API, roadmap, migration docs

### Phase 1 — Foundation
- ✅ 6 V2 migrations (≈35 tables, FKs, indexes, unique constraints, soft deletes); V1 tables untouched
- ✅ Missing `users` migration added (V1 relied on it without having it)
- ✅ Sanctum token auth: register (with 3 consents), login, logout, me
- ✅ Roles (`roles`, `user_roles`) + `role:` middleware; disabled accounts rejected
- ✅ Locale middleware (`X-Locale` / `?lang=` / `Accept-Language`) + polymorphic `translations`
- ✅ Rate limiting (global, auth, assessments); JSON errors on all API routes
- ✅ Frontend: Vite + React 19 + TypeScript + RTK Query + React Router 7 + Tailwind 4 + i18next (en/fr/ar, RTL)

### Phase 2 — Medical catalog
- ✅ Public catalog endpoints (published only; diseases gated by review status)
- ✅ Admin CRUD + publish/archive for specialties, body parts, symptoms, diseases, guidelines, countries, cities, languages
- ✅ Translations editable in admin (FR/AR next to each translatable field)
- ✅ Audit log on every admin write
- ✅ Guideline versioning + review workflow (approve requires reviewer name; edit → back to review)

### Phase 3 — Symptom checker
- ✅ Interactive body map (front/back, multi-select, keyboard, accessible list alternative)
- ✅ Dynamic questionnaire (per body part + global, display conditions, stale answers dropped)
- ✅ Rule engine (`OrientationEngine`, `RuleEvaluator`) with emergency-first evaluation
- ✅ Emergency interruption **during** the questionnaire (`emergency_detected`) with country emergency number
- ✅ Results page: summary, possible conditions (never "diagnosis"), associated symptoms, specialty, guidelines with source/review badges, disclaimer
- ✅ Admin CRUD for questions (options + conditions as JSON) and rules (validation workflow, versioning)
- ✅ AI abstraction (`MedicalAssistant`, `NullMedicalAssistant`, `AiSafetyGuard`) — no AI enabled

### Phase 4 — Doctor discovery
- ✅ Search: specialty, country, city, language, max fee, name; sort relevance/distance/experience/fee/name; pagination
- ✅ Location required (no unfiltered directory dump); distance only with explicit geolocation
- ✅ Haversine in SQL + bounding box on indexed lat/lng
- ✅ Only verified + published doctors publicly; demo doctors flagged and never shown as "verified"
- ✅ Doctor profile page with OSM map, contact, fees, languages
- ✅ Admin doctor management: profile, specialties, languages, location, photo upload, private documents, verification decision (requires approved document for real doctors)

### Phase 5 — Patient features
- ✅ Favorites
- ✅ Assessment history
- ✅ Profile edit, data export (JSON), account deletion (anonymisation)
- ⏸ Appointment requests — who confirms without a doctor dashboard? (see roadmap)
- ⏸ Email notifications — nothing to notify until appointments exist

### Phase 7 — Data collection (provenance, versions, ground truth, case summary)
- ✅ Knowledge sources (with mandatory licence) and source documents; `source_document_id` on rules, guidelines and conditions
- ✅ Fetch + SHA-256 fingerprint of normalised text; a change sends every **approved** item citing the document back to `in_review` (audit-logged); manual "Fetch now" + daily `docthub:sources:check`
- ✅ SSRF protection: http(s) only, private / reserved / loopback / link-local addresses refused, redirects followed manually and re-checked each hop
- ✅ `questionnaire_version` and `ruleset_version` fingerprints stamped on every assessment
- ✅ Readable case summary (voie A): per-question translatable `summary_template`, generic fallback, emergency-aware, copy / print, EN/FR/AR
- ✅ Post-consultation outcome (structured, no free text) for guests and patients; daily `docthub:send-follow-ups` (7 days, once, consent + e-mail opt-in only)
- ✅ Admin analytics: concordance (recommended vs consulted specialty), diagnosis among shown possibilities, emergency rate by rule, response rate, usefulness, content coverage (approved / traced), versions; small-sample warning under 30
- ✅ Pseudonymised JSON-Lines export (HMAC subject key, age band, week, no identifiers, consented patients only, audit-logged)
- ✅ Tests: +15 backend (65 total / 393 assertions), +3 frontend (26 total), browser run in French (summary, follow-up, analytics, sources)
- ⬜ Voie B (AI rewriting) — not started; interface and guard exist
- ⬜ DNS-rebinding hardening of the fetcher (pin the resolved IP for the connection)

### Data pipeline V2 — phase 1: raw layer and traceability (branch `feature/data-pipeline-v2`)
- ✅ `knowledge_sources.code` + `license_status` (`unverified/confirmed/unclear`, set by a human) + `license_checked_at` + `attribution`; `KnowledgeSourceSeeder` registers `icd11` and `medlineplus` as `unverified`
- ✅ `source_documents`: one row per fingerprint (`unique(url, language, content_hash)`), `superseded` status, `previous_document_id`, `raw_path`, `raw_format`, `raw_bytes`, `http_status`, `metadata`
- ✅ `knowledge:fetch {source}`: interface `Knowledge\Contracts\SourceFetcher`, `IcdFetcher` (OAuth2 client credentials, `API-Version: v2`, `Accept-Language`), `MedlinePlusFetcher` (XML web service, en only); raw body stored untouched in `storage/app/raw/{source}/{sha}.{ext}`
- ✅ Change of a known URL → new version, previous `superseded`, log "Source modifiée" + audit, approved linked items → `in_review`
- ✅ `ResilientHttp`: timeouts, retries with exponential backoff (connection errors, 429, 5xx), `Retry-After`, per-source minimum interval, max body size
- ✅ Old page monitor renamed `PageMonitor`; it no longer touches pipeline documents (409 on "fetch now", skipped by `docthub:sources:check`)
- ✅ Tests: +14 backend (79 total), all HTTP simulated with `Http::fake` + `preventStrayRequests`
- ⬜ Licences to be confirmed by the project owner (see `docs/sources.md`) — nothing has been downloaded for real
- ⬜ DNS-rebinding hardening still open (fixed hosts in config for API sources)

### Data pipeline V2 — phase 2: staging and structuring
- ✅ `knowledge:import {source} --as=disease|symptom [--only=…] [--dry-run]` into the existing `diseases` / `symptoms` tables
- ✅ ICD-11: code, title, URI only (decision 2026-11-21); MedlinePlus: title, “also called” synonyms, summary without HTML; linked pages never read
- ✅ Normalisation (UTF-8 check, HTML removed, trim), stable codes `icd11_<code>` / `mplus_<id>`; translations only for languages provided, others in `needs_translation`
- ✅ `knowledge_aliases` (deduplicated per locale, conflicts reported); cross-source duplicates reported, never merged
- ✅ Idempotent; reviewed / published items never overwritten (`conflict`); all rows draft, version 1, `source_document_id`; no severity, specialty, body part, link or rule
- ✅ Tests: +9 backend (88 total)
- ⬜ Type (symptom vs condition) chosen by the operator: ICD-11 chapter ranges not verified on an official page

### Data pipeline V2 — phase 3: quality checks
- ✅ `knowledge:validate [--format=table|markdown|json] [--strict]`: broken links (rules, conditions, guidelines, options, aliases; soft-deleted = missing), approved items (EN/FR/AR, source, reviewer, version), duplicate codes / external identities, severity and priority 0..100, unlinked conditions (error if approved/published, warning if draft), emergency rules without reviewer
- ✅ `reviewed_by_name` / `reviewed_at` on diseases and rules; admin fields; rule priority 0..100
- ✅ Sample catalog result: **13 errors** = 8 emergency rules without reviewer + 5 published sample conditions without any symptom (not fixed: that would be inventing links)
- ✅ GitHub Actions: API tests on MySQL 8 (blocking), web app typecheck/tests/build (blocking), validate report on the sample catalog (non-blocking)
- ✅ Tests: +9 backend (97 total)
- ⬜ The workflow has not run yet on GitHub (no push done)
- ⬜ Approving through the admin does not yet *refuse* a missing reviewer (the validator reports it); enforcing it changes an existing admin test

### Data pipeline V2 — phase 4: versioned exports and audit trail
- ✅ `knowledge:export [--major] [--notes]`: deterministic snapshot of approved conditions / guidelines / rules + the reference items they use → `storage/app/exports/knowledge_vX.Y.json` + immutable `knowledge_releases` row; refused while `knowledge:validate` fails or nothing is approved; no new version when content is unchanged
- ✅ `symptom_assessments.knowledge_version` stamped at creation and refreshed at evaluation (`X.Y` / `unreleased` / `unreviewed`), exposed in results, analytics (`knowledge_versions`) and the pseudonymised export
- ✅ Content hash cached and invalidated on every admin write, import and review reset
- ✅ Audit trail: admin updates journal columns + translations + links (condition symptoms / body parts, question options / conditions) with old and new values; changes of every save are kept (rule version bump); no-op saves not journaled; command-line actor = `artisan <command>`; importer records old values
- ✅ `knowledge:history type:code`, audit API filters (item, period), Admin → Audit trail page (EN/FR/AR)
- ✅ Tests: +11 backend (108), +2 frontend (28)
- ⬜ Export of the current sample catalog is refused (13 validation errors) — expected until content is reviewed
- ⬜ Changes made outside the app (SQL, tinker) bypass the audit trail and the hash cache

### Data pipeline V2 — phase 5: front-end
- ✅ Every data screen: skeleton while loading (`role=status`, `aria-busy`), clear error + **Retry** (`ErrorState`, no retry on 404), empty state
- ✅ Emergency screen: full screen portal (`role=alertdialog`, `aria-modal`, z-index above everything, scroll locked, focus on the `tel:` button, focus trapped); after "I understand" a bar stays fixed at the bottom with the call button
- ✅ Results: permanent "orientation, not a diagnosis" banner; "not reviewed" banner when any shown item is not approved (not only in development); all sources in one collapsible section. No sponsored doctors exist, so nothing to separate
- ✅ Assessment in progress remembered on the device for 24 h (id only), resume / discard card, cleared on results and logout
- ✅ Touch targets ≥ 44 px (buttons, inputs, header selects, nav, view toggle, footer links), low-contrast greys replaced (slate-400 → 500), back arrow mirrored in RTL
- ✅ Checked in real Chrome at 360 px (EN/FR/AR): no horizontal overflow, no interactive element under 44 px except the hidden skip link
- ✅ V1 front-end removed from the branch, kept in tag `archive/front-v1`
- ✅ Tests: +7 frontend (35 total); backend unchanged (108)
- ⬜ No automated contrast audit tool (axe) in the test suite; contrast checked by colour choice
- ⬜ Main bundle 523 kB (170 kB gzip) — split vendor chunks later

### Phase 6 — Quality & deployment
- ✅ Backend: 50 tests / 276 assertions (feature + unit) — auth, authorisation, validation, rules, emergency rules, discovery, admin, privacy
- ✅ Frontend: 23 tests (components, forms, API integration with mocked fetch, RTL, i18n key parity, auth guards)
- ✅ End-to-end run in real Chrome (playwright-core, not committed): normal flow, emergency flow, RTL, registration + favourite, admin
- ✅ Production build; deployment guide (`docs/DEPLOYMENT.md`)
- ✅ CI pipeline (GitHub Actions) — `.github/workflows/ci.yml` (data pipeline phase 3)
- ⬜ Bundle splitting: main chunk 510 kB (166 kB gzip); split vendor/i18n if needed
- ⬜ Booking concurrency tests — N/A until appointments exist

---

## 2. Architecture decisions (ADR summary)

| # | Decision | Why | Trade-off |
|---|---|---|---|
| 1 | Evolve `BACK/sahtek` in place; V2 under `/api/v1`, V1 routes kept | Keeps V1 working; import command reads V1 tables directly | Two generations of code in one app until V1 is removed |
| 2 | New frontend in `frontend/` (Vite + TS) instead of upgrading CRA | CRA is unmaintained; TS + Vite requested | V1 frontend left as-is (legacy) |
| 3 | Sanctum **personal access tokens** | Frontend and API on different origins | Token in `localStorage` (XSS risk) → no `dangerouslySetInnerHTML`, CSP in prod, revoke on logout/delete/deactivate |
| 4 | Polymorphic `translations` table with English base columns | One mechanism for every translatable entity; fallback is free | One extra eager-loaded query per list (scoped to the current locale) |
| 5 | Rule engine as data, AI deferred | Deterministic, testable, reviewable by clinicians | Rules need curation; JSON clause editing in admin is technical |
| 6 | `ContentGate`: only `approved` content in production, draft emergency rules always active | Never publish unreviewed advice; never miss a red flag | Over-triage possible with draft emergency rules (accepted) |
| 7 | Emergency match ⇒ return only the emergency instruction | Spec: emergency rules take priority and interrupt the flow | Patient sees no conditions in that case (intended) |
| 8 | Symptoms only reinforce conditions linked to selected body parts or matched by a rule | A generic symptom (fever) must not suggest unrelated organs | A condition must be linked to a body part or a rule to appear |
| 9 | Location context mandatory for `/doctors` | Spec: don't return all doctors unfiltered | Home page "discover" requires a selected country |
| 10 | Distance only with explicit device coordinates | Never pretend: city filter without distance otherwise | No "approximate distance from city centre" |
| 11 | Assessments addressed by UUID; guest-capable | Symptom checking must not require an account | Anyone holding a guest UUID can read that guest result (unguessable) |
| 12 | Admin catalog CRUD through one base controller + config | 11 near-identical resources | Nested data (options, links) edited as JSON |
| 13 | OpenStreetMap embed instead of Google/Mapbox | No API key/billing needed for V2 | Basic map; swap `MapView` to change provider |
| 15 | Outcomes are structured (choices, codes), never free text | Ground truth must be comparable and must not collect unintended personal data | Less nuance than a free comment |
| 16 | Record-level export only for patients with active health-data consent; guests appear in aggregates only | Guests have not given an explicit consent | Smaller export sample |
| 17 | A changed source never unpublishes content by itself; it sends approved items back to review | Humans decide; a page layout change must not silently remove advice | Reviewers must handle false positives (dynamic pages) |
| 18 | Raw layer = one row per fingerprint, raw bytes on disk, licence gate in the database | Full history (what exactly was read, when), reproducible imports; a human confirms each licence | Disk grows with each version (content-addressed, so identical content is stored once) |
| 19 | API sources hash the **raw bytes**; the page monitor keeps hashing **normalised text** | API JSON/XML is stable; HTML pages change cosmetically | Two fingerprint meanings in one table, distinguished by `raw_path` (set = raw bytes) |
| 20 | MedlinePlus French/Arabic are "skipped", never translated; ICD-11 only uses WHO's own languages | ICD-11 terms exclude translations without WHO agreement; no silent machine translation | FR/AR coverage depends on publishers |
| 21 | The operator chooses the target type (`--as`), the importer never infers it | Neither source gives a verified symptom/condition flag | One command per type |
| 22 | Imports never overwrite non-draft items and never merge sources | Review must stay meaningful; merging ICD-11 with another source is a mapping | Reviewers resolve `conflict` and duplicate notes by hand |
| 23 | Releases are snapshots of approved content; assessments record the release whose content hash matches the live approved content | Analyses compare like with like; no false claim that an assessment used a release when content changed since | `unreleased` until someone exports |
| 24 | Journal at the controller level (old/new values, translations, links) rather than model observers | Links and translations are saved outside the model events; one entry per admin action | Writes outside the controllers / commands are not journaled |
| 14 | Pinned latest *known* majors (Vite 7, TS 5.9, Vitest 3, i18next 25) instead of the newest (Vite 8, TS 7, Vitest 5) | Avoid unverified breaking changes during the build | Upgrade later in a dedicated step |

---

## 3. Technical concepts used

- **Modular monolith**: one deployable, code grouped by domain (Orientation, Discovery, Admin…), shared DB with FKs.
- **Form Requests / validation**: public & patient endpoints use dedicated `FormRequest` classes; admin resources declare `rules()` in their controller. Per-answer validation depends on the question type, so it lives in `AssessmentController::normaliseAnswer`.
- **API Resources**: stable output, locale-aware (`translate()`), never leak private fields (documents' `path` is `$hidden`).
- **Policies**: `SymptomAssessmentPolicy::access(?User, …)` supports guests (nullable user) and owners.
- **Encrypted casts**: `patients.date_of_birth` uses Laravel's `encrypted` cast.
- **Haversine**: `6371 * acos(cos φ1 cos φ2 cos(λ2−λ1) + sin φ1 sin φ2)`, wrapped in `LEAST(1, …)` to avoid `acos(>1)` rounding errors.
- **RTK Query**: one `createApi` with `injectEndpoints` per feature; the locale is part of query args so changing language refetches translated content.
- **RTL**: `<html dir="rtl">` + Tailwind logical utilities (`ms-`, `ps-`, `text-start`, `border-s`) — no separate RTL stylesheet.
- **i18n plurals**: Arabic needs `_zero/_one/_two/_few/_many/_other`; a test checks key parity across en/fr/ar.

---

## 4. Lessons learned (bugs found while building)

1. **V1 had no `users` migration** — `migrate:fresh` failed on a fresh DB. Added the standard Laravel migration.
2. **`shouldRenderJsonWhen()` is Laravel 11+.** On Laravel 10 override `shouldReturnJson()` in the exception handler.
3. **Model defaults vs DB defaults**: `User::create()` without `is_active` produced `null` in memory (DB default only applies on reload) → admins were treated as disabled. Fixed with `$attributes` defaults on the model.
4. **Conditional `required_if` on partial updates**: editing one field of a rule failed because `disease_id` became "required". Conditional requirements now only apply on create or when the type / field is actually sent.
5. **Over-broad scoring**: "fever" alone suggested sinusitis and gastroenteritis for a chest complaint. Symptoms now only reinforce candidates.
6. **Emergency message language**: alert text was stored in the assessment's original locale; it is now rendered in the viewer's locale (stored copy kept for audit).
7. **`withHeader()` in Laravel tests persists** across subsequent requests in the same test — pass headers per request instead.
8. **jsdom + Node 24 `Request`**: jsdom's `AbortSignal` is rejected by undici's `Request`, breaking RTK Query and React Router in tests. Test-only `Request` subclass drops the signal.
9. **Tailwind class conflicts**: `cx('bg-white', 'bg-brand-700')` doesn't override — CSS order wins, not class order. The recommended-specialty card rendered white on white (caught by an E2E screenshot, not by unit tests). Avoid overriding colour/padding utilities through `className` on base components.
11. **Logging in changes the UI language** to the account's preferred language: E2E selectors written for one language broke after the admin logged in. Tests should set the expected language explicitly.
12. **`orWhere` inside `whereHas`/`whereDoesntHave`** must be wrapped in a nested `where()`, otherwise it escapes the relation constraint (caught in review of the follow-up query).
13. **Fingerprinting web pages**: hashing raw HTML flags every cosmetic change. We hash normalised text (scripts/styles removed, whitespace collapsed); dynamic pages can still produce false "changed" alerts.
10. **E2E waits**: waiting a fixed 500 ms after "Next" raced the API; wait for the heading to change instead.
14. **MariaDB refuses to drop a unique index used by a foreign key** (`source_documents_source_url_unique` backed `knowledge_source_id`). Create the replacement index first, then drop.
15. **`php artisan migrate --env=testing` without a `.env.testing` runs against `.env`** (the dev database). Tests get their database from `phpunit.xml`; never pass `--env=testing` by hand.
17. **Console events in tests**: Laravel only reroutes Symfony console events (`CommandStarting`) outside unit tests; tests that check the CLI actor need the `WithConsoleEvents` trait.
18. **Dots in JSON keys**: `assertJsonPath('data.knowledge_versions.1.0')` reads `1` then `0`. Compare the whole map instead.
19. **Sticky vs sticky**: an emergency bar `sticky top-0` covered the sticky site header when scrolling. A bar fixed at the bottom keeps both visible.
20. **The app persists preferences on every Redux action**: a test script that writes `localStorage` and reloads can be overwritten by the running app. Seed storage from a static page of the same origin first.
16. **PHP arrow functions capture by value**: `fn () => array_shift($queue)` always shifts a fresh copy. Use `function () use (&$queue)` for sequences in fakes.

---

## 5. Medical-safety rules (do not break)

- Never present a condition as a diagnosis (UI wording "possible explanations", API `match_strength`).
- Emergency rules are evaluated first and interrupt the flow; AI output can never bypass them.
- Every rule/guideline carries `source_name`, review status and version; seeded content is `draft` + `is_sample`.
- `DOCTHUB_ALLOW_UNREVIEWED_CONTENT=false` in production.
- Demo doctors are never shown as verified; demo data is never seeded in production.
