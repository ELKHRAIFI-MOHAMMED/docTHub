<?php

namespace Database\Seeders;

use App\Models\BodyPart;
use App\Models\Disease;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\QuestionCondition;
use App\Models\QuestionOption;
use App\Models\Specialty;
use App\Models\Symptom;
use App\Models\SymptomQuestion;
use Illuminate\Database\Seeder;

/**
 * DEVELOPMENT SAMPLE questionnaire and orientation rules.
 * Emergency rules reflect widely published public "red flag" warning signs, but are still seeded as
 * draft + is_sample: they must be reviewed by a clinician. (Draft emergency rules are evaluated anyway —
 * see ContentGate — because over-triage is the safer failure mode.)
 */
class QuestionnaireSeeder extends Seeder
{
    private array $bodyParts;
    private array $symptoms;

    public function run(): void
    {
        $this->bodyParts = BodyPart::pluck('id', 'code')->all();
        $this->symptoms = Symptom::pluck('id', 'code')->all();

        // ---------- Global questions ----------
        $this->question('pain_intensity', null, 'scale', 10,
            ['How strong is the pain? (0 = none, 10 = worst imaginable)', 'Quelle est l’intensité de la douleur ? (0 = aucune, 10 = la pire imaginable)', 'ما شدة الألم؟ (0 = لا ألم، 10 = أسوأ ألم ممكن)'],
            [], ['min_value' => 0, 'max_value' => 10]);

        $this->question('pain_duration', null, 'single_choice', 20,
            ['How long have you had these symptoms?', 'Depuis combien de temps avez-vous ces symptômes ?', 'منذ متى لديك هذه الأعراض؟'], [
                ['lt_24h', 'Less than 24 hours', 'Moins de 24 heures', 'أقل من 24 ساعة'],
                ['1_7d', '1 to 7 days', '1 à 7 jours', 'من يوم إلى 7 أيام'],
                ['1_4w', '1 to 4 weeks', '1 à 4 semaines', 'من أسبوع إلى 4 أسابيع'],
                ['gt_4w', 'More than 4 weeks', 'Plus de 4 semaines', 'أكثر من 4 أسابيع'],
            ]);

        $this->question('pain_onset', null, 'single_choice', 30,
            ['How did it start?', 'Comment cela a-t-il commencé ?', 'كيف بدأت الأعراض؟'], [
                ['sudden', 'Suddenly (within seconds or minutes)', 'Brutalement (en quelques secondes ou minutes)', 'فجأة (خلال ثوانٍ أو دقائق)'],
                ['gradual', 'Gradually', 'Progressivement', 'تدريجيًا'],
            ]);

        $this->question('pain_frequency', null, 'single_choice', 40,
            ['How often do you have it?', 'À quelle fréquence ?', 'كم مرة تشعر بذلك؟'], [
                ['constant', 'All the time', 'En permanence', 'طوال الوقت'],
                ['intermittent', 'It comes and goes', 'Par épisodes', 'يأتي ويذهب'],
                ['first_time', 'This is the first time', "C'est la première fois", 'هذه أول مرة'],
            ]);

        $this->question('assoc_general', null, 'multi_choice', 50,
            ['Do you also have any of these?', 'Avez-vous aussi l’un de ces symptômes ?', 'هل لديك أيضًا أيٌّ مما يلي؟'], [
                ['fever', 'Fever', 'Fièvre', 'حمّى', 'fever'],
                ['nausea', 'Nausea or vomiting', 'Nausées ou vomissements', 'غثيان أو قيء', 'nausea'],
                ['dizziness', 'Dizziness', 'Vertiges', 'دوخة', 'dizziness'],
                ['none', 'None of these', 'Aucun', 'لا شيء مما سبق'],
            ]);

        // ---------- Head ----------
        $this->question('head_assoc', 'head', 'multi_choice', 10,
            ['Along with the headache, do you have…', 'En plus du mal de tête, avez-vous…', 'إلى جانب الصداع، هل لديك…'], [
                ['light_sensitivity', 'Sensitivity to light', 'Sensibilité à la lumière', 'حساسية للضوء', 'light-sensitivity'],
                ['nausea', 'Nausea or vomiting', 'Nausées ou vomissements', 'غثيان أو قيء', 'nausea'],
                ['runny_nose', 'Blocked or runny nose', 'Nez bouché ou qui coule', 'انسداد أو سيلان الأنف', 'runny-nose'],
                ['stiff_neck', 'Stiff neck', 'Raideur de la nuque', 'تيبّس الرقبة', 'stiff-neck'],
                ['one_sided_weakness', 'Weakness or numbness on one side of the body or face', 'Faiblesse ou engourdissement d’un côté du corps ou du visage', 'ضعف أو خدر في جانب واحد من الجسم أو الوجه', 'one-sided-weakness'],
                ['speech_difficulty', 'Difficulty speaking or understanding', 'Difficulté à parler ou à comprendre', 'صعوبة في الكلام أو الفهم', 'speech-difficulty'],
                ['confusion', 'Confusion', 'Confusion', 'ارتباك', 'confusion'],
                ['none', 'None of these', 'Aucun', 'لا شيء مما سبق'],
            ]);
        $worst = $this->question('head_worst_ever', 'head', 'boolean', 20,
            ['Is this the worst headache of your life, reaching full strength within about a minute?', 'Est-ce le pire mal de tête de votre vie, devenu maximal en une minute environ ?', 'هل هذا أسوأ صداع في حياتك ووصل إلى أقصى شدته خلال دقيقة تقريبًا؟']);
        $this->condition($worst, 'pain_onset', 'equals', 'sudden');

        // ---------- Chest ----------
        $this->question('chest_assoc', 'chest', 'multi_choice', 10,
            ['Along with the chest pain, do you have…', 'En plus de la douleur thoracique, avez-vous…', 'إلى جانب ألم الصدر، هل لديك…'], [
                ['shortness_of_breath', 'Shortness of breath', 'Essoufflement', 'ضيق في التنفس', 'shortness-of-breath'],
                ['sweating', 'Cold sweats', 'Sueurs froides', 'تعرّق بارد', 'sweating'],
                ['radiating_arm_jaw', 'Pain spreading to the arm, neck or jaw', 'Douleur irradiant vers le bras, le cou ou la mâchoire', 'ألم يمتد إلى الذراع أو الرقبة أو الفك', 'radiating-arm-jaw'],
                ['palpitations', 'Palpitations', 'Palpitations', 'خفقان', 'palpitations'],
                ['cough', 'Cough', 'Toux', 'سعال', 'cough'],
                ['none', 'None of these', 'Aucun', 'لا شيء مما سبق'],
            ]);
        $this->question('chest_trigger', 'chest', 'single_choice', 20,
            ['When does the chest pain occur?', 'Quand la douleur thoracique apparaît-elle ?', 'متى يظهر ألم الصدر؟'], [
                ['at_rest', 'At rest', 'Au repos', 'أثناء الراحة'],
                ['on_exertion', 'During physical effort, easing with rest', "À l'effort, et elle se calme au repos", 'أثناء المجهود وتخف مع الراحة'],
                ['breathing_pressing', 'When breathing deeply or pressing on the chest', 'En respirant profondément ou en appuyant sur la poitrine', 'عند التنفس العميق أو الضغط على الصدر'],
                ['after_meals', 'After meals or when lying down', 'Après les repas ou en position allongée', 'بعد الأكل أو عند الاستلقاء'],
            ]);
        $this->question('history_heart', 'chest', 'boolean', 30,
            ['Have you ever been diagnosed with a heart condition?', 'Avez-vous déjà eu un diagnostic de maladie cardiaque ?', 'هل سبق أن تم تشخيصك بمرض في القلب؟']);

        // ---------- Neck ----------
        $this->question('neck_assoc', 'neck', 'multi_choice', 10,
            ['Do you have any of these?', 'Avez-vous l’un de ces symptômes ?', 'هل لديك أيٌّ مما يلي؟'], [
                ['sore_throat', 'Sore throat', 'Mal de gorge', 'التهاب الحلق', 'sore-throat'],
                ['stiff_neck', 'Stiff neck (hard to bend the head forward)', 'Nuque raide (difficile de pencher la tête en avant)', 'تيبّس الرقبة (صعوبة في ثني الرأس للأمام)', 'stiff-neck'],
                ['none', 'None of these', 'Aucun', 'لا شيء مما سبق'],
            ]);

        // ---------- Abdomen ----------
        $this->question('abdomen_location', 'abdomen', 'single_choice', 10,
            ['Where exactly is the pain?', 'Où se situe exactement la douleur ?', 'أين يقع الألم بالضبط؟'], [
                ['upper', 'Upper belly', 'Haut du ventre', 'أعلى البطن'],
                ['lower_right', 'Lower right', 'En bas à droite', 'أسفل اليمين'],
                ['lower_left', 'Lower left', 'En bas à gauche', 'أسفل اليسار'],
                ['diffuse', 'All over', 'Partout', 'في كل البطن'],
            ]);
        $this->question('abdomen_assoc', 'abdomen', 'multi_choice', 20,
            ['Do you also have…', 'Avez-vous aussi…', 'هل لديك أيضًا…'], [
                ['diarrhea', 'Diarrhoea', 'Diarrhée', 'إسهال', 'diarrhea'],
                ['blood_in_stool', 'Blood in stool or black stools', 'Sang dans les selles ou selles noires', 'دم في البراز أو براز أسود', 'blood-in-stool'],
                ['burning_urination', 'Burning when urinating', 'Brûlures en urinant', 'حرقة أثناء التبول', 'burning-urination'],
                ['none', 'None of these', 'Aucun', 'لا شيء مما سبق'],
            ]);
        $rigid = $this->question('abdomen_rigid', 'abdomen', 'boolean', 30,
            ['Is your belly hard and extremely painful when touched?', 'Votre ventre est-il dur et extrêmement douloureux au toucher ?', 'هل بطنك صلب ومؤلم جدًا عند لمسه؟']);
        $this->condition($rigid, 'pain_intensity', 'gte', 7);

        // ---------- Pelvis ----------
        $this->question('pelvis_assoc', 'pelvis', 'multi_choice', 10,
            ['Do you have any of these?', 'Avez-vous l’un de ces symptômes ?', 'هل لديك أيٌّ مما يلي؟'], [
                ['burning_urination', 'Burning when urinating', 'Brûlures en urinant', 'حرقة أثناء التبول', 'burning-urination'],
                ['none', 'None of these', 'Aucun', 'لا شيء مما سبق'],
            ]);

        // ---------- Lower back ----------
        $this->question('back_assoc', 'lower-back', 'multi_choice', 10,
            ['Along with the back pain, do you have…', 'En plus du mal de dos, avez-vous…', 'إلى جانب ألم الظهر، هل لديك…'], [
                ['pain_down_leg', 'Pain going down one leg', 'Une douleur qui descend dans une jambe', 'ألم ينزل في إحدى الساقين', 'pain-down-leg'],
                ['saddle_numbness', 'Numbness between the legs or around the buttocks', 'Engourdissement entre les jambes ou autour des fesses', 'خدر بين الساقين أو حول الأرداف', 'saddle-numbness'],
                ['bladder_control_loss', 'New loss of bladder or bowel control', 'Perte récente du contrôle de la vessie ou des intestins', 'فقدان حديث للتحكم في المثانة أو الأمعاء', 'bladder-control-loss'],
                ['burning_urination', 'Burning when urinating', 'Brûlures en urinant', 'حرقة أثناء التبول', 'burning-urination'],
                ['none', 'None of these', 'Aucun', 'لا شيء مما سبق'],
            ]);

        // ---------- Leg ----------
        $this->question('leg_assoc', 'leg', 'multi_choice', 10,
            ['Do you notice any of these on the painful leg?', 'Remarquez-vous l’un de ces signes sur la jambe douloureuse ?', 'هل تلاحظ أيًّا مما يلي على الساق المؤلمة؟'], [
                ['swelling', 'Swelling of one leg', "Gonflement d'une jambe", 'تورّم إحدى الساقين', 'leg-swelling'],
                ['redness_warmth', 'Redness and warmth', 'Rougeur et chaleur', 'احمرار وسخونة', 'redness-warmth'],
                ['after_injury', 'Started after an injury or effort', 'Apparue après un traumatisme ou un effort', 'بدأ بعد إصابة أو مجهود'],
                ['none', 'None of these', 'Aucun', 'لا شيء مما سبق'],
            ]);

        $this->seedSummaryTemplates();
        $this->seedRules();
    }

