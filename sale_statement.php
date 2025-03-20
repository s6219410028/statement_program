<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
include(__DIR__ . '/db_config.php');


// Decode the JSON input
$data = json_decode(file_get_contents("php://input"), true);

if ($data && is_array($data)) {
    // Prepare an insert statement.
    // Here, we include the user_id column first.
    $stmt = $pdo->prepare("INSERT INTO sales_statements (
        user_id,
        customer_account, customer_business_group, name, invoice, invoice_date, 
        city, state, due_date, invoice_amount, currency, pdc_confirm, payments, 
        amount_not_settled, billing_date, billing_no, terms_of_payment, sales_responsible, 
        remark, status, company, record_id, pdc_no, method_of_payment
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    foreach ($data as $row) {
        // The row array is assumed to have 23 elements (0 to 22)
        // We prepend the logged-in user's id to the data.
        $stmt->execute([
            $_SESSION['user_id'],  // new first value for user_id
            $row[0],  // Customer account
            $row[1],  // Customer business group
            $row[2],  // Name
            $row[3],  // Invoice
            $row[4],  // Date (invoice_date)
            $row[5],  // City
            $row[6],  // State
            $row[7],  // Due date
            $row[8],  // Invoice amount
            $row[9],  // Currency
            $row[10], // PDC Confirm
            $row[11], // Payments
            $row[12], // Amount not settled
            $row[13], // Billing Date
            $row[14], // Billing No.
            $row[15], // Terms of payment
            $row[16], // Sales responsible
            $row[17], // Remark
            $row[18], // Status
            $row[19], // Company
            $row[20], // Record-ID
            $row[21], // PDC No
            $row[22]  // Method of payment
        ]);
    }
    echo json_encode(['status' => 'success', 'message' => 'Data saved successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>