<?php
require_once __DIR__ . '/../config/database.php';

// Fungsi untuk mendapatkan terjemahan
function t($key) {
    global $pdo;
    
    // Cek session language
    $lang = isset($_SESSION['language']) ? $_SESSION['language'] : 'id';
    
    // Load file bahasa
    $languageFile = __DIR__ . "/../languages/{$lang}.php";
    
    if (file_exists($languageFile)) {
        $translations = require($languageFile);
        return isset($translations[$key]) ? $translations[$key] : $key;
    }
    
    return $key;
}

// Fungsi untuk menerjemahkan nama kategori berdasarkan bahasa saat ini
function translateCategoryName($categoryName) {
    return t($categoryName);
}

// Fungsi untuk parsing file EPUB (sederhana)
function parseEpub($filepath) {
    // Untuk implementasi nyata, gunakan library seperti PHPePub
    // Ini adalah implementasi sederhana untuk demo
    
    $chapters = [];
    
    // Ekstrak file ZIP (EPUB adalah ZIP)
    $zip = new ZipArchive;
    if ($zip->open($filepath) === TRUE) {
        // Cari file container.xml
        $container = $zip->getFromName('META-INF/container.xml');
        
        if ($container) {
            // Parse container untuk mendapatkan OPF file
            preg_match('/full-path="([^"]+)"/', $container, $matches);
            $opfPath = $matches[1] ?? 'OEBPS/content.opf';
            
            // Baca OPF file
            $opf = $zip->getFromName($opfPath);
            
            if ($opf) {
                // Parse manifest untuk mendapatkan semua file
                if (preg_match('/<manifest>(.*?)<\/manifest>/s', $opf, $manifestMatch)) {
                    $manifest = $manifestMatch[1];
                    
                    // Ambil semua item
                    preg_match_all('/<item[^>]+href="([^"]+)"[^>]+media-type="([^"]+)"[^>]*>/', $manifest, $items, PREG_SET_ORDER);
                    
                    foreach ($items as $item) {
                        $href = $item[1];
                        $mediaType = $item[2];
                        
                        // Hanya ambil file HTML/XHTML
                        if (strpos($mediaType, 'html') !== false || strpos($mediaType, 'xhtml') !== false) {
                            $content = $zip->getFromName(dirname($opfPath) . '/' . $href);
                            
                            if ($content) {
                                // Bersihkan konten dari tag HTML
                                $cleanContent = strip_tags($content);
                                $cleanContent = html_entity_decode($cleanContent);
                                $cleanContent = preg_replace('/\s+/', ' ', $cleanContent);
                                
                                $chapters[] = [
                                    'title' => basename($href, '.xhtml'),
                                    'content' => $cleanContent
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        $zip->close();
    }
    
    return $chapters;
}

// Fungsi untuk menyimpan konten buku ke database
function saveBookContent($bookId, $chapters) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO book_content (book_id, chapter_title, chapter_number, content) VALUES (?, ?, ?, ?)");
        
        $chapterNum = 1;
        foreach ($chapters as $chapter) {
            $stmt->execute([$bookId, $chapter['title'], $chapterNum, $chapter['content']]);
            $chapterNum++;
        }
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Fungsi untuk mendapatkan konten buku
function getBookContent($bookId, $chapter = 1) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM book_content WHERE book_id = ? AND chapter_number = ?");
    $stmt->execute([$bookId, $chapter]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan jumlah bab
function getChapterCount($bookId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM book_content WHERE book_id = ?");
    $stmt->execute([$bookId]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

// Fungsi untuk mencari teks dalam buku
function searchInBook($bookId, $query) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM book_content 
        WHERE book_id = ? AND content LIKE ? 
        ORDER BY chapter_number
    ");
    
    $searchTerm = "%" . $query . "%";
    $stmt->execute([$bookId, $searchTerm]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan semua kategori
function getCategories() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Terjemahkan nama kategori sesuai bahasa yang dipilih
    foreach ($categories as &$category) {
        $category['display_name'] = translateCategoryName($category['name']);
    }
    
    return $categories;
}

// Fungsi untuk mendapatkan kategori berdasarkan ID
function getCategoryById($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan buku berdasarkan kategori (DIPERBAIKI)
function getBooksByCategory($categoryId = null) {
    global $pdo;
    
    if ($categoryId) {
        $stmt = $pdo->prepare("
            SELECT b.*, c.name as category_name, u.username 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.id 
            LEFT JOIN users u ON b.user_id = u.id 
            WHERE b.category_id = ? 
            ORDER BY b.upload_date DESC
        ");
        $stmt->execute([$categoryId]);
    } else {
        $stmt = $pdo->query("
            SELECT b.*, c.name as category_name, u.username 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.id 
            LEFT JOIN users u ON b.user_id = u.id 
            ORDER BY b.upload_date DESC
        ");
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan buku milik user
function getUserBooks($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT b.*, c.name as category_name 
        FROM books b 
        LEFT JOIN categories c ON b.category_id = c.id 
        WHERE b.user_id = ? 
        ORDER BY b.upload_date DESC
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Memeriksa apakah halaman saat ini sudah dibookmark oleh user
 */
function isBookmarked($user_id, $book_id, $chapter) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND book_id = ? AND chapter_number = ?");
    $stmt->execute([$user_id, $book_id, $chapter]);
    return $stmt->fetch() ? true : false;
}

/**
 * Mengambil semua bookmark milik user tertentu (opsional untuk di dashboard)
 */
function getUserBookmarks($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT b.*, bk.title, bk.author 
        FROM bookmarks b 
        JOIN books bk ON b.book_id = bk.id 
        WHERE b.user_id = ? 
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    // -------------------------
    // Statistik bacaan: helper
    // -------------------------
    function saveReadingStat($user_id, $book_id, $chapter_number, $page_number = 1, $words = 0, $pages = 0, $seconds = 0) {
        global $pdo;

        // Buat tabel jika belum ada (migrasi ringan) — sertakan page_number dan unique key
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS reading_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                book_id INT NOT NULL,
                chapter_number INT DEFAULT NULL,
                page_number INT DEFAULT 1,
                words INT DEFAULT 0,
                pages INT DEFAULT 0,
                seconds INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY ux_user_book_chapter_page (user_id, book_id, chapter_number, page_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Exception $e) {
            // ignore
        }

        // Jika tabel sudah ada tetapi tidak memiliki kolom page_number atau index, coba tambahkan (toleransi)
        try {
            // try to add page_number column if not present (some MySQL versions don't support IF NOT EXISTS)
            $pdo->exec("ALTER TABLE reading_stats ADD COLUMN page_number INT DEFAULT 1");
        } catch (Exception $e) {
            // ignore errors (column may already exist)
        }
        try {
            $pdo->exec("ALTER TABLE reading_stats ADD UNIQUE KEY ux_user_book_chapter_page (user_id, book_id, chapter_number, page_number)");
        } catch (Exception $e) {
            // ignore if index exists or alter not supported
        }

        // Gunakan upsert: jika sudah ada record untuk user/book/chapter/page, tambahkan nilai baru
        try {
            $sql = "INSERT INTO reading_stats (user_id, book_id, chapter_number, page_number, words, pages, seconds, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        words = words + VALUES(words),
                        pages = pages + VALUES(pages),
                        seconds = seconds + VALUES(seconds),
                        created_at = NOW()";

            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$user_id, $book_id, $chapter_number, $page_number, $words, $pages, $seconds]);
        } catch (Exception $e) {
            // fallback insert (no upsert support)
            try {
                $stmt = $pdo->prepare("INSERT INTO reading_stats (user_id, book_id, chapter_number, page_number, words, pages, seconds) VALUES (?, ?, ?, ?, ?, ?, ?)");
                return $stmt->execute([$user_id, $book_id, $chapter_number, $page_number, $words, $pages, $seconds]);
            } catch (Exception $e2) {
                return false;
            }
        }
    }

    function getUserReadingSummary($user_id) {
        global $pdo;
        // Pastikan tabel reading_stats ada agar query tidak error
        $pdo->exec("CREATE TABLE IF NOT EXISTS reading_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            chapter_number INT DEFAULT NULL,
            words INT DEFAULT 0,
            pages INT DEFAULT 0,
            seconds INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $stmt = $pdo->prepare("SELECT
            COALESCE(SUM(pages),0) AS total_pages,
            COALESCE(SUM(seconds),0) AS total_seconds,
            COUNT(*) AS sessions
            FROM reading_stats WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    function getUserBookReadingSummary($user_id, $book_id) {
        global $pdo;

        // Pastikan tabel ada
        $pdo->exec("CREATE TABLE IF NOT EXISTS reading_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            chapter_number INT DEFAULT NULL,
            words INT DEFAULT 0,
            pages INT DEFAULT 0,
            seconds INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $stmt = $pdo->prepare("SELECT
            COALESCE(SUM(pages),0) AS total_pages,
            COALESCE(SUM(seconds),0) AS total_seconds,
            COUNT(*) AS sessions
            FROM reading_stats WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$user_id, $book_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
?>

