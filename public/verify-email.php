<?php
require_once __DIR__ . "/../config.php";
$token = $_GET['token'] ?? '';
if ($token) {
    header("Location: " . url("verify-email/" . urlencode($token)), true, 301);
} else {
    header("Location: " . url("verify-email"), true, 301);
}
exit;
