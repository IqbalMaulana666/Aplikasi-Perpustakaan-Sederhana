// Toast notification
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// SweetAlert2 confirmation
function confirmAction(title, text, callback) {
    if (typeof Swal === 'undefined') {
        if (confirm(title + '\n\n' + text)) {
            callback();
        }
    } else {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#6dc7cf',
            cancelButtonColor: '#ccc',
            confirmButtonText: 'Ya, lanjutkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                callback();
            }
        });
    }
}

// Delete confirmation
function deleteItem(type, id) {
    confirmAction(
        'Hapus ' + type + '?',
        'Tindakan ini tidak bisa dibatalkan.',
        function() {
            deleteItemConfirmed(type, id);
        }
    );
}

// Delete confirmed
function deleteItemConfirmed(type, id) {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('type', type);
    formData.append('id', id);

    fetch(window.location.pathname, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Terjadi kesalahan', 'error');
    });
}

// Search and filter
function setupSearch(searchInputId, tableBodyId, pageSize = 10) {
    const searchInput = document.getElementById(searchInputId);
    if (!searchInput) return;

    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll(`#${tableBodyId} tr`);
        let visibleCount = 0;

        tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleCount === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="10" style="padding: 40px;"><div style="width: 100%; display: flex; justify-content: center; align-items: center;">Tidak ada data</div></td>';
            document.getElementById(tableBodyId).appendChild(emptyRow);
        }
    });
}

// Pagination
function setupPagination(tableBodyId, pageSize = 10) {
    const tbody = document.getElementById(tableBodyId);
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const totalPages = Math.ceil(rows.length / pageSize);

    if (totalPages <= 1) return;

    const paginationContainer = document.createElement('div');
    paginationContainer.className = 'pagination';

    // Previous button
    const prevBtn = document.createElement('button');
    prevBtn.textContent = '← Sebelumnya';
    prevBtn.onclick = function() {
        currentPage = Math.max(1, currentPage - 1);
        showPage(currentPage);
    };
    paginationContainer.appendChild(prevBtn);

    // Page buttons
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.textContent = i;
        pageBtn.className = i === 1 ? 'active' : '';
        pageBtn.onclick = function() {
            currentPage = i;
            showPage(currentPage);
        };
        paginationContainer.appendChild(pageBtn);
    }

    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.textContent = 'Selanjutnya →';
    nextBtn.onclick = function() {
        currentPage = Math.min(totalPages, currentPage + 1);
        showPage(currentPage);
    };
    paginationContainer.appendChild(nextBtn);

    const parent = tbody.parentElement;
    parent.parentElement.appendChild(paginationContainer);

    function showPage(page) {
        const start = (page - 1) * pageSize;
        const end = start + pageSize;

        rows.forEach((row, index) => {
            row.style.display = (index >= start && index < end) ? '' : 'none';
        });

        document.querySelectorAll('.pagination button').forEach((btn, index) => {
            btn.classList.toggle('active', parseInt(btn.textContent) === page);
        });
    }

    let currentPage = 1;
    showPage(1);
}

// Modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

// Sidebar toggle
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

// Set active nav link
function setActiveNav(selector) {
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    const activeLink = document.querySelector(selector);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR'
    }).format(amount);
}

// Format date
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('id-ID', options);
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    });

    // Close modals with close button
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.remove('show');
            }
        });
    });

    // Prevent accidental full-page submit on Enter (except book modal AJAX form)
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA') {
                return;
            }
            if (form.id === 'searchForm' || form.id === 'bookForm') {
                return;
            }
            e.preventDefault();
        });
    });

    // Responsive sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        if (window.innerWidth <= 768) {
            sidebarToggle.style.display = 'inline-block';
        }
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                sidebarToggle.style.display = 'inline-block';
            } else {
                sidebarToggle.style.display = 'none';
                const sidebar = document.querySelector('.sidebar');
                if (sidebar) sidebar.classList.remove('show');
            }
        });
    }
});

// Load external libraries on demand
function loadChartJS() {
    if (typeof Chart === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        document.head.appendChild(script);
    }
}
