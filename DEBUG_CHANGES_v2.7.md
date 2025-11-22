# Comprehensive Debug Changes - v2.7

## Summary

Added **extensive console logging** to track exactly where percentage values come from, how they're processed, and how they're displayed. Every major operation now logs detailed information with emoji prefixes for easy visual scanning.

---

## Changes Made

### 1. **loadGrades() Function** (Lines 101-145)

**Added Logging:**
- ✅ Logs when called with component ID
- ✅ Logs raw API response
- ✅ Shows complete dump of ALL grades loaded from database
- ✅ For each grade, displays: student_id_columnId, value, column_name, max_score

**Example Output:**
```
🔵 loadGrades called - componentId: 175
🔵 loadGrades response: {success: true, grades: {...}, count: 7}
🔵 FGS.grades updated: 7 grades loaded
🔵 ====== COMPLETE GRADES DUMP (7 total) ======
  [0] 2022-118764_175 = 56.83 | CW1 /100
  [1] 2022-118764_176 = 10 | cw 2 /10
  ...
🔵 ====== END DUMP ======
```

**Why This Matters:**
- Shows EXACTLY what values are in the database
- If you see "56.83%" with %, that's a string with percentage symbol
- If you see "71.83" for a /10 column, that's stored as percentage (should be 7.183)

---

### 2. **renderTable() Function** (Lines 459-590)

**Added Logging for Each Student and Column:**

For the FIRST STUDENT ONLY (to avoid spam):
- Column name, ID, and max score
- Complete grade object from FGS.grades
- Raw value from database with data type
- Check 1: Is value > max score? (Y/N)
- Check 2: Is value <= 100? (Y/N)
- If both true → AUTO-FIX TRIGGERED logs
- Final displayVal that will appear in input field
- Background color that will be applied (YELLOW if fixed, WHITE if not)

**Example Output:**
```
🎨 ====== RENDERING STUDENT #0: 2022-118764 ======
  Col 0: CW1 (ID:175, maxScore:100)
    Key: 2022-118764_175
    Grade Object: {score: 56.83}
    Raw Value From DB: 56.83 (type: number)
    → Parsed numVal: 56.83
    → Check: numVal (56.83) > maxScore (100)? false
    → Check: numVal (56.83) <= 100? true
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

  Col 2: cw 3 (ID:177, maxScore:10)
    Key: 2022-118764_177
    Grade Object: {score: 60}
    Raw Value From DB: 60 (type: number)
    → Parsed numVal: 60
    → Check: numVal (60) > maxScore (10)? true
    → Check: numVal (60) <= 100? true
    🔧 AUTO-FIX TRIGGERED: Converting 60% → 6/10
    → Final displayVal: "6"
    → Will render with background: #fff3cd (YELLOW)

🎨 ====== END STUDENT RENDER ======
```

**Why This Matters:**
- Shows the EXACT decision-making process for each column
- You can see if auto-fix should have triggered but didn't (possible logic bug)
- You can see what value is INTENDED to display before rendering
- Helps identify if database has percentages vs raw scores

---

### 3. **saveRawScore() Function** (Lines 1960-2015)

**Added Logging:**
- Logs when save is triggered
- Shows which student, column, and input value
- Logs the column name and max score
- Logs the exact FormData being sent
- Logs HTTP response status
- Logs JSON parse errors with full response text
- Logs database response with success/failure
- Logs grade reload process
- Logs table re-render process

**Example Output:**
```
💾 ====== SAVE TRIGGERED ======
💾 Student: 2022-118764, Column: 175, Input Value: "9.5"
💾 Column: CW1 / 100
💾 Sending to server: grade=9.5, student=2022-118764, column=175
💾 Response from server: {success: true, message: "Grade updated successfully!", ...}
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

**Why This Matters:**
- Shows if the save actually happened and was successful
- Shows if the server responded with errors
- Shows the full reload→re-render cycle
- Can identify if changes aren't persisting to database
- Shows HTTP errors or JSON parse errors that might be silent in UI

---

## How to Use These Logs

### Step 1: Open Console
Press `F12` and click the **Console** tab.

### Step 2: Load Your Component
Select a class and grading component. Watch the logs appear in console.

### Step 3: Reproduce Issue
1. Identify which columns show percentages
2. Look at the COMPLETE GRADES DUMP for those columns
3. Look at the RENDERING section for those columns
4. Compare what the logs show vs. what displays in UI

### Step 4: Make a Change
1. Edit a grade value in an input field
2. Press Tab or click another field to trigger save
3. Watch the SAVE logs and subsequent RELOAD logs
4. Check if the value persists or reverts

### Step 5: Take Screenshot
Capture the console output showing the exact logs for the affected columns.

---

## Key Log Sections

| Section | Emoji | What It Shows |
|---------|-------|---------------|
| **Load Data** | 🔵 | What grades are fetched from database |
| **Render Table** | 🎨 | How each column value is processed and displayed |
| **Auto-Fix** | 🔧 | When percentages are detected and converted |
| **Save Grade** | 💾 | When a value is saved and what response comes back |
| **Reload** | 🔄 | Re-fetching grades after save |

---

## Emoji Legend

- 🔵 = loadGrades operation
- 🎨 = renderTable operation
- 💾 = saveRawScore operation
- 🔧 = AUTO-FIX percentage detection
- ✅ = Success confirmation
- ❌ = Error/failure
- ⚠️ = Warning (external modification)
- 🔄 = Reloading data
- → = Processing step
- ✓ = Condition check passed
- ? = Condition check failed

---

## Data Type Tracking

The logs now show `(type: number)` or `(type: string)` to help identify:
- **`"56.83%"` (type: string)** = Stored as string with % symbol → Will be stripped
- **`56.83` (type: number)** = Stored as number → Use directly
- **Empty** = No grade for this student/column yet

---

## Comparison to Previous Version (v2.6)

| Feature | v2.6 | v2.7 |
|---------|------|------|
| Basic logs | ✅ | ✅ |
| Full grades dump | ❌ | ✅ Complete dump with names |
| Per-column breakdown | ✅ Limited | ✅ Comprehensive |
| Auto-fix logs | ✅ | ✅ Same + Decision logic |
| Save process logging | ✅ Basic | ✅ Detailed with errors |
| Type information | ❌ | ✅ Shows `(type: string/number)` |
| Database column info | ❌ | ✅ Shows column_name and max_score |
| Error details | ❌ | ✅ Shows parse errors and responses |

---

## Performance Note

These logs only apply to the **first student** in the table to prevent console spam. Subsequent students render silently. If you need to debug a different student, you can manually reload and select a different component, and the new "first student" will log all details.

---

## Next Steps

1. **Hard refresh browser** (Ctrl+Shift+F5) to load v2.7
2. **Verify version**: First console line should be v2.7, not v2.6
3. **Load a class and component**
4. **Check console for GRADES DUMP** - verify if database stores percentages or raw scores
5. **Identify affected columns** - which ones show percentages in UI?
6. **Share console output** - include GRADES DUMP and RENDERING sections for problem columns

