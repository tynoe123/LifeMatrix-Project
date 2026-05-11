<?php
// prediction.php  —  TFLite-powered cardiovascular risk predictor
session_start();

// ===== DATABASE CONNECTION =====
$host     = "localhost";
$dbname   = "freelanceelectro_data";
$username = "freelanceelectro_data";
$password = "Leeroyku2";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ===== FETCH LAST 7 DAYS OF READINGS =====
$sql = "
    SELECT heart_rate, spo2, temperature, fall_detected, created_at
    FROM patient_readings
    WHERE created_at >= NOW() - INTERVAL 7 DAY
      AND heart_rate  >= 45
      AND spo2        >= 85
      AND temperature >= 32
      AND hr_fresh     = 1
    ORDER BY created_at ASC
";

$result   = $conn->query($sql);
$readings = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $readings[] = $row;
    }
}

// ===== DEFAULTS =====
$avg_hr = $max_hr = $avg_spo2 = $avg_temp = $max_temp = $fall_count = 0;
$min_spo2 = 100;
$high_hr_count = $low_spo2_count = $high_temp_count = 0;
$stability_index   = 0;
$model_confidence  = 0;
$probabilities     = [];
$prediction = [
    'condition_name'  => 'No Predictive Insight Available',
    'risk_level'      => 'Unknown',
    'warning_message' => 'Not enough recent physiological data to generate an insight.',
    'recommendation'  => 'Continue monitoring to improve prediction reliability.',
    'actions'         => [],
    'urgency_color'   => 'slate',
];
$tflite_error = null;

