<?php
$servername = "mysql-container";
$username   = "root";
$password   = "nopass";
$dbname     = "mydb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
echo "✅ Connected to MySQL successfully!<br>";

$result = $conn->query("SHOW DATABASES;");
while ($row = $result->fetch_assoc()) {
  echo "📂 " . $row['Database'] . "<br>";
}

$conn->close();
