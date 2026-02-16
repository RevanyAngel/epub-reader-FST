<?php
session_start();

// Konfigurasi database
define('DB_HOST', 'sql305.infinityfree.com');
define('DB_USER', 'if0_41168336');
define('DB_PASS', 'rapursan09');
define('DB_NAME', 'if0_41168336_db_epub');

// Koneksi ke database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Set default timezone
date_default_timezone_set('Asia/Jakarta');
?>