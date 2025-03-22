<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
session_start();

// Ensure the user is authorized.
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include(__DIR__ . '/db_config.php');

// Get the POST data.
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Check if the required parameter is set.
if (!isset($data['sale_responsible']) || empty($data['sale_responsible'])) {
    echo json_encode(['status' => 'error', 'message' => 'No sale selected']);
    exit;
}

$selectedSale = $data['sale_responsible'];

// Prepare a DELETE statement that deletes only the records for the selected sale.
$sql = "DELETE FROM sale_statements WHERE sale_responsible = :sale";
$stmt = $pdo->prepare($sql);

if ($stmt->execute([':sale' => $selectedSale])) {
    echo json_encode(['status' => 'success', 'message' => 'Data deleted successfully for sale: ' . htmlspecialchars($selectedSale)]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete data for sale: ' . htmlspecialchars($selectedSale)]);
}
exit;
?>