<?php
$conn = new mysqli("localhost","freelanceelectro_data","Leeroyku2","freelanceelectro_data");

$result = $conn->query("SELECT * FROM patient_readings ORDER BY id DESC LIMIT 100");

$data = [];
while($row = $result->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
?>
