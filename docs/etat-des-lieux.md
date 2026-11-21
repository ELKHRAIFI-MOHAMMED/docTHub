# docTHub — État des lieux (phase 0 du pipeline de données)

Date : 2026-11-21 · Branche : `master` (aucune modification de code en phase 0)

> Ce document décrit le code **tel qu'il est**. Il compare l'existant avec la demande « pipeline de données V2 » et signale les écarts à trancher avant la phase 1.

## 1. Vue d'ensemble

Le dépôt contient **deux applications front-end** et **une API** :

| Dossier | Rôle | Stack | État |
|---|---|---|---|
| `BACK/sahtek` | API V1 (`/api/afficher-*`) + API V2 (`/api/v1`) | Laravel 10, PHP 8.2, Sanctum, MariaDB 10.11 (XAMPP) | V2 active ; routes V1 conservées en lecture seule |
| `front/sahtek` | **Front V1 historique** | CRA (react-scripts 5), React 18, Redux + redux-thunk, axios, JavaScript | Non maintenu, remplacé par `frontend/` |
| `frontend` | **Front V2** | Vite 7, React 19, TypeScript 5.9, Redux Toolkit + RTK Query, React Router 7, Tailwind 4, i18next | Actif |

⚠️ Une grande partie du travail V2 n'est **pas commitée** (78 entrées dans `git status`, un seul commit `82e9a94`). Avant de créer `feature/data-pipeline-v2`, il faut décider quoi faire de ce travail (voir §9).

## 2. Base de données

Les migrations se trouvent dans `BACK/sahtek/database/migrations`.

### 2.1 Tables V1 (lecture seule, utilisées par `docthub:import-v1`)
`genre`, `parties_corp`, `specialite`, `malade`, `symptome`, `consigne`, `medcin`

### 2.2 Tables V2

| Domaine | Tables |
|---|---|
| Comptes | `users`, `roles`, `user_roles`, `personal_access_tokens` |
| Plateforme | `languages`, `countries`, `cities`, `translations`, `system_settings`, `audit_logs`, `notifications` |
| Catalogue médical | `specialties`, `body_parts`, `symptoms`, `diseases`, `disease_symptoms`, `disease_body_parts`, `medical_guidelines` |
| Questionnaire et règles | `symptom_questions`, `question_options`, `question_conditions`, `orientation_rules` |
| Patients et médecins | `patients`, `patient_preferences`, `patient_consents`, `doctors`, `doctor_specialties`, `doctor_languages`, `doctor_locations`, `doctor_documents`, `favorites` |
| Évaluations | `symptom_assessments`, `assessment_answers`, `assessment_results`, `assessment_alerts`, `assessment_outcomes` |
| Collecte (phase 7 précédente) | `knowledge_sources`, `source_documents` |

### 2.3 Champs de traduction
Le **libellé de base est en anglais** dans la colonne. Les traductions FR/AR sont stockées dans la table polymorphe `translations` (`translatable_type, translatable_id, locale, field, value`). En l'absence de traduction, l'anglais est utilisé.

| Modèle | Champs traduisibles |
|---|---|
| Specialty, BodyPart, Symptom, Disease | `name`, `description` |
| MedicalGuideline | `title`, `content` |
| SymptomQuestion | `text`, `help_text`, `summary_template` |
| QuestionOption | `label` |
| OrientationRule | `message` |
| Country, City | `name` |
| Doctor | `bio`, `qualifications` |

Il n'existe **aucun marqueur `needs_translation`**. Une traduction absente est simplement une ligne qui n'existe pas.

### 2.4 Statuts, version, source, relecteur

| Table | Publication | Relecture | `version` | Source | Relecteur | `is_sample` |
|---|---|---|---|---|---|---|
| specialties, body_parts, symptoms | `status` draft/published/archived | — | — | — | — | — |
| diseases | `status` | `review_status` draft/in_review/approved/rejected/retired | **absente** | `source_name`, `source_url`, `source_document_id` | **absent** | oui |
| medical_guidelines | `status` | `review_status` | oui | `source_name`, `source_url`, `source_document_id` | `reviewed_by_name`, `reviewed_at` | oui |
| orientation_rules | — | `validation_status` (mêmes valeurs) | oui | `source_name`, `source_url`, `source_document_id` | **absent** | oui |
| symptom_questions | `status` | — | — | — | — | — |

Autres valeurs contrôlées :
- `diseases.severity` vaut `low | moderate | high`, ce qui correspond à faible / moyenne / élevée.
- `orientation_rules.priority` est un `integer` **sans borne**.
- `orientation_rules.result_type` vaut `emergency | disease | specialty | advice`.
- `emergency_severity` vaut `critical | urgent`.

