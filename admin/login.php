<?php
/*
 * Dieses Werk ist lizenziert unter der Creative Commons Lizenz:
 * Namensnennung - Nicht kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International (CC BY-NC-SA 4.0).
 * Siehe die Datei 'license.txt' für Details.
 */
session_start();
require_once '../db_connect.php';

// Wurde das Formular abgesendet?
if (isset($_POST['username'], $_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Beispiel: Passwort per password_verify prüfen
        if (password_verify($password, $user['password'])) {
            // Login erfolgreich
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user['username'];
            header('Location: dashboard.php');
            exit;
        }
    }
    $error = "Login fehlgeschlagen. Bitte überprüfen Sie Ihre Zugangsdaten.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin-Login</title>
</head>
<body>
    <h1>Admin Login</h1>
    <?php if (!empty($error)): ?>
        <p style="color: red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <label>Benutzername:
            <input type="text" name="username" required>
        </label><br><br>
        <label>Passwort:
            <input type="password" name="password" required>
        </label><br><br>
        <button type="submit">Einloggen</button>
    </form>
</body>
</html>
