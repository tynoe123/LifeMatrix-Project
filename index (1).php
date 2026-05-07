<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeMatrix - Secure Access</title>

    <!-- Tailwind -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex items-start justify-center p-6 overflow-y-auto">

    <!-- Background Glow -->
    <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-blue-500/5 rounded-full blur-[120px]"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-rose-500/5 rounded-full blur-[100px]"></div>

    <!-- WRAPPER -->
    <div class="w-full flex justify-center">
        
        <!-- CARD -->
        <div class="w-full max-w-md bg-white border border-slate-200 rounded-3xl p-10 shadow-2xl mt-10 mb-10 z-10">

            <!-- HEADER -->
            <div class="flex flex-col items-center mb-10 text-center">
                <div class="bg-blue-500 p-3 rounded-2xl shadow-lg mb-6">
                    <i data-lucide="activity" class="w-8 h-8 text-white"></i>
                </div>
                <h1 class="text-3xl font-bold text-slate-900">LifeMatrix</h1>
                <p class="text-slate-500 text-sm mt-2">Professional Health Monitoring</p>
            </div>

            <!-- LOGIN SECTION -->
            <div id="loginSection">
                <div class="space-y-6">

                    <div>
                        <label class="text-xs font-bold text-slate-400">Email</label>
                        <input type="email" id="loginEmail" class="w-full mt-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-400">Password</label>
                        <input type="password" id="loginPassword" class="w-full mt-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                    </div>

                    <button id="loginBtn" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-xl transition-all">
                        SIGN IN
                    </button>
                </div>

                <div id="error" class="text-rose-500 text-sm text-center mt-4"></div>

                <p class="mt-8 text-center text-sm text-slate-400">
                    New member?
                    <button id="showRegisterBtn" class="text-blue-500 font-bold hover:underline">Join Now</button>
                </p>
            </div>

            <!-- REGISTER SECTION -->
            <div id="createAccountSection" class="hidden">
                <div class="space-y-5">

                    <div>
                        <label class="text-xs font-bold text-slate-400">Full Name</label>
                        <input type="text" id="full_name" class="w-full mt-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-400">Email</label>
                        <input type="email" id="email" class="w-full mt-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-400">Phone</label>
                        <input type="text" id="phone" class="w-full mt-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-400">Password</label>
                        <input type="password" id="registerPassword" class="w-full mt-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3">
                    </div>

                    <button id="registerBtn" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-xl transition-all">
                        CREATE ACCOUNT
                    </button>
                </div>

                <div id="registerMessage" class="text-center mt-4"></div>

                <p class="mt-8 text-center text-sm text-slate-400">
                    Already registered?
                    <button id="showLoginBtn" class="text-blue-500 font-bold hover:underline">Sign In</button>
                </p>
            </div>

        </div>
    </div>

    <script>
        // Safe icon load
        if (window.lucide) lucide.createIcons();

        const loginSection = document.getElementById("loginSection");
        const registerSection = document.getElementById("createAccountSection");

        // Toggle
        document.getElementById("showRegisterBtn").onclick = () => {
            loginSection.classList.add("hidden");
            registerSection.classList.remove("hidden");
        };

        document.getElementById("showLoginBtn").onclick = () => {
            registerSection.classList.add("hidden");
            loginSection.classList.remove("hidden");
        };

        // LOGIN
        document.getElementById("loginBtn").onclick = () => {
            const email = document.getElementById("loginEmail").value.trim();
            const password = document.getElementById("loginPassword").value;

            if (!email || !password) {
                document.getElementById("error").innerText = "Please fill in all fields";
                return;
            }

            const formData = new URLSearchParams();
            formData.append("email", email);
            formData.append("password", password);

            fetch("api/login_user.php", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    sessionStorage.setItem("loggedIn", "true");
                    sessionStorage.setItem("userName", data.full_name);
                    window.location.href = "dash.php";
                } else {
                    document.getElementById("error").innerText = data.message;
                }
            })
            .catch(() => {
                document.getElementById("error").innerText = "Server connection failed";
            });
        };

        // REGISTER
        document.getElementById("registerBtn").onclick = () => {
            const full_name = document.getElementById("full_name").value.trim();
            const email = document.getElementById("email").value.trim();
            const phone = document.getElementById("phone").value.trim();
            const password = document.getElementById("registerPassword").value;

            if (!full_name || !email || !phone || !password) {
                document.getElementById("registerMessage").innerHTML =
                    "<span class='text-rose-500 font-bold'>All fields are required</span>";
                return;
            }

            const formData = new URLSearchParams();
            formData.append("full_name", full_name);
            formData.append("email", email);
            formData.append("phone", phone);
            formData.append("password", password);

            fetch("api/register_user.php", { method: "POST", body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    document.getElementById("registerMessage").innerHTML =
                        "<span class='text-emerald-500 font-bold'>" + data.message + "</span>";
                } else {
                    document.getElementById("registerMessage").innerHTML =
                        "<span class='text-rose-500 font-bold'>" + data.message + "</span>";
                }
            })
            .catch(() => {
                document.getElementById("registerMessage").innerHTML =
                    "<span class='text-rose-500 font-bold'>Server error</span>";
            });
        };

        // OPTIONAL: disable redirect while testing
        // sessionStorage.clear();
    </script>

</body>
</html>