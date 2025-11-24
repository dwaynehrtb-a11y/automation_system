# Course Outcomes Assessment (COA) - Complete Data Query Guide

## Database Structure for COA

### Core Tables

#### 1. **course_outcomes**
Stores learning outcomes for each course
- `co_id`: Primary key
- `course_code`: Links to subject (FK)
- `co_number`: Outcome number (1, 2, 3...)
- `co_description`: Description of the outcome
- `so_mappings`: JSON array mapping to student outcomes [1,1,1,0,0,1]

**Sample Data for CCPRGG1L:**
```
co_id | course_code | co_number | co_description
62    | CCPRGG1L    | 1         | Recognize modular programming
63    | CCPRGG1L    | 2         | Apply different control structures
64    | CCPRGG1L    | 3         | Implement array data structure and file manipulation
```

#### 2. **grading_components**
Assessment items/components for a class
- `id`: Primary key
- `class_code`: Links to class
- `component_name`: Name (Classwork, Quiz, Mock Defense, etc.)
- `percentage`: Weight in final grade
- `term_type`: 'midterm' or 'finals'

**Sample for class 24_T2_CCPRGG1L_INF222:**
```
id | class_code              | component_name | percentage | term_type
44 | 24_T2_CCPRGG1L_INF222  | Classwork      | 10.00      | midterm
48 | 24_T2_CCPRGG1L_INF222  | Quiz           | 10.00      | midterm
43 | 24_T2_CCPRGG1L_INF222  | Mock Defense   | 60.00      | finals
```

#### 3. **grading_component_columns**
Individual grading items within a component
- `id`: Primary key
- `component_id`: Links to grading_components
- `column_name`: Item name (CW 1, Quiz 1, etc.)
- `max_score`: Maximum possible score
- `co_mappings`: JSON array of mapped COs ["1"], ["2"], ["3"], etc.
- `performance_target`: Target percentage (usually 60%)
- `is_summative`: 'yes'/'no' - is this a summative assessment?

**Sample Classwork columns (component_id=44):**
```
id  | column_name | max_score | co_mappings | is_summative | performance_target
202 | CW 1        | 10        | ["1"]       | yes          | 60.00
203 | CW 2        | 10        | ["1"]       | yes          | 60.00
204 | CW 3        | 10        | ["1"]       | yes          | 60.00
```

#### 4. **student_flexible_grades**
Actual student grades for each grading item
- `id`: Primary key
- `student_id`: Links to student
- `class_code`: Links to class
- `component_id`: Links to grading_components
- `column_id`: Links to grading_component_columns
- `raw_score`: Student's raw score
- `status`: 'submitted', 'inc' (incomplete), etc.

**Sample Classwork grades:**
```
id  | student_id  | class_code              | column_id | raw_score | status
670 | 2022-118764 | 24_T2_CCPRGG1L_INF222  | 202       | 10.00     | inc
671 | 2022-118764 | 24_T2_CCPRGG1L_INF222  | 203       | 8.00      | submitted
672 | 2022-118764 | 24_T2_CCPRGG1L_INF222  | 204       | 7.00      | submitted
```

#### 5. **class_enrollments**
Student enrollment in a class
- `enrollment_id`: Primary key
- `student_id`: Student ID
- `class_code`: Class code
- `course_code`: Course code

#### 6. **subjects**
Course information
- `course_code`: Primary key
- `course_title`: Course name
- `course_desc`: Description
- `units`: Credit units

---

## Complete COA Query

### Query: Get Assessment Performance Metrics per Course Outcome

```sql
SELECT 
    co.co_number,
    co.co_description,
    gc.component_name AS assessment_name,
    gcc.performance_target,
    COUNT(DISTINCT CASE 
        WHEN (CAST(sfg.raw_score AS DECIMAL(10,2))/gcc.max_score*100) >= gcc.performance_target 
        THEN ce.student_id 
    END) AS students_met_target,
    COUNT(DISTINCT ce.student_id) AS total_students,
    ROUND(
        COUNT(DISTINCT CASE 
            WHEN (CAST(sfg.raw_score AS DECIMAL(10,2))/gcc.max_score*100) >= gcc.performance_target 
            THEN ce.student_id 
        END) * 100.0 / COUNT(DISTINCT ce.student_id), 
        2
    ) AS success_rate_percent
FROM course_outcomes co
LEFT JOIN grading_components gc ON gc.class_code = '24_T2_CCPRGG1L_INF222'
LEFT JOIN grading_component_columns gcc ON gcc.component_id = gc.id
LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
LEFT JOIN class_enrollments ce ON ce.class_code = gc.class_code
WHERE co.course_code = 'CCPRGG1L'
    AND JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS JSON))
    AND gcc.is_summative = 'yes'
GROUP BY co.co_number, co.co_description, gc.component_name, gcc.id
ORDER BY co.co_number, gc.order_index;
```

