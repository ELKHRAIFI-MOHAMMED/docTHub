# Sources de connaissances médicales

Ce fichier recense chaque source externe utilisée par le pipeline (`knowledge:fetch`) : adresse, licence, conditions et date de vérification.

**Règle :** une source n'est téléchargée que lorsque son statut de licence vaut `confirmed` dans **Admin → Sources de connaissances** (`knowledge_sources.license_status`). Ce statut est posé **par une personne** après lecture de la licence, jamais par le code ni par un seeder. Le statut `unclear` bloque la source.

| Code | Source | Licence | Statut dans le seeder | Vérifié le |
|---|---|---|---|---|
| `icd11` | OMS, CIM-11 (ICD-11), linéarisation MMS, via l'API ICD | CC BY-ND 3.0 IGO + conditions d'utilisation ICD-11 | `unverified` | 2026-11-21 (lecture de la documentation) |
| `medlineplus` | NLM, MedlinePlus Health Topics (service web) | Domaine public pour les résumés des *health topics* ; contenus A.D.A.M. et ASHP exclus | `unverified` | 2026-11-21 (lecture de la documentation) |

« Vérifié le » est la date à laquelle la documentation officielle a été lue pour préparer ce fichier. Ce n'est **pas** une confirmation juridique : la confirmation revient au responsable du projet (voir « À confirmer »).

---

## 1. CIM-11 (OMS)

### Références officielles lues le 2026-11-21
- Documentation de l'API v2 : https://icd.who.int/docs/icd-api/APIDoc-Version2/
- Authentification : https://icd.who.int/docs/icd-api/API-Authentication/
- Versions et langues : https://icd.who.int/docs/icd-api/SupportedClassifications/
- Définition OpenAPI : https://id.who.int/swagger/v2/swagger.json (version `v2.6.0`, téléchargée et lue en entier)
- Licence : https://icd.who.int/docs/icd-api/license/ et https://icd.who.int/en/docs/icd11-license.pdf

### Ce que le code applique (vérifié dans ces documents, rien n'est deviné)

| Élément | Valeur | Où c'est écrit |
|---|---|---|
| Jeton | `POST https://icdaccessmanagement.who.int/connect/token`, `grant_type=client_credentials`, `scope=icdapi_access`, identifiant et secret en **authentification HTTP Basic** | page *API-Authentication* |
| Durée du jeton | « environ 1 heure » : mis en cache, renouvelé une fois en cas de 401 | page *API-Authentication* |
| En-têtes | `API-Version: v2` (« seulement le numéro majeur »), `Accept: application/json`, `Accept-Language: <code ISO 639-1>`. Les deux premiers sont **obligatoires** dans l'OpenAPI | *APIDoc-Version2*, OpenAPI |
| Liste des versions | `GET /icd/release/11/mms`, champ `latestRelease` | OpenAPI `GetReleasesForLinerization` |
| Code → entité | `GET /icd/release/11/{releaseId}/mms/codeinfo/{code}`, champ `stemId` | OpenAPI `GetCodeInfo` |
| Entité | `GET /icd/release/11/{releaseId}/mms/{id}` | OpenAPI `GetLinearizationEntity` |
| Langues | `fr` et `ar` disponibles pour la MMS (versions 2023-01 à 2026-01) | *SupportedClassifications* |
| Limite de débit | **non documentée** : intervalle prudent de 0,2 s entre deux appels (`ICD_API_MIN_INTERVAL`) et réessais sur 429 | aucune page lue n'en mentionne |

