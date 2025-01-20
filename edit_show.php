<?php
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once '../db_connect.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: shows.php');
    exit;
}

// Show-Daten abrufen
$stmt = $pdo->prepare("SELECT * FROM shows WHERE id = :id");
$stmt->execute(['id' => $id]);
$show = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$show) {
    header('Location: shows.php');
    exit;
}

// Formular abgeschickt?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Felder auslesen
    $title = $_POST['title'] ?? '';
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $location = $_POST['location'] ?? null;
    $description = $_POST['description'] ?? null; // NEU: Beschreibung

    // SQL-Update (ohne Bild zunächst)
    $updateStmt = $pdo->prepare("
        UPDATE shows 
        SET title = :title, 
            date = :date, 
            time = :time,
            location = :location,
            description = :description
        WHERE id = :id
    ");
    $updateStmt->execute([
        'title' => $title,
        'date' => $date,
        'time' => $time,
        'location' => $location,
        'description' => $description,
        'id' => $id
    ]);

    // Bild-Upload prüfen (optional)
    // Hier nehmen wir an, dass das input-Feld "name='image'" heißt
    if (!empty($_FILES['image']['name'])) {
        // Ordner festlegen
        $uploadDir = __DIR__ . '/../uploads/'; // passt du an deine Struktur an
        // Dateiname generieren (z. B. Zeitstempel + original-Name)
        $filename = time() . '-' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $filename;

        // Prüfen, ob Upload erfolgreich
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            // Relativer Pfad, den wir in DB speichern (z. B. "uploads/1234-foto.jpg")
            $relativePath = 'uploads/' . $filename;

            // In DB updaten
            $imgStmt = $pdo->prepare("UPDATE shows SET image_path = :img WHERE id = :id");
            $imgStmt->execute([
                'img' => $relativePath,
                'id' => $id
            ]);
        } else {
            // Fehler-Handling (in realer Anwendung Meldung an Admin)
            // echo "Fehler beim Datei-Upload";
        }
    }

    // Redirect zurück
    header('Location: shows.php');
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Theaterstück bearbeiten</title>
</head>
<body>
    <h1>Theaterstück bearbeiten</h1>
    <form method="post" enctype="multipart/form-data">
        <label>Titel:
            <input type="text" name="title" value="<?= htmlspecialchars($show['title']) ?>" required>
        </label><br><br>
        
        <label>Datum:
            <input type="date" name="date" value="<?= $show['date'] ?>" required>
        </label><br><br>
        
        <label>Uhrzeit:
            <input type="time" name="time" value="<?= $show['time'] ?>" required>
        </label><br><br>
        
        <label>Ort:
            <input type="text" name="location" value="<?= htmlspecialchars($show['location']) ?>">
        </label><br><br>
        
        <!-- NEU: Beschreibung -->
        <label>Beschreibung / Inhalt:<br>
            <textarea name="description" rows="5" cols="50"><?= htmlspecialchars($show['description']) ?></textarea>
        </label><br><br>
        
        <!-- NEU: Bild-Upload -->
        <label>Bild hochladen (optional):<br>
            <input type="file" name="image" accept="image/*">
        </label><br><br>
        
        <?php if (!empty($show['image_path'])): ?>
            <p>Aktuelles Bild:<br>
                <img src="../<?= htmlspecialchars($show['image_path']) ?>" alt="Show Bild" style="max-width: 300px;">
            </p>
        <?php endif; ?>
        
        <button type="submit">Speichern</button>
    </form>
</body>
</html>