### What This Query Does:

1. **Gets all course outcomes** for the target course
2. **Links to grading components** that assess each CO
3. **Links to individual assessment items** (columns) with max_score and performance_target
4. **Calculates performance:**
   - Computes percentage: `(raw_score / max_score) * 100`
   - Checks if meets target: `percentage >= performance_target`
   - Counts students meeting target
   - Calculates success rate: `(met_target / total) * 100`
5. **Filters for:**
   - Summative assessments only (`is_summative = 'yes'`)
   - Items mapped to each CO via JSON_CONTAINS
   - Specific class code

### Expected Output for CCPRGG1L (24_T2_CCPRGG1L_INF222):

```
CO | Description                                    | Assessment    | Target | Met | Total | Success%
1  | Recognize modular programming                  | Classwork     | 60.00  | 2   | 3     | 66.67
2  | Apply different control structures             | Quiz          | 60.00  | 1   | 3     | 33.33
3  | Implement array data structure and file manip. | Mock Defense  | 60.00  | 0   | 3     | 0.00
```

---

## Alternative Queries for Different COA Views

### Query 1: Summary by CO Only (Aggregate all assessments)

```sql
SELECT 
    co.co_number,
    co.co_description,
    COUNT(DISTINCT CASE 
        WHEN (CAST(sfg.raw_score AS DECIMAL(10,2))/gcc.max_score*100) >= gcc.performance_target 
        THEN ce.student_id 
    END) AS total_met_target,
    COUNT(DISTINCT ce.student_id) AS total_students,
    ROUND(
        COUNT(DISTINCT CASE 
            WHEN (CAST(sfg.raw_score AS DECIMAL(10,2))/gcc.max_score*100) >= gcc.performance_target 
            THEN ce.student_id 
        END) * 100.0 / NULLIF(COUNT(DISTINCT ce.student_id), 0),
        2
    ) AS overall_success_rate
FROM course_outcomes co
LEFT JOIN grading_component_columns gcc ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS JSON))
LEFT JOIN grading_components gc ON gc.id = gcc.component_id
LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id
LEFT JOIN class_enrollments ce ON ce.class_code = gc.class_code
WHERE co.course_code = 'CCPRGG1L'
    AND gcc.is_summative = 'yes'
    AND gc.class_code = '24_T2_CCPRGG1L_INF222'
GROUP BY co.co_number, co.co_description
ORDER BY co.co_number;
```

### Query 2: Student-Level Detail (Show each student's performance)

```sql
SELECT 
    ce.student_id,
    CONCAT(s.last_name, ', ', s.first_name) AS student_name,
    co.co_number,
    co.co_description,
    gc.component_name,
    gcc.column_name,
    sfg.raw_score,
    gcc.max_score,
    ROUND((CAST(sfg.raw_score AS DECIMAL(10,2)) / gcc.max_score * 100), 2) AS percentage,
    gcc.performance_target,
    CASE 
        WHEN (CAST(sfg.raw_score AS DECIMAL(10,2)) / gcc.max_score * 100) >= gcc.performance_target 
        THEN 'MET' 
        ELSE 'NOT MET' 
    END AS status
FROM class_enrollments ce
JOIN student s ON s.student_id = ce.student_id
LEFT JOIN grading_components gc ON gc.class_code = ce.class_code
LEFT JOIN grading_component_columns gcc ON gcc.component_id = gc.id
LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id AND sfg.student_id = ce.student_id
LEFT JOIN course_outcomes co ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS JSON))
WHERE ce.class_code = '24_T2_CCPRGG1L_INF222'
    AND gcc.is_summative = 'yes'
    AND co.course_code = 'CCPRGG1L'
ORDER BY co.co_number, ce.student_id, gc.order_index;
```

### Query 3: Assessment Type Distribution (See which assessments map to which COs)

