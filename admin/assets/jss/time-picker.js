// Enhanced TimePicker.js - Professional Schedule Management
// ========================================================

let scheduleCounter = 1;

// Initialize TimePicker for a specific card
function initializeTimePickerForCard(card) {
    console.log('Initializing TimePicker for card:', card);
    
    if (!card) {
        console.warn('⚠️ TimePicker: No card provided for initialization');
        return;
    }
    
    const timeInputs = card.querySelectorAll('.time-input');
    const ampmButtons = card.querySelectorAll('.ampm-option');
    
    // Enhanced time input handling
    timeInputs.forEach(input => {
        // Input event for real-time validation
        input.addEventListener('input', function(e) {
            handleTimeInput(this);
            validateTimeRange(this.closest('.time-range-container'));
        });
        
        // Enhanced keypress validation
        input.addEventListener('keypress', function(e) {
            // Allow numbers, backspace, delete, tab, enter, arrow keys
            if (!/[0-9]/.test(e.key) && 
                !['Backspace', 'Delete', 'Tab', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                e.preventDefault();
            }
        });
        
        // Focus handling for better UX
        input.addEventListener('focus', function() {
            this.select(); // Select all text on focus
            this.closest('.time-input-group').style.borderColor = 'var(--primary-500)';
        });
        
        input.addEventListener('blur', function() {
            this.closest('.time-input-group').style.borderColor = '';
            formatTimeInput(this); // Auto-format on blur
            validateTimeRange(this.closest('.time-range-container'));
        });
        
        // Paste handling
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const numbersOnly = pastedText.replace(/\D/g, '');
            if (numbersOnly) {
                this.value = numbersOnly.substring(0, 2);
                handleTimeInput(this);
            }
        });
    });
    
    // Enhanced AM/PM button handling
    ampmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            handleAmPmToggle(this);
        });
        
        // Keyboard support for AM/PM buttons
        button.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleAmPmToggle(this);
            }
        });
        
        // Focus styling
        button.addEventListener('focus', function() {
            this.style.outline = '2px solid var(--primary-500)';
        });
        
        button.addEventListener('blur', function() {
            this.style.outline = '';
        });
    });
    
    // Initialize time display
    updateTimeDisplay(card.querySelector('.time-range-container'));
    
    console.log('✅ TimePicker initialized for card');
}

// Enhanced time input handling with better validation
function handleTimeInput(input) {
    const type = input.dataset.type;
    let value = input.value.replace(/\D/g, ''); // Remove non-digits
    
    if (type === 'hour') {
        // Hour validation (1-12 for 12-hour format)
        if (value === '') {
            input.value = '';
        } else if (parseInt(value) > 12) {
            value = '12';
            input.value = value;
            showInputFeedback(input, 'Hours must be between 1-12', 'warning');
        } else if (parseInt(value) < 1 && value !== '') {
            value = '1';
            input.value = value;
            showInputFeedback(input, 'Hours must be between 1-12', 'warning');
        } else {
            input.value = value;
        }
    } else if (type === 'minute') {
        // Minute validation (0-59)
        if (value === '') {
            input.value = '';
        } else if (parseInt(value) > 59) {
            value = '59';
            input.value = value;
            showInputFeedback(input, 'Minutes must be between 0-59', 'warning');
        } else {
            input.value = value;
        }
    }
    
    // Update time display
    updateTimeDisplay(input.closest('.time-range-container'));
    
    // Auto-advance to next field for better UX
    if (value.length === 2) {
        autoAdvanceToNextField(input);
    }
}

// Auto-format time input on blur
function formatTimeInput(input) {
    const value = input.value;
    if (value && value.length === 1) {
        input.value = '0' + value; // Add leading zero
        updateTimeDisplay(input.closest('.time-range-container'));
    }
}

// Auto-advance to next field for smooth UX
function autoAdvanceToNextField(input) {
    const container = input.closest('.time-input-group');
    const nextInput = container.querySelector('.time-input:not(:focus)');
    
    if (nextInput && nextInput !== input) {
        // Small delay for better UX
        setTimeout(() => {
            nextInput.focus();
            nextInput.select();
        }, 100);
    }
}

