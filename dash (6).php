<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeMatrix - Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .hidden { display: none !important; }
        .sidebar-active { background: #1e293b; color: white !important; }
        .sidebar-active i { color: #3b82f6 !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-600 flex overflow-hidden h-screen">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-slate-900 text-slate-400 flex flex-col shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-slate-800">
            <div class="bg-blue-500 p-2 rounded-lg shadow-lg">
                <i data-lucide="activity" class="w-6 h-6 text-white"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-white tracking-tight leading-none">LifeMatrix</h2>
                <p class="text-[10px] text-blue-400 font-bold uppercase tracking-widest mt-1">v2.4p</p>
            </div>
        </div>

        <nav class="flex-1 p-4 space-y-1 mt-4">
            <button onclick="showPage('dashboard', this)" class="nav-btn sidebar-active w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
            </button>
            <button onclick="showPage('history', this)" class="nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium hover:bg-slate-800 hover:text-slate-100">
                <i data-lucide="history" class="w-5 h-5"></i> Vitals History
            </button>
            <button onclick="showPage('reports', this)" class="nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium hover:bg-slate-800 hover:text-slate-100">
                <i data-lucide="bar-chart-3" class="w-5 h-5"></i> Reports
            </button>
            <button onclick="showPage('personal', this)" class="nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium hover:bg-slate-800 hover:text-slate-100">
                <i data-lucide="user" class="w-5 h-5"></i> Profile
            </button>
            <button onclick="showPage('settings', this)" class="nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium hover:bg-slate-800 hover:text-slate-100">
                <i data-lucide="settings" class="w-5 h-5"></i> Settings
            </button>
        </nav>

        <div class="p-6 border-t border-slate-800">
            <div class="bg-slate-800/50 p-4 rounded-xl mb-6">
                <p class="text-[10px] text-slate-500 uppercase font-bold mb-1 tracking-widest">Plan Status</p>
                <p class="text-sm text-blue-400 font-semibold italic">Premium Member</p>
            </div>
            <button onclick="logout()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-rose-500/10 hover:text-rose-400 transition-all text-sm font-semibold">
                <i data-lucide="log-out" class="w-4 h-4"></i> Sign Out
            </button>
        </div>
    </aside>

    <!-- CONTENT -->
    <main class="flex-1 overflow-y-auto">
        <!-- HEADER -->
        <header class="h-20 sticky top-0 bg-white/80 backdrop-blur-md border-b border-slate-200 px-8 flex justify-between items-center z-40">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight" id="welcomeText">Welcome back</h1>
                <p class="text-xs text-slate-500 font-medium">System Status: <span class="text-emerald-500 font-bold">● Syncing Live</span></p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-xs font-bold text-slate-800 leading-none mb-1">Patient ID</p>
                    <p class="text-[10px] text-slate-400 italic">LM-88291 | O+</p>
                </div>
               
            </div>
        </header>

        <div class="p-8 max-w-[1200px] mx-auto">
            
            <!-- DASHBOARD PAGE -->
            <div id="dashboardPage">
                <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm flex flex-col md:flex-row gap-8 items-center mb-8 relative overflow-hidden group">
                    <div id="riskStrip" class="absolute inset-y-0 left-0 w-2 bg-emerald-500"></div>
                    <div class="w-20 h-20 rounded-2xl flex items-center justify-center bg-slate-50 border border-slate-100 shadow-inner">
                        <i data-lucide="activity" class="w-10 h-10 text-emerald-500" id="statusIcon"></i>
                    </div>
                    <div class="flex-1">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-slate-400 mb-1 inline-block uppercase">Diagnostic Assessment</span>
                        <h1 class="text-3xl font-bold text-slate-800 mb-2" id="healthStatus">Scanning...</h1>
                        <div class="flex gap-3" id="statusNotes"></div>
                    </div>
                    <div class="text-right">
                        <div class="text-5xl font-bold text-slate-800 mb-1" id="riskPercent">--%</div>
                        <p class="text-slate-400 font-bold text-[10px] uppercase tracking-widest">Risk Analytics</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- KPI Cards -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                        <div class="flex justify-between mb-4">
                            <i data-lucide="heart" class="text-rose-500 bg-rose-50 p-2 rounded-lg"></i>
                            <span class="text-[10px] px-2 py-1 bg-emerald-50 text-emerald-600 rounded font-bold uppercase tracking-tight">Normal</span>
                        </div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Heart Rate</p>
                        <p class="text-2xl font-bold text-slate-800" id="hr">-- <small class="text-sm font-medium text-slate-400">BPM</small></p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                        <div class="flex justify-between mb-4">
                            <i data-lucide="wind" class="text-blue-500 bg-blue-50 p-2 rounded-lg"></i>
                            <span class="text-[10px] px-2 py-1 bg-emerald-50 text-emerald-600 rounded font-bold uppercase tracking-tight">Stable</span>
                        </div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Oxygen Saturation</p>
                        <p class="text-2xl font-bold text-slate-800" id="spo2">-- <small class="text-sm font-medium text-slate-400">%</small></p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                        <div class="flex justify-between mb-4">
                            <i data-lucide="thermometer" class="text-amber-500 bg-amber-50 p-2 rounded-lg"></i>
                            <span class="text-[10px] px-2 py-1 bg-emerald-50 text-emerald-600 rounded font-bold uppercase tracking-tight">Normal</span>
                        </div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Body Temp</p>
                        <p class="text-2xl font-bold text-slate-800" id="temp">-- <small class="text-sm font-medium text-slate-400">°C</small></p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                        <div class="flex justify-between mb-4">
                            <i data-lucide="alert-triangle" class="text-gray-400 bg-gray-50 p-2 rounded-lg" id="fallIcon"></i>
                            <span class="text-[10px] px-2 py-1 bg-gray-50 text-gray-500 rounded font-bold uppercase tracking-tight" id="fallBadge">Safe</span>
                        </div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Fall Status</p>
                        <p class="text-2xl font-bold text-slate-800" id="fall">NONE</p>
                    </div>
                </div>

                
            </div>

            <!-- HISTORY PAGE -->
            <div id="historyPage" class="hidden">
                <div class="flex justify-between items-end mb-6">
                    <h2 class="text-2xl font-bold text-slate-800">Clinical History Log</h2>
                </div>
                <div class="bg-white border border-slate-100 rounded-3xl overflow-hidden shadow-sm">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr class="text-[10px] uppercase font-bold text-slate-400 tracking-widest">
                                <th class="px-8 py-5">Timestamp</th>
                                <th class="px-8 py-5">Pulse</th>
                                <th class="px-8 py-5">SpO₂</th>
                                <th class="px-8 py-5">Temp</th>
                                <th class="px-8 py-5">Status</th>
                            </tr>
                        </thead>
                        <tbody id="historyTable" class="divide-y divide-slate-50"></tbody>
                    </table>
                </div>
            </div>

            <!-- REPORTS PAGE -->
            <div id="reportsPage" class="hidden">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white border border-slate-100 p-6 rounded-3xl shadow-sm">
                        <p class="text-[10px] text-slate-400 font-bold uppercase mb-4 tracking-widest">Avg Pulse</p>
                        <div class="text-4xl font-bold text-slate-800" id="avgPulse">-- <span class="text-base text-slate-400 font-medium">BPM</span></div>
                    </div>
                    <div class="bg-white border border-slate-100 p-6 rounded-3xl shadow-sm">
                        <p class="text-[10px] text-slate-400 font-bold uppercase mb-4 tracking-widest">Avg SpO₂</p>
                        <div class="text-4xl font-bold text-slate-800" id="avgSpo2">-- <span class="text-base text-slate-400 font-medium">%</span></div>
                    </div>
                    <div class="bg-white border border-slate-100 p-6 rounded-3xl shadow-sm">
                        <p class="text-[10px] text-slate-400 font-bold uppercase mb-4 tracking-widest">Incident Alerts</p>
                        <div class="text-4xl font-bold text-emerald-600" id="incidentCount">00 <span class="text-base text-slate-400 font-medium">Events</span></div>
                    </div>
                </div>

                <div class="mb-8 overflow-hidden group">
                    <button onclick="window.location.href='prediction.php'" class="w-full bg-slate-900 text-white font-bold py-7 rounded-3xl shadow-xl shadow-slate-900/10 hover:bg-slate-800 hover:-translate-y-1 transition-all flex items-center justify-center gap-5 border border-slate-700 relative">
                        <div class="p-2.5 bg-blue-500 rounded-xl group-hover:scale-110 transition-transform">
                            <i data-lucide="brain-circuit" class="w-7 h-7 text-white"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-[10px] uppercase tracking-[0.3em] font-bold text-blue-400 mb-1 leading-none">A.I. Diagnostic Matrix</p>
                            <span class="text-xl font-bold tracking-tight">Generate LifeMatrix AI Prediction</span>
                        </div>
                        <i data-lucide="chevron-right" class="w-6 h-6 ml-auto mr-4 text-slate-500"></i>
                    </button>
                </div>

                <div class="bg-white border border-slate-100 rounded-3xl p-8 shadow-sm h-[450px]">
                    <h3 class="text-lg font-bold text-slate-800 mb-10">Weekly Physiological Trends</h3>
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>

            <!-- PERSONAL -->
            <div id="personalPage" class="hidden">
    <div class="bg-white border border-slate-100 p-10 rounded-3xl shadow-sm max-w-2xl">
        <h2 class="text-2xl font-bold text-slate-800 mb-8">Personal Profile Record</h2>

        <div class="space-y-6">

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase">Legal Name</label>
                    <input id="full_name" type="text" class="w-full bg-slate-50 border rounded-xl p-3">
                </div>

                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase">Patient ID</label>
                    <input id="patient_id_display" type="text" readonly class="w-full bg-slate-100 border rounded-xl p-3">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <input id="age" type="number" placeholder="Age" class="w-full bg-slate-50 border rounded-xl p-3">
                <input id="weight" type="number" placeholder="Weight (kg)" class="w-full bg-slate-50 border rounded-xl p-3">
                <input id="height" type="number" step="0.01" placeholder="Height (m)" class="w-full bg-slate-50 border rounded-xl p-3">
            </div>

            <textarea id="conditions" placeholder="Underlying Conditions"
            class="w-full bg-slate-50 border rounded-xl p-3"></textarea>

            <div class="text-center">
                <p class="text-xs text-slate-400">BMI</p>
                <p id="bmiDisplay" class="text-2xl font-bold text-blue-600">--</p>
            </div>

            <button id="saveProfileBtn"
            class="bg-slate-900 text-white px-8 py-3.5 rounded-xl font-bold hover:bg-slate-800 w-full">
                Save Profile
            </button>

            <p id="profileMsg" class="text-center text-sm"></p>
        </div>
    </div>
</div>
         <!--SETTINGS-->
 
 <div id="settingsPage" class="hidden">
    <div class="max-w-2xl mx-auto">

        <h2 class="text-2xl font-bold text-slate-800 mb-8">System Settings</h2>

        <!-- MENU -->
        <div class="bg-white border border-slate-100 rounded-3xl shadow-sm overflow-hidden">

            <button onclick="openSetting('support')" class="setting-item w-full flex justify-between items-center px-6 py-5 hover:bg-slate-50 transition">
                <span class="font-medium">Support</span>
                <span>›</span>
            </button>

            <button onclick="openSetting('help')" class="setting-item w-full flex justify-between items-center px-6 py-5 border-t hover:bg-slate-50 transition">
                <span class="font-medium">Help Center</span>
                <span>›</span>
            </button>

            <button onclick="openSetting('terms')" class="setting-item w-full flex justify-between items-center px-6 py-5 border-t hover:bg-slate-50 transition">
                <span class="font-medium">Terms & Conditions</span>
                <span>›</span>
            </button>

            <button onclick="openSetting('privacy')" class="setting-item w-full flex justify-between items-center px-6 py-5 border-t hover:bg-slate-50 transition">
                <span class="font-medium">Privacy Policy</span>
                <span>›</span>
            </button>

            <button onclick="openSetting('about')" class="setting-item w-full flex justify-between items-center px-6 py-5 border-t hover:bg-slate-50 transition">
                <span class="font-medium">About System</span>
                <span>›</span>
            </button>

        </div>

        <!-- CONTENT DISPLAY -->
        <div id="settingsContent" class="mt-6 bg-white border border-slate-100 rounded-3xl p-6 shadow-sm hidden">
            <button onclick="closeSetting()" class="text-sm text-blue-500 font-bold mb-4">← Back</button>
            <div id="settingsDetails" class="text-slate-600 text-sm space-y-2"></div>
        </div>

    </div>
</div>

        </div>
    </main>

    <script>
        lucide.createIcons();
        if(sessionStorage.getItem("loggedIn") !== "true") window.location.href = "index.php";
        document.getElementById("welcomeText").innerText = "Welcome back, " + sessionStorage.getItem("userName");

        function showPage(pageId, btn){
            document.querySelectorAll("main [id$='Page']").forEach(p => p.classList.add("hidden"));
            document.getElementById(pageId + "Page").classList.remove("hidden");
            document.querySelectorAll(".nav-btn").forEach(b => b.classList.remove("sidebar-active"));
            if(btn) btn.classList.add("sidebar-active");
            if(pageId === "history") loadHistory();
            if(pageId === "reports") loadWeeklyData();
        }

        function logout(){ sessionStorage.clear(); window.location.href = "index.php"; }

        // MOCK LIVE UPDATES
       function loadLatest(){
    fetch("api/get_latest.php")
    .then(res => res.json())
    .then(data => {
        document.getElementById("hr").innerHTML = `${data.heart_rate} <small class="text-sm">BPM</small>`;
        document.getElementById("spo2").innerHTML = `${data.spo2} <small class="text-sm">%</small>`;
        document.getElementById("temp").innerHTML = `${data.temperature} <small class="text-sm">°C</small>`;
        document.getElementById("fall").innerText = data.fall_detected == 1 ? "INCIDENT" : "NONE";

        if(data.fall_detected == 1){
            document.getElementById("fallIcon").classList.replace("text-gray-400","text-rose-500");
            document.getElementById("fallBadge").classList.replace("bg-gray-50","bg-rose-50");
            document.getElementById("fallBadge").classList.replace("text-gray-500","text-rose-600");
            document.getElementById("fallBadge").innerText = "CRITICAL";
        } else {
            document.getElementById("fallIcon").classList.replace("text-rose-500","text-gray-400");
            document.getElementById("fallBadge").classList.replace("bg-rose-50","bg-gray-50");
            document.getElementById("fallBadge").classList.replace("text-rose-600","text-gray-500");
            document.getElementById("fallBadge").innerText = "Safe";
        }

        let risk = 0;
        let notes = "";

        // --- FALL ---
        if(data.fall_detected == 1) {
            risk += 40;
            notes += '<span class="px-2 py-1 bg-rose-50 text-rose-600 rounded text-[10px] font-bold">Fall Incident</span>';
        }

        // --- SPO2 (normal: 90-100%) ---
        if(data.spo2 < 90) {
            risk += 25;
            notes += '<span class="px-2 py-1 bg-amber-50 text-amber-600 rounded text-[10px] font-bold">Low Oxygen</span>';
        } else if(data.spo2 > 100) {
            risk += 10;
            notes += '<span class="px-2 py-1 bg-amber-50 text-amber-600 rounded text-[10px] font-bold">SpO2 Reading Error</span>';
        }

        // --- HEART RATE (normal: 60-120 BPM) ---
        if(data.heart_rate < 60) {
            risk += 20;
            notes += '<span class="px-2 py-1 bg-blue-50 text-blue-600 rounded text-[10px] font-bold">Low Heart Rate</span>';
        } else if(data.heart_rate > 120) {
            risk += 20;
            notes += '<span class="px-2 py-1 bg-orange-50 text-orange-600 rounded text-[10px] font-bold">High Heart Rate</span>';
        }

        // --- TEMPERATURE (normal: 32-34°C) ---
        if(data.temperature < 32) {
            risk += 15;
            notes += '<span class="px-2 py-1 bg-blue-50 text-blue-600 rounded text-[10px] font-bold">Hypothermia Risk</span>';
        } else if(data.temperature > 34) {
            risk += 15;
            notes += '<span class="px-2 py-1 bg-rose-50 text-rose-600 rounded text-[10px] font-bold">Elevated Temperature</span>';
        }

        // --- STATUS EVALUATION ---
        const statusEl = document.getElementById("healthStatus");
        const riskPercent = document.getElementById("riskPercent");
        const riskStrip = document.getElementById("riskStrip");

        if(risk >= 50) {
            statusEl.innerText = "CRITICAL";
            statusEl.style.color = "#e11d48";
            riskStrip.className = "absolute inset-y-0 left-0 w-2 bg-rose-500";
        } else if(risk >= 20) {
            statusEl.innerText = "WARNING";
            statusEl.style.color = "#d97706";
            riskStrip.className = "absolute inset-y-0 left-0 w-2 bg-amber-500";
        } else {
            statusEl.innerText = "HEALTHY";
            statusEl.style.color = "#10b981";
            notes = '<span class="px-2 py-1 bg-emerald-50 text-emerald-600 rounded text-[10px] font-bold">All Vitals Normal</span>';
            riskStrip.className = "absolute inset-y-0 left-0 w-2 bg-emerald-500";
        }

        riskPercent.innerText = risk + "%";
        document.getElementById("statusNotes").innerHTML = notes;
    });
}

        function loadHistory(){
            fetch("api/get_history.php").then(res => res.json()).then(data => {
                let html = "";
                data.forEach(row => {
                    html += `<tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-8 py-6 text-sm font-medium">${row.created_at}</td>
                        <td class="px-8 py-6 text-slate-800 font-bold">${row.heart_rate}</td>
                        <td class="px-8 py-6 text-slate-800 font-bold">${row.spo2}</td>
                        <td class="px-8 py-6 text-slate-800 font-bold">${row.temperature}</td>
                        <td class="px-8 py-6"><span class="px-3 py-1 rounded text-[9px] font-bold ${row.fall_detected == 1 ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600'}">${row.fall_detected == 1 ? 'INCIDENT' : 'STABLE'}</span></td>
                    </tr>`;
                });
                document.getElementById("historyTable").innerHTML = html;
            });
        }

        let weeklyChart = null;
        function loadWeeklyData(){
            fetch("api/get_weekly.php").then(res => res.json()).then(data => {
                const totalHR = data.reduce((a,b) => a + parseFloat(b.avg_heart_rate), 0) / data.length;
                const totalSP = data.reduce((a,b) => a + parseFloat(b.avg_spo2), 0) / data.length;
                const totalFalls = data.reduce((a,b) => a + parseInt(b.fall_count), 0);

                document.getElementById("avgPulse").innerHTML = `${Math.round(totalHR)} <span class="text-base text-slate-400 font-medium">BPM</span>`;
                document.getElementById("avgSpo2").innerHTML = `${Math.round(totalSP)} <span class="text-base text-slate-400 font-medium">%</span>`;
                document.getElementById("incidentCount").innerHTML = `${totalFalls.toString().padStart(2,'0')} <span class="text-base text-slate-400 font-medium">Events</span>`;
                document.getElementById("incidentCount").className = totalFalls > 0 ? "text-4xl font-bold text-rose-600" : "text-4xl font-bold text-emerald-600";

                const ctx = document.getElementById('weeklyChart').getContext('2d');
                if(weeklyChart) weeklyChart.destroy();
                weeklyChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(d => d.reading_date),
                        datasets: [
                            { label: 'Pulse', data: data.map(d => d.avg_heart_rate), borderColor: '#f43f5e', tension: 0.4 },
                            { label: 'SpO2', data: data.map(d => d.avg_spo2), borderColor: '#3b82f6', tension: 0.4 
                            },
                            
                             {
                           label: 'Temperature',
                data: data.map(d => d.avg_temperature), // make sure this exists
                borderColor: '#10b981',
                tension: 0.4
            }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            });
        }
function openSetting(type) {

        const content = document.getElementById("settingsContent");
        const details = document.getElementById("settingsDetails");

        content.classList.remove("hidden");

        let html = "";

        if (type === "support") {
            html = `
                <h3 class="text-lg font-bold text-slate-800 mb-3">Support</h3>
                <p>Email: tynoemasurani17@gmail.com</p>
                <p>Telephone: +263777055889</p>
                <p>Opening Hours: 08:00 - 16:00</p>
            `;
        }

        if (type === "help") {
            html = `
                <h3 class="text-lg font-bold text-slate-800 mb-3">Help Center</h3>
                <p>• Ensure your sensor is properly connected.</p>
                <p>• Keep your finger steady for accurate readings.</p>
                <p>• Refresh the dashboard if data stops updating.</p>
            `;
        }

        if (type === "terms") {
            html = `
                <h3 class="text-lg font-bold text-slate-800 mb-3">Terms & Conditions</h3>
                <p>This system is for monitoring purposes only.</p>
                <p>It does not replace professional medical advice.</p>
                <p>Use at your own discretion.</p>
            `;
        }

        if (type === "privacy") {
            html = `
                <h3 class="text-lg font-bold text-slate-800 mb-3">Privacy Policy</h3>
                <p>Your health data is stored securely.</p>
                <p>We do not share your information with third parties.</p>
            `;
        }

        if (type === "about") {
            html = `
                <h3 class="text-lg font-bold text-slate-800 mb-3">About LifeMatrix</h3>
                <p>Version: 2.4p</p>
                <p>Developed for real-time health monitoring.</p>
                <p>Powered by IoT + AI analytics.</p>
            `;
        }

        details.innerHTML = html;
    }

    function closeSetting() {
        document.getElementById("settingsContent").classList.add("hidden");
    }
        // Init Charts & Loops
        setInterval(loadLatest, 5000);
        loadLatest();
    </script>
</body>
</html>