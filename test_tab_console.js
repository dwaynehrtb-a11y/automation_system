// QUICK VERIFICATION - Add this to browser console to test

// Test that the function exists
console.log('handleGradeInputKeydown function:', typeof handleGradeInputKeydown);

// Count how many inputs have the keydown listener
const inputs = document.querySelectorAll('.fgs-score-input');
console.log('Total grade inputs found:', inputs.length);

// Try simulating Tab key on first input
if (inputs.length > 0) {
    const firstInput = inputs[0];
    firstInput.focus();
    firstInput.value = '85';
    
    // Create and dispatch Tab event
    const tabEvent = new KeyboardEvent('keydown', {
        key: 'Tab',
        code: 'Tab',
        keyCode: 9,
        which: 9,
        bubbles: true,
        cancelable: true
    });
    
    console.log('First input:', firstInput);
    console.log('Simulating Tab keypress...');
    firstInput.dispatchEvent(tabEvent);
}

console.log('✅ Test complete - check if focus moved to next input');
