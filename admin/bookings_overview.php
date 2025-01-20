<?php
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../db_connect.php';   // Pfad anpassen
require_once '../config.php';       // ggf. für RESERVATION_TIMEOUT etc.

// 1) Show-Auswahl
$showId = $_GET['show_id'] ?? null;

// 2) Alle Shows abrufen (für das Dropdown)
$shows = $pdo->query("SELECT id, title FROM shows ORDER BY date, time")->fetchAll(PDO::FETCH_ASSOC);

// Vorbereitung für Sitzplan
$seatRows    = 0;
$seatsPerRow = 0;
$seatMap     = []; // $seatMap[row][num] = ['status' => '...', 'name' => '...', 'email' => '...']

if ($showId) {
    // Seating-Daten holen
    $seatingStmt = $pdo->prepare("
        SELECT seat_rows, seats_per_row 
        FROM seating 
        WHERE show_id = :show_id
    ");
    $seatingStmt->execute(['show_id' => $showId]);
    $seating = $seatingStmt->fetch(PDO::FETCH_ASSOC);

    if ($seating) {
        $seatRows    = (int)$seating['seat_rows'];
        $seatsPerRow = (int)$seating['seats_per_row'];
    }

    // Buchungen laden
    $bookingsStmt = $pdo->prepare("
        SELECT seat_row, seat_number, status, name, email
        FROM bookings
        WHERE show_id = :show_id
    ");
    $bookingsStmt->execute(['show_id' => $showId]);
    $bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

    // seatMap befüllen
    foreach ($bookings as $b) {
        $r = (int)$b['seat_row'];
        $n = (int)$b['seat_number'];
        $seatMap[$r][$n] = [
            'status' => $b['status'],
            'name'   => $b['name'] ?? '',
            'email'  => $b['email'] ?? ''
        ];
    }
}

// Statistik berechnen (falls wir eine Show ausgewählt haben)
$bookedCount   = 0;  // Anzahl gebucht
$reservedCount = 0;  // Anzahl reserviert
$totalCount    = $seatRows * $seatsPerRow;
if ($showId && $totalCount > 0) {
    // Durch alle Plätze gehen
    for ($row = 1; $row <= $seatRows; $row++) {
        for ($num = 1; $num <= $seatsPerRow; $num++) {
            // Ist kein Eintrag im seatMap, ist der Platz frei:
            if (!isset($seatMap[$row][$num])) {
                continue;
            }
            $s = $seatMap[$row][$num]['status'];
            if ($s === 'gebucht') {
                $bookedCount++;
            } elseif ($s === 'reserviert') {
                $reservedCount++;
            }
        }
    }
}
$freeCount           = $totalCount - $bookedCount - $reservedCount;
$utilizationPercent  = $totalCount > 0 ? round(($bookedCount / $totalCount) * 100) : 0;

// --------------------------------
//  NEUE Sektion: Breite des Sitzplan-Containers berechnen
// --------------------------------
$seatWidth  = 40; // Breite eines Sitzes
$seatMargin = 3;  // Margin links/rechts
$outerWidth = $seatWidth + ($seatMargin * 2); // z.B. 46px pro Sitz
$containerWidth = ($seatsPerRow * $outerWidth)+200// Gesamtbreite in px
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin - Buchungsübersicht (Sitzplan)</title>
    <link rel="stylesheet" href="../css/admin.css"> <!-- ggf. eigenes CSS laden -->

    <!-- Inline-CSS für Beispiel -->
    <style>
        /* Container für den gesamten Inhalt */
        .layout {
            display: flex;
            gap: 20px;
        }
        .main-content {
            flex: 1;
        }
        .sidebar-right {
            width: 250px;
            background: #f7f7f7;
            padding: 10px;
            border: 1px solid #ccc;
        }

        /* Sitzplan-Wrapper */
        .seatplan-wrapper {
            background: #eaeaea;
            padding: 20px;
            border-radius: 6px;
        }
        /* Innerhalb seatplan-wrapper ein zentrierter Container */
        .seatplan {
            margin: 0 auto; /* Zentrieren */
            background: #ddd;
            border-radius: 6px;
            padding: 20px;
            box-sizing: border-box;
            /* Breite wird unten inline gesetzt */
        }

        /* Bühne */
        .stage {
            background: #333; 
            color: #fff; 
            text-align: center;
            font-weight: bold;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 6px;
            width: 100%; /* Füllt die gesamte seatplan-Breite aus */
            box-sizing: border-box;
        }

        /* Einzelne Reihen */
        .seat-row {
            margin-bottom: 10px;
        }

        /* Einzelne Sitzplätze */
        .seat {
            display: inline-block;
            width: 40px; 
            height: 40px;
            margin: 3px;
            text-align: center;
            line-height: 40px;
            color: #fff;
            background: #888; /* frei = grau */
            border-radius: 5px;
            position: relative;
            font-size: 0.8rem;
        }

        /* Verschiedene Status-Farben */
        .seat.gebucht {
            background: red; 
        }
        .seat.reserviert {
            background: orange;
        }

        /* Tooltip (Name, Email) */
        .seat .tooltip {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            bottom: 40px;
            left: 0; 
            right: 0;
            margin: 0 auto;
            background: rgba(0,0,0,0.85);
            color: #fff;
            padding: 5px 7px;
            border-radius: 4px;
            font-size: 0.75rem;
            text-align: center;
            transition: opacity 0.3s;
            white-space: nowrap;
            z-index: 99;
        }
        .seat:hover .tooltip {
            visibility: visible;
            opacity: 1;
        }

        /* Überschriften */
        h1, h2 {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<h1>Buchungsübersicht (Sitzplan mit Bühne)</h1>
<a href="dashboard.php">Zurück zum Dashboard</a>

<form method="get" style="margin: 20px 0;">
    <label>Show auswählen:
        <select name="show_id" onchange="this.form.submit()">
            <option value="">-- bitte wählen --</option>
            <?php foreach ($shows as $s):
                $selected = ($s['id'] == $showId) ? 'selected' : '';
            ?>
                <option value="<?= $s['id'] ?>" <?= $selected ?>>
                    <?= htmlspecialchars($s['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<?php if ($showId && $seatRows > 0 && $seatsPerRow > 0): ?>
    <!-- Hauptlayout -->
    <div class="layout">
        <div class="main-content">
            <div class="seatplan-wrapper">

                <!-- Hier fügen wir ein Element ein, 
                     das genau so breit ist wie alle Sitze zusammen -->
                <div class="seatplan" style="width: <?= $containerWidth ?>px;">
                    
                    <!-- Bühne -->
                    <div class="stage">BÜHNE</div>

                    <!-- Sitzplan -->
                    <?php for ($row = 1; $row <= $seatRows; $row++): ?>
                        <div class="seat-row">
                            <strong>Reihe 
                                <?php 
                                // Falls du zweistellige Kennung möchtest:
                                echo str_pad($row, 2, '0', STR_PAD_LEFT);
                                ?>
                            </strong>
                            <?php for ($num = 1; $num <= $seatsPerRow; $num++):
                                $seatData = $seatMap[$row][$num] ?? null;
                                $classes = ['seat'];   // Basis-Klasse
                                $tooltip = '';

                                if ($seatData) {
                                    // Gebucht/Reserviert
                                    $classes[] = $seatData['status'];

                                    // Tooltip mit Name und Email
                                    $infoParts = [];
                                    if (!empty($seatData['name'])) {
                                        $infoParts[] = 'Name: ' . $seatData['name'];
                                    }
                                    if (!empty($seatData['email'])) {
                                        $infoParts[] = 'Email: ' . $seatData['email'];
                                    }
                                    if (!empty($infoParts)) {
                                        $tooltip = implode("\n", $infoParts);
                                    }
                                }
                                ?>
                                <div class="<?= implode(' ', $classes) ?>">
                                    <?= $num ?>
                                    <?php if ($tooltip): ?>
                                        <div class="tooltip">
                                            <?= nl2br(htmlspecialchars($tooltip)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endfor; ?>
                </div><!-- .seatplan -->
            </div><!-- .seatplan-wrapper -->
        </div>
        <div class="sidebar-right">
            <h2>Statistik</h2>
            <p><strong>Gesamtplätze:</strong> <?= $totalCount ?></p>
            <p><strong>Gebucht:</strong> <?= $bookedCount ?></p>
            <p><strong>Reserviert:</strong> <?= $reservedCount ?></p>
            <p><strong>Frei:</strong> <?= $freeCount ?></p>
            <p><strong>Auslastung:</strong> <?= $utilizationPercent ?> %</p>
        </div>
    </div>
<?php elseif ($showId): ?>
    <p>Keine Bestuhlung für diese Show vorhanden oder Sitz-Daten fehlen.</p>
<?php endif; ?>

</body>
</html>
