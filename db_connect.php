<?php
// Datenbankverbindungsdaten
$host = '...';
$dbname = '...';
$username = '...';
$password = '...!';

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