Ce qui manque par rapport à la demande :
- **Codes externes :** il n'y a ni `external_system` ni `external_code` (CIM-11, HPO). La table de jointure `disease_symptoms` porte seulement un `weight`.
- **Synonymes :** il n'y a pas de table d'alias.

### 2.5 Traçabilité déjà présente
Elle a été construite lors de la phase « collecte de données » précédente.
- **`knowledge_sources`** : name, publisher, base_url, **license (obligatoire)**, license_url, allowed_use, is_active.
- **`source_documents`** :
  - colonnes : knowledge_source_id, url, title, language, published_at, retrieved_at, last_checked_at, `content_hash` (SHA-256 du texte normalisé), `raw_content` (texte normalisé, **stocké en base, pas sur disque**), status pending/active/changed/unavailable ;
  - unicité : **unique(knowledge_source_id, url)**, et non unique(url, sha).
  - Il n'y a donc **pas d'historique des versions brutes** : une nouvelle empreinte écrase l'ancienne.
- **`symptom_assessments`** : `engine_version`, `questionnaire_version`, `ruleset_version` (empreintes), `uses_unreviewed_content`. Il n'y a **pas de `knowledge_version`**.
- **`audit_logs`** : user_id, action, auditable_type/id, `old_values`, `new_values`, ip, user_agent, created_at. Ils sont alimentés par `AuditLogger` dans le CRUD admin, l'admin médecins et patients, l'authentification, le compte patient, l'export analytique et `SourceFetcher`.

## 3. Moteur et services (`app/Services`)

| Service | Rôle |
|---|---|
| `Orientation/OrientationEngine` | Ordre d'application : 1) règles d'urgence (prioritaires : elles arrêtent tout et vident les résultats) ; 2) score des affections (zone ×1, symptôme ×2 en renfort seulement, règle ×3 ; ≥6 fort, ≥3 moyen) ; 3) spécialités ; 4) conseils |
| `Orientation/RuleEvaluator`, `AnswerContext`, `QuestionFlow` | Évaluation des opérateurs et des conditions d'affichage des questions |
| `Orientation/ContentGate` | Le contenu non approuvé n'est utilisé que si `DOCTHUB_ALLOW_UNREVIEWED_CONTENT=true`. **Exception : les règles d'urgence restent actives tant qu'elles ne sont pas `rejected` ou `retired`** |
| `Orientation/ContentVersion` | Empreintes du questionnaire et du jeu de règles |
| `Orientation/CaseSummaryBuilder` | Résumé du cas à partir des modèles de phrase traduits (voie A, sans IA) |
| `Knowledge/SourceFetcher` | Téléchargement d'un document (http(s) public, protection SSRF, redirections revérifiées, `Http::timeout(20)`), calcul du SHA-256 ; si un document approuvé change, les éléments liés repassent `in_review`. **Il n'y a ni réessais, ni attente progressive, ni limitation de débit, ni client OAuth** |
| `Analytics/OutcomeAnalytics` | Concordance, taux d'urgence, taux de réponse, couverture du contenu. Export pseudonymisé JSONL |
| `Ai/*` | Interface `MedicalAssistant` et implémentation `NullMedicalAssistant` : **aucune IA active** |
| `Discovery/DoctorSearch` | Recherche de médecins, distance de Haversine |

Commandes Artisan :
- `docthub:import-v1` ;
- `docthub:sources:check` (03:00) ;
- `docthub:send-follow-ups` (10:00).

Il n'y a **ni `knowledge:fetch`, ni `knowledge:import`, ni `knowledge:validate`, ni `knowledge:export`**.

## 4. Seeders et contenu médical
- **`DatabaseSeeder`** : en production, il ne charge que les données de référence (langues, pays, villes, rôles).
- **`MedicalCatalogSeeder` et `QuestionnaireSeeder`** : contenu d'**exemple non validé** (`is_sample = true`, statut `draft`, source « SAMPLE »).
  - 12 spécialités, 9 zones, 23 symptômes, 17 affections, 8 conseils ;
  - 17 questions, 27 règles, dont **8 règles d'urgence, toutes non relues**.
- **`DemoAccountsSeeder`** : 1 compte admin et 1 compte patient de démonstration, 56 médecins fictifs (`is_demo`) et 1 médecin en attente.
- **Numéros d'urgence** : ils sont seedés avec `emergency_number_verified = false`.

