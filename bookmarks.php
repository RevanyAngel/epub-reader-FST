<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Proteksi halaman: hanya user yang login bisa akses
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$my_bookmarks = getUserBookmarks($user_id);
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-header">
    <h1><i class="fas fa-bookmark text-primary"></i> <?php echo t('my_bookmarks') ?? 'Bookmark Saya'; ?></h1>
    <p class="text-muted"><?php echo t('bookmark_description'); ?></p>
</div>

<div class="bookmarks-container" style="margin-top: 30px;">
    <?php if(empty($my_bookmarks)): ?>
        <div class="alert alert-warning text-center">
            <p><strong><?php echo t('no_bookmarks_yet'); ?></strong></p>
            <a href="dashboard.php" class="btn btn-primary" style="margin-top: 10px;"> <?php echo t('find_books'); ?></a>
        </div>
    <?php else: ?>
        <div class="book-grid"> <?php foreach($my_bookmarks as $mark): ?>
                <div class="book-card">
                    <div class="book-cover" style="height: 100px; font-size: 2rem;">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <div class="book-info">
                        <h3><?php echo htmlspecialchars($mark['title']); ?></h3>
                        <p class="book-meta">
                            <strong><?php echo t('author'); ?>:</strong> <?php echo htmlspecialchars($mark['author']); ?><br>
                            <strong><?php echo t('chapter'); ?>:</strong> <?php echo $mark['chapter_number']; ?>
                        </p>
                        <div class="book-actions">
                            <a href="reader.php?id=<?php echo $mark['book_id']; ?>&chapter=<?php echo $mark['chapter_number']; ?>" 
                               class="btn btn-success btn-block">
                                <i class="fas fa-book-open"></i> <?php echo t('continue_reading'); ?>
                            </a>
                            <button onclick="deleteBookmark(<?php echo $mark['book_id']; ?>, <?php echo $mark['chapter_number']; ?>)" 
                                    class="btn btn-danger btn-block" style="margin-top: 5px; padding: 8px;">
                                <i class="fas fa-trash"></i> <?php echo t('delete'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteBookmark(bookId, chapter) {
    if(confirm('Hapus bookmark ini?')) {
        const formData = new FormData();
        formData.append('book_id', bookId);
        formData.append('chapter', chapter);

        fetch('ajax_bookmark.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'removed') {
                location.reload(); // Refresh halaman untuk memperbarui daftar
            }
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>