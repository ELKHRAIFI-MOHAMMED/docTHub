/**
 * Admin resource definitions consumed by the generic ResourcePage.
 * The API remains the source of truth for validation; these only describe the forms and tables.
 */
export type FieldType = 'text' | 'textarea' | 'number' | 'select' | 'checkbox' | 'relation' | 'json';

export interface FieldDef {
  name: string;
  label: string;
  type: FieldType;
  options?: string[];            // select
  relation?: string;             // relation: admin resource to load options from
  relationLabel?: string;        // attribute used as label (default: name)
  translatable?: boolean;        // shows fr / ar inputs
  required?: boolean;
  hint?: string;
  fromRecord?: (r: Record<string, unknown>) => unknown; // value for the form when editing
}

export interface ResourceDef {
  key: string;                   // API path segment: /admin/<key>
  title: string;                 // i18n key
  columns: { name: string; label: string }[];
  fields: FieldDef[];
  statuses?: string[];           // PATCH /status values (publish/archive or review workflow)
  statusColumn?: string;
  actions?: { label: string; path: (id: number) => string }[]; // extra POST actions per row
}

const STATUS = ['draft', 'published', 'archived'];
const REVIEW = ['draft', 'in_review', 'approved', 'rejected', 'retired'];
const OPERATORS = ['equals', 'not_equals', 'in', 'not_in', 'gte', 'lte', 'answered', 'contains'];

