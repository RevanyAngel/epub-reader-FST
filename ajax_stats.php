<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
$chapter = isset($_POST['chapter']) ? intval($_POST['chapter']) : null;
$page = isset($_POST['page']) ? intval($_POST['page']) : 1;
$words = isset($_POST['words']) ? intval($_POST['words']) : 0;
$pages = isset($_POST['pages']) ? intval($_POST['pages']) : 0;
$seconds = isset($_POST['seconds']) ? intval($_POST['seconds']) : 0;

if($book_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid book']);
    exit;
}

$ok = saveReadingStat($user_id, $book_id, $chapter, $page, $words, $pages, $seconds);

if($ok) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
}

?>