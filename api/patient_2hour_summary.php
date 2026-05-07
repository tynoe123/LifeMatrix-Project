<?php

$servername = "localhost";
$username = "freelanceelectro_data";
$password = "Leeroyku2";
$dbname = "freelanceelectro_data";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed");
}


// Insert daily averages calculated from raw patient_readings
$patient_id = 'PATIENT001'; // change dynamically if needed

$sql = "
SELECT 
    day,
    avg_heart_rate,
    avg_spo2,
    avg_temperature,
    fall_count
FROM daily_averages
WHERE patient_id = ?
AND day >= CURDATE() - INTERVAL 6 DAY
ORDER BY day ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);

$stmt->close();
$conn->close();
?>