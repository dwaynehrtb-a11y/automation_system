<?php
// Test the exact normalization logic
$test_names = ['Quiz', 'Quizzes', 'quiz', 'quizzes', 'QUIZ', 'QUIZZES', 'Classwork', 'Laboratory Exam'];

foreach ($test_names as $name) {
    $assessment_key = strtolower(trim($name));
    if (strpos($assessment_key, 'quiz') !== false) {
        $assessment_key = 'quiz';
    }
    $display_name = $assessment_key === 'quiz' ? 'Quiz' : ucfirst($assessment_key);
    echo "'{$name}' -> key: '{$assessment_key}' -> display: '{$display_name}'\n";
}
?>
