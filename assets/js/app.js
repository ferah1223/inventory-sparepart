/**
 * app.js - Global Search, Delete Confirmation, Loading States
 * Inventaris Spare Part - Bengkel Jaya
 */
(function() {
    'use strict';

    // ============================================
    // 1. GLOBAL SEARCH
    // ============================================
    function initGlobalSearch() {
        const searchContainer = document.getElementById('globalSearchContainer');
        if (!searchContainer) return;

        const input = document.getElementById('globalSearchInput');
        const results = document.getElementById('globalSearchResults');
        let debounceTimer = null;

        input.addEventListener('input', function() {
            const query = this.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 2) {
                results.style.display = 'none';
                results.innerHTML = '';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetchSearchResults(query);
            }, 300);
        });

        input.addEventListener('focus', function() {
            if (this.value.trim().length >= 2) {
                results.style.display = 'block';
            }
        });

        // Close on outside click
        document.addEventListener('click', function(e) {
            if (!searchContainer.contains(e.target)) {
                results.style.display = 'none';
            }
        });

        // Keyboard navigation
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                results.style.display = 'none';
                this.blur();
            }
            if (e.key === 'Enter' && results.style.display === 'block') {
                const firstLink = results.querySelector('.global-search-item');
                if (firstLink) {
                    e.preventDefault();
                    firstLink.click();
                }
            }
        });
    }

    function fetchSearchResults(query) {
        const results = document.getElementById('globalSearchResults');
        
        results.innerHTML = '<div class="global-search-loading"><i class="fas fa-spinner fa-spin"></i> Mencari...</div>';
        results.style.display = 'block';

        fetch('search_api.php?q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    results.innerHTML = '<div class="global-search-empty"><i class="fas fa-search"></i> Tidak ada hasil ditemukan</div>';
                    return;
                }

                let html = '';
                data.forEach(item => {
                    html += `<a href="barang.php?q=${encodeURIComponent(item.kode_barang)}" class="global-search-item">
                        <div class="global-search-item-info">
                            <div class="global-search-item-name">${escapeHtml(item.nama_barang)}</div>
                            <div class="global-search-item-code">${escapeHtml(item.kode_barang)}</div>
                        </div>
                        <div class="global-search-item-stock ${item.stok <= item.stok_minimum ? 'low' : ''}">
                            Stok: ${item.stok}
                        </div>
                    </a>`;
                });
                html += `<a href="barang.php?q=${encodeURIComponent(query)}" class="global-search-all">
                    <i class="fas fa-search"></i> Lihat semua hasil untuk "${escapeHtml(query)}"
                </a>`;
                results.innerHTML = html;
            })
            .catch(() => {
                results.innerHTML = '<div class="global-search-empty"><i class="fas fa-exclamation-circle"></i> Gagal mencari</div>';
            });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ============================================
    // 2. DELETE CONFIRMATION MODAL
    // ============================================
    function showDeleteConfirm(message, formId) {
        let modal = document.getElementById('deleteConfirmModal');
        
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'deleteConfirmModal';
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal" style="max-width:420px;">
                    <div class="modal-body" style="text-align:center; padding:32px;">
                        <div class="delete-confirm-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="delete-confirm-title">Konfirmasi Hapus</h3>
                        <p class="delete-confirm-message" id="deleteConfirmMessage"></p>
                    </div>
                    <div class="modal-footer" style="justify-content:center;">
                        <button type="button" class="btn btn-outline" id="deleteConfirmCancel">Batal</button>
                        <button type="button" class="btn btn-danger" id="deleteConfirmOk">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Close handlers
            modal.addEventListener('click', function(e) {
                if (e.target === this) closeDeleteConfirm();
            });
            document.getElementById('deleteConfirmCancel').addEventListener('click', closeDeleteConfirm);
        }

        document.getElementById('deleteConfirmMessage').textContent = message;
        modal._targetFormId = formId;
        modal.classList.add('show');
    }

    function closeDeleteConfirm() {
        const modal = document.getElementById('deleteConfirmModal');
        if (modal) modal.classList.remove('show');
    }

    // Wire up the OK button
    document.addEventListener('click', function(e) {
        if (e.target.id === 'deleteConfirmOk' || e.target.closest('#deleteConfirmOk')) {
            const modal = document.getElementById('deleteConfirmModal');
            if (modal && modal._targetFormId) {
                const form = document.getElementById(modal._targetFormId);
                if (form) form.submit();
            }
            closeDeleteConfirm();
        }
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDeleteConfirm();
    });

    // ============================================
    // 3. LOADING STATES
    // ============================================
    function initLoadingStates() {
        // Create spinner overlay element
        let overlay = document.getElementById('loadingOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = `
                <div class="loading-spinner-container">
                    <div class="loading-spinner"></div>
                    <div class="loading-text">Menyimpan...</div>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        // Attach to all forms inside modals and delete forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                // Skip if validation failed (validation.js will prevent default)
                if (e.defaultPrevented) return;

                // Show loading overlay
                overlay.classList.add('show');

                // Disable submit buttons
                this.querySelectorAll('button[type="submit"]').forEach(btn => {
                    btn.disabled = true;
                    btn.classList.add('btn-loading');
                    btn.dataset.originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
                });
            });
        });
    }

    // ============================================
    // INIT ALL
    // ============================================
    function init() {
        initGlobalSearch();
        initLoadingStates();

        // Replace existing confirm() calls with custom modal
        // This is done by scanning onclick attributes
        document.querySelectorAll('[onclick*="confirm("]').forEach(el => {
            const onclick = el.getAttribute('onclick');
            // Extract message from confirm('...')
            const match = onclick.match(/confirm\(['"](.+?)['"]\)/);
            if (match) {
                const message = match[1];
                // Get form id from the onclick
                const formMatch = onclick.match(/getElementById\(['"](.+?)['"]\)\.submit/);
                if (formMatch) {
                    const formId = formMatch[1];
                    el.removeAttribute('onclick');
                    el.addEventListener('click', function(e) {
                        e.preventDefault();
                        showDeleteConfirm(message, formId);
                    });
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose globally
    window.showDeleteConfirm = showDeleteConfirm;
    window.closeDeleteConfirm = closeDeleteConfirm;
})();
