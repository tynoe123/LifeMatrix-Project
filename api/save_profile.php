<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost","freelanceelectro_data","Leeroyku2","freelanceelectro_data");

$patient_id = $_POST['patient_id'];
$age = $_POST['age'];
$weight = $_POST['weight'];
$height = $_POST['height'];
$conditions = $_POST['conditions'];

$bmi = null;
if ($weight > 0 && $height > 0) {
    $bmi = $weight / ($height * $height);
}

// Check if profile exists
$check = $conn->prepare("SELECT id FROM patient_profiles WHERE patient_id=?");
$check->bind_param("i", $patient_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {

    $stmt = $conn->prepare("UPDATE patient_profiles SET age=?, weight=?, height=?, bmi=?, conditions=? WHERE patient_id=?");
    $stmt->bind_param("iddssi", $age, $weight, $height, $bmi, $conditions, $patient_id);

} else {

    $stmt = $conn->prepare("INSERT INTO patient_profiles (patient_id, age, weight, height, bmi, conditions) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiddss", $patient_id, $age, $weight, $height, $bmi, $conditions);
}

$stmt->execute();

echo json_encode(["status"=>"success","bmi"=>$bmi]);