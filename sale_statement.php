<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
include(__DIR__ . '/db_config.php');

// Read and decode the JSON input
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!$data || !is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

// Extract the header row (assumed to be the first row)
$headers = array_shift($data);

// Define the expected columns with their corresponding header names
$expectedColumns = [
    'customer_account' => 'Customer account',
    'customer_business_group' => 'Customer business group',
    'name' => 'Name',
    'invoice' => 'Invoice',
    'invoice_date' => 'Date', // "Date" maps to invoice_date
    'city' => 'City',
    'state' => 'State',
    'due_date' => 'Due date',
    'invoice_amount' => 'Invoice amount',
    'currency' => 'Currency',
    'pdc_confirm' => 'PDC Confirm',
    'payments' => 'Payments',
    'amount_not_settled' => 'Amount not settled',
    'billing_date' => 'Billing Date',
    'billing_no' => 'Billing No.',
    'terms_of_payment' => 'Terms of payment',
    'sales_responsible' => 'Sales responsible',
    'remark' => 'Remark',
    'status' => 'Status',
    'company' => 'Company',
    'record_id' => 'Record-ID',
    'pdc_no' => 'PDC No',
    'method_of_payment' => 'Method of payment'
];

// Build a mapping from each expected column key to the index in the header row.
$headerMapping = [];
foreach ($expectedColumns as $key => $expectedHeader) {
    $found = false;
    foreach ($headers as $index => $headerVal) {
        if (strcasecmp(trim($headerVal), $expectedHeader) == 0) {
            $headerMapping[$key] = $index;
            $found = true;
            break;
        }
    }
    // If a header is not found, set mapping to null (defaulting to empty string later)
    if (!$found) {
        $headerMapping[$key] = null;
    }
}

// Prepare the INSERT statement with an extra column for uploader_name.
// Total columns: user_id, then 22 fields from Excel, then uploader_name = 25 columns.
$stmt = $pdo->prepare("INSERT INTO sales_statements (
    user_id,
    customer_account, customer_business_group, name, invoice, invoice_date,
    city, state, due_date, invoice_amount, currency, pdc_confirm, payments,
    amount_not_settled, billing_date, billing_no, terms_of_payment, sales_responsible,
    remark, status, company, record_id, pdc_no, method_of_payment, uploader_name
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

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
    // Return null if the date format is invalid.
    return null;
}

// Helper function: Clean numeric strings by removing commas and spaces.
function cleanNumeric($numStr)
{
    return str_replace([",", " "], "", trim($numStr));
}

// Get uploader's name from session (assumed to be stored as 'username')
$uploader = isset($_SESSION['username']) ? trim($_SESSION['username']) : '';

foreach ($data as $index => $row) {
    // Re-index the row if keys are not numeric.
    if (array_keys($row) !== range(0, count($row) - 1)) {
        $row = array_values($row);
    }
    $values = [
        $_SESSION['user_id'],                                            // user_id
        trim($row[$headerMapping['customer_account']] ?? ''),            // Customer account
        trim($row[$headerMapping['customer_business_group']] ?? ''),     // Customer business group
        trim($row[$headerMapping['name']] ?? ''),                          // Name
        trim($row[$headerMapping['invoice']] ?? ''),                       // Invoice
        convertDate($row[$headerMapping['invoice_date']] ?? ''),           // Invoice date
        trim($row[$headerMapping['city']] ?? ''),                          // City
        trim($row[$headerMapping['state']] ?? ''),                         // State
        convertDate($row[$headerMapping['due_date']] ?? ''),               // Due date
        cleanNumeric($row[$headerMapping['invoice_amount']] ?? ''),        // Invoice amount
        trim($row[$headerMapping['currency']] ?? ''),                      // Currency
        trim($row[$headerMapping['pdc_confirm']] ?? ''),                   // PDC Confirm
        cleanNumeric($row[$headerMapping['payments']] ?? ''),              // Payments
        cleanNumeric($row[$headerMapping['amount_not_settled']] ?? ''),      // Amount not settled
        convertDate($row[$headerMapping['billing_date']] ?? ''),           // Billing Date
        trim($row[$headerMapping['billing_no']] ?? ''),                    // Billing No.
        trim($row[$headerMapping['terms_of_payment']] ?? ''),              // Terms of payment
        trim($row[$headerMapping['sales_responsible']] ?? ''),             // Sales responsible
        trim($row[$headerMapping['remark']] ?? ''),                        // Remark
        trim($row[$headerMapping['status']] ?? ''),                        // Status
        trim($row[$headerMapping['company']] ?? ''),                       // Company
        trim($row[$headerMapping['record_id']] ?? ''),                     // Record-ID
        trim($row[$headerMapping['pdc_no']] ?? ''),                        // PDC No
        trim($row[$headerMapping['method_of_payment']] ?? ''),             // Method of payment
        $uploader                                                        // uploader_name
    ];
    if (count($values) !== 25) {
        throw new Exception("Row $index produced " . count($values) . " values instead of 25");
    }
    $stmt->execute($values);
}

echo json_encode(['status' => 'success', 'message' => 'Data saved successfully']);
?>