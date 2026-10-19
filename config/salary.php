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
            'label' => "Master's — Completed",
            'hourly_rate' => 200.00,
        ],
        'master_ongoing' => [
            'label' => "Master's — Ongoing",
            'hourly_rate' => 150.00,
        ],
    ],
    // Fallback bands used when the database-backed graduate rate table is not
    // available (for example, in isolated unit tests). Live requests use the
    // same bands from graduate_unit_rate_rules.
    'graduate_unit_rate_rules' => [
        'master_ongoing' => [
            [
                'minimum_units' => 0,
                'maximum_units' => 17,
                'hourly_rate' => 150.00,
            ],
            [
                'minimum_units' => 18,
                'maximum_units' => null,
                'hourly_rate' => 170.00,
            ],
        ],
    ],
    'full_time_salary_grades' => [
        // Generic Instructor postings are treated as the entry-level Instructor I classification.
        'instructor_i' => 'SG13',
    ],
    'projection_disclaimer' => 'Guide only—not the actual salary. Final compensation may vary by individual and is subject to profile and credential verification.',
];
