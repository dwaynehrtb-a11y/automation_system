# Debug Guide: Percentage Display Issues

## New Debug Version: v2.7

The flexible grading system now has **comprehensive console logging** to track exactly where percentage values come from and how they're being processed.

---

## How to Access the Debug Logs

1. **Open Faculty Dashboard** and navigate to the Grading System
2. **Press F12** to open Developer Tools
3. **Click the "Console" tab** at the top
4. **Select your class and component** to start grading
5. **Watch the console output** - you'll see detailed logs for every operation

---

## What to Look For

### 1. **Initial Load - `loadGrades()` logs**

When you first load a component, you'll see:

```
🔵 loadGrades called - componentId: 175
🔵 loadGrades response: {success: true, grades: {...}, count: 7}
🔵 FGS.grades updated: 7 grades loaded
🔵 ====== COMPLETE GRADES DUMP (7 total) ======
  [0] 2022-118764_175 = 56.83 | CW1 /100
  [1] 2022-118764_176 = 10 | cw 2 /10
  [2] 2022-118764_177 = 10 | cw 3 /10
  [3] 2022-118764_178 = 10 | cw 4 /10
  [4] 2022-118764_179 = 10 | cw 5 /10
  [5] 2022-118764_180 = 10 | cw 6 /10
🔵 ====== END DUMP ======
```

**What this tells you:**
- **Column 175 (CW1)**: Value is `56.83` out of 100 → This is ALREADY a raw score (not a percentage!)
- **Columns 176-180 (cw2-cw6)**: All have value `10` out of 10 → These are also raw scores

**If you see percentages like "56.83%" in the dump, that's the problem!**

---

### 2. **Rendering - `renderTable()` logs**

When the table is rendered, you'll see detailed per-column breakdowns:

```
🎨 ====== RENDERING STUDENT #0: 2022-118764 ======
  Col 0: CW1 (ID:175, maxScore:100)
    Key: 2022-118764_175
    Grade Object: {score: 56.83}
    Raw Value From DB: 56.83 (type: number)
    → Parsed numVal: 56.83
    → Check: numVal (56.83) > maxScore (100)? false
    ✓ No fix needed - value is already raw score
    → Final displayVal: "56.83"
    → Will render with background: #fff (WHITE)

  Col 1: cw 2 (ID:176, maxScore:10)
    Key: 2022-118764_176
    Grade Object: {score: 10}
    Raw Value From DB: 10 (type: number)
    → Parsed numVal: 10
    → Check: numVal (10) > maxScore (10)? false
    ✓ No fix needed - value is already raw score
    → Final displayVal: "10"
    → Will render with background: #fff (WHITE)

🎨 ====== END STUDENT RENDER ======
```

**What this tells you:**
- For each column, it shows: Is the value > max score? Is it being corrected?
- **YELLOW background** = value was auto-corrected from percentage to raw
- **WHITE background** = value is already a raw score
- **`displayVal`** = what will appear in the input field

---

### 3. **Saving - `saveRawScore()` logs**

When you change a grade, you'll see:

```
💾 ====== SAVE TRIGGERED ======
💾 Student: 2022-118764, Column: 175, Input Value: "56.83"
💾 Column: CW1 / 100
💾 Sending to server: grade=56.83, student=2022-118764, column=175
💾 Response from server: {success: true, message: "Grade updated successfully!", recomputed: {...}}
💾 ✅ Save successful!
💾 🔄 Reloading grades for component 175...
🔵 loadGrades called - componentId: 175
🔵 loadGrades response: {success: true, grades: {...}, count: 7}
🔵 FGS.grades updated: 7 grades loaded
💾 🎨 Re-rendering table...
🎨 ====== RENDERING STUDENT #0: 2022-118764 ======
  ...
🎨 ====== END STUDENT RENDER ======
```

**What this tells you:**
- What value was sent to the server
- What the server responded with
- Whether the reload and re-render happened

---

## Key Debug Checks

### Check 1: Are percentages coming from the database?

**Look at the COMPLETE GRADES DUMP** (lines starting with `🔵`):
- Do you see values like `"56.83"` for a `/100` column? That's a raw score.
- Do you see values like `"71.83"` for a `/10` column? **That's the percentage!** (Should be 7.183)
- Do you see `"60.00%"` with a % symbol? That's definitely a percentage string.

### Check 2: Is the auto-fix being triggered?

**Look for the AUTO-FIX log** (lines with 🔧):
```
🔧 AUTO-FIX TRIGGERED: Converting 71.83% → 7.183/10
```

If you DON'T see this but the value is wrong, then the percentage isn't being detected. Why?
- The detection logic checks: `if (numVal > maxScore && numVal <= 100)`
- For CW1 (max 100), a value of 56.83 would NOT trigger fix (56.83 is NOT > 100)
- For cw 2-6 (max 10), a value of 10 would NOT trigger fix (10 is NOT > 10)