// Show temporary feedback for input validation
function showInputFeedback(input, message, type = 'info') {
    // Remove existing feedback
    const existingFeedback = input.parentNode.querySelector('.input-feedback');
    if (existingFeedback) {
        existingFeedback.remove();
    }
    
    // Create feedback element
    const feedback = document.createElement('div');
    feedback.className = `input-feedback input-feedback-${type}`;
    feedback.style.cssText = `
        position: absolute;
        top: -30px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--${type === 'warning' ? 'warning' : 'info'}-600);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
    `;
    feedback.textContent = message;
    
    // Position relative for feedback
    input.parentNode.style.position = 'relative';
    input.parentNode.appendChild(feedback);
    
    // Animate in
    requestAnimationFrame(() => {
        feedback.style.opacity = '1';
    });
    
    // Remove after delay
    setTimeout(() => {
        feedback.style.opacity = '0';
        setTimeout(() => {
            if (feedback.parentNode) {
                feedback.parentNode.removeChild(feedback);
            }
        }, 300);
    }, 2000);
}

// Enhanced AM/PM toggle with accessibility
function handleAmPmToggle(button) {
    const container = button.closest('.ampm-selector');
    const allButtons = container.querySelectorAll('.ampm-option');
    
    // Remove active class from all buttons
    allButtons.forEach(btn => {
        btn.classList.remove('active');
        btn.setAttribute('aria-pressed', 'false');
    });
    
    // Add active class to clicked button
    button.classList.add('active');
    button.setAttribute('aria-pressed', 'true');
    
    // Update time display
    updateTimeDisplay(button.closest('.time-range-container'));
    
    // Provide audio feedback for accessibility
    if (window.speechSynthesis) {
        const utterance = new SpeechSynthesisUtterance(button.dataset.ampm);
        utterance.volume = 0.1;
        utterance.rate = 2;
        // Uncomment if you want audio feedback
        // window.speechSynthesis.speak(utterance);
    }
}