    /** Sentences used by the readable case summary (":answer" = the patient's answer). */
    private function seedSummaryTemplates(): void
    {
        $templates = [
            'pain_intensity' => ['Pain intensity: :answer out of 10.', 'Intensité de la douleur : :answer sur 10.', 'شدة الألم: :answer من 10.'],
            'pain_duration' => ['Duration of symptoms: :answer.', 'Durée des symptômes : :answer.', 'مدة الأعراض: :answer.'],
            'pain_onset' => ['Onset: :answer.', 'Début : :answer.', 'بداية الأعراض: :answer.'],
            'pain_frequency' => ['Frequency: :answer.', 'Fréquence : :answer.', 'التكرار: :answer.'],
            'assoc_general' => ['Other symptoms: :answer.', 'Autres symptômes : :answer.', 'أعراض أخرى: :answer.'],
            'head_assoc' => ['With the headache: :answer.', 'Avec le mal de tête : :answer.', 'مع الصداع: :answer.'],
            'head_worst_ever' => ['Sudden, worst headache ever: :answer.', 'Mal de tête brutal, le pire jamais ressenti : :answer.', 'صداع مفاجئ وهو الأسوأ على الإطلاق: :answer.'],
            'chest_assoc' => ['With the chest pain: :answer.', 'Avec la douleur thoracique : :answer.', 'مع ألم الصدر: :answer.'],
            'chest_trigger' => ['The chest pain occurs: :answer.', 'Survenue de la douleur thoracique : :answer.', 'وقت ظهور ألم الصدر: :answer.'],
            'history_heart' => ['Known heart condition: :answer.', 'Antécédent cardiaque connu : :answer.', 'مرض قلبي معروف: :answer.'],
            'neck_assoc' => ['Neck and throat: :answer.', 'Cou et gorge : :answer.', 'الرقبة والحلق: :answer.'],
            'abdomen_location' => ['Location of the abdominal pain: :answer.', 'Localisation de la douleur abdominale : :answer.', 'موضع ألم البطن: :answer.'],
            'abdomen_assoc' => ['With the abdominal pain: :answer.', 'Avec la douleur abdominale : :answer.', 'مع ألم البطن: :answer.'],
            'abdomen_rigid' => ['Hard, very painful belly: :answer.', 'Ventre dur et très douloureux : :answer.', 'بطن صلب ومؤلم جدًا: :answer.'],
            'pelvis_assoc' => ['Lower belly symptoms: :answer.', 'Symptômes du bas-ventre : :answer.', 'أعراض أسفل البطن: :answer.'],
            'back_assoc' => ['With the back pain: :answer.', 'Avec le mal de dos : :answer.', 'مع ألم الظهر: :answer.'],
            'leg_assoc' => ['On the painful leg: :answer.', 'Sur la jambe douloureuse : :answer.', 'على الساق المؤلمة: :answer.'],
        ];

        foreach ($templates as $code => [$en, $fr, $ar]) {
            $question = SymptomQuestion::where('code', $code)->first();
            if ($question) {
                $question->update(['summary_template' => $en]);
                $question->syncTranslations(['fr' => ['summary_template' => $fr], 'ar' => ['summary_template' => $ar]]);
            }
        }
    }

