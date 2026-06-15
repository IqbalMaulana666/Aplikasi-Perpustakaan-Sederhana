<?php
require_once '../includes/session_check.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $student_id = (int)($_POST['student_id'] ?? 0);
    $book_id = (int)($_POST['book_id'] ?? 0);

    if ($student_id <= 0 || $book_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }

    // Check student exists
    $query = "SELECT * FROM students WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
        exit;
    }

    if (!isAllowedStudentClass($student['class'])) {
        echo json_encode(['success' => false, 'message' => 'Peminjaman hanya untuk siswa SMP (kelas 7, 8, dan 9)']);
        exit;
    }

    // Check book exists and available
    $book = getBookByID($book_id);
    if (!$book || $book['stock'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Buku tidak tersedia']);
        exit;
    }

    // Check total fine
    $student_id_val = $student['id'];
    $total_fine = calculateTotalFine($student_id_val);
    if ($total_fine > 50000) {
        echo json_encode(['success' => false, 'message' => 'Siswa tidak bisa meminjam karena denda lebih dari Rp50.000']);
        exit;
    }

    // Check 3 books per week rule
    $borrowed_this_week = countBorrowedThisWeek($student_id_val);
    if ($borrowed_this_week >= 3) {
        echo json_encode(['success' => false, 'message' => 'Siswa sudah mencapai batas 3 buku per minggu']);
        exit;
    }

    // Process borrowing
    $borrow_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+7 days'));

    $query = "INSERT INTO borrowings (book_id, student_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, 'active')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiss", $book_id, $student_id_val, $borrow_date, $due_date);

    if ($stmt->execute()) {
        // Update book stock
        $new_stock = $book['stock'] - 1;
        $status = $new_stock <= 0 ? 'borrowed' : 'available';
        $update_query = "UPDATE books SET stock = ?, status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("isi", $new_stock, $status, $book_id);
        $update_stmt->execute();

        logActivity('pinjam_buku', "Siswa: {$student['name']} - Buku: {$book['title']}");
        
        // Get current time for detail display
        $borrow_time = date('H:i');
        $borrow_day = date('l', strtotime($borrow_date));
        $borrow_date_formatted = date('d F Y', strtotime($borrow_date));
        
        echo json_encode([
            'success' => true,
            'message' => 'Buku berhasil dipinjam',
            'borrowing_detail' => [
                'student_nis' => $student['student_id'],
                'student_name' => $student['name'],
                'book_title' => $book['title'],
                'book_author' => $book['author'],
                'borrow_time' => $borrow_time,
                'borrow_day' => $borrow_day,
                'borrow_date' => $borrow_date_formatted,
                'due_date' => date('d F Y', strtotime($due_date))
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memproses peminjaman']);
    }
    exit;
}

// Get all students
$students_query = "
    SELECT s.id, s.student_id, s.name, s.class,
    (SELECT COALESCE(SUM(fine), 0) FROM returns r WHERE r.student_id = s.id) as total_fine
    FROM students s
    WHERE " . sqlStudentsOnlySMP('s') . "
    ORDER BY s.name ASC
";
$students = $conn->query($students_query)->fetch_all(MYSQLI_ASSOC);

// Get available books
$books_query = "SELECT id, title, author FROM books WHERE stock > 0 ORDER BY title ASC";
$books = $conn->query($books_query)->fetch_all(MYSQLI_ASSOC);
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
    <title>Peminjaman - BukuKita | SMP TAQ SADAMIYYAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body class="dashboard-layout">
    <div class="main-layout">
        <!-- Sidebar -->
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
                <a href="books.php" class="nav-link">
                    <i class="fas fa-book"></i> <span>Manajemen Buku</span>
                </a>
                <a href="students.php" class="nav-link">
                    <i class="fas fa-users"></i> <span>Manajemen Siswa</span>
                </a>
                <a href="borrowing.php" class="nav-link active">
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
            <h2>Peminjaman Buku</h2>
            <p>Proses peminjaman buku untuk siswa</p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Form Peminjaman Buku</h5>
                    </div>
                    <div class="card-body">
                    <form id="borrowingForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="classSelect" class="form-label">Filter Kelas</label>
                                    <select class="form-control" id="classSelect">
                                        <option value="" disabled selected hidden>Pilih Kelas</option>
                                        <?php 
                                        $classes = array_unique(array_column($students, 'class'));
                                        sort($classes);
                                        foreach ($classes as $class): 
                                            if(!empty($class)):
                                        ?>
                                        <option value="<?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($class); ?></option>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nisInput" class="form-label">Cari NIS</label>
                                    <input type="text" class="form-control" id="nisInput" placeholder="Ketik NIS">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="studentSelect" class="form-label">Pilih Siswa</label>
                            <select class="form-control" id="studentSelect" name="student_id" required>
                                <option value="">Cari dan pilih siswa</option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['student_id'] . ' - ' . $student['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="studentInfo" style="display: none; padding: 15px; background-color: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 20px;">
                            <div><strong>Nama:</strong> <span id="infoName"></span></div>
                            <div><strong>Kelas:</strong> <span id="infoClass"></span></div>
                            <div><strong>Total Denda:</strong> <span id="infoFine"></span></div>
                            <div><strong>Buku yang Dipinjam Minggu Ini:</strong> <span id="infoBorrowed"></span>/3</div>
                        </div>

                        <div class="form-group">
                            <label for="bookSearchInput" class="form-label">Cari Buku (Judul / Penulis)</label>
                            <input type="text" class="form-control" id="bookSearchInput" placeholder="Ketik judul atau penulis buku...">
                        </div>

                        <div class="form-group">
                            <label for="bookSelect" class="form-label">Pilih Buku</label>
                            <select class="form-control" id="bookSelect" name="book_id" required>
                                <option value="">Cari dan pilih buku</option>
                                <?php foreach ($books as $book): ?>
                                <option value="<?php echo $book['id']; ?>">
                                    <?php echo htmlspecialchars($book['title'] . ' - ' . $book['author']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-check-circle"></i> Proses Peminjaman
                        </button>
                    </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Informasi Peminjaman</h5>
                    </div>
                    <div style="padding: 20px;">
                        <div style="margin-bottom: 15px;">
                            <strong>Aturan Peminjaman:</strong>
                            <ul style="margin-top: 10px; font-size: 14px; margin-bottom: 0;">
                                <li>Maksimal 3 buku per minggu</li>
                                <li>Waktu peminjaman: 7 hari</li>
                                <li>Denda terlambat: Rp5.000/hari</li>
                                <li>Maksimal denda per buku: Rp100.000</li>
                                <li>Blokir pinjam jika denda > Rp50.000</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theme-toggle.js"></script>
    <script>
        setActiveNav('a[href="borrowing.php"]');

        const students = <?php echo json_encode($students); ?>;
        const books = <?php echo json_encode($books); ?>;

        const classSelect = document.getElementById('classSelect');
        const nisInput = document.getElementById('nisInput');
        const studentSelect = document.getElementById('studentSelect');
        
        const bookSearchInput = document.getElementById('bookSearchInput');
        const bookSelect = document.getElementById('bookSelect');

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function renderBookOptions(filteredBooks) {
            let html = '';
            if (filteredBooks.length === 0) {
                html = '<option value="">Buku tidak ditemukan...</option>';
            } else {
                html = '<option value="">Cari dan pilih buku</option>';
                filteredBooks.forEach(b => {
                    html += `<option value="${b.id}">${escapeHtml(b.title)} - ${escapeHtml(b.author)}</option>`;
                });
            }
            bookSelect.innerHTML = html;
        }

        bookSearchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            
            if (query) {
                const filtered = books.filter(b => {
                    const title = b.title ? String(b.title).toLowerCase() : '';
                    const author = b.author ? String(b.author).toLowerCase() : '';
                    return title.includes(query) || author.includes(query);
                });
                
                renderBookOptions(filtered);
                
                // Automatically select the first option so it is instantly selected and shown to the user
                if (filtered.length > 0) {
                    bookSelect.value = filtered[0].id;
                } else {
                    bookSelect.value = "";
                }
            } else {
                renderBookOptions(books);
                bookSelect.value = "";
            }
        });

        function renderStudentOptions(filteredStudents) {
            let html = '<option value="">Cari dan pilih siswa...</option>';
            filteredStudents.forEach(s => {
                html += `<option value="${s.id}">${s.student_id} - ${s.name}</option>`;
            });
            studentSelect.innerHTML = html;
        }

        classSelect.addEventListener('change', function() {
            const selectedClass = this.value;
            let filtered = students;
            
            if (selectedClass) {
                filtered = students.filter(s => s.class === selectedClass);
            }
            
            renderStudentOptions(filtered);
            studentSelect.value = "";
            studentSelect.dispatchEvent(new Event('change'));
            nisInput.value = ""; // Reset NIS input when class changes
        });

        nisInput.addEventListener('input', function() {
            const nis = this.value.trim().toLowerCase();
            
            if (nis) {
                // Try to find exact match
                const exactMatch = students.find(s => s.student_id.toLowerCase() === nis);
                
                if (exactMatch) {
                    // If exact match found, update class filter and select student
                    classSelect.value = exactMatch.class;
                    renderStudentOptions(students.filter(s => s.class === exactMatch.class));
                    studentSelect.value = exactMatch.id;
                    studentSelect.dispatchEvent(new Event('change'));
                } else {
                    // Filter options by partial NIS
                    const filtered = students.filter(s => s.student_id.toLowerCase().includes(nis));
                    renderStudentOptions(filtered);
                    studentSelect.value = "";
                    document.getElementById('studentInfo').style.display = 'none';
                }
            } else {
                // If empty, revert to class filter logic
                classSelect.dispatchEvent(new Event('change'));
            }
        });

        studentSelect.addEventListener('change', function() {
            const studentId = this.value;
            const studentInfo = document.getElementById('studentInfo');

            if (!studentId) {
                studentInfo.style.display = 'none';
                return;
            }

            const student = students.find(s => s.id == studentId);
            if (student) {
                fetch('../api/get-borrowings.php?student_id=' + studentId)
                    .then(response => response.json())
                    .then(borrowings => {
                        const fineText = formatCurrency(student.total_fine);
                        document.getElementById('infoName').textContent = student.name;
                        document.getElementById('infoClass').textContent = student.class;
                        document.getElementById('infoFine').textContent = fineText;
                        document.getElementById('infoBorrowed').textContent = borrowings.length;
                        studentInfo.style.display = 'block';
                    });
            }
        });

        document.getElementById('borrowingForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('student_id', document.getElementById('studentSelect').value);
            formData.append('book_id', document.getElementById('bookSelect').value);

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            submitBtn.disabled = true;

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerHTML = originalBtnHtml;
                submitBtn.disabled = false;

                if (data.success) {
                    const detail = data.borrowing_detail;
                    
                    Swal.fire({
                        title: 'Peminjaman Berhasil!',
                        html: `
                            <div style="text-align: left; font-size: 14px; margin-top: 15px; background: var(--bg-main); border: 1px solid var(--border-color); padding: 20px; border-radius: 8px;">
                                <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
                                    <div style="margin-bottom: 8px;">
                                        <strong style="color: var(--text-main);">NIS Siswa:</strong>
                                        <div style="color: var(--primary); font-weight: bold; font-size: 16px; margin-top: 3px;">${detail.student_nis}</div>
                                    </div>
                                    <div>
                                        <strong style="color: var(--text-main);">Nama Siswa:</strong>
                                        <div style="color: var(--text-muted); margin-top: 3px;">${detail.student_name}</div>
                                    </div>
                                </div>

                                <div style="margin-bottom: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                                    <strong style="color: var(--text-main);">Buku yang Dipinjam:</strong>
                                    <div style="color: var(--text-muted); margin-top: 3px;"><strong>${detail.book_title}</strong></div>
                                    <div style="color: var(--text-muted); font-size: 12px; margin-top: 2px;">Pengarang: ${detail.book_author}</div>
                                </div>

                                <div style="margin-bottom: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                                    <strong style="color: var(--text-main);">Waktu Peminjaman:</strong>
                                    <div style="color: var(--text-muted); margin-top: 3px;">
                                        <i class="fas fa-clock" style="color: var(--primary);"></i> Jam: <strong>${detail.borrow_time}</strong>
                                    </div>
                                    <div style="color: var(--text-muted); margin-top: 4px;">
                                        <i class="fas fa-calendar" style="color: var(--primary);"></i> ${detail.borrow_day}
                                    </div>
                                </div>

                                <div style="margin-bottom: 0; padding: 12px 0;">
                                    <strong style="color: var(--text-main);">Tanggal Peminjaman:</strong>
                                    <div style="color: var(--text-muted); margin-top: 3px;">${detail.borrow_date}</div>
                                    <div style="margin-top: 10px; padding: 10px; background-color: var(--primary-soft); border-radius: 6px; font-size: 13px;">
                                        <strong style="color: var(--primary);">Jatuh Tempo:</strong> <span style="color: var(--text-muted);">${detail.due_date}</span>
                                    </div>
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#6dc7cf',
                        confirmButtonText: 'Selesai'
                    }).then(() => {
                        document.getElementById('borrowingForm').reset();
                        document.getElementById('studentInfo').style.display = 'none';
                        setTimeout(() => location.reload(), 1000);
                    });
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                submitBtn.innerHTML = originalBtnHtml;
                submitBtn.disabled = false;
                console.error('Error:', error);
                showToast('Terjadi kesalahan koneksi', 'error');
            });
        });

    </script>
</body>
</html>
