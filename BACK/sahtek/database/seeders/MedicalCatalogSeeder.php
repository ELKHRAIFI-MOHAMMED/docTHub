<?php

namespace Database\Seeders;

use App\Models\BodyPart;
use App\Models\Disease;
use App\Models\MedicalGuideline;
use App\Models\Specialty;
use App\Models\Symptom;
use Illuminate\Database\Seeder;

/**
 * DEVELOPMENT SAMPLE medical catalog.
 *
 * Specialties, body parts and symptoms are neutral terminology (published).
 * Diseases and guidelines are SAMPLE content: review_status = draft, is_sample = true.
 * They are only used by the orientation engine when DOCTHUB_ALLOW_UNREVIEWED_CONTENT=true
 * and must be reviewed by a qualified professional before any real use.
 */
class MedicalCatalogSeeder extends Seeder
{
    public const SAMPLE_SOURCE = 'DEV SAMPLE — not clinically validated; requires review by a qualified health professional';

    public function run(): void
    {
        $specialties = [
            'general-practice' => ['General practice', 'Médecine générale', 'الطب العام'],
            'cardiology' => ['Cardiology', 'Cardiologie', 'أمراض القلب'],
            'neurology' => ['Neurology', 'Neurologie', 'طب الأعصاب'],
            'gastroenterology' => ['Gastroenterology', 'Gastro-entérologie', 'أمراض الجهاز الهضمي'],
            'pulmonology' => ['Pulmonology', 'Pneumologie', 'أمراض الرئة'],
            'orthopedics' => ['Orthopedics', 'Orthopédie', 'جراحة العظام'],
            'rheumatology' => ['Rheumatology', 'Rhumatologie', 'أمراض الروماتيزم'],
            'ent' => ['Ear, nose and throat (ENT)', 'ORL', 'الأنف والأذن والحنجرة'],
            'urology' => ['Urology', 'Urologie', 'المسالك البولية'],
            'general-surgery' => ['General surgery', 'Chirurgie générale', 'الجراحة العامة'],
            'dermatology' => ['Dermatology', 'Dermatologie', 'الأمراض الجلدية'],
            'gynecology' => ['Gynecology', 'Gynécologie', 'أمراض النساء'],
        ];
        $i = 0;
        foreach ($specialties as $code => [$en, $fr, $ar]) {
            Specialty::updateOrCreate(['code' => $code], ['name' => $en, 'status' => 'published', 'sort_order' => $i++])
                ->syncTranslations(['fr' => ['name' => $fr], 'ar' => ['name' => $ar]]);
        }

        // svg_region must match a region drawn by the frontend BodyMap component.
        $bodyParts = [
            'head' => ['Head', 'Tête', 'الرأس', 'both'],
            'neck' => ['Neck and throat', 'Cou et gorge', 'الرقبة والحلق', 'front'],
            'chest' => ['Chest', 'Poitrine', 'الصدر', 'front'],
            'abdomen' => ['Abdomen', 'Abdomen', 'البطن', 'front'],
            'pelvis' => ['Pelvis and lower belly', 'Bassin et bas-ventre', 'الحوض وأسفل البطن', 'front'],
            'arm' => ['Arm and shoulder', 'Bras et épaule', 'الذراع والكتف', 'both'],
            'leg' => ['Leg and knee', 'Jambe et genou', 'الساق والركبة', 'both'],
            'upper-back' => ['Upper back', 'Haut du dos', 'أعلى الظهر', 'back'],
            'lower-back' => ['Lower back', 'Bas du dos', 'أسفل الظهر', 'back'],
        ];
        $i = 0;
        foreach ($bodyParts as $code => [$en, $fr, $ar, $view]) {
            BodyPart::updateOrCreate(['code' => $code], ['name' => $en, 'view' => $view, 'svg_region' => $code, 'status' => 'published', 'sort_order' => $i++])
                ->syncTranslations(['fr' => ['name' => $fr], 'ar' => ['name' => $ar]]);
        }

        $symptoms = [
            'fever' => ['Fever', 'Fièvre', 'حمّى'],
            'nausea' => ['Nausea or vomiting', 'Nausées ou vomissements', 'غثيان أو قيء'],
            'diarrhea' => ['Diarrhoea', 'Diarrhée', 'إسهال'],
            'shortness-of-breath' => ['Shortness of breath', 'Essoufflement', 'ضيق في التنفس'],
            'cough' => ['Cough', 'Toux', 'سعال'],
            'sweating' => ['Cold sweats', 'Sueurs froides', 'تعرّق بارد'],
            'dizziness' => ['Dizziness', 'Vertiges', 'دوخة'],
            'light-sensitivity' => ['Sensitivity to light', 'Sensibilité à la lumière', 'حساسية للضوء'],
            'stiff-neck' => ['Stiff neck', 'Raideur de la nuque', 'تيبّس الرقبة'],
            'one-sided-weakness' => ['Weakness on one side of the body', "Faiblesse d'un côté du corps", 'ضعف في جانب واحد من الجسم'],
            'speech-difficulty' => ['Difficulty speaking', 'Difficulté à parler', 'صعوبة في الكلام'],
            'confusion' => ['Confusion', 'Confusion', 'ارتباك'],
            'radiating-arm-jaw' => ['Pain spreading to the arm or jaw', 'Douleur irradiant vers le bras ou la mâchoire', 'ألم يمتد إلى الذراع أو الفك'],
            'palpitations' => ['Palpitations', 'Palpitations', 'خفقان'],
            'blood-in-stool' => ['Blood in stool', 'Sang dans les selles', 'دم في البراز'],
            'burning-urination' => ['Burning when urinating', 'Brûlures en urinant', 'حرقة أثناء التبول'],
            'pain-down-leg' => ['Pain radiating down the leg', 'Douleur descendant dans la jambe', 'ألم يمتد إلى الساق'],
            'saddle-numbness' => ['Numbness between the legs / buttocks', "Engourdissement entre les jambes / fesses", 'خدر بين الساقين أو في الأرداف'],
            'bladder-control-loss' => ['Loss of bladder or bowel control', 'Perte de contrôle de la vessie ou des intestins', 'فقدان التحكم في المثانة أو الأمعاء'],
            'sore-throat' => ['Sore throat', 'Mal de gorge', 'التهاب الحلق'],
            'runny-nose' => ['Blocked or runny nose', 'Nez bouché ou qui coule', 'انسداد أو سيلان الأنف'],
            'leg-swelling' => ['Swelling of one leg', "Gonflement d'une jambe", 'تورّم إحدى الساقين'],
            'redness-warmth' => ['Redness and warmth', 'Rougeur et chaleur', 'احمرار وسخونة'],
        ];
        foreach ($symptoms as $code => [$en, $fr, $ar]) {
            Symptom::updateOrCreate(['code' => $code], ['name' => $en, 'status' => 'published'])
                ->syncTranslations(['fr' => ['name' => $fr], 'ar' => ['name' => $ar]]);
        }

        // code => [EN name, FR name, AR name, specialty, severity, body parts, symptoms, short description]
        $diseases = [
            'tension-headache' => ['Tension-type headache', 'Céphalée de tension', 'صداع التوتر', 'general-practice', 'low', ['head'], [], 'A common headache often described as a band-like pressure.'],
            'migraine' => ['Migraine', 'Migraine', 'الشقيقة', 'neurology', 'moderate', ['head'], ['light-sensitivity' => 2, 'nausea' => 1], 'Recurrent headaches, often one-sided, which may come with nausea or sensitivity to light.'],
            'sinusitis' => ['Sinusitis', 'Sinusite', 'التهاب الجيوب الأنفية', 'ent', 'low', ['head'], ['runny-nose' => 2, 'fever' => 1], 'Inflammation of the sinuses, often after a cold.'],
            'pharyngitis' => ['Sore throat (pharyngitis)', 'Angine / pharyngite', 'التهاب البلعوم', 'ent', 'low', ['neck'], ['sore-throat' => 3, 'fever' => 1], 'Inflammation of the throat, usually caused by an infection.'],
            'angina' => ['Angina (heart-related chest pain)', 'Angine de poitrine', 'الذبحة الصدرية', 'cardiology', 'high', ['chest'], ['shortness-of-breath' => 2, 'palpitations' => 1], 'Chest pain caused by reduced blood flow to the heart, typically on exertion.'],
            'gerd' => ['Acid reflux (GERD)', 'Reflux gastro-œsophagien', 'الارتجاع المعدي المريئي', 'gastroenterology', 'low', ['chest', 'abdomen'], ['nausea' => 1], 'Stomach acid flowing back into the oesophagus, causing a burning sensation.'],
            'bronchitis' => ['Acute bronchitis', 'Bronchite aiguë', 'التهاب الشعب الهوائية الحاد', 'pulmonology', 'low', ['chest'], ['cough' => 3, 'fever' => 1], 'Inflammation of the airways, usually following a viral infection.'],
            'chest-wall-pain' => ['Chest wall (musculoskeletal) pain', 'Douleur pariétale thoracique', 'ألم جدار الصدر', 'general-practice', 'low', ['chest', 'upper-back'], [], 'Pain coming from the muscles, bones or cartilage of the chest wall.'],
            'gastroenteritis' => ['Gastroenteritis', 'Gastro-entérite', 'التهاب المعدة والأمعاء', 'gastroenterology', 'moderate', ['abdomen'], ['diarrhea' => 3, 'nausea' => 2, 'fever' => 1], 'Infection of the gut causing diarrhoea and/or vomiting.'],
            'appendicitis' => ['Appendicitis', 'Appendicite', 'التهاب الزائدة الدودية', 'general-surgery', 'high', ['abdomen', 'pelvis'], ['fever' => 1, 'nausea' => 1], 'Inflammation of the appendix; it requires prompt medical assessment.'],
            'uti' => ['Urinary tract infection', 'Infection urinaire', 'عدوى المسالك البولية', 'urology', 'moderate', ['pelvis', 'abdomen'], ['burning-urination' => 3, 'fever' => 1], 'Infection of the bladder or urinary tract.'],
            'kidney-stones' => ['Kidney stones', 'Calculs rénaux', 'حصى الكلى', 'urology', 'moderate', ['lower-back', 'abdomen'], ['nausea' => 1], 'Hard deposits in the kidney that can cause intense, wave-like pain.'],
            'low-back-pain' => ['Mechanical low back pain', 'Lombalgie commune', 'ألم أسفل الظهر الميكانيكي', 'rheumatology', 'low', ['lower-back'], [], 'Very common back pain related to muscles, joints or posture.'],
            'sciatica' => ['Sciatica', 'Sciatique', 'عرق النسا', 'rheumatology', 'moderate', ['lower-back', 'leg'], ['pain-down-leg' => 3], 'Pain along the sciatic nerve, from the lower back down the leg.'],
            'dvt' => ['Deep vein thrombosis', 'Thrombose veineuse profonde', 'تخثر الأوردة العميقة', 'cardiology', 'high', ['leg'], ['leg-swelling' => 3, 'redness-warmth' => 1], 'A blood clot in a deep vein, usually in the leg; it needs prompt medical assessment.'],
            'muscle-strain' => ['Muscle strain', 'Élongation musculaire', 'شدّ عضلي', 'orthopedics', 'low', ['leg', 'arm', 'upper-back'], [], 'Overstretching or tearing of a muscle.'],
            'shoulder-tendinitis' => ['Shoulder tendinitis', "Tendinite de l'épaule", 'التهاب أوتار الكتف', 'orthopedics', 'low', ['arm'], [], 'Inflammation of the tendons of the shoulder.'],
        ];

        $specialtyIds = Specialty::pluck('id', 'code');
        $bodyPartIds = BodyPart::pluck('id', 'code');
        $symptomIds = Symptom::pluck('id', 'code');

        foreach ($diseases as $code => [$en, $fr, $ar, $specialty, $severity, $parts, $syms, $description]) {
            $disease = Disease::updateOrCreate(['code' => $code], [
                'name' => $en,
                'description' => $description,
                'specialty_id' => $specialtyIds[$specialty],
                'severity' => $severity,
                'status' => 'published',
                'review_status' => 'draft',
                'source_name' => self::SAMPLE_SOURCE,
                'is_sample' => true,
            ]);
            $disease->syncTranslations(['fr' => ['name' => $fr], 'ar' => ['name' => $ar]]);
            $disease->bodyParts()->sync(collect($parts)->mapWithKeys(fn ($p) => [$bodyPartIds[$p] => ['weight' => 1]])->all());
            $disease->symptoms()->sync(collect($syms)->mapWithKeys(fn ($w, $s) => [$symptomIds[$s] => ['weight' => $w]])->all());
        }

        // Generic, conservative "when to consult" sample guidelines.
        $diseaseIds = Disease::pluck('id', 'code');
        $guidelines = [
            ['general-mild-pain', null, 'self_care', 'Mild pain: monitor your symptoms', 'Rest, stay hydrated and note how your symptoms evolve. See a doctor if the pain lasts more than a few days, gets worse, or if new symptoms appear.'],
            ['migraine-consult', 'migraine', 'when_to_consult', 'When to see a doctor about headaches', 'Consult a doctor if headaches are frequent, change in pattern, or disturb your daily life. A doctor can confirm the type of headache and discuss suitable options.'],
            ['angina-consult', 'angina', 'when_to_consult', 'Chest pain on exertion needs a medical assessment', 'Chest pain that appears with effort and eases with rest should be assessed by a doctor quickly. If the pain lasts several minutes at rest, call emergency services.'],
            ['gastro-consult', 'gastroenteritis', 'self_care', 'Diarrhoea and vomiting: prevent dehydration', 'Drink small amounts of fluid often. See a doctor if you cannot keep fluids down, if symptoms last more than a few days, or if you notice blood in your stool.'],
            ['appendicitis-consult', 'appendicitis', 'when_to_consult', 'Pain in the lower right belly needs prompt assessment', 'Pain that settles in the lower right part of the belly, especially with fever or vomiting, should be assessed by a doctor the same day.'],
            ['uti-consult', 'uti', 'when_to_consult', 'Burning when urinating', 'See a doctor for a urine test. Seek care quickly if you have fever, back pain or if you are pregnant.'],
            ['back-pain-selfcare', 'low-back-pain', 'self_care', 'Staying active with back pain', 'Gentle movement is usually better than bed rest. See a doctor if the pain lasts more than a few weeks or spreads down the leg.'],
            ['dvt-consult', 'dvt', 'when_to_consult', 'A swollen, painful leg needs same-day assessment', 'A swollen, painful or warm leg should be assessed by a doctor the same day. Call emergency services if you also become short of breath.'],
        ];
        foreach ($guidelines as [$key, $disease, $type, $title, $content]) {
            MedicalGuideline::updateOrCreate(['title' => $title], [
                'disease_id' => $disease ? $diseaseIds[$disease] : null,
                'guideline_type' => $type,
                'content' => $content,
                'source_name' => self::SAMPLE_SOURCE,
                'review_status' => 'draft',
                'is_sample' => true,
                'status' => 'published',
            ]);
        }
    }
}
