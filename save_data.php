<?php
try {
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 1); // For debugging; set to 0 in production.
    header('Content-Type: application/json');

    session_start();
    if (!isset($_SESSION['user_id'])) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    include(__DIR__ . '/db_config.php');

    // ---------------- Helper Functions (defined once) ----------------

    /**
     * Normalize a header string: trim and lowercase.
     */
    function normalizeHeader($str)
    {
        return strtolower(trim($str));
    }

    /**
     * Convert a date string to 'Y-m-d' format.
     */
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

    /**
     * Remove commas and spaces from numeric strings.
     */
    function cleanNumeric($numStr)
    {
        return str_replace([",", " "], "", trim($numStr));
    }

    /**
     * Given a numeric data row and a header row (both numeric arrays),
     * return the value corresponding to the expected header.
     */
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

    // ---------------- End of Helper Functions ----------------

    // Read and trim raw POST input.
    $rawInput = trim(file_get_contents("php://input"));
    error_log("Raw Input (length " . strlen($rawInput) . "): " . $rawInput);

    // If the input is form-encoded (starts with "json_data="), extract the JSON string.
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

    // We expect 23 Excel fields.
    $expectedColumnCount = 23;

    // Pad or slice the header row to exactly 23 elements.
    if (count($headerRow) < $expectedColumnCount) {
        $headerRow = array_pad($headerRow, $expectedColumnCount, '');
    } elseif (count($headerRow) > $expectedColumnCount) {
        $headerRow = array_slice($headerRow, 0, $expectedColumnCount);
    }
    error_log("Padded Header Row: " . print_r($headerRow, true));

    /*
     Define the full expected headers mapping for 23 Excel fields in order:
       1. Customer account  
       2. Customer business group  
       3. Name  
       4. Invoice  
       5. Date  
       6. City  
       7. State  
       8. Due date  
       9. Invoice amount  
       10. Currency  
       11. PDC Confirm  
       12. Payments  
       13. Amount not settled  
       14. Billing Date  
       15. Billing No.  
       16. Terms of payment  
       17. Sales responsible  
       18. Remark  
       19. Status  
       20. Company  
       21. Record-ID  
       22. PDC No (missing in file; will be padded)  
       23. Method of payment  
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
        'pdc_no' => 'PDC No',
        'method_of_payment' => 'Method of payment'
    ];

    // Prepare the INSERT statement.
    // Database columns: user_id, then 23 Excel fields = 24 columns.
    $sql = "INSERT INTO sale_statements (
        user_id,
        customer_account, customer_business_group, name, invoice, invoice_date,
        city, state, due_date, invoice_amount, currency, pdc_confirm, payment,
        amount_not_settled, billing_date, billing_no, term_of_payment, sale_responsible,
        remark, status, company, record_id, pdc_no, method_of_payment
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);

    // Helper function: Build a row's values from the provided row.
    function buildRowValues($row, $headerRow, $expectedHeaders, $expectedCount)
    {
        $values = [];
        foreach ($expectedHeaders as $key => $expectedHeader) {
            $values[] = trim(getValue($row, $expectedHeader, $headerRow));
        }
        if (count($values) < $expectedCount) {
            $values = array_pad($values, $expectedCount, '');
        } elseif (count($values) > $expectedCount) {
            $values = array_slice($values, 0, $expectedCount);
        }
        return $values;
    }

    // Loop through each data row.
    foreach ($tableData as $index => $row) {
        $row = array_values($row);

        // Before processing a row, check if all cells are empty.
        if (empty(array_filter($row, function ($cell) {
            return trim($cell) !== ''; 
        }))) {
            error_log("Row $index is completely blank. Skipping.");
            continue; // Skip this row if all cells are blank.
        }





        // Pad or slice the row to exactly 23 elements.
        if (count($row) < $expectedColumnCount) {
            $row = array_pad($row, $expectedColumnCount, '');
        } elseif (count($row) > $expectedColumnCount) {
            $row = array_slice($row, 0, $expectedColumnCount);
        }

        $valuesFromFile = buildRowValues($row, $headerRow, $fullExpectedHeaders, $expectedColumnCount);

        // Build final values array: [user_id] + Excel fields (23 values) = 24 columns.
        $values = array_merge(
            [$_SESSION['user_id']],
            $valuesFromFile
        );
        error_log("Row $index final values: " . print_r($values, true));
        if (count($values) !== 24) {
            throw new Exception("Row $index produced " . count($values) . " values instead of 24");
        }

        // Convert date fields: index 5 (invoice_date), index 8 (due_date), index 14 (billing_date).
        $values[5] = convertDate($values[5]);
        $values[8] = convertDate($values[8]);
        $values[14] = convertDate($values[14]);

        // Clean numeric fields: index 9 (invoice_amount), index 12 (payment), index 13 (amount_not_settled).
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