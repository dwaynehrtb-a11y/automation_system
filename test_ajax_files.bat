@echo off
echo ========================================
echo Testing AJAX Validation Endpoints
echo ========================================
echo.

echo Testing check_student_id.php...
curl -s "http://localhost/automation_system/ajax/check_student_id.php?id=2024-123456"
echo.
echo.

echo Testing check_student_email.php...
curl -s "http://localhost/automation_system/ajax/check_student_email.php?email=test@test.com"
echo.
echo.

echo Testing check_course_code.php...
curl -s "http://localhost/automation_system/ajax/check_course_code.php?code=TESTCODE"
echo.
echo.

echo ========================================
echo All tests complete!
echo If you see JSON responses above, the files are working correctly.
echo ========================================
pause
