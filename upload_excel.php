<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require './vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Load the Excel file from the uploaded file.
if (!isset($_FILES['excel_file'])) {
    echo "No file uploaded.";
    exit;
}
$filename = $_FILES['excel_file']['tmp_name'];

try {
    $spreadsheet = IOFactory::load($filename);
    // Get the active sheet as an array with keys like "A", "B", etc.
    $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

    if (count($sheetData) < 1) {
        echo "No data found in Excel file.";
        exit;
    }

    // Extract the header row and force it to be a numeric array.
    $headerRow = array_values(array_shift($sheetData));

    // Display the data in an editable HTML table.
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Upload Excel Data</title>
        <link rel="stylesheet" href="./css/style.css">
        <style>
            /* Basic styling for navbar and table */
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

            #loadingOverlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 2000;
                color: white;
                text-align: center;
                padding-top: 20%;
                font-size: 2rem;
            }

            table {
                border-collapse: collapse;
                width: 95%;
                margin: 20px auto;
            }

            th,
            td {
                border: 1px solid #ccc;
                padding: 5px;
                text-align: center;
            }
        </style>
    </head>

    <body>
        <!-- Navbar -->
        <div id="navbar">
            <a href="dashboard.php">Dashboard</a>
            <a href="sale_report.php">Sale Report</a>
        </div>

        <!-- Fixed Save Button and Loading Overlay -->
        <div id="buttons-container">
            <button type="button" id="saveData">Save Data</button>
        </div>
        <div id="loadingOverlay">Saving...</div>

        <!-- Display the table inside a form -->
        <form id="editForm">
            <table>
                <tr>
                    <?php
                    // Print header cells.
                    foreach ($headerRow as $header) {
                        echo '<th>' . htmlspecialchars($header) . '</th>';
                    }
                    // Do not include the extra "Action" header in the payload.
                    ?>
                    <th>Action</th>
                </tr>
                <?php
                // Print data rows; convert each row to a numeric array.
                foreach ($sheetData as $row) {
                    $row = array_values($row);
                    echo '<tr>';
                    foreach ($headerRow as $colIndex => $header) {
                        $cell = isset($row[$colIndex]) ? $row[$colIndex] : '';
                        echo '<td contenteditable="true">' . htmlspecialchars($cell) . '</td>';
                    }
                    echo '<td><button type="button" class="deleteRow">Delete</button></td>';
                    echo '</tr>';
                }
                ?>
            </table>
        </form>

        <!-- Output headerRow as a JS variable (without the extra "Action" column) -->
        <script>
            var headerRow = <?php echo json_encode($headerRow); ?>;
        </script>

        <!-- JavaScript to handle deletion and saving -->
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                // Delete row functionality.
                document.addEventListener("click", function (e) {
                    if (e.target && e.target.classList.contains("deleteRow")) {
                        e.target.closest("tr").remove();
                    }
                });

                // Save Data button event.
                document.getElementById("saveData").addEventListener("click", function () {
                    var form = document.getElementById("editForm");
                    var table = form.querySelector("table");
                    var rows = table.querySelectorAll("tr");
                    var header = [];
                    // Get header from first row (<th> cells) and remove any extra column like "Action"
                    var headerCells = rows[0].querySelectorAll("th");
                    headerCells.forEach(function (cell, index) {
                        // Only add headers from the first N columns (where N equals headerRow.length)
                        if (index < headerRow.length) {
                            header.push(cell.innerText.trim());
                        }
                    });

                    var tableData = [];
                    // Loop through data rows (skip header row).
                    for (var i = 1; i < rows.length; i++) {
                        var row = rows[i];
                        var cells = row.querySelectorAll("td");
                        var rowData = [];
                        // Only include the first N cells (exclude the action cell)
                        for (var j = 0; j < cells.length - 1; j++) {
                            rowData.push(cells[j].innerText.trim());
                        }
                        tableData.push(rowData);
                    }

                    var payload = {
                        header: header,
                        tableData: tableData
                    };
                    console.log("Data to send:", JSON.stringify(payload));
                    document.getElementById("loadingOverlay").style.display = "block";
                    fetch("save_data.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(payload)
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (result) {
                            document.getElementById("loadingOverlay").style.display = "none";
                            alert(result.message);
                        })
                        .catch(function (error) {
                            document.getElementById("loadingOverlay").style.display = "none";
                            console.error("Error during fetch:", error);
                            alert("Error saving data.");
                        });
                });
            });
            console.log("Script file loaded.");
        </script>
    </body>

    </html>
    <?php
} catch (Exception $e) {
    echo "Error loading Excel file: " . $e->getMessage();
}
ob_end_flush();
?>