<?php
// logout.php
define('SKIP_TIMEOUT_CHECK', true);
require_once __DIR__ . '/config/session.php';

$timeout = isset($_GET['timeout']);
Session::destroy();
header('Location: ' . ($timeout ? '/index.php?timeout=1' : '/index.php'));
exit();