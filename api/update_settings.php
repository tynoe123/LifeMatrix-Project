<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost","freelanceelectro_data","Leeroyku2","freelanceelectro_data");

if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"Database connection failed"]);
    exit;
}

$emergency_contact = isset($_POST['emergency_contact']) ? $_POST['emergency_contact'] : "";
$automatic_backup = isset($_POST['automatic_backup']) ? (int)$_POST['automatic_backup'] : 0;

$stmt = $conn->prepare("UPDATE patient_settings SET emergency_contact=?, automatic_backup=? WHERE id=1");

$stmt->bind_param("si", $emergency_contact, $automatic_backup);

if($stmt->execute()){
    echo json_encode([
        "status"=>"success",
        "message"=>"Settings updated successfully"
    ]);
}else{
    echo json_encode([
        "status"=>"error",
        "message"=>"Update failed"
    ]);
}

$stmt->close();
$conn->close();
?>
