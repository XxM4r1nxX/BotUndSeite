<?php
require_once __DIR__ . '/functions.php';
logout($pdo);
header('Location: index.php');
exit;
