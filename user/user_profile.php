<?php

session_start();
// Database connection
$servername = "localhost";
$username = "root";
$password = "12345678";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = '';
$error_message = '';

// Debug: Log POST data for troubleshooting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data received: " . print_r($_POST, true));
}

// Create education table if it doesn't exist
$create_education_table = "CREATE TABLE IF NOT EXISTS user_education (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    degree VARCHAR(255) NOT NULL,
    field_of_study VARCHAR(255) NOT NULL,
    institution VARCHAR(255) NOT NULL,
    start_year INT NOT NULL,
    end_year INT NOT NULL,
    gpa VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (!$conn->query($create_education_table)) {
    error_log("Error creating education table: " . $conn->error);
}

// Handle Education form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveEducation'])) {
    // Validate required fields
    if (empty($_POST['ed_degree']) || empty($_POST['ed_fs']) || empty($_POST['ed_ins']) || 
        empty($_POST['ed_sy']) || empty($_POST['ed_ey'])) {
        $error_message = "Please fill in all required fields.";
    } else {
        $ed_degree = $conn->real_escape_string($_POST['ed_degree']);
        $ed_fs = $conn->real_escape_string($_POST['ed_fs']);
        $ed_ins = $conn->real_escape_string($_POST['ed_ins']);
        $ed_sy = (int)$_POST['ed_sy'];
        $ed_ey = (int)$_POST['ed_ey'];
        $ed_gpa = isset($_POST['ed_gpa']) ? $conn->real_escape_string($_POST['ed_gpa']) : '';
        // Get user ID from session
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            $error_message = "User not logged in properly. Please log in again.";
            header("Location: ../index.php");
            exit();
        }

        $sql_insert = "INSERT INTO user_education (user_id, degree, field_of_study, institution, start_year, end_year, gpa) 
                       VALUES ('$user_id', '$ed_degree', '$ed_fs', '$ed_ins', '$ed_sy', '$ed_ey', '$ed_gpa')";

        if ($conn->query($sql_insert) === TRUE) {
            $success_message = "Education added successfully.";
        } else {
            $error_message = "Error adding education: " . $conn->error;
            error_log("Education insert error: " . $conn->error . " SQL: " . $sql_insert);
        }
    }
}

// Create work experience table if it doesn't exist
$create_experience_table = "CREATE TABLE IF NOT EXISTS user_experience (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_title VARCHAR(255) NOT NULL,
    company VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE,
    description TEXT,
    is_current BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (!$conn->query($create_experience_table)) {
    error_log("Error creating experience table: " . $conn->error);
}

// Handle Experience form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveExperience'])) {
    // Validate required fields
    if (empty($_POST['job_title']) || empty($_POST['work_comp']) || empty($_POST['start_date'])) {
        $error_message = "Please fill in all required fields.";
    } else {
        $job_title = $conn->real_escape_string($_POST['job_title']);
        $work_comp = $conn->real_escape_string($_POST['work_comp']);
        $work_loc = isset($_POST['work_loc']) ? $conn->real_escape_string($_POST['work_loc']) : '';
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = isset($_POST['is_current']) ? NULL : (isset($_POST['end_date']) ? $conn->real_escape_string($_POST['end_date']) : NULL);
        $work_descript = isset($_POST['work_descript']) ? $conn->real_escape_string($_POST['work_descript']) : '';
        $is_current = isset($_POST['is_current']) ? 1 : 0;
        // Get user ID from session
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            $error_message = "User not logged in properly. Please log in again.";
            header("Location: ../index.php");
            exit();
        }

        // Convert date format from YYYY-MM to YYYY-MM-01 for MySQL DATE type
        $start_date_formatted = $start_date . '-01';
        $end_date_formatted = $end_date ? $end_date . '-01' : NULL;

        $sql_insert = "INSERT INTO user_experience (user_id, job_title, company, location, start_date, end_date, description, is_current) 
                       VALUES ('$user_id', '$job_title', '$work_comp', '$work_loc', '$start_date_formatted', " . 
                       ($end_date_formatted ? "'$end_date_formatted'" : "NULL") . ", '$work_descript', '$is_current')";

        if ($conn->query($sql_insert) === TRUE) {
            $success_message = "Work experience added successfully.";
        } else {
            $error_message = "Error adding work experience: " . $conn->error;
            error_log("Experience insert error: " . $conn->error . " SQL: " . $sql_insert);
        }
    }
}

// Create user_skills table if it doesn't exist
$create_skills_table = "CREATE TABLE IF NOT EXISTS user_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill_name VARCHAR(255) NOT NULL,
    proficiency_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'Beginner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES applicants(id) ON DELETE CASCADE
)";
$conn->query($create_skills_table);

// Add profile_picture column to applicants table if it doesn't exist
$check_column = "SHOW COLUMNS FROM applicants LIKE 'profile_picture'";
$result = $conn->query($check_column);
if ($result->num_rows == 0) {
    $add_column = "ALTER TABLE applicants ADD COLUMN profile_picture VARCHAR(255) NULL";
    $conn->query($add_column);
}

// Ensure required columns exist in applicants table
$required_columns = [
    'contact_number' => 'VARCHAR(20) NULL',
    'address' => 'TEXT NULL'
];

foreach ($required_columns as $column => $definition) {
    $check = "SHOW COLUMNS FROM applicants LIKE '$column'";
    $result = $conn->query($check);
    if ($result->num_rows == 0) {
        $add = "ALTER TABLE applicants ADD COLUMN $column $definition";
        if (!$conn->query($add)) {
            error_log("Error adding column $column: " . $conn->error);
        }
    }
}

