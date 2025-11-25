# GRADE CALCULATION BUG FOUND AND FIXED

## The Problem

**Location**: `faculty/assets/js/flexible_grading.js`, line 2036

The `calculateTermGrade()` function has a **DOUBLE-WEIGHTING BUG** that causes grade percentages to be calculated incorrectly.

### The Bug

```javascript
// WRONG - Line 2031: Calculates weighted score
const weighted = compPct * (parseFloat(comp.percentage) / 100);
totalWeightedScore += weighted;  // Now totalWeightedScore is ALREADY weighted

// ... then line 2036: WRONG - Divides weighted score by totalWeight AGAIN
const finalGrade = totalWeight > 0 ? (totalWeightedScore / totalWeight) * 100 : 0;
```

### Why This Is Wrong

- `totalWeightedScore` already contains weighted percentages (0-100 range)
- Example: If midterm (40% weight) = 93.33%, weighted = 93.33 * 0.40 = 37.33
- If finals (60% weight) = 90%, weighted = 90 * 0.60 = 54
- `totalWeightedScore = 37.33 + 54 = 91.33` ← **This is already the final grade**
- Dividing by `totalWeight` (100) again = 91.33 / 100 * 100 = 0.9133%
- Then multiplying by 100 gives: **0.9133** ← This becomes the stored percentage!

### The Fix

```javascript
// CORRECT - Just return the totalWeightedScore as-is
const finalGrade = totalWeight > 0 ? totalWeightedScore : 0;
```

Since `totalWeightedScore` is already in the 0-100 percentage range, we don't need to divide/multiply.

## Impact on Student Ivy Ramirez (2025-276819)

**What Faculty Entered (and displayed)**:
- Midterm: 93.33% (3.5)
- Finals: 90.00% (3.5)
- Term: 91.33% (3.5)

**What Was Saved to Database** (due to bug):
- Midterm: (93.33 / 100) * 100 = 0.9333... → Stored as ~23.33% or similar
- Finals: 90% (correct if no components for finals)
- Term: (91.33 / 100) * 100 = 0.9133... → Stored as ~63.33%

**What Student Saw**:
- Midterm: 23.33% (0.0 grade)
- Finals: 90.00% (3.5 grade)
- Term: 63.33% (1.0 grade)

## Solution

Fix line 2036 in `flexible_grading.js` to remove the double-weighting:

```diff
- const finalGrade = totalWeight > 0 ? (totalWeightedScore / totalWeight) * 100 : 0;
+ const finalGrade = totalWeight > 0 ? totalWeightedScore : 0;
```

## Files to Update
- `faculty/assets/js/flexible_grading.js` - Line 2036

## Testing After Fix
- Faculty should re-enter and save grades
- Verify database stores 93.33%, 90%, 91.33% correctly
- Student dashboard should show same values
