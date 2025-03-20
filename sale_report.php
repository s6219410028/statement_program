<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

include('db_config.php');

try {
  // Fetch distinct sales responsible for the dropdown.
  $stmtSales = $pdo->query("SELECT DISTINCT sale_responsible FROM sale_statements ORDER BY sale_responsible");
  $saleResponsibles = $stmtSales->fetchAll(PDO::FETCH_COLUMN, 0);

  // Get selected sales responsible from GET parameter; default to "ALL".
  $selectedSaleResp = isset($_GET['sale_responsible']) ? trim($_GET['sale_responsible']) : "ALL";

  // Build query for data. If a specific sales responsible is chosen, filter by it.
  $sql = "SELECT * FROM sale_statements";
  $params = [];
  if ($selectedSaleResp !== "ALL") {
    $sql .= " WHERE sale_responsible = :sr";
    $params[':sr'] = $selectedSaleResp;
  }
  // Order by sale_responsible, state, city, customer_account, invoice_date.
  $sql .= " ORDER BY sale_responsible, state, city, customer_account, invoice_date";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  die("Database error: " . $e->getMessage());
}

// Pre-calculate grand totals.
$grandCount = count($rows);
$grandTotal = 0;
foreach ($rows as $r) {
  $grandTotal += (float) $r['invoice_amount'];
}