## 5. API (`routes/api_v1.php`, 51 routes déclarées, et `routes/api.php` pour la V1)
La référence complète est [V2_API.md](V2_API.md). En résumé :
- **Authentification :** `/auth/register|login|logout|me`.
- **Référentiel :** `/languages`, `/countries`, `/cities`, `/body-parts`, `/specialties`, `/symptoms`, `/diseases`.
- **Évaluations :** `POST /assessments`, `GET …/questions`, `POST …/answers`, `POST …/evaluate`, `GET …/results`, `GET|PUT …/outcome`.
- **Médecins :** `/doctors` (localisation obligatoire), `/doctors/nearby`, `/doctors/{id|slug}`, `/doctors/countries|cities`.
- **Patient :** profil, historique, favoris, export, suppression du compte.
- **Admin :**
  - CRUD générique : spécialités, zones, symptômes, affections, conseils, questions, règles, pays, villes, langues, médecins, sources, documents ;
  - `PATCH …/status`, vérification des médecins, documents, patients, tableau de bord, journal d'audit ;
  - `analytics`, `analytics/export`, `source-documents/{id}/fetch`.
- **Legacy V1 :** `/api/afficher-partie_corp`, `afficher-malade/{idp}/{idg}`, `afficher-consigne/{idm}`, `afficher-symptome/{idm}`, `afficher-medcin/{ids}`.

## 6. Écrans

### 6.1 Front V2 (`frontend/src/features`)

| Public / patient | Admin |
|---|---|
| Accueil, Vérificateur (schéma du corps), Questionnaire, Résultats (avec résumé et formulaire de suivi), Médecins, Fiche médecin, Favoris, Historique, Profil, Connexion, Inscription | Tableau de bord, Analyse, Ressources (CRUD générique), Médecins, Patients |

État par rapport à la phase 5 demandée :

| Exigence | État actuel |
|---|---|
| Aucun `alert()` / `console.log` | ✅ Un seul `console.error` volontaire dans `app/api.ts`, quand `VITE_API_BASE_URL` manque |
| Client centralisé, URL en variable d'environnement | ✅ RTK Query (`app/api.ts`), variable **`VITE_API_BASE_URL`** (pas d'axios). Aucune URL en dur dans le code ; seul `.env.example` contient `127.0.0.1:8000` |
| Chargement / erreur / vide | 🟡 Spinner (pas de skeleton), message d'erreur, état vide sur les listes. **Pas de bouton « Réessayer »** |
| RTL arabe et sélecteur de langue mémorisé | ✅ `dir="rtl"`, langue en `localStorage`. Les propriétés CSS logiques sont à vérifier écran par écran |
| Écran d'urgence | 🟡 Bloc rouge `role="alert"` avec lien `tel:`, mais **pas en plein écran** |
| Bandeau « orientation, pas diagnostic » et « contenu non relu », sources | ✅ Disclaimer, bandeau des contenus d'exemple, source par conseil. 🟡 Les sources ne sont **pas repliables** |
| Médecins sponsorisés | Le concept n'existe pas (aucune colonne) : rien à séparer |
| Schéma du corps au clavier, aria-label, liste alternative | ✅ Testés dans `BodyMap.test.tsx` |
| Questionnaire : progression, retour, sauvegarde | ✅ Barre de progression et bouton Retour. Les réponses sont enregistrées côté serveur. 🟡 Une évaluation interrompue ne peut être reprise que via l'URL : aucun lien « reprendre » |
| Filtre par ville branché sur l'API | ✅ `city_id` envoyé à `/doctors` |
| Mobile 360 px, cibles de 44 px, contraste AA | 🟡 Testé visuellement en e2e, **non audité** |

### 6.2 Front V1 (`front/sahtek/src`), dette technique
- `alert()` : `interface1.jsx:22`, `interface2.jsx:22-23`, `redux/reducers.jsx:26`.
- `console.log` / `console.error` : `interface1.jsx:28-29`, `interface2.jsx:17,29,41-43`, `interface3.jsx:14,19,23`, `redux/thunk.jsx:12`.
- **URL en dur `http://127.0.0.1:8000`** : `interface2.jsx:15,25`, `interface3.jsx:17,21`, `redux/thunk.jsx:8`.
- Pas d'i18n, pas d'état d'erreur, pas de tests utiles (le `App.test.js` est celui de CRA).

