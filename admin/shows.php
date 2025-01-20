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

// Shows abrufen
$stmt = $pdo->query("SELECT * FROM shows ORDER BY date, time");
$shows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Theaterstücke verwalten</title>
</head>
<body>
    <h1>Theaterstücke verwalten</h1>
    <a href="dashboard.php">Zurück zum Dashboard</a> |
    <a href="add_show.php">Neues Theaterstück anlegen</a>

    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Titel</th>
            <th>Datum</th>
            <th>Uhrzeit</th>
            <th>Ort</th>
            <th>Aktionen</th>
        </tr>
        <?php foreach($shows as $show): ?>
        <tr>
            <td><?= $show['id'] ?></td>
            <td><?= htmlspecialchars($show['title']) ?></td>
            <td><?= $show['date'] ?></td>
            <td><?= $show['time'] ?></td>
            <td><?= htmlspecialchars($show['location']) ?></td>
            <td>
                <a href="edit_show.php?id=<?= $show['id'] ?>">Bearbeiten</a>
                <!-- Hier könnte man auch Löschen anbieten -->
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
