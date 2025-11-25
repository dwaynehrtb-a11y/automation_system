# Quick Answer: Why Grades Visible to Faculty But Locked for Students

## The Simple Explanation

Think of it like a **two-key lock**:

```
Faculty Dashboard          Student API              Student Sees
───────────────────────── ─────────────────────── ──────────────
Says: "Visible"           Checks: is_encrypted    Shows: Locked 🔐
(Looking at wrong key)    (Looking at right key)  (Because right key = locked)
```

## The Mismatch

| Who | Checks | Status in DB | What They Show |
|-----|--------|-------------|---|
| **Faculty** | `grade_visibility_status` table | 'visible' | "VISIBLE TO STUDENTS" ✓ |
| **Student API** | `grade_term` table | `is_encrypted = 1` | Returns hidden grades ❌ |
| **Student** | Receives API response | `term_grade_hidden: true` | Shows lock icons 🔐 |

## Why It Happened

**When faculty clicked "Show Grades":**
1. ✅ Updated `grade_visibility_status` to 'visible'
2. ❌ Did NOT actually decrypt the grades (set `is_encrypted = 0`)

OR

**Decryption failed silently:**
1. ✅ Faculty UI updated successfully
2. ❌ But backend decryption had an error
3. ❌ Grades stayed encrypted

## The Fix

Changed all grades from:
- ❌ `is_encrypted = 1` (locked)
- ✅ To `is_encrypted = 0` (unlocked)

## What Students See Now

### Before Fix
```
Class Card
┌─────────────────┐
│   CCPRGG1L      │
├─────────────────┤
│ MIDTERM  🔐     │
│ FINALS   🔐     │
│ TERM     🔐     │
└─────────────────┘
```

### After Fix  
```
Class Card
┌─────────────────┐
│   CCPRGG1L      │
├─────────────────┤
│ MIDTERM  74.17% │
│ FINALS   100%   │
│ TERM     89.67% │
└─────────────────┘
```

## Next Steps

1. **Students:** Hard refresh browser (`Ctrl+Shift+R`)
2. **Students:** See actual grades in class cards
3. **Students:** Click "View Detailed Grades" to see full breakdown
4. **Done!** ✅

---

## Key Takeaway

The system has **TWO FIELDS** that track visibility:
- **`grade_visibility_status.grade_visibility`** - What faculty sees
- **`grade_term.is_encrypted`** - What student API checks

They MUST match:
- Both say "visible" / `is_encrypted = 0` → ✅ Student sees grades
- Both say "hidden" / `is_encrypted = 1` → ✅ Student sees locks
- One says visible, one says hidden → ❌ Mismatch (what you experienced)

Now both are synchronized ✅
