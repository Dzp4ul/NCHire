<?php

return [
    'scoring_version' => '1.0.0',
    'weights' => [
        'education' => 25.0,
        'experience' => 25.0,
        'skills' => 20.0,
        'certifications' => 10.0,
        'requirements' => 20.0,
    ],
    'labels' => [
        ['minimum' => 90, 'label' => 'Highly Qualified'],
        ['minimum' => 80, 'label' => 'Very Qualified'],
        ['minimum' => 70, 'label' => 'Qualified'],
        ['minimum' => 60, 'label' => 'Partially Qualified'],
        ['minimum' => 0, 'label' => 'Does Not Fully Meet Requirements'],
    ],
    'education_levels' => [
        'other' => 0,
        'high_school' => 1,
        'associate' => 2,
        'bachelor' => 3,
        'master' => 4,
        'doctorate' => 5,
    ],
    'teaching_terms' => [
        'teacher', 'teaching', 'instructor', 'faculty', 'lecturer',
        'professor', 'tutor', 'trainer', 'classroom', 'academic',
    ],
    'phrase_aliases' => [
        'information technology' => 'computing',
        'computer science' => 'computing',
        'information systems' => 'computing',
        'software engineering' => 'computing',
        'hospitality management' => 'hospitality',
        'hotel restaurant management' => 'hospitality',
        'teacher education' => 'education',
        'professional education' => 'education',
        'licensed professional teacher' => 'teaching license',
        'licensure examination for teachers' => 'teaching license',
        'let passer' => 'teaching license',
    ],
    'stop_words' => [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
        'in', 'into', 'is', 'of', 'on', 'or', 'the', 'to', 'with',
        'degree', 'course', 'program', 'required', 'preferred', 'relevant',
        'experience', 'qualification', 'qualifications', 'skill', 'skills',
    ],
];