// Handle Personal Information form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['savePersonal'])) {
    // Validate required fields
    if (empty($_POST['applicant_fname']) || empty($_POST['applicant_lname']) || empty($_POST['applicant_email'])) {
        $error_message = "Please fill in all required fields (First Name, Last Name, Email).";
    } else {
        $fname = $conn->real_escape_string($_POST['applicant_fname']);
        $lname = $conn->real_escape_string($_POST['applicant_lname']);
        $email = $conn->real_escape_string($_POST['applicant_email']);
        $phone = isset($_POST['applicant_num']) ? $conn->real_escape_string($_POST['applicant_num']) : '';
        $address = isset($_POST['applicant_address']) ? $conn->real_escape_string($_POST['applicant_address']) : '';
        
        // Validate Philippine phone number format (must be 11 digits starting with 09)
        if (!empty($phone) && !preg_match('/^09[0-9]{9}$/', $phone)) {
            $error_message = "Invalid phone number format. Must be 11 digits starting with 09 (e.g., 09123456789).";
        } else {
            // Get user ID from session
            $user_id = $_SESSION['user_id'] ?? null;
            if (!$user_id) {
                $error_message = "User not logged in properly. Please log in again.";
                header("Location: ../index.php");
                exit();
            }

            // Use prepared statement for security
            $sql_update = "UPDATE applicants SET 
                           first_name = ?, 
                           last_name = ?, 
                           applicant_email = ?, 
                           contact_number = ?, 
                           address = ? 
                           WHERE id = ?";
            
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("sssssi", $fname, $lname, $email, $phone, $address, $user_id);
                
                if ($stmt_update->execute()) {
                    $success_message = "Personal information updated successfully.";
                    // Also update session data
                    $_SESSION['first_name'] = $fname;
                    $_SESSION['user_email'] = $email;
                } else {
                    $error_message = "Error updating personal information: " . $stmt_update->error;
                    error_log("Personal info update error: " . $stmt_update->error);
                }
                $stmt_update->close();
            } else {
                $error_message = "Error preparing update statement: " . $conn->error;
                error_log("Prepare error: " . $conn->error);
            }
        }
    }
}

// Handle Skill form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveSkill'])) {
    // Validate required fields
    if (empty($_POST['skill_name']) || empty($_POST['skill_category']) || empty($_POST['skill_level']) || $_POST['skill_level'] == '0') {
        $error_message = "Please fill in all required fields and select a skill level.";
    } else {
        $skill_name = $conn->real_escape_string($_POST['skill_name']);
        $skill_category = $conn->real_escape_string($_POST['skill_category']);
        $skill_level = (int)$_POST['skill_level'];
        // Get user ID from session
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            $error_message = "User not logged in properly. Please log in again.";
            header("Location: ../index.php");
            exit();
        }

        // Validate skill level is between 1 and 5
        if ($skill_level < 1 || $skill_level > 5) {
            $error_message = "Please select a valid skill level (1-5).";
        } else {
            $sql_insert = "INSERT INTO user_skills (user_id, skill_name, skill_category, skill_level) 
                           VALUES ('$user_id', '$skill_name', '$skill_category', '$skill_level')";

            if ($conn->query($sql_insert) === TRUE) {
                $success_message = "Skill added successfully.";
            } else {
                $error_message = "Error adding skill: " . $conn->error;
                error_log("Skill insert error: " . $conn->error . " SQL: " . $sql_insert);
            }
        }
    }
}

// Get user ID from session
$current_user_id = $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    header("Location: ../index.php");
    exit();
}

// Fetch applicant info using the logged-in user's ID
$sql = "SELECT * FROM applicants WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $applicant = $result->fetch_assoc();
    // Map applicants table fields to expected field names
    $applicant['applicant_fname'] = $applicant['first_name'] ?? '';
    $applicant['applicant_lname'] = $applicant['last_name'] ?? '';
    $applicant['applicant_email'] = $applicant['applicant_email'] ?? '';
    $applicant['applicant_num'] = $applicant['contact_number'] ?? '';
    $applicant['applicant_address'] = $applicant['address'] ?? '';
    $applicant['applicant_profile'] = $applicant['profile_picture'] ?? '';
} else {
    $applicant = [
        'applicant_fname' => '',
        'applicant_lname' => '',
        'applicant_email' => '',
        'applicant_num' => '',
        'applicant_address' => '',
        'applicant_profile' => ''
    ];
}
$stmt->close();

// Fetch education data
$education_sql = "SELECT * FROM user_education WHERE user_id = ? ORDER BY end_year DESC";
$education_stmt = $conn->prepare($education_sql);
$education_stmt->bind_param("i", $current_user_id);
$education_stmt->execute();
$education_result = $education_stmt->get_result();
$education_data = [];
if ($education_result && $education_result->num_rows > 0) {
    while ($row = $education_result->fetch_assoc()) {
        $education_data[] = $row;
    }
}
$education_stmt->close();

// Fetch work experience data
$experience_sql = "SELECT * FROM user_experience WHERE user_id = ? ORDER BY start_date DESC";
$experience_stmt = $conn->prepare($experience_sql);
$experience_stmt->bind_param("i", $current_user_id);
$experience_stmt->execute();
$experience_result = $experience_stmt->get_result();
$experience_data = [];
if ($experience_result && $experience_result->num_rows > 0) {
    while ($row = $experience_result->fetch_assoc()) {
        $experience_data[] = $row;
    }
}
$experience_stmt->close();

// Fetch skills data
$skills_sql = "SELECT * FROM user_skills WHERE user_id = ? ORDER BY skill_category, skill_name";
$skills_stmt = $conn->prepare($skills_sql);
$skills_stmt->bind_param("i", $current_user_id);
$skills_stmt->execute();
$skills_result = $skills_stmt->get_result();
$skills_data = [];
if ($skills_result && $skills_result->num_rows > 0) {
    while ($row = $skills_result->fetch_assoc()) {
        $skills_data[] = $row;
    }
}
$skills_stmt->close();
?>


