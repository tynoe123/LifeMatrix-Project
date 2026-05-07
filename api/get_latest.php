<?php
$conn = new mysqli("localhost","freelanceelectro_data","Leeroyku2","freelanceelectro_data");

$result = $conn->query("SELECT * FROM patient_readings ORDER BY id DESC LIMIT 1");
echo json_encode($result->fetch_assoc());
$conn->close();
?>
