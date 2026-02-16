<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$chapter = isset($_GET['chapter']) ? intval($_GET['chapter']) : 1;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$stmt = $pdo->prepare("
    SELECT b.*, c.name as category_name, u.username 
    FROM books b 
    LEFT JOIN categories c ON b.category_id = c.id 
    LEFT JOIN users u ON b.user_id = u.id 
    WHERE b.id = ?
");
$stmt->execute([$book_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$book) {
    header('Location: dashboard.php');
    exit();
}

$content = getBookContent($book_id, $chapter);
$chapter_count = getChapterCount($book_id);

// Pagination sederhana: hitung total kata dan bagi menjadi halaman per bab
$words_per_page = 400; // sesuaikan jika perlu
if ($content) {
    $raw_text = $content['content'];
    $plain_text = strip_tags($raw_text);
    $plain_text = html_entity_decode($plain_text);
    $plain_text = preg_replace('/\s+/', ' ', trim($plain_text));
    $total_words = $plain_text === '' ? 0 : str_word_count($plain_text);
    // jika tidak ada kata, set total halaman bab = 0 (akan dianggap kosong)
    $total_pages_in_chapter = $total_words === 0 ? 0 : (int) ceil($total_words / $words_per_page);
    if ($page < 1) $page = 1;
    if ($total_pages_in_chapter > 0 && $page > $total_pages_in_chapter) $page = $total_pages_in_chapter;
    $words_arr = $plain_text === '' ? [] : preg_split('/\s+/', $plain_text);
    $start = ($page - 1) * $words_per_page;
    $page_words = array_slice($words_arr, $start, $words_per_page);
    $page_text = implode(' ', $page_words);
} else {
    $raw_text = '';
    $plain_text = '';
    $total_pages_in_chapter = 1;
    $page_text = '';
}

// Detect cover page (abaikan sebagai halaman baca)
$is_cover = false;
if ($content) {
    $title = $content['chapter_title'] ?? '';
    if (preg_match('/cover/i', $title) || preg_match('/<img/i', $raw_text) || preg_match('/cover/i', $plain_text)) {
        $is_cover = true;
        // hide page text and force single page
        $page_text = '';
        $total_pages_in_chapter = 1;
    }
}

// Jika halaman kosong atau cover, langsung lompat ke halaman/ bab berikutnya
if ((trim($page_text) === '' || $is_cover)) {
    // jangan lakukan redirect saat melakukan pencarian khusus (user mungkin ingin melihat hasil kosong)
    if (empty($search)) {
        // jika masih ada halaman berikutnya di bab yang sama
        if ($page < $total_pages_in_chapter) {
            $next_page = $page + 1;
            $target = "reader.php?id={$book_id}&chapter={$chapter}&page={$next_page}";
        } else {
            // lompat ke bab berikutnya jika ada
            if ($chapter < $chapter_count) {
                $next_chapter = $chapter + 1;
                $target = "reader.php?id={$book_id}&chapter={$next_chapter}&page=1";
            } else {
                // sudah di bab terakhir dan halaman terakhir — kembali ke dashboard
                $target = 'dashboard.php';
            }
        }
        header('Location: ' . $target);
        exit();
    }
}

// Build visible chapters list (only chapters that have content and are not cover)
$visible_chapters = [];
for ($i = 1; $i <= $chapter_count; $i++) {
    $c = getBookContent($book_id, $i);
    if (!$c) continue;
    $raw = $c['content'] ?? '';
    $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($raw))));
    $isCoverCheck = false;
    $cht = $c['chapter_title'] ?? '';
    if (preg_match('/cover/i', $cht) || preg_match('/<img/i', $raw) || preg_match('/cover/i', $plain)) {
        $isCoverCheck = true;
    }
    if ($plain !== '' && !$isCoverCheck) {
        $visible_chapters[] = $i;
    }
}
// determine visible index for current chapter (1-based)
$visible_chapter_index = null;
if (!empty($visible_chapters)) {
    $pos = array_search($chapter, $visible_chapters);
    if ($pos !== false) $visible_chapter_index = $pos + 1;
}