// Enhanced time display with better debugging for multiple schedules
function updateTimeDisplay(container) {
    if (!container) {
        console.warn('⚠️ TimePicker: No container provided for time display update');
        return;
    }
    
    // Find which schedule card this belongs to
    const scheduleCard = container.closest('.schedule-card');
    const scheduleNumber = scheduleCard ? scheduleCard.querySelector('.schedule-number')?.textContent : 'unknown';
    
    console.log(`🕒 Updating time display for schedule ${scheduleNumber}`);
    
    const timeGroups = container.querySelectorAll('.time-input-group');
    if (timeGroups.length < 2) {
        console.warn(`⚠️ Schedule ${scheduleNumber}: Expected 2 time input groups, found`, timeGroups.length);
        return;
    }
    
    const startTimeGroup = timeGroups[0];
    const endTimeGroup = timeGroups[1];
    
    // Get start time values
    const startHour = startTimeGroup.querySelector('.hour-input')?.value || '';
    const startMinute = startTimeGroup.querySelector('.minute-input')?.value || '';
    const startAmPm = startTimeGroup.querySelector('.ampm-option.active')?.dataset.ampm || 'AM';
    
    // Get end time values
    const endHour = endTimeGroup.querySelector('.hour-input')?.value || '';
    const endMinute = endTimeGroup.querySelector('.minute-input')?.value || '';
    const endAmPm = endTimeGroup.querySelector('.ampm-option.active')?.dataset.ampm || 'AM';
    
    console.log(`📊 Schedule ${scheduleNumber} time data:`, {
        startHour, startMinute, startAmPm,
        endHour, endMinute, endAmPm
    });
    
    let formattedTime = 'Not set';
    let isValidTime = false;
    
    // Check if all time components are provided
    if (startHour && startMinute && endHour && endMinute) {
        const startTime = `${startHour.padStart(2, '0')}:${startMinute.padStart(2, '0')} ${startAmPm}`;
        const endTime = `${endHour.padStart(2, '0')}:${endMinute.padStart(2, '0')} ${endAmPm}`;
        formattedTime = `${startTime} - ${endTime}`;
        isValidTime = true;
        
        console.log(`✅ Schedule ${scheduleNumber} formatted time: ${formattedTime}`);
        
        // Validate time logic (end time should be after start time)
        const startDate = parseTimeToDate(startHour, startMinute, startAmPm);
        const endDate = parseTimeToDate(endHour, endMinute, endAmPm);
        
        if (endDate <= startDate) {
            formattedTime += ' ⚠️';
            isValidTime = false;
            console.warn(`⚠️ Schedule ${scheduleNumber}: End time must be after start time`);
            showTimeRangeWarning(container, 'End time must be after start time');
        } else {
            clearTimeRangeWarning(container);
        }
    } else {
        console.log(`❌ Schedule ${scheduleNumber}: Missing time components`, {
            startHour: !!startHour,
            startMinute: !!startMinute,
            endHour: !!endHour,
            endMinute: !!endMinute
        });
    }
    
    // Update display elements
    const timeDisplay = container.querySelector('.formatted-time');
    const hiddenInput = container.querySelector('.time-value');
    
    if (timeDisplay) {
        timeDisplay.textContent = formattedTime;
        timeDisplay.className = `formatted-time ${isValidTime ? 'time-valid' : 'time-invalid'}`;
        console.log(`📺 Schedule ${scheduleNumber} display updated: ${formattedTime}`);
    }
    
    if (hiddenInput) {
        const hiddenValue = isValidTime ? formattedTime.replace(' ⚠️', '') : '';
        hiddenInput.value = hiddenValue;
        console.log(`🔒 Schedule ${scheduleNumber} hidden input set: "${hiddenValue}"`);
        console.log(`🔒 Hidden input details:`, {
            id: hiddenInput.id,
            name: hiddenInput.name,
            value: hiddenInput.value
        });
    } else {
        console.error(`❌ Schedule ${scheduleNumber}: Hidden input not found!`);
    }
    
    // Update container styling based on validity
    container.classList.toggle('time-range-valid', isValidTime && formattedTime !== 'Not set');
    container.classList.toggle('time-range-invalid', !isValidTime && formattedTime !== 'Not set');
    
    console.log(`🎯 Schedule ${scheduleNumber} final status:`, {
        isValid: isValidTime,
        formattedTime: formattedTime,
        hiddenInputValue: hiddenInput?.value || 'NOT FOUND'
    });
}


// Helper function to parse time to Date object for comparison
function parseTimeToDate(hour, minute, ampm) {
    let hour24 = parseInt(hour);
    if (ampm === 'PM' && hour24 !== 12) hour24 += 12;
    if (ampm === 'AM' && hour24 === 12) hour24 = 0;
    
    const date = new Date();
    date.setHours(hour24, parseInt(minute), 0, 0);
    return date;
}

// Show time range warning
function showTimeRangeWarning(container, message) {
    clearTimeRangeWarning(container); // Clear existing warning
    
    const warning = document.createElement('div');
    warning.className = 'time-range-warning';
    warning.style.cssText = `
        color: var(--warning-600);
        font-size: 12px;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    `;
    warning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
    
    const timeDisplay = container.querySelector('.time-display');
    if (timeDisplay) {
        timeDisplay.appendChild(warning);
    }
}

// Clear time range warning
function clearTimeRangeWarning(container) {
    const warning = container.querySelector('.time-range-warning');
    if (warning) {
        warning.remove();
    }
}

// Validate individual time range
function validateTimeRange(container) {
    if (!container) return true;
    
    const timeGroups = container.querySelectorAll('.time-input-group');
    if (timeGroups.length < 2) return false;
    
    const startInputs = timeGroups[0].querySelectorAll('.time-input');
    const endInputs = timeGroups[1].querySelectorAll('.time-input');
    
    // Check if all inputs have values
    const allFilled = [...startInputs, ...endInputs].every(input => input.value.trim() !== '');
    
    if (allFilled) {
        updateTimeDisplay(container);
        return container.classList.contains('time-range-valid');
    }
    
    return false;
}

