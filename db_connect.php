<?php
// Datenbankverbindungsdaten
$host = 'db5017028276.hosting-data.io';
$dbname = 'dbs13711244';
$username = 'dbu2366982';
$password = 'D6.snh84imG!';

// PDO-Verbindung zur Datenbank herstellen
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}
