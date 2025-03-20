<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include('db_config.php');

function cleanNumeric($numStr)
{
    // Remove commas and spaces from the string.
    return str_replace([",", " "], "", trim($numStr));
}

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!$data || !isset($data['updates']) || !is_array($data['updates'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }
    $updates = $data['updates'];


    // Prepare an UPDATE statement that updates only the invoice details.
    $sql = "UPDATE sale_statements
    SET
      invoice = :invoice,
      invoice_date = :invoice_date,
      invoice_amount = :invoice_amount,
      amount_not_settled = :amount_not_settled,
      status = :status,
      billing_no = :billing_no,
      due_date = :due_date,
      remark = IF(:remark1 = '', remark, :remark2),
      sale_responsible = IF(:sale1 = '', sale_responsible, :sale2)
    WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    foreach ($updates as $row) {
        if (!isset($row['id']))
            continue;
        $stmt->execute([
            ':invoice' => trim($row['invoice']),
            ':invoice_date' => trim($row['invoice_date']),
            ':invoice_amount' => cleanNumeric($row['invoice_amount']),
            ':amount_not_settled' => cleanNumeric($row['amount_not_settled']),
            ':status' => trim($row['status']),
            ':billing_no' => trim($row['billing_no']),
            ':due_date' => trim($row['due_date']),
            ':remark1' => trim($row['remark']),
            ':remark2' => trim($row['remark']),
            ':sale1' => trim($row['sale']),
            ':sale2' => trim($row['sale']),
            ':id' => (int) $row['id']
        ]);

    }
    echo json_encode(['status' => 'success', 'message' => 'Changes saved successfully!']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>