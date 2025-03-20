<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Adjust the include path as needed
include(__DIR__ . '/db_config.php');

try {
    // Prepare an INSERT statement for table "sale_statements".
    // Your table has 25 columns:
    //   id (auto-increment),
    //   user_id,
    //   customer_account,
    //   customer_business_group,
    //   name,
    //   invoice,
    //   invoice_date,
    //   city,
    //   state,
    //   due_date,
    //   invoice_amount,
    //   currency,
    //   pdc_confirm,
    //   payment,
    //   amount_not_settled,
    //   billing_date,
    //   billing_no,
    //   term_of_payment,
    //   sale_responsible,
    //   remark,
    //   status,
    //   company,
    //   record_id,
    //   pdc_no,
    //   method_of_payment
    // We insert 24 values (skipping id).
    $stmt = $pdo->prepare("INSERT INTO sale_statements (
        user_id,
        customer_account, customer_business_group, name, invoice, invoice_date,
        city, state, due_date, invoice_amount, currency, pdc_confirm, payment,
        amount_not_settled, billing_date, billing_no, term_of_payment, sale_responsible,
        remark, status, company, record_id, pdc_no, method_of_payment
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    // Dummy data: 24 values. Note the following adjustments:
    // - Date values are in YYYY-MM-DD format.
    // - Numeric values do not contain commas.
    $dummyData = [
        1,                      // user_id (dummy)
        'C003592',              // customer_account
        'CB003592',             // customer_business_group
        'โรงพยาบาลเชียงดาว',   // name
        'IST240069082',         // invoice
        '10/5/67',           // invoice_date (proper format)
        'อำเภอเชียงดาว',        // city
        'เชียงใหม่',            // state
        '5/1/68',           // due_date (proper format)
        24000.00,               // invoice_amount (no commas)
        'THB',                  // currency
        'No',                   // pdc_confirm
        0,                      // payment
        24000.00,               // amount_not_settled (no commas)
        '2/1/68',           // billing_date (proper format)
        'BILL123',              // billing_no
        'Net30',                // term_of_payment
        'Test Sales',           // sale_responsible
        'No remark',            // remark
        'Active',               // status
        'Test Company',         // company
        'REC123',               // record_id
        'PDC123',               // pdc_no
        'PDC'                   // method_of_payment
    ];

    // Debug: Log the count and contents of dummyData
    error_log("Number of values in dummyData: " . count($dummyData)); // Should log 24
    error_log("Dummy data: " . print_r($dummyData, true));

    $stmt->execute($dummyData);

    echo json_encode(['status' => 'success', 'message' => 'Test insert successful']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Test insert error: ' . $e->getMessage()]);
}
?>
