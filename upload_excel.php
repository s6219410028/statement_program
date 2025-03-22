<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require './vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Upload Excel Data</title>
    <link rel="stylesheet" href="./css/style.css">
    <style>
        /* Navbar styling */
        #navbar {
            background-color: #333;
            overflow: hidden;
        }

        #navbar a {
            float: left;
            display: block;
            color: #f2f2f2;
            text-align: center;
            padding: 14px 16px;
            text-decoration: none;
        }

        #navbar a:hover {
            background-color: #ddd;
            color: black;
        }

        /* Fixed buttons container at top right */
        #buttons-container {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }

        #buttons-container button {
            margin: 5px;
            padding: 8px 12px;
            background-color: #4caf50;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
        }

        #buttons-container button:hover {
            background-color: #45a049;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <div id="navbar">
        <a href="dashboard.php">Dashboard</a>
        <a href="sale_report.php">Sale Report</a>
    </div>

    <!-- Fixed Save Button -->
    <div id="buttons-container">
        <button type="button" id="saveData">Save Data</button>
    </div>

    <?php
    if (isset($_FILES['excel_file'])) {
        $filename = $_FILES['excel_file']['tmp_name'];

        try {
            // Load the Excel file
            $spreadsheet = IOFactory::load($filename);
            // Convert the active sheet to an array; keys like A, B, C, etc.
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            if (count($sheetData) < 1) {
                echo "No data found in Excel file.";
                exit;
            }

            // Assume the first row contains headers
            $headers = array_shift($sheetData);

            // Display the data in a table with headers horizontally inside a form.
            echo '<form id="editForm">';
            echo '<table border="1" cellpadding="5" cellspacing="0">';

            // Header row
            echo '<tr>';
            foreach ($headers as $colKey => $header) {
                echo '<th>' . htmlspecialchars($header) . '</th>';
            }
            // Optional action column
            echo '<th>Action</th>';
            echo '</tr>';

            // Data rows
            foreach ($sheetData as $row) {
                echo '<tr>';
                foreach ($headers as $colKey => $header) {
                    // If a value is missing, default to an empty string
                    $cell = isset($row[$colKey]) ? $row[$colKey] : '';
                    echo '<td contenteditable="true">' . htmlspecialchars($cell) . '</td>';
                }
                echo '<td><button type="button" class="deleteRow">Delete</button></td>';
                echo '</tr>';
            }

            echo '</table>';
            echo '</form>';

        } catch (Exception $e) {
            echo 'Error loading file: ' . $e->getMessage();
        }
    } else {
        echo "No file uploaded.";
    }
    ?>
    <!-- Include your JavaScript file here. Adjust the path as needed -->
    <script src="./js/scripts.js"></script>
</body>

</html>