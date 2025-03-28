<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}
include('db_config.php');

// Get distinct Sales Responsible values
$stmtSales = $pdo->query("SELECT DISTINCT sale_responsible FROM sale_statements ORDER BY sale_responsible");
$saleResponsibles = $stmtSales->fetchAll(PDO::FETCH_COLUMN);

// Get distinct States
$stmtStates = $pdo->query("SELECT DISTINCT state FROM sale_statements ORDER BY state");
$states = $stmtStates->fetchAll(PDO::FETCH_COLUMN);

// Get distinct Uploader names
$stmtUploaders = $pdo->query("SELECT DISTINCT uploader_name FROM sale_statements ORDER BY uploader_name");
$uploaders = $stmtUploaders->fetchAll(PDO::FETCH_COLUMN);

// Get selected filters from GET parameters (default is "ALL")
$selectedSaleResp = isset($_GET['sale_responsible']) ? trim($_GET['sale_responsible']) : "ALL";
$selectedState = isset($_GET['state']) ? trim($_GET['state']) : "ALL";
$selectedUploader = isset($_GET['uploader']) ? trim($_GET['uploader']) : "ALL";

// Build the query based on filters
$sql = "SELECT * FROM sale_statements WHERE 1=1";
$params = [];
if ($selectedSaleResp !== "ALL") {
  $sql .= " AND sale_responsible = :sale_responsible";
  $params[':sale_responsible'] = $selectedSaleResp;
}
if ($selectedState !== "ALL") {
  $sql .= " AND state = :state";
  $params[':state'] = $selectedState;
}
if ($selectedUploader !== "ALL") {
  $sql .= " AND uploader_name = :uploader_name";
  $params[':uploader_name'] = $selectedUploader;
}
$sql .= " ORDER BY sale_responsible, state, city, customer_account, invoice_date";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-calculate grand totals.
$grandCount = count($rows);
$grandTotal = 0;
foreach ($rows as $r) {
  $grandTotal += (float) $r['invoice_amount'];
}

// Pre-calculate totals and counts by sale responsible, state, city.
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
  <title>รายการบิลคงค้าง</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background-color: white;
    }

    h2 {
      text-align: center;
    }

    .filter-form {
      text-align: center;
      margin-bottom: 10px;
    }

    .header-line {
      display: flex;
      justify-content: space-between;
      margin: 0 10px;
    }

    .city-header {
      text-align: center;
      margin: 0 auto;
      background-color: white;
      padding: 0;
      display: inline-block;
    }

    .table-container {
      margin: 0 10px 10px 10px;
    }

    table {
      width: 95%;
      border-collapse: collapse;
      margin-bottom: 0px;
    }

    th,
    td {
      padding: 5px;
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
      margin: 0;
    }

    /* Navbar styling */
    #navbar {
      background-color: #333;
      overflow: hidden;
    }

    #navbar a {
      float: left;
      display: block;
      color: #f2f2f2;
      text-align: center;
      padding: 14px 16px;
      text-decoration: none;
    }

    #navbar a:hover {
      background-color: #ddd;
      color: black;
    }

    /* Fixed buttons container at top right */
    #buttons-container {
      position: fixed;
      top: 10px;
      right: 10px;
      z-index: 1000;
    }

    #buttons-container button {
      margin: 5px;
      padding: 8px 12px;
      background-color: #4caf50;
      color: white;
      border: none;
      cursor: pointer;
      font-size: 0.8rem;
    }

    #buttons-container button:hover {
      background-color: #45a049;
    }

    /* Loading screen styling */
    #loadingScreen {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 3000;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 2rem;
    }
  </style>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

</head>

