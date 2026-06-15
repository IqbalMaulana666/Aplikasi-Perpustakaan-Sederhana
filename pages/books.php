<?php
require_once '../includes/session_check.php';
require_once '../includes/functions.php';

$book_save_api_path = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/') . '/api/save-book.php';
$book_save_api_url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . $book_save_api_path;
$book_covers_web_prefix = '../' . BOOK_COVERS_WEB_PATH;

$search = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? '';

$query = "SELECT * FROM books WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    // Search keyword anywhere in title or author
    // Contoh: "alchemist" akan match "The Alchemist", "Alchemist Study", dll
    $search_pattern = '%' . $search . '%';
    $query .= " AND (title LIKE ? OR author LIKE ?)";
    $params = [$search_pattern, $search_pattern];
    $types = 'ss';
}

if (!empty($filter_category)) {
    $query .= " AND category = ?";
    $params[] = $filter_category;
    $types .= 's';
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- Theme Detection - MUST BE FIRST to prevent white flash -->
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Buku - BukuKita | SMP TAQ SADAMIYYAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body class="dashboard-layout page-table-only">
    <div class="main-layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="book-logo">
                    <i class="fas fa-book-open"></i>
                </div>
                <span class="brand-title">BukuKita</span>
            </div>
            <nav>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> <span>Dashboard</span>
                </a>
                <a href="books.php" class="nav-link active">
                    <i class="fas fa-book"></i> <span>Manajemen Buku</span>
                </a>
                <a href="students.php" class="nav-link">
                    <i class="fas fa-users"></i> <span>Manajemen Siswa</span>
                </a>
                <a href="borrowing.php" class="nav-link">
                    <i class="fas fa-hand-holding"></i> <span>Peminjaman</span>
                </a>
                <a href="returning.php" class="nav-link">
                    <i class="fas fa-undo"></i> <span>Pengembalian</span>
                </a>
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-pie"></i> <span>Laporan</span>
                </a>
            </nav>
        </aside>

        <main class="content-wrapper">
            <!-- Navbar -->
            <nav class="navbar">
                <div class="navbar-content">
                    <div class="navbar-title">
                        <i class="fas fa-bars" style="cursor: pointer; display: none;" onclick="toggleSidebar()" id="sidebarToggle"></i>
                        <img src="../assets/uploads/logo/logo-transparent.png" alt="Logo SMP TAQ SADAMIYYAH" class="school-logo">
                        BukuKita | SMP TAQ SADAMIYYAH
                    </div>
                    <div class="navbar-user">
                        <button class="theme-toggle-btn" title="Switch Theme">
                            <i class="fas fa-sun"></i>
                        </button>
                        <span class="user-name">Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <a href="../auth/logout.php" class="btn-logout">Logout</a>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <section class="dashboard-content">

        <div class="page-header">
            <h2>Manajemen Buku</h2>
            <p>Kelola koleksi buku perpustakaan sekolah</p>
        </div>

        <!-- Book Management Card -->
        <div class="card table-card">
            <div class="card-header">
                <h5>Daftar Buku</h5>
                <button type="button" class="btn btn-primary" onclick="addBook()">
                    <i class="fas fa-plus"></i> Tambah Buku
                </button>
            </div>
            
            <!-- Search & Filter -->
            <div class="card-body" style="border-bottom: 1px solid var(--border-color);">
                <div class="search-box" style="margin-bottom: 0;">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" id="searchInput" placeholder="Cari judul atau pengarang..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select class="filter-select" id="categoryFilter">
                        <option value="" <?php echo $filter_category === '' ? 'selected' : ''; ?>>Semua Kategori</option>
                        <option value="Fiksi" <?php echo $filter_category === 'Fiksi' ? 'selected' : ''; ?>>Fiksi</option>
                        <option value="Nonfiksi" <?php echo $filter_category === 'Nonfiksi' ? 'selected' : ''; ?>>Nonfiksi</option>
                        <option value="Sejarah" <?php echo $filter_category === 'Sejarah' ? 'selected' : ''; ?>>Sejarah</option>
                        <option value="Akademik" <?php echo $filter_category === 'Akademik' ? 'selected' : ''; ?>>Akademik</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Judul</th>
                            <th>Pengarang</th>
                            <th>Kategori</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="booksTable">
                        <?php if (empty($books)): ?>
                            <tr>
                                <td colspan="7" style="padding: 40px;">
                                    <div style="width: 100%; display: flex; justify-content: center; align-items: center;">Belum ada buku</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($books as $book): ?>
                            <tr>
                                <td class="book-cover-td">
                                    <?php echo renderBookCoverThumbnail($book, '../'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo htmlspecialchars($book['category']); ?></td>
                                <td><?php echo $book['stock']; ?> buku</td>
                                <td>
                                    <?php if ((int)$book['stock'] <= 0): ?>
                                        <span class="badge-soft badge-soft-danger">Habis</span>
                                    <?php elseif ($book['status'] === 'available'): ?>
                                        <span class="badge-soft badge-soft-success">Tersedia</span>
                                    <?php else: ?>
                                        <span class="badge-soft badge-soft-warning">Dipinjam</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editBook(<?php echo $book['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteBook(<?php echo $book['id']; ?>)">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

            </section>
        </main>
    </div>

    <!-- Book Modal -->
    <div id="bookModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="bookModalTitle">Tambah Buku</h5>
                <button type="button" class="btn-close" data-close-modal style="border: none; background: none; font-size: 24px; cursor: pointer; color: var(--text-main);"><i class="fas fa-times"></i></button>
            </div>
            <form id="bookForm" method="POST" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="action" id="bookFormAction" value="add">
                <input type="hidden" name="id" id="bookFormId" value="">
                <input type="hidden" name="old_cover" id="bookOldCover" value="">
                <div class="form-group">
                    <label for="bookTitle" class="form-label">Judul Buku</label>
                    <input type="text" class="form-control" id="bookTitle" name="title" required>
                </div>
                <div class="form-group">
                    <label for="bookAuthor" class="form-label">Pengarang</label>
                    <input type="text" class="form-control" id="bookAuthor" name="author" required>
                </div>
                <div class="form-group">
                    <label for="bookCategory" class="form-label">Kategori</label>
                    <select class="form-control" id="bookCategory" name="category" required>
                        <option value="">Pilih Kategori</option>
                        <option value="Fiksi">Fiksi</option>
                        <option value="Nonfiksi">Nonfiksi</option>
                        <option value="Sejarah">Sejarah</option>
                        <option value="Akademik">Akademik</option>
                    </select>
                </div>
                <div id="bookCoverPreview" class="book-cover-modal-preview" style="display: none;">
                    <label class="form-label" id="bookCoverPreviewLabel">Cover saat ini</label>
                    <div>
                        <img id="bookCoverPreviewImg" src="" alt="" class="book-cover-modal-img" width="320" height="480" loading="lazy" decoding="async">
                    </div>
                </div>
                <div class="form-group">
                    <label for="cover" class="form-label">Cover Buku (JPG, JPEG, PNG, WebP — maks. 2MB)</label>
                    <input type="file" class="form-control-file" id="cover" name="cover" accept=".jpg,.jpeg,.png,.webp">
                </div>
                <div class="form-group">
                    <label for="bookStock" class="form-label">Stok Awal</label>
                    <input type="number" class="form-control" id="bookStock" name="stock" min="1" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" data-close-modal>Batal</button>
                    <button type="submit" class="btn-primary" id="bookSubmitBtn">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theme-toggle.js"></script>
    <script>
        setActiveNav('a[href="books.php"]');

        let editingBookId = null;
        let bookFormSubmitting = false;
        let coverPreviewObjectUrl = null;
        const bookForm = document.getElementById('bookForm');
        const bookPostUrl = <?php echo json_encode($book_save_api_url, JSON_UNESCAPED_SLASHES); ?>;
        const bookCoversWebPrefix = <?php echo json_encode($book_covers_web_prefix, JSON_UNESCAPED_SLASHES); ?>;
        const COVER_MAX_BYTES = 2 * 1024 * 1024;
        const COVER_ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp'];
        const COVER_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/pjpeg', 'image/x-png'];

        function revokeCoverPreviewUrl() {
            if (coverPreviewObjectUrl) {
                URL.revokeObjectURL(coverPreviewObjectUrl);
                coverPreviewObjectUrl = null;
            }
        }

        function getCoverFileExtension(file) {
            const parts = (file.name || '').split('.');
            return (parts.length > 1 ? parts.pop() : '').toLowerCase();
        }

        function isAllowedCoverFile(file) {
            const ext = getCoverFileExtension(file);
            if (COVER_ALLOWED_EXT.includes(ext)) {
                return true;
            }
            if (!file.type || file.type === 'application/octet-stream') {
                return COVER_ALLOWED_EXT.includes(ext);
            }
            return COVER_ALLOWED_MIME.includes(file.type);
        }

        function showCoverPreviewFromFile(file) {
            console.log('showCoverPreviewFromFile called with file:', file.name);
            const prev = document.getElementById('bookCoverPreview');
            const prevImg = document.getElementById('bookCoverPreviewImg');
            const prevLabel = document.getElementById('bookCoverPreviewLabel');
            
            console.log('Elements found:', {prev, prevImg, prevLabel});
            
            if (!prev || !prevImg || !prevLabel) {
                console.error('One or more preview elements not found!');
                return;
            }
            
            revokeCoverPreviewUrl();
            coverPreviewObjectUrl = URL.createObjectURL(file);
            console.log('Cover preview URL created:', coverPreviewObjectUrl);
            
            prevImg.src = coverPreviewObjectUrl;
            prevImg.alt = 'Pratinjau cover';
            prevLabel.textContent = editingBookId ? 'Pratinjau cover baru' : 'Pratinjau cover';
            prev.style.display = 'block';
            
            console.log('Preview shown, display style:', prev.style.display);
        }

        function addBook() {
            editingBookId = null;
            document.getElementById('bookModalTitle').textContent = 'Tambah Buku';
            bookForm.reset();
            document.getElementById('bookFormAction').value = 'add';
            document.getElementById('bookFormId').value = '';
            document.getElementById('bookOldCover').value = '';
            document.getElementById('bookStock').min = '1';
            document.querySelector('label[for="bookStock"]').textContent = 'Stok Awal';
            revokeCoverPreviewUrl();
            document.getElementById('bookCoverPreview').style.display = 'none';
            document.getElementById('bookCoverPreviewImg').removeAttribute('src');
            document.getElementById('cover').value = '';
            openModal('bookModal');
        }

        function handleBookApiResponse(response) {
            return response.text().then(function(text) {
                var data;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    console.error('Non-JSON response from server:', text.substring(0, 500));
                    throw new Error('Respons server tidak valid (status ' + response.status + ').');
                }
                if (!response.ok || data.success === false) {
                    throw new Error(data.message || ('Permintaan gagal (HTTP ' + response.status + ')'));
                }
                return data;
            });
        }

        bookForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (bookFormSubmitting) {
                console.warn('Submit ignored: request already in progress');
                return;
            }

            const title = document.getElementById('bookTitle').value.trim();
            const author = document.getElementById('bookAuthor').value.trim();
            const category = document.getElementById('bookCategory').value;
            const stockNum = parseInt(document.getElementById('bookStock').value, 10);
            const coverInput = document.getElementById('cover');
            const coverFile = coverInput.files[0];

            if (!title || !author || !category || Number.isNaN(stockNum)) {
                showToast('Semua field harus diisi', 'error');
                return;
            }

            if (editingBookId) {
                if (stockNum < 0) {
                    showToast('Stok tidak boleh negatif', 'error');
                    return;
                }
            } else if (stockNum < 1) {
                showToast('Stok harus minimal 1', 'error');
                return;
            }

            if (coverFile && coverFile.size > 0) {
                if (coverFile.size > COVER_MAX_BYTES) {
                    showToast('Ukuran foto terlalu besar. Maksimal 2MB', 'error');
                    return;
                }
                if (!isAllowedCoverFile(coverFile)) {
                    showToast('Format foto harus JPG, JPEG, PNG, atau WebP', 'error');
                    return;
                }
            }

            const formData = new FormData(bookForm);
            formData.set('action', editingBookId ? 'edit' : 'add');
            formData.set('title', title);
            formData.set('author', author);
            formData.set('category', category);
            formData.set('stock', String(stockNum));

            if (editingBookId) {
                formData.set('id', String(editingBookId));
            } else {
                formData.delete('id');
            }

            if (!coverFile || coverFile.size === 0) {
                formData.delete('cover');
            }

            const submitBtn = document.getElementById('bookSubmitBtn');
            if (!submitBtn) {
                console.error('Submit button #bookSubmitBtn not found');
                return;
            }
            const originalText = submitBtn.textContent;
            bookFormSubmitting = true;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Menyimpan...';

            fetch(bookPostUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(handleBookApiResponse)
            .then(function(data) {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('bookModal');
                    bookForm.reset();
                    editingBookId = null;
                    revokeCoverPreviewUrl();
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showToast(data.message || 'Gagal menyimpan data', 'error');
                    console.error('Save failed:', data);
                }
            })
            .catch(function(error) {
                console.error('Submit error:', error);
                showToast('Terjadi kesalahan: ' + error.message, 'error');
            })
            .finally(function() {
                bookFormSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });

        function editBook(id) {
            fetch('../api/get-books.php?id=' + id)
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error, status = ' + response.status);
                }
                return response.json();
            })
            .then(book => {
                if (!book || !book.id) {
                    throw new Error('Buku tidak ditemukan');
                }
                document.getElementById('bookModalTitle').textContent = 'Edit Buku';
                document.getElementById('bookTitle').value = book.title || '';
                document.getElementById('bookAuthor').value = book.author || '';
                document.getElementById('bookCategory').value = book.category || '';
                document.getElementById('bookStock').value = book.stock ?? 0;
                document.getElementById('bookStock').min = '0';
                document.querySelector('label[for="bookStock"]').textContent = 'Stok';
                editingBookId = book.id;
                document.getElementById('bookFormAction').value = 'edit';
                document.getElementById('bookFormId').value = String(book.id);
                document.getElementById('bookOldCover').value = book.cover || '';
                document.getElementById('cover').value = '';
                revokeCoverPreviewUrl();
                const prev = document.getElementById('bookCoverPreview');
                const prevImg = document.getElementById('bookCoverPreviewImg');
                const prevLabel = document.getElementById('bookCoverPreviewLabel');
                if (book.cover) {
                    prevImg.src = '../api/cover-image.php?id=' + book.id + '&t=full';
                    prevImg.alt = book.title || 'Cover';
                    prevLabel.textContent = 'Cover saat ini';
                    prev.style.display = 'block';
                    prevImg.loading = 'eager';
                } else {
                    prev.style.display = 'none';
                    prevImg.removeAttribute('src');
                }
                openModal('bookModal');
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Gagal memuat data buku: ' + error.message, 'error');
            });
        }

        function deleteBook(id) {
            confirmAction('Hapus Buku?', 'Tindakan ini tidak bisa dibatalkan.', function() {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                fetch(bookPostUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(handleBookApiResponse)
                .then(function(data) {
                    showToast(data.message, 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    showToast('Terjadi kesalahan: ' + error.message, 'error');
                });
            });
        }

        // Hanya cari ketika Enter ditekan
        document.getElementById('searchInput').addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                const search = this.value;
                const category = document.getElementById('categoryFilter').value;
                window.location.href = '?search=' + encodeURIComponent(search) + '&category=' + encodeURIComponent(category);
            }
        });

        // Auto-refresh ketika input dikosongkan
        document.getElementById('searchInput').addEventListener('input', function(event) {
            if (this.value === '') {
                const category = document.getElementById('categoryFilter').value;
                window.location.href = '?search=&category=' + encodeURIComponent(category);
            }
        });

        document.getElementById('categoryFilter').addEventListener('change', function() {
            const search = document.getElementById('searchInput').value;
            const category = this.value;
            window.location.href = '?search=' + encodeURIComponent(search) + '&category=' + encodeURIComponent(category);
        });

        // Event listener untuk file input cover - dengan check element exists
        var coverInput = document.getElementById('cover');
        console.log('Cover input element found:', coverInput);
        
        if (coverInput) {
            coverInput.addEventListener('change', function(e) {
                console.log('Cover input change event triggered');
                const file = e.target.files[0];
                const fileInput = this;

                if (!file) {
                    console.log('No file selected');
                    return;
                }

                console.log('File selected:', file.name, 'Size:', file.size);

                if (file.size > COVER_MAX_BYTES) {
                    console.log('File too large');
                    showToast('Ukuran foto terlalu besar. Maksimal 2MB', 'error');
                    fileInput.value = '';
                    return;
                }

                if (!isAllowedCoverFile(file)) {
                    console.log('File type not allowed');
                    showToast('Format foto harus JPG, JPEG, PNG, atau WebP', 'error');
                    fileInput.value = '';
                    return;
                }

                console.log('File validation passed, showing preview');
                showCoverPreviewFromFile(file);
            });
        } else {
            console.error('Cover input element NOT FOUND! ID="cover" does not exist in DOM');
        }
    </script>
</body>
</html>
