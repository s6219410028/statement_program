<?php
// test_json.php

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // Display a form for testing JSON payloads.
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Test JSON Payload</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }

            textarea {
                width: 100%;
                height: 200px;
            }

            input[type="submit"] {
                padding: 10px 20px;
                font-size: 1rem;
            }
        </style>
    </head>

    <body>
        <h2>Test JSON Payload</h2>
        <form method="post">
            <textarea name="json_data">
    {
      "header": [
        "Customer account",
        "Customer business group",
        "Name",
        "Invoice",
        "Date",
        "City",
        "State",
        "Due date",
        "Invoice amount",
        "Currency",
        "PDC Confirm",
        "Payments",
        "Amount not settled",
        "Billing Date",
        "Billing No.",
        "Terms of payment",
        "Sales responsible",
        "Remark",
        "Status",
        "Company",
        "Record-ID",
        "Method of payment"
      ],
      "tableData": [
        [
          "TestCA",
          "TestCB",
          "Test Name",
          "Test Inv",
          "1/1/2025",
          "Test City",
          "Test State",
          "2/1/2025",
          "1000.00",
          "THB",
          "No",
          "0.00",
          "1000.00",
          "1/2/2025",
          "Test Billing",
          "30 days",
          "Test Sales",
          "Test Remark",
          "Yes",
          "Test Company",
          "TestID",
          "Test Method"
        ]
      ]
    }
                </textarea>
            <br>
            <input type="submit" value="Send JSON">
        </form>
    </body>

    </html>
    <?php
    exit;
}

// For POST requests, process the input.
header('Content-Type: application/json');

// Get the raw POST input.
$rawInput = file_get_contents('php://input');

// If the raw input starts with "json_data=", parse it as form data.
if (strpos($rawInput, 'json_data=') === 0) {
    parse_str($rawInput, $parsed);
    if (isset($parsed['json_data'])) {
        $rawInput = $parsed['json_data'];
    }
}

$data = json_decode($rawInput, true);

if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "status" => "error",
        "message" => "JSON decode error: " . json_last_error_msg(),
        "rawInput" => $rawInput
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "data" => $data
]);
exit;