// ===== LABEL → DISPLAY MAP =====
function labelToDisplay(string $label): array {
    $map = [
        'Normal Healthy' => [
            'risk_level'      => 'Low Risk',
            'urgency_color'   => 'emerald',
            'warning_message' => 'All vital signs are within healthy reference ranges.',
            'recommendation'  => 'Your cardiovascular profile looks excellent. Maintain your current routine and stay active.',
            'actions'         => [
                'Schedule a routine annual check-up',
                'Maintain a balanced diet and regular exercise',
                'Keep monitoring vitals for baseline tracking',
            ],
        ],
        'Generally Stable' => [
            'risk_level'      => 'Low Risk',
            'urgency_color'   => 'emerald',
            'warning_message' => 'Vital signs are within acceptable ranges with minor fluctuations.',
            'recommendation'  => 'No immediate concern. Continue regular monitoring.',
            'actions'         => [
                'Continue regular vitals monitoring',
                'Maintain hydration and sleep schedule',
            ],
        ],
        'Resting Athlete Condition' => [
            'risk_level'      => 'Low Risk',
            'urgency_color'   => 'emerald',
            'warning_message' => 'Low resting heart rate consistent with high cardiovascular fitness.',
            'recommendation'  => 'This is a normal finding for physically fit individuals. No concern detected.',
            'actions'         => [
                'Maintain hydration and routine health checks',
                'Monitor for symptoms like dizziness if HR drops very low at rest',
            ],
        ],
        'Post-Exercise Elevated HR' => [
            'risk_level'      => 'Low Risk',
            'urgency_color'   => 'blue',
            'warning_message' => 'Elevated heart rate is likely attributed to recent physical activity.',
            'recommendation'  => 'Allow adequate recovery time and re-measure at rest.',
            'actions'         => [
                'Rest for 15–30 minutes then re-measure',
                'Ensure adequate hydration post-exercise',
            ],
        ],
        'Borderline Tachycardia' => [
            'risk_level'      => 'Moderate Risk',
            'urgency_color'   => 'amber',
            'warning_message' => 'Heart rate is slightly elevated above the normal resting range.',
            'recommendation'  => 'Monitor closely. Lifestyle adjustments recommended.',
            'actions'         => [
                'Reduce caffeine and stimulant intake',
                'Practice stress-reduction techniques (breathing, meditation)',
                'Revisit readings after 24–48 hours',
                'Consult a GP if elevation persists beyond 3 days',
            ],
        ],
        'Bradycardia' => [
            'risk_level'      => 'Moderate Risk',
            'urgency_color'   => 'amber',
            'warning_message' => 'Heart rate is consistently below the normal resting threshold.',
            'recommendation'  => 'Medical evaluation recommended. Avoid medications that further lower HR.',
            'actions'         => [
                'Book a cardiology or GP consultation',
                'Avoid beta-blockers or HR-lowering supplements without advice',
                'Watch for dizziness, fatigue, or fainting episodes',
            ],
        ],
        'Hypertension Stage 1 (Compensated)' => [
            'risk_level'      => 'Moderate Risk',
            'urgency_color'   => 'amber',
            'warning_message' => 'Early-stage hypertension detected; currently within compensated range.',
            'recommendation'  => 'Lifestyle intervention can significantly reduce progression risk.',
            'actions'         => [
                'Reduce dietary sodium to below 2g/day',
                'Increase aerobic physical activity (30 min, 5×/week)',
                'Monitor blood pressure daily at the same time',
                'Schedule a GP review within 2 weeks',
            ],
        ],
        'Hypertension Stage 2' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'Significant sustained hypertension detected across the monitoring period.',
            'recommendation'  => 'Prompt medical attention required. Medication may be necessary.',
            'actions'         => [
                'See your GP or cardiologist within 48 hours',
                'Strictly limit salt, alcohol, and stimulants',
                'Begin daily blood pressure logging',
                'Avoid strenuous physical activity until reviewed',
            ],
        ],
        'Hypertensive Crisis Risk' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'Cardiac indicators suggest a hypertensive crisis may be developing.',
            'recommendation'  => 'Seek urgent medical review immediately.',
            'actions'         => [
                'Seek urgent medical attention today',
                'Avoid all strenuous physical activity',
                'Do not take any unprescribed medication',
                'If headache, vision changes, or chest pain occur — go to A&E',
            ],
        ],
        'Atrial Fibrillation Pattern' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'Irregular heart rate pattern consistent with atrial fibrillation detected.',
            'recommendation'  => 'Urgent cardiology referral required.',
            'actions'         => [
                'Contact a cardiologist urgently',
                'Avoid caffeine, alcohol, and all stimulants',
                'Do not drive until cleared by a physician',
                'If palpitations are severe, go to A&E immediately',
            ],
        ],
        'Supraventricular Tachycardia' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'Rapid abnormal heart rhythm originating above the ventricles detected.',
            'recommendation'  => 'Seek prompt medical evaluation.',
            'actions'         => [
                'Seek medical evaluation within 24 hours',
                'Avoid triggers: caffeine, stress, sleep deprivation',
                'Learn vagal manoeuvres (under medical guidance)',
                'Go to A&E if episode lasts more than 30 minutes',
            ],
        ],
        'Angina (Stable)' => [
            'risk_level'      => 'Moderate Risk',
            'urgency_color'   => 'amber',
            'warning_message' => 'Pattern consistent with stable angina; chest discomfort may be present with exertion.',
            'recommendation'  => 'Cardiology consultation and lifestyle modification advised.',
            'actions'         => [
                'Book a cardiology review within 1 week',
                'Avoid overexertion and heavy physical activity',
                'Monitor for worsening symptoms at rest',
                'Review dietary fat and cholesterol intake',
            ],
        ],
        'Ischemic Heart Disease' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'Indicators suggest reduced blood flow to the heart muscle.',
            'recommendation'  => 'Immediate cardiology consultation is required.',
            'actions'         => [
                'Seek cardiology consultation immediately',
                'Avoid all physical exertion until reviewed',
                'Take prescribed antiplatelet therapy as directed',
                'Begin cardiac rehabilitation planning',
                'Adopt a low-fat, Mediterranean-style diet',
            ],
        ],
        'Acute Coronary Syndrome' => [
            'risk_level'      => 'Critical Risk',
            'urgency_color'   => 'red',
            'warning_message' => 'Acute coronary syndrome pattern detected — high risk of imminent cardiac event.',
            'recommendation'  => 'Seek emergency medical attention immediately.',
            'actions'         => [
                '🚨 Call emergency services (ambulance) NOW',
                'Sit or lie down — do not exert yourself',
                'Chew a 300mg aspirin if not allergic and available',
                'Unlock your front door for paramedics',
                'Do not eat or drink anything further',
            ],
        ],
        'Early Heart Failure' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'Early indicators of heart failure present across multiple vital parameters.',
            'recommendation'  => 'Urgent cardiology referral. Daily monitoring of fluid status.',
            'actions'         => [
                'Contact a cardiologist urgently',
                'Monitor daily weight — report >2 kg gain in 2 days',
                'Restrict fluid intake as directed by your physician',
                'Review current medication compliance',
                'Avoid NSAIDs (ibuprofen, aspirin for pain relief)',
            ],
        ],
        'Chronic Heart Failure Exacerbation' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'Worsening heart failure indicators detected over the monitoring window.',
            'recommendation'  => 'Contact your cardiologist immediately and review medication compliance.',
            'actions'         => [
                'Call your cardiologist today',
                'Check fluid balance and weight daily',
                'Confirm medication compliance (diuretics, ACE inhibitors)',
                'Reduce sodium and fluid intake',
                'Rest and avoid all exertion until reviewed',
            ],
        ],
        'Pulmonary Hypertension' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'Elevated pulmonary pressure pattern detected affecting oxygen saturation.',
            'recommendation'  => 'Specialist referral required. Oxygen therapy may be needed.',
            'actions'         => [
                'Seek a pulmonologist or cardiologist referral',
                'Avoid high-altitude environments',
                'Avoid strenuous exercise without medical clearance',
                'Monitor oxygen saturation continuously if possible',
            ],
        ],
        'Myocarditis' => [
            'risk_level'      => 'Critical Risk',
            'urgency_color'   => 'red',
            'warning_message' => 'Inflammation of heart muscle is suspected based on vital sign patterns.',
            'recommendation'  => 'Seek immediate medical attention. All physical exertion must stop.',
            'actions'         => [
                '🚨 Go to A&E or call emergency services now',
                'Cease all physical activity immediately',
                'Do not take anti-inflammatory medication without guidance',
                'Report chest pain, shortness of breath, or palpitations',
            ],
        ],
        'Cardiogenic Shock' => [
            'risk_level'      => 'Critical Risk',
            'urgency_color'   => 'red',
            'warning_message' => 'Severe cardiac dysfunction detected across all monitored parameters.',
            'recommendation'  => 'Emergency medical intervention required immediately.',
            'actions'         => [
                '🚨 Call emergency services NOW',
                'Do not stand or walk — remain lying down',
                'Do not take any oral medication',
                'Ensure someone stays with the patient',
            ],
        ],
        'Septic Shock with Cardiac Stress' => [
            'risk_level'      => 'Critical Risk',
            'urgency_color'   => 'red',
            'warning_message' => 'Systemic infection with severe cardiac stress pattern detected.',
            'recommendation'  => 'Emergency medical intervention required immediately.',
            'actions'         => [
                '🚨 Call emergency services immediately',
                'Do not administer antibiotics without medical direction',
                'Maintain warmth and rest',
                'Monitor consciousness level continuously',
            ],
        ],
        'Severe Infection with Cardiac Load' => [
            'risk_level'      => 'Critical Risk',
            'urgency_color'   => 'red',
            'warning_message' => 'Severe infection placing significant load on cardiac function.',
            'recommendation'  => 'Urgent hospitalisation recommended.',
            'actions'         => [
                'Go to A&E or call an ambulance',
                'Avoid oral food or drink until assessed',
                'Bring a list of current medications',
                'Do not attempt to drive yourself',
            ],
        ],
        'Chronic Obstructive Pulmonary Disease Impact' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'COPD-related oxygen and cardiac stress pattern detected.',
            'recommendation'  => 'Pulmonologist consultation and oxygen therapy review required.',
            'actions'         => [
                'Contact your pulmonologist or GP urgently',
                'Review current inhaler usage and compliance',
                'Consider supplemental oxygen therapy',
                'Avoid exposure to smoke, dust, and cold air',
                'Monitor SpO₂ every 4 hours',
            ],
        ],
        'Pericarditis' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'Inflammation of the pericardium (heart lining) pattern detected.',
            'recommendation'  => 'Medical evaluation required promptly.',
            'actions'         => [
                'See a cardiologist or GP within 24 hours',
                'Avoid strenuous physical activity',
                'NSAIDs (e.g. ibuprofen) may be prescribed — follow medical guidance only',
                'Report worsening chest pain immediately',
            ],
        ],
        'Mild Hypothermia' => [
            'risk_level'      => 'Moderate Risk',
            'urgency_color'   => 'amber',
            'warning_message' => 'Body temperature is below the normal range, consistent with mild hypothermia.',
            'recommendation'  => 'Warm the patient gently and monitor core temperature.',
            'actions'         => [
                'Move to a warm environment immediately',
                'Apply warm (not hot) blankets',
                'Give warm non-alcoholic fluids if conscious',
                'Monitor temperature every 15 minutes',
                'Seek medical attention if temperature does not rise',
            ],
        ],
        'Moderate Hypothermia' => [
            'risk_level'      => 'High Risk',
            'urgency_color'   => 'rose',
            'warning_message' => 'Significant drop in core body temperature detected — cardiac arrhythmia risk elevated.',
            'recommendation'  => 'Seek medical assistance immediately.',
            'actions'         => [
                'Call emergency services',
                'Do not rub or massage the patient',
                'Apply warm blankets to core (avoid limbs first)',
                'Avoid giving fluids if shivering is absent',
                'Keep patient horizontal to prevent cardiac shock',
            ],
        ],
        'Simple Viral Infection' => [
            'risk_level'      => 'Low Risk',
            'urgency_color'   => 'blue',
            'warning_message' => 'Mild elevation in temperature and heart rate consistent with a viral illness.',
            'recommendation'  => 'Rest, hydration, and symptom management are the priority.',
            'actions'         => [
                'Rest adequately and increase fluid intake',
                'Take paracetamol for fever management if needed',
                'Avoid strenuous activity during illness',
                'Consult a GP if symptoms worsen after 5 days',
            ],
        ],
        'Mild Respiratory Distress' => [
            'risk_level'      => 'Moderate Risk',
            'urgency_color'   => 'amber',
            'warning_message' => 'Mild respiratory compromise is affecting oxygen saturation levels.',
            'recommendation'  => 'Medical review recommended. Monitor SpO₂ closely.',
            'actions'         => [
                'See a GP or respiratory physician within 24 hours',
                'Avoid dusty, smoky, or cold environments',
                'Use prescribed inhalers if available',
                'Call 999/112 if SpO₂ drops below 90%',
            ],
        ],
    ];

    $display = $map[$label] ?? [
        'risk_level'      => 'Moderate Risk',
        'urgency_color'   => 'amber',
        'warning_message' => 'An unusual physiological pattern has been detected.',
        'recommendation'  => 'Manual clinical review is recommended.',
        'actions'         => ['Consult a healthcare professional for further evaluation.'],
    ];
    $display['condition_name'] = $label;
    return $display;
}

