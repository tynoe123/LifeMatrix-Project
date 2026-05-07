<?php
header("Content-Type: application/json");
session_start();

$conn = new mysqli("localhost","freelanceelectro_data","Leeroyku2","freelanceelectro_data");

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : "";
$password = isset($_POST['password']) ? $_POST['password'] : "";

if ($email === "" || $password === "") {
    echo json_encode([
        "status" => "error",
        "message" => "Email and password are required"
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id, full_name, email, password FROM patient_accounts WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email or password"
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();

if (password_verify($password, $user['password'])) {
    $_SESSION['patient_id'] = $user['id'];
    $_SESSION['patient_name'] = $user['full_name'];
    $_SESSION['patient_email'] = $user['email'];

    echo json_encode([
        "status" => "success",
        "message" => "Login successful",
        "full_name" => $user['full_name'],
        "email" => $user['email']
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email or password"
    ]);
}

$stmt->close();
$conn->close();
?>