### Check 3: What gets saved to the database?

**Look at the SAVE logs**:
- What `Input Value` is being sent?
- After re-render, what does the grade dump show?
- Did the value change in the database?

---

## Common Scenarios

### Scenario A: Value shows as "56.83" for CW1 (/100)
```
💾 Input Value: "56.83"
🎨 Final displayVal: "56.83"
```
**Status**: ✅ CORRECT - This is a raw score, not a percentage.

---

### Scenario B: Value shows as "71.83" for cw 2 (/10)
```
💾 Input Value: "71.83"
🔧 AUTO-FIX TRIGGERED: Converting 71.83% → 7.183/10
🎨 Final displayVal: "7.18" (with YELLOW background)
```
**Status**: ⚠️ STORED AS PERCENTAGE - Auto-fix triggered, corrected on display.

---

### Scenario C: Value shows as "71.83%" (with % symbol)
```
🔵 Raw Value From DB: 71.83% (type: string)
→ Found % symbol, stripping...
→ After strip: 71.83
🔧 AUTO-FIX TRIGGERED: Converting 71.83% → 7.183/10
```
**Status**: ⚠️ STRING WITH % - Will be stripped and corrected.

---

## Troubleshooting Steps

### Step 1: Clear your browser cache
The new v2.7 has extensive logging. If you don't see the new logs, your browser is cached on v2.6.

**Do one of:**
- **Ctrl + Shift + F5** (hardest refresh)
- **F12 → Right-click refresh button → "Empty Cache and Hard Reload"**
- **Close all browser tabs and restart browser**

Verify v2.7 loaded: First console log should show v2.7, not v2.6.

### Step 2: Reproduce the issue
1. Load your class and component
2. **Take a screenshot of the input fields** showing which ones display percentages
3. **Look at the console logs** to see:
   - What values are in the database (GRADES DUMP)
   - What values are being rendered (RENDERING logs)
   - What's being saved (SAVE logs)

### Step 3: Collect the data
**Copy the entire console output** (select all logs, Ctrl+C) and share:
1. The GRADES DUMP section
2. The RENDERING section for affected columns
3. What input values actually show on screen

### Step 4: Identify the pattern
- **Are CW1-CW3 showing different values than CW4-CW6?**
  - Check GRADES DUMP: are they stored differently in the database?
- **Do the percentages appear in a separate column, not the input fields?**
  - Maybe you're looking at the TOTAL % column, not the individual scores?
- **After you change a value and refresh, does it change back?**
  - Check SAVE logs: was the change actually saved?

---

## What to Report

If the issue persists, provide:

1. **Screenshot** of the grading table with percentages visible
2. **Console logs** (F12 → Console tab, copy everything)
3. **Student ID and column name** showing the percentage
4. **What you entered** and **what currently displays**
5. **Whether it's in the input field or a separate column**

Example report:
```
Student: 2022-118764
Column: CW1 (/100)
Expected: 56.83
Actually shows: 56.83%
Location: Inside the input field
After refresh: Changes to 56.83 (correct)

Console shows:
🔵 Raw Value From DB: 56.83% (type: string)
```

---

## Manual Test

To manually test if the system is working:

1. **Open Console (F12)**
2. **Type this JavaScript** and press Enter:
```javascript
// Check what's in FGS.grades
console.table(FGS.grades);

// Check a specific student
console.log(FGS.grades['2022-118764_175']);
```

This will show you exactly what values the JavaScript has loaded from the database.

---

## Version Info

- **v2.6**: Initial debugging added
- **v2.7**: **Comprehensive logging** - Every step logged with emojis and details
  - 🔵 loadGrades events
  - 🎨 renderTable events  
  - 💾 saveRawScore events
  - 🔧 Auto-fix triggers

---

## FAQ

**Q: I don't see any logs in the console**
A: Press F12, reload page, then try again. Make sure Console tab is active. Also check cache version - refresh hard (Ctrl+Shift+F5).

**Q: The logs show correct values, but display is wrong**
A: There may be CSS or JavaScript from another script overwriting the values. Check for MutationObserver warnings (⚠️ INPUT VALUE CHANGED EXTERNALLY).

**Q: Should CW1-CW3 have YELLOW backgrounds?**
A: Only if the AUTO-FIX was triggered (percentage detected and corrected). If correct raw values are in DB, should be WHITE.

**Q: Why does the GRADES DUMP show different values than what I see?**
A: After auto-fix, values are corrected before display but database has original value. After save, database gets updated with corrected value. Reload the component to see updated database value in GRADES DUMP.

