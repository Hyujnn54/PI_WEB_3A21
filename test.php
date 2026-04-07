<?php
try {
    $pdo = new PDO('sqlite:d:/PI_WEB_3A21/var/data.db');
    echo "SQLite connected successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}