<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include(__DIR__ . '/db_config.php');

function cleanNumeric($numStr)
{
    return str_replace([",", " "], "", trim($numStr));
}

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
    throw new Exception("Invalid date format: '$dateStr'");
}

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!$data || !isset($data['updates']) || !is_array($data['updates'])) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }
    $updates = $data['updates'];

    $sql = "UPDATE sale_statements
            SET
              invoice = :invoice,
              invoice_date = :invoice_date,
              invoice_amount = :invoice_amount,
              amount_not_settled = :amount_not_settled,
              status = :status,
              remark = IF(:remark1 = '', remark, :remark2),
              due_date = :due_date,
              billing_no = :billing_no,
              method_of_payment = :method_of_payment,
              sale_responsible = IF(:sale1 = '', sale_responsible, :sale2)
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    foreach ($updates as $row) {
        if (!isset($row['id']))
            continue;
        $stmt->execute([
            ':invoice' => trim($row['invoice']),
            // Convert invoice date using convertDate
            ':invoice_date' => convertDate(trim($row['invoice_date'])),
            ':invoice_amount' => cleanNumeric($row['invoice_amount']),
            ':amount_not_settled' => cleanNumeric($row['amount_not_settled']),
            ':status' => trim($row['status']),
            ':remark1' => trim($row['remark']),
            ':remark2' => trim($row['remark']),
            ':due_date' => convertDate(trim($row['due_date'])),
            ':billing_no' => trim($row['billing_no']),
            // Use method_of_payment key from the updated mapping
            ':method_of_payment' => trim($row['method_of_payment']),
            ':sale1' => trim($row['sale']),
            ':sale2' => trim($row['sale']),
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