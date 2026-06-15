<?php
require_once '../includes/session_check.php';
require_once '../includes/functions.php';

$stats = getDashboardStats();
$borrowings_data = getBorrowingsCurrentWeek();
$borrowings_by_major = getBorrowingsByMajor();
$recent_transactions = getRecentTransactions(5);
$overdue_books = getOverdueBooks();
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
    <title>Dashboard - BukuKita | SMP TAQ SADAMIYYAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body class="dashboard-layout">
    <div class="main-layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="book-logo">
                    <i class="fas fa-book-open"></i>
                </div>
                <span class="brand-title">BukuKita</span>
            </div>
            <nav>
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-chart-line"></i> <span>Dashboard</span>
                </a>
                <a href="books.php" class="nav-link">
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
            <h2>Dashboard BukuKita - SMP TAQ SADAMIYYAH</h2>
            <p>Kelola perpustakaan sekolah dengan mudah dan efisien</p>
        </div>

        <!-- Statistics Cards (3 kolom sama lebar) -->
        <div class="row g-4 mb-4 dashboard-stats-row align-items-stretch">
            <div class="col-12 col-md-4 dashboard-stat-col">
                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-value"><?php echo $stats['available_books']; ?></div>
                        <div class="stat-label">Buku Tersedia</div>
                        <div class="stat-badge" style="margin-top: 8px;">
                            <i class="fas fa-check-circle" style="font-size: 11px;"></i>
                            <span><?php echo $stats['total_books'] > 0 ? round(($stats['available_books'] / $stats['total_books']) * 100) : 0; ?>% tersedia</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 dashboard-stat-col">
                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-value"><?php echo $stats['borrowed_books']; ?></div>
                        <div class="stat-label">Buku Dipinjam</div>
                        <div class="stat-badge" style="margin-top: 8px;">
                            <i class="fas fa-book-reader" style="font-size: 11px;"></i>
                            <span>Sedang aktif</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 dashboard-stat-col">
                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-value"><?php echo $stats['late_books']; ?></div>
                        <div class="stat-label">Terlambat</div>
                        <div class="stat-badge danger" style="margin-top: 8px;">
                            <i class="fas fa-exclamation-circle" style="font-size: 11px;"></i>
                            <span>Perlu tindakan</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert for overdue books -->
        <?php if (!empty($overdue_books)): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Perhatian!</strong> Ada <?php echo count($overdue_books); ?> buku yang akan jatuh tempo dalam 3 hari.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4 mb-4 dashboard-main-row align-items-stretch">
            <!-- Chart -->
            <div class="col-12 col-xl-6 d-flex">
                <div class="card dashboard-section-card h-100" style="width: 100%; display: flex; flex-direction: column;">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-chart-line" style="color: var(--primary); margin-right: 8px;"></i>
                            Peminjaman 6 Hari Terakhir
                        </h5>
                    </div>
                    <div class="card-body" style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px;">
                        <canvas id="borrowingChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Borrowing by Major -->
            <div class="col-12 col-xl-6 d-flex">
                <div class="card dashboard-section-card h-100" style="width: 100%; display: flex; flex-direction: column;">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-layer-group" style="color: var(--primary); margin-right: 8px;"></i>
                            Statistik Peminjaman per Kelas
                        </h5>
                    </div>
                    <div class="card-body" style="flex: 1;">
                        <div class="stats-wrapper">
                            <?php 
                            // Define total students per major based on DB schema (330 total, approx 60-90 per major)
                            $major_totals = [
                                '7A' => 25,
                                '7B' => 25,
                                '8A' => 25,
                                '8B' => 25,
                                '9A' => 25,
                                '9B' => 25
                            ];
                            
                            foreach ($borrowings_by_major as $major => $count): 
                                $total_students = $major_totals[$major] ?? 60;
                                // Target max is 75% (3/4) of total students
                                $target_max = floor($total_students * 0.75);
                                $percentage = min(100, ($count / $target_max) * 100);
                            ?>
                            <div class="stat-item">
                                <span class="stat-label"><?php echo htmlspecialchars($major); ?></span>
                                <div class="stat-progress">
                                    <div class="stat-progress-bar">
                                        <div class="stat-progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                </div>
                                <span class="stat-percent"><?php echo $count; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card table-card">
            <div class="card-header">
                <h5>Peminjaman Buku Terbaru</h5>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Judul Buku</th>
                            <th>Tanggal Pinjam</th>
                            <th>Status</th>
                            <th style="text-align: center; width: 80px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_transactions)): ?>
                            <tr>
                                <td colspan="6" style="padding: 40px;">
                                    <div style="width: 100%; display: flex; justify-content: center; align-items: center;">Belum ada transaksi</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_transactions as $transaction): ?>
                            <tr id="transaction-<?php echo $transaction['id']; ?>">
                                <td><?php echo htmlspecialchars($transaction['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['name']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['title']); ?></td>
                                <td><?php echo date('d M Y', strtotime($transaction['borrow_date'])); ?></td>
                                <td>
                                    <?php if ($transaction['status'] === 'active'): ?>
                                        <span class="badge-soft badge-soft-warning">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge-soft badge-soft-success">Selesai</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($transaction['status'] === 'returned'): ?>
                                        <button class="btn-sm btn-danger delete-btn" data-id="<?php echo $transaction['id']; ?>" style="width: 100%; padding: 4px 8px; font-size: 12px; border: none; cursor: pointer;">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;">-</span>
                                    <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theme-toggle.js"></script>
    <script>
        setActiveNav('a[href="dashboard.php"]');

        // Initialize Chart with Modern Configuration
        const borrowingData = <?php echo json_encode($borrowings_data); ?>;
        const ctx = document.getElementById('borrowingChart').getContext('2d');
        
        // Create gradient for modern appearance
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(74, 114, 212, 0.15)');
        gradient.addColorStop(1, 'rgba(74, 114, 212, 0.01)');

        const borrowingChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: borrowingData.map(d => d.date),
                datasets: [{
                    label: 'Jumlah Peminjaman',
                    data: borrowingData.map(d => d.count),
                    backgroundColor: 'rgba(91, 124, 250, 0.85)',
                    borderColor: '#4a72d4',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                    hoverBackgroundColor: '#3a5bb0',
                    hoverBorderColor: '#2a4a90',
                    hoverBorderWidth: 1,
                    barThickness: 'flex',
                    maxBarThickness: 40,
                    minBarLength: 0,
                }]
            },
            options: {
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const dayData = borrowingData[index];
                        showDailyDetails(dayData.full_date, dayData.date);
                    }
                },
                onHover: (event, elements) => {
                    event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                },
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 13,
                                weight: '600',
                                family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                            },
                            color: '#555',
                            padding: 20,
                            usePointStyle: true,
                            boxWidth: 8,
                            boxHeight: 8,
                            borderRadius: 4
                        }
                    },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#4a72d4',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        boxPadding: 8,
                        titleFont: {
                            size: 14,
                            weight: 'bold',
                            family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                        },
                        bodyFont: {
                            size: 13,
                            family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                        },
                        cornerRadius: 8,
                        caretPadding: 12,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + Math.round(context.parsed.y) + ' peminjaman';
                            },
                            afterLabel: function(context) {
                                return 'Total: ' + Math.round(context.parsed.y);
                            }
                        }
                    },
                    filler: {
                        propagate: true
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            precision: 0,
                            stepSize: 20,
                            font: {
                                size: 12,
                                weight: '500',
                                family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                            },
                            color: '#888',
                            padding: 10,
                            callback: function(value) {
                                if (Math.floor(value) === value) {
                                    return value;
                                }
                            }
                        },
                        grid: {
                            color: 'rgba(200, 200, 200, 0.1)',
                            lineWidth: 1,
                            drawBorder: false,
                            drawTicks: false
                        },
                        border: {
                            display: false
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12,
                                weight: '500',
                                family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                            },
                            color: '#888',
                            padding: 8
                        },
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        border: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart',
                    delay: function(context) {
                        let delay = 0;
                        if (context.type === 'data') {
                            delay = context.dataIndex * 50;
                        }
                        return delay;
                    }
                }
            }
        });

        function showDailyDetails(date, dayName) {
            Swal.fire({
                title: 'Memuat...',
                didOpen: () => { Swal.showLoading(); }
            });

            fetch(`../api/get-daily-borrowing-details.php?date=${date}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = `
                            <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                                <p style="font-weight: 600; margin-bottom: 15px;">Daftar Peminjaman - ${dayName}, ${data.date_formatted}</p>
                                <table class="table table-sm" style="font-size: 13px;">
                                    <thead>
                                        <tr>
                                            <th>Siswa</th>
                                            <th>Buku</th>
                                            <th>Kelas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        if (data.data.length === 0) {
                            html += '<tr><td colspan="3" style="padding: 20px; text-align: center;"><div style="width: 100%; display: flex; justify-content: center; align-items: center;">Tidak ada peminjaman pada hari ini</div></td></tr>';
                        } else {
                            data.data.forEach(item => {
                                html += `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td>${item.title}</td>
                                        <td>${item.class}</td>
                                    </tr>
                                `;
                            });
                        }
                        
                        html += '</tbody></table></div>';

                        Swal.fire({
                            title: `<i class="fas fa-info-circle" style="color: #5b7cfa;"></i> Info Peminjaman`,
                            html: html,
                            width: '600px',
                            confirmButtonText: 'Tutup',
                            confirmButtonColor: '#5b7cfa'
                        });
                    } else {
                        Swal.fire('Gagal!', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error!', 'Gagal mengambil data: ' + err.message, 'error');
                });
        }

        // Event handler untuk button delete
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const borrowingId = this.getAttribute('data-id');
                deleteTransaction(borrowingId);
            });
        });

        function deleteTransaction(borrowingId) {
            Swal.fire({
                title: 'Hapus Transaksi?',
                text: 'Data transaksi yang sudah dihapus tidak bisa dipulihkan. Pastikan transaksi sudah selesai sebelum menghapus.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#ccc',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send delete request
                    const formData = new FormData();
                    formData.append('borrowing_id', borrowingId);

                    fetch('../api/delete-transaction.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire(
                                'Berhasil!',
                                'Transaksi telah dihapus.',
                                'success'
                            ).then(() => {
                                // Reload halaman untuk refresh data
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Gagal!',
                                data.message || 'Transaksi tidak bisa dihapus',
                                'error'
                            );
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire(
                            'Error!',
                            'Terjadi kesalahan: ' + error.message,
                            'error'
                        );
                    });
                }
            });
        }
    </script>
</body>
</html>