// ===== PROCESS READINGS & RUN TFLITE MODEL =====
if (count($readings) > 0) {
    $count     = count($readings);
    $total_hr  = $total_spo2 = $total_temp = 0;

    foreach ($readings as $row) {
        $total_hr   += $row['heart_rate'];
        $total_spo2 += $row['spo2'];
        $total_temp += $row['temperature'];
        $max_hr      = max($max_hr,  $row['heart_rate']);
        $min_spo2    = min($min_spo2, $row['spo2']);
        $max_temp    = max($max_temp, $row['temperature']);
        if ($row['fall_detected'] == 1) $fall_count++;
        if ($row['heart_rate']  > 100)  $high_hr_count++;
        if ($row['spo2']        <  90)  $low_spo2_count++;
        if ($row['temperature'] >  34)  $high_temp_count++;
    }

    $avg_hr   = round($total_hr   / $count, 1);
    $avg_spo2 = round($total_spo2 / $count, 1);
    $avg_temp = round($total_temp / $count, 1);

    // --- Call Render TFLite API ---
$api_url = "https://lifematrix-api.onrender.com/predict";
$payload = json_encode(["features" => [
    $avg_hr, $max_hr, $avg_spo2, $min_spo2,
    $avg_temp, $max_temp, $fall_count,
    $high_hr_count, $low_spo2_count, $high_temp_count
]]);

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$raw = curl_exec($ch);
curl_close($ch);

    $result = json_decode($raw, true);

    if ($result && empty($result['error'])) {
        $prediction       = labelToDisplay($result['top_label']);
        $model_confidence = $result['confidence'];
        $probabilities    = $result['probabilities'];
        // Sort descending for display
        arsort($probabilities);
    } else {
        $tflite_error = $result['error'] ?? 'Unknown error from TFLite bridge.';
    }

    $stability_index = round(max(20, 100 - ($fall_count * 10 + $high_hr_count * 2)));
}