<body>
  <!-- Navbar -->
  <div id="navbar">
    <a href="dashboard.php">Dashboard</a>
    <a href="sale_report.php">Sale Report</a>
  </div>

  <!-- Fixed Buttons Container at Top Right -->
  <div id="buttons-container">
    <button class="save-button" onclick="saveChanges()">Save Changes</button>
    <button class="export-button" onclick="exportToExcel()">Export to Excel</button>
    <button class="delete-button" onclick="deleteData()">Delete Data</button>
  </div>

  <!-- Loading Screen -->
  <div id="loadingScreen">
    <span>Loading...</span>
  </div>

  <!-- Filter Form -->
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

      <label for="state">Select State: </label>
      <select name="state" id="state">
        <option value="ALL" <?php if ($selectedState == "ALL")
          echo "selected"; ?>>-- All --</option>
        <?php foreach ($states as $st): ?>
          <option value="<?php echo htmlspecialchars($st); ?>" <?php if ($selectedState == $st)
               echo "selected"; ?>>
            <?php echo htmlspecialchars($st); ?>
          </option>
        <?php endforeach; ?>
      </select>


      <button type="submit">Filter</button>
    </form>
  </div>

  <!-- Report Content -->
  <div id="reportContent">
    <div class="report-header">
      <h2>รายการบิลคงค้าง</h2>
    </div>
    <!-- Global header -->
    <div class="header-line" style="font-size:1.0rem; color:#333;">
      <p>Sale Responsible: <?php echo htmlspecialchars($selectedSaleResp); ?></p>
      <p>จำนวนบิลทั้งหมด: <?php echo $grandCount; ?> บิล</p>
      <p>ยอดรวมทั้งหมด: <?php echo number_format($grandTotal, 2); ?> บาท</p>
    </div>
    <div style="height:0px; overflow:hidden;"></div>
    <hr>

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

      // // --- Group by Sale Responsible ---
      // if ($row['sale_responsible'] !== $currentSaleResp) {
      //   if ($currentSaleResp !== null) {
      //     echo "<div class='header-line'>Sale Responsible: " . htmlspecialchars($currentSaleResp) .
      //       " | Invoice Count: " . $saleRespCounts[$currentSaleResp] .
      //       " | Total Bill: " . number_format($saleRespTotal, 2) . "</div>";
      //     echo "<hr>";
      //   }
      //   $currentSaleResp = $row['sale_responsible'];
      //   $saleRespTotal = 0;
      //   $currentState = null;
      //   $currentCity = null;
      //   $currentCustomer = null;
      // }
      // $saleRespTotal += $invoiceAmount;
    
      // --- Group by State (show once per state) ---
      if ($row['state'] !== $currentState) {
        if ($currentState !== null) {
          echo "<hr>";
        }
        $currentState = $row['state'];
        $stateCount = isset($stateCounts[$currentState]) ? $stateCounts[$currentState] : 0;
        $stateTotal = isset($groupedStateTotals[$currentState]) ? $groupedStateTotals[$currentState] : 0;
        echo "<div class='header-line'>เขตการขายจังหวัด(State): " . htmlspecialchars($currentState) .
          " จำนวนบิล: " . $stateCount . " ยอด: " . number_format($stateTotal, 2) . "</div>";
      }
      // --- Group by City ---
      $cityKey = $currentState . '|' . $row['city'];
      if ($row['city'] !== $currentCity) {
        if ($currentCustomer !== null) {
          echo "<tr class='cust-total'>
          <td></td> <!-- col1: ลำดับ -->
          <td></td> <!-- col2: Invoice -->
          <td></td> <!-- col3: Invoice Date -->
          <td>" . number_format($customerInvoiceTotal, 2) . "</td> <!-- col4: Invoice Amount -->
          <td>" . number_format($customerNotSettledTotal, 2) . "</td> <!-- col5: Amount Not Settled -->
          <td></td> <!-- col6: Status -->
          <td></td> <!-- col7: Remark -->
          <td></td> <!-- col8: Term Of Payment -->
          <td></td> <!-- col9: Billing NO. -->
          <td></td> <!-- col10: Method of Payment -->
          <td></td> <!-- col11: Invoicing and delivery on hold -->
          <td></td> <!-- col12: Sale responsible -->
        </tr>";

          echo "</table></div>";
          $currentCustomer = null;
        }
        $currentCity = $row['city'];
        $cityCount = isset($cityCounts[$cityKey]) ? $cityCounts[$cityKey] : 0;
        echo "<div class='city-header'>เขต >> " . htmlspecialchars($currentCity) . " " . $cityCount . "</div>";
        echo "<div class='table-container'><table border='0' cellpadding='5' cellspacing='0'>";
        echo "<tr><td colspan='12' class='cust-header'>";
        echo "<span style='font-size:1.0em; margin-right:20px;'>" . htmlspecialchars($row['customer_account']) . "</span>";
        echo "<span style='font-size:1.0em;'>" . htmlspecialchars($row['name']) . "</span>";
        echo "</td></tr>";
        echo "<tr>
            <th>ลำดับ</th>
            <th>Invoice</th>
            <th>Invoice Date</th>
            <th>Invoice Amount</th>
            <th>Amount Not Settled</th>
            <th>Status</th>
            <th>Remark</th>
            <th>Term Of Payment</th>
            <th>Billing NO.</th>
            <th>Method of Payment</th>
            <th>Invoicing and delivery on hold</th>
            <th>Sale responsible</th>
          </tr>";
        $cityRowNum = 0;
        $customerInvoiceTotal = 0;
        $customerNotSettledTotal = 0;
        $currentCustomer = $row['customer_account'];
      } elseif ($row['customer_account'] !== $currentCustomer) {
        echo "<tr class='cust-total'>
          <td></td>  <!-- Column 1: ลำดับ -->
          <td></td>  <!-- Column 2: Invoice -->
          <td></td>  <!-- Column 3: Invoice Date -->
          <td>" . number_format($customerInvoiceTotal, 2) . "</td>  <!-- Column 4: Invoice Amount -->
          <td>" . number_format($customerNotSettledTotal, 2) . "</td>  <!-- Column 5: Amount Not Settled -->
          <td></td>  <!-- Column 6: Status -->
          <td></td>  <!-- Column 7: Remark -->
          <td></td>  <!-- Column 8: Term Of Payment -->
          <td></td>  <!-- Column 9: Billing NO. -->
          <td></td>  <!-- Column 10: Method of Payment -->
          <td></td>  <!-- Column 11: Invoicing and delivery on hold -->
          <td></td>  <!-- Column 12: Sale responsible -->
        </tr>";
        echo "</table></div>";
        $currentCustomer = $row['customer_account'];
        $cityRowNum = 0;
        $customerInvoiceTotal = 0;
        $customerNotSettledTotal = 0;
        echo "<div class='table-container'><table border='0' cellpadding='5' cellspacing='0'>";
        echo "<tr><td colspan='12' class='cust-header'>";
        echo "<span style='font-size:1.0em; margin-right:20px;'>" . htmlspecialchars($row['customer_account']) . "</span>";
        echo "<span style='font-size:1.0em;'>" . htmlspecialchars($row['name']) . "</span>";
        echo "</td></tr>";
        echo "<tr>
            <th>ลำดับ</th>
            <th>Invoice</th>
            <th>Invoice Date</th>
            <th>Invoice Amount</th>
            <th>Amount Not Settled</th>
            <th>Status</th>
            <th>Remark</th>
            <th>Term Of Payment</th>
            <th>Billing NO.</th>
            <th>Method of Payment</th>
            <th>Invoicing and delivery on hold</th>
            <th>Sale respoonsible</th>
          </tr>";
      }

      $cityRowNum++;
      $customerInvoiceTotal += $invoiceAmount;
      $customerNotSettledTotal += (float) $row['amount_not_settled'];

      $invoiceDate = $row['invoice_date'];
      $invoiceDateFormatted = (!empty($invoiceDate) && $invoiceDate != "0000-00-00")
        ? date('d/m/Y', strtotime($invoiceDate))
        : "";

      // Instead of formatting due_date, we output term_of_payment directly.
      $termOfPayment = $row['term_of_payment'];

      echo "<tr data-id='" . htmlspecialchars($row['id']) . "'>";
      echo "<td>" . $cityRowNum . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['invoice']) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($invoiceDateFormatted) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . number_format($invoiceAmount, 2) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . number_format((float) $row['amount_not_settled'], 2) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['status']) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['remark']) . "</td>";
      // Changed column: now show Term Of Payment instead of Due Date.
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($termOfPayment) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['billing_no']) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['method_of_payment']) . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['invoicing_and_delivery_on_hold'] ?? '') . "</td>";
      echo "<td contenteditable='true' class='editable'>" . htmlspecialchars($row['sale_responsible']) . "</td>";
      echo "</tr>";
    }
    if ($currentCustomer !== null) {
      echo "<tr class='cust-total'>
          <td></td>  <!-- Column 1: ลำดับ -->
          <td></td>  <!-- Column 2: Invoice -->
          <td></td>  <!-- Column 3: Invoice Date -->
          <td>" . number_format($customerInvoiceTotal, 2) . "</td>  <!-- Column 4: Invoice Amount -->
          <td>" . number_format($customerNotSettledTotal, 2) . "</td>  <!-- Column 5: Amount Not Settled -->
          <td></td>  <!-- Column 6: Status -->
          <td></td>  <!-- Column 7: Remark -->
          <td></td>  <!-- Column 8: Term Of Payment -->
          <td></td>  <!-- Column 9: Billing NO. -->
          <td></td>  <!-- Column 10: Method of Payment -->
          <td></td>  <!-- Column 11: Invoicing and delivery on hold -->
          <td></td>  <!-- Column 12: Sale responsible -->
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

    <script>
      // Loading overlay functions
      function showLoading() {
        let loadingScreen = document.getElementById("loadingScreen");
        if (loadingScreen) {
          loadingScreen.style.display = "flex";
        }
      }
      function hideLoading() {
        let loadingScreen = document.getElementById("loadingScreen");
        if (loadingScreen) {
          loadingScreen.style.display = "none";
        }
      }

      // This function collects updated invoice data from each editable row and sends it to update_row.php.
      function saveChanges() {
        let rows = document.querySelectorAll('tr[data-id]');
        let updates = [];
        rows.forEach(row => {
          let id = row.getAttribute('data-id');
          let cells = row.querySelectorAll('td');
          // Mapping based on new header order:
          // cells[0]: ลำดับ (non-editable)
          // cells[1]: Invoice
          // cells[2]: Invoice Date
          // cells[3]: Invoice Amount
          // cells[4]: Amount Not Settled
          // cells[5]: Status
          // cells[6]: Remark
          // cells[7]: Term Of Payment (changed)
          // cells[8]: Billing NO.
          // cells[9]: Method of Payment
          // cells[10]: invoicing_and_delivery_on_hold
          // cells[11]: Sale
          updates.push({
            id: row.getAttribute('data-id'),
            invoice: cells[1].innerText.trim(),
            invoice_date: cells[2].innerText.trim(),
            invoice_amount: cells[3].innerText.trim(),
            amount_not_settled: cells[4].innerText.trim(),
            status: cells[5].innerText.trim(),
            remark: cells[6].innerText.trim(),
            term_of_payment: cells[7].innerText.trim(),
            billing_no: cells[8].innerText.trim(),
            method_of_payment: cells[9].innerText.trim(),
            invoicing_and_delivery_on_hold: cells[10].innerText.trim(),
            sale_responsible: cells[11].innerText.trim()
          });

        });
        showLoading();
        fetch('update_row.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ updates: updates })
        })
          .then(response => response.json())
          .then(result => {
            hideLoading();
            alert(result.message);
          })
          .catch(error => {
            hideLoading();
            console.error('Error:', error);
            alert('Error saving changes.');
          });
      }

      // Example deleteData function (adjust as needed)
      function deleteData() {
        if (confirm("Are you sure you want to delete the data for the selected sale? This action cannot be undone.")) {
          let saleResponsible = document.getElementById('sale_responsible').value;
          showLoading();
          fetch('delete_data.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sale_responsible: saleResponsible })
          })
            .then(response => response.json())
            .then(result => {
              hideLoading();
              if (result.status === 'success') {
                alert(result.message);
                document.getElementById("reportContent").innerHTML = "";
              } else {
                alert("Error: " + result.message);
              }
            })
            .catch(error => {
              hideLoading();
              console.error("Error:", error);
              alert("Error deleting data.");
            });
        }
      }




      function exportToExcel() {
        var exportData = [];
        var mergeRanges = [];
        var container = document.getElementById("reportContent");

        // Define the new detailed header row.
        var newHeaders = [
          "ลำดับ",
          "ใบกำกับภาษี",
          "วันที่ในบิล",
          "จำนวนเงินในบิล",
          "ยอดที่ต้องชำระ",
          "สถานะ",
          "remark",
          "Term",
          "วางบิล",
          "การชำระ",
          "on hold",
          "Sale"
        ];

        // Loop through each child element of reportContent.
        for (var i = 0; i < container.children.length; i++) {
          var el = container.children[i];
          if (el.classList.contains("filter-form")) continue;

          // For group header elements outside of tables.
          if (
            el.classList.contains("report-header") ||
            el.classList.contains("cust-header") ||
            el.classList.contains("header-line") ||
            el.classList.contains("city-header")
          ) {
            var rowArr = [el.innerText.trim()];
            while (rowArr.length < 12) { rowArr.push(""); }
            rowArr = rowArr.slice(0, 12);
            var mergeRowIndex = exportData.length;
            exportData.push(rowArr);
            mergeRanges.push({ s: { r: mergeRowIndex, c: 0 }, e: { r: mergeRowIndex, c: 11 } });
            exportData.push([]); // blank row for spacing.
          }
          // For table containers (detailed rows)
          else if (el.classList.contains("table-container")) {
            var table = el.querySelector("table");
            if (table) {
              var rows = table.querySelectorAll("tr");
              rows.forEach(function (row) {
                // Detect if this row is a customer header row by checking for the "cust-header" class.
                var isCustHeader = row.classList.contains("cust-header") ||
                  (row.firstElementChild && row.firstElementChild.classList.contains("cust-header"));
                var rowData = [];
                var cells = row.querySelectorAll("th, td");
                cells.forEach(function (cell) {
                  rowData.push(cell.innerText.trim());
                });
                // Force rowData to have exactly 12 cells.
                if (rowData.length < 12) {
                  while (rowData.length < 12) { rowData.push(""); }
                } else if (rowData.length > 12) {
                  rowData = rowData.slice(0, 12);
                }
                // Replace detailed header row (detected by first cell "ลำดับ") with newHeaders.
                if (rowData[0] === "ลำดับ") {
                  rowData = newHeaders;
                }
                exportData.push(rowData);
                // If this row is a customer header row, add a merge range.
                if (isCustHeader) {
                  var mergeRowIndex = exportData.length - 1;
                  mergeRanges.push({ s: { r: mergeRowIndex, c: 0 }, e: { r: mergeRowIndex, c: 11 } });
                }
              });
              exportData.push([]); // spacing row after table.
            }
          }
          // For other block-level elements.
          else if (
            el.tagName === "DIV" ||
            el.tagName === "P" ||
            el.tagName === "H2" ||
            el.tagName === "H3"
          ) {
            if (el.innerText && el.innerText.trim() !== "") {
              var divRow = [el.innerText.trim()];
              while (divRow.length < 12) { divRow.push(""); }
              divRow = divRow.slice(0, 12);
              exportData.push(divRow);
              exportData.push([]);
            }
          }
          else if (el.tagName === "HR") {
            exportData.push([]);
          }
        }

        // Clean cells: ensure empty cells are exactly empty string.
        for (var r = 0; r < exportData.length; r++) {
          for (var c = 0; c < exportData[r].length; c++) {
            if (!exportData[r][c] || exportData[r][c].trim() === "") {
              exportData[r][c] = "";
            }
          }
        }

        // Create worksheet.
        var ws = XLSX.utils.aoa_to_sheet(exportData);

        // Set cell styles.
        var range = XLSX.utils.decode_range(ws["!ref"]);
        for (var R = range.s.r; R <= range.e.r; R++) {
          for (var C = range.s.c; C <= range.e.c; C++) {
            var cellAddress = XLSX.utils.encode_cell({ r: R, c: C });
            if (ws[cellAddress]) {
              if (!ws[cellAddress].s) ws[cellAddress].s = {};
              // For the detailed header row, check if first cell equals newHeaders[0].
              if (exportData[R] && exportData[R][0] === newHeaders[0]) {
                ws[cellAddress].s.alignment = { vertical: "top", horizontal: "center", wrapText: true, indent: 0 };
              } else {
                ws[cellAddress].s.alignment = { vertical: "top", horizontal: "center", wrapText: false, indent: 0 };
              }
            }
          }
        }

        // Set column widths based on new header text.
        ws["!cols"] = [
          { wch: 5 },   // ลำดับ
          { wch: 10 },  // ใบกำกับภาษี
          { wch: 12 },  // วันที่ในบิล
          { wch: 14 },  // จำนวนเงินในบิล
          { wch: 14 },  // ยอดที่ต้องชำระ
          { wch: 7 },   // สถานะ
          { wch: 7 },   // remark
          { wch: 7 },  // Term Of Payment
          { wch: 8 },  // วางบิล
          { wch: 8 },  // การชำระ
          { wch: 8 },  // invoicing and delivery on hold
          { wch: 10 }   // Sale
        ];

        // Apply merge ranges.
        if (mergeRanges.length > 0) {
          ws["!merges"] = mergeRanges;
        }

        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Report");
        var wbout = XLSX.write(wb, { bookType: "xlsx", type: "array" });
        var blob = new Blob([wbout], { type: "application/octet-stream" });
        var url = URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = "report.xlsx";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }



    </script>
</body>

</html>