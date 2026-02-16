<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect jika belum login
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Proses upload
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $category_id = intval($_POST['category_id']);
    
    // Validasi file
    if(isset($_FILES['epub_file']) && $_FILES['epub_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['epub_file'];
        
        // Cek ekstensi file
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if($file_ext != 'epub') {
            $_SESSION['message'] = t('invalid_file_type');
            $_SESSION['message_type'] = 'error';
        }
        // Cek ukuran file (max 20MB)
        elseif($file['size'] > 20 * 1024 * 1024) {
            $_SESSION['message'] = t('file_too_large');
            $_SESSION['message_type'] = 'error';
        }
        else {
            // Generate nama file unik
            $filename = uniqid() . '_' . preg_replace('/[^A-Za-z0-9\.]/', '_', $file['name']);
            $filepath = 'uploads/' . $filename;
            
            // Pindahkan file ke folder uploads
            if(move_uploaded_file($file['tmp_name'], $filepath)) {
                try {
                    // Simpan informasi buku ke database
                    $stmt = $pdo->prepare("
                        INSERT INTO books (title, author, category_id, user_id, filename, filepath, file_size) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $title,
                        $author,
                        $category_id,
                        $_SESSION['user_id'],
                        $file['name'],
                        $filepath,
                        $file['size']
                    ]);
                    
                    $book_id = $pdo->lastInsertId();
                    
                    // Parse file EPUB dan simpan kontennya
                    $chapters = parseEpub($filepath);
                    if($chapters) {
                        saveBookContent($book_id, $chapters);
                    }
                    
                    $_SESSION['message'] = t('upload_success');
                    $_SESSION['message_type'] = 'success';
                    header('Location: dashboard.php');
                    exit();
                    
                } catch(PDOException $e) {
                    $_SESSION['message'] = "Error: " . $e->getMessage();
                    $_SESSION['message_type'] = 'error';
                    // Hapus file yang sudah diupload
                    if(file_exists($filepath)) {
                        unlink($filepath);
                    }
                }
            } else {
                $_SESSION['message'] = t('upload_error');
                $_SESSION['message_type'] = 'error';
            }
        }
    } else {
        $_SESSION['message'] = "Please select a file";
        $_SESSION['message_type'] = 'error';
    }
}

$categories = getCategories();
?>

<?php include 'includes/header.php'; ?>

<div class="upload-container" style="width:500px;">
    <h1><?php echo t('upload_book'); ?></h1>
    
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title"><?php echo t('title'); ?> *</label>
            <input type="text" id="title" name="title" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="author"><?php echo t('author'); ?></label>
            <input type="text" id="author" name="author" class="form-control">
        </div>
        
        <div class="form-group">
            <label for="category_id"><?php echo t('category'); ?> *</label>
            <select id="category_id" name="category_id" class="form-control" required>
                <option value=""><?php echo t('select_category'); ?></option>
                <?php foreach($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>">
                        <?php echo htmlspecialchars($category['display_name'] ?? $category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label><?php echo t('choose_file'); ?> *</label>
            <div class="file-upload">
                <input type="file" id="epubFile" name="epub_file" accept=".epub" required>
                <label for="epubFile">
                    <i class="fas fa-cloud-upload-alt"></i> <?php echo t('choose_file'); ?>
                </label>
                <div id="fileName" class="file-name"><?php echo t('no_file_chosen'); ?></div>
                <small><?php echo t('file_type'); ?></small>
            </div>
        </div>
        
        <button type="submit" class="btn btn-block btn-success"><?php echo t('upload'); ?></button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>