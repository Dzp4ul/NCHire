<?php
/**
 * Profile Form Handlers
 * Handles saving profile data
 */

function handlePersonalInfoSave($conn, $user_id, $post_data) {
    if (empty($post_data['applicant_fname']) || empty($post_data['applicant_lname']) || empty($post_data['applicant_email'])) {
        return ['success' => false, 'message' => 'Please fill in all required fields.'];
    }

    $fname = $conn->real_escape_string($post_data['applicant_fname']);
    $lname = $conn->real_escape_string($post_data['applicant_lname']);
    $email = $conn->real_escape_string($post_data['applicant_email']);
    $phone = isset($post_data['applicant_num']) ? $conn->real_escape_string($post_data['applicant_num']) : '';
    $address = isset($post_data['applicant_address']) ? $conn->real_escape_string($post_data['applicant_address']) : '';

    // Validate phone
    if (!empty($phone) && !preg_match('/^09[0-9]{9}$/', $phone)) {
        return ['success' => false, 'message' => 'Invalid phone number format. Must be 11 digits starting with 09.'];
    }

    $sql = "UPDATE applicants SET first_name = ?, last_name = ?, applicant_email = ?, contact_number = ?, address = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }

    $stmt->bind_param("sssssi", $fname, $lname, $email, $phone, $address, $user_id);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Personal information updated successfully.'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error updating: ' . $error];
    }
}

function handleEducationSave($conn, $user_id, $post_data) {
    if (empty($post_data['degree']) || empty($post_data['field_of_study']) || empty($post_data['institution'])) {
        return ['success' => false, 'message' => 'Please fill in all required fields.'];
    }

    $sql = "INSERT INTO user_education (user_id, degree, field_of_study, institution, start_year, end_year, gpa) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $gpa = $post_data['gpa'] ?? '';
    $stmt->bind_param("isssiis", $user_id, $post_data['degree'], $post_data['field_of_study'], $post_data['institution'], $post_data['start_year'], $post_data['end_year'], $gpa);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Education added successfully.'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error: ' . $error];
    }
}

function handleExperienceSave($conn, $user_id, $post_data) {
    if (empty($post_data['job_title']) || empty($post_data['company']) || empty($post_data['start_date'])) {
        return ['success' => false, 'message' => 'Please fill in all required fields.'];
    }

    $sql = "INSERT INTO user_experience (user_id, job_title, company, location, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $location = $post_data['location'] ?? '';
    $end_date = $post_data['end_date'] ?? null;
    $description = $post_data['description'] ?? '';
    $stmt->bind_param("issssss", $user_id, $post_data['job_title'], $post_data['company'], $location, $post_data['start_date'], $end_date, $description);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Experience added successfully.'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error: ' . $error];
    }
}

function handleSkillsSave($conn, $user_id, $post_data) {
    if (empty($post_data['skill_name'])) {
        return ['success' => false, 'message' => 'Please enter a skill name.'];
    }

    $sql = "INSERT INTO user_skills (user_id, skill_name, skill_category, skill_level) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $category = $post_data['skill_category'] ?? 'other';
    $level = $post_data['skill_level'] ?? 1;
    $stmt->bind_param("issi", $user_id, $post_data['skill_name'], $category, $level);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Skill added successfully.'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error: ' . $error];
    }
}
?>
