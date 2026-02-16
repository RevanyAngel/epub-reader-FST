<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect jika belum login
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Ubah bahasa jika diminta
if(isset($_GET['lang'])) {
    $_SESSION['language'] = $_GET['lang'];
    
    // Update di database
    $stmt = $pdo->prepare("UPDATE users SET language = ? WHERE id = ?");
    $stmt->execute([$_GET['lang'], $_SESSION['user_id']]);
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// --- AMBIL PARAMETER ---
$category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
$view = isset($_GET['view']) ? $_GET['view'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ambil semua kategori untuk filter
$categories = getCategories();

// Cari nama kategori berdasarkan ID (untuk judul halaman) - gunakan display_name yang sudah diterjemahkan
$category_name = '';
if($category_id) {
    foreach($categories as $cat) {
        if($cat['id'] == $category_id) {
            $category_name = $cat['display_name'] ?? $cat['name'];
            break;
        }
    }
}

// --- LOGIKA PENGAMBILAN DATA (DENGAN SEARCH) ---
if($view == 'mybooks') {
    // Ambil buku milik user sendiri
    $query = "SELECT books.*, categories.name as category_name, users.username 
              FROM books 
              LEFT JOIN categories ON books.category_id = categories.id 
              LEFT JOIN users ON books.user_id = users.id 
              WHERE books.user_id = ?";
    $params = [$_SESSION['user_id']];

    if(!empty($search)) {
        $query .= " AND (books.title LIKE ? OR books.author LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $page_title = t('my_books');
} else {
    // Ambil buku umum / berdasarkan kategori
    $query = "SELECT books.*, categories.name as category_name, users.username 
              FROM books 
              LEFT JOIN categories ON books.category_id = categories.id 
              LEFT JOIN users ON books.user_id = users.id 
              WHERE 1=1";
    $params = [];

    if($category_id) {
        $query .= " AND books.category_id = ?";
        $params[] = $category_id;
    }

    if(!empty($search)) {
        $query .= " AND (books.title LIKE ? OR books.author LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY books.upload_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = $category_id ? $category_name : t('all_books');
}

// Hitung statistik (tetap)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM books WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_book_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$total_books = $pdo->query("SELECT COUNT(*) as total FROM books")->fetch()['total'];
$total_categories = $pdo->query("SELECT COUNT(*) as total FROM categories")->fetch()['total'];

// Ambil ringkasan statistik bacaan untuk user
$readingSummary = getUserReadingSummary($_SESSION['user_id']);
// Format waktu baca
$total_seconds = isset($readingSummary['total_seconds']) ? intval($readingSummary['total_seconds']) : 0;
$hours = floor($total_seconds / 3600);
$minutes = floor(($total_seconds % 3600) / 60);
$seconds_display = $total_seconds % 60;
$time_read_label = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds_display);
$total_pages_read = isset($readingSummary['total_pages']) ? intval($readingSummary['total_pages']) : 0;
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-header">
    <h1><?php echo t('welcome'); ?>, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <a href="upload.php" class="btn btn-success"><?php echo t('upload_book'); ?></a>
</div>

<div class="dashboard-stats">
    <div class="stat-card">
        <i class="fas fa-book"></i>
        <h3><?php echo $user_book_count; ?></h3>
        <p><?php echo t('my_books'); ?></p>
    </div>
    
    <div class="stat-card">
        <i class="fas fa-book"></i>
        <h3><?php echo $total_books; ?></h3>
        <p><?php echo t('all_books'); ?></p>
    </div>
    
    <div class="stat-card">
        <i class="fas fa-graduation-cap"></i>
        <h3><?php echo $total_categories; ?></h3>
        <p><?php echo t('category'); ?></p>
    </div>

    <div class="stat-card">
        <i class="fas fa-clock"></i>
        <h3><?php echo htmlspecialchars($time_read_label); ?></h3>
        <p><?php echo t('time_read'); ?></p>
    </div>

    <div class="stat-card">
        <i class="fas fa-file-alt"></i>
        <h3><?php echo $total_pages_read; ?></h3>
        <p><?php echo t('pages_read'); ?></p>
    </div>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">
    <h2><?php echo $page_title; ?> <?php echo !empty($search) ? ' - Search: '.htmlspecialchars($search) : ''; ?></h2>
    
    <form action="dashboard.php" method="GET" style="display: flex; gap: 5px; min-width: 300px;">
        <?php if($category_id): ?>
            <input type="hidden" name="category" value="<?php echo $category_id; ?>">
        <?php endif; ?>
        <?php if($view == 'mybooks'): ?>
            <input type="hidden" name="view" value="mybooks">
        <?php endif; ?>
        
        <input type="text" name="search" class="form-control" 
               placeholder=<?php echo t('searchbook_placeholder');?>
               value="<?php echo htmlspecialchars($search); ?>"
               style="flex-grow: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        
        <button type="submit" class="btn btn-primary" style="padding: 8px 15px;">
            <i class="fas fa-search"></i>
        </button>
        
        <?php if(!empty($search)): ?>
            <a href="dashboard.php<?php echo $category_id ? '?category='.$category_id : ''; ?>" 
               class="btn btn-secondary" style="padding: 8px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">
                Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<div class="category-filter" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="dashboard.php<?php echo !empty($search) ? '?search='.urlencode($search) : ''; ?>" 
       class="btn <?php echo !$category_id ? 'btn-success' : 'btn-secondary'; ?>">
        <?php echo t('all_categories'); ?>
    </a>
    <?php foreach($categories as $category): ?>
        <a href="dashboard.php?category=<?php echo $category['id']; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
           class="btn <?php echo ($category_id == $category['id']) ? 'btn-success' : 'btn-secondary'; ?>">
            <?php echo htmlspecialchars($category['display_name'] ?? $category['name']); ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if(empty($books)): ?>
    <div class="alert alert-warning" style="padding: 20px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 4px;">
        <?php echo !empty($search) ? "Tidak ada buku yang cocok dengan kata kunci '" . htmlspecialchars($search) . "'" : t('no_results'); ?>
    </div>
<?php else: ?>
    <div class="book-grid">
        <?php foreach($books as $book): ?>
                    <div class="book-card">
                        <div class="book-cover">
                            <?php if (!empty($book['filepath']) && file_exists($book['filepath'])): ?>
                                <img src="cover.php?f=<?php echo urlencode(basename($book['filepath'])); ?>" alt="Cover" style="width:120px; height:auto; border-radius:6px; object-fit:cover;" />
                            <?php else: ?>
                                <i class="fas fa-book-open"></i>
                            <?php endif; ?>
                        </div>
                    <div class="book-info">
                    <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                    <div class="book-meta">
                        <p><strong><?php echo t('author'); ?>:</strong> <?php echo htmlspecialchars($book['author'] ?? 'Unknown'); ?></p>
                        <p><strong><?php echo t('category'); ?>:</strong> <?php echo htmlspecialchars(translateCategoryName($book['category_name']) ?? 'Uncategorized'); ?></p>
                        <p><strong><?php echo t('uploaded_by'); ?>:</strong> <?php echo htmlspecialchars($book['username'] ?? 'Unknown'); ?></p>
                        <p><strong><?php echo t('upload_date'); ?>:</strong> <?php echo date('d/m/Y', strtotime($book['upload_date'])); ?></p>
                    </div>
                    <div class="book-actions">
                        <a href="reader.php?id=<?php echo $book['id']; ?>" class="btn"><?php echo t('read'); ?></a>
                        <?php if($book['user_id'] == $_SESSION['user_id']): ?>
                            <a href="delete.php?id=<?php echo $book['id']; ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('<?php echo t('delete_confirm'); ?>')">
                                <?php echo t('delete'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>