export const RESOURCES: ResourceDef[] = [
  {
    key: 'specialties', title: 'admin.res.specialties', statuses: STATUS, statusColumn: 'status',
    columns: [{ name: 'code', label: 'Code' }, { name: 'name', label: 'Name' }, { name: 'status', label: 'Status' }],
    fields: [
      { name: 'code', label: 'Code', type: 'text', required: true, hint: 'lowercase-with-dashes' },
      { name: 'name', label: 'Name (EN)', type: 'text', required: true, translatable: true },
      { name: 'description', label: 'Description (EN)', type: 'textarea', translatable: true },
      { name: 'sort_order', label: 'Sort order', type: 'number' },
    ],
  },
  {
    key: 'body-parts', title: 'admin.res.bodyParts', statuses: STATUS, statusColumn: 'status',
    columns: [{ name: 'code', label: 'Code' }, { name: 'name', label: 'Name' }, { name: 'view', label: 'View' }, { name: 'status', label: 'Status' }],
    fields: [
      { name: 'code', label: 'Code', type: 'text', required: true },
      { name: 'name', label: 'Name (EN)', type: 'text', required: true, translatable: true },
      { name: 'description', label: 'Description (EN)', type: 'textarea', translatable: true },
      { name: 'view', label: 'View', type: 'select', options: ['front', 'back', 'both'], required: true },
      { name: 'svg_region', label: 'Body map region', type: 'text', hint: 'head, neck, chest, abdomen, pelvis, arm, leg, upper-back, lower-back' },
      { name: 'sort_order', label: 'Sort order', type: 'number' },
    ],
  },
  {
    key: 'symptoms', title: 'admin.res.symptoms', statuses: STATUS, statusColumn: 'status',
    columns: [{ name: 'code', label: 'Code' }, { name: 'name', label: 'Name' }, { name: 'status', label: 'Status' }],
    fields: [
      { name: 'code', label: 'Code', type: 'text', required: true },
      { name: 'name', label: 'Name (EN)', type: 'text', required: true, translatable: true },
      { name: 'description', label: 'Description (EN)', type: 'textarea', translatable: true },
    ],
  },
  {
    key: 'diseases', title: 'admin.res.diseases', statuses: STATUS, statusColumn: 'status',
    columns: [{ name: 'code', label: 'Code' }, { name: 'name', label: 'Name' }, { name: 'review_status', label: 'Review' }, { name: 'status', label: 'Status' }],
    fields: [
      { name: 'code', label: 'Code', type: 'text', required: true },
      { name: 'name', label: 'Name (EN)', type: 'text', required: true, translatable: true },
      { name: 'description', label: 'Description (EN)', type: 'textarea', translatable: true },
      { name: 'specialty_id', label: 'Recommended specialty', type: 'relation', relation: 'specialties' },
      { name: 'severity', label: 'Severity', type: 'select', options: ['low', 'moderate', 'high'] },
      { name: 'review_status', label: 'Review status', type: 'select', options: REVIEW },
      { name: 'source_name', label: 'Medical source', type: 'text', hint: 'Required for approval' },
      { name: 'source_url', label: 'Source URL', type: 'text' },
      { name: 'source_document_id', label: 'Source document', type: 'relation', relation: 'source-documents', relationLabel: 'title', hint: 'Traceability: changes to this document send the item back to review' },
      { name: 'symptoms', label: 'Symptoms', type: 'json', hint: '[{"id": 3, "weight": 2}]',
        fromRecord: (r) => ((r.symptoms as { id: number; pivot?: { weight: number } }[]) ?? []).map((s) => ({ id: s.id, weight: s.pivot?.weight ?? 1 })) },
      { name: 'body_parts', label: 'Body parts', type: 'json', hint: '[{"id": 1, "weight": 1}]',
        // Relation keys come from Eloquent relation names (camelCase).
        fromRecord: (r) => ((r.bodyParts ?? r.body_parts) as { id: number; pivot?: { weight: number } }[] ?? []).map((s) => ({ id: s.id, weight: s.pivot?.weight ?? 1 })) },
    ],
  },
  {
    key: 'guidelines', title: 'admin.res.guidelines', statuses: STATUS, statusColumn: 'status',
    columns: [{ name: 'title', label: 'Title' }, { name: 'guideline_type', label: 'Type' }, { name: 'review_status', label: 'Review' }, { name: 'version', label: 'v' }, { name: 'status', label: 'Status' }],
    fields: [
      { name: 'title', label: 'Title (EN)', type: 'text', required: true, translatable: true },
      { name: 'content', label: 'Content (EN)', type: 'textarea', required: true, translatable: true },
      { name: 'guideline_type', label: 'Type', type: 'select', options: ['self_care', 'when_to_consult', 'prevention', 'emergency'], required: true },
      { name: 'disease_id', label: 'Disease', type: 'relation', relation: 'diseases' },
      { name: 'specialty_id', label: 'Specialty', type: 'relation', relation: 'specialties' },
      { name: 'source_name', label: 'Medical source', type: 'text', required: true },
      { name: 'source_url', label: 'Source URL', type: 'text' },
      { name: 'source_document_id', label: 'Source document', type: 'relation', relation: 'source-documents', relationLabel: 'title', hint: 'Traceability: changes to this document send the item back to review' },
      { name: 'review_status', label: 'Review status', type: 'select', options: REVIEW },
      { name: 'reviewed_by_name', label: 'Reviewed by (required to approve)', type: 'text' },
    ],
  },
  {
    key: 'questions', title: 'admin.res.questions', statuses: STATUS, statusColumn: 'status',
    columns: [{ name: 'code', label: 'Code' }, { name: 'text', label: 'Question' }, { name: 'type', label: 'Type' }, { name: 'status', label: 'Status' }],
    fields: [
      { name: 'code', label: 'Code', type: 'text', required: true },
      { name: 'text', label: 'Question (EN)', type: 'textarea', required: true, translatable: true },
      { name: 'help_text', label: 'Help text (EN)', type: 'textarea', translatable: true },
      { name: 'summary_template', label: 'Summary sentence (EN)', type: 'text', translatable: true, hint: 'Used in the case summary. :answer = the patient answer, e.g. "Pain intensity: :answer out of 10."' },
      { name: 'type', label: 'Type', type: 'select', options: ['single_choice', 'multi_choice', 'scale', 'number', 'boolean'], required: true },
      { name: 'body_part_id', label: 'Body part (empty = all)', type: 'relation', relation: 'body-parts' },
      { name: 'min_value', label: 'Min value', type: 'number' },
      { name: 'max_value', label: 'Max value', type: 'number' },
      { name: 'unit', label: 'Unit', type: 'text' },
      { name: 'is_required', label: 'Required', type: 'checkbox' },
      { name: 'sort_order', label: 'Sort order', type: 'number' },
      { name: 'options', label: 'Options', type: 'json', hint: '[{"code":"yes","label":"Yes","symptom_id":null,"translations":{"fr":{"label":"Oui"}}}]',
        fromRecord: (r) => ((r.options as Record<string, unknown>[]) ?? []).map(({ code, label, score, symptom_id }) => ({ code, label, score, symptom_id })) },
      { name: 'conditions', label: 'Display conditions', type: 'json', hint: `[{"depends_on_question_id":3,"operator":"equals","value":"sudden"}] — operators: ${OPERATORS.join(', ')}`,
        fromRecord: (r) => ((r.conditions as Record<string, unknown>[]) ?? []).map(({ depends_on_question_id, operator, value }) => ({ depends_on_question_id, operator, value })) },
    ],
  },
  {
    key: 'rules', title: 'admin.res.rules', statuses: REVIEW, statusColumn: 'validation_status',
    columns: [{ name: 'code', label: 'Code' }, { name: 'name', label: 'Name' }, { name: 'result_type', label: 'Result' }, { name: 'priority', label: 'Priority' }, { name: 'version', label: 'v' }, { name: 'validation_status', label: 'Validation' }],
    fields: [
      { name: 'code', label: 'Code', type: 'text', required: true },
      { name: 'name', label: 'Name', type: 'text', required: true },
      { name: 'description', label: 'Description', type: 'textarea' },
      { name: 'result_type', label: 'Result type', type: 'select', options: ['emergency', 'disease', 'specialty', 'advice'], required: true },
      { name: 'priority', label: 'Priority (higher first)', type: 'number' },
      { name: 'body_part_id', label: 'Body part', type: 'relation', relation: 'body-parts' },
      { name: 'question_id', label: 'Question', type: 'relation', relation: 'questions', relationLabel: 'code' },
      { name: 'operator', label: 'Operator', type: 'select', options: OPERATORS },
      { name: 'expected_value', label: 'Expected answer', type: 'json', hint: '"yes"  or  ["a","b"]  or  7' },
      { name: 'conditions', label: 'Extra conditions (AND)', type: 'json', hint: '[{"question":"pain_intensity","operator":"gte","value":7}] or [{"field":"age","operator":"gte","value":65}]' },
      { name: 'disease_id', label: 'Disease (disease rules)', type: 'relation', relation: 'diseases' },
      { name: 'specialty_id', label: 'Specialty (specialty rules)', type: 'relation', relation: 'specialties' },
      { name: 'guideline_id', label: 'Guideline (advice rules)', type: 'relation', relation: 'guidelines', relationLabel: 'title' },
      { name: 'emergency_severity', label: 'Emergency severity', type: 'select', options: ['critical', 'urgent'] },
      { name: 'weight', label: 'Weight (1-10)', type: 'number' },
      { name: 'message', label: 'Emergency message (EN)', type: 'textarea', translatable: true },
      { name: 'source_name', label: 'Medical source', type: 'text', required: true },
      { name: 'source_url', label: 'Source URL', type: 'text' },
      { name: 'source_document_id', label: 'Source document', type: 'relation', relation: 'source-documents', relationLabel: 'title', hint: 'Traceability: changes to this document send the item back to review' },
    ],
  },
  {
    key: 'countries', title: 'admin.res.countries',
    columns: [{ name: 'iso2', label: 'ISO' }, { name: 'name', label: 'Name' }, { name: 'emergency_number', label: 'Emergency' }, { name: 'emergency_number_verified', label: 'Verified' }, { name: 'is_active', label: 'Active' }],
    fields: [
      { name: 'iso2', label: 'ISO2 code', type: 'text', required: true },
      { name: 'name', label: 'Name (EN)', type: 'text', required: true, translatable: true },
      { name: 'phone_code', label: 'Phone code', type: 'text', hint: '+212' },
      { name: 'currency_code', label: 'Currency', type: 'text', hint: 'MAD, EUR…' },
      { name: 'timezone', label: 'Timezone', type: 'text', hint: 'Africa/Casablanca' },
      { name: 'default_language_code', label: 'Default language', type: 'text' },
      { name: 'emergency_number', label: 'Emergency number', type: 'text' },
      { name: 'emergency_number_verified', label: 'Emergency number checked against an official source', type: 'checkbox' },
      { name: 'is_active', label: 'Active', type: 'checkbox' },
    ],
  },
  {
    key: 'cities', title: 'admin.res.cities',
    columns: [{ name: 'name', label: 'Name' }, { name: 'country_id', label: 'Country' }, { name: 'latitude', label: 'Lat' }, { name: 'longitude', label: 'Lng' }, { name: 'is_active', label: 'Active' }],
    fields: [
      { name: 'country_id', label: 'Country', type: 'relation', relation: 'countries', required: true },
      { name: 'name', label: 'Name (EN)', type: 'text', required: true, translatable: true },
      { name: 'latitude', label: 'Latitude', type: 'number' },
      { name: 'longitude', label: 'Longitude', type: 'number' },
      { name: 'timezone', label: 'Timezone', type: 'text' },
      { name: 'is_active', label: 'Active', type: 'checkbox' },
    ],
  },
  {
    key: 'languages', title: 'admin.res.languages',
    columns: [{ name: 'code', label: 'Code' }, { name: 'name', label: 'Name' }, { name: 'direction', label: 'Dir' }, { name: 'is_active', label: 'Active' }],
    fields: [
      { name: 'code', label: 'Code', type: 'text', required: true },
      { name: 'name', label: 'Name', type: 'text', required: true },
      { name: 'native_name', label: 'Native name', type: 'text', required: true },
      { name: 'direction', label: 'Direction', type: 'select', options: ['ltr', 'rtl'] },
      { name: 'is_active', label: 'Active', type: 'checkbox' },
    ],
  },
];

