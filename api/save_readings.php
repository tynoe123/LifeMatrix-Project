<?php
$servername = "localhost";
$username = "freelanceelectro_data";
$password = "Leeroyku2";
$dbname = "freelanceelectro_data";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed");
}

$patient_id  = $_POST['patient_id'];
$heart_rate  = intval($_POST['heart_rate']);
$spo2        = $_POST['spo2'];
$temperature = $_POST['temperature'];
$fall        = $_POST['fall'];


// If the Arduino didn't send hr_fresh (old firmware), default to 1
// so existing behaviour is preserved
$hr_fresh = isset($_POST['hr_fresh']) ? intval($_POST['hr_fresh']) : 1;
// ========================================================

$sql = "INSERT INTO patient_readings 
        (patient_id, heart_rate, spo2, temperature, fall_detected, hr_fresh) 
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("siidii", $patient_id, $heart_rate, $spo2, $temperature, $fall, $hr_fresh);
$stmt->execute();
$stmt->close();

if ($fall == 1) {
    $conn->query("UPDATE emergency_contact 
                  SET number_of_falls = number_of_falls + 1 
                  WHERE id = 1");
}

$conn->close();
echo "OK";
?>