// Enhanced schedule management with better UX
function updateScheduleNumbers() {
    const scheduleCards = document.querySelectorAll('.schedule-card');
    
    scheduleCards.forEach((card, index) => {
        const numberSpan = card.querySelector('.schedule-number');
        if (numberSpan) {
            numberSpan.textContent = index + 1;
            numberSpan.setAttribute('aria-label', `Schedule ${index + 1}`);
        }
        
        const removeBtn = card.querySelector('.btn-remove-schedule');
        if (removeBtn) {
            const shouldShow = scheduleCards.length > 1;
            removeBtn.style.display = shouldShow ? 'flex' : 'none';
            removeBtn.disabled = !shouldShow;
            removeBtn.setAttribute('aria-label', `Remove schedule ${index + 1}`);
            
            if (!shouldShow) {
                removeBtn.setAttribute('title', 'Cannot remove the last schedule');
            } else {
                removeBtn.setAttribute('title', `Remove schedule ${index + 1}`);
            }
        }
    });
    
    // Update global counter
    scheduleCounter = scheduleCards.length;
    
    console.log(`📊 Updated ${scheduleCards.length} schedule cards`);
}

// Enhanced schedule removal with confirmation and animation
function removeSchedule(button) {
    const scheduleCard = button.closest('.schedule-card');
    const scheduleCards = document.querySelectorAll('.schedule-card');
    
    if (scheduleCards.length <= 1) {
        if (window.Toast) {
            Toast.fire({
                icon: 'warning',
                title: 'At least one schedule is required'
            });
        } else {
            alert('At least one schedule is required');
        }
        return;
    }
    
    // Get schedule number for confirmation
    const scheduleNumber = scheduleCard.querySelector('.schedule-number')?.textContent || 'this';
    
    // Optional: Show confirmation for better UX
    const shouldConfirm = scheduleCards.length > 2; // Only confirm if more than 2 schedules
    
    if (shouldConfirm) {
        if (!confirm(`Remove schedule ${scheduleNumber}?`)) {
            return;
        }
    }
    
    // Animate removal
    scheduleCard.style.transition = 'all 0.3s ease-out';
    scheduleCard.style.opacity = '0';
    scheduleCard.style.transform = 'translateX(-20px) scale(0.95)';
    scheduleCard.style.maxHeight = scheduleCard.offsetHeight + 'px';
    
    setTimeout(() => {
        scheduleCard.style.maxHeight = '0';
        scheduleCard.style.marginBottom = '0';
        scheduleCard.style.paddingTop = '0';
        scheduleCard.style.paddingBottom = '0';
    }, 150);
    
    setTimeout(() => {
        scheduleCard.remove();
        updateScheduleNumbers();
        
        // Show success feedback
        if (window.Toast) {
            Toast.fire({
                icon: 'success',
                title: `Schedule ${scheduleNumber} removed`,
                timer: 2000
            });
        }
        
        console.log(`🗑️ Removed schedule ${scheduleNumber}`);
    }, 300);
}

// Add new schedule with smooth animation
function addSchedule() {
    scheduleCounter++;
    const scheduleList = document.getElementById('scheduleList');
    
    if (!scheduleList) {
        console.error('❌ Schedule list container not found');
        if (window.Toast) {
            Toast.fire({
                icon: 'error',
                title: 'Cannot add schedule: container not found'
            });
        }
        return;
    }
    
    const newScheduleHTML = createScheduleCard(scheduleCounter);
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = newScheduleHTML;
    const newCard = tempDiv.firstElementChild;
    
    // Prepare for animation
    newCard.style.opacity = '0';
    newCard.style.transform = 'translateY(-20px) scale(0.95)';
    newCard.style.maxHeight = '0';
    newCard.style.overflow = 'hidden';
    
    scheduleList.appendChild(newCard);
    
    // Initialize time picker for new card
    initializeTimePickerForCard(newCard);
    
    // Animate in
    requestAnimationFrame(() => {
        newCard.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        newCard.style.opacity = '1';
        newCard.style.transform = 'translateY(0) scale(1)';
        newCard.style.maxHeight = '1000px'; // Large enough value
        
        setTimeout(() => {
            newCard.style.maxHeight = '';
            newCard.style.overflow = '';
        }, 400);
    });
    
    updateScheduleNumbers();
    
    // Show success feedback
    if (window.Toast) {
        Toast.fire({
            icon: 'success',
            title: `Schedule ${scheduleCounter} added`,
            timer: 2000
        });
    }
    
    // Focus on the day select of the new schedule
    setTimeout(() => {
        const daySelect = newCard.querySelector('select[name="day[]"]');
        if (daySelect) {
            daySelect.focus();
        }
    }, 500);
    
    console.log(`➕ Added schedule ${scheduleCounter}`);
}

