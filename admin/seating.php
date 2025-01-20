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

// Wurde das Formular zum Speichern abgesendet?
if (isset($_POST['show_id'], $_POST['seat_rows'], $_POST['seats_per_row'])) {
    $showId = (int)$_POST['show_id'];
    $rows = (int)$_POST['seat_rows'];
    $perRow = (int)$_POST['seats_per_row'];

    // Prüfen, ob es bereits einen Seating-Eintrag gibt
    $checkStmt = $pdo->prepare("SELECT id FROM seating WHERE show_id = :show_id");
    $checkStmt->execute(['show_id' => $showId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update
        $updateStmt = $pdo->prepare("
            UPDATE seating 
            SET seat_rows = :rows, seats_per_row = :perRow 
            WHERE show_id = :show_id
        ");
        $updateStmt->execute([
            'rows' => $rows,
            'perRow' => $perRow,
            'show_id' => $showId
        ]);
    } else {
        // Insert
        $insertStmt = $pdo->prepare("
            INSERT INTO seating (show_id, seat_rows, seats_per_row) 
            VALUES (:show_id, :rows, :perRow)
        ");
        $insertStmt->execute([
            'show_id' => $showId,
            'rows' => $rows,
            'perRow' => $perRow
        ]);
    }
}

// Alle Shows abfragen und ggf. vorhandene Seating-Daten
$showsStmt = $pdo->query("
    SELECT s.id, s.title, seat.seat_rows, seat.seats_per_row
    FROM shows s
    LEFT JOIN seating seat ON s.id = seat.show_id
    ORDER BY s.date, s.time
");
$allShows = $showsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bestuhlung verwalten</title>
</head>
<body>
<h1>Bestuhlung verwalten</h1>
<a href="dashboard.php">Zurück zum Dashboard</a>

<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>Show ID</th>
        <th>Titel</th>
        <th>Reihen</th>
        <th>Sitze pro Reihe</th>
        <th>Aktion</th>
    </tr>
    <?php foreach ($allShows as $show): ?>
    <tr>
        <form method="post">
            <td><?= $show['id'] ?></td>
            <td><?= htmlspecialchars($show['title']) ?></td>
            <td><input type="number" name="seat_rows" value="<?= $show['seat_rows'] ?? 0 ?>"></td>
            <td><input type="number" name="seats_per_row" value="<?= $show['seats_per_row'] ?? 0 ?>"></td>
            <td>
                <input type="hidden" name="show_id" value="<?= $show['id'] ?>">
                <button type="submit">Speichern</button>
            </td>
        </form>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
