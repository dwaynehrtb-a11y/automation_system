#!/bin/bash
# Hide Grades Feature - Testing Checklist & Commands
# Run this script from the project root directory

echo "=================================="
echo "Hide Grades Feature - Test Suite"
echo "=================================="
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test 1: Check database tables
echo -e "${BLUE}[TEST 1]${NC} Checking database tables..."
mysql -u root << EOF
USE automation_system;

-- Check grade_term table structure
SELECT "grade_term columns:" AS test;
SHOW COLUMNS FROM grade_term WHERE Field IN ('id', 'student_id', 'class_code', 'is_encrypted', 'term_grade');

-- Check grade_visibility_status table structure
SELECT "grade_visibility_status columns:" AS test;
SHOW COLUMNS FROM grade_visibility_status WHERE Field IN ('student_id', 'class_code', 'grade_visibility', 'changed_by');

-- Show sample data
SELECT "Sample grade records:" AS test;
SELECT 
    student_id, 
    class_code, 
    is_encrypted, 
    SUBSTR(term_grade, 1, 10) as term_grade_preview
FROM grade_term 
LIMIT 5;

SELECT "Visibility status distribution:" AS test;
SELECT grade_visibility, COUNT(*) as count 
FROM grade_visibility_status 
GROUP BY grade_visibility;
EOF

echo ""
echo -e "${BLUE}[TEST 2]${NC} Checking required files..."

files=(
    "faculty/ajax/encrypt_decrypt_grades.php"
    "student/ajax/get_grades.php"
    "dashboards/faculty_dashboard.php"
    "student/assets/js/student_dashboard.js"
    "student/student_dashboard.php"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $file exists"
    else
        echo -e "${RED}✗${NC} $file NOT FOUND"
    fi
done

echo ""
echo -e "${BLUE}[TEST 3]${NC} Checking for key functions..."

echo "Checking faculty/ajax/encrypt_decrypt_grades.php:"
if grep -q "encrypt_all" faculty/ajax/encrypt_decrypt_grades.php; then
    echo -e "${GREEN}✓${NC} encrypt_all action found"
else
    echo -e "${RED}✗${NC} encrypt_all action NOT FOUND"
fi

if grep -q "decrypt_all" faculty/ajax/encrypt_decrypt_grades.php; then
    echo -e "${GREEN}✓${NC} decrypt_all action found"
else
    echo -e "${RED}✗${NC} decrypt_all action NOT FOUND"
fi

echo ""
echo "Checking student/ajax/get_grades.php:"
if grep -q "grade_visibility_status" student/ajax/get_grades.php; then
    echo -e "${GREEN}✓${NC} Visibility check found"
else
    echo -e "${RED}✗${NC} Visibility check NOT FOUND"
fi

if grep -q "term_grade_hidden" student/ajax/get_grades.php; then
    echo -e "${GREEN}✓${NC} Hidden flag implementation found"
else
    echo -e "${RED}✗${NC} Hidden flag implementation NOT FOUND"
fi

echo ""
echo "Checking student/assets/js/student_dashboard.js:"
if grep -q "data-grades-hidden" student/assets/js/student_dashboard.js; then
    echo -e "${GREEN}✓${NC} Frontend hidden attribute found"
else
    echo -e "${RED}✗${NC} Frontend hidden attribute NOT FOUND"
fi

if grep -q "renderGradePreview" student/assets/js/student_dashboard.js; then
    echo -e "${GREEN}✓${NC} Grade preview render function found"
else
    echo -e "${RED}✗${NC} Grade preview render function NOT FOUND"
fi

echo ""
echo "=================================="
echo "Manual Testing Steps"
echo "=================================="
echo ""
echo "1. FACULTY HIDE GRADES TEST"
echo "   - Go to: dashboards/faculty_dashboard.php"
echo "   - Select Academic Year, Term, and Class"
echo "   - Click SUMMARY tab"
echo "   - Find 'Grade Encryption' section"
echo "   - Click 'Hide Grades' button"
echo "   - Confirm in dialog"
echo "   - Status should show: HIDDEN FROM STUDENTS (yellow)"
echo ""

echo "2. STUDENT VERIFY HIDDEN GRADES"
echo "   - Go to: student/student_dashboard.php (login as student)"
echo "   - Look for the class that was hidden"
echo "   - Grade preview should show 🔐 lock icons"
echo "   - Text should say: 'Grades not yet released'"
echo "   - Button should be disabled (grayed out)"
echo ""

echo "3. FACULTY SHOW GRADES TEST"
echo "   - Go back to faculty dashboard"
echo "   - Same class, click 'Show Grades' button"
echo "   - Confirm in dialog"
echo "   - Status should show: VISIBLE TO STUDENTS (green)"
echo ""

echo "4. STUDENT VERIFY VISIBLE GRADES"
echo "   - Go to student dashboard (refresh)"
echo "   - Should see actual grade values"
echo "   - Should see percentages"
echo "   - Button should be enabled (clickable)"
echo "   - Click 'View Detailed Grades' to see breakdown"
echo ""

echo "5. MULTIPLE CLASSES TEST"
echo "   - Hide grades for Class A"
echo "   - Show grades for Class B"
echo "   - Student should see:"
echo "     - Class A: locked 🔐"
echo "     - Class B: grades visible"
echo ""

echo "=================================="
echo "Quick Access to Test Tools"
echo "=================================="
echo ""
echo "System Verification Tool:"
echo "  URL: /verify_hide_grades.php"
echo "  Purpose: Check database and system status"
echo ""
echo "Interactive Testing Tool:"
echo "  URL: /test_hide_grades.php"
echo "  Purpose: Manual hide/show operations"
echo ""
echo "Documentation:"
echo "  - HIDE_GRADES_IMPLEMENTATION.md (technical details)"
echo "  - HIDE_GRADES_QUICK_REFERENCE.md (user guide)"
echo "  - HIDE_GRADES_SUMMARY.md (overview)"
echo ""

echo "=================================="
echo "Testing Complete!"
echo "=================================="
