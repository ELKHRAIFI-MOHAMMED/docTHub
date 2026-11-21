# docTHub V2 — Requirements

> "Your Health, Our Priority — Find the Right Doctor Near You."

docTHub V2 is an international **medical orientation and doctor discovery** platform.
It is an orientation tool, **not a diagnostic medical device**.

## 1. Users

| Role | Account | Capabilities |
|---|---|---|
| Patient | Yes | Profile, location & language, symptom checker, orientation results, doctor discovery, favorites, assessment history, data export, account deletion |
| Guest | No | Symptom checker and doctor discovery (nothing saved to a profile) |
| Doctor | No (V2) | Represented only by a **public professional profile** managed by admins |
| Administrator | Yes | Catalog, medical content, rules, doctors & verification, geography, languages, patients (read/deactivate), analytics, audit log |

## 2. Functional requirements

### Patient
- **FR-P1** Register: first/last name, email, phone, password, date of birth, optional sex, country, city, preferred language, plus explicit consents (terms, privacy, health-data processing).
- **FR-P2** Login / logout / current user (`/auth/me`).
- **FR-P3** Update profile; export personal data (JSON); delete account (soft delete + anonymisation).
- **FR-P4** Choose country, city and interface language at any time (also as guest).

### Symptom checker & orientation
- **FR-S1** Interactive body map with front and back views, multi-selection, keyboard and touch accessible.
- **FR-S2** Dynamic questionnaire: questions configured in the admin, global or per body part, shown/hidden by conditions on previous answers.
- **FR-S3** Rule-based orientation engine:
  - **emergency rules first** — a match interrupts the normal flow and shows a country-specific emergency instruction;
  - possible conditions (never presented as a diagnosis), associated symptoms, recommended specialty, guidelines.
- **FR-S4** Every result screen shows the medical disclaimer. Content not professionally reviewed is labelled and excluded in production.
- **FR-S5** No AI in V2. An interface exists for a future assistant; its output must always pass the safety rules.

### Doctor discovery
- **FR-D1** Filter by specialty, country, city, language, max fee, name; sort by distance, experience, fee, name; paginated.
- **FR-D2** Distance only when the patient explicitly shares geolocation **or** the selected city has coordinates; otherwise filter by city and show no distance.
- **FR-D3** Only `verified` + `published` doctors by default.
- **FR-D4** Doctor profile: photo, name, verification badge, specialties, bio, qualifications, experience, languages, locations + map, fees, contact.
- **FR-D5** Patients can save favourite doctors.

### Administration
- **FR-A1** CRUD + publish/archive: specialties, body parts, symptoms, diseases, guidelines, questions (+ options, conditions), orientation rules, countries, cities, languages.
- **FR-A2** Doctors: CRUD, photo upload, verification documents (private storage), verify / reject / suspend.
- **FR-A3** Patients: list, view, deactivate/reactivate.
- **FR-A4** Dashboard: counts, assessments per day, top recommended specialties, emergency alerts.
- **FR-A5** Every admin write is recorded in `audit_logs`.

## 3. Out of scope (strict)
Clinic/hospital management, establishment dashboards, clinic staff, multi-clinic admin, doctor-side patient management, EMR/prescriptions, telemedicine, online payments.

**Appointments** are optional in the brief and **deferred**: with no doctor dashboard, nobody is defined to confirm a request. This needs a product decision (see `V2_ROADMAP.md`).

## 4. Non-functional requirements
- **i18n**: English, French, Arabic (RTL). Medical content translatable. No hardcoded countries/cities/specialties in the frontend.
- **Security**: hashed passwords, Sanctum tokens, role middleware + policies, rate limiting, Form Request validation, Eloquent/bound queries only, validated uploads, audit logs, encrypted sensitive fields, consent records.
- **Privacy**: designed around GDPR principles (minimisation, consent, export, erasure). **No compliance claim is made**; a legal and technical assessment is required before production.
- **Medical safety**: no invented content is published. Every guideline/rule carries a source, review status and version. Seeded medical data is flagged `is_sample` and `draft`.
- **Definition of done**: UI + API + DB + validation + permissions + tests.
