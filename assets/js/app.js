// ===== Sidebar Toggle =====
const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const sidebarClose = document.getElementById('sidebarClose');
let overlay = document.querySelector('.sidebar-overlay');

if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
}

if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.add('open');
        overlay.classList.add('active');
    });
}

if (sidebarClose) {
    sidebarClose.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });
}

overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
});

// ===== Modal =====
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', (e) => {
        if (e.target === m) m.classList.remove('active');
    });
});

// ===== Toast Notifications =====
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-20px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===== File Upload =====
function initFileUpload(dropAreaId, inputId, listId) {
    const dropArea = document.getElementById(dropAreaId);
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);

    if (!dropArea || !input) return;

    dropArea.addEventListener('click', () => input.click());

    ['dragenter', 'dragover'].forEach(evt => {
        dropArea.addEventListener(evt, (e) => {
            e.preventDefault();
            dropArea.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        dropArea.addEventListener(evt, (e) => {
            e.preventDefault();
            dropArea.classList.remove('dragover');
        });
    });

    dropArea.addEventListener('drop', (e) => {
        input.files = e.dataTransfer.files;
        updateFileList(input, list);
    });

    input.addEventListener('change', () => {
        updateFileList(input, list);
    });
}

function updateFileList(input, list) {
    if (!list) return;
    list.innerHTML = '';
    Array.from(input.files).forEach((file, index) => {
        const ext = file.name.split('.').pop().toLowerCase();
        const icon = getFileIconClass(ext);
        const size = formatFileSize(file.size);
        const item = document.createElement('li');
        item.className = 'file-list-item';
        item.innerHTML = `
            <i class="fas ${icon}"></i>
            <span class="file-name">${file.name}</span>
            <span class="file-size">${size}</span>
        `;
        list.appendChild(item);
    });
}

function getFileIconClass(ext) {
    const icons = {
        'pdf': 'fa-file-pdf', 'doc': 'fa-file-word', 'docx': 'fa-file-word',
        'xls': 'fa-file-excel', 'xlsx': 'fa-file-excel',
        'ppt': 'fa-file-powerpoint', 'pptx': 'fa-file-powerpoint',
        'jpg': 'fa-file-image', 'jpeg': 'fa-file-image', 'png': 'fa-file-image', 'gif': 'fa-file-image',
        'zip': 'fa-file-archive', 'rar': 'fa-file-archive',
        'mp4': 'fa-file-video', 'psd': 'fa-file-image', 'ai': 'fa-file-image'
    };
    return icons[ext] || 'fa-file';
}

function formatFileSize(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' bytes';
}

// ===== Confirm Delete =====
function confirmDelete(message, formId) {
    if (confirm(message || 'هل أنت متأكد من الحذف؟')) {
        document.getElementById(formId).submit();
    }
}

// ===== Auto-hide alerts =====
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
});

// ===== Notification click =====
const notifBtn = document.getElementById('notifBtn');
if (notifBtn) {
    notifBtn.addEventListener('click', () => {
        window.location.href = 'notifications.php';
    });
}
