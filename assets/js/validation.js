/**
 * validation.js - Client-side form validation
 * Inventaris Spare Part - Bengkel Jaya
 */
(function() {
    'use strict';

    // Validation rules configuration per form
    const validationConfigs = {
        // Barang form (tambah/edit)
        'barang-form': {
            fields: [
                { name: 'kode_barang', rules: ['required'], label: 'Kode Barang', condition: (form) => form.querySelector('[name="action"]').value === 'tambah' },
                { name: 'nama_barang', rules: ['required'], label: 'Nama Barang' },
                { name: 'stok_minimum', rules: ['numeric_min'], label: 'Stok Minimum', min: 0 },
                { name: 'harga_beli', rules: ['numeric_min'], label: 'Harga Beli', min: 0 },
                { name: 'harga_jual', rules: ['numeric_min'], label: 'Harga Jual', min: 0 }
            ]
        },
        // Masuk form
        'masuk-form': {
            fields: [
                { name: 'barang_id', rules: ['required_select'], label: 'Barang' },
                { name: 'jumlah', rules: ['numeric_positive'], label: 'Jumlah' },
                { name: 'tanggal_masuk', rules: ['required'], label: 'Tanggal Masuk' },
                { name: 'harga_satuan', rules: ['numeric_min'], label: 'Harga Satuan', min: 0 }
            ]
        },
        // Keluar form
        'keluar-form': {
            fields: [
                { name: 'barang_id', rules: ['required_select'], label: 'Barang' },
                { name: 'jumlah', rules: ['numeric_positive'], label: 'Jumlah' },
                { name: 'tanggal_keluar', rules: ['required'], label: 'Tanggal Keluar' },
                { name: 'harga_satuan', rules: ['numeric_min'], label: 'Harga Satuan', min: 0 }
            ]
        }
    };

    // Validation rule functions
    const rules = {
        required(value, label) {
            if (!value || value.trim() === '') {
                return `${label} wajib diisi`;
            }
            return null;
        },
        required_select(value, label) {
            if (!value || value === '') {
                return `${label} wajib dipilih`;
            }
            return null;
        },
        numeric_positive(value, label) {
            if (!value || value.trim() === '') {
                return `${label} wajib diisi`;
            }
            const num = parseFloat(value);
            if (isNaN(num) || num <= 0) {
                return `${label} harus berupa angka lebih dari 0`;
            }
            return null;
        },
        numeric_min(value, label, fieldConfig) {
            if (!value || value.trim() === '') return null; // optional field
            const num = parseFloat(value);
            const min = fieldConfig.min !== undefined ? fieldConfig.min : 0;
            if (isNaN(num) || num < min) {
                return `${label} harus berupa angka minimal ${min}`;
            }
            return null;
        }
    };

    // Show inline error
    function showError(formGroup, message) {
        clearError(formGroup);
        formGroup.classList.add('has-error');
        const input = formGroup.querySelector('.form-control, .form-select');
        if (input) input.classList.add('error');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        // Insert after the form-control or form-select
        const target = input || formGroup;
        target.parentNode.insertBefore(errorDiv, target.nextSibling);
    }

    // Clear inline error
    function clearError(formGroup) {
        formGroup.classList.remove('has-error');
        const input = formGroup.querySelector('.form-control, .form-select');
        if (input) input.classList.remove('error');
        const existing = formGroup.querySelector('.field-error');
        if (existing) existing.remove();
    }

    // Validate a single form
    function validateForm(form, configName) {
        const config = validationConfigs[configName];
        if (!config) return true;

        let isValid = true;

        config.fields.forEach(fieldConfig => {
            // Check condition (e.g., kode_barang only when tambah)
            if (fieldConfig.condition && !fieldConfig.condition(form)) {
                return;
            }

            const input = form.querySelector(`[name="${fieldConfig.name}"]`);
            if (!input) return;

            const formGroup = input.closest('.form-group');
            if (!formGroup) return;

            clearError(formGroup);

            const value = input.value;
            for (const ruleName of fieldConfig.rules) {
                const error = rules[ruleName](value, fieldConfig.label, fieldConfig);
                if (error) {
                    showError(formGroup, error);
                    isValid = false;
                    break;
                }
            }
        });

        return isValid;
    }

    // Auto-attach validation to forms
    function initValidation() {
        // Find all modal forms and attach validation
        document.querySelectorAll('.modal-overlay form').forEach(form => {
            const modal = form.closest('.modal-overlay');
            if (!modal) return;

            // Determine form type based on context
            let configName = null;
            const page = window.location.pathname.split('/').pop();

            if (page === 'barang.php') configName = 'barang-form';
            else if (page === 'masuk.php') configName = 'masuk-form';
            else if (page === 'keluar.php') configName = 'keluar-form';

            if (!configName) return;

            form.addEventListener('submit', function(e) {
                if (!validateForm(this, configName)) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Scroll to first error
                    const firstError = this.querySelector('.field-error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });

            // Clear errors on input
            form.querySelectorAll('.form-control, .form-select').forEach(input => {
                input.addEventListener('input', function() {
                    const formGroup = this.closest('.form-group');
                    if (formGroup) clearError(formGroup);
                });
                input.addEventListener('change', function() {
                    const formGroup = this.closest('.form-group');
                    if (formGroup) clearError(formGroup);
                });
            });
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initValidation);
    } else {
        initValidation();
    }

    // Expose for manual use
    window.FormValidation = { validateForm, showError, clearError };
})();