<!DOCTYPE html>
<html lang="en">
<head><script src="https://static.readdy.ai/static/e.js"></script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NCHire - My Profile</title>
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" rel="stylesheet">
<style>
:where([class^="ri-"])::before { content: "\f3c2"; }
</style>
<script>
tailwind.config = {
theme: {
extend: {
colors: {
primary: '#1e40af',
secondary: '#f59e0b'
},
borderRadius: {
'none': '0px',
'sm': '4px',
DEFAULT: '8px',
'md': '12px',
'lg': '16px',
'xl': '20px',
'2xl': '24px',
'3xl': '32px',
'full': '9999px',
'button': '8px'
}
}
}
}
</script>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Custom Toast Notification Container -->
<div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

<!-- Save Confirmation Modal -->
<div id="saveConfirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center">
  <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
    <div class="p-6">
      <div class="flex items-center justify-center w-12 h-12 mx-auto bg-blue-100 rounded-full mb-4">
        <i class="ri-save-line text-blue-600 text-2xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Save Changes</h3>
      <p class="text-sm text-gray-600 text-center mb-6">Are you sure you want to save these changes to your profile information?</p>
      <div class="flex gap-3">
        <button onclick="closeSaveModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
          Cancel
        </button>
        <button onclick="confirmSave()" class="flex-1 px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
          Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Custom toast notification function
function showToast(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  
  // Determine colors based on type
  const colors = {
    success: {
      bg: 'bg-green-50',
      border: 'border-green-500',
      icon: 'ri-checkbox-circle-fill',
      iconColor: 'text-green-500',
      textColor: 'text-green-800'
    },
    error: {
      bg: 'bg-red-50',
      border: 'border-red-500',
      icon: 'ri-error-warning-fill',
      iconColor: 'text-red-500',
      textColor: 'text-red-800'
    },
    warning: {
      bg: 'bg-yellow-50',
      border: 'border-yellow-500',
      icon: 'ri-alert-fill',
      iconColor: 'text-yellow-500',
      textColor: 'text-yellow-800'
    }
  };
  
  const style = colors[type] || colors.success;
  
  // Create toast element
  const toast = document.createElement('div');
  toast.className = `${style.bg} border-l-4 ${style.border} p-4 rounded-lg shadow-lg max-w-md transform transition-all duration-300 opacity-0 translate-x-full`;
  toast.innerHTML = `
    <div class="flex items-center">
      <i class="${style.icon} ${style.iconColor} text-xl mr-3"></i>
      <p class="${style.textColor} font-medium">${message}</p>
      <button onclick="this.parentElement.parentElement.remove()" class="ml-auto ${style.textColor} hover:opacity-70">
        <i class="ri-close-line text-xl"></i>
      </button>
    </div>
  `;
  
  container.appendChild(toast);
  
  // Trigger animation
  setTimeout(() => {
    toast.classList.remove('opacity-0', 'translate-x-full');
  }, 10);
  
  // Auto remove after 5 seconds
  setTimeout(() => {
    toast.classList.add('opacity-0', 'translate-x-full');
    setTimeout(() => toast.remove(), 300);
  }, 5000);
}

// Show notifications if present
<?php if ($success_message): ?>
  showToast('<?php echo addslashes($success_message); ?>', 'success');
<?php endif; ?>

<?php if ($error_message): ?>
  showToast('<?php echo addslashes($error_message); ?>', 'error');
<?php endif; ?>

<?php if (isset($_GET['education_added'])): ?>
  showToast('Education added successfully.', 'success');
<?php endif; ?>

