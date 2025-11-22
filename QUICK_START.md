# Quick Reference - Auto-Save Setup

## 1. Run This First (Critical!)

```bash
cd c:\xampp\htdocs\automation_system
php recalculate_term_grades.php
```

**What it does**: Fixes corrupted percentage values in `term_grades` by recalculating from actual raw_score data.

**Before**:
```
term_grades shows: midterm=67.33%, finals=60.00%, term=62.93%  ❌ WRONG (stored percentages)
```

**After**:
```
term_grades shows: midterm=28.60%, finals=70.00%, term=54.96%  ✅ CORRECT (calculated from data)
```

---

## 2. Database Structure (Auto-Save Optimized)

### Where grades go:
```
student_flexible_grades (single source of truth)
├── raw_score: 85 (what user entered)
├── column_id: 175 (CW1 item)
├── component_id: 40 (Classwork component)
└── class_code: '24_T2_CCPRGG1L_INF222'
```

### How they're calculated:
```
term_grades (auto-calculated display)
├── midterm_percentage: 28.60% (sum of midterm component weighted scores)
├── finals_percentage: 70.00% (sum of finals component weighted scores)
└── term_percentage: 54.96% (40% midterm + 60% finals)
```

---

## 3. How It Works

```
User enters: 85
Presses: Tab
    ↓
Backend: INSERT INTO student_flexible_grades (raw_score = 85)
Backend: RECALCULATE all percentages from raw_scores
Backend: UPDATE term_grades with calculated percentages
    ↓
Frontend: Update summary silently (no toasts, no animations)
    ↓
User continues typing (seamless experience)
```

---

## 4. Files You Need to Know

### Entry Point
- `/ajax/update_grade.php` - Handles grade save + recalculation

### One-Time Setup
- `/recalculate_term_grades.php` - Fixes corrupted data (run once)

### Documentation
- `/AUTO_SAVE_IMPLEMENTATION.md` - Full technical guide

---

## 5. Testing

### Test Grade Entry:
1. Open faculty dashboard
2. Select class '24_T2_CCPRGG1L_INF222'
3. Enter grade in CW1: `85`
4. Press Tab
5. **Expected**: CW1 shows `85`, summary updates silently

### Verify Backend:
```sql
-- Check that raw_score is stored (not percentage)
SELECT * FROM student_flexible_grades 
WHERE student_id='2022-118764' AND column_id=175;

-- Should show: raw_score = 85 ✅

-- Check that term_grades is recalculated
SELECT midterm_percentage, finals_percentage, term_percentage 
FROM term_grades 
WHERE student_id='2022-118764' AND class_code='24_T2_CCPRGG1L_INF222';

-- Should show calculated percentages, not stored percentages
```

---

## 6. Common Issues & Fixes

| Issue | Fix |
|-------|-----|
| Still seeing 67.33%, 60%, 62.93% | Run `recalculate_term_grades.php` again |
| Auto-save not working | Check browser console for JS errors |
| Percentages seem wrong | Verify term_grades table was recalculated |
| Grade doesn't appear in summary | Hard refresh browser (Ctrl+Shift+R) |

---

## 7. Key Database Concepts

### ✅ Raw Scores (Always stored)
- What the student earned (1-100)
- Stored in `student_flexible_grades.raw_score`
- NEVER stored as a percentage

### ✅ Component Weights
- How much each category counts (Classwork 10%, Quiz 10%, Lab 20%, etc.)
- Stored in `grading_components.percentage`

### ✅ Calculated Percentages
- Derived from raw_scores × component weights
- Stored in `term_grades.midterm_percentage`, `finals_percentage`, `term_percentage`
- Recalculated every time a grade is saved

---

## 8. Silent Auto-Save Feature

**User Experience**:
- Enter grade
- Press Tab
- ✅ Grade saves automatically
- ✅ Summary updates automatically
- ✅ NO toast messages
- ✅ NO animations
- ✅ NO page refresh
- **Result**: Smooth, uninterrupted data entry

**Technical Implementation** (`/ajax/update_grade.php`):
```php
// 1. Save raw_score
INSERT INTO student_flexible_grades (raw_score) ...

// 2. Recalculate term percentages
$midterm_pct = calculate_component_percentage('midterm');
$finals_pct = calculate_component_percentage('finals');
$term_pct = ($midterm_pct * 0.40) + ($finals_pct * 0.60);

// 3. Update term_grades
UPDATE term_grades SET 
  midterm_percentage = $midterm_pct,
  finals_percentage = $finals_pct,
  term_percentage = $term_pct;

// 4. Return JSON (no page redirect)
return {'success': true, 'recomputed': {...}};
```

---

## 9. Next Steps

✅ **Step 1**: Run `recalculate_term_grades.php` (fixes corrupted data)  
✅ **Step 2**: Test entering a grade (should save silently)  
✅ **Step 3**: Verify percentages are correct in database  
✅ **Step 4**: Users can now enter grades smoothly!

---

## Questions?

Refer to: `/AUTO_SAVE_IMPLEMENTATION.md` for detailed technical documentation.
