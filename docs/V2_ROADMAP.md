# docTHub V2 — Roadmap

Status legend: ✅ done · 🟡 partial · ⬜ not started · ⏸ deferred (needs decision)

| Phase | Item | Status |
|---|---|---|
| 0 Audit | V1 inspection, docs, migration plan | ✅ |
| 1 Foundation | Migrations, models, Sanctum auth, roles, locale middleware, rate limiting, frontend skeleton, i18n (en/fr/ar + RTL) | ✅ |
| 2 Catalog | Public catalog API, admin CRUD for catalog + geography + languages, translations, audit log | ✅ |
| 3 Symptom checker | Body map, dynamic questionnaire, rule engine, emergency alerts, results page | ✅ |
| 4 Doctor discovery | Search/filter/sort/paginate, distance, profiles, verification, admin doctor management | ✅ |
| 5 Patient features | Favorites, assessment history, data export, account deletion | ✅ |
| 5 Patient features | Appointment requests | ⏸ |
| 5 Patient features | Email notifications | ⏸ |
| 7 Data collection | Source provenance + change detection, version stamps, case summary, follow-up, analytics, pseudonymised export | ✅ |
| 7 Data collection | AI rewriting of the summary (voie B) | ⬜ |
| 6 Quality & deployment | Backend feature/unit tests, frontend tests, deployment guide, CI (GitHub Actions) | ✅ |
| Data pipeline | Phase 1 raw layer · Phase 2 import · Phase 3 `knowledge:validate` + CI | ✅ |
| Data pipeline | Phase 4 versioned exports, `knowledge_version` on assessments, audit trail | ✅ |
| Data pipeline | Phase 5 front-end | ⬜ |

`LEARN.md` holds the live progress checklist; this file lists the plan and the open decisions.

## Open decisions (blockers for product owner)
1. **Appointments.** Doctors have no dashboard, so who confirms an appointment request? Options: (a) request = email sent to the doctor's public address, no status tracking; (b) admins confirm on the doctor's behalf; (c) postpone until a doctor portal exists. Tables `appointments` / `appointment_status_history` will be created once decided.
2. **Medical content review.** All seeded medical content is sample data. A qualified reviewer must approve rules and guidelines before production; `DOCTHUB_ALLOW_UNREVIEWED_CONTENT` must be `false` in production.
3. **Emergency numbers.** Seeded per-country numbers are marked `emergency_number_verified = false` until checked against an official source for each country.
4. **Map provider.** OpenStreetMap embed is used now. Switching to Google Maps / Mapbox needs an account and API key.
5. **Legal.** Privacy policy, terms and a GDPR / Moroccan law 09-08 assessment are required before launch. The platform does not claim compliance.

## Later (post-V2)
- AI-assisted free-text symptom entry behind `MedicalAssistant` + `AiSafetyGuard` (requires consent flow and DPA with provider).
- Redis cache for catalog endpoints if traffic requires it.
- Doctor self-service portal (out of scope for V2).
