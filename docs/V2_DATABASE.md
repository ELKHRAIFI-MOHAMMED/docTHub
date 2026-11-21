# docTHub V2 — Database

Target: **MySQL 8** (developed and tested on MariaDB 10.11, which is what XAMPP ships). All V2 tables use `bigint` ids, InnoDB, `utf8mb4`.
V1 tables (`genre`, `parties_corp`, `specialite`, `malade`, `symptome`, `consigne`, `medcin`) are kept read-only for the import command.

Migrations live in `BACK/sahtek/database/migrations/2025_*` and are grouped by domain.

## Conventions
- `status` on catalog records: `draft | published | archived`. Only `published` is served publicly.
- `review_status` / `validation_status` on medical content: `draft | in_review | approved | rejected | retired`.
- `is_sample = true` marks seeded development content that must never be published as clinical advice.
- Soft deletes on users, patients, doctors and medical catalog tables (so assessment history keeps its references).
- Translatable fields: base column = English; overrides in `translations`.

## Tables

### Authentication
| Table | Key columns |
|---|---|
| `users` | id, name, email (unique), password, phone, preferred_language, is_active, last_login_at, soft deletes |
| `roles` | id, name (unique: `patient`, `admin`), label |
| `user_roles` | user_id, role_id — PK(user_id, role_id) |
| `personal_access_tokens` | Sanctum |

### Patient
| Table | Key columns |
|---|---|
| `patients` | id, user_id (unique), first_name, last_name, phone, date_of_birth (**encrypted**), sex (`female/male/other/undisclosed`, nullable), country_id, city_id, soft deletes |
| `patient_preferences` | patient_id (unique), language_code, share_location, email_notifications |
| `patient_consents` | patient_id, consent_type (`terms/privacy/health_data`), version, granted_at, revoked_at, ip_address |

### Geography & languages
| Table | Key columns |
|---|---|
| `languages` | code (unique), name, native_name, direction (`ltr/rtl`), is_active |
| `countries` | iso2 (unique), name\*, phone_code, currency_code, timezone, default_language_code, emergency_number, emergency_number_verified, is_active |
| `cities` | country_id, name\*, latitude, longitude, timezone, is_active — unique(country_id, name) |

### Doctors
| Table | Key columns |
|---|---|
| `doctors` | first_name, last_name, title, slug (unique), photo_path, bio\*, qualifications\*, years_of_experience, email, phone, website, consultation_fee, fee_currency, verification_status (`pending/verified/rejected/suspended`), verified_at, verified_by, is_demo, status, soft deletes — index(status, verification_status) |
| `doctor_specialties` | doctor_id, specialty_id, is_primary — unique pair |
| `doctor_languages` | doctor_id, language_id — unique pair |
| `doctor_locations` | doctor_id, label, address, country_id, city_id, postal_code, latitude, longitude, is_primary — index(country_id, city_id), index(latitude, longitude) |
| `doctor_documents` | doctor_id, type, disk, path, original_name, mime_type, size, status, reviewed_by, reviewed_at, notes |

### Medical catalog
| Table | Key columns |
|---|---|
| `specialties` | code (unique), name\*, description\*, icon, status, sort_order |
| `body_parts` | code (unique), name\*, description\*, view (`front/back/both`), svg_region, parent_id, sort_order, status |
| `symptoms` | code (unique), name\*, description\*, status |
| `diseases` | code (unique), name\*, description\*, specialty_id, severity, status, review_status, source_name, source_url, is_sample |
| `disease_symptoms` | disease_id, symptom_id, weight |
| `disease_body_parts` | disease_id, body_part_id, weight |
| `medical_guidelines` | disease_id, specialty_id, title\*, content\*, guideline_type (`self_care/when_to_consult/prevention/emergency`), source_name, source_url, review_status, reviewed_by_name, reviewed_at, version, is_sample, status |

### Questionnaire & rules
| Table | Key columns |
|---|---|
| `symptom_questions` | code (unique), body_part_id (null = global), text\*, help_text\*, type (`single_choice/multi_choice/scale/number/boolean`), min_value, max_value, unit, is_required, sort_order, status |
| `question_options` | question_id, code, label\*, score, symptom_id, sort_order — unique(question_id, code) |
| `question_conditions` | question_id, depends_on_question_id, operator (`equals/not_equals/in/not_in/gte/lte/answered`), value (json) |
| `orientation_rules` | code (unique), name, description, body_part_id, question_id, operator, expected_value (json), conditions (json, extra AND clauses), priority, result_type (`emergency/disease/specialty/advice`), disease_id, specialty_id, guideline_id, emergency_severity (`critical/urgent`), weight, message\*, source_name, source_url, validation_status, version, is_sample |

### Assessments
| Table | Key columns |
|---|---|
| `symptom_assessments` | uuid (unique), patient_id, country_id, city_id, locale, sex, age_years, body_part_ids (json), status (`in_progress/completed`), engine_version, uses_unreviewed_content, evaluated_at |
| `assessment_answers` | assessment_id, question_id, option_codes (json), value — unique(assessment_id, question_id) |
| `assessment_results` | assessment_id, result_type (`condition/specialty/guideline`), disease_id, specialty_id, guideline_id, score, rank, matched_rule_ids (json) |
| `assessment_alerts` | assessment_id, rule_id, severity, message, emergency_number |

### Discovery & platform
| Table | Key columns |
|---|---|
| `favorites` | patient_id, doctor_id — unique pair |
| `audit_logs` | user_id, action, auditable_type, auditable_id, old_values, new_values, ip_address, user_agent, created_at |
| `notifications` | Laravel standard notifications table |
| `translations` | translatable_type, translatable_id, locale, field, value — unique(type, id, locale, field) |
| `system_settings` | key (unique), value (json), description |

\* translatable field.

### Data collection (phase 7)
| Table | Key columns |
|---|---|
| `knowledge_sources` | name, publisher, base_url, **license** (required), license_url, allowed_use, is_active |
| `source_documents` | knowledge_source_id, url (unique per source), title, language, published_at, retrieved_at, last_checked_at, content_hash (SHA-256), raw_content (normalised text, hidden), status (`pending/active/changed/unavailable`) |
| `assessment_outcomes` | assessment_id (unique), consulted, consulted_specialty_id, diagnosis_disease_id, diagnosis_icd11, went_to_emergency, helpfulness (1–5), source (`patient_reported/doctor_confirmed`), collected_at, reminder_sent_at |

New columns: `source_document_id` on `medical_guidelines`, `orientation_rules`, `diseases`; `summary_template`\* on `symptom_questions`; `questionnaire_version`, `ruleset_version` on `symptom_assessments`.

## Deferred tables
`appointments`, `appointment_status_history` — not created until the appointment workflow is decided (see roadmap).

## Relationships (summary)

```
users 1─1 patients 1─* patient_consents, 1─1 patient_preferences, 1─* favorites *─1 doctors
doctors *─* specialties (doctor_specialties) ; doctors *─* languages ; doctors 1─* doctor_locations *─1 cities *─1 countries
diseases *─1 specialties ; diseases *─* symptoms ; diseases *─* body_parts ; medical_guidelines *─1 diseases/specialties
symptom_questions 1─* question_options ; symptom_questions 1─* question_conditions
orientation_rules *─1 (body_parts, symptom_questions, diseases, specialties, medical_guidelines)
symptom_assessments 1─* assessment_answers / assessment_results / assessment_alerts
```