    private function seedRules(): void
    {
        $q = SymptomQuestion::pluck('id', 'code');
        $d = Disease::pluck('id', 'code');
        $s = Specialty::pluck('id', 'code');
        $bp = $this->bodyParts;
        $source = MedicalCatalogSeeder::SAMPLE_SOURCE;

        $rules = [
            // ----- Emergency (evaluated first) -----
            ['em-chest-cardiac', 'Chest pain with breathlessness, cold sweats or radiating pain', 'emergency', 100, $bp['chest'], $q['chest_assoc'], 'in', ['shortness_of_breath', 'sweating', 'radiating_arm_jaw'], null, ['emergency_severity' => 'critical']],
            ['em-stroke-signs', 'Possible stroke warning signs', 'emergency', 100, null, $q['head_assoc'], 'in', ['one_sided_weakness', 'speech_difficulty', 'confusion'], null, ['emergency_severity' => 'critical']],
            ['em-thunderclap', 'Sudden, worst-ever headache', 'emergency', 90, $bp['head'], $q['head_worst_ever'], 'equals', 'yes', null, ['emergency_severity' => 'critical']],
            ['em-fever-stiff-neck-head', 'Fever with stiff neck (head)', 'emergency', 90, null, $q['head_assoc'], 'in', ['stiff_neck'], [['question' => 'assoc_general', 'operator' => 'in', 'value' => ['fever']]], ['emergency_severity' => 'critical']],
            ['em-fever-stiff-neck-neck', 'Fever with stiff neck (neck)', 'emergency', 90, null, $q['neck_assoc'], 'in', ['stiff_neck'], [['question' => 'assoc_general', 'operator' => 'in', 'value' => ['fever']]], ['emergency_severity' => 'critical']],
            ['em-rigid-abdomen', 'Hard, extremely painful abdomen', 'emergency', 80, $bp['abdomen'], $q['abdomen_rigid'], 'equals', 'yes', null, ['emergency_severity' => 'urgent']],
            ['em-gi-bleeding', 'Blood in stool or black stools', 'emergency', 80, null, $q['abdomen_assoc'], 'in', ['blood_in_stool'], null, ['emergency_severity' => 'urgent']],
            ['em-cauda-equina', 'Back pain with saddle numbness or loss of bladder control', 'emergency', 80, null, $q['back_assoc'], 'in', ['saddle_numbness', 'bladder_control_loss'], null, ['emergency_severity' => 'urgent']],

            // ----- Possible conditions -----
            ['dz-migraine', 'Headache with light sensitivity or nausea', 'disease', 10, null, $q['head_assoc'], 'in', ['light_sensitivity', 'nausea'], null, ['disease_id' => $d['migraine']]],
            ['dz-sinusitis', 'Headache with blocked nose', 'disease', 10, null, $q['head_assoc'], 'in', ['runny_nose'], null, ['disease_id' => $d['sinusitis']]],
            ['dz-tension', 'Gradual, intermittent headache', 'disease', 5, $bp['head'], $q['pain_onset'], 'equals', 'gradual', [['question' => 'pain_intensity', 'operator' => 'lte', 'value' => 6]], ['disease_id' => $d['tension-headache']]],
            ['dz-angina', 'Chest pain on exertion', 'disease', 20, null, $q['chest_trigger'], 'equals', 'on_exertion', null, ['disease_id' => $d['angina'], 'weight' => 2]],
            ['dz-chest-wall', 'Chest pain when breathing or pressing', 'disease', 10, null, $q['chest_trigger'], 'equals', 'breathing_pressing', null, ['disease_id' => $d['chest-wall-pain']]],
            ['dz-gerd', 'Chest pain after meals or lying down', 'disease', 10, null, $q['chest_trigger'], 'equals', 'after_meals', null, ['disease_id' => $d['gerd'], 'weight' => 2]],
            ['dz-bronchitis', 'Chest pain with cough', 'disease', 10, null, $q['chest_assoc'], 'in', ['cough'], null, ['disease_id' => $d['bronchitis']]],
            ['dz-appendicitis', 'Lower right abdominal pain', 'disease', 20, null, $q['abdomen_location'], 'equals', 'lower_right', null, ['disease_id' => $d['appendicitis'], 'weight' => 2]],
            ['dz-gastro', 'Abdominal pain with diarrhoea', 'disease', 10, null, $q['abdomen_assoc'], 'in', ['diarrhea'], null, ['disease_id' => $d['gastroenteritis']]],
            ['dz-uti-abdomen', 'Burning urination (abdomen)', 'disease', 10, null, $q['abdomen_assoc'], 'in', ['burning_urination'], null, ['disease_id' => $d['uti']]],
            ['dz-uti-pelvis', 'Burning urination (pelvis)', 'disease', 10, null, $q['pelvis_assoc'], 'in', ['burning_urination'], null, ['disease_id' => $d['uti'], 'weight' => 2]],
            ['dz-sciatica', 'Back pain radiating down the leg', 'disease', 10, null, $q['back_assoc'], 'in', ['pain_down_leg'], null, ['disease_id' => $d['sciatica'], 'weight' => 2]],
            ['dz-kidney-stones', 'Sudden, severe lower back pain', 'disease', 10, $bp['lower-back'], $q['pain_onset'], 'equals', 'sudden', [['question' => 'pain_intensity', 'operator' => 'gte', 'value' => 7]], ['disease_id' => $d['kidney-stones']]],
            ['dz-low-back', 'Gradual lower back pain', 'disease', 5, $bp['lower-back'], $q['pain_onset'], 'equals', 'gradual', null, ['disease_id' => $d['low-back-pain']]],
            ['dz-dvt', 'One swollen leg', 'disease', 20, null, $q['leg_assoc'], 'in', ['swelling'], null, ['disease_id' => $d['dvt'], 'weight' => 2]],
            ['dz-strain', 'Leg pain after injury or effort', 'disease', 10, null, $q['leg_assoc'], 'in', ['after_injury'], null, ['disease_id' => $d['muscle-strain']]],
            ['dz-pharyngitis', 'Neck pain with sore throat', 'disease', 10, null, $q['neck_assoc'], 'in', ['sore_throat'], null, ['disease_id' => $d['pharyngitis']]],

            // ----- Specialty and advice -----
            ['sp-heart-history', 'Chest pain with known heart condition', 'specialty', 10, $bp['chest'], $q['history_heart'], 'equals', 'yes', null, ['specialty_id' => $s['cardiology'], 'weight' => 2]],
            ['ad-mild-pain', 'Mild pain: self-monitoring advice', 'advice', 0, null, $q['pain_intensity'], 'lte', 3, null, ['guideline_id' => MedicalGuideline::where('title', 'Mild pain: monitor your symptoms')->value('id')]],
        ];

        foreach ($rules as [$code, $name, $type, $priority, $bodyPartId, $questionId, $operator, $expected, $conditions, $extra]) {
            OrientationRule::updateOrCreate(['code' => $code], array_merge([
                'name' => $name,
                'result_type' => $type,
                'priority' => $priority,
                'body_part_id' => $bodyPartId,
                'question_id' => $questionId,
                'operator' => $operator,
                'expected_value' => $expected,
                'conditions' => $conditions,
                'weight' => 1,
                'source_name' => $source,
                'validation_status' => 'draft',
                'version' => 1,
                'is_sample' => true,
                'disease_id' => null,
                'specialty_id' => null,
                'guideline_id' => null,
                'emergency_severity' => null,
            ], $extra));
        }
    }

