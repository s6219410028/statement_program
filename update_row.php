<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include(__DIR__ . '/db_config.php');

session_start();
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Helper function: Convert a date string to 'Y-m-d' format.
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
        if ($dateObj && $errors['warning_count'] === 0 && $errors['error_count'] === 0) {
            return $dateObj->format('Y-m-d');
        }
    }
    return null;
}

// Helper function: Remove commas and spaces from numeric strings.
function cleanNumeric($numStr)
{
    return str_replace([",", " "], "", trim($numStr));
}

// Helper function: Retrieve a field value from an update row by expected key (case-insensitive).
function getField($row, $expectedKey)
{
    foreach ($row as $key => $value) {
        if (strcasecmp(trim($key), $expectedKey) == 0) {
            return $value;
        }
    }
    return '';
}

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    // Expecting payload to contain "updates"
    if (!$data || !isset($data['updates']) || !is_array($data['updates'])) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid data format']);
        exit;
    }

    $updates = $data['updates'];

    // Define the expected update columns matching the client payload keys.
    // We add the new field "invoicing and delivery on hold" here.
    $expectedUpdateColumns = [
        'invoice' => 'invoice',
        'invoice_date' => 'invoice_date',
        'invoice_amount' => 'invoice_amount',
        'amount_not_settled' => 'amount_not_settled',
        'status' => 'status',
        'remark' => 'remark',
        'term_of_payment' => 'term_of_payment',
        'billing_no' => 'billing_no',
        'method_of_payment' => 'method_of_payment',
        'invoicing_and_delivery_on_hold' => 'invoicing_and_delivery_on_hold',
        'sale_responsible' => 'sale_responsible'
    ];

    // Prepare the UPDATE statement.
    // This statement simply overwrites the columns with the provided values.
    $sql = "UPDATE sale_statements SET
                invoice = :invoice,
                invoice_date = :invoice_date,
                invoice_amount = :invoice_amount,
                amount_not_settled = :amount_not_settled,
                status = :status,
                remark = :remark,
                term_of_payment = :term_of_payment,
                billing_no = :billing_no,
                method_of_payment = :method_of_payment,
                invoicing_and_delivery_on_hold = :invoicing_and_delivery_on_hold,
                sale_responsible = :sale_responsible
            WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    foreach ($updates as $index => $row) {
        // Skip rows without an "id"
        if (!isset($row['id']) || empty($row['id'])) {
            continue;
        }

        $invoice = trim(getField($row, $expectedUpdateColumns['invoice']));
        $invoice_date_raw = trim(getField($row, $expectedUpdateColumns['invoice_date']));
        $invoice_date = convertDate($invoice_date_raw);
        $invoice_amount = cleanNumeric(getField($row, $expectedUpdateColumns['invoice_amount']));
        $amount_not_settled = cleanNumeric(getField($row, $expectedUpdateColumns['amount_not_settled']));
        $status = trim(getField($row, $expectedUpdateColumns['status']));
        $remark = trim(getField($row, $expectedUpdateColumns['remark']));
        $term_of_payment = trim(getField($row, $expectedUpdateColumns['term_of_payment']));
        $billing_no = trim(getField($row, $expectedUpdateColumns['billing_no']));
        $method_of_payment = trim(getField($row, $expectedUpdateColumns['method_of_payment']));
        $invoicing_and_delivery_on_hold = trim(getField($row, $expectedUpdateColumns['invoicing_and_delivery_on_hold']));
        $sale_responsible = trim(getField($row, $expectedUpdateColumns['sale_responsible']));

        $stmt->execute([
            ':invoice' => $invoice,
            ':invoice_date' => $invoice_date,
            ':invoice_amount' => $invoice_amount,
            ':amount_not_settled' => $amount_not_settled,
            ':status' => $status,
            ':remark' => $remark,
            ':term_of_payment' => $term_of_payment,
            ':billing_no' => $billing_no,
            ':method_of_payment' => $method_of_payment,
            ':invoicing_and_delivery_on_hold' => $invoicing_and_delivery_on_hold,
            ':sale_responsible' => $sale_responsible,
            ':id' => (int) $row['id']
        ]);
    }

    ob_end_clean();
    echo json_encode(['status' => 'success', 'message' => 'Changes saved successfully!']);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>