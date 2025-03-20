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
</head>
<body>
  <div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
    <a href="logout.php">Logout</a>
    <h3>Upload Sales Statement Report (Excel)</h3>
    <form id="uploadForm" enctype="multipart/form-data" method="post" action="upload_excel.php">
      <br><br>
      
      <label for="excel_file">Choose Excel file (.xlsx):</label>
      <input type="file" name="excel_file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">

      <button type="submit">Upload</button>
    </form>
    
    <hr>

  </div>
  
  <script src="./js/scripts.js"></script>
</body>
</html>