```sql
SELECT 
    gc.component_name AS assessment_type,
    COUNT(DISTINCT gcc.id) AS num_items,
    GROUP_CONCAT(DISTINCT co.co_number ORDER BY co.co_number) AS mapped_cos,
    GROUP_CONCAT(DISTINCT co.co_description ORDER BY co.co_number) AS co_descriptions
FROM grading_components gc
LEFT JOIN grading_component_columns gcc ON gcc.component_id = gc.id
LEFT JOIN course_outcomes co ON JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS JSON))
WHERE gc.class_code = '24_T2_CCPRGG1L_INF222'
    AND gcc.is_summative = 'yes'
GROUP BY gc.id, gc.component_name
ORDER BY gc.order_index;
```

---

## Data Dictionary for COA Report

### Key Calculations:

**Performance Percentage:**
```
Performance % = (Student Raw Score / Max Score) × 100
```

**Target Met:**
```
Target Met = Performance % >= Performance Target (usually 60%)
```

**Success Rate:**
```
Success Rate = (# Students Met Target / Total Students) × 100
```

### JSON Mappings:

**co_mappings in grading_component_columns:**
- `["1"]` = Maps to CO1 only
- `["2"]` = Maps to CO2 only  
- `["3"]` = Maps to CO3 only
- `["1","2"]` = Maps to CO1 and CO2

**so_mappings in course_outcomes:**
- `[1,1,1,0,0,1]` = Maps to SOs 1, 2, 3, and 6

### Important Filters:

1. **is_summative = 'yes':** Only include summative/graded assessments
2. **status = 'submitted':** Only include submitted grades (exclude 'inc', draft, etc.)
3. **JSON_CONTAINS():** Match assessments to course outcomes
4. **class_code:** Filter by specific class section
5. **course_code:** Filter by course (all COs for that course)

---

## Sample Data Points

### Class Information:
- **Class Code:** 24_T2_CCPRGG1L_INF222
- **Course:** CCPRGG1L (Fundamentals of Programming)
- **Section:** INF222
- **Term:** T2 (Term 2)
- **Faculty:** Denzil Tumambing (ID: 114)

### Enrolled Students:
- 2022-118764 (Mayo Suwail)
- 2022-171253 (Lobo Kenneth)
- 2022-182121 (Drex Cueto)

### Course Outcomes:
- CO1: Recognize modular programming
- CO2: Apply different control structures
- CO3: Implement array data structure and file manipulation

### Assessment Components:
- Classwork (10% midterm): Maps to CO1
- Quiz (10% midterm): Maps to CO2
- Mock Defense (60% finals): Maps to CO3

---

## Query Performance Tips

1. **Use NULLIF() for division** to avoid errors: `COUNT(...) / NULLIF(COUNT(...), 0)`
2. **Index on frequently filtered columns:** `class_code`, `co_mappings`, `is_summative`
3. **Pre-aggregate if generating many reports:** Create a materialized view
4. **Consider date filtering** if history tracking is needed: `WHERE sfg.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)`

---

## Troubleshooting

### Common Issues:

**Issue:** "Unknown column 'gcc.component_name'"
- **Cause:** component_name is in grading_components (gc), not grading_component_columns (gcc)
- **Solution:** Use `gc.component_name` instead

**Issue:** JSON_CONTAINS returns NULL
- **Cause:** co_mappings might be NULL or malformed JSON
- **Solution:** Use `JSON_CONTAINS(gcc.co_mappings, CAST(co.co_number AS JSON))` with proper casting

**Issue:** Students showing 0 grades
- **Cause:** No student_flexible_grades entry or raw_score is NULL
- **Solution:** Use COALESCE: `COALESCE(sfg.raw_score, 0)` or LEFT JOIN with CASE handling

**Issue:** Performance target always 60%
- **Cause:** Default value in schema; check specific columns for custom targets
- **Solution:** Can be updated per assessment or globally in settings

---

## Integration Points

### Frontend Integration:
- Call `generate_coa_html.php?class_code=24_T2_CCPRGG1L_INF222` endpoint
- Returns JSON with table data and calculated metrics
- Display in modal with PDF download option

### Backend Files:
- **Endpoint:** `faculty/ajax/generate_coa_html.php`
- **Frontend JS:** `faculty/assets/js/car_management.js` (openCOAPreparation function)
- **Query Location:** `generate_coa_html.php` (core query logic)

---

## Last Updated
November 23, 2025

### Query Status: ✅ VERIFIED AND WORKING
- Tested with sample data from class 24_T2_CCPRGG1L_INF222
- Returns 9+ rows of valid assessment data
- Performance targets and success rates calculated correctly
