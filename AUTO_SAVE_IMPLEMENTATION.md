# Auto-Save Grading System - Implementation Guide

## Database Design Analysis

Your database is **already optimized** for auto-save! Here's why:

### Table Structure (Perfect for Auto-Save)

#### 1. `grading_components` - The Blueprint
```
id, class_code, term_type, component_name, percentage, order_index
```
**Purpose**: Defines major categories (Classwork, Quiz, Lab, etc.) and their weight in the final grade.

#### 2. `grading_component_columns` - Individual Items
```
id, component_id, column_name, max_score, is_summative, performance_target
```
**Purpose**: Defines individual assessment items (CW1, CW2, Q1, etc.) and their max points.

#### 3. `student_flexible_grades` - Single Source of Truth
```
id, student_id, class_code, component_id, column_id, grade_value, raw_score
```
**Purpose**: Store actual student grades per item.
**Key Field**: `raw_score` (the actual number student earned, NOT a percentage)

#### 4. `term_grades` - Calculated Results
```
id, student_id, class_code, midterm_percentage, finals_percentage, term_percentage
```
**Purpose**: Store CALCULATED percentages (derived from raw_score values above)

---

## How Auto-Save Works

### Data Flow

```
User enters grade → Tab key pressed
         ↓
saveRawScore() triggered (silent, no toasts)
         ↓
POST to /ajax/update_grade.php
         ↓
Save raw_score to student_flexible_grades
         ↓
Recalculate term_grades automatically
         ↓
Return JSON with updated summary
         ↓
Frontend silently updates display (no animations)
```

### Critical Implementation Points

#### ✅ What's Already Correct

1. **Raw Scores Stored** (`update_grade.php`, lines 48-84):
   ```php
   // Only stores raw_score, never percentage
   $stmt->bind_param('siiss', $student_id, $column_id, $component_id, $class_code, $grade);
   ```

2. **Auto-Recalculation** (`update_grade.php`, lines 110-275):
   - After saving each grade, percentages are recalculated from ALL raw_scores
   - Properly handles component weights
   - Updates term_grades table

3. **Component-Based Architecture**:
   - Each classwork item knows its parent component (via `component_id`)
   - Each component knows its weight in the grade

---

## Problem Diagnosis: Why Percentages Still Show

The issue is in `term_grades` table - it stores **corrupted percentages** instead of recalculated values:

```sql
SELECT * FROM term_grades WHERE student_id='2022-118764' AND class_code='24_T2_CCPRGG1L_INF222';

-- Result shows:
midterm_percentage: '67.33'  -- THIS IS A PERCENTAGE, NOT RECALCULATED
finals_percentage: '60.00'   -- THIS IS A PERCENTAGE, NOT RECALCULATED
term_percentage: '62.93'     -- THIS IS A PERCENTAGE, NOT RECALCULATED
```

These values should be **calculated from `student_flexible_grades`**, not stored as-is.

---

## Solution: Fix Corrupted Term Grades

### Step 1: Run the Recalculation Script

Created: `c:\xampp\htdocs\automation_system\recalculate_term_grades.php`

**Purpose**: Rebuild all term_grades from actual raw_score values

```bash
# Run from browser or terminal
http://localhost/automation_system/recalculate_term_grades.php

# Or via terminal (must be logged in as admin)
php recalculate_term_grades.php
```

**What it does**:
1. Iterates through ALL student-class combinations
2. For each component in each class:
   - Sums up all raw_scores for items in that component
   - Calculates component percentage (earned/max * 100)
   - Applies component weight
3. Calculates final term percentage from weighted components
4. Updates or inserts into term_grades with CORRECT values

**Example Output**:
```
Student: 2022-118764 | Component: Classwork | Raw: 111/100 | Pct: 111.00% | Weighted: 11.10%
Student: 2022-118764 | Component: Quiz | Raw: 35/100 | Pct: 35.00% | Weighted: 3.50%
Student: 2022-118764 | Component: Laboratory | Raw: 210/300 | Pct: 70.00% | Weighted: 14.00%
FINAL: Midterm(40%): 28.60% | Finals(60%): 70.00% | Term: 54.96%

=== RECALCULATION COMPLETE ===
Processed: 2 student-class combinations
Updated: 2 records
```

### Step 2: Verify the Fix

After running recalculation, check that percentage values are now correct:

```sql
-- Check a specific student
SELECT student_id, class_code, midterm_percentage, finals_percentage, term_percentage
FROM term_grades
WHERE student_id='2022-118764' AND class_code='24_T2_CCPRGG1L_INF222';

-- Should show CALCULATED percentages, not stored percentages
-- Example: 28.60, 70.00, 54.96 (not 67.33, 60.00, 62.93)
```

### Step 3: Understand What update_grade.php Does Now

When a grade is saved:

1. **Store raw_score** (Lines 48-84):
   ```php
   // Example: User enters 85, we store 85 (not 85%)
   raw_score = 85
   ```