// Pre-calculate totals and counts by state and city.
$saleRespCounts = [];
$stateCounts = [];
$cityCounts = [];
$groupedStateTotals = [];
foreach ($rows as $row) {
  $sr = $row['sale_responsible'];
  $state = $row['state'];
  $cityKey = $state . '|' . $row['city'];
  $saleRespCounts[$sr] = isset($saleRespCounts[$sr]) ? $saleRespCounts[$sr] + 1 : 1;
  $stateCounts[$state] = isset($stateCounts[$state]) ? $stateCounts[$state] + 1 : 1;
  $cityCounts[$cityKey] = isset($cityCounts[$cityKey]) ? $cityCounts[$cityKey] + 1 : 1;
  $groupedStateTotals[$state] = isset($groupedStateTotals[$state]) ? $groupedStateTotals[$state] + (float) $row['invoice_amount'] : (float) $row['invoice_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>รายงานเปิดบิลคงค้าง</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0px;
      background-color: white;
    }

    h1 {
      text-align: center;
      margin-bottom: 0px;
    }

    .report-header {
      text-align: center;
      margin-bottom: 0px;
    }

    .filter-form {
      text-align: center;
      margin-bottom: 0px;
    }

    .header-line {
      display: flex;
      justify-content: space-between;
      margin: 0px 0px;
    }

    .city-header {
      text-align: center;
      margin: 0px auto;
      background-color: white;
      padding: 0px;
      display: inline-block;
    }

    .table-container {
      margin-left: 0px;
      margin-bottom: 0px;
    }

    table {
      width: 95%;
      border-collapse: collapse;
      margin-bottom: 0px;
    }

    table,
    th,
    td {
      border-collapse: collapse;
    }

    th,
    td {
      padding: 0px;
      text-align: center;
      font-size: 0.8rem;
    }

    th {
      background-color: white;
    }

    td.editable {
      background-color: white;
      cursor: text;
    }

    hr {
      border: 0;
      border-collapse: collapse;
      margin: 0px 0;
    }

    .save-button,
    .export-button,
    .print-button {
      display: inline-block;
      margin: 0px 0px;
      padding: 0px 0px;
      background-color: #4caf50;
      color: white;
      border: none;
      cursor: pointer;
      font-size: 0.8rem;
    }

    .save-button:hover,
    .export-button:hover,
    .print-button:hover {
      background-color: #45a049;
    }

    .cust-header {
      background-color: white;

      text-align: left;
      padding: 0px;
    }

    .cust-total {

      background-color: white;
    }
  </style>
</head>

<body>
  <!-- Wrap the entire report in a container -->
  <div id="reportContent">
    <div class="report-header">
      <h2>รายการบิลคงค้าง</h2>
    </div>
    <!-- Global header -->
    <div class="header-line" style="font-size:1.0rem; color:#333;">
      <?php
      echo "<p>Sale Responsible:  " . htmlspecialchars($selectedSaleResp) . "</p>";
      echo "<p>จำนวนบิลทั้งหมด:  " . $grandCount . "     บิล " . "</p>" ;
      echo "<p>ยอดรวมทั้งหมด:  " . number_format($grandTotal, 2) . "    บาท" . "</p>" ;
      ?>
    </div>
    <hr>
    <!-- Dropdown filter for Sales Responsible -->
    <div class="filter-form">
      <form method="get" action="">
        <label for="sale_responsible">Select Sales Responsible: </label>
        <select name="sale_responsible" id="sale_responsible">
          <option value="ALL" <?php if ($selectedSaleResp == "ALL")
            echo "selected"; ?>>-- All --</option>
          <?php foreach ($saleResponsibles as $sr): ?>
            <option value="<?php echo htmlspecialchars($sr); ?>" <?php if ($selectedSaleResp == $sr)
                 echo "selected"; ?>>
              <?php echo htmlspecialchars($sr); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit">Filter</button>
      </form>
    </div>
    <?php
    if (!$rows) {
      echo "<p>No data found.</p>";
      exit;
    }

    $currentSaleResp = null;
    $currentState = null;
    $currentCity = null;
    $currentCustomer = null;
    $saleRespTotal = 0;

    foreach ($rows as $row) {
      $invoiceAmount = (float) $row['invoice_amount'];

      // --- Group by Sale Responsible ---
      if ($row['sale_responsible'] !== $currentSaleResp) {
        if ($currentSaleResp !== null) {
          echo "<div class='header-line'>Sale Responsible: " . htmlspecialchars($currentSaleResp) .
            " | Invoice Count: " . $saleRespCounts[$currentSaleResp] .
            " | Total Bill: " . number_format($saleRespTotal, 2) . "</div>";
          echo "<hr>";
        }
        $currentSaleResp = $row['sale_responsible'];
        $saleRespTotal = 0;
        $currentState = null;
        $currentCity = null;
        $currentCustomer = null;
      }
      $saleRespTotal += $invoiceAmount;

      // --- Group by State ---
      if ($row['state'] !== $currentState) {
        if ($currentState !== null) {
          echo "<hr>";
        }
        $currentState = $row['state'];
        $stateCount = isset($stateCounts[$currentState]) ? $stateCounts[$currentState] : 0;
        $stateTotal = isset($groupedStateTotals[$currentState]) ? $groupedStateTotals[$currentState] : 0;
        echo "<div class='header-line'>State: " . htmlspecialchars($currentState) .
          " | Invoice Count (State): " . $stateCount .
          " | Total Bill (State): " . number_format($stateTotal, 2) . "</div>";
      }

      // --- Group by City ---
      $cityKey = $currentState . '|' . $row['city'];
      if ($row['city'] !== $currentCity) {
        if ($currentCustomer !== null) {
          // Output customer totals footer before closing previous table.
          echo "<tr class='cust-total'>
              <td colspan='3' style='text-align:right;'>Customer Total:</td>
              <td>" . number_format($customerInvoiceTotal, 2) . "</td>
              <td>" . number_format($customerNotSettledTotal, 2) . "</td>
              <td colspan='4'></td>
            </tr>";
          echo "</table></div>";
          $currentCustomer = null;
        }
        $currentCity = $row['city'];
        $cityCount = isset($cityCounts[$cityKey]) ? $cityCounts[$cityKey] : 0;
        // Independent header for the city.
        echo "<div class='city-header'>";
        echo "City: " . htmlspecialchars($currentCity) . " | ";
        echo "State: " . htmlspecialchars($currentState) . " | ";
        echo "Invoice Count (City): " . $cityCount;
        echo "</div>";
        // Start a new table for this city.
        echo "<div class='table-container'><table>";
        // Add a customer header row (one set for the customer) with Customer Account and Name.
        echo "<tr><td colspan='12' class='cust-header'>";
        echo "Customer Account: " . htmlspecialchars($row['customer_account']) . " | ";
        echo "Name: " . htmlspecialchars($row['name']);
        echo "</td></tr>";
        // Add column headers with new columns for Remark and Sale.
        echo "<tr>
            <th>ลำดับ</th>
            <th>Invoice</th>
            <th>Invoice Date</th>
            <th>Invoice Amount</th>
            <th>Amount Not Settled</th>
            <th>Status</th>
            <th>Billing NO.</th>
            <th>Due Date</th>
            <th>Remark</th>
            <th>Sale</th>
          </tr>";
        $cityRowNum = 0;
        // Initialize customer totals.
        $customerInvoiceTotal = 0;
        $customerNotSettledTotal = 0;
        $currentCustomer = $row['customer_account'];
      } elseif ($row['customer_account'] !== $currentCustomer) {
        // If customer changes within the same city, output totals for previous customer.
        echo "<tr class='cust-total'>
            <td colspan='3' style='text-align:right;'>Customer Total:</td>
            <td>" . number_format($customerInvoiceTotal, 2) . "</td>
            <td>" . number_format($customerNotSettledTotal, 2) . "</td>
            <td colspan='4'></td>
          </tr>";
        echo "</table></div>";
        // Start new customer group.
        $currentCustomer = $row['customer_account'];
        $cityRowNum = 0;
        $customerInvoiceTotal = 0;
        $customerNotSettledTotal = 0;
        echo "<div class='table-container'><table>";
        echo "<tr><td colspan='12' class='cust-header'>";
        echo "Customer Account: " . htmlspecialchars($row['customer_account']) . " | ";
        echo "Name: " . htmlspecialchars($row['name']);
        echo "</td></tr>";
        echo "<tr>
            <th>ลำดับ</th>
            <th>Invoice</th>
            <th>Invoice Date</th>
            <th>Invoice Amount</th>
            <th>Amount Not Settled</th>
            <th>Status</th>
            <th>Billing NO.</th>
            <th>Due Date</th>
            <th>Remark</th>
            <th>Sale</th>
          </tr>";
      }

      // Accumulate customer totals.
      $cityRowNum++;
      $customerInvoiceTotal += $invoiceAmount;
      $customerNotSettledTotal += (float) $row['amount_not_settled'];

      // Format dates to dd/mm/yyyy.
      $invoiceDate = $row['invoice_date'];
      $invoiceDateFormatted = (!empty($invoiceDate) && $invoiceDate != "0000-00-00")
        ? date('d/m/Y', strtotime($invoiceDate))
        : "";
      $dueDate = $row['due_date'];
      $dueDateFormatted = (!empty($dueDate) && $dueDate != "0000-00-00")
        ? date('d/m/Y', strtotime($dueDate))
        : "";


      echo "<tr data-id='" . htmlspecialchars($row['id']) . "'>";
      echo "<td>" . $cityRowNum . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['invoice']) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($invoiceDateFormatted) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . number_format($invoiceAmount, 2) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . number_format((float) $row['amount_not_settled'], 2) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['status']) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['billing_no']) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($dueDateFormatted) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['remark']) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['sale_responsible']) . "</td>";
      echo "</tr>";
    }
    if ($currentCustomer !== null) {
      echo "<tr class='cust-total'>
          <td colspan='3' style='text-align:right;'>Customer Total:</td>
          <td>" . number_format($customerInvoiceTotal, 2) . "</td>
          <td>" . number_format($customerNotSettledTotal, 2) . "</td>
          <td colspan='4'></td>
        </tr>";
      echo "</table></div>";
    }
    if ($currentSaleResp !== null) {
      echo "<div class='header-line'>Sale Responsible: " . htmlspecialchars($currentSaleResp) .
        " | Invoice Count: " . $saleRespCounts[$currentSaleResp] .
        " | Total Bill: " . number_format($saleRespTotal, 2) . "</div>";
      echo "<hr>";
    }
    ?>
    <!-- Save Changes, Export, and Print Buttons -->
    <div style="text-align:center;">
      <button class="save-button" onclick="saveChanges()">Save Changes</button>
      <button class="export-button" onclick="exportToExcel()">Export to Excel</button>
      <button class="print-button" onclick="window.print()">Print</button>
    </div>
    <script>
      // This function collects updated invoice data from each editable row and sends it to update_row.php.
      function saveChanges() {
        let rows = document.querySelectorAll('tr[data-id]');
        let updates = [];

        rows.forEach(row => {
          let id = row.getAttribute('data-id');
          let cells = row.querySelectorAll('td');
          // Mapping:
          // cells[0]: ลำดับ, cells[1]: Invoice, cells[2]: Invoice Date, cells[3]: Invoice Amount,
          // cells[4]: Amount Not Settled, cells[5]: Status, cells[6]: Billing NO., cells[7]: Due Date,
          // cells[8]: Remark, cells[9]: Sale.
          updates.push({
            id: id,
            invoice: cells[1].innerText.trim(),
            invoice_date: cells[2].innerText.trim(),
            invoice_amount: cells[3].innerText.trim(),
            amount_not_settled: cells[4].innerText.trim(),
            status: cells[5].innerText.trim(),
            billing_no: cells[6].innerText.trim(),
            due_date: cells[7].innerText.trim(),
            remark: cells[8].innerText.trim(),
            sale: cells[9].innerText.trim()
          });
        });

        fetch('update_row.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ updates: updates })
        })
          .then(response => response.json())
          .then(result => {
            alert(result.message);
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Error saving changes.');
          });
      }

      // This function exports all tables within the #reportContent container to a CSV file.
      function exportToExcel() {
        var csv = "\uFEFF";
        // Get all tables inside the reportContent container.
        var tables = document.querySelectorAll("#reportContent table");
        tables.forEach(function (table) {
          var rows = table.querySelectorAll("tr");
          rows.forEach(function (row) {
            var cells = row.querySelectorAll("th, td");
            var rowData = [];
            cells.forEach(function (cell) {
              // Escape double quotes in the cell text.
              var text = cell.innerText.replace(/"/g, '""');
              rowData.push('"' + text + '"');
            });
            csv += rowData.join(",") + "\n";
          });
          csv += "\n"; // add an extra newline between tables
        });

        var blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        var url = URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = "report.csv";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }
    </script>
</body>

</html>