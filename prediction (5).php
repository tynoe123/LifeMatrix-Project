<?php
// prediction.php
session_start();

// ===== DATABASE CONNECTION =====
$host = "localhost";
$dbname = "freelanceelectro_data";
$username = "freelanceelectro_data";
$password = "Leeroyku2";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ===== FETCH LAST 7 DAYS OF READINGS =====
// ================== CHANGE: hr_fresh = 1 filter added ==================
$sql = "
    SELECT heart_rate, spo2, temperature, fall_detected, created_at
    FROM patient_readings
    WHERE created_at >= NOW() - INTERVAL 7 DAY
      AND heart_rate >= 45
      AND spo2 >= 85
      AND temperature >= 32
      AND hr_fresh = 1
    ORDER BY created_at ASC
";
// =======================================================================

$result = $conn->query($sql);

$readings = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $readings[] = $row;
    }
}

// ===== DEFAULT VALUES =====
$avg_hr = 0; $max_hr = 0; $avg_spo2 = 0; $min_spo2 = 0; $avg_temp = 0; $max_temp = 0; $fall_count = 0;
$high_hr_count = 0; $low_spo2_count = 0; $high_temp_count = 0; $risk_score = 0;
$model_confidence = 0; $stability_index = 0; $signal_correlation = "Insufficient Data"; $pattern_severity = "Undetermined";

$prediction = [
    'condition_name' => 'No Predictive Insight Available',
    'risk_level' => 'Unknown',
    'warning_message' => 'Not enough recent physiological data to generate an insight.',
    'recommendation' => 'Continue monitoring to improve prediction reliability.'
];

function loadTrainingData(string $csvPath): array {
    $data = [];
    if (!file_exists($csvPath)) return $data;
    $handle = fopen($csvPath, 'r');
    fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 11) continue;
        $data[] = ['features' => array_map('floatval', array_slice($row, 0, 10)), 'label' => trim($row[10])];
    }
    fclose($handle);
    return $data;
}

function normalise(array $features, array $ranges): array {
    $norm = [];
    foreach ($features as $i => $val) {
        $range = $ranges['maxs'][$i] - $ranges['mins'][$i];
        $norm[] = $range > 0 ? ($val - $ranges['mins'][$i]) / $range : 0.0;
    }
    return $norm;
}

function knnPredict(array $queryFeatures, array $trainingData, int $k = 5): array {
    if (empty($trainingData)) return ['label' => 'Unknown', 'confidence' => 0];
    $mins = array_fill(0, 10, PHP_FLOAT_MAX); $maxs = array_fill(0, 10, -PHP_FLOAT_MAX);
    foreach ($trainingData as $s) {
        foreach ($s['features'] as $i => $v) { $mins[$i] = min($mins[$i], $v); $maxs[$i] = max($maxs[$i], $v); }
    }
    $normQuery = normalise($queryFeatures, ['mins'=>$mins, 'maxs'=>$maxs]);
    $distances = [];
    foreach ($trainingData as $idx => $sample) {
        $normS = normalise($sample['features'], ['mins'=>$mins, 'maxs'=>$maxs]);
        $sum = 0; foreach ($normQuery as $i => $v) $sum += ($v - $normS[$i]) ** 2;
        $distances[$idx] = sqrt($sum);
    }
    asort($distances); $topK = array_slice($distances, 0, $k, true);
    $votes = []; $totalWeight = 0;
    foreach ($topK as $idx => $dist) {
        $label = $trainingData[$idx]['label']; $weight = $dist > 0 ? 1.0 / $dist : 100.0;
        $votes[$label] = ($votes[$label] ?? 0) + $weight; $totalWeight += $weight;
    }
    arsort($votes); $winner = array_key_first($votes);
    return ['label' => $winner, 'confidence' => $totalWeight > 0 ? round(($votes[$winner] / $totalWeight) * 100) : 0];
}

