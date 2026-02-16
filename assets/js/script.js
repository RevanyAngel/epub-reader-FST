// ============================================
// INISIALISASI SAAT HALAMAN DIMUAT
// ============================================
// Dijalankan ketika DOM siap untuk dimanipulasi
document.addEventListener('DOMContentLoaded', function() {
    // UPLOAD FILE - menampilkan nama file yang dipilih saat upload EPUB
    const fileInput = document.getElementById('epubFile');
    const fileName = document.getElementById('fileName');
    if (fileInput && fileName) {
        fileInput.addEventListener('change', function() {
            fileName.textContent = this.files.length > 0 ? this.files[0].name : 'No file chosen';
        });
    }
    
    // PENCARIAN - fitur search di halaman reader untuk mencari teks dalam buku
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            const query = searchInput.value.trim();
            if (query) {
                const url = new URL(window.location.href);
                url.searchParams.set('search', query);
                window.location.href = url.toString();
            }
        });
    }
    
    // NAVIGASI BAB - tombol untuk pindah ke bab sebelumnya/berikutnya
    const prevBtn = document.getElementById('prevChapter');
    const nextBtn = document.getElementById('nextChapter');
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            const ch = parseInt(this.dataset.chapter) || 1;
            if (ch > 1) window.location.href = `reader.php?id=${this.dataset.book}&chapter=${ch - 1}`;
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            const ch = parseInt(this.dataset.chapter) || 1;
            const total = parseInt(this.dataset.total) || 1;
            if (ch < total) window.location.href = `reader.php?id=${this.dataset.book}&chapter=${ch + 1}`;
        });
    }
    
    // HIGHLIGHT HASIL PENCARIAN - menyoroti kata yang dicari dalam teks halaman
    const searchTerm = new URLSearchParams(window.location.search).get('search');
    if (searchTerm) highlightText(searchTerm);

    // INISIALISASI HIGHLIGHT MANAGER - setup fitur highlight/anotasi pada buku
    // Mengambil ID buku dan bab dari data attribute atau parameter URL
    const bookId = document.querySelector('[data-book-id]')?.dataset.bookId || 
                   new URLSearchParams(window.location.search).get('book_id') ||
                   new URLSearchParams(window.location.search).get('id');
    const chapter = document.querySelector('[data-chapter]')?.dataset.chapter || 
                   new URLSearchParams(window.location.search).get('chapter') || 1;

    if (bookId && chapter) window.highlightManager = new HighlightManager(bookId, chapter);
});

// ============================================
// FUNGSI UTILITAS
// ============================================

// Fungsi untuk menyoroti (highlight) teks hasil pencarian dalam konten
// Mencari semua kemunculan term dan membungkusnya dengan tag <span class="highlight">
function highlightText(term) {
    const rc = document.querySelector('.reader-content');
    if (!rc) return;
    rc.innerHTML = rc.innerHTML.replace(new RegExp(`(${escapeRegExp(term)})`, 'gi'), '<span class="highlight">$1</span>');
}