### Licence : points relevés
- La CIM-11 est publiée sous **CC BY-ND 3.0 IGO** (attribution, **pas d'œuvre dérivée**).
- Selon les conditions d'utilisation ICD-11 (PDF) :
  - L'intégration dans un logiciel n'est pas une adaptation si l'on conserve **le code, le titre et l'URI** de chaque entité (§ 1.2.2–1.2.3).
  - **Produire des traductions ou des correspondances (mappings) n'est pas couvert par la licence** et exige un accord écrit séparé de l'OMS (§ 1.2.4). Conséquence : on n'utilise que les langues publiées par l'OMS, et on ne traduit jamais un libellé CIM-11 nous-mêmes.
  - Des champs ajoutés sont permis s'ils sont **clairement identifiés comme ne venant pas de l'OMS** (§ 1.2.5).
  - Citation imposée (§ 1.3) : « International Classification of Diseases, Eleventh Revision (ICD-11), World Health Organization (WHO) 2019 ».

### À confirmer avant de passer à `confirmed`
1. **La section 4 du PDF de licence** (« autres conditions ») et **les clauses d'usage commercial** n'ont pas pu être lues : l'outil de lecture PDF n'est pas disponible ici.
2. **Les conditions acceptées à la création des identifiants de l'API** sur https://icd.who.int/icdapi.
3. Le rapprochement CIM-11 ↔ symptômes / spécialités de l'application est-il une « correspondance » au sens du § 1.2.4 ? **Décision du 2026-11-21 : l'import ne reprend que le code, le titre et l'URI, sans aucun lien créé automatiquement.**

---

## 2. MedlinePlus (NLM)

### Références officielles lues le 2026-11-21
- Service web : https://medlineplus.gov/about/developers/webservices/
- Fichiers XML : https://medlineplus.gov/xml.html
- Réutilisation du contenu : https://medlineplus.gov/about/using/usingcontent/ (mise à jour du 26 octobre 2022)

### Ce que le code applique

| Élément | Valeur | Source |
|---|---|---|
| Adresse | `https://wsearch.nlm.nih.gov/ws/query` (HTTPS demandé) | page *webservices* |
| Paramètres | `db=healthTopics` (anglais), `term`, `rettype=topic` (fiches complètes), `retmax`, `tool=docthub`. **Pas d'`email`**, pour ne transmettre aucune donnée personnelle | page *webservices* |
| Réponse | XML, racine `nlmSearchResult` : contrôlée, puis stockée telle quelle | page *webservices* |
| Limite de débit | « 85 requêtes par minute et par IP au maximum » : on en fait 80 (`MEDLINEPLUS_REQUESTS_PER_MINUTE`) | page *webservices* |
| Mise en cache conseillée | 12 à 24 h : relancer au plus une fois par jour | page *webservices* |
| Langues | **anglais et espagnol seulement** : `fr` et `ar` sont ignorés (« skipped »), jamais traduits | pages *webservices* et *xml* |

### Licence : points relevés
- « Summaries on health topic pages » figure parmi les contenus du **domaine public** (œuvre du gouvernement fédéral américain).
- **Sous droits d'auteur, interdits sans autorisation écrite :**
  - l'encyclopédie médicale A.D.A.M. (articles et vidéos) ;
  - les monographies de médicaments de l'ASHP ;
  - la plupart des images.
- Interdiction d'intégrer ou de mettre sous sa marque le **contenu sous droits** dans un dossier patient informatisé, un portail patient ou tout autre système informatique de santé.
- **Attribution :** « Source: MedlinePlus, National Library of Medicine ». Ne pas utiliser le logo MedlinePlus ni suggérer un soutien de la NLM.

### À confirmer avant de passer à `confirmed`
1. La page sur la réutilisation ne dit **pas explicitement** que les résumés renvoyés par le service web ou les fichiers XML sont les mêmes résumés du domaine public. C'est très probable, mais pas écrit.
2. Les fiches peuvent contenir des **liens** vers des pages A.D.A.M. : la phase 2 ne devra reprendre que le titre, les synonymes et le résumé de la fiche, et **jamais** le contenu des pages liées.

---

## 3. Relancer un téléchargement

```bash
cd BACK/sahtek
# 1. Enregistrer les sources (une fois ; ne modifie jamais une source existante)
php artisan db:seed --class=KnowledgeSourceSeeder
# 2. Une personne lit ce fichier et les licences, puis passe le statut à "confirmed" dans Admin → Sources de connaissances
# 3. Télécharger (CIM-11 : identifiants dans .env)
php artisan knowledge:fetch icd11 --target=<code CIM-11> --target=entity:<id> --lang=en --lang=fr --lang=ar
php artisan knowledge:fetch medlineplus --target="<terme de recherche>"
```

Résultat pour chaque couple (cible, langue) :

| Résultat | Signification |
|---|---|
| `new` | première version |
| `unchanged` | même empreinte : seule la date de contrôle change |
| `changed` | nouvelle version : l'ancienne passe `superseded`, l'événement « Source modifiée » est journalisé, les éléments `approved` liés repassent `in_review` |
| `skipped` | langue non proposée par la source |
| `FAILED` | échec après les réessais ; code de sortie 1 |

Les fichiers bruts sont écrits dans `storage/app/raw/{source}/{sha256}.{json|xml}`. Ils sont exclus de git et ne sont jamais modifiés.

## 4. Importer dans le catalogue (`knowledge:import`)

```bash
# Aperçu sans rien écrire
php artisan knowledge:import icd11 --as=disease --dry-run
# CIM-11 : code, titre et URI seulement ; vous choisissez le type (disease = affection, symptom = symptôme)
php artisan knowledge:import icd11 --as=disease --only=<code>
# MedlinePlus : lister les fiches trouvées, puis importer celles que vous choisissez
php artisan knowledge:import medlineplus
php artisan knowledge:import medlineplus --as=symptom --only=<topic id>
```

| Règle | Effet |
|---|---|
| Type | **Jamais déduit** : `--as` est obligatoire. La plage de codes du chapitre 21 de la CIM-11 (symptômes) n'a pas pu être vérifiée sur une page officielle ; une recherche MedlinePlus mélange des fiches de plusieurs types. |
| Statut | `draft`, relecture `draft`, `version = 1`, `is_sample = false`, `source_document_id` = la version brute utilisée |
| Contenu | CIM-11 : code, titre, URI (`external_uri`), sans définition. MedlinePlus : titre, synonymes *also called*, résumé sans HTML. Les pages liées (A.D.A.M., sites externes) ne sont jamais lues |
| Rien d'inventé | aucune gravité (`severity` NULL), aucune spécialité, aucune zone du corps, aucun lien symptôme ↔ affection, aucune règle |
| Langues | seules les langues fournies par la source sont enregistrées ; les autres sont listées dans `needs_translation`, sans traduction automatique |
| Code interne | `icd11_<code>` ou `mplus_<id>`, en snake_case minuscule ; le libellé n'est qu'une traduction |
| Idempotence | relancer l'import ne crée aucun doublon (`unchanged`) |
| Élément déjà relu | un élément qui n'est plus brouillon n'est **jamais écrasé** : l'écart est signalé (`conflict`) |
| Synonymes | table `knowledge_aliases`, dédoublonnés par langue ; un synonyme déjà rattaché à un autre élément est signalé et non ajouté |
| Doublons entre sources | même nom sous deux codes (CIM-11 et MedlinePlus) : signalé, **jamais fusionné** (fusionner serait une correspondance) |

Spécialités et recommandations : aucune des deux sources n'en fournit sous une forme réutilisable telle quelle ; elles restent saisies et relues dans l'admin.
