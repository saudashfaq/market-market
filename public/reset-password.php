<?php
require_once __DIR__ . "/../config.php";
$token = $_GET['token'] ?? '';
if ($token) {
    header("Location: " . url("reset-password/" . urlencode($token)), true, 301);
} else {
    header("Location: " . url("reset-password"), true, 301);
}
exit;
