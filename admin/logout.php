<?php
/*
 * Dieses Werk ist lizenziert unter der Creative Commons Lizenz:
 * Namensnennung - Nicht kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International (CC BY-NC-SA 4.0).
 * Siehe die Datei 'license.txt' für Details.
 */
session_start();
session_destroy();
header('Location: login.php');
exit;
