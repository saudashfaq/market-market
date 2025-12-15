<?php
require_once __DIR__ . "/../config.php";
// Redirect using 307 to preserve POST method and data
header("Location: " . url("resend-verification"), true, 307);
exit;
