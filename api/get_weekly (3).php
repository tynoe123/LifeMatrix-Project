<?php
$conn = new mysqli("localhost","freelanceelectro_data","Leeroyku2","freelanceelectro_data");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$patient_id = $_GET['patient_id'] ?? 'PATIENT001';

$query = "
SELECT 
    DATE(created_at) AS reading_date,
    AVG(heart_rate) AS avg_heart_rate,
    AVG(spo2) AS avg_spo2,
    AVG(temperature) AS avg_temperature,
    SUM(fall_detected) AS fall_count
FROM patient_readings
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY reading_date ASC
";

$result = $conn->query($query);

$data = [];

while($row = $result->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);
?>