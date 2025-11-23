console.log('Classes.js loading...');

try {
    // Class management system that mirrors subjects.js
    class ClassManager {
        constructor() {
            console.log('ClassManager constructor called');
            this.editMode = false;
            this.currentEditId = null;
            this.allScheduleIds = []; // Track all schedule IDs for group editing
            this.isSubmitting = false;
            this.init();
        }
        
        debugFormData() {
            console.log('=== FORM DEBUG ===');
            const form = document.getElementById('addClassForm');
            
            // Check schedules
            const scheduleCards = form.querySelectorAll('.schedule-card');
            console.log(`Found ${scheduleCards.length} schedule cards`);
            
            scheduleCards.forEach((card, index) => {
                const daySelect = card.querySelector('[name="day[]"]');
                const timeValue = card.querySelector('[name="time[]"]');
                const timeDisplay = card.querySelector('.formatted-time');
                
                console.log(`Schedule ${index + 1}:`, {
                    day: daySelect ? daySelect.value : 'no day select',
                    timeValue: timeValue ? timeValue.value : 'no time value',
                    timeDisplay: timeDisplay ? timeDisplay.textContent : 'no time display'
                });
            });
            
            console.log('=== END DEBUG ===');
        }
        
        init() {
            console.log('ClassManager initializing...');
            
            // Get the form
            const form = document.getElementById('addClassForm');
            if (!form) {
                console.log('Class form not found on this page');
                return;
            }
            
            console.log('Class form found, setting up handlers');
            
            // Just add our event listener without cloning (simpler approach)
            form.addEventListener('submit', (e) => this.handleSubmit(e));
            
            console.log('Class form AJAX handler initialized');
        }
        
        handleSubmit(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Class form submitted via AJAX');
            
            // Prevent multiple submissions (same as subjects.js)
            if (this.isSubmitting) {
                return;
            }
            
            // Validate form
            if (!this.validateForm()) {
                return;
            }
            
            // Proceed with submission
            this.performSubmit();
        }
        
        validateForm() {
            console.log('Validating class form...');
            
            const form = document.getElementById('addClassForm');
            
            // Check required fields
            const requiredFields = [
                { name: 'section', label: 'Section' },
                { name: 'academic_year', label: 'Academic Year' },
                { name: 'term', label: 'Term' },
                { name: 'course_code', label: 'Course' },
                { name: 'room', label: 'Room' },
                { name: 'faculty_id', label: 'Faculty' }
            ];
            
            let isValid = true;
            let firstInvalidField = null;
            
            for (let field of requiredFields) {
                const input = form.querySelector(`[name="${field.name}"]`);
                const value = input ? input.value.trim() : '';
                
                if (!value) {
                    isValid = false;
                    if (!firstInvalidField) {
                        firstInvalidField = input;
                    }
                    
                    if (input) {
                        input.style.borderColor = '#dc2626';
                        setTimeout(() => input.style.borderColor = '', 3000);
                    }
                }
            }
            
            if (!isValid) {
                if (window.Toast) {
                    Toast.fire({
                        icon: 'warning',
                        title: 'Please fill in all required fields'
                    });
                } else {
                    alert('Please fill in all required fields');
                }
                
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
                return false;
            }
            
            // Enhanced schedule validation that works with TimePicker
            console.log('Checking schedule validation...');
            
            // Get all schedules
            const scheduleCards = form.querySelectorAll('.schedule-card');
            let hasValidSchedule = false;
            let invalidSchedules = [];
            
            console.log(`Found ${scheduleCards.length} schedule cards`);
            
            scheduleCards.forEach((card, index) => {
                const scheduleNum = index + 1;
                
                // Check day selection
                const daySelect = card.querySelector('select[name="day[]"]');
                const dayValue = daySelect ? daySelect.value.trim() : '';
                
                // Check time inputs - look for any time format
                const timeHiddenInput = card.querySelector('input[name="time[]"]');
                const timeDisplay = card.querySelector('.formatted-time');
                
                // Check individual time components as fallback
                const startHour = card.querySelector('.hour-input:first-of-type')?.value || '';
                const startMinute = card.querySelector('.minute-input:first-of-type')?.value || '';
                const endHour = card.querySelector('.hour-input:last-of-type')?.value || '';
                const endMinute = card.querySelector('.minute-input:last-of-type')?.value || '';
                
                console.log(`Schedule ${scheduleNum}:`, {
                    day: dayValue,
                    timeHidden: timeHiddenInput ? timeHiddenInput.value : 'NO HIDDEN INPUT',
                    timeDisplay: timeDisplay ? timeDisplay.textContent : 'NO DISPLAY',
                    timeComponents: { startHour, startMinute, endHour, endMinute }
                });
                
                // Try to manually build time if TimePicker didn't do it
                let manualTimeString = '';
                if (startHour && startMinute && endHour && endMinute) {
                    // Get AM/PM selectors for this specific card
                    const ampmSelectors = card.querySelectorAll('.ampm-selector');
                    const startAmPm = ampmSelectors[0] ? 
                        (ampmSelectors[0].querySelector('.ampm-option.active')?.dataset.ampm || 'AM') : 'AM';
                    const endAmPm = ampmSelectors[1] ? 
                        (ampmSelectors[1].querySelector('.ampm-option.active')?.dataset.ampm || 'AM') : 'AM';
                    
                    manualTimeString = `${startHour.padStart(2, '0')}:${startMinute.padStart(2, '0')} ${startAmPm} - ${endHour.padStart(2, '0')}:${endMinute.padStart(2, '0')} ${endAmPm}`;
                    
                    console.log(`Built manual time for schedule ${scheduleNum}:`, manualTimeString);
                    
                    // Update the hidden input with our manually built time
                    if (timeHiddenInput && (!timeHiddenInput.value || timeHiddenInput.value === 'Not set')) {
                        timeHiddenInput.value = manualTimeString;
                        console.log(`Updated hidden input for schedule ${scheduleNum}`);
                    }
                    
                    // Update the display too
                    if (timeDisplay && timeDisplay.textContent === 'Not set') {
                        timeDisplay.textContent = manualTimeString;
                        console.log(`Updated display for schedule ${scheduleNum}`);
                    }
                }
                
                // Consider schedule valid if:
                // 1. Has a day selected AND
                // 2. Either has time in hidden input OR has all time components filled OR manual time was built
                const hasDay = dayValue !== '';
                const hasHiddenTime = timeHiddenInput && timeHiddenInput.value && 
                                    timeHiddenInput.value !== 'Not set' && 
                                    timeHiddenInput.value.trim() !== '';
                const hasTimeComponents = startHour && startMinute && endHour && endMinute;
                const hasDisplayTime = timeDisplay && timeDisplay.textContent !== 'Not set';
                const hasManualTime = manualTimeString !== '';
                
                const isScheduleValid = hasDay && (hasHiddenTime || hasTimeComponents || hasDisplayTime || hasManualTime);
                
                console.log(`Schedule ${scheduleNum} validation:`, {
                    hasDay,
                    hasHiddenTime,
                    hasTimeComponents,
                    hasDisplayTime,
                    hasManualTime,
                    isValid: isScheduleValid
                });
                
                if (isScheduleValid) {
                    hasValidSchedule = true;
                    
                    // If hidden input is empty but we have time components, try to build the time string
                    if (!hasHiddenTime && hasTimeComponents) {
                        const startAmPm = card.querySelector('.ampm-option.active')?.dataset.ampm || 'AM';
                        const endAmPmButtons = card.querySelectorAll('.ampm-option.active');
                        const endAmPm = endAmPmButtons.length > 1 ? endAmPmButtons[1].dataset.ampm : 'AM';
                        
                        const timeString = `${startHour.padStart(2, '0')}:${startMinute.padStart(2, '0')} ${startAmPm} - ${endHour.padStart(2, '0')}:${endMinute.padStart(2, '0')} ${endAmPm}`;
                        
                        if (timeHiddenInput) {
                            timeHiddenInput.value = timeString;
                            console.log(`Fixed time for schedule ${scheduleNum}:`, timeString);
                        }
                    }
                } else {
                    invalidSchedules.push(scheduleNum);
                }
            });
            
            if (!hasValidSchedule) {
                const message = invalidSchedules.length === 1 
                    ? `Please complete schedule ${invalidSchedules[0]} (select day and set time)`
                    : `Please complete schedules: ${invalidSchedules.join(', ')} (select day and set time)`;
                
                if (window.Toast) {
                    Toast.fire({
                        icon: 'warning',
                        title: message
                    });
                } else {
                    alert(message);
                }
                return false;
            }
            
            console.log('Schedule validation passed!');
            
            console.log('Form validation passed');
            return true;
        }
        
        performSubmit() {
            // Set submitting flag
            this.isSubmitting = true;
            
            // Set loading state
            this.setLoadingState(true);
            
            // Debug: Check what data we're about to send
            this.debugFormData();
            
            // Prepare form data
            const formData = new FormData(document.getElementById('addClassForm'));
            const submitUrl = 'ajax/process_class.php';

            // Set action based on edit mode
            if (this.editMode && this.currentEditId) {
                formData.set('action', 'update_group'); // New action for group updates
                formData.set('primary_class_id', this.currentEditId);
                
                // Add all schedule IDs if available
                if (this.allScheduleIds && this.allScheduleIds.length > 0) {
                    formData.set('all_schedule_ids', JSON.stringify(this.allScheduleIds));
                }
            } else {
                formData.set('action', 'add');
            }
            
            // Debug log
            console.log('Sending form data:');
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}: ${value}`);
            }
            
            // Submit
            fetch(submitUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text(); // Get raw text first
            })
            .then(text => {
                console.log('Raw response:', text); // Debug the actual response
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was:', text);
                    throw new Error('Server returned invalid JSON: ' + text.substring(0, 100));
                }
            })
            .then(data => {
                console.log('Response data:', data);
                
                this.setLoadingState(false);
                this.isSubmitting = false;
                
                if (data.success) {
                    this.handleSuccess(data);
                } else {
                    this.handleError(data);
                }
            })
            .catch(error => {
                this.setLoadingState(false);
                this.isSubmitting = false;
                console.error('Class submission error:', error);
                
                if (window.Toast) {
                    Toast.fire({
                        icon: 'error',
                        title: 'Error: ' + (error.message || 'An unexpected error occurred!')
                    });
                } else {
                    alert('Error: ' + (error.message || 'An unexpected error occurred!'));
                }
            });
        }
        
        handleSuccess(data) {
            console.log('handleSuccess called');
            
            if (this.editMode) {
                // Show Toast success message for edit
                if (window.Toast) {
                    Toast.fire({ 
                        icon: 'success', 
                        title: 'Class updated successfully!' 
                    });
                } else {
                    alert('Class updated successfully!');
                }
                
                // Reset edit mode WITHOUT showing cancel message
                this.editMode = false;
                this.currentEditId = null;
                
                // Reset form
                const form = document.getElementById('addClassForm');
                form.reset();
                
                // Update form UI back to add mode
                this.updateFormForAdd();
                
                // Reset time displays safely
                this.resetFormStateQuietly();
                
                // Stay on classes page - just refresh the classes section
                setTimeout(() => {
                    if (typeof showSection === 'function') {
                        showSection('classes'); // Refresh the classes section
                    } else {
                        // Fallback: reload current page with classes section
                        const currentUrl = new URL(window.location);
                        currentUrl.searchParams.set('page', 'classes');
                        window.location.href = currentUrl.toString();
                    }
                }, 1500);
            } else {
                // Show Toast success message for add
                if (window.Toast) {
                    Toast.fire({ 
                        icon: 'success', 
                        title: 'Class created successfully!' 
                    });
                } else {
                    alert('Class created successfully!');
                }
                
                // Reset form
                document.getElementById('addClassForm').reset();
                this.resetFormState();

                // Add to table if we have the data
                if (data.newItem) {
                    this.addRowToTable(data.newItem);
                } else {
                    // If no newItem data, refresh page after delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            }
        }
        
        handleError(data) {
            if (window.Toast) {
                Toast.fire({
                    icon: 'error',
                    title: 'Error: ' + (data.message || 'An error occurred while processing your request.')
                });
            } else {
                alert('Error: ' + (data.message || 'An error occurred while processing your request.'));
            }
        }
        
        addRowToTable(newItem) {
            console.log('Adding row to table:', newItem);
            
            const classTable = document.querySelector('#classes .table tbody');
            
            if (!classTable) {
                console.log('Classes table not found, reloading page...');
                setTimeout(() => window.location.reload(), 1000);
                return;
            }
            
            // Remove "no classes" message if it exists
            const noDataRow = classTable.querySelector('tr td[colspan="8"]');
            if (noDataRow) {
                noDataRow.closest('tr').remove();
            }
            
            // Build schedule display
            const scheduleCount = newItem.created_count || newItem.schedule_count || 1;
            const scheduleDisplay = newItem.schedule_display || `${newItem.day} ${newItem.time}`;
            
            // Escape single quotes for onclick handlers
            const escapeQuotes = (str) => str ? str.replace(/'/g, "\\'") : '';
            
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><strong>${newItem.class_id}</strong></td>
                <td><span class="badge badge-primary">${newItem.section}</span></td>
                <td>
                    <div>
                        <strong>${newItem.course_code}</strong><br>
                        <small style="color: var(--text-muted);">${newItem.course_title || 'N/A'}</small>
                    </div>
                </td>
                <td>
                    <div class="schedule-display">
                        ${scheduleDisplay}
                        ${scheduleCount > 1 ? `<br><small class="text-muted">(${scheduleCount} schedules)</small>` : ''}
                    </div>
                </td>
                <td><span class="badge badge-info">${newItem.room}</span></td>
                <td>${newItem.faculty_name || 'Unassigned'}</td>
                <td>
                    <div>
                        <small style="color: var(--text-muted);">${newItem.academic_year}</small><br>
                        <span class="badge badge-success">${newItem.term}</span>
                    </div>
                </td>
                <td>
                    <div class="d-flex">
                        <button onclick="viewClassSchedules('${escapeQuotes(newItem.section)}', '${escapeQuotes(newItem.course_code)}', '${escapeQuotes(newItem.academic_year)}', '${escapeQuotes(newItem.term)}')" class="btn btn-info btn-sm">
                            <i class="fas fa-eye"></i>
                            View (${scheduleCount})
                        </button>
                        <button onclick="editClassGroup('${escapeQuotes(newItem.section)}', '${escapeQuotes(newItem.academic_year)}', '${escapeQuotes(newItem.term)}', '${escapeQuotes(newItem.course_code)}', ${newItem.faculty_id || 'null'}, '${escapeQuotes(newItem.room)}', '${escapeQuotes(newItem.schedule_data || '')}')" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i>
                            Edit
                        </button>
                        <button onclick="confirmDeleteClassGroup('${escapeQuotes(newItem.section)}', '${escapeQuotes(newItem.academic_year)}', '${escapeQuotes(newItem.term)}', '${escapeQuotes(newItem.course_code)}', ${newItem.faculty_id || 'null'}, '${escapeQuotes(newItem.room)}')" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i>
                            Delete
                        </button>
                    </div>
                </td>
            `;
            
            // Add the new row at the top
            classTable.insertBefore(newRow, classTable.firstChild);
            
            // Highlight the new row briefly
            newRow.style.backgroundColor = '#d4edda';
            setTimeout(() => {
                newRow.style.backgroundColor = '';
            }, 2000);
            
            console.log('Row added successfully');
        }
        
        setLoadingState(loading) {
            const submitBtn = document.querySelector('#addClassForm button[type="submit"]');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            
            if (loading) {
                submitBtn.disabled = true;
                if (btnText) btnText.style.display = 'none';
                if (btnLoading) btnLoading.style.display = 'flex';
            } else {
                submitBtn.disabled = false;
                if (btnText) btnText.style.display = 'flex';
                if (btnLoading) btnLoading.style.display = 'none';
            }
        }
        
        resetFormState() {
            // Reset time displays using TimePicker if available
            if (window.TimePicker && window.TimePicker.resetTimeDisplays) {
                window.TimePicker.resetTimeDisplays(document.getElementById('addClassForm'));
            }
            
            // Reset to first schedule only
            const scheduleList = document.getElementById('scheduleList');
            if (scheduleList) {
                const scheduleCards = scheduleList.querySelectorAll('.schedule-card');
                
                // Remove all but the first schedule
                for (let i = 1; i < scheduleCards.length; i++) {
                    scheduleCards[i].remove();
                }
                
                // Update schedule numbers
                if (window.TimePicker && window.TimePicker.updateScheduleNumbers) {
                    window.TimePicker.updateScheduleNumbers();
                }
            }
        }

        resetFormStateQuietly() {
            // Reset time displays without any messages
            const form = document.getElementById('addClassForm');
            if (!form) return;
            
            // Reset time-related elements manually without calling TimePicker.resetTimeDisplays
            const elementsToReset = [
                { selector: '.formatted-time', value: 'Not set' },
                { selector: '.time-value', value: '' },
                { selector: '.time-input', value: '' }
            ];
            
            elementsToReset.forEach(({ selector, value }) => {
                form.querySelectorAll(selector).forEach(element => {
                    if (selector === '.formatted-time') {
                        element.textContent = value;
                    } else {
                        element.value = value;
                    }
                });
            });
            
            // Reset AM/PM buttons
            form.querySelectorAll('.ampm-option').forEach(button => {
                button.classList.remove('active');
                button.setAttribute('aria-pressed', 'false');
            });
            
            form.querySelectorAll('.ampm-selector').forEach(selector => {
                const firstButton = selector.querySelector('.ampm-option[data-ampm="AM"]');
                if (firstButton) {
                    firstButton.classList.add('active');
                    firstButton.setAttribute('aria-pressed', 'true');
                }
            });
            
            // Reset container states
            form.querySelectorAll('.time-range-container').forEach(container => {
                container.classList.remove('time-range-valid', 'time-range-invalid', 'validation-error');
                // Clear warnings without calling TimePicker methods
                const warning = container.querySelector('.time-range-warning');
                if (warning) {
                    warning.remove();
                }
            });
            
            // Reset to first schedule only
            const scheduleList = document.getElementById('scheduleList');
            if (scheduleList) {
                const scheduleCards = scheduleList.querySelectorAll('.schedule-card');
                
                // Remove all but the first schedule
                for (let i = 1; i < scheduleCards.length; i++) {
                    scheduleCards[i].remove();
                }
                
                // Update schedule numbers without messages
                if (window.TimePicker && window.TimePicker.updateScheduleNumbers) {
                    window.TimePicker.updateScheduleNumbers();
                }
            }
        }

        // EDITING METHODS (Same style as StudentManager)
        updateFormForEdit() {
            const form = document.getElementById('addClassForm');
            if (!form) return;
            
            const header = form.closest('.form-section').querySelector('.form-header h3');
            const subtitle = form.closest('.form-section').querySelector('.form-subtitle');
            const submitBtn = form.querySelector('button[type="submit"]');
            const btnText = submitBtn.querySelector('.btn-text');
            
            if (header) header.innerHTML = '<i class="fas fa-edit"></i> Edit Class';
            if (subtitle) subtitle.textContent = 'Update class information';
            if (btnText) btnText.innerHTML = '<i class="fas fa-save"></i> Update Class';
            
            // Add cancel button if it doesn't exist
            if (!form.querySelector('.btn-cancel-edit')) {
                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.className = 'btn btn-outline btn-cancel-edit';
                cancelBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
                cancelBtn.style.marginRight = '10px';
                cancelBtn.addEventListener('click', () => this.cancelEdit());
                
                submitBtn.parentNode.insertBefore(cancelBtn, submitBtn);
            }
        }

        updateFormForAdd() {
            const form = document.getElementById('addClassForm');
            if (!form) return;
            
            const header = form.closest('.form-section').querySelector('.form-header h3');
            const subtitle = form.closest('.form-section').querySelector('.form-subtitle');
            const submitBtn = form.querySelector('button[type="submit"]');
            const btnText = submitBtn.querySelector('.btn-text');
            const cancelBtn = form.querySelector('.btn-cancel-edit');
            
            if (header) header.innerHTML = '<i class="fas fa-plus-circle"></i> Create New Class';
            if (subtitle) subtitle.textContent = 'Configure class details and schedule';
            if (btnText) btnText.innerHTML = '<i class="fas fa-save"></i> Create Class';
            
            if (cancelBtn) {
                cancelBtn.remove();
            }
        }

        cancelEdit() {
            this.editMode = false;
            this.currentEditId = null;
            
            // Reset form
            document.getElementById('addClassForm').reset();
            
            // Update form UI back to add mode
            this.updateFormForAdd();
            
            // Reset time displays
            if (window.TimePicker && window.TimePicker.resetTimeDisplays) {
                window.TimePicker.resetTimeDisplays();
            }
            
            // Show message
            if (window.Toast) {
                Toast.fire({
                    icon: 'info',
                    title: 'Edit cancelled'
                });
            }
        }

        clearAllErrors() {
            const form = document.getElementById('addClassForm');
            if (!form) return;
            
            const invalidFields = form.querySelectorAll('.is-invalid');
            invalidFields.forEach(field => {
                field.classList.remove('is-invalid');
            });
        }
    }

    // GLOBAL FUNCTIONS
    
    // ENHANCED EDIT CLASS FUNCTION - Now supports multiple schedules
    function editClass(classId, section, academicYear, term, courseCode, day, time, room, facultyId) {
        console.log('editClass called with:', {classId, section, academicYear, term, courseCode, day, time, room, facultyId});
        
        if (window.classManager) {
            // First, load all schedules for this class group
            loadAllSchedulesForEdit(section, academicYear, term, courseCode, facultyId, room);
        }
    }

    // NEW: Load all schedules for a class group when editing
    function loadAllSchedulesForEdit(section, academicYear, term, courseCode, facultyId, room) {
        console.log('Loading all schedules for edit:', {section, academicYear, term, courseCode, facultyId, room});
        
        // Show loading
        if (window.Toast) {
            Toast.fire({
                icon: 'info',
                title: 'Loading class schedules for editing...',
                timer: 2000
            });
        }
        
        // Fetch all schedules for this class group
        fetch(`ajax/get_class_schedules.php?section=${encodeURIComponent(section)}&course_code=${encodeURIComponent(courseCode)}&academic_year=${encodeURIComponent(academicYear)}&term=${encodeURIComponent(term)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.length > 0) {
                populateFormWithAllSchedules(data.data, section, academicYear, term, courseCode, room, facultyId);
            } else {
                // Fallback to single schedule edit
                console.warn('Could not load all schedules, falling back to single schedule edit');
                populateFormWithSingleSchedule(section, academicYear, term, courseCode, '', '', room, facultyId);
            }
        })
        .catch(error => {
            console.error('Error loading schedules:', error);
            if (window.Toast) {
                Toast.fire({
                    icon: 'error',
                    title: 'Error loading schedules. Using basic edit mode.'
                });
            }
            // Fallback to single schedule edit
            populateFormWithSingleSchedule(section, academicYear, term, courseCode, '', '', room, facultyId);
        });
    }

    // NEW: Populate form with all schedules for comprehensive editing
    function populateFormWithAllSchedules(schedules, section, academicYear, term, courseCode, room, facultyId) {
        console.log('Populating form with all schedules:', schedules);
        
        const form = document.getElementById('addClassForm');
        if (!form) {
            console.error('Form not found');
            return;
        }
        
        // DON'T reset the form - we want to keep existing data
        // form.reset(); // ❌ Remove this line
        
        // Populate basic fields
        document.getElementById('section').value = section;
        document.getElementById('academic_year').value = academicYear;
        document.getElementById('term').value = term;
        document.getElementById('course_code').value = courseCode;
        document.getElementById('room').value = room;
        document.getElementById('faculty_id').value = facultyId;
        
        // Get the schedule list container
        const scheduleList = document.getElementById('scheduleList');
        if (!scheduleList) {
            console.error('Schedule list not found');
            return;
        }
        
        // Clear existing schedule cards CAREFULLY
        scheduleList.innerHTML = '';
        
        // Ensure we have at least the number of schedule cards we need
        for (let i = 0; i < schedules.length; i++) {
            const scheduleNumber = i + 1;
            
            // Create new schedule card
            let newScheduleHTML;
            if (window.TimePicker && window.TimePicker.createScheduleCard) {
                newScheduleHTML = window.TimePicker.createScheduleCard(scheduleNumber);
            } else {
                newScheduleHTML = createBasicScheduleCard(scheduleNumber);
            }
            
            // Add to DOM
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = newScheduleHTML;
            const newCard = tempDiv.firstElementChild;
            
            scheduleList.appendChild(newCard);
            
            // Initialize time picker for new card
            if (window.TimePicker && window.TimePicker.initializeTimePickerForCard) {
                window.TimePicker.initializeTimePickerForCard(newCard);
            }
            
            console.log(`Created schedule card ${scheduleNumber}`);
        }
        
        // Update schedule numbers
        if (window.TimePicker && window.TimePicker.updateScheduleNumbers) {
            window.TimePicker.updateScheduleNumbers();
        }
        
        // Now populate each schedule card with data
        schedules.forEach((schedule, index) => {
            const scheduleNumber = index + 1;
            
            // Small delay to ensure DOM is ready
            setTimeout(() => {
                populateScheduleCard(scheduleNumber, schedule);
            }, (index + 1) * 100); // Staggered delays
        });
        
        // Set edit mode
        window.classManager.editMode = true;
        window.classManager.currentEditId = schedules[0].class_id; // Use first schedule's ID as primary
        window.classManager.allScheduleIds = schedules.map(s => s.class_id); // Store all IDs for updating
        
        // Update form UI
        window.classManager.updateFormForEdit();
        
        // Clear any existing validation states
        window.classManager.clearAllErrors();
        
        // Scroll to form
        form.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
        
        // Focus on first field after a delay
        setTimeout(() => {
            document.getElementById('section').focus();
        }, 500);
        
        // Show success message after all schedules are loaded
        setTimeout(() => {
            if (window.Toast) {
                Toast.fire({
                    icon: 'success',
                    title: `Loaded ${schedules.length} schedule(s) for editing`,
                    timer: 3000
                });
            }
        }, schedules.length * 100 + 500);
    }

    // NEW: Populate individual schedule card with schedule ID (ENHANCED)
    function populateScheduleCard(scheduleNumber, schedule) {
        console.log(`🔄 Populating schedule ${scheduleNumber} with:`, schedule);
        
        // More robust element selection
        const daySelect = document.querySelector(`#day_${scheduleNumber}, [id="day_${scheduleNumber}"]`);
        if (daySelect) {
            daySelect.value = schedule.day;
            console.log(`✅ Set day for schedule ${scheduleNumber}: ${schedule.day}`);
        } else {
            console.warn(`❌ Day select not found for schedule ${scheduleNumber}`);
        }
        
        // Store schedule ID in a hidden input for updates
        let scheduleIdInput = document.getElementById(`schedule_id_${scheduleNumber}`);
        if (!scheduleIdInput) {
            // Create hidden input for schedule ID
            scheduleIdInput = document.createElement('input');
            scheduleIdInput.type = 'hidden';
            scheduleIdInput.id = `schedule_id_${scheduleNumber}`;
            scheduleIdInput.name = 'schedule_id[]';
            
            // Add it to the schedule card - more robust selection
            const scheduleCard = document.querySelector(`.schedule-card:nth-child(${scheduleNumber})`) ||
                                document.querySelector(`.schedule-card[data-schedule="${scheduleNumber}"]`) ||
                                document.querySelectorAll('.schedule-card')[scheduleNumber - 1];
            
            if (scheduleCard) {
                scheduleCard.appendChild(scheduleIdInput);
                console.log(`✅ Created schedule ID input for ${scheduleNumber}`);
            } else {
                console.warn(`❌ Schedule card not found for ${scheduleNumber}`);
            }
        }
        
        if (scheduleIdInput) {
            scheduleIdInput.value = schedule.class_id;
            console.log(`✅ Set schedule ID for ${scheduleNumber}: ${schedule.class_id}`);
        }
        
        // Parse and set time with better error handling
        try {
            const timeParts = schedule.time.split(' - ');
            if (timeParts.length === 2) {
                const startTime = parseTime(timeParts[0]);
                const endTime = parseTime(timeParts[1]);
                
                console.log(`🕒 Parsed times for schedule ${scheduleNumber}:`, { startTime, endTime });
                
                // Set start time with multiple selection strategies
                const startHourInput = document.querySelector(`#start_hour_${scheduleNumber}, [id="start_hour_${scheduleNumber}"]`);
                const startMinuteInput = document.querySelector(`#start_minute_${scheduleNumber}, [id="start_minute_${scheduleNumber}"]`);
                
                if (startHourInput && startMinuteInput) {
                    startHourInput.value = startTime.hour;
                    startMinuteInput.value = startTime.minute;
                    setAmPm('start', scheduleNumber, startTime.ampm);
                    console.log(`✅ Set start time for schedule ${scheduleNumber}: ${startTime.hour}:${startTime.minute} ${startTime.ampm}`);
                } else {
                    console.warn(`❌ Start time inputs not found for schedule ${scheduleNumber}`);
                }
                
                // Set end time
                const endHourInput = document.querySelector(`#end_hour_${scheduleNumber}, [id="end_hour_${scheduleNumber}"]`);
                const endMinuteInput = document.querySelector(`#end_minute_${scheduleNumber}, [id="end_minute_${scheduleNumber}"]`);
                
                if (endHourInput && endMinuteInput) {
                    endHourInput.value = endTime.hour;
                    endMinuteInput.value = endTime.minute;
                    setAmPm('end', scheduleNumber, endTime.ampm);
                    console.log(`✅ Set end time for schedule ${scheduleNumber}: ${endTime.hour}:${endTime.minute} ${endTime.ampm}`);
                } else {
                    console.warn(`❌ End time inputs not found for schedule ${scheduleNumber}`);
                }
                
                // Update time display with longer delay to ensure DOM is ready
                setTimeout(() => {
                    try {
                        if (window.TimePicker && window.TimePicker.updateTimeDisplay) {
                            const timeContainer = document.querySelector(`#time_value_${scheduleNumber}`)?.closest('.time-range-container');
                            if (timeContainer) {
                                window.TimePicker.updateTimeDisplay(timeContainer);
                                console.log(`✅ Updated time display for schedule ${scheduleNumber}`);
                            } else {
                                console.warn(`❌ Time container not found for schedule ${scheduleNumber}`);
                            }
                        }
                    } catch (error) {
                        console.error(`Error updating time display for schedule ${scheduleNumber}:`, error);
                    }
                }, 200);
                
            } else {
                console.warn(`❌ Invalid time format for schedule ${scheduleNumber}: ${schedule.time}`);
            }
        } catch (error) {
            console.error(`Error parsing time for schedule ${scheduleNumber}:`, error);
        }
        
        console.log(`🎯 Finished populating schedule ${scheduleNumber}`);
    }

    // NEW: Add schedule card manually (fallback)
    function addScheduleCard(scheduleNumber) {
        const scheduleList = document.getElementById('scheduleList');
        if (!scheduleList) return;
        
        const newScheduleHTML = window.TimePicker ? 
            window.TimePicker.createScheduleCard(scheduleNumber) : 
            createBasicScheduleCard(scheduleNumber);
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newScheduleHTML;
        const newCard = tempDiv.firstElementChild;
        
        scheduleList.appendChild(newCard);
        
        // Initialize time picker for new card
        if (window.TimePicker && window.TimePicker.initializeTimePickerForCard) {
            window.TimePicker.initializeTimePickerForCard(newCard);
        }
    }

    // NEW: Fallback to single schedule edit
    function populateFormWithSingleSchedule(section, academicYear, term, courseCode, day, time, room, facultyId) {
        console.log('Populating form with single schedule (fallback)');
        
        // Populate basic fields
        document.getElementById('section').value = section;
        document.getElementById('academic_year').value = academicYear;
        document.getElementById('term').value = term;
        document.getElementById('course_code').value = courseCode;
        document.getElementById('room').value = room;
        document.getElementById('faculty_id').value = facultyId;
        
        // Set basic schedule if provided
        if (day && time) {
            document.getElementById('day_1').value = day;
            
            const timeParts = time.split(' - ');
            if (timeParts.length === 2) {
                const startTime = parseTime(timeParts[0]);
                const endTime = parseTime(timeParts[1]);
                
                document.getElementById('start_hour_1').value = startTime.hour;
                document.getElementById('start_minute_1').value = startTime.minute;
                setAmPm('start', 1, startTime.ampm);
                
                document.getElementById('end_hour_1').value = endTime.hour;
                document.getElementById('end_minute_1').value = endTime.minute;
                setAmPm('end', 1, endTime.ampm);
                
                // Update time display
                setTimeout(() => {
                    if (window.TimePicker && window.TimePicker.updateTimeDisplay) {
                        const timeContainer = document.querySelector('#time_value_1')?.closest('.time-range-container');
                        if (timeContainer) {
                            window.TimePicker.updateTimeDisplay(timeContainer);
                        }
                    }
                }, 100);
            }
        }
        
        // Set edit mode
        window.classManager.editMode = true;
        window.classManager.currentEditId = null; // No specific ID for fallback
        
        // Update form UI
        window.classManager.updateFormForEdit();
        
        // Clear any existing validation states
        window.classManager.clearAllErrors();
        
        // Scroll to form
        document.getElementById('addClassForm').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
        
        // Focus on first field
        document.getElementById('section').focus();
        
        // Show info message
        if (window.Toast) {
            Toast.fire({
                icon: 'info',
                title: 'Basic edit mode - some features may be limited'
            });
        }
    }
    function createBasicScheduleCard(scheduleNumber) {
        return `
            <div class="schedule-card">
                <div class="schedule-card-header">
                    <span class="schedule-number">${scheduleNumber}</span>
                    <button type="button" class="btn-remove-schedule" onclick="removeSchedule(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="schedule-card-body">
                    <div class="schedule-row">
                        <div class="form-group">
                            <label for="day_${scheduleNumber}" class="form-label">Day</label>
                            <select id="day_${scheduleNumber}" name="day[]" class="form-control" required>    
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Time Range</label>
                            <div class="time-range-container">
                                <input type="hidden" id="time_value_${scheduleNumber}" name="time[]" class="time-value">
                                <div class="formatted-time">Not set</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Helper functions for editClass
    function parseTime(timeStr) {
        const match = timeStr.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
        if (match) {
            return {
                hour: match[1].padStart(2, '0'),
                minute: match[2],
                ampm: match[3].toUpperCase()
            };
        }
        return { hour: '08', minute: '00', ampm: 'AM' };
    }

    function setAmPm(type, scheduleNum, ampm) {
        const container = document.querySelector(`#${type}_hour_${scheduleNum}`).closest('.time-input-group');
        if (container) {
            const ampmButtons = container.querySelectorAll('.ampm-option');
            ampmButtons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent === ampm) {
                    btn.classList.add('active');
                }
            });
        }
    }

    // Global delete function
    function confirmDeleteClassGroup(section, academic_year, term, course_code, faculty_id, room) {
        Swal.fire({
            title: 'Are you sure?',
            text: `Delete all schedules for ${section} - ${course_code}? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Deleting...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Make delete request
                const formData = new FormData();
                formData.append('action', 'delete_group');
                formData.append('section', section);
                formData.append('academic_year', academic_year);
                formData.append('term', term);
                formData.append('course_code', course_code);
                formData.append('faculty_id', faculty_id);
                
                fetch('ajax/process_class.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close the loading dialog first
                        Swal.close();
                        
                        // Show success message
                        if (window.Toast) {
                            Toast.fire({ 
                                icon: 'success', 
                                title: 'Class deleted successfully!' 
                            });
                        } else {
                            alert('Class deleted successfully!');
                        }
                        
                        // Remove the row from table
                        removeClassGroupRow(section, academic_year, term, course_code, faculty_id);
                    } else {
                        // Show error message
                        if (window.Toast) {
                            Toast.fire({
                                icon: 'error',
                                title: 'Error: ' + (data.message || 'An error occurred while deleting.')
                            });
                        } else {
                            alert('Error: ' + (data.message || 'An error occurred while deleting.'));
                        }
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    
                    if (window.Toast) {
                        Toast.fire({
                            icon: 'error',
                            title: 'Error: An unexpected error occurred.'
                        });
                    } else {
                        alert('Error: An unexpected error occurred.');
                    }
                });
            }
        });
    }

    // Complete removeClassGroupRow function
    function removeClassGroupRow(section, academic_year, term, course_code, faculty_id) {
        console.log('Removing row for class group:', section, course_code);
        
        const classTable = document.querySelector('#classes .table tbody');
        
        if (!classTable) {
            console.log('Classes table not found');
            setTimeout(() => window.location.reload(), 1000);
            return;
        }
        
        // Find and remove the row
        const rows = classTable.querySelectorAll('tr');
        let rowFound = false;
        
        for (let row of rows) {
            const sectionCell = row.querySelector('td:nth-child(2) .badge');
            const courseCell = row.querySelector('td:nth-child(3) strong');
            const termYearCell = row.querySelector('td:nth-child(7)');
            
            if (sectionCell && courseCell && termYearCell) {
                const rowSection = sectionCell.textContent.trim();
                const rowCourse = courseCell.textContent.trim();
                const rowAcademicYear = termYearCell.querySelector('small')?.textContent.trim();
                const rowTerm = termYearCell.querySelector('.badge')?.textContent.trim();
                
                if (rowSection === section && 
                    rowCourse === course_code && 
                    rowAcademicYear === academic_year && 
                    rowTerm === term) {
                    
                    console.log('Found matching row, removing...');
                    rowFound = true;
                    
                    // Animate row removal
                    row.style.transition = 'opacity 0.3s ease';
                    row.style.opacity = '0';
                    
                    setTimeout(() => {
                        row.remove();
                        console.log('Row removed successfully');
                        
                        // Check if table is now empty
                        const remainingRows = classTable.querySelectorAll('tr');
                        if (remainingRows.length === 0) {
                            // Add "no classes" message
                            const noDataRow = document.createElement('tr');
                            noDataRow.innerHTML = `
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i><br>
                                    No classes found
                                </td>
                            `;
                            classTable.appendChild(noDataRow);
                        }
                    }, 300);
                    
                    break;
                }
            }
        }
        
        if (!rowFound) {
            console.log('No matching row found for deletion');
            // Reload page if we can't find the row
            setTimeout(() => window.location.reload(), 1000);
        }
    }

    // DEBUG: Function to check form state after loading
    function debugFormState() {
        console.log('=== FORM STATE DEBUG ===');
        
        const form = document.getElementById('addClassForm');
        if (!form) {
            console.log('❌ Form not found');
            return;
        }
        
        // Check basic fields
        const basicFields = ['section', 'academic_year', 'term', 'course_code', 'room', 'faculty_id'];
        basicFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            console.log(`${fieldId}: ${field ? field.value : 'NOT FOUND'}`);
        });
        
        // Check schedule cards
        const scheduleCards = document.querySelectorAll('.schedule-card');
        console.log(`Found ${scheduleCards.length} schedule cards`);
        
        scheduleCards.forEach((card, index) => {
            const scheduleNum = index + 1;
            const daySelect = card.querySelector(`select[name="day[]"]`);
            const timeDisplay = card.querySelector('.formatted-time');
            const scheduleIdInput = card.querySelector(`input[name="schedule_id[]"]`);
            
            console.log(`Schedule ${scheduleNum}:`, {
                day: daySelect ? daySelect.value : 'NO SELECT',
                timeDisplay: timeDisplay ? timeDisplay.textContent : 'NO DISPLAY',
                scheduleId: scheduleIdInput ? scheduleIdInput.value : 'NO ID'
            });
        });
        
        console.log('=== END DEBUG ===');
    }

    // Make debug function available globally
    window.debugFormState = debugFormState;
    window.editClass = editClass;
    window.confirmDeleteClassGroup = confirmDeleteClassGroup;
    window.removeClassGroupRow = removeClassGroupRow;

    // Initialize ClassManager when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.classManager = new ClassManager();
        });
    } else {
        // DOM is already ready
        window.classManager = new ClassManager();
    }

    console.log('Classes.js loaded successfully');

} catch (error) {
    console.error('Error in classes.js:', error);
    
    // Show user-friendly error message
    if (window.Toast) {
        Toast.fire({
            icon: 'error',
            title: 'Failed to load class management system'
        });
    } else {
        alert('Failed to load class management system. Please refresh the page.');
    }
}