// Save confirmation modal functions
function showSaveModal() {
  document.getElementById('saveConfirmModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeSaveModal() {
  document.getElementById('saveConfirmModal').classList.add('hidden');
  document.body.style.overflow = '';
}

function confirmSave() {
  closeSaveModal();
  // Validate phone number before submitting
  const phoneInput = document.querySelector('input[name="applicant_num"]');
  if (phoneInput && phoneInput.value && !/^09[0-9]{9}$/.test(phoneInput.value.trim())) {
    showToast('Invalid phone number! Must be 11 digits starting with 09 (e.g., 09123456789)', 'error');
    return;
  }
  
  // Copy values from visible inputs to hidden form
  document.getElementById('form_fname').value = document.querySelector('input[name="applicant_fname"]').value;
  document.getElementById('form_lname').value = document.querySelector('input[name="applicant_lname"]').value;
  document.getElementById('form_email').value = document.querySelector('input[name="applicant_email"]').value;
  document.getElementById('form_phone').value = document.querySelector('input[name="applicant_num"]').value;
  document.getElementById('form_address').value = document.querySelector('textarea[name="applicant_address"]').value;
  
  // Submit the form
  document.getElementById('personalInfoForm').submit();
}

// Handle Save Changes button click
document.addEventListener('DOMContentLoaded', function() {
  const saveBtn = document.getElementById('savePersonalBtn');
  if (saveBtn) {
    saveBtn.addEventListener('click', function(e) {
      e.preventDefault();
      showSaveModal();
    });
  }
});
</script>


<!-- Hidden form for personal information -->
<form id="personalInfoForm" method="POST" action="" style="display: none;">
  <input type="hidden" name="savePersonal" value="1">
  <input type="hidden" name="applicant_fname" id="form_fname">
  <input type="hidden" name="applicant_lname" id="form_lname">
  <input type="hidden" name="applicant_email" id="form_email">
  <input type="hidden" name="applicant_num" id="form_phone">
  <input type="hidden" name="applicant_address" id="form_address">
</form>

<div id="profileMainContent">
<main class="max-w-7xl mx-auto px-6 py-8">
<div class="mb-8">

<h1 class="text-3xl font-bold text-gray-900 mb-2">My Profile</h1>
<p class="text-gray-600">Manage your personal information and account settings</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
<div class="lg:col-span-1">
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
<div class="text-center">
<div class="relative inline-block">
<div class="w-32 h-32 bg-gradient-to-br from-primary to-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 overflow-hidden" id="profilePictureContainer">
<?php if (!empty($applicant['profile_picture']) && file_exists('uploads/profile_pictures/' . $applicant['profile_picture'])): ?>
    <img src="uploads/profile_pictures/<?php echo htmlspecialchars($applicant['profile_picture']); ?>" alt="Profile Picture" class="w-full h-full object-cover" id="profileImage">
<?php else: ?>
    <?php
    // Show initials from first and last name
    $initials = '';
    if (!empty($applicant['applicant_fname'])) {
        $initials .= strtoupper($applicant['applicant_fname'][0]);
    }
    if (!empty($applicant['applicant_lname'])) {
        $initials .= strtoupper($applicant['applicant_lname'][0]);
    }
    echo '<span class="text-white font-bold text-4xl" id="profileInitials">' . htmlspecialchars($initials) . '</span>';
    ?>
<?php endif; ?>
</div>
<button class="absolute bottom-0 right-0 w-10 h-10 bg-secondary rounded-full flex items-center justify-center text-white hover:bg-yellow-600 transition-colors !rounded-button" id="uploadPhotoBtn">
<i class="ri-camera-line text-lg"></i>
</button>
</div>
<h3 class="text-xl font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($applicant['applicant_fname'] . ' ' . $applicant['applicant_lname']); ?></h3>

<div class="text-sm text-gray-500 space-y-1">
<p>Supported formats: JPG, PNG, GIF</p>
<p>Maximum size: 5MB</p>
</div>
<input type="file" id="photoUpload" accept="image/*" class="hidden">
</div>
</div>
</div>

<div class="lg:col-span-2" id="personalInfo">
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
<div class="flex items-center justify-between mb-6">
<h3 class="text-xl font-semibold text-gray-900">Personal Information</h3>
<button class="text-primary hover:text-blue-700 text-sm font-medium" id="editPersonalBtn">Edit</button>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
<input type="text" name="applicant_fname" value="<?php echo htmlspecialchars($applicant['applicant_fname']); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" disabled>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
<input type="text" name="applicant_lname" value="<?php echo htmlspecialchars($applicant['applicant_lname']); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" disabled>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
<input type="email" name="applicant_email" value="<?php echo htmlspecialchars($applicant['applicant_email']); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" disabled>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
<input type="tel" name="applicant_num" value="<?php echo htmlspecialchars($applicant['applicant_num']); ?>" 
       pattern="09[0-9]{9}" 
       maxlength="11" 
       placeholder="09XXXXXXXXX"
       title="Please enter a valid Philippine mobile number (e.g., 09123456789)"
       oninput="this.value = this.value.replace(/[^0-9]/g, '')"
       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" disabled>
</div>
<div class="md:col-span-2">
<label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
<textarea name="applicant_address" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm resize-none" disabled placeholder="Enter your complete address"><?php echo htmlspecialchars($applicant['applicant_address']); ?></textarea>
</div>
</div>
<div class="hidden mt-6 flex justify-end space-x-4" id="personalActions">
<button class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition-colors !rounded-button" id="cancelPersonalBtn">Cancel</button>
<button class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors text-sm !rounded-button" id="savePersonalBtn">Save Changes</button>
</div>
</div>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
<div class="border-b border-gray-200">
<nav class="flex space-x-8 px-6">
<button class="py-4 px-1 border-b-2 border-primary text-primary font-medium text-sm whitespace-nowrap tab-btn" data-tab="education">Education</button>
<button class="py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm whitespace-nowrap tab-btn" data-tab="experience">Work Experience</button>
<button class="py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm whitespace-nowrap tab-btn" data-tab="skills">Skills</button>
<button class="py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm whitespace-nowrap tab-btn" data-tab="settings">Account Settings</button>
</nav>
</div>

<div class="p-6">
<div id="education" class="tab-content">
<div class="flex items-center justify-between mb-6">
<h3 class="text-xl font-semibold text-gray-900">Education Background</h3>
<button class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors text-sm !rounded-button" id="addEducationBtn">Add Education</button>
</div>
<div class="space-y-3" id="educationList">
<?php if (!empty($education_data)): ?>
<?php foreach ($education_data as $education): ?>
<div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
<div class="flex items-start justify-between">
<div class="flex-1">
<h4 class="font-semibold text-gray-900 text-base"><?php echo htmlspecialchars($education['degree']); ?></h4>
<p class="text-gray-600 mt-1 text-sm"><?php echo htmlspecialchars($education['institution']); ?></p>
<p class="text-gray-500 text-sm mt-1">
<?php echo htmlspecialchars($education['start_year'] . ' - ' . $education['end_year']); ?>
<?php if (!empty($education['gpa'])): ?>
 | GPA: <?php echo htmlspecialchars($education['gpa']); ?>
<?php endif; ?>
</p>
</div>
<div class="flex space-x-1 ml-4">
<button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors">
<i class="ri-edit-line text-sm"></i>
</button>
<button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors">
<i class="ri-delete-bin-line text-sm"></i>
</button>
</div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="text-center py-12 text-gray-500">
<i class="ri-graduation-cap-line text-4xl mb-4 text-gray-300"></i>
<p class="text-gray-600">No education records found.</p>
<p class="text-sm text-gray-500 mt-1">Click "Add Education" to get started.</p>
</div>
<?php endif; ?>
</div>
</div>

<div id="experience" class="tab-content hidden">
<div class="flex items-center justify-between mb-6">
<h3 class="text-xl font-semibold text-gray-900">Work Experience</h3>
<button class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors text-sm !rounded-button" id="addExperienceBtn">Add Experience</button>
</div>
<div class="space-y-3" id="experienceList">
<?php if (!empty($experience_data)): ?>
<?php foreach ($experience_data as $experience): ?>
<div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
<div class="flex items-start justify-between">
<div class="flex-1">
<h4 class="font-semibold text-gray-900 text-base"><?php echo htmlspecialchars($experience['job_title']); ?></h4>
<p class="text-gray-600 mt-1 text-sm"><?php echo htmlspecialchars($experience['company']); ?></p>
<p class="text-gray-500 text-sm mt-1"><?php
// Format dates nicely
$startDate = date('M Y', strtotime($experience['start_date']));
$endDate = $experience['end_date'] ? date('M Y', strtotime($experience['end_date'])) : 'Present';
echo $startDate . ' - ' . $endDate;
if (!empty($experience['location'])) {
    echo ' | ' . htmlspecialchars($experience['location']);
}
?></p>
<?php if (!empty($experience['description'])): ?>
<p class="text-gray-700 mt-2 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($experience['description'])); ?></p>
<?php endif; ?>
</div>
<div class="flex space-x-1 ml-4">
<button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors">
<i class="ri-edit-line text-sm"></i>
</button>
<button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors">
<i class="ri-delete-bin-line text-sm"></i>
</button>
</div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="text-center py-12 text-gray-500">
<i class="ri-briefcase-line text-4xl mb-4 text-gray-300"></i>
<p class="text-gray-600">No work experience records found.</p>
<p class="text-sm text-gray-500 mt-1">Click "Add Experience" to get started.</p>
</div>
<?php endif; ?>
</div>
</div>

<div id="skills" class="tab-content hidden">
<div class="flex items-center justify-between mb-6">
<h3 class="text-xl font-semibold text-gray-900">Skills & Expertise</h3>
<button class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors text-sm !rounded-button" id="addSkillBtn">Add Skill</button>
</div>
<div class="space-y-3" id="skillsList">
<?php if (!empty($skills_data)): ?>
<?php foreach ($skills_data as $skill): ?>
<div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
<div class="flex items-start justify-between">
<div class="flex-1">
<h4 class="font-semibold text-gray-900 text-base"><?php echo htmlspecialchars($skill['skill_name']); ?></h4>
<div class="flex items-center mt-2">
<?php for ($i = 1; $i <= 5; $i++): ?>
<div class="w-2.5 h-2.5 rounded-full mr-1 <?php echo $i <= $skill['skill_level'] ? 'bg-blue-500' : 'bg-gray-200'; ?>"></div>
<?php endfor; ?>
<span class="text-sm text-gray-500 ml-2">
<?php 
$levels = ['', 'Beginner', 'Novice', 'Intermediate', 'Advanced', 'Expert'];
echo $levels[$skill['skill_level']];
?>
</span>
</div>
</div>
<div class="flex space-x-1 ml-4">
<button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors">
<i class="ri-edit-line text-sm"></i>
</button>
<button class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors">
<i class="ri-delete-bin-line text-sm"></i>
</button>
</div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="text-center py-12 text-gray-500">
<i class="ri-tools-line text-4xl mb-4 text-gray-300"></i>
<p class="text-gray-600">No skills records found.</p>
<p class="text-sm text-gray-500 mt-1">Click "Add Skill" to get started.</p>
</div>
<?php endif; ?>
</div>
</div>
</div>

<div id="settings" class="tab-content hidden">
<h3 class="text-xl font-semibold text-gray-900 mb-6">Account Settings</h3>
<div class="space-y-6">
<div class="border border-gray-200 rounded-lg p-4">
<h4 class="font-medium text-gray-900 mb-3">Change Password</h4>
<p class="text-sm text-gray-600 mb-4">Update your account password to keep your account secure.</p>
<div class="space-y-4">
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
<input type="password" id="currentPassword" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" placeholder="Enter your current password" required>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
<input type="password" id="newPassword" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" placeholder="Enter new password (min. 8 characters)" required>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
<input type="password" id="confirmPassword" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" placeholder="Re-enter new password" required>
</div>
<div class="flex items-center gap-3">
<button type="button" id="updatePasswordBtn" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium !rounded-button">
<i class="ri-lock-password-line mr-2"></i>Update Password
</button>
<button type="button" id="cancelPasswordBtn" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium !rounded-button">
Cancel
</button>
</div>
</div>
</div>
</div>
</div>
</div>
</main>
</div>

<script id="headerInteractions">
document.addEventListener('DOMContentLoaded', function() {
// Header interactions - only if elements exist
const notificationBtn = document.getElementById('notificationBtn');
const notificationDropdown = document.getElementById('notificationDropdown');
const profileDropdownBtn = document.getElementById('profileDropdownBtn');
const profileDropdown = document.getElementById('profileDropdown');

if (notificationBtn && notificationDropdown) {
  notificationBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    if (profileDropdown) profileDropdown.classList.add('hidden');
    notificationDropdown.classList.toggle('hidden');
  });
}

if (profileDropdownBtn && profileDropdown) {
  profileDropdownBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    if (notificationDropdown) notificationDropdown.classList.add('hidden');
    profileDropdown.classList.toggle('hidden');
  });
}