    /**
     * @param  array{0: string, 1: string, 2: string}  $text  [en, fr, ar]
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4?: string}>  $options  [code, en, fr, ar, symptom code?]
     */
    private function question(string $code, ?string $bodyPart, string $type, int $sort, array $text, array $options = [], array $extra = []): SymptomQuestion
    {
        $question = SymptomQuestion::updateOrCreate(['code' => $code], array_merge([
            'body_part_id' => $bodyPart ? $this->bodyParts[$bodyPart] : null,
            'text' => $text[0],
            'type' => $type,
            'is_required' => true,
            'sort_order' => $sort,
            'status' => 'published',
        ], $extra));
        $question->syncTranslations(['fr' => ['text' => $text[1]], 'ar' => ['text' => $text[2]]]);

        foreach ($options as $i => $opt) {
            $option = QuestionOption::updateOrCreate(['question_id' => $question->id, 'code' => $opt[0]], [
                'label' => $opt[1],
                'symptom_id' => isset($opt[4]) ? $this->symptoms[$opt[4]] : null,
                'sort_order' => $i,
            ]);
            $option->syncTranslations(['fr' => ['label' => $opt[2]], 'ar' => ['label' => $opt[3]]]);
        }

        return $question;
    }

    private function condition(SymptomQuestion $question, string $dependsOnCode, string $operator, mixed $value): void
    {
        QuestionCondition::updateOrCreate(
            ['question_id' => $question->id, 'depends_on_question_id' => SymptomQuestion::where('code', $dependsOnCode)->value('id')],
            ['operator' => $operator, 'value' => $value]
        );
    }
}
