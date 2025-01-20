<?php
session_start();
require_once '../db_connect.php';
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $showId = $_POST['show_id'] ?? null;
    $selectedSeats = $_POST['selected_seats'] ?? [];

    if (!$showId || empty($selectedSeats)) {
        header('Location: index.php');
        exit;
    }

    // Reservierungslogik
    $sessionId = session_id();
    $reservedUntil = date('Y-m-d H:i:s', time() + (int)RESERVATION_TIMEOUT * 60);

    foreach ($selectedSeats as $seat) {
        list($row, $num) = explode('-', $seat);

        // Prüfen, ob der Sitz noch frei ist
        $checkStmt = $pdo->prepare("
            SELECT id, status 
            FROM bookings 
            WHERE show_id = :show_id
              AND seat_row = :row
              AND seat_number = :num
              AND (status = 'gebucht' OR (status = 'reserviert' AND reserved_until >= NOW()))
        ");
        $checkStmt->execute([
            'show_id' => $showId,
            'row' => $row,
            'num' => $num
        ]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            // Keine gültige Reservierung/Buchung vorhanden -> reservieren
            $pdo->beginTransaction();

            try {
                // Gibt es irgendeinen (alten) Eintrag?
                $existingRowStmt = $pdo->prepare("
                    SELECT id FROM bookings
                    WHERE show_id = :show_id
                      AND seat_row = :row
                      AND seat_number = :num
                ");
                $existingRowStmt->execute([
                    'show_id' => $showId,
                    'row' => $row,
                    'num' => $num
                ]);
                $oldBooking = $existingRowStmt->fetch(PDO::FETCH_ASSOC);

                if ($oldBooking) {
                    // Update
                    $updStmt = $pdo->prepare("
                        UPDATE bookings
                        SET status = 'reserviert',
                            reserved_until = :reserved_until,
                            reserved_by = :reserved_by,
                            name = NULL,
                            email = NULL,
                            booked_at = NULL
                        WHERE id = :id
                    ");
                    $updStmt->execute([
                        'reserved_until' => $reservedUntil,
                        'reserved_by' => $sessionId,
                        'id' => $oldBooking['id']
                    ]);
                } else {
                    // Insert
                    $insStmt = $pdo->prepare("
                        INSERT INTO bookings
                            (show_id, seat_row, seat_number, status, reserved_until, reserved_by)
                        VALUES
                            (:show_id, :row, :num, 'reserviert', :reserved_until, :reserved_by)
                    ");
                    $insStmt->execute([
                        'show_id' => $showId,
                        'row' => $row,
                        'num' => $num,
                        'reserved_until' => $reservedUntil,
                        'reserved_by' => $sessionId
                    ]);
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                // In Produktion: Loggen, Fehlermeldung etc.
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Buchung abschließen</title>
</head>
<body>
<h1>Buchung abschließen</h1>
<form method="post" action="success.php">
    <input type="hidden" name="show_id" value="<?= htmlspecialchars($showId) ?>">
    <p>Bitte geben Sie Ihre Daten ein, um die Buchung abzuschließen.</p>
    <label>Vorname, Name:<br>
        <input type="text" name="name" required>
    </label><br><br>
    <label>E-Mail-Adresse:<br>
        <input type="email" name="email" required>
    </label><br><br>
    <button type="submit">Buchung durchführen</button>
</form>
</body>
</html>