document.addEventListener('click', function(e) {
  if (notificationDropdown && notificationBtn && !notificationDropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
    notificationDropdown.classList.add('hidden');
  }
  if (profileDropdown && profileDropdownBtn && !profileDropdown.contains(e.target) && !profileDropdownBtn.contains(e.target)) {
    profileDropdown.classList.add('hidden');
  }
});
});
</script>

<script id="tabNavigation">
document.addEventListener('DOMContentLoaded', function() {
  // Wait a bit to ensure all elements are loaded
  setTimeout(function() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    // Function to switch tabs
    function switchTab(targetTabId, clickedButton) {
      // Hide all tab contents
      tabContents.forEach(content => {
        content.classList.add('hidden');
      });

      // Remove active state from all tab buttons
      tabButtons.forEach(btn => {
        btn.classList.remove('border-primary', 'text-primary');
        btn.classList.add('border-transparent', 'text-gray-500');
      });

      // Show target content
      const targetContent = document.getElementById(targetTabId);
      if (targetContent) {
        targetContent.classList.remove('hidden');
      }

      // Add active state to clicked button
      if (clickedButton) {
        clickedButton.classList.remove('border-transparent', 'text-gray-500');
        clickedButton.classList.add('border-primary', 'text-primary');
      }
    }

    // Add click event listeners to tab buttons
    tabButtons.forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const targetTab = this.getAttribute('data-tab');
        if (targetTab) {
          switchTab(targetTab, this);
        }
      });
    });

    // Initialize - make sure education tab is active by default
    if (tabButtons.length > 0) {
      const educationTab = document.querySelector('[data-tab="education"]');
      if (educationTab) {
        switchTab('education', educationTab);
      }
    }
  }, 100);
});
</script>


