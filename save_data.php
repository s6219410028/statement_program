<?php
try {
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 1); // For debugging; change to 0 in production.
    header('Content-Type: application/json');

    session_start();
    if (!isset($_SESSION['user_id'])) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    include(__DIR__ . '/db_config.php');

    // ---------------- Helper Functions ----------------

    // Normalize a header string: trim and lowercase.
    function normalizeHeader($str)
    {
        return strtolower(trim($str));
    }

    // Convert a date string to 'Y-m-d' format.
    function convertDate($dateStr)
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }
        $formats = ['d/m/Y', 'd/m/y', 'm/d/Y', 'm/d/y'];
        foreach ($formats as $format) {
            $dateObj = DateTime::createFromFormat($format, $dateStr);
            $errors = DateTime::getLastErrors();
            if ($dateObj && $errors['warning_count'] == 0 && $errors['error_count'] == 0) {
                return $dateObj->format('Y-m-d');
            }
        }
        return null;
    }

    // Remove commas and spaces from numeric strings.
    function cleanNumeric($numStr)
    {
        return str_replace([",", " "], "", trim($numStr));
    }

    // Given a numeric data row and a header row (both numeric arrays),
    // return the value corresponding to the expected header.
    function getValue($row, $expectedHeader, $headerRow)
    {
        $expected = normalizeHeader($expectedHeader);
        foreach ($headerRow as $index => $header) {
            if (normalizeHeader($header) === $expected) {
                return isset($row[$index]) ? $row[$index] : '';
            }
        }
        return '';
    }

    // ---------------- End Helper Functions ----------------

    // Read and trim raw POST input.
    $rawInput = trim(file_get_contents("php://input"));
    error_log("Raw Input (length " . strlen($rawInput) . "): " . $rawInput);

    // If input is form-encoded (starts with "json_data="), extract the JSON string.
    if (strpos($rawInput, 'json_data=') === 0) {
        parse_str($rawInput, $parsed);
        if (isset($parsed['json_data'])) {
            $rawInput = $parsed['json_data'];
        }
        error_log("Extracted JSON (length " . strlen($rawInput) . "): " . $rawInput);
    }

    $data = json_decode($rawInput, true);
    error_log("JSON Decode Error: " . json_last_error_msg());
    if (!is_array($data)) {
        throw new Exception("Invalid JSON. Raw input: " . $rawInput);
    }

    // Determine payload format.
    if (isset($data['header']) && isset($data['tableData'])) {
        // New format.
        $headerRow = array_values($data['header']);
        $tableData = $data['tableData'];
        error_log("Payload format: new. Header count: " . count($headerRow));
    } elseif (is_array($data)) {
        // Old format: first row is header.
        if (count($data) > 0) {
            $headerRow = array_values(array_shift($data));
            $tableData = $data;
            error_log("Payload format: old. Header count: " . count($headerRow));
        } else {
            throw new Exception("No rows found in data");
        }
    } else {
        throw new Exception("Invalid data structure. Raw input: " . $rawInput);
    }

    error_log("Original Header Row: " . print_r($headerRow, true));

    // We expect 23 Excel fields from the file.
    $expectedFileColumnCount = 23;

    // Pad or slice the header row to exactly 23 elements.
    if (count($headerRow) < $expectedFileColumnCount) {
        $headerRow = array_pad($headerRow, $expectedFileColumnCount, '');
    } elseif (count($headerRow) > $expectedFileColumnCount) {
        $headerRow = array_slice($headerRow, 0, $expectedFileColumnCount);
    }
    error_log("Padded Header Row: " . print_r($headerRow, true));

    /*
      Full expected headers mapping for 23 Excel fields (in order as they appear in the file):
      0. Customer account  
      1. Customer business group  
      2. Name  
      3. Invoice  
      4. Date  
      5. City  
      6. State  
      7. Due date  
      8. Invoice amount  
      9. Currency  
      10. PDC Confirm  
      11. Payments  
      12. Amount not settled  
      13. Billing Date  
      14. Billing No.  
      15. Terms of payment  
      16. Sales responsible  
      17. Remark  
      18. Status  
      19. Company  
      20. Record-ID  
      21. Method of payment  
      22. Invoicing and delivery on hold
    */
    $fullExpectedHeaders = [
        'customer_account' => 'Customer account',
        'customer_business_group' => 'Customer business group',
        'name' => 'Name',
        'invoice' => 'Invoice',
        'invoice_date' => 'Date',
        'city' => 'City',
        'state' => 'State',
        'due_date' => 'Due date',
        'invoice_amount' => 'Invoice amount',
        'currency' => 'Currency',
        'pdc_confirm' => 'PDC Confirm',
        'payment' => 'Payments',
        'amount_not_settled' => 'Amount not settled',
        'billing_date' => 'Billing Date',
        'billing_no' => 'Billing No.',
        'term_of_payment' => 'Terms of payment',
        'sale_responsible' => 'Sales responsible',
        'remark' => 'Remark',
        'status' => 'Status',
        'company' => 'Company',
        'record_id' => 'Record-ID',
        'method_of_payment' => 'Method of payment',
        'invoicing_and_delivery_on_hold' => 'Invoicing and delivery on hold'
    ];

    // Our file provides 23 fields, but our database table (excluding id) requires 26 columns:
    // We need to supply values for: 
    // user_id, then 24 values, then uploader_name. 
    // The mapping from Excel to database will be:
    // Excel field 1 -> customer_account  
    // Excel field 2 -> customer_business_group  
    // Excel field 3 -> name  
    // Excel field 4 -> invoice  
    // Excel field 5 -> invoice_date  
    // Excel field 6 -> city  
    // Excel field 7 -> state  
    // Excel field 8 -> due_date  
    // Excel field 9 -> invoice_amount  
    // Excel field 10 -> currency  
    // Excel field 11 -> pdc_confirm  
    // Excel field 12 -> payment  
    // Excel field 13 -> amount_not_settled  
    // Excel field 14 -> billing_date  
    // Excel field 15 -> billing_no  
    // Excel field 16 -> term_of_payment  
    // Excel field 17 -> sale_responsible  
    // Excel field 18 -> remark  
    // Excel field 19 -> status  
    // Excel field 20 -> company  
    // Excel field 21 -> record_id  
    // Excel field 22 -> method_of_payment  
    // Excel field 23 -> invoicing_and_delivery_on_hold  
    // However, your database table also has a column "pdc_no" that is not provided by Excel.
    // So, we need to insert an empty string for pdc_no between record_id and method_of_payment.
    // Therefore, we want to build a final "Excel values" array with 24 elements:
    // Use the 23 values from the file and then insert an empty string at index 21.

    // Helper function: Build a row's values from the provided row.
    function buildRowValues($row, $headerRow, $expectedHeaders, $expectedFileCount)
    {
        $values = [];
        foreach ($expectedHeaders as $key => $expectedHeader) {
            // Since $expectedHeaders is a full mapping for 23 fields, we get values for each.
            $values[] = trim(getValue($row, $expectedHeader, $headerRow));
        }
        // $values now has 23 items (as provided by Excel).
        return $values;
    }

    // Prepare the INSERT statement.
    // Database columns: user_id, then 24 Excel-derived values, then uploader_name = 1 + 24 + 1 = 26 columns.
    $sql = "INSERT INTO sale_statements (
        user_id,
        customer_account, customer_business_group, name, invoice, invoice_date,
        city, state, due_date, invoice_amount, currency, pdc_confirm, payment,
        amount_not_settled, billing_date, billing_no, term_of_payment, sale_responsible,
        remark, status, company, record_id, pdc_no, method_of_payment, invoicing_and_delivery_on_hold,
        uploader_name
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);

    // Loop through each data row.
    foreach ($tableData as $index => $row) {
        $row = array_values($row);
        // Skip completely blank rows.
        if (
            empty(array_filter($row, function ($cell) {
                return trim($cell) !== '';
            }))
        ) {
            error_log("Row $index is completely blank. Skipping.");
            continue;
        }
        // Pad or slice the row to exactly 23 elements.
        if (count($row) < 23) {
            $row = array_pad($row, 23, '');
        } elseif (count($row) > 23) {
            $row = array_slice($row, 0, 23);
        }
        $excelValues = buildRowValues($row, $headerRow, $fullExpectedHeaders, 23);
        // Insert an empty string for pdc_no at position 21 (0-indexed position 20 is record_id, so insert after that).
        // Our excelValues array indices: 0 to 22.
        // We want: 0-20: unchanged, then index 21: blank (for pdc_no), then index 21 becomes old index 21, and index 22 becomes old index 22.
        array_splice($excelValues, 21, 0, ''); // Now excelValues has 24 elements.
        // Build final values array: [user_id] + excelValues (24 values) + [uploader_name] = 26 columns.
        $values = array_merge(
            [$_SESSION['user_id']],
            $excelValues,
            [$_SESSION['username']]
        );
        error_log("Row $index final values: " . print_r($values, true));
        if (count($values) !== 26) {
            throw new Exception("Row $index produced " . count($values) . " values instead of 26");
        }

        // Convert date fields:
        // Index 5: invoice_date, index 8: due_date, index 14: billing_date.
        $values[5] = convertDate($values[5]);
        $values[8] = convertDate($values[8]);
        $values[14] = convertDate($values[14]);

        // Clean numeric fields:
        // Index 9: invoice_amount, index 12: payment, index 13: amount_not_settled.
        $values[9] = cleanNumeric($values[9]);
        $values[12] = cleanNumeric($values[12]);
        $values[13] = cleanNumeric($values[13]);

        $stmt->execute($values);
    }

    ob_end_flush();
    echo json_encode(['status' => 'success', 'message' => 'Data saved successfully']);
    exit;
} catch (Exception $e) {
    ob_end_clean();
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>