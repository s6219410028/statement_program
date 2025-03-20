<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); // Debug mode (disable in production)
header('Content-Type: application/json');

try {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized");
    }
    // Adjust the include path as needed (if db_config.php is in the same folder)
    include(__DIR__ . '/db_config.php');

    // Read raw input and log it
    $rawInput = file_get_contents("php://input");
    error_log("Raw input: " . $rawInput);

    $data = json_decode($rawInput, true);
    error_log("Decoded data: " . print_r($data, true));
    if (!$data || !is_array($data)) {
        throw new Exception("Invalid data: " . $rawInput);
    }

    // If the data is nested under "tableData", use that array.
    if (isset($data['tableData']) && is_array($data['tableData'])) {
        $data = $data['tableData'];
    }

    if (count($data) === 0) {
        throw new Exception("No rows found in data");
    }

    // Prepare the INSERT statement with 24 placeholders.
    $stmt = $pdo->prepare("INSERT INTO sale_statements (
        user_id,
        customer_account, customer_business_group, name, invoice, invoice_date,
        city, state, due_date, invoice_amount, currency, pdc_confirm, payment,
        amount_not_settled, billing_date, billing_no, term_of_payment, sale_responsible,
        remark, status, company, record_id, pdc_no, method_of_payment
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    // Helper function: Convert date from d/m/y to Y-m-d.
    function convertDate($dateStr)
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }
        
        // Define an array of acceptable date formats.
        $formats = ['d/m/Y', 'd/m/y', 'm/d/Y', 'm/d/y'];
        
        foreach ($formats as $format) {
            $dateObj = DateTime::createFromFormat($format, $dateStr);
            $errors = DateTime::getLastErrors();
            if ($dateObj && $errors['warning_count'] === 0 && $errors['error_count'] === 0) {
                return $dateObj->format('Y-m-d');
            }
        }
        
        throw new Exception("Invalid date format: '$dateStr'");
    }
    
    // Helper function: Remove commas and spaces from numeric strings.
    function cleanNumeric($numStr)
    {
        return str_replace([",", " "], "", trim($numStr));
    }

    // Loop through each row in the data
    foreach ($data as $index => $row) {
        if (!is_array($row)) {
            error_log("Row $index is not an array.");
            continue;
        }
        // If the row keys are not numeric (like "A", "B", etc.), re-index it.
        if (array_keys($row) !== range(0, count($row) - 1)) {
            $row = array_values($row);
        }
        // Force the row to have exactly 23 elements (the Excel fields)
        if (count($row) < 23) {
            $row = array_pad($row, 23, '');
        } else {
            $row = array_slice($row, 0, 23);
        }

        // Build the values array (24 values: session user_id + 23 fields)
        $values = [
            $_SESSION['user_id'],             // user_id
            trim($row[0]),                    // customer_account
            trim($row[1]),                    // customer_business_group
            trim($row[2]),                    // name
            trim($row[3]),                    // invoice
            convertDate($row[4] ?? ''),       // invoice_date (converted)
            trim($row[5]),                    // city
            trim($row[6]),                    // state
            convertDate($row[7] ?? ''),       // due_date (converted)
            cleanNumeric($row[8] ?? ''),      // invoice_amount (commas removed)
            trim($row[9]),                    // currency
            trim($row[10]),                   // pdc_confirm
            cleanNumeric($row[11] ?? ''),     // payment (commas removed)
            cleanNumeric($row[12] ?? ''),     // amount_not_settled (commas removed)
            convertDate($row[13] ?? ''),      // billing_date (converted)
            trim($row[14]),                   // billing_no
            trim($row[15]),                   // term_of_payment
            trim($row[16]),                   // sale_responsible
            trim($row[17]),                   // remark
            trim($row[18]),                   // status
            trim($row[19]),                   // company
            trim($row[20]),                   // record_id
            trim($row[21]),                   // pdc_no
            trim($row[22])                    // method_of_payment
        ];

        $count = count($values);
        error_log("Row $index processed values count: $count");
        error_log("Row $index processed values: " . json_encode($values));
        if ($count !== 24) {
            throw new Exception("Row $index produced $count values instead of 24");
        }
        $stmt->execute($values);
    }

    ob_end_flush();
    echo json_encode(['status' => 'success', 'message' => 'Data saved successfully']);
} catch (Exception $e) {
    ob_end_clean();
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;
?>