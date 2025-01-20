<?php
session_start();
require_once '../db_connect.php';   // ggf. anpassen
require_once '../config.php';       // enthält RESERVATION_TIMEOUT usw.

// 1) Show ermitteln
$showId = $_GET['show_id'] ?? null;
if (!$showId) {
    header('Location: index.php');
    exit;
}

// 2) Show-Informationen abrufen (Titel, Datum, Zeit, Location)
$showInfoStmt = $pdo->prepare("SELECT * FROM shows WHERE id = :id");
$showInfoStmt->execute(['id' => $showId]);
$showInfo = $showInfoStmt->fetch(PDO::FETCH_ASSOC);
if (!$showInfo) {
    die("Ungültige Show-ID");
}

// 3) Seating-Daten abrufen
$seatingStmt = $pdo->prepare("
    SELECT seat_rows, seats_per_row 
    FROM seating
    WHERE show_id = :sid
");
$seatingStmt->execute(['sid' => $showId]);
$seating = $seatingStmt->fetch(PDO::FETCH_ASSOC);
if (!$seating) {
    die("Keine Bestuhlung für dieses Theaterstück definiert.");
}
$seatRows    = (int)$seating['seat_rows'];
$seatsPerRow = (int)$seating['seats_per_row'];

// 4) Abgelaufene Reservierungen freigeben
$reservationTime = date('Y-m-d H:i:s', time() - (int)RESERVATION_TIMEOUT * 60);
$pdo->beginTransaction();
$updateStmt = $pdo->prepare("
    UPDATE bookings
    SET status = 'reserviert', reserved_by = NULL, reserved_until = NULL
    WHERE status = 'reserviert'
      AND reserved_until < :resTime
");
$updateStmt->execute(['resTime' => $reservationTime]);

// 5) Aktuelle Buchungen/Reservierungen abrufen
$bookedStmt = $pdo->prepare("
    SELECT seat_row, seat_number, status
    FROM bookings
    WHERE show_id = :sid
      AND (status = 'gebucht' OR (status = 'reserviert' AND reserved_until >= NOW()))
");
$bookedStmt->execute(['sid' => $showId]);
$bookedSeats = $bookedStmt->fetchAll(PDO::FETCH_ASSOC);
$pdo->commit();

// 6) seatMap aufbauen
$seatMap = [];
foreach ($bookedSeats as $b) {
    $r = (int)$b['seat_row'];
    $n = (int)$b['seat_number'];
    $seatMap[$r][$n] = $b['status'];  // 'gebucht' oder 'reserviert'
}

// 7) Breite für den Sitzplancontainer berechnen (optional)
$seatWidth  = 40;
$seatMargin = 3;
$outerWidth = $seatWidth + ($seatMargin * 2); 
$containerWidth = ($seatsPerRow * $outerWidth)+220;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sitzplatz Buchung</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 1rem;
            background: #f0f0f0; /* leichte Hintergr. Farbe */
        }
        .layout {
            display: flex;
            gap: 20px;
        }
        .seatplan-container {
            flex: 1; /* Linke Spalte füllt den Platz */
        }
        .sidebar-right {
            width: 300px; /* Rechte Spalte für Show-Infos & Auswahl */
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 1rem;
        }
        .seatplan-wrapper {
            background: #ddd;
            padding: 20px;
            border-radius: 6px;
        }
        .seatplan {
            margin: 0 auto;
            border-radius: 6px;
            padding: 20px;
            background: #e0e0e0;
            box-sizing: border-box;
            width: <?= $containerWidth+40 ?>px; /* So breit wie alle Sitze zusammen */
        }
        .stage {
            background: #333;
            color: #fff;
            text-align: center;
            font-weight: bold;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 6px;
            width: 100%;
            box-sizing: border-box;
        }
        .seat-row {
            margin-bottom: 10px;
        }
        .row-label {
            margin-right: 8px;
            font-weight: bold;
        }
        .seat {
            display: inline-block;
            width: 40px;
            height: 40px;
            margin: 3px;
            background: #888; /* frei */
            color: #fff;
            text-align: center;
            line-height: 40px;
            border-radius: 5px;
            font-size: 0.8rem;
            cursor: pointer;
            position: relative;
        }
        .seat.gebucht {
            background: red;
            cursor: not-allowed;
        }
        .seat.reserviert {
            background: orange;
            cursor: not-allowed;
        }
        .seat.selected {
            background: #007bff; /* Blau */
        }
        /* Sidebar-Inhalte */
        h2 {
            margin-top: 0;
        }
        .show-info p {
            margin: 0.5rem 0;
        }
        .seat-selection {
            margin: 1rem 0;
        }
        .seat-selection ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        .seat-selection ul li {
            margin: 5px 0;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #28a745; /* grün */
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            text-align: center;
            cursor: pointer;
        }
        .btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>

<h1>Sitzplatz Buchung</h1>
<div class="layout">
    <!-- Linke Spalte: Sitzplan -->
    <div class="seatplan-container">
        <div class="seatplan-wrapper">
            <form method="post" action="confirm.php" id="bookingForm">
                <input type="hidden" name="show_id" value="<?= htmlspecialchars($showId) ?>">

                <div class="seatplan">
                    <div class="stage">BÜHNE</div>

                    <!-- Sitzreihen -->
                    <?php for ($row = 1; $row <= $seatRows; $row++): ?>
                        <div class="seat-row">
                            <span class="row-label">
                                Reihe <?= str_pad($row, 2, '0', STR_PAD_LEFT) ?>
                            </span>
                            <?php for ($num = 1; $num <= $seatsPerRow; $num++):
                                $status = $seatMap[$row][$num] ?? 'frei';
                                $classes = ['seat'];
                                if ($status === 'gebucht') {
                                    $classes[] = 'gebucht';
                                } elseif ($status === 'reserviert') {
                                    $classes[] = 'reserviert';
                                }
                                ?>
                                <div class="<?= implode(' ', $classes) ?>"
                                     data-row="<?= $row ?>"
                                     data-num="<?= $num ?>"
                                     data-status="<?= $status ?>"
                                     onclick="toggleSeat(event)">
                                    <?= $num ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endfor; ?>
                </div><!-- .seatplan -->
            </form>
        </div><!-- .seatplan-wrapper -->
    </div>

    <!-- Rechte Spalte: Show-Info & Auswahl -->
    <div class="sidebar-right">
        <div class="show-info">
            <h2><?= htmlspecialchars($showInfo['title']) ?></h2>
            <p><strong>Datum:</strong> <?= htmlspecialchars($showInfo['date']) ?></p>
            <p><strong>Uhrzeit:</strong> <?= htmlspecialchars($showInfo['time']) ?></p>
            <?php if (!empty($showInfo['location'])): ?>
                <p><strong>Ort:</strong> <?= htmlspecialchars($showInfo['location']) ?></p>
            <?php endif; ?>
        </div>

        <div class="seat-selection">
            <h3>Ihre Auswahl</h3>
            <ul id="selectedList">
                <!-- per JS aktualisiert -->
            </ul>
        </div>

        <!-- Buchen-Button -->
        <button type="submit" form="bookingForm" class="btn">
            Buchen
        </button>
    </div>
</div><!-- .layout -->

<script>
/*
 * JS zur Sitz-Auswahl:
 * - Klick auf "freie" Sitze -> selected
 * - Klick auf "selected" Sitze -> abwählen
 * - Gebuchte/Reservierte Sitze bleiben unanklickbar
 * - Anzeige in der rechten Spalte + hidden inputs
*/
let selectedSeats = [];

function toggleSeat(e) {
    const seat = e.currentTarget;
    const status = seat.getAttribute('data-status');
    const row = seat.getAttribute('data-row');
    const num = seat.getAttribute('data-num');

    if (status === 'gebucht' || status === 'reserviert') {
        // Nicht anklickbar
        return;
    }

    // Ist der Sitz schon selected?
    const seatId = row + '-' + num;
    const idx = selectedSeats.indexOf(seatId);

    if (idx === -1) {
        // Noch nicht ausgewählt -> auswählen
        selectedSeats.push(seatId);
        seat.classList.add('selected');
        seat.setAttribute('data-status', 'selected');
    } else {
        // Abwählen
        selectedSeats.splice(idx, 1);
        seat.classList.remove('selected');
        seat.setAttribute('data-status', 'frei');
    }

    updateSelectionList();
    updateHiddenInputs();
}

function updateSelectionList() {
    // Zeige Auswahl in der rechten Spalte
    const list = document.getElementById('selectedList');
    list.innerHTML = '';
    selectedSeats.forEach(seatId => {
        const li = document.createElement('li');
        li.textContent = "Sitz " + seatId.replace('-', ' Reihe ');
        // z.B. "Sitz 2 Reihe 5" (oder du formatierst es anders)
        list.appendChild(li);
    });
}

function updateHiddenInputs() {
    // Alte inputs entfernen
    const form = document.getElementById('bookingForm');
    const oldInputs = form.querySelectorAll('input[name="selected_seats[]"]');
    oldInputs.forEach(inp => inp.remove());

    // Neue inputs hinzufügen
    selectedSeats.forEach(seatId => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'selected_seats[]';
        inp.value = seatId;
        form.appendChild(inp);
    });
}
</script>

</body>
</html>