// ===== COLOUR HELPERS =====
function riskBadgeClass(string $level): string {
    return match($level) {
        'Low Risk'      => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'Moderate Risk' => 'bg-amber-50   text-amber-700   border-amber-200',
        'High Risk'     => 'bg-rose-50    text-rose-700    border-rose-200',
        'Critical Risk' => 'bg-red-100    text-red-800     border-red-300',
        default         => 'bg-slate-50   text-slate-600   border-slate-200',
    };
}

function probBarColor(float $pct): string {
    if ($pct >= 60) return 'bg-rose-500';
    if ($pct >= 30) return 'bg-amber-400';
    if ($pct >= 10) return 'bg-blue-400';
    return 'bg-slate-300';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeMatrix | AI Prediction</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .prob-bar { transition: width 0.8s cubic-bezier(.4,0,.2,1); }
    </style>
</head>
<body class="bg-slate-50 text-slate-600 flex overflow-hidden h-screen">

<!-- ===== SIDEBAR ===== -->
<aside class="w-64 bg-slate-900 text-slate-300 flex flex-col shrink-0">
    <div class="p-6 flex items-center gap-3 border-b border-slate-800">
        <div class="bg-blue-500 p-2 rounded-lg"><i class="fa fa-heartbeat text-white"></i></div>
        <span class="font-extrabold text-xl text-white tracking-tight">LifeMatrix</span>
    </div>
    <nav class="flex-1 p-4 space-y-2 mt-4">
        <a href="dash.php"   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors"><i class="fa fa-th-large opacity-60"></i><span class="font-medium">Dashboard</span></a>
        <a href="dash.php"   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors"><i class="fa fa-history opacity-60"></i><span class="font-medium">Vitals History</span></a>
        <a href="#"          class="bg-slate-800 text-white flex items-center gap-3 px-4 py-3 rounded-xl"><i class="fa fa-brain text-blue-400"></i><span class="font-medium">AI Intelligence</span></a>
        <a href="dash.php"   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors"><i class="fa fa-user opacity-60"></i><span class="font-medium">Profile</span></a>
    </nav>
    <div class="p-6 border-t border-slate-800">
        <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-rose-400 transition-colors"><i class="fa fa-sign-out-alt"></i><span class="font-medium">Sign Out</span></a>
    </div>
</aside>

<!-- ===== MAIN ===== -->
<main class="flex-1 flex flex-col overflow-y-auto">

    <!-- Header -->
    <header class="h-20 bg-white border-b border-slate-200 px-8 flex items-center justify-between shrink-0">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Diagnostic Matrix</h1>
            <p class="text-xs text-slate-400 font-medium tracking-wide">Last Updated: <?php echo date('H:i:s'); ?> &nbsp;|&nbsp; TensorFlow Lite Neural Network</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-slate-800 italic">PATIENT: LM-88291</p>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Premium Clinical Tier</p>
            </div>
        </div>
    </header>

    <div class="p-8 max-w-6xl mx-auto w-full space-y-8">

        <?php if ($tflite_error): ?>
        <!-- TFLite Error Banner -->
        <div class="bg-red-50 border border-red-200 p-5 rounded-2xl flex items-start gap-4">
            <i class="fa fa-exclamation-triangle text-red-500 mt-0.5"></i>
            <div>
                <p class="font-bold text-red-700 text-sm">TFLite Inference Error</p>
                <p class="text-red-600 text-xs mt-1 font-mono"><?php echo htmlspecialchars($tflite_error); ?></p>
                <p class="text-red-500 text-xs mt-2">Ensure <code>predict.py</code>, <code>patient_risk_model.tflite</code>, <code>patient_scaler.save</code>, and <code>class_names.json</code> are in the same directory as this file.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== PRIMARY RESULT CARD ===== -->
        <div class="bg-white p-8 rounded-[32px] border border-slate-100 shadow-sm relative overflow-hidden hover:shadow-xl transition-all duration-500">
            <div class="absolute top-0 right-0 p-8 text-slate-50 opacity-20">
                <i class="fa fa-brain text-9xl"></i>
            </div>
            <div class="relative z-10">
                <div class="flex flex-wrap items-center gap-3 mb-6">
                    <span class="px-4 py-1.5 rounded-full border text-[11px] font-extrabold tracking-widest uppercase <?php echo riskBadgeClass($prediction['risk_level']); ?>">
                        <?php echo $prediction['risk_level']; ?>
                    </span>
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em]">7-Day Analysis Window &nbsp;·&nbsp; TFLite Neural Network</span>
                </div>

                <h2 class="text-4xl lg:text-5xl font-extrabold text-slate-900 mb-4 tracking-tight">
                    <?php echo htmlspecialchars($prediction['condition_name']); ?>
                </h2>
                <p class="text-lg text-slate-500 leading-relaxed max-w-3xl mb-8">
                    <?php echo htmlspecialchars($prediction['warning_message']); ?>
                </p>

                <!-- Recommendation box -->
                <div class="bg-slate-50 border border-slate-100 rounded-3xl p-6 flex items-start gap-4 mb-6">
                    <div class="bg-blue-500 p-3 rounded-2xl shadow-lg shadow-blue-500/20">
                        <i class="fa fa-stethoscope text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-slate-800 mb-2 uppercase tracking-wide">Clinical Recommendation</h4>
                        <p class="text-slate-500 font-medium leading-relaxed mb-4"><?php echo htmlspecialchars($prediction['recommendation']); ?></p>

                        <?php if (!empty($prediction['actions'])): ?>
                        <ul class="space-y-2">
                            <?php foreach ($prediction['actions'] as $action): ?>
                            <li class="flex items-start gap-2 text-sm text-slate-600">
                                <i class="fa fa-check-circle text-blue-400 mt-0.5 shrink-0"></i>
                                <span><?php echo htmlspecialchars($action); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== STATS ROW ===== -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Confidence -->
            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-4">Neural Network Confidence</p>
                <div class="text-4xl font-bold text-slate-800 mb-4"><?php echo $model_confidence; ?><span class="text-xl text-slate-300 ml-1">%</span></div>
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="bg-blue-500 h-full rounded-full prob-bar" style="width:<?php echo $model_confidence; ?>%"></div>
                </div>
                <p class="text-[10px] text-slate-400 mt-2">Softmax probability of top class</p>
            </div>
            <!-- Stability -->
            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-4">Patient Stability Index</p>
                <div class="text-4xl font-bold text-emerald-500 mb-4"><?php echo $stability_index; ?><span class="text-xl text-slate-300 ml-1">/100</span></div>
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="bg-emerald-500 h-full rounded-full prob-bar" style="width:<?php echo $stability_index; ?>%"></div>
                </div>
                <p class="text-[10px] text-slate-400 mt-2">Based on falls & tachycardia events</p>
            </div>
            <!-- Data window -->
            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-4">Readings Analysed</p>
                <div class="text-4xl font-bold text-slate-800 mb-4"><?php echo count($readings); ?><span class="text-xl text-slate-300 ml-1">pts</span></div>
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="bg-slate-400 h-full rounded-full" style="width:<?php echo min(100, count($readings)); ?>%"></div>
                </div>
                <p class="text-[10px] text-slate-400 mt-2">Sensor readings over last 7 days</p>
            </div>
        </div>

        <!-- ===== PROBABILITY DISTRIBUTION + METRICS ===== -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Probability bars -->
            <?php if (!empty($probabilities)): ?>
            <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm lg:col-span-2">
                <h3 class="font-bold text-slate-800 mb-2 flex items-center gap-2">
                    <i class="fa fa-chart-bar text-blue-500"></i> Condition Probability Distribution
                </h3>
                <p class="text-xs text-slate-400 mb-6">Full softmax output from TFLite model — all <?php echo count($probabilities); ?> cardiovascular classes</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-4">
                    <?php foreach ($probabilities as $label => $pct): ?>
                    <?php $isTop = ($label === $prediction['condition_name']); ?>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-xs font-medium <?php echo $isTop ? 'text-slate-900 font-bold' : 'text-slate-500'; ?> truncate max-w-[75%]">
                                <?php if ($isTop): ?><i class="fa fa-caret-right text-blue-500 mr-1"></i><?php endif; ?>
                                <?php echo htmlspecialchars($label); ?>
                            </span>
                            <span class="text-xs font-bold <?php echo $pct >= 30 ? 'text-rose-600' : 'text-slate-400'; ?> ml-2 shrink-0"><?php echo $pct; ?>%</span>
                        </div>
                        <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full prob-bar <?php echo $isTop ? 'bg-blue-500' : probBarColor($pct); ?>"
                                 style="width:<?php echo min(100, $pct); ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Vital metrics -->
            <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm">
                <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <i class="fa fa-chart-line text-blue-500"></i> Calculated Feature Inputs
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between py-3 border-b border-slate-50">
                        <span class="text-sm font-medium text-slate-500">Average Heart Rate</span>
                        <span class="font-bold text-slate-800"><?php echo $avg_hr; ?> BPM</span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-slate-50">
                        <span class="text-sm font-medium text-slate-500">Peak Heart Rate</span>
                        <span class="font-bold text-rose-500"><?php echo $max_hr; ?> BPM</span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-slate-50">
                        <span class="text-sm font-medium text-slate-500">Average SpO₂</span>
                        <span class="font-bold text-slate-800"><?php echo $avg_spo2; ?> %</span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-slate-50">
                        <span class="text-sm font-medium text-slate-500">Minimum SpO₂</span>
                        <span class="font-bold text-slate-800"><?php echo $min_spo2; ?> %</span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-slate-50">
                        <span class="text-sm font-medium text-slate-500">Average Temperature</span>
                        <span class="font-bold text-slate-800"><?php echo $avg_temp; ?> °C</span>
                    </div>
                    <div class="flex justify-between py-3 border-b border-slate-50">
                        <span class="text-sm font-medium text-slate-500">Peak Temperature</span>
                        <span class="font-bold text-slate-800"><?php echo $max_temp; ?> °C</span>
                    </div>
                    <div class="flex justify-between py-3">
                        <span class="text-sm font-medium text-slate-500">Collision Events</span>
                        <span class="font-bold <?php echo $fall_count > 0 ? 'text-rose-500' : 'text-emerald-500'; ?>"><?php echo $fall_count; ?> events</span>
                    </div>
                </div>
            </div>

            <!-- Signal alerts -->
            <div class="bg-slate-900 p-8 rounded-3xl shadow-xl border border-slate-800">
                <h3 class="font-bold text-white mb-6 uppercase tracking-widest text-xs opacity-60">Contributing Signal Alerts</h3>
                <div class="space-y-4">
                    <?php if ($high_hr_count > 0): ?>
                    <div class="flex gap-4 p-4 bg-slate-800/50 rounded-2xl border border-slate-700/50">
                        <div class="w-1.5 shrink-0 bg-rose-500 rounded-full min-h-[40px]"></div>
                        <div>
                            <p class="text-sm font-bold text-white"><?php echo $high_hr_count; ?> Tachycardia Events</p>
                            <p class="text-xs text-slate-400 mt-1">HR exceeded 100 BPM threshold</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($low_spo2_count > 0): ?>
                    <div class="flex gap-4 p-4 bg-slate-800/50 rounded-2xl border border-slate-700/50">
                        <div class="w-1.5 shrink-0 bg-blue-500 rounded-full min-h-[40px]"></div>
                        <div>
                            <p class="text-sm font-bold text-white"><?php echo $low_spo2_count; ?> Hypoxia Episodes</p>
                            <p class="text-xs text-slate-400 mt-1">SpO₂ dropped below 90%</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($high_temp_count > 0): ?>
                    <div class="flex gap-4 p-4 bg-slate-800/50 rounded-2xl border border-slate-700/50">
                        <div class="w-1.5 shrink-0 bg-amber-400 rounded-full min-h-[40px]"></div>
                        <div>
                            <p class="text-sm font-bold text-white"><?php echo $high_temp_count; ?> Fever Events</p>
                            <p class="text-xs text-slate-400 mt-1">Temperature exceeded 34°C threshold</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($fall_count > 0): ?>
                    <div class="flex gap-4 p-4 bg-rose-900 border border-rose-800 rounded-2xl">
                        <div class="w-1.5 shrink-0 bg-white rounded-full min-h-[40px]"></div>
                        <div>
                            <p class="text-sm font-bold text-white"><?php echo $fall_count; ?> Rapid Kinetic Events</p>
                            <p class="text-xs text-rose-200 mt-1">Collision sensor triggered</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($high_hr_count == 0 && $low_spo2_count == 0 && $fall_count == 0 && $high_temp_count == 0): ?>
                    <p class="text-slate-500 italic text-sm">No abnormal signal clusters identified in this window.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Disclaimer -->
        <div class="bg-blue-50 border border-blue-100 p-6 rounded-2xl text-center">
            <p class="text-[10px] text-blue-400 font-bold uppercase tracking-[0.2em] mb-1">Clinical Disclaimer</p>
            <p class="text-xs text-blue-600 font-medium leading-relaxed px-6">
                This prediction is generated by a TensorFlow Lite neural network trained on labelled physiological data and should
                <strong>NOT</strong> be used as a primary clinical diagnosis. Confidence values represent model probability estimates only.
                Always consult a qualified medical professional for confirmation and treatment decisions.
            </p>
        </div>

    </div><!-- /container -->
</main>

</body>
</html>