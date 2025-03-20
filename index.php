<?php
// index.php
session_start();
if(isset($_SESSION['user_id'])){
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf8mb4">
  <title>Login</title>
  <link rel="stylesheet" href="./css/style.css">
</head>
<body>
  <div class="login-container">
    <h2>Login</h2>
    <form action="login_process.php" method="post">
      <label for="username">Username:</label>
      <input type="text" name="username" id="username" required>
      <label for="password">Password:</label>
      <input type="password" name="password" id="password" required>
      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>