<script id="profilePictureUtils">
// Universal function to update all profile pictures on the page
function updateAllProfilePictures(profilePictureUrl) {
  // Update header profile picture
  const headerProfilePicture = document.querySelector('#profileDropdownBtn .w-8.h-8');
  if (headerProfilePicture) {
    headerProfilePicture.innerHTML = `<img src="${profilePictureUrl}" alt="Profile Picture" class="w-full h-full object-cover">`;
  }
  
  // Update any other profile pictures that might exist
  const allProfileContainers = document.querySelectorAll('[id*="profilePicture"], [class*="profile-picture"]');
  allProfileContainers.forEach(container => {
    if (container.id !== 'profilePictureContainer') { // Don't update the main one again
      const img = container.querySelector('img');
      if (img) {
        img.src = profilePictureUrl;
      }
    }
  });
}
</script>

<script id="photoUpload">
document.addEventListener('DOMContentLoaded', function() {
const uploadPhotoBtn = document.getElementById('uploadPhotoBtn');
const photoUpload = document.getElementById('photoUpload');
const profilePictureContainer = document.getElementById('profilePictureContainer');

if (uploadPhotoBtn && photoUpload) {
  uploadPhotoBtn.addEventListener('click', function() {
    photoUpload.click();
  });

  photoUpload.addEventListener('change', function() {
    if (this.files && this.files[0]) {
      const file = this.files[0];
      
      // Validate file size (5MB max)
      if (file.size > 5 * 1024 * 1024) {
        if (typeof showNotification === 'function') {
          showNotification('File size must be less than 5MB', 'error');
        }
        return;
      }
      
      // Validate file type
      const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
      if (!allowedTypes.includes(file.type)) {
        if (typeof showNotification === 'function') {
          showNotification('Please select a valid image file (JPG, PNG, GIF)', 'error');
        }
        return;
      }
      
      // Create FormData for upload
      const formData = new FormData();
      formData.append('profile_picture', file);
      formData.append('upload_profile_picture', '1');
      
      // Show loading state
      const originalContent = profilePictureContainer.innerHTML;
      profilePictureContainer.innerHTML = '<div class="flex items-center justify-center"><i class="ri-loader-4-line text-white text-2xl animate-spin"></i></div>';
      
      // Upload file
      fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update profile picture display
          if (data.profile_picture_url) {
            profilePictureContainer.innerHTML = `<img src="${data.profile_picture_url}" alt="Profile Picture" class="w-full h-full object-cover" id="profileImage">`;
            
            // Update all profile pictures on the page immediately
            updateAllProfilePictures(data.profile_picture_url);
          }
          if (typeof showNotification === 'function') {
            showNotification('Profile photo updated successfully!', 'success');
          }
        } else {
          // Restore original content on error
          profilePictureContainer.innerHTML = originalContent;
          if (typeof showNotification === 'function') {
            showNotification('Error: ' + data.message, 'error');
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
        // Restore original content on error
        profilePictureContainer.innerHTML = originalContent;
        if (typeof showNotification === 'function') {
          showNotification('Error uploading profile picture', 'error');
        }
      });
    }
  });
}
});
</script>

