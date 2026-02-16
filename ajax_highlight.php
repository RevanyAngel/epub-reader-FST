<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'list';

if($action === 'list') {
    $book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
    $chapter = isset($_GET['chapter']) ? intval($_GET['chapter']) : 0;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 0;

    $stmt = $pdo->prepare("SELECT id, text, color FROM highlights WHERE user_id = ? AND book_id = ? AND chapter = ? AND page = ? ORDER BY id ASC");
    $stmt->execute([$user_id, $book_id, $chapter, $page]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if($action === 'save') {
        $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
        $chapter = isset($_POST['chapter']) ? intval($_POST['chapter']) : 0;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 0;
        $text = isset($_POST['text']) ? trim($_POST['text']) : '';
        $color = isset($_POST['color']) ? trim($_POST['color']) : '#fff59d';

        if($text === '') {
            echo json_encode(['status'=>'error','message'=>'empty_text']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO highlights (user_id, book_id, chapter, page, text, color) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $book_id, $chapter, $page, $text, $color]);
        $id = $pdo->lastInsertId();
        echo json_encode(['status'=>'ok','id'=>$id]);
        exit;
    }

    if($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if($id <= 0) {
            echo json_encode(['status'=>'error','message'=>'invalid_id']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM highlights WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['status'=>'ok']);
        exit;
    }
}

echo json_encode(['status'=>'error','message'=>'unknown_action']);
exit;

?>
