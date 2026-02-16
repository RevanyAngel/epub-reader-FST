<?php
require_once 'config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$book_id = $_POST['book_id'] ?? 0;
$chapter = $_POST['chapter'] ?? 0;

if ($book_id > 0 && $chapter > 0) {
    // Cek apakah sudah ada
    $stmt = $pdo->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND book_id = ? AND chapter_number = ?");
    $stmt->execute([$user_id, $book_id, $chapter]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Jika ada, hapus (Toggle Off)
        $delete = $pdo->prepare("DELETE FROM bookmarks WHERE id = ?");
        $delete->execute([$existing['id']]);
        echo json_encode(['status' => 'removed']);
    } else {
        // Jika tidak ada, tambah (Toggle On)
        $insert = $pdo->prepare("INSERT INTO bookmarks (user_id, book_id, chapter_number) VALUES (?, ?, ?)");
        $insert->execute([$user_id, $book_id, $chapter]);
        echo json_encode(['status' => 'added']);
    }
}
?>