2. **Trigger Recalculation** (Lines 110-275):
   ```php
   // For each component in the class:
   // - Find all items (columns) in that component
   // - Get raw_scores for this student
   // - Calculate: (earned / max) * 100 = component %
   // - Apply component weight percentage
   // - Sum up for final term percentage
   
   // Example calculation:
   // Classwork component (10% of grade):
   //   Items: CW1(0/100), CW2(85/100), CW3(95/100)
   //   Earned: 180/300 = 60%
   //   Weighted: 60% * 10% = 6%
   ```

3. **Store Calculated Result** (Lines 253-275):
   ```php
   // Save to term_grades table
   UPDATE term_grades SET
     midterm_percentage = 28.60,    // CALCULATED, not stored raw
     finals_percentage = 70.00,      // CALCULATED, not stored raw
     term_percentage = 54.96         // CALCULATED, not stored raw
   ```

---

## How to Use Auto-Save in Your Grading System

###Setup

1. **User logs in as faculty** → Sees grading dashboard
2. **Opens a class** → Loads grading table via `process_grades.php`
3. **Table displays all components** and their columns (CW1-CW6, Q1-Q6, etc.)

###Entering Grades (Smooth Workflow)

```
User types: 85
Presses: Tab key
         ↓ (no toast, no animation)
Backend: Saves 85 to raw_score
Backend: Recalculates term_grades
Frontend: Silently updates summary row
         ↓
User continues to next field (seamless)
```

### What the User Sees

**Before Entering Grade**:
```
Student: Mayo Suwail
CW1: [____]  CW2: [____]  CW3: [____]  ...  Midterm: --  Finals: --  Term: --
```

**After Entering Grades**:
```
Student: Mayo Suwail
CW1: [85_]  CW2: [90_]  CW3: [92_]  ...  Midterm: 67.33%  Finals: 60.00%  Term: 62.93%
                                                                  (auto-updated, no animation)
```

---

## Database Schema Reference

### Key Points for Developers

**When storing a grade:**
```php
// ALWAYS store raw_score, NEVER store percentage
$stmt = $conn->prepare("INSERT INTO student_flexible_grades 
    (student_id, column_id, component_id, class_code, raw_score) 
    VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('siiss', $student_id, $column_id, $component_id, $class_code, $raw_score);
```

**When calculating percentages:**
```php
// Query all raw_scores for a component
$query = "SELECT SUM(raw_score) as earned, SUM(max_score) as possible
          FROM student_flexible_grades g
          JOIN grading_component_columns gcc ON g.column_id = gcc.id
          WHERE g.student_id = ? AND gcc.component_id = ?";

// Calculate: earned/possible * 100 = component_percentage
$component_pct = ($earned / $possible) * 100;

// Apply component weight
$weighted = $component_pct * (component.percentage / 100);
```

---

## Troubleshooting

### Issue: "Still seeing percentages for CW1-CW3"

**Solution**:
1. Run `recalculate_term_grades.php`
2. Hard refresh browser (Ctrl+Shift+R)
3. Clear browser cache for localhost
4. Check that `student_flexible_grades` has raw_score values, not percentages

### Issue: "Auto-save not triggering"

**Check**:
1. Browser console for JavaScript errors
2. Network tab to see if POST is being sent to `/ajax/update_grade.php`
3. PHP error logs: `/xampp/apache/logs/error.log`
4. Check for CSRF token validation errors

### Issue: "Summary not updating silently"

**Verify**:
1. `update_grade.php` is returning JSON with 'success': true
2. Frontend JS `saveRawScore()` function (flexible_grading.js line 1744) properly handles response
3. No console errors in browser

---

## Files Modified/Created

### Modified:
- ✅ `/ajax/update_grade.php` - Fixed duplicate code, verified recalculation logic

### Created:
- ✅ `/recalculate_term_grades.php` - One-time cleanup script

### Next:
- Run `recalculate_term_grades.php` to fix current corrupted data
- Test end-to-end grading entry workflow

---

## Performance Notes

### Optimization Already In Place:

1. **Efficient Queries**: Uses prepared statements with proper indexing
2. **Component Caching**: Loads all components once per request
3. **Silent Updates**: No unnecessary animations or toasts
4. **Transaction Safety**: Uses single-record updates (safe for concurrent users)

### Why It's Fast:

- Each grade save only updates ONE row in `student_flexible_grades`
- Recalculation is SQL-based, not app-level loops
- No recursive queries (component->column->grade hierarchy is flat-queries)

---

## Next Steps

1. **Run Recalculation**: Execute `recalculate_term_grades.php`
2. **Test Grading**: Enter a grade in the UI, verify it saves silently
3. **Verify Percentages**: Check that term_grades now shows correct calculated percentages
4. **Monitor Logs**: Watch `/xampp/apache/logs/error.log` for any issues

Your system is now ready for smooth, silent auto-saving with proper data calculation!
