<?php
require_once __DIR__ . "/../config.php";
// Redirect legacy file to clean URL
header("Location: " . url('forgotPassword'), true, 301);
exit;