function labelToDisplay(string $label): array {
    $map = [
        'Normal Healthy' => [
            'risk_level' => 'Low Risk',
            'warning_message' => 'Vitals remain within healthy reference ranges.',
            'recommendation' => 'Maintain current routine. Schedule a routine check-up annually.'
        ],
        'Generally Stable' => [
            'risk_level' => 'Low Risk',
            'warning_message' => 'Vital signs are within acceptable ranges with minor fluctuations.',
            'recommendation' => 'Continue regular monitoring. No immediate action required.'
        ],
        'Resting Athlete Condition' => [
            'risk_level' => 'Low Risk',
            'warning_message' => 'Low resting heart rate consistent with high fitness levels.',
            'recommendation' => 'No concern. Maintain hydration and routine health checks.'
        ],
        'Borderline Tachycardia' => [
            'risk_level' => 'Moderate Risk',
            'warning_message' => 'Heart rate is slightly elevated above normal range.',
            'recommendation' => 'Monitor closely. Reduce caffeine and stress. Revisit if persistent.'
        ],
        'Post-Exercise Elevated HR' => [
            'risk_level' => 'Low Risk',
            'warning_message' => 'Elevated heart rate likely attributed to recent physical activity.',
            'recommendation' => 'Allow adequate recovery time and re-measure at rest.'
        ],
        'Bradycardia' => [
            'risk_level' => 'Moderate Risk',
            'warning_message' => 'Heart rate is consistently below normal resting threshold.',
            'recommendation' => 'Consult a cardiologist. Avoid medications that lower heart rate without advice.'
        ],
        'Hypertension Stage 1 (Compensated)' => [
            'risk_level' => 'Moderate Risk',
            'warning_message' => 'Early stage hypertension detected. Currently compensated.',
            'recommendation' => 'Reduce sodium intake, increase physical activity, monitor blood pressure daily.'
        ],
        'Hypertensive Crisis Risk' => [
            'risk_level' => 'High Risk',
            'warning_message' => 'Blood pressure and cardiac indicators suggest hypertensive crisis risk.',
            'recommendation' => 'Seek urgent medical review. Avoid strenuous activity.'
        ],
        'Atrial Fibrillation Pattern' => [
            'risk_level' => 'High Risk',
            'warning_message' => 'Irregular heart rate pattern consistent with atrial fibrillation.',
            'recommendation' => 'Urgent cardiology referral required. Avoid stimulants.'
        ],
        'Supraventricular Tachycardia' => [
            'risk_level' => 'High Risk',
            'warning_message' => 'Rapid abnormal heart rhythm originating above the ventricles detected.',
            'recommendation' => 'Seek medical evaluation promptly. Avoid triggers like caffeine and stress.'
        ],
        'Angina (Stable)' => [
            'risk_level' => 'Moderate Risk',
            'warning_message' => 'Pattern consistent with stable angina. Chest discomfort may be present.',
            'recommendation' => 'Consult cardiologist. Avoid overexertion and monitor symptoms.'
        ],
        'Ischemic Heart Disease' => [
            'risk_level' => 'High Risk',
            'warning_message' => 'Indicators suggest reduced blood flow to the heart muscle.',
            'recommendation' => 'Immediate cardiology consultation required. Lifestyle changes essential.'
        ],
        'Acute Coronary Syndrome' => [
            'risk_level' => 'Critical Risk',
            'warning_message' => 'Acute coronary syndrome pattern detected. High cardiac event risk.',
            'recommendation' => 'Seek emergency medical attention immediately.'
        ],
        'Early Heart Failure' => [
            'risk_level' => 'High Risk',
            'warning_message' => 'Early indicators of heart failure present across multiple vitals.',
            'recommendation' => 'Urgent cardiology referral. Monitor fluid retention and weight daily.'
        ],
        'Chronic Heart Failure Exacerbation' => [
            'risk_level' => 'High Risk',
            'warning_message' => 'Worsening heart failure indicators detected over monitoring period.',
            'recommendation' => 'Contact your cardiologist immediately. Review medication compliance.'
        ],
        'Pulmonary Hypertension' => [
            'risk_level' => 'High Risk',
            'warning_message' => 'Elevated pulmonary pressure pattern detected affecting oxygen levels.',
            'recommendation' => 'Specialist referral required. Avoid high altitude and strenuous exercise.'
        ],
        'Myocarditis' => [
            'risk_level' => 'Critical Risk',
            'warning_message' => 'Inflammation of heart muscle suspected based on vital patterns.',
            'recommendation' => 'Seek immediate medical attention. Avoid all physical exertion.'
        ],
        'Cardiogenic Shock' => [
            'risk_level' => 'Critical Risk',
            'warning_message' => 'Severe cardiac dysfunction detected across all monitored parameters.',
            'recommendation' => 'Seek immediate emergency medical attention.'
        ],
        'Septic Shock with Cardiac Stress' => [
            'risk_level' => 'Critical Risk',
            'warning_message' => 'Systemic infection with severe cardiac stress pattern detected.',
            'recommendation' => 'Emergency medical intervention required immediately.'
        ],
        'Severe Infection with Cardiac Load' => [
            'risk_level' => 'Critical Risk',
            'warning_message' => 'Severe infection placing significant load on cardiac function.',
            'recommendation' => 'Urgent hospitalisation recommended.'
        ],
        'Chronic Obstructive Pulmonary Disease Impact' => [
            'risk_level' => 'High Risk',
            'warning_message' => 'COPD-related oxygen and cardiac stress pattern detected.',
            'recommendation' => 'Pulmonologist consultation required. Review inhaler usage and oxygen therapy.'
        ],
    ];

    $display = $map[$label] ?? [
        'risk_level' => 'Moderate Risk',
        'warning_message' => 'An unusual physiological pattern has been detected.',
        'recommendation' => 'Manual clinical review is recommended.'
    ];
    $display['condition_name'] = $label;
    return $display;
}

