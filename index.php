<?php
session_start();
require_once '../db_connect.php';   // Pfad anpassen
require_once '../config.php';       // Falls du RESERVATION_TIMEOUT oder anderes brauchst

// OPTIONAL: nur Stücke zeigen, die aktuell buchbar sind
// (Variante A: direkter Zeit-Filter in der DB-Abfrage)

// --- Variante A: Nur buchbare Stücke (CURRENT_TIMESTAMP zwischen start_time und end_time) 
/*
$sql = "
    SELECT s.id, s.title, s.date, s.time, s.location, s.description, s.image_path,
           bp.start_time, bp.end_time
    FROM shows s
    INNER JOIN booking_periods bp ON s.id = bp.show_id
    WHERE NOW() >= bp.start_time
      AND NOW() <= bp.end_time
    ORDER BY s.date, s.time
";
$shows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
*/

// --- Variante B: Alle Stücke zeigen (ggf. separate Prüfung in PHP)
// (erweitert um s.description und s.image_path)
$sql = "
    SELECT s.id, s.title, s.date, s.time, s.location, s.description, s.image_path,
           bp.start_time, bp.end_time
    FROM shows s
    LEFT JOIN booking_periods bp ON s.id = bp.show_id
    ORDER BY s.date, s.time
";
$shows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// 1) Wurde eine Show ausgewählt?
$selectedShowId = $_GET['show_id'] ?? null;
$selectedShow = null;

// 2) Aus dem Array $shows die ausgewählte Show herausfiltern
if ($selectedShowId) {
    foreach ($shows as $show) {
        if ($show['id'] == $selectedShowId) {
            $selectedShow = $show;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Theaterstücke</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 1rem;
            background: #f0f0f0;
        }
        .layout {
            display: flex;
            gap: 20px;
        }
        .sidebar-left {
            width: 250px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .sidebar-left ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .sidebar-left li {
            border-bottom: 1px solid #ddd;
        }
        .sidebar-left li a {
            display: block;
            padding: 10px;
            text-decoration: none;
            color: #333;
        }
        .sidebar-left li a:hover {
            background: #eee;
        }

        .main-content {
            flex: 1;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 1rem;
        }
        .btn {
            display: inline-block;
            background: #007bff;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 10px;
        }
        .btn:hover {
            background: #0056b3;
        }
        /* optionales Styling für Bild & Beschreibung */
        .show-image {
            max-width: 400px;
            margin-bottom: 1rem;
        }
        .show-description {
            margin: 1rem 0;
            white-space: pre-wrap; /* Damit Zeilenumbrüche beachtet werden */
        }
    </style>
</head>
<body>
<h1>Theaterstücke auswählen</h1>
<div class="layout">
    <!-- Linke Spalte: Stück-Liste -->
    <div class="sidebar-left">
        <ul>
            <?php if (empty($shows)): ?>
                <li style="padding:10px;">Keine Stücke gefunden.</li>
            <?php else: ?>
                <?php foreach ($shows as $show): ?>
                    <li>
                        <a href="index.php?show_id=<?= $show['id'] ?>">
                            <?= htmlspecialchars($show['title']) ?>
                            <br><small>(<?= htmlspecialchars($show['date']) ?>, <?= htmlspecialchars($show['time']) ?>)</small>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Rechte Spalte: Detailanzeige -->
    <div class="main-content">
        <?php if (!$selectedShow): ?>
            <h2>Bitte wählen Sie ein Theaterstück links aus.</h2>
        <?php else: ?>
            <h2><?= htmlspecialchars($selectedShow['title']) ?></h2>
            <p><strong>Datum:</strong> <?= htmlspecialchars($selectedShow['date']) ?></p>
            <p><strong>Uhrzeit:</strong> <?= htmlspecialchars($selectedShow['time']) ?></p>
            <?php if (!empty($selectedShow['location'])): ?>
                <p><strong>Ort:</strong> <?= htmlspecialchars($selectedShow['location']) ?></p>
            <?php endif; ?>

            <!-- NEU: Beschreibung anzeigen (sofern vorhanden) -->
            <?php if (!empty($selectedShow['description'])): ?>
                <div class="show-description">
                    <?= nl2br(htmlspecialchars($selectedShow['description'])) ?>
                </div>
            <?php endif; ?>

            <!-- NEU: Bild anzeigen (sofern hinterlegt) -->
            <?php if (!empty($selectedShow['image_path'])): ?>
                <div>
                    <img src="../<?= htmlspecialchars($selectedShow['image_path']) ?>"
                         alt="Show Bild"
                         class="show-image">
                </div>
            <?php endif; ?>

            <?php
            // Prüfen, ob wir uns im Buchungszeitraum befinden
            $startTime = $selectedShow['start_time'] ?? null;
            $endTime   = $selectedShow['end_time'] ?? null;
            $now       = date('Y-m-d H:i:s');

            $isBookable = false;
            if ($startTime && $endTime) {
                if ($now >= $startTime && $now <= $endTime) {
                    $isBookable = true;
                }
            }
            ?>
            <?php if ($isBookable): ?>
                <p>
                    <a class="btn" href="seats.php?show_id=<?= $selectedShow['id'] ?>">
                        Jetzt buchen
                    </a>
                </p>
            <?php else: ?>
                <p style="color: red;">
                    Aktuell keine Buchung möglich (außerhalb des Buchungszeitraums).
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