### 6.3 Back-end, petite dette
- `config/sanctum.php` : la liste stateful par défaut contient `localhost` et `127.0.0.1:8000`. C'est le comportement standard de Laravel, surchargeable par `SANCTUM_STATEFUL_DOMAINS`.
- `config/cors.php` : les origines par défaut de développement (`localhost:5173/3000`) sont surchargeables par `CORS_ALLOWED_ORIGINS`.
- Aucun `dd()` / `dump()` dans `app/`.
- **Pas de CI** : le dossier `.github/` est absent.

## 7. Tests existants

| Suite | Fichiers | Résultat au 2026-11-21 |
|---|---|---|
| Serveur (PHPUnit, base `docthub_test`) | Feature : Admin, AssessmentFlow, Auth, Catalog, DataCollection, DoctorDiscovery, PatientAccount · Unit : RuleEvaluator | **65/65 réussis** (393 assertions, 216 s) |
| Interface (Vitest + Testing Library) | BodyMap, QuestionField, i18n (parité des clés + RTL), ResultsPage, DoctorsPage, auth, followup | **26/26 réussis** (7 fichiers) |
| Front V1 | `App.test.js` (modèle CRA) | non lancé, hors périmètre |

Il n'y a pas de Pest : le projet utilise PHPUnit 10.

## 8. Écarts entre la demande et l'existant

| Demande | Existant | Proposition |
|---|---|---|
| « React + Redux, `api.js` axios, `VITE_API_URL` ou `REACT_APP_API_URL` » | Front V2 en TypeScript avec RTK Query et `VITE_API_BASE_URL` ; le front V1 est l'ancien CRA | Faire la phase 5 sur `frontend/`, sans réécrire en axios. Supprimer ou archiver `front/sahtek` |
| `source_documents(source_name, url, licence, fetched_at, content_sha256, raw_path, unique(url, sha))` | Table existante : source et licence dans `knowledge_sources`, brut en base, unique(source, url) | **Étendre** la table existante : ajouter `content_sha256` ou réutiliser `content_hash`, ajouter `raw_path`, et passer à un historique (une ligne par empreinte). Ne pas créer une seconde table du même nom |
| Interface `SourceFetcher` | Classe concrète `Knowledge\SourceFetcher` (HTML générique) | La renommer en `GenericPageFetcher` derrière une interface commune, puis ajouter `IcdFetcher` et `MedlinePlusFetcher` |
| Gravité {faible, moyenne, élevée} | `low / moderate / high` | Correspondance directe, sans changer le schéma |
| « Élément approved : relecteur et version » | `diseases` sans version ni relecteur ; `orientation_rules` sans relecteur | Migration en phase 2 ou 3 : `reviewed_by_name`, `reviewed_at`, `version` là où ils manquent |
| « Règle d'urgence sans relecteur → échec » | Les 8 règles d'urgence d'exemple n'ont pas de relecteur | `knowledge:validate` **échouera sur l'état actuel**. C'est le résultat attendu et honnête. Il faut décider si la CI doit être bloquante dès maintenant |
| « Affection sans zone, symptôme ou spécialité → échec » | La CIM-11 ne fournit **ni zone, ni symptômes, ni spécialité** | Appliquer ce contrôle aux seuls éléments `approved` ou `published`, sinon tout import CIM-11 sera en échec |
| « Ne pas écrire de règle d'orientation » | Le moteur a besoin de règles et de liens affection↔symptôme | Les éléments importés resteront inertes (non reliés) jusqu'au travail du médecin relecteur. C'est voulu, mais l'orientation ne s'améliorera pas grâce à l'import seul |

## 9. Points bloquants avant la phase 1
1. **Travail V2 non commité.** Faut-il commiter d'abord l'état actuel sur `master`, ou sur une branche de base, avant de créer `feature/data-pipeline-v2` ? Sinon, le premier commit de phase mélangera tout.
2. **Licences, à vérifier dans la documentation officielle avant tout import :**
   - **CIM-11 (OMS)** : l'API exige un compte (client_id et client_secret). Les conditions d'utilisation et la licence (CC BY-ND 3.0 IGO à confirmer) doivent être lues. Une clause « pas de modification » pourrait limiter la normalisation ou les traductions ajoutées.
   - **MedlinePlus (NLM)** : une partie du contenu est du domaine public, mais certaines sections sont sous licence tierce (encyclopédie A.D.A.M., fiches médicaments). Il faut identifier précisément le jeu (ex. export XML des *health topics*) et ses conditions.
   - Ces deux points seront consignés dans `docs/sources.md`. **Rien ne sera importé tant qu'ils ne sont pas confirmés.**
3. **Identifiants CIM-11** : ils sont à créer par vous sur le portail de l'OMS. Ils iront uniquement dans `.env`.