<script id="modalHandlers">
document.addEventListener('DOMContentLoaded', function() {
  // Modal elements
  const educationModal = document.getElementById('educationModal');
  const experienceModal = document.getElementById('experienceModal');
  const skillModal = document.getElementById('skillModal');

  // Buttons that open modals
  const addEducationBtn = document.getElementById('addEducationBtn');
  const addExperienceBtn = document.getElementById('addExperienceBtn');
  const addSkillBtn = document.getElementById('addSkillBtn');

  // Buttons that close modals
  const closeEducationModalBtn = document.getElementById('closeEducationModal');
  const cancelEducationBtn = document.getElementById('cancelEducationBtn');

  const closeExperienceModalBtn = document.getElementById('closeExperienceModal');
  const cancelExperienceBtn = document.getElementById('cancelExperienceBtn');

  const closeSkillModalBtn = document.getElementById('closeSkillModal');
  const cancelSkillBtn = document.getElementById('cancelSkillBtn');

  // Skill level buttons and hidden input
  const skillLevelButtons = document.querySelectorAll('.skill-level');
  const skillLevelInput = document.getElementById('skill_level');

  // Open modals - with null checks
  if (addEducationBtn && educationModal) {
    addEducationBtn.addEventListener('click', (e) => {
      e.preventDefault();
      educationModal.classList.remove('hidden');
      educationModal.classList.add('flex');
    });
  }

  if (addExperienceBtn && experienceModal) {
    addExperienceBtn.addEventListener('click', (e) => {
      e.preventDefault();
      experienceModal.classList.remove('hidden');
      experienceModal.classList.add('flex');
    });
  }

  if (addSkillBtn && skillModal) {
    addSkillBtn.addEventListener('click', (e) => {
      e.preventDefault();
      skillModal.classList.remove('hidden');
      skillModal.classList.add('flex');
    });
  }

  // Close modals
  function closeModal(modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  // Close modal event listeners with null checks
  if (closeEducationModalBtn) {
    closeEducationModalBtn.addEventListener('click', () => closeModal(educationModal));
  }
  if (cancelEducationBtn) {
    cancelEducationBtn.addEventListener('click', () => closeModal(educationModal));
  }

  if (closeExperienceModalBtn) {
    closeExperienceModalBtn.addEventListener('click', () => closeModal(experienceModal));
  }
  if (cancelExperienceBtn) {
    cancelExperienceBtn.addEventListener('click', () => closeModal(experienceModal));
  }

  if (closeSkillModalBtn) {
    closeSkillModalBtn.addEventListener('click', () => closeModal(skillModal));
  }
  if (cancelSkillBtn) {
    cancelSkillBtn.addEventListener('click', () => closeModal(skillModal));
  }

  // Skill level selection
  skillLevelButtons.forEach(button => {
    button.addEventListener('click', function() {
      const level = parseInt(this.getAttribute('data-level'));
      skillLevelInput.value = level;
      skillLevelButtons.forEach((btn, index) => {
        if (index < level) {
          btn.classList.remove('bg-gray-300');
          btn.classList.add('bg-primary');
        } else {
          btn.classList.remove('bg-primary');
          btn.classList.add('bg-gray-300');
        }
      });
    });
  });

  // Handle form submissions with AJAX
  const educationForm = document.getElementById('educationForm');
  const experienceForm = document.getElementById('experienceForm');
  const skillForm = document.getElementById('skillForm');

  if (educationForm) {
    educationForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          closeModal(educationModal);
          this.reset();
          if (typeof showNotification === 'function') {
            showNotification(data.message, 'success');
          }
          // Reload page to show new data
          setTimeout(() => location.reload(), 1000);
        } else {
          if (typeof showNotification === 'function') {
            showNotification('Error: ' + data.message, 'error');
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
        if (typeof showNotification === 'function') {
          showNotification('Error saving education data', 'error');
        }
      });
    });
  }

  if (experienceForm) {
    experienceForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          closeModal(experienceModal);
          this.reset();
          if (typeof showNotification === 'function') {
            showNotification(data.message, 'success');
          }
          // Reload page to show new data
          setTimeout(() => location.reload(), 1000);
        } else {
          if (typeof showNotification === 'function') {
            showNotification('Error: ' + data.message, 'error');
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
        if (typeof showNotification === 'function') {
          showNotification('Error saving experience data', 'error');
        }
      });
    });
  }

  if (skillForm) {
    skillForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          closeModal(skillModal);
          this.reset();
          // Reset skill level buttons
          skillLevelButtons.forEach(btn => {
            btn.classList.remove('bg-primary');
            btn.classList.add('bg-gray-300');
          });
          skillLevelInput.value = '0';
          if (typeof showNotification === 'function') {
            showNotification(data.message, 'success');
          }
          // Reload page to show new data
          setTimeout(() => location.reload(), 1000);
        } else {
          if (typeof showNotification === 'function') {
            showNotification('Error: ' + data.message, 'error');
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
        if (typeof showNotification === 'function') {
          showNotification('Error saving skill data', 'error');
        }
      });
    });
  }
});

</script>

<script id="toggleSwitches">
document.addEventListener('DOMContentLoaded', function() {
const emailToggle = document.getElementById('emailNotificationToggle');
const pushToggle = document.getElementById('pushNotificationToggle');

if (emailToggle) {
  emailToggle.addEventListener('click', function() {
    const isEnabled = this.classList.contains('bg-primary');
    if (isEnabled) {
      this.classList.remove('bg-primary');
      this.classList.add('bg-gray-200');
      this.querySelector('span').classList.remove('translate-x-6');
      this.querySelector('span').classList.add('translate-x-1');
    } else {
      this.classList.remove('bg-gray-200');
      this.classList.add('bg-primary');
      this.querySelector('span').classList.remove('translate-x-1');
      this.querySelector('span').classList.add('translate-x-6');
    }
  });
}

if (pushToggle) {
  pushToggle.addEventListener('click', function() {
    const isEnabled = this.classList.contains('bg-primary');
    if (isEnabled) {
      this.classList.remove('bg-primary');
      this.classList.add('bg-gray-200');
      this.querySelector('span').classList.remove('translate-x-6');
      this.querySelector('span').classList.add('translate-x-1');
    } else {
      this.classList.remove('bg-gray-200');
      this.classList.add('bg-primary');
      this.querySelector('span').classList.remove('translate-x-1');
      this.querySelector('span').classList.add('translate-x-6');
    }
  });
}
});
</script>

<script id="saveActions">
document.addEventListener('DOMContentLoaded', function() {
const saveAllBtn = document.getElementById('saveAllBtn');
const cancelAllBtn = document.getElementById('cancelAllBtn');

if (saveAllBtn) {
  saveAllBtn.addEventListener('click', function() {
    if (typeof showNotification === 'function') {
      showNotification('All changes saved successfully!', 'success');
    }
  });
}

if (cancelAllBtn) {
  cancelAllBtn.addEventListener('click', function() {
    if (confirm('Are you sure you want to cancel all changes?')) {
      location.reload();
    }
  });
}

function showNotification(message, type) {
const notification = document.createElement('div');
notification.className = `fixed top-4 right-4 ${type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-blue-100 border-blue-400 text-blue-700'} px-4 py-3 rounded border z-50`;
notification.innerHTML = `
<div class="flex items-center">
<i class="${type === 'success' ? 'ri-check-line' : 'ri-information-line'} mr-2"></i>
<span>${message}</span>
</div>
`;
document.body.appendChild(notification);
setTimeout(() => {
notification.remove();
}, 3000);
}

window.showNotification = showNotification;
});
</script>

