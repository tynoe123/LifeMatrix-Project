<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Profile</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-slate-50 flex justify-center p-6">

<div class="w-full max-w-md bg-white p-8 rounded-3xl shadow-xl">

    <h2 class="text-2xl font-bold mb-6 text-center">Patient Profile</h2>

    <div class="space-y-4">

        <input id="age" type="number" placeholder="Age" class="w-full p-3 border rounded-xl">
        <input id="weight" type="number" placeholder="Weight (kg)" class="w-full p-3 border rounded-xl">
        <input id="height" type="number" step="0.01" placeholder="Height (m)" class="w-full p-3 border rounded-xl">

        <textarea id="conditions" placeholder="Underlying Conditions"
        class="w-full p-3 border rounded-xl"></textarea>

        <div class="text-center">
            <p class="text-sm text-slate-500">BMI</p>
            <p id="bmiDisplay" class="text-xl font-bold text-blue-600">--</p>
        </div>

        <button id="saveBtn" class="w-full bg-slate-900 text-white py-3 rounded-xl">
            Save Profile
        </button>

        <p id="message" class="text-center text-sm"></p>

    </div>
</div>

<script>

// 🔐 Protect page
if (!sessionStorage.getItem("userId")) {
    window.location.href = "index.php";
}

const userId = sessionStorage.getItem("userId");

// 📥 LOAD PROFILE
fetch("api/get_profile.php?patient_id=" + userId)
.then(res => res.json())
.then(res => {

    if (res.status === "success") {
        const d = res.data;

        age.value = d.age || "";
        weight.value = d.weight || "";
        height.value = d.height || "";
        conditions.value = d.conditions || "";

        if (d.bmi) bmiDisplay.innerText = d.bmi.toFixed(2);
    }
});

// 🧠 AUTO BMI CALCULATION
function calculateBMI() {
    const w = parseFloat(weight.value);
    const h = parseFloat(height.value);

    if (w > 0 && h > 0) {
        const bmi = w / (h * h);
        bmiDisplay.innerText = bmi.toFixed(2);
        return bmi;
    }

    bmiDisplay.innerText = "--";
    return null;
}

weight.oninput = calculateBMI;
height.oninput = calculateBMI;

// 💾 SAVE PROFILE
saveBtn.onclick = () => {

    const formData = new URLSearchParams();
    formData.append("patient_id", userId);
    formData.append("age", age.value);
    formData.append("weight", weight.value);
    formData.append("height", height.value);
    formData.append("conditions", conditions.value);

    message.innerText = "Saving...";

    fetch("api/save_profile.php", { method: "POST", body: formData })
    .then(res => res.json())
    .then(res => {

        if (res.status === "success") {
            message.innerText = "Profile saved successfully";
            if (res.bmi) bmiDisplay.innerText = res.bmi.toFixed(2);
        } else {
            message.innerText = "Error saving profile";
        }
    });
};

</script>
</body>
</html>