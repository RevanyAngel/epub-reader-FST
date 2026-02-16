<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect jika belum login
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Ambil parameter
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($book_id > 0) {
    try {
        // Cek apakah buku milik user
        $stmt = $pdo->prepare("SELECT user_id, filepath FROM books WHERE id = ?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($book && $book['user_id'] == $_SESSION['user_id']) {
            // Hapus file dari server
            if(file_exists($book['filepath'])) {
                unlink($book['filepath']);
            }
            
            // Hapus dari database (cascade akan menghapus konten juga)
            $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
            $stmt->execute([$book_id]);
            
            $_SESSION['message'] = t('delete_success');
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = t('delete_error');
            $_SESSION['message_type'] = 'error';
        }
    } catch(PDOException $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

header('Location: dashboard.php');
exit();
?>