<?php $conn->close(); ?>
<!-- Education Modal -->
<div id="educationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-xl max-w-md w-full mx-4">
    <form method="POST" action="" class="p-6 space-y-4" id="educationForm">
      <div class="border-b border-gray-200 flex justify-between items-center pb-4">
        <h3 class="text-lg font-semibold text-gray-900">Add Education</h3>
        <button type="button" id="closeEducationModal" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600">
          <i class="ri-close-line text-xl"></i>
        </button>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="ed_degree">Degree</label>
        <input type="text" name="ed_degree" id="ed_degree" placeholder="e.g., Bachelor of Science" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="ed_fs">Field of Study</label>
        <input type="text" name="ed_fs" id="ed_fs" placeholder="e.g., Computer Science" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="ed_ins">Institution</label>
        <input type="text" name="ed_ins" id="ed_ins" placeholder="University name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2" for="ed_sy">Start Year</label>
          <input type="number" name="ed_sy" id="ed_sy" placeholder="2020" required min="1900" max="2100" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2" for="ed_ey">End Year</label>
          <input type="number" name="ed_ey" id="ed_ey" placeholder="2024" required min="1900" max="2100" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="ed_gpa">GPA (Optional)</label>
        <input type="text" name="ed_gpa" id="ed_gpa" placeholder="3.8/4.0" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
      </div>
      <div class="flex justify-end space-x-4 pt-4">
        <button type="button" id="cancelEducationBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition-colors !rounded-button">Cancel</button>
        <button type="submit" name="saveEducation" id="saveEducationBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors text-sm !rounded-button">Add Education</button>
      </div>
    </form>
  </div>
</div>

<!-- Experience Modal -->
<div id="experienceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-xl max-w-md w-full mx-4">
    <form method="POST" action="" class="p-6 space-y-4" id="experienceForm">
      <div class="border-b border-gray-200 flex justify-between items-center pb-4">
        <h3 class="text-lg font-semibold text-gray-900">Add Work Experience</h3>
        <button type="button" id="closeExperienceModal" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600">
          <i class="ri-close-line text-xl"></i>
        </button>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="job_title">Job Title</label>
        <input type="text" name="job_title" id="job_title" placeholder="e.g., Software Developer" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="work_comp">Company</label>
        <input type="text" name="work_comp" id="work_comp" placeholder="Company name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="work_loc">Location</label>
        <input type="text" name="work_loc" id="work_loc" placeholder="City, State" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2" for="start_date">Start Date</label>
          <input type="month" name="start_date" id="start_date" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2" for="end_date">End Date</label>
          <input type="month" name="end_date" id="end_date" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
        </div>
      </div>
      <div class="flex items-center">
        <input type="checkbox" name="is_current" id="is_current" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
        <label for="is_current" class="ml-2 block text-sm text-gray-700">I currently work here</label>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="work_descript">Description</label>
        <textarea name="work_descript" id="work_descript" placeholder="Describe your responsibilities and achievements..." rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm resize-none"></textarea>
      </div>
{{ ... }}
        <button type="button" id="cancelExperienceBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition-colors !rounded-button">Cancel</button>
        <button type="submit" name="saveExperience" id="saveExperienceBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors text-sm !rounded-button">Add Experience</button>
      </div>
    </form>
  </div>
</div>

<!-- Skill Modal -->
<div id="skillModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-xl max-w-md w-full mx-4">
    <form method="POST" action="" class="p-6 space-y-4" id="skillForm">
      <div class="border-b border-gray-200 flex justify-between items-center pb-4">
        <h3 class="text-lg font-semibold text-gray-900">Add Skill</h3>
        <button type="button" id="closeSkillModal" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600">
          <i class="ri-close-line text-xl"></i>
        </button>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="skill_name">Skill Name</label>
        <input type="text" name="skill_name" id="skill_name" placeholder="e.g., JavaScript" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="skill_category">Category</label>
        <select name="skill_category" id="skill_category" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
          <option value="">Select category</option>
          <option value="programming">Programming Languages</option>
          <option value="frameworks">Frameworks & Technologies</option>
          <option value="tools">Tools & Software</option>
          <option value="soft">Soft Skills</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Proficiency Level</label>
        <div class="flex items-center space-x-2">
          <span class="text-sm text-gray-600">Beginner</span>
          <div class="flex space-x-1">
            <button type="button" class="w-3 h-3 rounded-full bg-gray-300 hover:bg-primary transition-colors skill-level" data-level="1"></button>
            <button type="button" class="w-3 h-3 rounded-full bg-gray-300 hover:bg-primary transition-colors skill-level" data-level="2"></button>
            <button type="button" class="w-3 h-3 rounded-full bg-gray-300 hover:bg-primary transition-colors skill-level" data-level="3"></button>
            <button type="button" class="w-3 h-3 rounded-full bg-gray-300 hover:bg-primary transition-colors skill-level" data-level="4"></button>
            <button type="button" class="w-3 h-3 rounded-full bg-gray-300 hover:bg-primary transition-colors skill-level" data-level="5"></button>
          </div>
          <span class="text-sm text-gray-600">Expert</span>
        </div>
        <input type="hidden" name="skill_level" id="skill_level" value="0" required>
      </div>
      <div class="flex justify-end space-x-4 pt-4">
        <button type="button" id="cancelSkillBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition-colors !rounded-button">Cancel</button>
        <button type="submit" name="saveSkill" id="saveSkillBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors text-sm !rounded-button">Add Skill</button>
      </div>
    </form>
  </div>
</div>


</body>
</html>