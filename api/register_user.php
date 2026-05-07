<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost","freelanceelectro_data","Leeroyku2","freelanceelectro_data");

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : "";
$email = isset($_POST['email']) ? trim($_POST['email']) : "";
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : "";
$password = isset($_POST['password']) ? $_POST['password'] : "";

if ($full_name === "" || $email === "" || $phone === "" || $password === "") {
    echo json_encode([
        "status" => "error",
        "message" => "All fields are required"
    ]);
    exit;
}

$check = $conn->prepare("SELECT id FROM patient_accounts WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Email already exists"
    ]);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO patient_accounts (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $full_name, $email, $phone, $hashed_password);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Account created successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to create account"
    ]);
}

$stmt->close();
$conn->close();
?>