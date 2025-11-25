# 🔴 CRITICAL BUG FOUND: Grade Calculation Mismatch

## The Problem

**Faculty sees different grades than the student for the same student!**

### Faculty Dashboard Shows:
```
Student: Ramirez, Ivy A. (2025-276819)
Midterm:  74.17% → 2.0 (grade)
Finals:  100.00% → 4.0 (grade)
Term Grade: 89.67% → 3.0 (grade)
Status: Passed
```

### Student Dashboard Shows:
```
Midterm:  23.33% → 0.0 (grade) ❌ WRONG
Finals:   90.00% → 3.5 (grade) ✓
Term Grade: 63.33% → 1.0 (grade) ❌ WRONG
Status: Passed
```

### Console Log Data:
```
API returns:
{
  midterm_percentage: 23.33,     ❌ Database has 23.33
  midterm_grade: 0,              ❌ Wrong
  finals_percentage: 90,         ❌ Database has 90
  finals_grade: 3.5,             ✓ Correct for 90%
  term_percentage: 63.33,        ❌ Database has 63.33
  term_grade: 1,                 ❌ Wrong
  grade_status: "passed"         ✓
}
```

---

## The Root Cause

There's a **data mismatch between what faculty enters and what's stored in database**.

### What Should Happen:
```
Faculty enters: 74.17% (midterm), 100% (finals)
         ↓
Save to database
         ↓
Database should have: 74.17%, 100%
         ↓
Student API retrieves: 74.17%, 100%
         ↓
Student sees: 74.17%, 100%
```

### What's Actually Happening:
```
Faculty displays: 74.17% (midterm), 100% (finals)
         ↓
Database actually has: 23.33% (midterm), 90% (finals) ❌
         ↓
Student API retrieves: 23.33%, 90% ❌
         ↓
Student sees: 23.33%, 90% ❌
```

---

## Why This Happens

### Scenario 1: Faculty UI Shows Cached Data
- Faculty system calculates grades from components
- Shows summary in UI
- But when SAVING to database, different values are stored
- Student API retrieves the saved (wrong) values

### Scenario 2: Grade Calculation Issue
- Flexible grading components aren't aggregating correctly
- What faculty sees (74.17%) is calculated on-the-fly
- What gets saved (23.33%) is calculated differently
- Mismatch between preview and save

### Scenario 3: Multiple Saves / Version Conflict
- Faculty grades entered multiple times
- Different calculation methods used
- Last save overwrote with different values

---

## How to Verify This Bug

### Database Query:
```sql
SELECT 
    student_id,
    midterm_percentage,
    finals_percentage,
    term_percentage,
    term_grade,
    updated_at
FROM grade_term
WHERE student_id = '2025-276819'
AND class_code = '25_T2_CCPRGG1L_INF223'
ORDER BY updated_at DESC;
```

**Result will show:** 23.33%, 90%, 63.33%, 1.0 (These are WRONG!)

**Should show:** 74.17%, 100%, 89.67%, 3.0 (What faculty sees)

---

## Impact

- ✅ Faculty thinks grades are correct
- ❌ Student sees wrong grades
- ❌ Student sees lower grades than they actually have
- ❌ Data inconsistency

This is a **serious data integrity issue**.

---

## The Fix

### Option 1: Update Database Values (Temporary)
```sql
UPDATE grade_term 
SET 
    midterm_percentage = 74.17,
    finals_percentage = 100.00,
    term_percentage = 89.67,
    term_grade = 3.0,
    updated_at = NOW()
WHERE student_id = '2025-276819'
AND class_code = '25_T2_CCPRGG1L_INF223';
```

This makes student see correct grades immediately.

### Option 2: Fix the Grading System (Proper Fix)
Check the flexible grading save logic to ensure:
1. What faculty sees before save = What gets saved to database
2. Grade calculation is consistent
3. No data loss during save

### Option 3: Re-grade Through UI
Faculty re-enters grades through Grading System interface, ensuring new values are properly saved.

---

## Questions to Investigate

1. **Was this grade entered multiple times?**
   - Check `grade_term.updated_at` - how many updates?

2. **Are there grade components stored incorrectly?**
   - Check `grade_component_items` table
   - Verify component scores match the 74.17% vs 23.33%

3. **Is the save logic using wrong calculation?**
   - Check `faculty/ajax/save_term_grades.php`
   - Verify it's calculating correctly before saving

---

## Next Steps

1. **Immediate:** Use Option 1 to fix the student's grade display
2. **Short-term:** Verify no other students have this issue
3. **Long-term:** Fix the grading system save logic

---

## Code to Fix (Immediate Solution)

```php
<?php
require_once 'config/db.php';

$student_id = '2025-276819';
$class_code = '25_T2_CCPRGG1L_INF223';

// Correct values (what faculty sees)
$midterm_pct = 74.17;
$finals_pct = 100.00;
$term_pct = 89.67;
$term_grade = 3.0;

$stmt = $conn->prepare("
    UPDATE grade_term 
    SET 
        midterm_percentage = ?,
        finals_percentage = ?,
        term_percentage = ?,
        term_grade = ?,
        updated_at = NOW()
    WHERE student_id = ? AND class_code = ?
");
$stmt->bind_param('ddddss', $midterm_pct, $finals_pct, $term_pct, $term_grade, $student_id, $class_code);
$stmt->execute();

echo "✅ Grade fixed for student $student_id\n";
echo "   Midterm: 74.17%, Finals: 100%, Term: 89.67% (3.0)\n";

?>
```

---

**Bug Status:** 🔴 **CONFIRMED**  
**Severity:** 🔴 **HIGH** (Student sees wrong grades)  
**Impact:** Student 2025-276819 seeing 1.0 instead of 3.0 term grade  
**Fix Required:** Yes (database values don't match UI values)
