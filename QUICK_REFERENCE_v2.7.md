# QUICK REFERENCE - Debug v2.7

## TL;DR - Do This Now

```
1. Press Ctrl+Shift+F5 (force refresh)
2. Press F12 (open console)
3. Load your grading component
4. Look for "GRADES DUMP" and "RENDERING" sections in console
5. Screenshot those sections
6. Send me the screenshot
```

---

## Console Log Sections

### 🔵 GRADES DUMP
**Shows**: What values are actually stored in database
**Location**: Appears first when you load a component
**What to look for**: 
- Are the values raw scores (e.g., `56.83` for /100) or percentages (e.g., `71.83` for /10)?

**Example:**
```
🔵 [0] 2022-118764_175 = 56.83 | CW1 /100  ← CORRECT (raw score)
🔵 [1] 2022-118764_176 = 71.83 | cw 2 /10  ← WRONG (should be 7.183)
```

---

### 🎨 RENDERING
**Shows**: How each column is processed before display
**Location**: Appears after GRADES DUMP
**What to look for**:
- Does it say "No fix needed" or "AUTO-FIX TRIGGERED"?
- What is the "Final displayVal" going to show?

**Example:**
```
🎨 Col 0: CW1 (maxScore:100)
  Raw Value: 56.83 (type: number)
  → numVal (56.83) > maxScore (100)? false
  ✓ No fix needed
  → Final displayVal: "56.83"  ← Will show THIS in input field

🎨 Col 1: cw 2 (maxScore:10)
  Raw Value: 71.83 (type: number)  
  → numVal (71.83) > maxScore (10)? true
  🔧 AUTO-FIX TRIGGERED: Converting 71.83% → 7.183/10
  → Final displayVal: "7.18"  ← Will show THIS in input field (CORRECTED)
```

---

### 💾 SAVE
**Shows**: When you change a value, what happens
**Location**: Appears when you edit a grade and move to next field
**What to look for**:
- Was the save successful?
- After saving, did it reload and re-render correctly?

**Example:**
```
💾 Input Value: "9.5"
💾 Response from server: {success: true, ...}
💾 ✅ Save successful!
💾 🔄 Reloading grades...
```

---

## Problem Diagnosis

### If you see this...
```
🔵 Raw Value: 71.83 (type: number) for column with /10 max
🎨 AUTO-FIX TRIGGERED: Converting 71.83% → 7.183/10
🎨 → Final displayVal: "7.18"
```
✅ **System is working** - The value IS being corrected before display. Browser should show "7.18" in the input field. **If it shows "71.83%" instead, clear your browser cache.**

---

### If you see this...
```
🔵 Raw Value: 56.83% (type: string)
→ Found % symbol, stripping...
🔧 AUTO-FIX TRIGGERED: Converting 56.83% → 5.683/100
```
⚠️ **Database stores percentages AS STRINGS with %** - System is handling it, but backend is storing data incorrectly.

---

### If you see this...
```
🔵 Raw Value: 56.83 (type: number) for column with /100 max
→ numVal (56.83) > maxScore (100)? false
✓ No fix needed
→ Final displayVal: "56.83"
```
✅ **Correct** - Raw score is stored properly. Input should show "56.83". If it shows "56.83%", cache issue or separate UI bug.

---

## Quick Answers

| What You See | Root Cause | Solution |
|---|---|---|
| GRADES DUMP shows `71.83` for `/10` column | Database stores percentages instead of raw scores | Backend needs fixing to convert on save |
| RENDERING shows AUTO-FIX TRIGGERED | System detects percentage, corrects it | Working as intended - verify display |
| RENDERING shows "No fix needed" with correct value | Value is already raw score | Should display correctly |
| Input shows "71.83%" but RENDERING shows "7.18" | Browser showing old cached version | Hard refresh: Ctrl+Shift+F5 |
| After save, value reverts | Either save failed or reload didn't work | Check 💾 SAVE logs for errors |

---

## How to Share Your Debug Info

Send me a message with:

1. **Screenshot of GRADES DUMP section** (shows database values)
2. **Screenshot of RENDERING section** (shows processing)
3. **Screenshot of actual table** (shows what displays)
4. **Answers:**
   - Which columns show percentages?
   - Which show raw scores?
   - After you change and save, does it persist or revert?

---

## Emoji Guide

| Emoji | Meaning |
|-------|---------|
| 🔵 | Loading grades from database |
| 🎨 | Rendering table for display |
| 💾 | Saving a grade |
| 🔧 | Auto-fix triggered (percentage corrected) |
| ✅ | Success |
| ❌ | Error/Failure |
| ✓ | Condition met |
| → | Processing step |
| 🔄 | Reloading/Re-rendering |

---

## If Still Broken After Cache Clear

1. Close ALL browser tabs
2. Close browser completely
3. Wait 10 seconds
4. Reopen browser
5. Go to Faculty Dashboard
6. Check console - should say v2.7 now

If STILL showing v2.6, browser cache is corrupted:
- Chrome: Settings → Privacy → Clear browsing data → Select "All time" → Clear
- Firefox: Settings → Privacy → Cookies and Data → Clear
- Then reload

---

## One More Thing

The system NOW has auto-fix for legacy percentage data. Even if database stores percentages, they'll be corrected on display (with YELLOW highlighting). After you save, they'll be corrected in the database too.

So even if the problem exists, **the system now protects against it** and automatically fixes it!

