/**
 * CAR (Course Assessment Report) Management
 * Handles CAR data preparation and PDF generation with HTML Preview
 */

const CARManager = {
    currentClassCode: null,
    currentStep: 1,
    totalSteps: 4,
    carData: null,
    courseOutcomes: [],
    
    /**
     * Initialize CAR Manager
     */
    init() {
        console.log('CAR Manager initialized');
    },
    
    /**
     * Open CAR Preparation
     */
    async open() {
        // Check if class is selected
        if (typeof FGS === 'undefined' || !FGS.currentClassCode) {
            Toast.fire({
                icon: 'warning',
                title: 'Please select a class first'
            });
            return;
        }
        
        // Check if grades are complete
        if (!FGS.students || FGS.students.length === 0) {
            Toast.fire({
                icon: 'warning',
                title: 'No students found. Load students first.'
            });
            return;
        }
        
        this.currentClassCode = FGS.currentClassCode;
        
        // Check if CAR data exists
        showLoading();
        const exists = await this.checkCARDataExists();
        hideLoading();
        
        if (exists) {
            // Show options: Edit or Generate PDF
            this.showCAROptions();
        } else {
            // Start CAR preparation wizard
            this.startCARWizard();
        }
    },
    
    /**
     * Check if CAR data exists for current class
     */
    async checkCARDataExists() {
        try {
            const response = await fetch(`${window.APP?.apiPath || '/faculty/ajax/'}car_handler.php?action=check&class_code=${this.currentClassCode}`);
            const data = await response.json();
            
            if (data.success && data.exists) {
                this.carData = data.car_data;
                return true;
            }
            return false;
        } catch (error) {
            console.error('Error checking CAR data:', error);
            return false;
        }
    },
    
    /**
     * Show CAR options (Edit or Generate)
     */
    showCAROptions() {
    Swal.fire({
        title: '<i class="fas fa-file-alt" style="color: #FDB81E;"></i> CAR Data Found',
        html: `
            <p style="color: #e5e7eb; font-size: 16px; font-weight: 500;">CAR data already exists for this class.</p>
            <p style="color: #a0aec0; font-size: 14px;">
                Status: <strong style="color: #FDB81E;">${this.carData.status || 'draft'}</strong>
            </p>
        `,
        icon: 'info',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '<i class="fas fa-file-word"></i> Generate Word Document',
        denyButtonText: '<i class="fas fa-edit"></i> Edit Data',
        cancelButtonText: '<i class="fas fa-times"></i> Cancel',
        confirmButtonColor: '#FDB81E',
        denyButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        customClass: {
            popup: 'nu-swal-popup',
            title: 'nu-swal-title',
            htmlContainer: 'nu-swal-html',
            confirmButton: 'nu-swal-confirm',
            denyButton: 'nu-swal-cancel',
            cancelButton: 'nu-swal-cancel'
        }
    }).then(async (result) => {  //  ADD: async
        if (result.isConfirmed) {
            //  Reload fresh data before generating
            await this.checkCARDataExists();
            await this.generateCAR();
        } else if (result.isDenied) {
            //  Reload fresh data before editing
            await this.checkCARDataExists();
            this.startCARWizard();
        }
    });
},
    
    /**
     * Start CAR Preparation Wizard
     */
    async startCARWizard() {
        // Reset to step 1
        this.currentStep = 1;
        
        // Load class info
        await this.loadClassInfo();
        
        // Load course outcomes
        // await this.loadCourseOutcomes();
        
        // Load existing data if any
        if (this.carData) {
            this.populateForm();
        }
        
        // Show modal
        document.getElementById('car-wizard-modal').style.display = 'flex';
        
        // Show step 1
        this.showStep(1);
    },
    
    /**
     * Load class information
     */
    async loadClassInfo() {
        try {
            // Get class info from FGS or fetch from server
            const classInfo = `${FGS.currentClassCode} - ${FGS.currentClassName || 'Class'}`;
            document.getElementById('car-class-info').textContent = classInfo;
        } catch (error) {
            console.error('Error loading class info:', error);
        }
    },
    
    /**
     * Load course outcomes for recommendations
     */
    async loadCourseOutcomes() {
        try {
            // Extract course code from class code (e.g., "CTAPROJ1-INF231-24-T1" -> "CTAPROJ1")
            const courseCode = this.currentClassCode.split('-')[0];
            
            const response = await fetch(`../../ajax/get_subject_outcomes.php?code=${courseCode}`);
            const data = await response.json();
            
            if (data.success && data.outcomes) {
                this.courseOutcomes = data.outcomes;
                this.renderRecommendations();
            } else {
                // No outcomes found
                document.getElementById('recommendations-container').innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #6b7280;">
                        <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <p>No course outcomes found for this subject.</p>
                        <p style="font-size: 14px;">Please add course outcomes in the admin panel first.</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading course outcomes:', error);
            document.getElementById('recommendations-container').innerHTML = `
                <div style="text-align: center; padding: 40px; color: #ef4444;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p>Error loading course outcomes.</p>
                </div>
            `;
        }
    },
    
    /**
     * Render recommendations section
     */
    renderRecommendations() {
        const container = document.getElementById('recommendations-container');
        
        if (this.courseOutcomes.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #6b7280;">
                    <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p>No course outcomes available for this subject.</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        
        this.courseOutcomes.forEach(outcome => {
            html += `
                <div class="recommendation-card">
                    <div class="recommendation-card-header">
                        <div class="co-badge">CO${outcome.number}</div>
                        <div class="co-description">${this.escapeHtml(outcome.description)}</div>
                    </div>
                    <textarea 
                        id="recommendation-co${outcome.number}" 
                        class="form-control"
                        placeholder="Enter recommendation for CO${outcome.number}..."
                        rows="4"
                    ></textarea>
                </div>
            `;
        });
        
        container.innerHTML = html;
    },
    
    /**
     * Populate form with existing data
     */
   
    /**
 * Populate form with existing data
 */
populateForm() {
    if (!this.carData) return;
    
    // Populate text fields
    const teachingStrategies = document.getElementById('teaching-strategies');
    if (teachingStrategies) teachingStrategies.value = this.carData.teaching_strategies || '';
    
    const problemsEncountered = document.getElementById('problems-encountered');
    if (problemsEncountered) problemsEncountered.value = this.carData.problems_encountered || '';
    
    const actionsTaken = document.getElementById('actions-taken');
    if (actionsTaken) actionsTaken.value = this.carData.actions_taken || '';
    
    const proposedActions = document.getElementById('proposed-actions');
    if (proposedActions) proposedActions.value = this.carData.proposed_actions || '';
    
    // Populate interventions table
    const tbody = document.getElementById('interventions-table-body');
    const emptyState = document.getElementById('interventions-empty-state');
    
    if (tbody && this.carData.intervention_activities) {
        // Clear existing rows
        tbody.innerHTML = '';
        
        try {
            // Parse JSON interventions
            let interventions = this.carData.intervention_activities;
            
            // If it's a string, parse it as JSON
            if (typeof interventions === 'string') {
                interventions = JSON.parse(interventions);
            }
            
            console.log('Parsed interventions:', interventions);
            
            // Add rows for each intervention
            if (Array.isArray(interventions) && interventions.length > 0) {
                interventions.forEach((intervention, index) => {
                    interventionCounter++;
                    
                    const row = document.createElement('tr');
                    row.id = `intervention-row-${interventionCounter}`;
                    row.innerHTML = `
                        <td>
                            <input type="text" 
                                   class="form-control" 
                                   id="intervention-desc-${interventionCounter}"
                                   value="${intervention.description || ''}"
                                   style="width: 100%;">
                        </td>
                        <td>
                            <input type="number" 
                                   class="form-control" 
                                   id="intervention-students-${interventionCounter}"
                                   value="${intervention.students || 0}"
                                   min="0"
                                   style="width: 100%;">
                        </td>
                        <td style="text-align: center;">
                            <button type="button" 
                                    class="btn btn-danger btn-sm" 
                                    onclick="removeInterventionRow(${interventionCounter})"
                                    title="Remove">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                    
                    tbody.appendChild(row);
                });
                
                // Hide empty state
                if (emptyState) emptyState.style.display = 'none';
            } else {
                // Show empty state if no interventions
                if (emptyState) emptyState.style.display = 'block';
            }
        } catch (error) {
            console.error('Error parsing interventions JSON:', error);
            if (emptyState) emptyState.style.display = 'block';
        }
    }
},
    /**
     * Show specific step
     */
    showStep(step) {
        this.currentStep = step;
        
        // Hide all steps
        document.querySelectorAll('.wizard-step').forEach(el => {
            el.classList.remove('active');
        });
        
        // Show current step
        document.getElementById(`car-step-${step}`).classList.add('active');
        
        // Update progress steps
        document.querySelectorAll('.progress-step').forEach((el, index) => {
            el.classList.remove('active', 'completed');
            if (index + 1 < step) {
                el.classList.add('completed');
            } else if (index + 1 === step) {
                el.classList.add('active');
            }
        });
        
        // Update progress bar
        const progress = (step / this.totalSteps) * 100;
        document.getElementById('wizard-progress-fill').style.width = `${progress}%`;
        
        // Update buttons
        document.getElementById('wizard-prev-btn').disabled = (step === 1);
        
        const nextBtn = document.getElementById('wizard-next-btn');
if (step === this.totalSteps) {
    nextBtn.innerHTML = '<i class="fas fa-check"></i> Complete';
    nextBtn.removeAttribute('onclick');
    nextBtn.onclick = () => this.completeWizard();
} else {
    nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right"></i>';
    nextBtn.removeAttribute('onclick');
    nextBtn.onclick = () => this.nextStep();
}
    },
    
    /**
     * Next step
     */
    nextStep() {
        // Validate current step
        if (!this.validateStep(this.currentStep)) {
            return;
        }
        
        if (this.currentStep < this.totalSteps) {
            this.showStep(this.currentStep + 1);
        }
    },
    
    /**
     * Previous step
     */
    previousStep() {
        if (this.currentStep > 1) {
            this.showStep(this.currentStep - 1);
        }
    },
    
    /**
     * Validate current step
     */
    validateStep(step) {
        let fieldId, fieldName;
        
        switch(step) {
            case 1:
                fieldId = 'teaching-strategies';
                fieldName = 'Teaching Strategies';
                break;
            case 2:
    // Validate interventions table
    const interventions = getInterventionsData();
    if (interventions.length === 0) {
        Toast.fire({
            icon: 'warning',
            title: 'Please add at least one intervention activity'
        });
        return false;
    }
    return true;
            case 3:
                // Validate both fields
                const problems = document.getElementById('problems-encountered').value.trim();
                const actions = document.getElementById('actions-taken').value.trim();
                
                if (!problems) {
                    Toast.fire({
                        icon: 'warning',
                        title: 'Please enter Problems Encountered'
                    });
                    return false;
                }
                
                if (!actions) {
                    Toast.fire({
                        icon: 'warning',
                        title: 'Please enter Actions Taken'
                    });
                    return false;
                }
                return true;
            case 4:
                fieldId = 'proposed-actions';
                fieldName = 'Proposed Actions';
                break;
            case 5:
                // Validate all recommendations
                let allFilled = true;
                this.courseOutcomes.forEach(outcome => {
                    const rec = document.getElementById(`recommendation-co${outcome.number}`).value.trim();
                    if (!rec) {
                        allFilled = false;
                    }
                });
                
                if (!allFilled) {
                    Toast.fire({
                        icon: 'warning',
                        title: 'Please provide recommendations for all course outcomes'
                    });
                    return false;
                }
                return true;
        }
        
        // Check if field has value
        if (fieldId) {
            const value = document.getElementById(fieldId).value.trim();
            if (!value) {
                Toast.fire({
                    icon: 'warning',
                    title: `Please enter ${fieldName}`
                });
                return false;
            }
        }
        
        return true;
    },
    
    /**
     * Complete wizard and save
     */
    async completeWizard() {
        // Validate final step
        if (!this.validateStep(this.currentStep)) {
            return;
        }
        
        // Collect all data
        const formData = this.collectFormData();
        
        // Show confirmation
        Swal.fire({
            title: 'Complete CAR Preparation?',
            text: 'This will save all CAR data and show a preview.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check"></i> Yes, Complete',
            cancelButtonText: '<i class="fas fa-times"></i> Cancel',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280'
        }).then(async (result) => {
            if (result.isConfirmed) {
                await this.saveCARData(formData, 'completed');
            }
        });
    },
    
     /**
     * Save as draft
     */
   async saveDraft() {
        const formData = this.collectFormData();
        
        // Update carData in memory with latest form values
        this.carData = {
            ...this.carData,
            teaching_strategies: formData.teaching_strategies,
            intervention_activities: formData.intervention_activities,
            problems_encountered: formData.problems_encountered,
            actions_taken: formData.actions_taken,
            proposed_actions: formData.proposed_actions
        };
        
        await this.saveCARData(formData, 'draft');
    },
    
    /**
     * Collect form data
     */
    collectFormData() {
        const recommendations = {};
        this.courseOutcomes.forEach(outcome => {
            const rec = document.getElementById(`recommendation-co${outcome.number}`).value.trim();
            recommendations[`co${outcome.number}`] = rec;
        });
        
        return {
            class_code: this.currentClassCode,
            teaching_strategies: document.getElementById('teaching-strategies').value.trim(),
            intervention_activities: JSON.stringify(getInterventionsData()),
            problems_encountered: document.getElementById('problems-encountered').value.trim(),
            actions_taken: document.getElementById('actions-taken').value.trim(),
            proposed_actions: document.getElementById('proposed-actions').value.trim(),
            recommendations: JSON.stringify(recommendations)
        };
    },
    
    /**
     * Save CAR data to database
     */
    async saveCARData(formData, status) {
    showLoading();
    
    formData.status = status;
    formData.action = 'save';
    
    const fd = new FormData();
    Object.keys(formData).forEach(key => {
        fd.append(key, formData[key]);
    });
    
    try {
        const response = await fetch(`${window.APP?.apiPath || '/faculty/ajax/'}car_handler.php`, {
            method: 'POST',
            body: fd
        });
        
        const data = await response.json();
        
        hideLoading();
        
        if (data.success) {
            Toast.fire({
                icon: 'success',
                title: status === 'draft' ? 'Draft saved!' : 'CAR data saved successfully!'
            });
            
            // ADDED: Reload fresh data after saving
            await this.checkCARDataExists();
            
            if (status === 'completed') {
                this.closeWizard();
                await this.generateCAR();
            }
        } else {
            Toast.fire({
                icon: 'error',
                title: data.message || 'Failed to save CAR data'
            });
        }
    } catch (error) {
        hideLoading();
        console.error('Error saving CAR data:', error);
        Toast.fire({
            icon: 'error',
            title: 'Failed to save CAR data'
        });
    }
},
    
    /**
     * Generate CAR - Use new CAR_PDF system
     */
    async generateCAR() {
        console.log('🎯 Generating CAR for:', this.currentClassCode);
        
        // Use the new CAR_PDF system (car-pdf-generator.js)
        await CAR_PDF.preview(this.currentClassCode);
    },
    
    /**
     * Download CAR as PDF
     */
    async downloadCAR(classCode) {
        console.log('📥 Downloading CAR PDF...');
        
        // Use the new CAR_PDF system
        await CAR_PDF.generate(classCode);
    },
    
    /**
     * Close wizard
     */
    closeWizard() {
        document.getElementById('car-wizard-modal').style.display = 'none';
        this.currentStep = 1;
        this.carData = null;
    },
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// 
// INTERVENTION ACTIVITIES MANAGEMENT
// 
let interventionCounter = 0;

function addInterventionRow() {
    interventionCounter++;
    
    const tbody = document.getElementById('interventions-table-body');
    const emptyState = document.getElementById('interventions-empty-state');
    
    // Hide empty state
    if (emptyState) {
        emptyState.style.display = 'none';
    }
    
    const row = document.createElement('tr');
    row.id = `intervention-row-${interventionCounter}`;
    row.innerHTML = `
        <td>
            <input type="text" 
                   class="form-control" 
                   id="intervention-desc-${interventionCounter}"
                   placeholder="e.g., Remedial classes for struggling students"
                   style="width: 100%;">
        </td>
        <td>
            <input type="number" 
                   class="form-control" 
                   id="intervention-students-${interventionCounter}"
                   placeholder="0"
                   min="0"
                   style="width: 100%;">
        </td>
        <td style="text-align: center;">
            <button type="button" 
                    class="btn btn-danger btn-sm" 
                    onclick="removeInterventionRow(${interventionCounter})"
                    title="Remove">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
}

function removeInterventionRow(id) {
    const row = document.getElementById(`intervention-row-${id}`);
    if (row) {
        row.remove();
    }
    
    // Show empty state if no rows left
    const tbody = document.getElementById('interventions-table-body');
    const emptyState = document.getElementById('interventions-empty-state');
    
    if (tbody.children.length === 0 && emptyState) {
        emptyState.style.display = 'block';
    }
}

function getInterventionsData() {
    const rows = document.querySelectorAll('#interventions-table-body tr');
    const interventions = [];
    
    rows.forEach(row => {
        const id = row.id.split('-')[2];
        const desc = document.getElementById(`intervention-desc-${id}`).value.trim();
        const students = document.getElementById(`intervention-students-${id}`).value.trim();
        
        if (desc && students) {
            interventions.push({
                description: desc,
                students: students
            });
        }
    });
    
    return interventions;
}

/**
 * Global functions for button onclick
 */
function openCARPreparation() {
    CARManager.open();
}

function closeCARWizard() {
    CARManager.closeWizard();
}

function nextWizardStep() {
    CARManager.nextStep();
}

function previousWizardStep() {
    CARManager.previousStep();
}

function saveCARDraft() {
    CARManager.saveDraft();
}

/**
 * COA (Course Outcomes Assessment Summary) Preview & Download
 */
function openCOAPreparation() {
    // Try multiple ways to get the class code
    let classCode = document.querySelector('[data-class-code]')?.dataset.classCode;
    
    if (!classCode) {
        classCode = document.getElementById('flex_grading_class')?.value;
    }
    
    if (!classCode) {
        classCode = document.querySelector('#classSelect')?.value;
    }
    
    if (!classCode && typeof FGS !== 'undefined') {
        classCode = FGS.currentClassCode;
    }
    
    if (!classCode) {
        Swal.fire('Error', 'Please select a class first', 'error');
        return;
    }

    // Fetch COA HTML
    fetch(`${window.APP?.apiPath || '/faculty/ajax/'}generate_coa_html.php?class_code=${encodeURIComponent(classCode)}`)
        .then(res => {
            if (!res.ok) {
                return res.text().then(text => {
                    try {
                        return Promise.reject(JSON.parse(text));
                    } catch (e) {
                        return Promise.reject({ message: `HTTP ${res.status}: ${text}` });
                    }
                });
            }
            return res.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to generate COA');
            }

            // Show preview modal
            showCOAPreview(data.html, classCode);
        })
        .catch(err => {
            console.error('COA Error:', err);
            const errorMsg = err.message || JSON.stringify(err);
            Swal.fire('Error', 'Failed to generate COA: ' + errorMsg, 'error');
        });
}

function showCOAPreview(html, classCode) {
    try {
        // Create modal for preview - SAME APPROACH AS CAR PREVIEW
        const modal = document.createElement('div');
        modal.id = 'coa-preview-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10001;
            padding: 20px;
        `;
        
        // A4 Portrait dimensions: 210mm × 297mm (8.27" × 11.69")
        const aspect = 210/297; // ≈0.707
        const scaledWidth = Math.min(window.innerWidth - 80, 900);
        const scaledHeight = Math.round(scaledWidth / aspect); // maintain portrait ratio
        
        // Store HTML for download button
        window.currentCOAHtml = html;
        window.currentCOAClassCode = classCode;
        
        modal.innerHTML = `
            <div style="
                background: white;
                width: ${scaledWidth}px;
                max-width: 95vw;
                height: ${scaledHeight}px;
                max-height: 90vh;
                border-radius: 8px;
                display: flex;
                flex-direction: column;
                box-shadow: 0 25px 50px rgba(0,0,0,0.3);
                overflow: hidden;
                box-sizing: border-box;
            ">
                <div style="
                    padding: 15px 20px;
                    border-bottom: 1px solid #ddd;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #f5f5f5;
                    flex-shrink: 0;
                ">
                    <h3 style="margin: 0; color: #333; font-size: 16px;">COA Report - ${classCode}</h3>
                    <div>
                        <button onclick="document.getElementById('coa-preview-modal').remove()" style="
                            background: #dc3545;
                            color: white;
                            border: none;
                            padding: 8px 16px;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 14px;
                            margin-left: 10px;
                            white-space: nowrap;
                        ">Close</button>
                        <button onclick="downloadCOAPDF(window.currentCOAHtml, window.currentCOAClassCode); document.getElementById('coa-preview-modal').remove();" style="
                            background: #28a745;
                            color: white;
                            border: none;
                            padding: 8px 16px;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 14px;
                            margin-left: 10px;
                            white-space: nowrap;
                        ">Download PDF</button>
                    </div>
                </div>
                <div style="
                    flex: 1;
                    overflow: auto;
                    padding: 10px;
                    background: #e8e8e8;
                    box-sizing: border-box;
                ">
                    <div style="
                        background: white;
                        padding: 20px;
                        border: 1px solid #999;
                        margin: 0;
                        width: 100%;
                        height: 100%;
                        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                        overflow: auto;
                        box-sizing: border-box;
                    ">
                        ${html}
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close on background click
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        };
        
        console.log('COA Preview modal opened successfully');
    } catch (error) {
        console.error('COA Preview Error:', error);
        Swal.fire('Error', 'Failed to display COA report: ' + error.message, 'error');
    }
}

function downloadCOAPDF(html, classCode) {
    const { jsPDF } = window.jspdf;
    const html2canvas = window.html2canvas;

    if (!html2canvas || !jsPDF) {
        Swal.fire('Error', 'PDF libraries not loaded', 'error');
        return;
    }

    Swal.fire({
        title: 'Generating PDF...',
        html: 'Please wait while we create your Course Outcomes Assessment Summary.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const container = document.createElement('div');
    container.innerHTML = html;
    container.style.cssText = `
        position: absolute;
        left: -9999px;
        width: 1000px;
        background: white;
        padding: 20px;
    `;
    document.body.appendChild(container);

    html2canvas(container, {
        scale: 2,
        useCORS: true,
        allowTaint: true
    }).then(canvas => {
        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        const imgData = canvas.toDataURL('image/png');
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = pdf.internal.pageSize.getHeight();
        const imgWidth = pdfWidth - 20;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;

        let yPosition = 10;
        let remainingHeight = imgHeight;

        while (remainingHeight > 0) {
            const canvasHeight = Math.min(remainingHeight, pdfHeight - 20);
            const sourceYOffset = imgHeight - remainingHeight;

            pdf.addImage(imgData, 'PNG', 10, yPosition, imgWidth, canvasHeight);
            remainingHeight -= (pdfHeight - 20);

            if (remainingHeight > 0) {
                pdf.addPage();
                yPosition = 10;
            }
        }

        const filename = `COA_${classCode}_${new Date().getTime()}.pdf`;
        pdf.save(filename);

        document.body.removeChild(container);
        Swal.close();

        Swal.fire('Success', 'COA PDF downloaded successfully!', 'success');
    }).catch(err => {
        console.error('Error generating PDF:', err);
        document.body.removeChild(container);
        Swal.fire('Error', 'Failed to generate PDF: ' + err.message, 'error');
    });
}

/**
 * Initialize on page load
 */
document.addEventListener('DOMContentLoaded', () => {
    CARManager.init();
});
