<?php
$conn = new mysqli("localhost","freelanceelectro_bin","Leeroyku2","freelanceelectro_data");

$result = $conn->query("SELECT * FROM emergency_contact WHERE id=1");
echo json_encode($result->fetch_assoc());

$conn->close();
?>
