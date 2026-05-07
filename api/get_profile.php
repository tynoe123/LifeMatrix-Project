<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost","freelanceelectro_data","Leeroyku2","freelanceelectro_data");

$patient_id = $_GET['patient_id'] ?? 0;

$stmt = $conn->prepare("SELECT age, weight, height, bmi, conditions FROM patient_profiles WHERE patient_id=?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(["status"=>"success","data"=>$row]);
} else {
    echo json_encode(["status"=>"empty"]);
}