<?php
/*
 * Dieses Werk ist lizenziert unter der Creative Commons Lizenz:
 * Namensnennung - Nicht kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International (CC BY-NC-SA 4.0).
 * Siehe die Datei 'license.txt' für Details.
 */
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../css/admin.css"> 
    <title>Admin-Dashboard</title>
</head>
<body>
    <div class="container">
    <h1>Admin-Dashboard</h1>
    <p>Willkommen, <?= htmlspecialchars($_SESSION['admin_username']); ?>!</p>
    <ul>
        <li><a href="shows.php">Theaterstücke verwalten</a></li>
        <li><a href="seating.php">Bestuhlung verwalten</a></li>
        <li><a href="bookings_overview.php">Buchungsübersicht ansehen</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
    </div>
</body>
</html>
