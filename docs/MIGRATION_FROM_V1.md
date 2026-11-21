# Migration from V1 to V2

## V1 audit summary

### Structure
- `BACK/sahtek` — Laravel 10, PHP ≥ 8.1, Sanctum installed but unused.
- `front/sahtek` — Create React App (React 18), Redux + redux-thunk, React Router 6, Axios. Three screens. **Removed from the code base in the data pipeline phase 5; kept in the git tag `archive/front-v1`.**

### V1 data model
| V1 table | Meaning | V2 target |
|---|---|---|
| `parties_corp` (nom, description, photo) | Body part | `body_parts` |
| `genre` (nom, description) | Gender filter for diseases (id 4 = "both") | Removed as a table; becomes an optional `sex` condition on questions/rules |
| `specialite` (nom, description) | Specialty | `specialties` |
| `malade` (nom, description, id_parties_corps, id_genre, id_specialite) | Disease | `diseases` + `disease_body_parts` |
| `symptome` (description, id_malade) | Symptom attached to one disease | `symptoms` + `disease_symptoms` (many-to-many) |
| `consigne` (description, id_malade) | Advice | `medical_guidelines` (draft, `is_sample = false`, source "Imported from V1 — needs review") |
| `medcin` (nom_prenom, bio, ville, id_specialite) | Doctor | `doctors` + `doctor_specialties` + `doctor_locations` (city text matched to `cities`) |

### V1 API
`GET /api/afficher-partie_corp`, `/api/afficher-malade/{idp}/{idg}`, `/api/afficher-consigne/{idm}`, `/api/afficher-symptome/{idm}`, `/api/afficher-medcin/{ids}`.
The route definitions contain a **trailing space** in the URI (`'/afficher-malade/{idp}/{idg} '`); Laravel trims it, so they work, but it should not be copied.

### Problems found in V1
| # | Problem | Where |
|---|---|---|
| 1 | Hardcoded API URL `http://127.0.0.1:8000` in 4 places | `interface2.jsx`, `interface3.jsx`, `redux/thunk.jsx` |
| 2 | Debug `alert()` calls (`"fhjfhfhf"`, `alert(idp)`, `alert(genre)`, `alert(1)` inside a reducer) and many `console.log` | interfaces + `reducers.jsx` |
| 3 | Doctors are **not filtered by city** although the user picks one | `afficher_medcin` |
| 4 | Cities hardcoded in React (Casablanca, Rabat, Agadir); gender ids hardcoded (2, 3, 4) | `interface1.jsx`, `Controller.php` |
| 5 | `Medcin` model references a non-existent `Specialite` model; `ville` missing from `$fillable` | `app/Models/Medcin.php` |
| 6 | `Malade::consignes()` relation uses wrong keys (`id_consigne` instead of `id_malade`) | `app/Models/malade.php` |
| 7 | Model files with lowercase names (`malade.php`, `consigne.php`, `partie_corp.php`) — breaks PSR-4 autoload on case-sensitive filesystems (Linux servers) | `app/Models` |
| 8 | Missing `logo.png` referenced by the navbar | `front/sahtek/public` |
| 9 | Interface 3 crashes on page refresh (relies on router `location.state`) | `interface3.jsx` |
| 10 | Gender selected with two checkboxes (both can be checked); genre not required → `undefined` in API URL | `interface1.jsx` |
| 11 | No authentication, validation, pagination, i18n, tests or seed data | whole project |
| 12 | CORS open to `*` (acceptable for public read-only V1, not for V2 auth) | `config/cors.php` |
| 13 | Default `.env.example` DB name `gestionentreprise` unrelated to the project | `.env.example` |

### Reusable from V1
- The domain model idea (body part → disease → symptoms/advice → specialty → doctor) — kept and normalised.
- Laravel 10 skeleton, Sanctum, CORS config.
- Assets `background.png`, `undraw_medicine_b1ol.png` (illustration) — copied to the V2 frontend.
- The cyan / pink palette of V1 is replaced by the medical cyan identity requested for V2 (cyan stays the primary colour).

## Migration strategy
1. V2 tables are created **alongside** V1 tables (no V1 table is altered or dropped).
2. V1 API and V1 frontend continue to work unchanged.
3. Import legacy content into V2:
   ```bash
   php artisan docthub:import-v1 --country=MA
   ```
   - Idempotent (uses `code = v1-<entity>-<id>`).
   - Imported medical content is set to `draft` / `review_status = draft` — **it must be reviewed by a professional before publication.**
   - Imported doctors are `verification_status = pending`, `status = draft` (V1 had no verification).
   - V1 cities are matched by name to `cities` in the given country; unmatched cities are reported, not created.
4. Once V2 is live and the data reviewed, V1 routes/tables can be removed in a later release.
