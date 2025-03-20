<?php
// upload_file.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (isset($_FILES['csv_file'])) {
    $filename = $_FILES['csv_file']['tmp_name'];
    $file = fopen($filename, "r");
    $headers = fgetcsv($file);
    
    // Start output table as an HTML form
    echo '<form id="editForm">';
    echo '<table border="1">';
    // Table header
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '<th>Action</th>';
    echo '</tr>';
    
    // Table rows (each cell is made editable)
    while (($row = fgetcsv($file)) !== FALSE) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td contenteditable="true">' . htmlspecialchars($cell) . '</td>';
        }
        echo '<td><button type="button" class="deleteRow">Delete</button></td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '<button type="button" id="saveData">Save Data</button>';
    echo '</form>';
    fclose($file);
} else {
    echo "No file uploaded.";
}
?>
