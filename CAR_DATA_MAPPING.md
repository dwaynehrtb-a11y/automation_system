# CAR (Course Assessment Report) Data Mapping

## Overview
This document outlines all CAR data fields that are captured in the form and displayed in the preview/PDF.

## Data Fields Captured

### Core CAR Information (from car_data table)
- **teaching_strategies**: Text - List of teaching strategies used in class
- **intervention_activities**: JSON Array - Intervention activities with structure:
  ```json
  [
    {
      "description": "Activity description",
      "students": <number>
    }
  ]
  ```
- **problems_encountered**: Text - Brief description of problems faced
- **actions_taken**: Text - Actions taken to address problems
- **proposed_actions** / **proposed_improvements**: Text - Proposed improvements for the course
- **status**: draft | completed

### CO-Specific Recommendations (from car_recommendations table)
- **CO Recommendations**: One recommendation per Course Outcome
  - Links: car_id → co_number → recommendation text

### Supporting Data (auto-populated)
- **Grade Distribution**: From term_grades table
- **CO Performance**: From grading_component_columns + student_flexible_grades
- **Incomplete/Dropped Students**: From term_grades + lacking_requirements
- **Course Outcomes**: From course_outcomes table
- **CO-SO Mapping**: From co_so_mapping table

---

## Preview/PDF Display Layout

### Page 1: Course Information & Grade Distribution
- **Header**: Logo, class code, term, academic year
- **Course Details**: 
  - Course code, section, class size
  - Course title & description
  - Instructor name
  - Signature area
- **Grade Distribution Table**: Shows count of each grade (4.00, 3.50... INC, DRP, etc.)
- **CO-SO Map**: Shows mapping between all Course Outcomes and Student Outcomes (SO1-SO6)

### Page 2: Assessment & Implementation Details
- **Course Learning Outcomes Assessment**: Table showing:
  - CO number
  - Summative assessment name
  - Performance target %
  - Success rate % [count of students met target]
- **Incomplete/Dropped Students**: List of students with INC status and lacking requirements
- **Teaching Strategies**: Numbered list of strategies (parsed from teaching_strategies field)
- **Intervention Activities**: Table with:
  - Activity description
  - Number of students involved (for each individual intervention)
- **Problems Encountered & Actions Taken**: Two-column layout showing:
  - Brief problem description
  - Actions taken to address (bullet-pointed)

### Page 3: Recommendations & Improvements
- **CO-Specific Recommendations**: Table showing recommendation for each Course Outcome
- **Proposed Actions for Course Improvement**: Numbered list of proposed improvements

---

## Data Validation

### Required Fields
- Class code (auto-selected)
- At least one teaching strategy
- At least one intervention activity (optional, but displays "None" if empty)

### Optional Fields
- Problems encountered
- Actions taken
- Proposed improvements
- CO recommendations (one per outcome)

### Data Format Rules
- **Intervention Activities**: Must be valid JSON array with description and students fields
- **Teaching Strategies**: Line-separated, can include dash/em-dash for description separator
- **Actions Taken**: Line-separated, strips leading bullets/symbols for display
- **CO Recommendations**: Free text, max 500 chars per recommendation

---

## Database Schema References

### car_data
```sql
- car_id (PRIMARY)
- class_id (FK)
- teaching_strategies (LONGTEXT)
- intervention_activities (JSON)
- problems_encountered (TEXT)
- actions_taken (TEXT)
- proposed_actions (TEXT)
- status (ENUM: draft, completed)
- created_at, updated_at
```

### car_recommendations
```sql
- id (PRIMARY)
- car_id (FK to car_data)
- co_number (INT)
- recommendation (TEXT)
```

### car_metadata (Alternative source)
```sql
- id (PRIMARY)
- class_code (unique)
- teaching_strategies
- intervention_activities
- problems_encountered
- actions_taken
- proposed_actions
```

---

## Preview Generation Flow

1. **Fetch Class Info**: Get class_id, course_code, faculty name
2. **Fetch CAR Data**: Check car_metadata first, then car_data
3. **Fetch CO Recommendations**: Query car_recommendations table using car_id
4. **Parse Interventions**: Decode JSON intervention_activities and extract each with student count
5. **Calculate Performance**: Query grading_components + student grades for CO success rates
6. **Build HTML**: Construct multi-page HTML with all data
7. **Return JSON**: Include HTML content for preview/PDF conversion

---

## Known Limitations & Enhancements

### Current Implementation
✅ All core CAR fields captured and displayed
✅ CO-specific recommendations included
✅ Intervention activities shown with individual student counts
✅ Grade distribution displayed
✅ CO performance metrics calculated

### Future Enhancements
- [ ] Add sub-learning outcomes (SLO) tracking
- [ ] Enhanced intervention tracking with dates
- [ ] Student learning progress visualization
- [ ] Historical CAR comparison (year-over-year)
- [ ] Export to different formats (Excel, PDF)
