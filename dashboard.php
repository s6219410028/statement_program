<?php
// dashboard.php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf8mb4">
  <title>Dashboard</title>
  <link rel="stylesheet" href="./css/style.css">
  <style>
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
  </style>
</head>

<body>

  <!-- Navbar -->
  <div id="navbar">
    <a href="dashboard.php">Dashboard</a>
    <a href="sale_report.php">Sale Report</a>
  </div>



  <div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
    <a href="logout.php">Logout</a>
    <h3>Upload Sales Statement Report (Excel)</h3>
    <form id="uploadForm" enctype="multipart/form-data" method="post" action="upload_excel.php">
      <br><br>

      <label for="excel_file">Choose Excel file (.xlsx):</label>
      <input type="file" name="excel_file"
        accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">

      <button type="submit">Upload</button>
    </form>

    <hr>

  </div>

  <script src="./js/scripts.js"></script>
</body>

</html>