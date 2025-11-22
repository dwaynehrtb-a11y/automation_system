Bulk flexible grade upload feature has been removed from the system. This guide is deprecated and retained only as a placeholder.

No bulk grade CSV/Excel import is currently supported. Please use the per-item / per-student grading interface to enter scores.

If you later decide to restore this functionality, reintroduce:
- Upload endpoint (previously `ajax/bulk_flexible_grade_upload.php`)
- Column export endpoint (previously `ajax/export_flexible_columns.php`)
- Front-end form in `faculty_dashboard.php`
- JS integration in `flexible_grading.js`

For now, you can safely ignore any references to bulk grade import.