RESOURCES.push(
  {
    key: 'knowledge-sources', title: 'admin.res.knowledgeSources',
    columns: [{ name: 'name', label: 'Name' }, { name: 'publisher', label: 'Publisher' }, { name: 'license', label: 'Licence' }, { name: 'is_active', label: 'Active' }],
    fields: [
      { name: 'name', label: 'Name', type: 'text', required: true },
      { name: 'publisher', label: 'Publisher', type: 'text' },
      { name: 'base_url', label: 'Website', type: 'text' },
      { name: 'license', label: 'Licence (required before any reuse)', type: 'text', required: true },
      { name: 'license_url', label: 'Licence URL', type: 'text' },
      { name: 'allowed_use', label: 'What we are allowed to do', type: 'textarea' },
      { name: 'is_active', label: 'Monitor this source daily', type: 'checkbox' },
    ],
  },
  {
    key: 'source-documents', title: 'admin.res.sourceDocuments',
    columns: [{ name: 'title', label: 'Title' }, { name: 'status', label: 'Status' }, { name: 'last_checked_at', label: 'Last check' }, { name: 'content_hash', label: 'Fingerprint' }],
    actions: [{ label: 'admin.fetchNow', path: (id) => `/admin/source-documents/${id}/fetch` }],
    fields: [
      { name: 'knowledge_source_id', label: 'Source', type: 'relation', relation: 'knowledge-sources', required: true },
      { name: 'title', label: 'Title', type: 'text', required: true },
      { name: 'url', label: 'Address (URL)', type: 'text', required: true },
      { name: 'language', label: 'Language', type: 'text', hint: 'en, fr, ar…' },
      { name: 'published_at', label: 'Publication date', type: 'text', hint: 'YYYY-MM-DD' },
    ],
  },
);

export const TRANSLATION_LOCALES = ['fr', 'ar'];