// Processing
if (count($readings) > 0) {
    $count = count($readings); $total_hr = 0; $total_spo2 = 0; $total_temp = 0; $max_hr = 0; $min_spo2 = 100; $max_temp = 0;
    foreach ($readings as $row) {
        $total_hr += $row['heart_rate']; $total_spo2 += $row['spo2']; $total_temp += $row['temperature'];
        $max_hr = max($max_hr, $row['heart_rate']); $min_spo2 = min($min_spo2, $row['spo2']); $max_temp = max($max_temp, $row['temperature']);
        if ($row['fall_detected'] == 1) $fall_count++;
        if ($row['heart_rate'] > 100) $high_hr_count++;
        if ($row['spo2'] < 90) $low_spo2_count++;
        if ($row['temperature'] > 34) $high_temp_count++;
    }
    $avg_hr = round($total_hr/$count, 1); $avg_spo2 = round($total_spo2/$count, 1); $avg_temp = round($total_temp/$count, 1);

    $trainingData = loadTrainingData(__DIR__ . '/patient_training_data.csv');
    if (!empty($trainingData)) {
        $knnRes = knnPredict([$avg_hr, $max_hr, $avg_spo2, $min_spo2, $avg_temp, $max_temp, $fall_count, $high_hr_count, $low_spo2_count, $high_temp_count], $trainingData);
        $prediction = labelToDisplay($knnRes['label']);
        $model_confidence = $knnRes['confidence'];
    }
    $stability_index = round(max(20, 100 - ($fall_count * 10 + $high_hr_count * 2)));
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
    </style>
</head>
<body class="bg-slate-50 text-slate-600 flex overflow-hidden h-screen">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-slate-800">
            <div class="bg-blue-500 p-2 rounded-lg"><i class="fa fa-heartbeat text-white"></i></div>
            <span class="font-extrabold text-xl text-white tracking-tight">LifeMatrix</span>
        </div>
        <nav class="flex-1 p-4 space-y-2 mt-4">
            <a href="dash.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors"><i class="fa fa-th-large opacity-60"></i><span class="font-medium">Dashboard</span></a>
            <a href="dash.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors"><i class="fa fa-history opacity-60"></i><span class="font-medium">Vitals History</span></a>
            <a href="#" class="bg-slate-800 text-white flex items-center gap-3 px-4 py-3 rounded-xl"><i class="fa fa-brain text-blue-400"></i><span class="font-medium">AI Intelligence</span></a>
            <a href="dash.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors"><i class="fa fa-user opacity-60"></i><span class="font-medium">Profile</span></a>
        </nav>
        <div class="p-6 border-t border-slate-800">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-rose-400 transition-colors"><i class="fa fa-sign-out-alt"></i><span class="font-medium">Sign Out</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto">

        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 px-8 flex items-center justify-between shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Diagnostic Matrix</h1>
                <p class="text-xs text-slate-400 font-medium tracking-wide">Last Updated: <?php echo date('H:i:s'); ?></p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-xs font-bold text-slate-800 italic">PATIENT: LM-88291</p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Premium Clinical Tier</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-slate-100 border-2 border-white shadow-sm flex items-center justify-center font-bold text-slate-500">JD</div>
            </div>
        </header>

        <!-- Prediction Body -->
        <div class="p-8 max-w-5xl mx-auto w-full space-y-8">

            <!-- Main Result Card -->
            <div class="bg-white p-8 rounded-[32px] border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-xl transition-all duration-500">
                <div class="absolute top-0 right-0 p-8 text-slate-50 opacity-20"><i class="fa fa-microchip text-8xl"></i></div>

                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-6">
                        <?php
                            $badgeColor = "bg-emerald-50 text-emerald-600 border-emerald-100";
                            if($prediction['risk_level'] == 'High Risk')     $badgeColor = "bg-rose-50 text-rose-600 border-rose-100";
                            if($prediction['risk_level'] == 'Moderate Risk') $badgeColor = "bg-amber-50 text-amber-600 border-amber-100";
                            if($prediction['risk_level'] == 'Critical Risk') $badgeColor = "bg-rose-100 text-rose-700 border-rose-200";
                        ?>
                        <span class="px-4 py-1.5 rounded-full border text-[11px] font-extrabold tracking-widest uppercase <?php echo $badgeColor; ?>">
                            <?php echo $prediction['risk_level']; ?>
                        </span>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em] ml-2">7-Day Analysis Window</span>
                    </div>

                    <h2 class="text-4xl lg:text-5xl font-extrabold text-slate-900 mb-4 tracking-tight">
                        <?php echo $prediction['condition_name']; ?>
                    </h2>
                    <p class="text-lg text-slate-500 leading-relaxed max-w-3xl mb-8">
                        <?php echo $prediction['warning_message']; ?>
                    </p>

                    <div class="bg-slate-50 border border-slate-100 rounded-3xl p-6 flex items-start gap-4">
                        <div class="bg-blue-500 p-3 rounded-2xl shadow-lg shadow-blue-500/20">
                            <i class="fa fa-stethoscope text-white"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-800 mb-1 leading-none uppercase tracking-wide">Recommendation</h4>
                            <p class="text-slate-500 font-medium leading-relaxed"><?php echo $prediction['recommendation']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Confidence -->
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-4">Signal Confidence</p>
                    <div class="text-4xl font-bold text-slate-800 mb-4"><?php echo $model_confidence; ?><span class="text-xl text-slate-300 ml-1">%</span></div>
                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="bg-blue-500 h-full rounded-full" style="width: <?php echo $model_confidence; ?>%"></div>
                    </div>
                </div>
                <!-- Stability -->
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-4">Stability Index</p>
                    <div class="text-4xl font-bold text-emerald-500 mb-4"><?php echo $stability_index; ?><span class="text-xl text-slate-300 ml-1">/100</span></div>
                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="bg-emerald-500 h-full rounded-full" style="width: <?php echo $stability_index; ?>%"></div>
                    </div>
                </div>
                <!-- Correlation -->
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-4">Trend Correlator</p>
                    <div class="text-xl font-bold text-slate-800 mt-4 leading-none"><?php echo explode(' ', $signal_correlation)[0]; ?> Alignment</div>
                    <p class="text-xs text-slate-400 mt-2 font-medium italic">Multi-sensor validation active</p>
                </div>
            </div>

            <!-- Detailed Metrics & Contributing Factors -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Metrics Table -->
                <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm">
                    <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i class="fa fa-chart-line text-blue-500"></i> Calculated Features
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
                            <span class="text-sm font-medium text-slate-500">Minimum Oxygen</span>
                            <span class="font-bold text-slate-800"><?php echo $min_spo2; ?> %</span>
                        </div>
                        <div class="flex justify-between py-3 border-b border-slate-50">
                            <span class="text-sm font-medium text-slate-500">Highest Temperature</span>
                            <span class="font-bold text-slate-800"><?php echo $max_temp; ?>&deg;C</span>
                        </div>
                        <div class="flex justify-between py-3">
                            <span class="text-sm font-medium text-slate-500">Collision Detection</span>
                            <span class="font-bold <?php echo $fall_count > 0 ? 'text-rose-500' : 'text-emerald-500'; ?>"><?php echo $fall_count; ?> Events</span>
                        </div>
                    </div>
                </div>

                <!-- Contributing Signals -->
                <div class="bg-slate-900 p-8 rounded-3xl shadow-xl border border-slate-800">
                    <h3 class="font-bold text-white mb-6 uppercase tracking-widest text-xs opacity-60">Contributing Signal Alerts</h3>
                    <div class="space-y-4">
                        <?php if($high_hr_count > 0): ?>
                            <div class="flex gap-4 p-4 bg-slate-800/50 rounded-2xl border border-slate-700/50">
                                <div class="w-1.5 h-full bg-rose-500 rounded-full"></div>
                                <div>
                                    <p class="text-sm font-bold text-white"><?php echo $high_hr_count; ?> Tachycardia Events</p>
                                    <p class="text-xs text-slate-400 mt-1">HR exceeded 100BPM threshold</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if($low_spo2_count > 0): ?>
                            <div class="flex gap-4 p-4 bg-slate-800/50 rounded-2xl border border-slate-700/50">
                                <div class="w-1.5 h-full bg-blue-500 rounded-full"></div>
                                <div>
                                    <p class="text-sm font-bold text-white"><?php echo $low_spo2_count; ?> Hypoxia Episodes</p>
                                    <p class="text-xs text-slate-400 mt-1">Oxygen saturation below 90%</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if($fall_count > 0): ?>
                            <div class="flex gap-4 p-4 bg-rose-900 border border-rose-800 rounded-2xl">
                                <div class="w-1.5 h-full bg-white rounded-full"></div>
                                <div>
                                    <p class="text-sm font-bold text-white"><?php echo $fall_count; ?> Rapid Kinetic Events</p>
                                    <p class="text-xs text-rose-200 mt-1">Collision sensor triggered</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if($high_hr_count == 0 && $low_spo2_count == 0 && $fall_count == 0): ?>
                            <p class="text-slate-500 italic text-sm">No abnormal signal clusters identified.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Disclaimer footer -->
            <div class="bg-blue-50 border border-blue-100 p-6 rounded-2xl text-center">
                <p class="text-[10px] text-blue-400 font-bold uppercase tracking-[0.2em] mb-1">Clinical Disclaimer</p>
                <p class="text-xs text-blue-600 font-medium leading-relaxed px-6">
                    This A.I. prediction is based on statistical patterns (kNN) and should NOT be used as a primary clinical diagnosis. Always consult with a qualified medical professional for biological confirmation.
                </p>
            </div>

        </div>
    </main>

</body>
</html>