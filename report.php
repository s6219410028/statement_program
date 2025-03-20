<?php
// report.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
include(__DIR__ . '/db_config.php');


// 1) Get the salesperson from the query string
$salesResponsible = isset($_GET['sales_responsible']) ? $_GET['sales_responsible'] : null;

if (!$salesResponsible) {
    die("No salesperson selected.");
}

// 2) Query the database for all records that match this salesperson
$stmt = $pdo->prepare("SELECT * FROM sales_statements 
                      WHERE sales_responsible = ?
                      ORDER BY customer_account, invoice");
$stmt->execute([$salesResponsible]);
$rows = $stmt->fetchAll();

// If you want grouping or subtotals by customer, you can do so in PHP
// We'll do a simple grouping example:
$groupedData = [];
foreach ($rows as $r) {
    $cust = $r['customer_account'];
    if (!isset($groupedData[$cust])) {
        $groupedData[$cust] = [];
    }
    $groupedData[$cust][] = $r;
}

// 3) Calculate overall totals
$totalInvoices = count($rows);
$totalAmount   = 0;
foreach ($rows as $r) {
    $amt = floatval(str_replace(',', '', $r['invoice_amount'])); // remove commas if needed
    $totalAmount += $amt;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf8mb4">
  <title>Report for <?php echo htmlspecialchars($salesResponsible); ?></title>
  <link rel="stylesheet" href="./css/style.css">
</head>
<body>
  <div class="container">
    <h2>Outstanding Report</h2>
    <p>Sales Responsible: <strong><?php echo htmlspecialchars($salesResponsible); ?></strong></p>
    <p>Total Invoices: <strong><?php echo $totalInvoices; ?></strong></p>
    <p>Total Outstanding: <strong><?php echo number_format($totalAmount, 2); ?></strong> THB</p>
    
    <hr>
    
    <?php foreach ($groupedData as $customer => $records): ?>
      <h3>Customer: <?php echo htmlspecialchars($customer); ?></h3>
      <table border="1" cellpadding="5" cellspacing="0" width="100%">
        <tr>
          <th>Invoice</th>
          <th>Invoice Date</th>
          <th>Due Date</th>
          <th>Invoice Amount</th>
          <th>Status</th>
          <th>Billing No</th>
          <th>Remark</th>
        </tr>
        <?php 
          $subTotal = 0;
          foreach ($records as $rec):
            $amt = floatval(str_replace(',', '', $rec['invoice_amount']));
            $subTotal += $amt;
        ?>
          <tr>
            <td><?php echo htmlspecialchars($rec['invoice']); ?></td>
            <td><?php echo htmlspecialchars($rec['invoice_date']); ?></td>
            <td><?php echo htmlspecialchars($rec['due_date']); ?></td>
            <td style="text-align:right;"><?php echo number_format($amt, 2); ?></td>
            <td><?php echo htmlspecialchars($rec['status']); ?></td>
            <td><?php echo htmlspecialchars($rec['billing_no']); ?></td>
            <td><?php echo htmlspecialchars($rec['remark']); ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td colspan="3" style="text-align:right;"><strong>Subtotal:</strong></td>
          <td style="text-align:right;"><strong><?php echo number_format($subTotal, 2); ?></strong></td>
          <td colspan="3"></td>
        </tr>
      </table>
      <br>
    <?php endforeach; ?>
  </div>
</body>
</html>