// Enhanced schedule card creation with better accessibility
function createScheduleCard(scheduleCounter) {
    return `
        <div class="schedule-card" role="group" aria-labelledby="schedule-${scheduleCounter}-title">
            <div class="schedule-card-header">
                <span class="schedule-number" id="schedule-${scheduleCounter}-title">${scheduleCounter}</span>
                <button type="button" 
                        class="btn-remove-schedule" 
                        onclick="removeSchedule(this)"
                        aria-label="Remove schedule ${scheduleCounter}"
                        title="Remove this schedule">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="schedule-card-body">
                <div class="schedule-row">
                    <div class="form-group">
                        <label for="day_${scheduleCounter}" class="form-label">
                            <i class="fas fa-calendar-day" aria-hidden="true"></i> Day
                        </label>
                        <select id="day_${scheduleCounter}" 
                                name="day[]" 
                                class="form-control" 
                                required
                                aria-describedby="day_${scheduleCounter}_help">    
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                        <small id="day_${scheduleCounter}_help" class="form-text text-muted">
                            Select the day of the week for this class
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-clock" aria-hidden="true"></i> Time Range
                        </label>
                        <div class="time-range-container">
                            <div>
                                <div class="time-label">From</div>
                                <div class="time-input-group">
                                    <input type="text" 
                                           id="start_hour_${scheduleCounter}" 
                                           class="time-input hour-input" 
                                           placeholder="08" 
                                           maxlength="2" 
                                           data-type="hour" 
                                           aria-label="Start hour"
                                           autocomplete="off">
                                    <span class="time-colon">:</span>
                                    <input type="text" 
                                           id="start_minute_${scheduleCounter}" 
                                           class="time-input minute-input" 
                                           placeholder="00" 
                                           maxlength="2" 
                                           data-type="minute" 
                                           aria-label="Start minute"
                                           autocomplete="off">
                                    <div class="ampm-selector" role="group" aria-label="Start time AM/PM">
                                        <button type="button" 
                                                class="ampm-option active" 
                                                data-ampm="AM"
                                                aria-pressed="true"
                                                aria-label="AM">AM</button>
                                        <button type="button" 
                                                class="ampm-option" 
                                                data-ampm="PM"
                                                aria-pressed="false"
                                                aria-label="PM">PM</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="time-separator">
                                <i class="fas fa-arrow-down" aria-hidden="true"></i>
                            </div>
                            
                            <div>
                                <div class="time-label">To</div>
                                <div class="time-input-group">
                                    <input type="text" 
                                           id="end_hour_${scheduleCounter}" 
                                           class="time-input hour-input" 
                                           placeholder="09" 
                                           maxlength="2" 
                                           data-type="hour" 
                                           aria-label="End hour"
                                           autocomplete="off">
                                    <span class="time-colon">:</span>
                                    <input type="text" 
                                           id="end_minute_${scheduleCounter}" 
                                           class="time-input minute-input" 
                                           placeholder="30" 
                                           maxlength="2" 
                                           data-type="minute" 
                                           aria-label="End minute"
                                           autocomplete="off">
                                    <div class="ampm-selector" role="group" aria-label="End time AM/PM">
                                        <button type="button" 
                                                class="ampm-option active" 
                                                data-ampm="AM"
                                                aria-pressed="true"
                                                aria-label="AM">AM</button>
                                        <button type="button" 
                                                class="ampm-option" 
                                                data-ampm="PM"
                                                aria-pressed="false"
                                                aria-label="PM">PM</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="time-display" role="status" aria-live="polite">
                                <strong>Time Range:</strong> <span class="formatted-time">Not set</span>
                            </div>
                            
                            <input type="hidden" 
                                   id="time_value_${scheduleCounter}" 
                                   name="time[]" 
                                   class="time-value">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Enhanced schedule validation with detailed feedback
function validateScheduleTimes(form) {
    if (!form) {
        console.error('❌ No form provided for schedule validation');
        return { valid: false, message: 'Form not found' };
    }
    
    const timeContainers = form.querySelectorAll('.time-range-container');
    let allTimesValid = true;
    let invalidTimeMessage = '';
    const invalidSchedules = [];
    
    timeContainers.forEach((container, index) => {
        const hiddenInput = container.querySelector('.time-value');
        const timeDisplay = container.querySelector('.formatted-time');
        const isValidContainer = container.classList.contains('time-range-valid');
        
        if (!hiddenInput || !hiddenInput.value || 
            hiddenInput.value === 'Not set' || 
            hiddenInput.value.includes('00:00') ||
            timeDisplay.textContent === 'Not set' ||
            !isValidContainer) {
            
            allTimesValid = false;
            invalidSchedules.push(index + 1);
            
            // Add visual indicator
            container.classList.add('validation-error');
            setTimeout(() => {
                container.classList.remove('validation-error');
            }, 3000);
        }
    });
    
    if (!allTimesValid) {
        if (invalidSchedules.length === 1) {
            invalidTimeMessage = `Please set a valid time range for schedule ${invalidSchedules[0]}`;
        } else {
            invalidTimeMessage = `Please set valid time ranges for schedules ${invalidSchedules.join(', ')}`;
        }
    }
    
    console.log('📋 Schedule validation:', { valid: allTimesValid, invalidSchedules });
    
    return { 
        valid: allTimesValid, 
        message: invalidTimeMessage,
        invalidSchedules: invalidSchedules
    };
}

// Enhanced reset with smooth animations
function resetTimeDisplays(form) {
    if (!form) {
        console.warn('⚠️ No form provided for reset');
        return;
    }
    
    console.log('🔄 Resetting time displays...');
    
    // Reset all time-related elements
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
        clearTimeRangeWarning(container);
    });
    
    // Reset schedule counter
    scheduleCounter = 1;
    updateScheduleNumbers();
    
    console.log('✅ Time displays reset successfully');
}

// Initialize TimePicker on page load
function initializeTimePicker() {
    console.log('🚀 Initializing TimePicker system...');
    
    // Initialize existing schedule cards
    const existingCards = document.querySelectorAll('.schedule-card');
    console.log(`📋 Found ${existingCards.length} existing schedule cards`);
    
    existingCards.forEach((card, index) => {
        initializeTimePickerForCard(card);
        console.log(`✅ Initialized schedule card ${index + 1}`);
    });
    
    // Setup add schedule button
    const addScheduleBtn = document.getElementById('addScheduleBtn');
    if (addScheduleBtn) {
        addScheduleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addSchedule();
        });
        
        // Add keyboard support
        addScheduleBtn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                addSchedule();
            }
        });
        
        console.log('✅ Add schedule button initialized');
    } else {
        console.warn('⚠️ Add schedule button not found');
    }
    
    // Update initial schedule numbers
    updateScheduleNumbers();
    
    console.log('✅ TimePicker system initialized successfully!');
}

// Export for global access
window.TimePicker = {
    initializeTimePickerForCard,
    handleTimeInput,
    handleAmPmToggle,
    updateTimeDisplay,
    updateScheduleNumbers,
    removeSchedule,
    addSchedule,
    createScheduleCard,
    validateScheduleTimes,
    resetTimeDisplays,
    initializeTimePicker,
    validateTimeRange,
    formatTimeInput,
    parseTimeToDate
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on a page with schedule components
    if (document.querySelector('.schedule-container') || document.querySelector('#addClassForm')) {
        console.log('📅 Schedule components detected, initializing TimePicker...');
        initializeTimePicker();
    } else {
        console.log('ℹ️ No schedule components found on this page');
    }
});

// Global function for onclick handlers
window.removeSchedule = removeSchedule;
