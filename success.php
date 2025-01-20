<?php
session_start();
require_once '../db_connect.php';
require_once '../config.php';

// FPDF-Klasse einbinden (manuell, Pfad anpassen)
require_once __DIR__ . '/../fpdf/fpdf.php';

// PHPMailer (manuell, Pfad anpassen) ODER Composer-Autoloader
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $showId = $_POST['show_id'] ?? null;
    $name   = $_POST['name'] ?? null;
    $email  = $_POST['email'] ?? null;

    if (!$showId || !$name || !$email) {
        header('Location: index.php');
        exit;
    }

    // Reservierte Sitze dieses Users (session_id) für diese Show auf 'gebucht' setzen
    $sessionId = session_id();
    $bookedAt  = date('Y-m-d H:i:s');

    $updateStmt = $pdo->prepare("
        UPDATE bookings
        SET status = 'gebucht',
            name   = :name,
            email  = :email,
            booked_at = :booked_at
        WHERE show_id     = :show_id
          AND reserved_by = :reserved_by
          AND status      = 'reserviert'
          AND reserved_until >= NOW()
    ");
    $updateStmt->execute([
        'name'        => $name,
        'email'       => $email,
        'booked_at'   => $bookedAt,
        'show_id'     => $showId,
        'reserved_by' => $sessionId
    ]);

    $affected = $updateStmt->rowCount();

    // -------------------------------------------------------------------------
    // NEU: Falls Sitze gebucht wurden, PDF generieren & E-Mail versenden
    // -------------------------------------------------------------------------
    if ($affected > 0) {
        // 1) Infos über die Show abrufen (Titel, Datum etc.)
        $showStmt = $pdo->prepare("SELECT * FROM shows WHERE id = :id");
        $showStmt->execute(['id' => $showId]);
        $show = $showStmt->fetch(PDO::FETCH_ASSOC);

        // 2) Gebuchte Sitze abrufen (alle, die jetzt auf status 'gebucht' + $email + $bookedAt sind)
        //    Falls du nur die Sitze dieser Session brauchst, kannst du nochmals filtern
        $bookedSeatsStmt = $pdo->prepare("
            SELECT seat_row, seat_number
            FROM bookings
            WHERE show_id = :show_id
              AND email = :email
              AND booked_at >= :booked_at
              AND status = 'gebucht'
        ");
        $bookedSeatsStmt->execute([
            'show_id'   => $showId,
            'email'     => $email,
            'booked_at' => $bookedAt
        ]);
        $bookedSeats = $bookedSeatsStmt->fetchAll(PDO::FETCH_ASSOC);

        // ---------------------------------------------------
        // 3) PDF erzeugen (mit FPDF)
        // ---------------------------------------------------
        // Beispiel: einfache Eintrittskarten - pro Sitz eine "Karte"
        // require_once 'pfad/zu/fpdf.php'; // Falls noch nicht eingebunden.

        // PDF starten
        $pdf = new FPDF();
        $pdf->SetTitle('Ihre Tickets - ' . ($show['title'] ?? ''));
        $pdf->SetAuthor('Aula-Buchungssystem');

        foreach ($bookedSeats as $seat) {
            // Neue Seite / "Karte" pro Sitz
            $pdf->AddPage();

            // Logo, Überschrift o.ä. (optional)
            // $pdf->Image('logo.png', 10, 10, 50);

            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, utf8_decode('Ihr Ticket'), 0, 1, 'C');
            $pdf->Ln(10);

            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 6, utf8_decode('Name: ' . $name), 0, 1);
            $pdf->Cell(0, 6, utf8_decode('E-Mail: ' . $email), 0, 1);
            $pdf->Cell(0, 6, utf8_decode('Veranstaltung: ' . ($show['title'] ?? '')), 0, 1);
            $pdf->Cell(0, 6, utf8_decode('Datum: ' . ($show['date'] ?? '')), 0, 1);
            $pdf->Cell(0, 6, utf8_decode('Uhrzeit: ' . ($show['time'] ?? '')), 0, 1);

            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, utf8_decode('Sitzplatz'), 0, 1);

            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 6, utf8_decode('Reihe: ' . $seat['seat_row'] . ' | Platz: ' . $seat['seat_number']), 0, 1);

            // Optional: QR-Code / Barcode / ...
        }

        // PDF in Variable speichern
        $pdfContent = $pdf->Output('S'); // 'S' = Rückgabe als String

        // ---------------------------------------------------
        // 4) E-Mail versenden mit PHPMailer
        // ---------------------------------------------------
        try {
            $mail = new PHPMailer(true);  // true = Exceptions aktivieren

            // (A) SMTP-Einstellungen anpassen, falls du SMTP benutzen willst
            /*
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'dein_username';
            $mail->Password = 'dein_passwort';
            $mail->SMTPSecure = 'tls';  // oder 'ssl'
            $mail->Port = 587;          // oder 465
            */

            // (B) Absender & Empfänger
            $mail->setFrom('noreply@deine-domain.de', 'Aula-Buchungssystem');
            $mail->addAddress($email, $name);

            // (C) Betreff & Body (HTML oder Plain)
            $mail->Subject = 'Ihre Buchung: ' . ($show['title'] ?? 'Tickets');
            $bodyText = "Hallo {$name},\n\nanbei Ihre Tickets für das Stück \"{$show['title']}\".\n\nViel Spaß!";
            $mail->Body = nl2br($bodyText);
            $mail->AltBody = $bodyText; // Plaintext-Variante

            // (D) PDF als Anhang
            $mail->addStringAttachment($pdfContent, 'tickets.pdf', 'base64', 'application/pdf');

            // (E) Mail senden
            $mail->send();

        } catch (Exception $e) {
            // Fehlermeldung im Log, Admin-Mail, etc.
            // echo 'Mail konnte nicht gesendet werden: ', $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Buchung erfolgreich</title>
</head>
<body>
<h1>Buchung erfolgreich!</h1>
<?php if (!empty($affected)): ?>
    <p>Es wurden <?= $affected ?> Plätze erfolgreich gebucht.</p>
    <p>Sie erhalten in Kürze eine E-Mail mit Ihren Tickets im PDF-Anhang.</p>
<?php else: ?>
    <p>Keine Plätze wurden gebucht. Möglicherweise ist die Reservierung abgelaufen.</p>
<?php endif; ?>
<a href="index.php">Zurück zur Übersicht</a>
</body>
</html>
