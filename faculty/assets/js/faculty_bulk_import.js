window.BulkImport = {
    // Open bulk import modal using existing modal system
    openBulkImportModal: function() {
        const modalContent = `
            <div class="bulk-import-container">
                <div class="import-instructions">
                    <h4><i class="fas fa-info-circle"></i> CSV Import Instructions</h4>
                    <ul>
                        <li>CSV file should contain: <strong>student_id, first_name, last_name, middle_initial, email, birthday, status</strong></li>
                        <li>First row should be the header row</li>
                        <li>Date format: YYYY-MM-DD (e.g., 2000-01-15)</li>
                        <li>Status values: active, inactive, pending, transferred, suspended, graduated</li>
                        <li>Maximum file size: 5MB</li>
                    </ul>
                    
                    <div class="sample-download">
                        <button class="btn btn-secondary btn-sm" onclick="BulkImport.downloadSampleCSV()">
                            <i class="fas fa-download"></i> Download Sample CSV
                        </button>
                    </div>
                </div>

                <form id="bulkImportForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csvFile" class="form-label">
                            <i class="fas fa-file-csv"></i> Select CSV File
                        </label>
                        <div class="file-input-container">
                            <input type="file" id="csvFile" name="csvFile" accept=".csv" class="file-input" required>
                            <div class="file-input-display">
                                <span class="file-placeholder">Choose CSV file...</span>
                                <button type="button" class="btn btn-outline">Browse</button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Only .csv files are allowed</small>
                    </div>

                    <div class="import-options">
                        <h5><i class="fas fa-cogs"></i> Import Options</h5>
                        <div class="form-check">
                            <input type="checkbox" id="skipDuplicates" name="skipDuplicates" checked>
                            <label for="skipDuplicates">Skip duplicate student IDs</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="updateExisting" name="updateExisting">
                            <label for="updateExisting">Update existing student information</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="validateEmails" name="validateEmails" checked>
                            <label for="validateEmails">Validate email addresses</label>
                        </div>
                    </div>

                    <div class="preview-section" id="previewSection" style="display: none;">
                        <h5><i class="fas fa-eye"></i> Data Preview</h5>
                        <div class="preview-table-container">
                            <table class="preview-table">
                                <thead id="previewTableHead"></thead>
                                <tbody id="previewTableBody"></tbody>
                            </table>
                        </div>
                        <div class="preview-stats">
                            <span id="previewStats">No data loaded</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-info" onclick="BulkImport.previewCSV()">
                            <i class="fas fa-eye"></i> Preview Data
                        </button>
                        <button type="submit" class="btn btn-primary" disabled id="importBtn">
                            <span class="btn-text">
                                <i class="fas fa-upload"></i> Import Students
                            </span>
                            <span class="btn-loading" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i> Importing...
                            </span>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="Modal.closeModal()">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        `;

        // Use existing modal system
        Modal.showCustomModal('Bulk Import Students', modalContent, {
            width: '900px',
            showConfirm: false,
            showCancel: false
        }).then(() => {
            this.initBulkImportForm();
        });
    },

    // Initialize bulk import form
    initBulkImportForm: function() {
        // File input change handler
        const csvFileInput = document.getElementById('csvFile');
        if (csvFileInput) {
            csvFileInput.addEventListener('change', function() {
                const fileName = this.files[0]?.name || 'Choose CSV file...';
                const placeholder = document.querySelector('.file-placeholder');
                if (placeholder) {
                    placeholder.textContent = fileName;
                }
                
                if (this.files[0]) {
                    BulkImport.previewCSV();
                }
            });
        }

        // Form submission handler
        const bulkImportForm = document.getElementById('bulkImportForm');
        if (bulkImportForm) {
            bulkImportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                BulkImport.handleBulkImport(this);
            });
        }
    },

    // Preview CSV content
    previewCSV: function() {
        const fileInput = document.getElementById('csvFile');
        const file = fileInput?.files[0];
        
        if (!file) {
            FacultyUtils.showError('Please select a CSV file first');
            return;
        }
        
        if (!file.name.toLowerCase().endsWith('.csv')) {
            FacultyUtils.showError('Please select a valid CSV file');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const csv = e.target.result;
            const lines = csv.split('\n').filter(line => line.trim());
            
            if (lines.length < 2) {
                FacultyUtils.showError('CSV file should contain at least a header row and one data row');
                return;
            }
            
            // Parse CSV
            const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
            const previewRows = lines.slice(1, 6); // Show first 5 rows
            
            // Build preview table
            let tableHead = '<tr>';
            headers.forEach(header => {
                tableHead += `<th>${header}</th>`;
            });
            tableHead += '</tr>';
            
            const previewTableHead = document.getElementById('previewTableHead');
            if (previewTableHead) {
                previewTableHead.innerHTML = tableHead;
            }
            
            let tableBody = '';
            previewRows.forEach(row => {
                const cells = row.split(',').map(cell => cell.trim().replace(/"/g, ''));
                tableBody += '<tr>';
                cells.forEach(cell => {
                    tableBody += `<td>${cell}</td>`;
                });
                tableBody += '</tr>';
            });
            
            const previewTableBody = document.getElementById('previewTableBody');
            if (previewTableBody) {
                previewTableBody.innerHTML = tableBody;
            }
            
            // Show preview section
            const previewSection = document.getElementById('previewSection');
            if (previewSection) {
                previewSection.style.display = 'block';
            }
            
            const previewStats = document.getElementById('previewStats');
            if (previewStats) {
                previewStats.textContent = `${lines.length - 1} total records found. Showing first ${Math.min(5, lines.length - 1)} rows.`;
            }
            
            // Enable import button
            const importBtn = document.getElementById('importBtn');
            if (importBtn) {
                importBtn.disabled = false;
            }
        };
        
        reader.readAsText(file);
    },

    // Handle bulk import
    handleBulkImport: function(form) {
        // Show coming soon message for now
        Swal.fire({
            title: 'Coming Soon!',
            text: 'Bulk CSV import functionality will be implemented in the next update.',
            icon: 'info',
            confirmButtonText: 'Got it!'
        });
        
        // Future implementation
        /*
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const loadingState = FacultyUtils.handleFormSubmit(form, submitBtn);
        
        formData.append('action', 'bulk_import');
        formData.append('csrf_token', window.csrfToken);
        
        FacultyUtils.fetchWithErrorHandling('ajax/process_bulk_import.php', {
            method: 'POST',
            body: formData
        })
        .then(data => {
            if (data.success) {
                FacultyUtils.showSuccess(data.message || 'Students imported successfully!');
                Modal.closeModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                FacultyUtils.showError(data.message || 'Import failed!');
            }
        })
        .catch(error => {
            console.error('Import error:', error);
            FacultyUtils.showError('Network error occurred during import!');
        })
        .finally(() => {
            loadingState.restore();
        });
        */
    },

    // Download sample CSV
    downloadSampleCSV: function() {
        const csvContent = `student_id,first_name,last_name,middle_initial,email,birthday,status
2024-123456,Juan,Dela Cruz,S,juan.delacruz@email.com,2000-01-15,active
2024-123457,Maria,Santos,A,maria.santos@email.com,1999-12-20,active
2024-123458,Jose,Garcia,,jose.garcia@email.com,2001-03-10,pending`;
        
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'student_import_sample.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }
};

// Global function for backward compatibility
function openBulkImportModal() {
    window.BulkImport.openBulkImportModal();
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Bulk Import module initialized');
});
