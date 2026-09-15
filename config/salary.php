<?php

/**
 * Central projected-salary configuration for NCHire.
 *
 * Qualification rates are hourly. Permanent/Full-Time postings expose only
 * their Salary Grade; NCHire does not publish the underlying peso amount.
 */
return [
    'currency_symbol' => '₱',
    'part_time_rates' => [
        'doctorate_completed' => [
            'label' => 'Doctorate - Completed',
            'hourly_rate' => 220.00,
        ],
        'master_completed' => [
            'label' => "Master's - Completed",
            'hourly_rate' => 200.00,
        ],
        'master_ongoing' => [
            'label' => "Master's - Ongoing",
            'hourly_rate' => 150.00,
        ],
    ],
    'full_time_salary_grades' => [
        // Generic Instructor postings are treated as the entry-level Instructor I classification.
        'instructor_i' => 'SG13',
    ],
    'projection_disclaimer' => 'Guide only—not the actual salary. Final compensation may vary by individual and is subject to profile and credential verification.',
];