// Fungsi untuk escape karakter spesial regex agar tidak terinterprasi sebagai operator regex
// Contoh: mengubah "(" menjadi "\(" untuk pencarian literal
function escapeRegExp(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

// Fungsi untuk menampilkan dialog konfirmasi sebelum menghapus item
// Mengembalikan true jika user klik OK, false jika klik Cancel
function confirmDelete(msg) { return confirm(msg || 'Are you sure?'); }

// Fungsi sederhana untuk menutup alert
function setupSimpleAlertClose() {
    document.querySelectorAll('.close-alert').forEach(button => {
        button.addEventListener('click', function() {
            const alert = this.closest('.alert');
            alert.style.display = 'none';
        });
    });
}

// Panggil saat DOM siap
document.addEventListener('DOMContentLoaded', setupSimpleAlertClose);

// ============================================
// HIGHLIGHT MANAGER - FITUR ANOTASI & HIGHLIGHT
// ============================================
// Kelas untuk mengelola semua fungsi highlight/penanda teks dalam buku
// Termasuk: membuat highlight, menyimpan ke database, menampilkan kembali, dan menghapus

class HighlightManager {
    // Constructor - inisialisasi dengan ID buku, nomor bab, dan halaman
    constructor(bookId, chapter, page = 0) {
        this.bookId = bookId;           // ID buku yang sedang dibaca
        this.chapter = chapter;         // Nomor bab saat ini
        this.page = page;               // Nomor halaman (opsional)
        this.highlights = [];           // Daftar highlight yang dimuat dari database
        this.originalHTML = null;       // Simpan HTML asli untuk restore saat re-render
        this.currentColor = '#fff59d';  // Warna highlight yang dipilih user (default kuning)
        this.colors = ['#fff59d', '#f8bbd0', '#c8e6c9', '#bbdefb', '#ffe0b2', '#d1c4e9']; // Palet warna highlight
        this.init();
    }

    // Inisialisasi: muat highlights dari database dan setup event listeners
    init() {
        this.loadHighlights();
        this.setupEventListeners();
    }

    // MUAT HIGHLIGHTS - ambil semua highlight untuk buku/bab ini dari database via AJAX
    async loadHighlights() {
        try {
            const res = await fetch(`ajax_highlight.php?action=list&book_id=${this.bookId}&chapter=${this.chapter}&page=${this.page}`);
            this.highlights = await res.json();
            this.renderHighlights(); // Tampilkan highlight setelah dimuat
        } catch (e) { console.error('Error loading highlights:', e); }
    }

    // RENDER HIGHLIGHTS - tampilkan semua highlight pada konten
    // Proses: restore HTML asli → apply setiap highlight → add click handlers
    renderHighlights() {
        const c = document.getElementById('content') || document.querySelector('.reader-content-body');
        if (!c) return;
        
        // Simpan HTML asli pada pemanggilan pertama agar bisa di-restore
        if (!this.originalHTML) this.originalHTML = c.innerHTML;
        
        // Mulai dari HTML asli (tanpa highlight sebelumnya)
        c.innerHTML = this.originalHTML;
        
        // Terapkan setiap highlight pada konten
        this.highlights.forEach(h => c.innerHTML = this.applyHighlight(c.innerHTML, h));
        
        // Tambahkan click handler ke setiap highlight untuk menampilkan menu delete
        document.querySelectorAll('.highlighted').forEach(el => {
            el.addEventListener('click', (e) => { e.stopPropagation(); this.showHighlightMenu(el); });
        });
    }

    // APPLY HIGHLIGHT - terapkan satu highlight pada teks HTML
    // Mencari teks pertama yang cocok dan membungkusnya dengan <mark> berwarna
    applyHighlight(html, h) {
        // Escape karakter regex khusus agar pencarian literal
        const esc = h.text.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&');
        // Regex untuk mencari text (case-insensitive) yang tidak dalam tag HTML
        const reg = new RegExp(`(${esc})(?![^<]*>)`, 'i');
        
        let replaced = false;
        return html.replace(reg, (m) => {
            if (replaced) return m; // Hanya replace kemunculan pertama
            replaced = true;
            return `<mark class="highlighted" data-id="${h.id}" style="background-color: ${h.color}; cursor: pointer; padding: 2px 4px; border-radius: 2px;" title="Click to delete">${m}</mark>`;
        });
    }

    // SETUP EVENT LISTENERS - atur handler untuk pemilihan teks dan tombol highlight
    setupEventListeners() {
        const c = document.getElementById('content') || document.querySelector('.reader-content-body');
        if (!c) return;
        
        this.lastSelectedText = null; // Simpan teks terakhir yang user pilih
        const btn = document.getElementById('highlightBtn'); // Tombol di header untuk save highlight
        const col = document.getElementById('highlightColor'); // Color picker di header
        
        // Mulai dengan tombol disabled sampai ada pemilihan teks
        if (btn) btn.disabled = true;
        
        // Click handler: ketika user klik tombol highlight di header
        if (btn) btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const t = this.lastSelectedText || window.getSelection().toString().trim();
            if (!t) return;
            const color = (col && col.value) ? col.value : this.currentColor;
            
            const id = await this.saveHighlight(t, color);
            if (id) {
                this.highlights.push({ id, text: t, color });
                this.renderHighlights();
                this.lastSelectedText = null;
                btn.disabled = true;
            }
        });
        
        // Mouse up handler: ketika user selesai memilih teks di konten
        // Tampilkan popup color picker dan enable tombol header
        c.addEventListener('mouseup', () => {
            const st = window.getSelection().toString().trim();
            this.lastSelectedText = st;
            if (st.length > 0) {
                if (btn) btn.disabled = false; // Enable tombol header
                this.showHighlightPrompt(st); // Tampilkan popup pemilihan warna
            } else if (btn) btn.disabled = true; // Disable jika tidak ada teks terpilih
        });
    }

    // SHOW HIGHLIGHT PROMPT - tampilkan popup menu untuk pilih warna dan confirm highlight
    async showHighlightPrompt(st) {
        // Buat element popup dengan palet warna
        const m = document.createElement('div');
        m.className = 'highlight-menu';
        m.innerHTML = `<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); min-width: 250px;"><div style="margin-bottom: 8px; font-size: 12px; color: #666; font-weight: bold;">Pilih warna:</div><div id="color-options" style="display: flex; gap: 4px; margin-bottom: 8px; flex-wrap: wrap;">${this.colors.map(c => `<button class="color-btn" data-color="${c}" style="width: 28px; height: 28px; background: ${c}; border: 2px solid #999; cursor: pointer; border-radius: 3px;"></button>`).join('')}</div><button id="confirm-highlight" style="background: #4CAF50; color: #fff; border: 0; padding: 8px 16px; border-radius: 3px; cursor: pointer; width: 100%; font-weight: bold;">✓ Highlight</button></div>`;
        document.body.appendChild(m);
        
        // Posisikan popup di bawah teks yang dipilih
        const r = window.getSelection().getRangeAt(0).getBoundingClientRect();
        m.style.cssText = `position: fixed; top: ${r.bottom + 10}px; left: ${r.left}px; z-index: 10000;`;
        
        // Handler: klik tombol warna untuk pilih warna highlight
        m.querySelectorAll('.color-btn').forEach(b => {
            b.addEventListener('click', (e) => {
                e.preventDefault();
                // Reset border semua tombol
                m.querySelectorAll('.color-btn').forEach(x => x.style.border = '2px solid #999');
                // Highlight tombol yang dipilih
                b.style.border = '3px solid #333';
                // Simpan warna yang dipilih
                this.currentColor = b.dataset.color;
            });
        });
        
        // Handler: klik tombol Highlight untuk save
        m.querySelector('#confirm-highlight').addEventListener('click', async (e) => {
            e.preventDefault();
            const id = await this.saveHighlight(st, this.currentColor);
            if (id) {
                this.highlights.push({ id, text: st, color: this.currentColor });
                this.renderHighlights();
            }
            // Tutup popup dan clear selection
            if (m.parentNode) document.body.removeChild(m);
            window.getSelection().removeAllRanges();
        });
        
        // Tutup popup saat user click di luar popup
        setTimeout(() => {
            const ch = (e) => {
                if (m && m.parentNode && !m.contains(e.target)) {
                    document.body.removeChild(m);
                    document.removeEventListener('click', ch);
                }
            };
            document.addEventListener('click', ch);
        }, 0);
    }

    // SAVE HIGHLIGHT - kirim highlight ke server untuk disimpan ke database
    async saveHighlight(t, c) {
        try {
            const fd = new FormData();
            fd.append('action', 'save');
            fd.append('book_id', this.bookId);
            fd.append('chapter', this.chapter);
            fd.append('page', this.page);
            fd.append('text', t);
            fd.append('color', c);
            
            const r = await fetch('ajax_highlight.php', { method: 'POST', body: fd });
            const res = await r.json();
            return res.status === 'ok' ? res.id : null;
        } catch (e) { console.error('Error saving:', e); return null; }
    }

    // SHOW HIGHLIGHT MENU - tampilkan popup menu untuk menghapus highlight
    // Dipanggil ketika user click pada text yang sudah di-highlight
    showHighlightMenu(el) {
        const id = el.dataset.id;
        const m = document.createElement('div');
        m.innerHTML = `<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"><button class="delete-highlight" data-id="${id}" style="background: #f44336; color: #fff; border: 0; padding: 8px 16px; border-radius: 3px; cursor: pointer; width: 100%; font-weight: bold;">× Hapus</button></div>`;
        
        // Posisikan popup di bawah highlight yang diklik
        const r = el.getBoundingClientRect();
        m.style.cssText = `position: fixed; top: ${r.bottom + 5}px; left: ${r.left}px; z-index: 10001;`;
        document.body.appendChild(m);
        
        // Handler: klik tombol Hapus
        m.querySelector('.delete-highlight').addEventListener('click', async (e) => {
            e.preventDefault();
            await this.deleteHighlight(id);
            this.highlights = this.highlights.filter(h => h.id != id); // Hapus dari array
            this.renderHighlights(); // Update tampilan
            if (m.parentNode) document.body.removeChild(m);
        });
        
        // Tutup popup saat user click di luar
        const ch = (e) => {
            if (m && m.parentNode && !m.contains(e.target) && !el.contains(e.target)) {
                document.body.removeChild(m);
                document.removeEventListener('click', ch);
            }
        };
        document.addEventListener('click', ch);
    }

    // DELETE HIGHLIGHT - kirim request ke server untuk hapus highlight dari database
    async deleteHighlight(id) {
        try {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            await fetch('ajax_highlight.php', { method: 'POST', body: fd });
        } catch (e) { console.error('Error deleting:', e); }
    }
}
