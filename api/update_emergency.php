<?php
$conn = new mysqli("localhost","freelanceelectro_bin","Leeroyku2","freelanceelectro_data");

$fullname = $_POST['fullname'];
$surname = $_POST['surname'];
$phone = $_POST['phone'];
$relationship = $_POST['relationship'];
$residential = $_POST['residential_address'];
$physical = $_POST['physical_address'];

$conn->query("UPDATE emergency_contact SET
fullname='$fullname',
surname='$surname',
phone='$phone',
relationship='$relationship',
residential_address='$residential',
physical_address='$physical'
WHERE id=1");

echo "Saved";

$conn->close();
?>
