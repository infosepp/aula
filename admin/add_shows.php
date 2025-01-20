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

require_once '../db_connect.php';

if (isset($_POST['title'], $_POST['date'], $_POST['time'])) {
    $title = $_POST['title'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $location = $_POST['location'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO shows (title, date, time, location) VALUES (:title, :date, :time, :location)");
    $stmt->execute([
        'title' => $title,
        'date' => $date,
        'time' => $time,
        'location' => $location
    ]);

    header('Location: shows.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Neues Theaterstück anlegen</title>
</head>
<body>
    <h1>Neues Theaterstück anlegen</h1>
    <form method="post">
        <label>Titel:
            <input type="text" name="title" required>
        </label><br><br>
        <label>Datum:
            <input type="date" name="date" required>
        </label><br><br>
        <label>Uhrzeit:
            <input type="time" name="time" required>
        </label><br><br>
        <label>Ort:
            <input type="text" name="location">
        </label><br><br>
        <button type="submit">Speichern</button>
    </form>
</body>
</html>
