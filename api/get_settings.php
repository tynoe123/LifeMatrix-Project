<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost", "freelanceelectro_data", "Leeroyku2", "freelanceelectro_data");

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

$result = $conn->query("SELECT * FROM patient_settings WHERE id = 1");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    $response = [
        "status" => "success",
        "page_title" => "Settings",
        "sections" => [
            [
                "heading" => "Personal Information",
                "items" => [
                    ["title" => "Edit Profile"],
                    ["title" => "Manage Preferences"]
                ]
            ],
            [
                "heading" => "Account Settings",
                "items" => [
                    ["title" => "Change Password"],
                    ["title" => "Email & Notifications"],
                    ["title" => "Linked Accounts"]
                ]
            ],
            [
                "heading" => "Help & Feedback",
                "items" => [
                    ["title" => "FAQs"],
                    ["title" => "Contact Support"],
                    ["title" => "Submit Feedback"]
                ]
            ],
            [
                "heading" => "Privacy Policy",
                "items" => [
                    ["title" => "View Policy"],
                    ["title" => "Manage Data Permissions"]
                ]
            ],
            [
                "heading" => "Backup & Data Export",
                "items" => [
                    [
                        "title" => "Automatic Backup",
                        "type" => "toggle",
                        "value" => isset($row["automatic_backup"]) ? (int)$row["automatic_backup"] : 0
                    ],
                    [
                        "title" => "Export Data",
                        "type" => "button"
                    ]
                ]
            ],
            [
                "heading" => "Emergency Contact",
                "items" => [
                    [
                        "title" => "Phone Number",
                        "type" => "input",
                        "value" => isset($row["emergency_contact"]) ? $row["emergency_contact"] : ""
                    ]
                ]
            ]
        ],
        "emergency_contact" => isset($row["emergency_contact"]) ? $row["emergency_contact"] : "",
        "automatic_backup" => isset($row["automatic_backup"]) ? (int)$row["automatic_backup"] : 0
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "No settings found"
    ]);
}

$conn->close();
?>