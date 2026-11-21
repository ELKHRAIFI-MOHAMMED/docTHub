// Types mirroring the Laravel API Resources (BACK/sahtek/app/Http/Resources).

export interface Paginated<T> {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; [k: string]: unknown };
  links?: Record<string, string | null>;
}

export interface Language { id: number; code: string; name: string; native_name: string; direction: 'ltr' | 'rtl' }
export interface Country {
  id: number; iso2: string; name: string; phone_code: string | null; currency_code: string | null;
  timezone: string | null; default_language_code: string | null;
  emergency_number: string | null; emergency_number_verified: boolean;
}
export interface City { id: number; country_id: number; name: string; latitude: number | null; longitude: number | null }
export interface Specialty { id: number; code: string; name: string; description: string | null; icon: string | null; is_primary?: boolean }
export interface BodyPart { id: number; code: string; name: string; description: string | null; view: 'front' | 'back' | 'both'; svg_region: string | null }
export interface Symptom { id: number; code: string; name: string }

export interface Disease {
  id: number; code: string; name: string; description: string | null; severity: 'low' | 'moderate' | 'high';
  specialty?: Specialty; symptoms?: Symptom[]; review_status: string; is_sample: boolean; source_name: string | null;
}

export interface Guideline {
  id: number; title: string; content: string; guideline_type: string; source_name: string | null; source_url: string | null;
  review_status: string; reviewed_at: string | null; version: number; is_sample: boolean;
}

export type QuestionType = 'single_choice' | 'multi_choice' | 'scale' | 'number' | 'boolean';
export interface Answer { options: string[]; value: string | null }
export interface Question {
  id: number; code: string; body_part_id: number | null; text: string; help_text: string | null; type: QuestionType;
  min_value: number | null; max_value: number | null; unit: string | null; is_required: boolean;
  options: { code: string; label: string }[]; answer: Answer | null;
}

export interface QuestionnaireState {
  assessment_id: string; status: 'in_progress' | 'completed'; questions: Question[];
  complete: boolean; next_question_id: number | null; emergency_detected: boolean;
}

export interface AssessmentSummary {
  id: string; status: string; locale: string; body_parts: BodyPart[]; has_emergency_alert?: boolean;
  recommended_specialty?: Specialty | null; follow_up_collected?: boolean; created_at: string; evaluated_at: string | null;
}

export interface OrientationResult {
  id: string; status: string; evaluated_at: string | null; engine_version: string | null;
  body_parts: BodyPart[]; summary: { question: string; answer: string }[];
  emergency: null | {
    severity: 'critical' | 'urgent'; messages: string[]; emergency_number: string | null;
    emergency_number_verified: boolean; country: string | null;
  };
  conditions: { disease: Disease; rank: number; match_strength: 'strong' | 'moderate' | 'weak' }[];
  specialties: Specialty[]; guidelines: Guideline[];
  uses_unreviewed_content: boolean; disclaimer: string; unreviewed_notice: string | null;
  case_summary: { paragraphs: string[]; text: string; generated_by: string };
  follow_up: { collected: boolean; available: boolean };
}

export interface Outcome {
  collected: boolean; consulted: boolean | null; consulted_specialty_id: number | null; diagnosis_disease_id: number | null;
  diagnosis_icd11: string | null; went_to_emergency: boolean | null; helpfulness: number | null; collected_at: string | null;
}

export interface DoctorLocation {
  id: number; label: string | null; address: string | null; postal_code: string | null;
  latitude: number | null; longitude: number | null; is_primary: boolean; city: City | null; country: Country | null;
}

export interface Doctor {
  id: number; slug: string; title: string | null; first_name: string; last_name: string; full_name: string;
  photo_url: string | null; years_of_experience: number | null; consultation_fee: number | null; fee_currency: string | null;
  is_verified: boolean; verification_status: string; is_demo: boolean;
  specialties: Specialty[]; languages: Language[]; city: City | null; country: Country | null;
  distance_km: number | null; is_favorite: boolean | null; bio_excerpt?: string;
  // detailed
  bio?: string | null; qualifications?: string | null; email?: string | null; phone?: string | null; website?: string | null;
  locations?: DoctorLocation[];
}

export interface Patient {
  id: number; first_name: string; last_name: string; phone: string | null; date_of_birth: string | null;
  sex: 'female' | 'male' | 'other' | 'undisclosed' | null; country?: Country | null; city?: City | null;
  preferences?: { language_code: string; share_location: boolean; email_notifications: boolean };
}

export interface User {
  id: number; name: string; email: string; phone: string | null; preferred_language: string;
  roles: ('patient' | 'admin')[]; patient?: Patient | null;
}

export interface ApiError { status: number | string; data?: { message?: string; errors?: Record<string, string[]> } }