// Build mapping from real chapter number => visible index (1-based)
$visible_map = [];
foreach ($visible_chapters as $idx => $chapnum) {
    $visible_map[$chapnum] = $idx + 1;
}

if($search) {
    $results = searchInBook($book_id, $search);
}
?>

<?php include 'includes/header.php'; ?>

<style>
    .reader-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        /* Gunakan variabel agar otomatis berubah */
        background: var(--bg-card); 
        color: var(--text-main);
        line-height: 1.8;
        border-radius: 8px;
        box-shadow: 0 4px 15px var(--shadow);
    }
    
    .reader-header {
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 30px;
        padding-bottom: 20px;
    }

    /* Memastikan teks konten e-pub juga berubah warna */
    .reader-content-body, .reader-content-body p {
        color: var(--text-main);
    }

    .reader-footer {
        background-color: var(--bg-card);
    }

    .btn-dash {
        color: var(--text-main);
    }

    .reader-controls { display:flex; gap:8px; align-items:center; }
    .font-btn { border:1px solid var(--border-color); background:white; padding:6px 10px; border-radius:6px; cursor:pointer; font-weight:600; }
    .footer-bar { display:flex; gap:12px; align-items:center; justify-content:center; padding:12px; border-top:1px solid rgba(0,0,0,0.06); background: linear-gradient(180deg, rgba(255,255,255,0.02), transparent); }
    .footer-bar .btn { border-radius:8px; padding:8px 12px; background: var(--accent, #4b6cff); color: #fff; box-shadow: 0 4px 16px rgba(75,108,255,0.12); border: none; font-weight:600; }
    .footer-bar .btn.disabled { opacity: 0.5; pointer-events: none; }
    .footer-bar .btn-outline-primary { background: transparent; border: 1px solid var(--accent, #4b6cff); color: var(--accent, #4b6cff); }
    .reader-content-body p { margin: 0 0 1.1em 0; }
    .reader-meta small { color: var(--muted); }
</style>

<div class="reader-wrapper" style="display:flex; gap:50px; position:relative; left: -10px;">
    <aside class="chapter-sidebar" style="width:260px;">
        <h3 style="margin:0 0 12px; font-size:1.05rem;">
            <?php echo t('chapters') ?: 'Daftar Bab'; ?>
        </h3>
        <ul class="chapter-list" style="list-style:none; padding:0; margin:0; max-height:80vh; overflow:auto;">
            <?php if(!empty($visible_chapters)): ?>
                <?php foreach($visible_chapters as $ch):
                    $cinfo = getBookContent($book_id, $ch);
                    $displayIndex = isset($visible_map[$ch]) ? $visible_map[$ch] : $ch;
                    $ctitle = sprintf('%s %d', t('chapter'), $displayIndex);
                ?>
                    <li style="margin:6px 0;">
                        <a href="reader.php?id=<?php echo $book_id; ?>&chapter=<?php echo $ch; ?>&page=1" style="display:block; padding:8px 10px; border-radius:6px; color:var(--text-main); text-decoration:none; background:transparent; <?php echo ($ch == $chapter) ? 'font-weight:700; background:var(--bg-card); box-shadow:0 2px 8px var(--shadow);' : ''; ?>">
                            <?php echo htmlspecialchars($ctitle); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li style="color:var(--muted);"><?php echo t('no_chapters') ?: 'Tidak ada bab tersedia'; ?></li>
            <?php endif; ?>
        </ul>
    </aside>

    <div class="reader-container">
    <div class="reader-header" style="display:flex; gap:14px; align-items:center;">
        <?php if (!empty($book['filepath'])): ?>
            <img src="cover.php?f=<?php echo urlencode(basename($book['filepath'])); ?>" alt="Cover" class="book-cover" style="width:110px; height:auto; border-radius:6px; object-fit:cover;" />
        <?php endif; ?>
        <div style="flex:1;">
            <p class="author"><?php echo t('by'); ?></p>: <?php echo htmlspecialchars($book['author'] ?? 'Unknown'); ?></p>
        </div>
        <div class="bookmark-wrapper" style="margin-top: 10px;">
            <?php $bookmarked = isBookmarked($_SESSION['user_id'], $book_id, $chapter); ?>
            <button id="bookmarkBtn" class="btn <?php echo $bookmarked ? 'btn-warning' : 'btn-outline-secondary'; ?>" 
                    data-book="<?php echo $book_id; ?>" 
                    data-chapter="<?php echo $chapter; ?>">
                <i class="<?php echo $bookmarked ? 'fas' : 'far'; ?> fa-bookmark"></i> 
                <span id="bookmarkText">
                    <?php echo $bookmarked ? t('bookmark_saved') : t('bookmark_save_page'); ?>
                </span>
            </button>
        </div>
        <div style="margin-top: 15px; display:flex; gap:12px; justify-content:space-between; align-items:center;">
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="<?php echo t('search_placeholder'); ?>" 
                       value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="display:inline-block; width:auto;">
                <button id="searchBtn" class="btn btn-primary"><?php echo t('search'); ?></button>
            </div>

            <div class="reader-controls">
                <button id="fontDec" class="font-btn" title="<?php echo t('decrease_font'); ?>">A-</button>
                <button id="fontReset" class="font-btn" title="<?php echo t('reset_font'); ?>">A</button>
                <button id="fontInc" class="font-btn" title="<?php echo t('increase_font'); ?>">A+</button>
                <!-- Highlight controls -->
                <input type="color" id="highlightColor" value="#fff59d" title="Pilih warna highlight" style="width:40px; height:34px; padding:0; border-radius:6px; border:1px solid var(--border-color);">
                <button id="highlightBtn" class="font-btn" title="Highlight teks" disabled><?php echo t('highlight'); ?></button>
            </div>
        </div>
        
    </div>
    
    <div class="reader-content">
        <?php if($search): ?>
            <?php if(!empty($results)): ?>
            <h2><?php echo t('search_results'); ?>: "<?php echo htmlspecialchars($search); ?>"</h2>
            <?php foreach($results as $result): ?>
                <div class="result-item" style="border-bottom: 1px solid #eee; padding: 15px 0;">
                    <h3><?php echo t('chapter'); ?> <?php echo $result['chapter_number']; ?></h3>
                    <p class="excerpt">...</p>
                    <a href="reader.php?id=<?php echo $book_id; ?>&chapter=<?php echo $result['chapter_number']; ?>" class="btn btn-sm btn-outline-primary">Buka Bab</a>
                </div>
            <?php endforeach; ?>

            <?php else: ?>
                <?php /* no output when search has no results (hide message) */ ?>
            <?php endif; ?>
        <?php elseif($content): ?>
            <h2 class="chapter-title-centered" style="text-align:center; margin-bottom:6px; font-size:1.35rem; font-weight:700;">
                <?php if ($visible_chapter_index !== null) { echo sprintf('%s %d', t('chapter'), $visible_chapter_index); } else { echo sprintf('%s %d', t('chapter'), $chapter); } ?>
            </h2>
            <div style="text-align:center; margin-bottom:14px;">
                <div style="font-size:0.95rem; color:var(--muted);">
                    <?php echo sprintf('%s %d', t('page'), $page); ?>
                </div>
            </div>

            <div class="reader-meta" style="display:flex; justify-content:flex-end; align-items:center; gap:18px; margin-bottom:10px;">
                <div>
                    <?php if(isset($_SESSION['user_id'])):
                        $bookSummary = getUserBookReadingSummary($_SESSION['user_id'], $book_id);
                        $secs = isset($bookSummary['total_seconds']) ? intval($bookSummary['total_seconds']) : 0;
                        $h = floor($secs/3600); $m = floor(($secs%3600)/60); $s = $secs%60;
                        $time_label = sprintf('%02d:%02d:%02d', $h,$m,$s);
                        $pages_read = isset($bookSummary['total_pages']) ? intval($bookSummary['total_pages']) : 0;
                    ?>
                        <small><?php echo t('pages_read'); ?>: <strong><?php echo $pages_read; ?></strong></small>
                        &nbsp; &middot; &nbsp;
                        <small><?php echo t('time_read'); ?>: <strong><?php echo htmlspecialchars($time_label); ?></strong></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="reader-content-body" style="font-family: Georgia, 'Times New Roman', serif; font-size:16px; line-height:1.9; color:var(--text-main);" id="content" data-book-id="<?php echo $book_id; ?>" data-chapter="<?php echo $chapter; ?>" data-page="<?php echo $page; ?>">
                <?php
                // Tampilkan teks halaman saat ini (plain-text yang sudah diproses)
                if (trim($page_text) !== '') {
                    // bagi menjadi paragraf berdasarkan kalimat sederhana
                    $sentences = preg_split('/(?<=[.?!])\s+/', $page_text);
                    $buffer = '';
                    foreach ($sentences as $i => $sent) {
                        $buffer .= $sent . ' ';
                        // buat paragraf setiap 4 kalimat untuk kenyamanan
                        if ((($i+1) % 4) === 0) {
                            echo '<p>' . htmlspecialchars(trim($buffer)) . '</p>';
                            $buffer = '';
                        }
                    }
                    if (trim($buffer) !== '') echo '<p>' . htmlspecialchars(trim($buffer)) . '</p>';
                } else {
                    // fallback jika kosong — do not display 'no results' message
                    // intentionally left blank to avoid showing placeholder text
                }
                ?>
            </div>
            
        <?php else: ?>
            <div class="alert alert-warning"><?php echo t('no_results'); ?></div>
        <?php endif; ?>
    </div>
    
            <div class="reader-footer">
                <div class="footer-bar" style="margin-bottom:10px;">
                    <div style="display:flex; gap:8px; align-items:center; justify-content:center;">
                        <a href="reader.php?id=<?php echo $book_id; ?>&chapter=<?php echo $chapter; ?>&page=<?php echo max(1, $page-1); ?>" class="btn btn-outline-primary page-nav prev-page <?php echo ($page <= 1) ? 'disabled' : ''; ?>">&larr; <?php echo t('prev_page'); ?></a>
                        <?php
                        if ($page >= $total_pages_in_chapter) {
                            $next_chapter = min($chapter + 1, $chapter_count);
                            $next_href = "reader.php?id={$book_id}&chapter={$next_chapter}&page=1";
                        } else {
                            $next_page = $page + 1;
                            $next_href = "reader.php?id={$book_id}&chapter={$chapter}&page={$next_page}";
                        }
                        ?>
                        <a href="<?php echo $next_href; ?>" class="btn btn-outline-primary page-nav next-page <?php echo ($page >= $total_pages_in_chapter && $chapter >= $chapter_count) ? 'disabled' : ''; ?>"><?php echo t('next_page'); ?> &rarr;</a>
                    </div>
                </div>

                <div class="btn-dash" style="text-align: center;">
                    <a href="dashboard.php" class="btn btn-link"><?php echo t('back_to_dashboard'); ?></a>
                </div>
            </div>
    </div>
</div>
<script>
document.getElementById('bookmarkBtn').addEventListener('click', function() {
    const btn = this;
    const icon = btn.querySelector('i');
    const text = document.getElementById('bookmarkText');
    const formData = new FormData();
    formData.append('book_id', btn.dataset.book);
    formData.append('chapter', btn.dataset.chapter);

    fetch('ajax_bookmark.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'added') {
            btn.classList.replace('btn-outline-secondary', 'btn-warning');
            icon.classList.replace('far', 'fas');
            text.innerText = 'Tersimpan';
        } else if(data.status === 'removed') {
            btn.classList.replace('btn-warning', 'btn-outline-secondary');
            icon.classList.replace('fas', 'far');
            text.innerText = 'Simpan Halaman';
        }
    })
    .catch(error => console.error('Error:', error));
});
</script>
<script>
// Statistik bacaan: hitung kata/halaman + waktu baca
(() => {
    const start = Date.now();
    const bookId = <?php echo json_encode($book_id); ?>;
    const chapterNum = <?php echo json_encode($chapter); ?>;
    const userId = <?php echo isset($_SESSION['user_id']) ? json_encode($_SESSION['user_id']) : 'null'; ?>;

    function getWordsAndPages() {
        const el = document.querySelector('.reader-content-body');
        if(!el) return { words: 0, pages: 0 };
        const text = el.innerText || el.textContent || '';
        const words = text.trim().split(/\s+/).filter(Boolean).length;
        // Karena kita menggunakan pagination server-side, setiap unload = 1 halaman dibaca
        const pages = 1;
        return { words, pages };
    }

    let statsSent = false;
    let skipStatsOnUnload = false;
    function sendStats(seconds, words, pages) {
        if(!userId) return;
        if (statsSent) return; // prevent duplicate sends

        const data = new FormData();
        data.append('book_id', bookId);
        data.append('chapter', chapterNum);
        data.append('page', <?php echo json_encode($page); ?>);
        data.append('words', words);
        data.append('pages', pages);
        data.append('seconds', seconds);

        const url = 'ajax_stats.php';

        // If this is a cover page, skip sending (server-side also tolerates)
        const isCover = <?php echo json_encode($is_cover ? true : false); ?>;
        if (isCover) {
            statsSent = true;
            return;
        }

        if (navigator.sendBeacon) {
            const payload = new URLSearchParams();
            for (const pair of data.entries()) payload.append(pair[0], pair[1]);
            const ok = navigator.sendBeacon(url, payload);
            statsSent = true;
            return;
        }

        // Fallback: fetch with keepalive
        fetch(url, { method: 'POST', body: data, keepalive: true }).catch(()=>{});
        statsSent = true;
    }

    // NOTE: Do not send stats on refresh/unload to avoid incrementing pages_read on refresh.
    // Statistics are sent only when user clicks the forward navigation (next page / next chapter).

    // Only send when clicking forward navigation (next page)
    document.querySelectorAll('.page-nav.next-page').forEach(a => {
        a.addEventListener('click', function(e) {
            if (statsSent) return; // already sent
            const seconds = Math.round((Date.now() - start) / 1000);
            const { words, pages } = getWordsAndPages();
            sendStats(seconds, words, pages);
            // allow navigation to proceed
        });
    });

    // When user clicks any previous/back links, skip sending on unload
    document.querySelectorAll('.page-nav.prev-page').forEach(a => {
        a.addEventListener('click', function(){
            skipStatsOnUnload = true;
        });
    });
})();
</script>
<script>
// Font size controls
(function(){
    const content = document.querySelector('.reader-content-body');
    if(!content) return;
    const defaultSize = parseInt(localStorage.getItem('readerFontSize') || 16, 10);
    let size = defaultSize;
    function apply() {
        content.style.fontSize = size + 'px';
    }
    document.getElementById('fontInc').addEventListener('click', function(){ size += 2; apply(); localStorage.setItem('readerFontSize', size); });
    document.getElementById('fontDec').addEventListener('click', function(){ size = Math.max(12, size - 2); apply(); localStorage.setItem('readerFontSize', size); });
    document.getElementById('fontReset').addEventListener('click', function(){ size = 16; apply(); localStorage.setItem('readerFontSize', size); });
    apply();
})();
</script>
<?php include 'includes/footer.php'; ?>