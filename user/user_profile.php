<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_email'])) {
    header("Location: ../public/index.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nchire";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = '';
$error_message = '';

// Debug: Log session data
error_log("user_profile.php - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("user_profile.php - Session user_email: " . ($_SESSION['user_email'] ?? 'NOT SET'));

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

// Handle Education form submission - DISABLED (handled by save_profile_data.php via AJAX)
if (false && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveEducation'])) {
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
        
        // If user_id not set, try to get it from email
        if (!$user_id && isset($_SESSION['user_email'])) {
            $email_stmt = $conn->prepare("SELECT id FROM applicants WHERE applicant_email = ?");
            $email_stmt->bind_param("s", $_SESSION['user_email']);
            $email_stmt->execute();
            $email_result = $email_stmt->get_result();
            if ($email_result->num_rows > 0) {
                $user_row = $email_result->fetch_assoc();
                $user_id = $user_row['id'];
                $_SESSION['user_id'] = $user_id; // Store for future use
            }
            $email_stmt->close();
        }
        
        if (!$user_id) {
            $error_message = "User not logged in properly. Please log in again.";
            error_log("Education save error: No user_id found in session");
            header("Location: ../public/index.php");
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

// Handle Experience form submission - DISABLED (handled by save_profile_data.php via AJAX)
if (false && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveExperience'])) {
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
        
        // If user_id not set, try to get it from email
        if (!$user_id && isset($_SESSION['user_email'])) {
            $email_stmt = $conn->prepare("SELECT id FROM applicants WHERE applicant_email = ?");
            $email_stmt->bind_param("s", $_SESSION['user_email']);
            $email_stmt->execute();
            $email_result = $email_stmt->get_result();
            if ($email_result->num_rows > 0) {
                $user_row = $email_result->fetch_assoc();
                $user_id = $user_row['id'];
                $_SESSION['user_id'] = $user_id;
            }
            $email_stmt->close();
        }
        
        if (!$user_id) {
            $error_message = "User not logged in properly. Please log in again.";
            error_log("Experience save error: No user_id found in session");
            header("Location: ../public/index.php");
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
    skill_category VARCHAR(100) NOT NULL,
    skill_level INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_skills_table);

// Check if old column exists and migrate if needed
$check_old_column = "SHOW COLUMNS FROM user_skills LIKE 'proficiency_level'";
$old_col_result = $conn->query($check_old_column);
if ($old_col_result && $old_col_result->num_rows > 0) {
    // Drop old column if it exists
    $conn->query("ALTER TABLE user_skills DROP COLUMN proficiency_level");
}

// Add skill_category column if it doesn't exist
$check_category = "SHOW COLUMNS FROM user_skills LIKE 'skill_category'";
$cat_result = $conn->query($check_category);
if ($cat_result && $cat_result->num_rows == 0) {
    $conn->query("ALTER TABLE user_skills ADD COLUMN skill_category VARCHAR(100) NOT NULL DEFAULT 'general' AFTER skill_name");
}

// Add skill_level column if it doesn't exist
$check_level = "SHOW COLUMNS FROM user_skills LIKE 'skill_level'";
$level_result = $conn->query($check_level);
if ($level_result && $level_result->num_rows == 0) {
    $conn->query("ALTER TABLE user_skills ADD COLUMN skill_level INT NOT NULL DEFAULT 1 AFTER skill_category");
}

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
            
            // If user_id not set, try to get it from email
            if (!$user_id && isset($_SESSION['user_email'])) {
                $email_stmt = $conn->prepare("SELECT id FROM applicants WHERE applicant_email = ?");
                $email_stmt->bind_param("s", $_SESSION['user_email']);
                $email_stmt->execute();
                $email_result = $email_stmt->get_result();
                if ($email_result->num_rows > 0) {
                    $user_row = $email_result->fetch_assoc();
                    $user_id = $user_row['id'];
                    $_SESSION['user_id'] = $user_id;
                }
                $email_stmt->close();
            }
            
            if (!$user_id) {
                $error_message = "User not logged in properly. Please log in again.";
                error_log("Personal info save error: No user_id found in session");
                header("Location: ../public/index.php");
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

// Handle Skill form submission - DISABLED (handled by save_profile_data.php via AJAX)
if (false && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveSkill'])) {
    // Validate required fields
    if (empty($_POST['skill_name']) || empty($_POST['skill_category']) || empty($_POST['skill_level']) || $_POST['skill_level'] == '0') {
        $error_message = "Please fill in all required fields and select a skill level.";
    } else {
        $skill_name = $conn->real_escape_string($_POST['skill_name']);
        $skill_category = $conn->real_escape_string($_POST['skill_category']);
        $skill_level = (int)$_POST['skill_level'];
        // Get user ID from session
        $user_id = $_SESSION['user_id'] ?? null;
        
        // If user_id not set, try to get it from email
        if (!$user_id && isset($_SESSION['user_email'])) {
            $email_stmt = $conn->prepare("SELECT id FROM applicants WHERE applicant_email = ?");
            $email_stmt->bind_param("s", $_SESSION['user_email']);
            $email_stmt->execute();
            $email_result = $email_stmt->get_result();
            if ($email_result->num_rows > 0) {
                $user_row = $email_result->fetch_assoc();
                $user_id = $user_row['id'];
                $_SESSION['user_id'] = $user_id;
            }
            $email_stmt->close();
        }
        
        if (!$user_id) {
            $error_message = "User not logged in properly. Please log in again.";
            error_log("Skill save error: No user_id found in session");
            header("Location: ../public/index.php");
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
<html lang="en">
<head><script src="https://static.readdy.ai/static/e.js"></script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NCHire - My Profile</title>
<link rel="icon" type="image/png" href="../public/assets/images/image-removebg-preview (1).png">
<link rel="shortcut icon" type="image/png" href="../public/assets/images/image-removebg-preview (1).png">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css" rel="stylesheet">
<style>
:where([class^="ri-"])::before { content: "\f3c2"; }

/* ============================================
   MOBILE RESPONSIVE STYLES FOR USER PROFILE
   ============================================ */

@media (max-width: 768px) {
    /* Header adjustments */
    header .px-6 {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    /* Main content padding */
    .max-w-7xl {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
    }
    
    /* Profile sections */
    .grid {
        grid-template-columns: 1fr !important;
    }
    
    /* Profile cards */
    .rounded-xl {
        border-radius: 0.75rem;
    }
    
    /* Form inputs */
    input, textarea, select {
        font-size: 16px !important; /* Prevents zoom on iOS */
    }
    
    /* Buttons */
    .flex.gap-3 {
        flex-direction: column;
    }
    
    .flex.gap-3 button {
        width: 100%;
    }
    
    /* Profile picture upload */
    .relative.group {
        width: 100%;
        max-width: 150px;
        margin: 0 auto;
    }
    
    /* Skills and experience items */
    .flex.items-center.justify-between {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    /* Modal responsive */
    .max-w-md {
        max-width: 95vw !important;
    }
    
    /* Toast notifications */
    #toastContainer {
        right: 0.5rem;
        left: 0.5rem;
        top: 1rem;
    }
    
    /* Profile header */
    .mb-8 h1 {
        font-size: 1.5rem !important;
    }
    
    /* Section titles */
    h2 {
        font-size: 1.25rem !important;
    }
}

/* Tablet adjustments */
@media (min-width: 769px) and (max-width: 1024px) {
    .max-w-7xl {
        padding-left: 2rem !important;
        padding-right: 2rem !important;
    }
}

/* Real-time update animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

/* Smooth transitions for delete operations */
#educationList > div,
#experienceList > div,
#skillsList > div {
    transition: opacity 0.3s ease-out, transform 0.3s ease-out;
}
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

// Handle Edit, Save, and Cancel for Personal Information
document.addEventListener('DOMContentLoaded', function() {
  const editBtn = document.getElementById('editPersonalBtn');
  const saveBtn = document.getElementById('savePersonalBtn');
  const cancelBtn = document.getElementById('cancelPersonalBtn');
  const personalActions = document.getElementById('personalActions');
  
  // Get all personal info inputs
  const firstNameInput = document.querySelector('input[name="applicant_fname"]');
  const lastNameInput = document.querySelector('input[name="applicant_lname"]');
  const emailInput = document.querySelector('input[name="applicant_email"]');
  const phoneInput = document.querySelector('input[name="applicant_num"]');
  const addressInput = document.querySelector('textarea[name="applicant_address"]');
  
  // Store original values for cancel functionality
  let originalValues = {};
  
  // Prevent numbers from being entered in first name and last name fields
  function preventNumbersInNames(event) {
    const char = event.key;
    // Allow navigation keys, backspace, delete, tab
    if (event.ctrlKey || event.metaKey || 
        ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(char)) {
        return;
    }
    // Block if character is a number
    if (/[0-9]/.test(char)) {
        event.preventDefault();
        showToast('Numbers are not allowed in name fields', 'warning');
    }
  }
  
  // Function to attach number validation to name fields
  function attachNameValidation() {
    if (firstNameInput) {
      firstNameInput.addEventListener('keydown', preventNumbersInNames);
      // Also prevent pasted numbers
      firstNameInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[0-9]/g, '');
      });
    }
    
    if (lastNameInput) {
      lastNameInput.addEventListener('keydown', preventNumbersInNames);
      // Also prevent pasted numbers
      lastNameInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[0-9]/g, '');
      });
    }
  }
  
  // Attach validation on page load
  attachNameValidation();
  
  // Handle Edit button click
  if (editBtn) {
    editBtn.addEventListener('click', function(e) {
      e.preventDefault();
      
      // Store original values
      originalValues = {
        fname: firstNameInput ? firstNameInput.value : '',
        lname: lastNameInput ? lastNameInput.value : '',
        email: emailInput ? emailInput.value : '',
        phone: phoneInput ? phoneInput.value : '',
        address: addressInput ? addressInput.value : ''
      };
      
      // Enable all inputs
      if (firstNameInput) firstNameInput.disabled = false;
      if (lastNameInput) lastNameInput.disabled = false;
      if (emailInput) emailInput.disabled = false;
      if (phoneInput) phoneInput.disabled = false;
      if (addressInput) addressInput.disabled = false;
      
      // Show Save/Cancel buttons, hide Edit button
      if (personalActions) personalActions.classList.remove('hidden');
      editBtn.style.display = 'none';
    });
  }
  
  // Handle Cancel button click
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function(e) {
      e.preventDefault();
      
      // Restore original values
      if (firstNameInput) firstNameInput.value = originalValues.fname;
      if (lastNameInput) lastNameInput.value = originalValues.lname;
      if (emailInput) emailInput.value = originalValues.email;
      if (phoneInput) phoneInput.value = originalValues.phone;
      if (addressInput) addressInput.value = originalValues.address;
      
      // Disable all inputs
      if (firstNameInput) firstNameInput.disabled = true;
      if (lastNameInput) lastNameInput.disabled = true;
      if (emailInput) emailInput.disabled = true;
      if (phoneInput) phoneInput.disabled = true;
      if (addressInput) addressInput.disabled = true;
      
      // Hide Save/Cancel buttons, show Edit button
      if (personalActions) personalActions.classList.add('hidden');
      if (editBtn) editBtn.style.display = 'block';
    });
  }
  
  // Handle Save button click
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
<input type="text" name="applicant_fname" value="<?php echo htmlspecialchars($applicant['applicant_fname']); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" pattern="[A-Za-z\s\-']+" title="Please enter only letters, spaces, hyphens, and apostrophes" disabled>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
<input type="text" name="applicant_lname" value="<?php echo htmlspecialchars($applicant['applicant_lname']); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" pattern="[A-Za-z\s\-']+" title="Please enter only letters, spaces, hyphens, and apostrophes" disabled>
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
<button onclick="editEducation(<?php echo $education['id']; ?>)" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
<i class="ri-edit-line text-sm"></i>
</button>
<button onclick="deleteEducation(<?php echo $education['id']; ?>)" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
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
</div>
<div class="flex space-x-1 ml-4">
<button onclick="editExperience(<?php echo $experience['id']; ?>)" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
<i class="ri-edit-line text-sm"></i>
</button>
<button onclick="deleteExperience(<?php echo $experience['id']; ?>)" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
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
<button onclick="editSkill(<?php echo $skill['id']; ?>)" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
<i class="ri-edit-line text-sm"></i>
</button>
<button onclick="deleteSkill(<?php echo $skill['id']; ?>)" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
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
<div class="px-4 -mt-2">
<h3 class="text-xl font-semibold text-gray-900 mb-3">Account Settings</h3>
<div class="space-y-6">
<div class="border border-gray-200 rounded-lg p-8">
<h4 class="font-medium text-gray-900 mb-3">Change Password</h4>
<p class="text-sm text-gray-600 mb-6">Update your account password to keep your account secure.</p>
<div class="space-y-5">
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
<div class="relative">
<input type="password" id="currentPassword" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');" class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" placeholder="Enter your current password" required>
<button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none" onclick="togglePasswordVisibility('currentPassword', this)">
<i class="ri-eye-line text-lg"></i>
</button>
</div>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
<div class="relative">
<input type="password" id="newPassword" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');" class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" placeholder="Enter new password (min. 8 characters)" required>
<button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none" onclick="togglePasswordVisibility('newPassword', this)">
<i class="ri-eye-line text-lg"></i>
</button>
</div>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
<div class="relative">
<input type="password" id="confirmPassword" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');" class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm" placeholder="Re-enter new password" required>
<button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none" onclick="togglePasswordVisibility('confirmPassword', this)">
<i class="ri-eye-line text-lg"></i>
</button>
</div>
</div>
<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
<p class="text-xs text-amber-800">
<i class="ri-shield-check-line mr-1"></i>
<strong>Password Requirements:</strong><br>
• At least 8 characters long<br>
• Must contain at least one number<br>
• Must contain at least one symbol (!@#$%^&*)
</p>
</div>
<div class="flex items-center gap-3 pt-2">
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
      
      // Clear password fields when switching to Account Settings tab
      if (targetTabId === 'settings') {
        const currentPassword = document.getElementById('currentPassword');
        const newPassword = document.getElementById('newPassword');
        const confirmPassword = document.getElementById('confirmPassword');
        
        if (currentPassword) {
          currentPassword.value = '';
          currentPassword.setAttribute('readonly', 'readonly');
        }
        if (newPassword) {
          newPassword.value = '';
          newPassword.setAttribute('readonly', 'readonly');
        }
        if (confirmPassword) {
          confirmPassword.value = '';
          confirmPassword.setAttribute('readonly', 'readonly');
        }
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

    // Initialize - restore last active tab from localStorage or default to education
    if (tabButtons.length > 0) {
      const lastActiveTab = localStorage.getItem('activeProfileTab');
      const defaultTab = lastActiveTab || 'education';
      
      const tabButton = document.querySelector(`[data-tab="${defaultTab}"]`);
      if (tabButton) {
        switchTab(defaultTab, tabButton);
      }
      
      // Clear the stored tab so it doesn't persist on next normal visit
      localStorage.removeItem('activeProfileTab');
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

<script id="passwordFieldClear">
// Clear password fields on page load and prevent browser autofill
document.addEventListener('DOMContentLoaded', function() {
  // Clear immediately and re-add readonly protection
  function clearPasswordFields() {
    const currentPassword = document.getElementById('currentPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    if (currentPassword) {
      currentPassword.value = '';
      currentPassword.setAttribute('readonly', 'readonly');
    }
    if (newPassword) {
      newPassword.value = '';
      newPassword.setAttribute('readonly', 'readonly');
    }
    if (confirmPassword) {
      confirmPassword.value = '';
      confirmPassword.setAttribute('readonly', 'readonly');
    }
  }
  
  // Clear on load
  clearPasswordFields();
  
  // Clear again after a short delay to override browser autofill
  setTimeout(clearPasswordFields, 100);
  setTimeout(clearPasswordFields, 500);
  setTimeout(clearPasswordFields, 1000);
  
  // Also clear when Cancel button is clicked
  const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
  if (cancelPasswordBtn) {
    cancelPasswordBtn.addEventListener('click', clearPasswordFields);
  }
});
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

<script id="reloadFunctions">
// Only define these functions if we're on the profile page (not dashboard)
if (document.getElementById('profileMainContent')) {
  
  // Helper function to escape HTML with null safety
  function escapeHtml(text) {
    if (text === null || text === undefined) {
      return '';
    }
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
  }

  // Function to reload education list
  window.reloadEducationList = async function() {
    console.log('reloadEducationList function called');
    try {
      // Add cache-busting parameter to ensure fresh data
      const timestamp = new Date().getTime();
      const response = await fetch(`get_profile_lists.php?type=education&_=${timestamp}`);
    const result = await response.json();
    console.log('Education data received:', result);
    if (result.success) {
      const educationList = document.getElementById('educationList');
      console.log('Education list element found:', educationList);
      if (!educationList) {
        console.error('Education list element not found!');
        return;
      }
      if (result.data && result.data.length > 0) {
        console.log('Updating education list with', result.data.length, 'items');
        const html = result.data.map(edu => `
          <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <h4 class="font-semibold text-gray-900 text-base">${escapeHtml(edu.degree)}</h4>
                <p class="text-gray-600 mt-1 text-sm">${escapeHtml(edu.institution)}</p>
                <p class="text-gray-500 text-sm mt-1">
                  ${escapeHtml(edu.start_year + ' - ' + edu.end_year)}
                  ${edu.gpa ? ' | GPA: ' + escapeHtml(edu.gpa) : ''}
                </p>
              </div>
              <div class="flex space-x-1 ml-4">
                <button onclick="editEducation(${edu.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
                  <i class="ri-edit-line text-sm"></i>
                </button>
                <button onclick="deleteEducation(${edu.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
                  <i class="ri-delete-bin-line text-sm"></i>
                </button>
              </div>
            </div>
          </div>
        `).join('');
        console.log('Generated HTML length:', html.length);
        educationList.innerHTML = html;
        console.log('Education list updated successfully!');
      } else {
        console.log('No education data, showing empty state');
        educationList.innerHTML = `
          <div class="text-center py-12 text-gray-500">
            <i class="ri-graduation-cap-line text-4xl mb-4 text-gray-300"></i>
            <p class="text-gray-600">No education records found.</p>
            <p class="text-sm text-gray-500 mt-1">Click "Add Education" to get started.</p>
          </div>
        `;
      }
    }
  } catch (error) {
    console.error('Error reloading education list:', error);
  }
  };

  // Function to reload experience list
  window.reloadExperienceList = async function() {
    console.log('reloadExperienceList function called');
    try {
    // Add cache-busting parameter to ensure fresh data
    const timestamp = new Date().getTime();
    const response = await fetch(`get_profile_lists.php?type=experience&_=${timestamp}`);
    const result = await response.json();
    console.log('Experience data received:', result);
    if (result.success) {
      const experienceList = document.getElementById('experienceList');
      console.log('Experience list element found:', experienceList);
      if (!experienceList) {
        console.error('Experience list element not found!');
        return;
      }
      if (result.data && result.data.length > 0) {
        console.log('Updating experience list with', result.data.length, 'items');
        const html = result.data.map(exp => {
          const startDate = new Date(exp.start_date).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
          const endDate = exp.end_date ? new Date(exp.end_date).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) : 'Present';
          return `
            <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h4 class="font-semibold text-gray-900 text-base">${escapeHtml(exp.job_title)}</h4>
                  <p class="text-gray-600 mt-1 text-sm">${escapeHtml(exp.company)}</p>
                  <p class="text-gray-500 text-sm mt-1">
                    ${startDate} - ${endDate}
                    ${exp.location ? ' | ' + escapeHtml(exp.location) : ''}
                  </p>
                </div>
                <div class="flex space-x-1 ml-4">
                  <button onclick="editExperience(${exp.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
                    <i class="ri-edit-line text-sm"></i>
                  </button>
                  <button onclick="deleteExperience(${exp.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
                    <i class="ri-delete-bin-line text-sm"></i>
                  </button>
                </div>
              </div>
            </div>
          `;
        }).join('');
        console.log('Generated HTML length:', html.length);
        experienceList.innerHTML = html;
        console.log('Experience list updated successfully!');
      } else {
        console.log('No experience data, showing empty state');
        experienceList.innerHTML = `
          <div class="text-center py-12 text-gray-500">
            <i class="ri-briefcase-line text-4xl mb-4 text-gray-300"></i>
            <p class="text-gray-600">No work experience records found.</p>
            <p class="text-sm text-gray-500 mt-1">Click "Add Experience" to get started.</p>
          </div>
        `;
      }
    }
  } catch (error) {
    console.error('Error reloading experience list:', error);
  }
  };

  // Function to reload skills list
  window.reloadSkillsList = async function() {
    console.log('reloadSkillsList function called');
    try {
    // Add cache-busting parameter to ensure fresh data
    const timestamp = new Date().getTime();
    const response = await fetch(`get_profile_lists.php?type=skills&_=${timestamp}`);
    const result = await response.json();
    console.log('Skills data received:', result);
    if (result.success) {
      const skillsList = document.getElementById('skillsList');
      console.log('Skills list element found:', skillsList);
      if (!skillsList) {
        console.error('Skills list element not found!');
        return;
      }
      if (result.data && result.data.length > 0) {
        console.log('Updating skills list with', result.data.length, 'items');
        const levels = ['', 'Beginner', 'Novice', 'Intermediate', 'Advanced', 'Expert'];
        const html = result.data.map(skill => {
          const dots = Array.from({length: 5}, (_, i) => 
            `<div class="w-2.5 h-2.5 rounded-full mr-1 ${i < skill.skill_level ? 'bg-blue-500' : 'bg-gray-200'}"></div>`
          ).join('');
          return `
            <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h4 class="font-semibold text-gray-900 text-base">${escapeHtml(skill.skill_name)}</h4>
                  <div class="flex items-center mt-2">
                    ${dots}
                    <span class="text-sm text-gray-500 ml-2">${levels[skill.skill_level]}</span>
                  </div>
                </div>
                <div class="flex space-x-1 ml-4">
                  <button onclick="editSkill(${skill.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
                    <i class="ri-edit-line text-sm"></i>
                  </button>
                  <button onclick="deleteSkill(${skill.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
                    <i class="ri-delete-bin-line text-sm"></i>
                  </button>
                </div>
              </div>
            </div>
          `;
        }).join('');
        console.log('Generated HTML length:', html.length);
        skillsList.innerHTML = html;
        console.log('Skills list updated successfully!');
      } else {
        console.log('No skills data, showing empty state');
        skillsList.innerHTML = `
          <div class="text-center py-12 text-gray-500">
            <i class="ri-tools-line text-4xl mb-4 text-gray-300"></i>
            <p class="text-gray-600">No skills records found.</p>
            <p class="text-sm text-gray-500 mt-1">Click "Add Skill" to get started.</p>
          </div>
        `;
      }
    }
  } catch (error) {
    console.error('Error reloading skills list:', error);
  }
  };
  
} // End of profileMainContent check
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

  // Functions to reload sections dynamically
  function reloadEducationList() {
    fetch('get_profile_data.php?type=education')
      .then(response => response.json())
      .then(result => {
        if (result.success) {
          const educationList = document.getElementById('educationList');
          if (result.data.length === 0) {
            educationList.innerHTML = `
              <div class="text-center py-12 text-gray-500">
                <i class="ri-graduation-cap-line text-4xl mb-4 text-gray-300"></i>
                <p class="text-gray-600">No education records found.</p>
                <p class="text-sm text-gray-500 mt-1">Click "Add Education" to get started.</p>
              </div>
            `;
          } else {
            let html = '';
            result.data.forEach(education => {
              html += `
                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
                  <div class="flex items-start justify-between">
                    <div class="flex-1">
                      <h4 class="font-semibold text-gray-900 text-base">${escapeHtml(education.degree)}</h4>
                      <p class="text-gray-600 mt-1 text-sm">${escapeHtml(education.institution)}</p>
                      <p class="text-gray-500 text-sm mt-1">
                        ${escapeHtml(education.start_year + ' - ' + education.end_year)}
                        ${education.gpa ? ' | GPA: ' + escapeHtml(education.gpa) : ''}
                      </p>
                    </div>
                    <div class="flex space-x-1 ml-4">
                      <button onclick="editEducation(${education.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
                        <i class="ri-edit-line text-sm"></i>
                      </button>
                      <button onclick="deleteEducation(${education.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
                        <i class="ri-delete-bin-line text-sm"></i>
                      </button>
                    </div>
                  </div>
                </div>
              `;
            });
            educationList.innerHTML = html;
          }
        }
      })
      .catch(error => console.error('Error reloading education:', error));
  }

  function reloadExperienceList() {
    fetch('get_profile_data.php?type=experience')
      .then(response => response.json())
      .then(result => {
        if (result.success) {
          const experienceList = document.getElementById('experienceList');
          if (result.data.length === 0) {
            experienceList.innerHTML = `
              <div class="text-center py-12 text-gray-500">
                <i class="ri-briefcase-line text-4xl mb-4 text-gray-300"></i>
                <p class="text-gray-600">No work experience records found.</p>
                <p class="text-sm text-gray-500 mt-1">Click "Add Experience" to get started.</p>
              </div>
            `;
          } else {
            let html = '';
            result.data.forEach(experience => {
              const startDate = new Date(experience.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
              const endDate = experience.end_date ? new Date(experience.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short' }) : 'Present';
              const location = experience.location ? ' | ' + escapeHtml(experience.location) : '';
              
              html += `
                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
                  <div class="flex items-start justify-between">
                    <div class="flex-1">
                      <h4 class="font-semibold text-gray-900 text-base">${escapeHtml(experience.job_title)}</h4>
                      <p class="text-gray-600 mt-1 text-sm">${escapeHtml(experience.company)}</p>
                      <p class="text-gray-500 text-sm mt-1">${startDate} - ${endDate}${location}</p>
                    </div>
                    <div class="flex space-x-1 ml-4">
                      <button onclick="editExperience(${experience.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
                        <i class="ri-edit-line text-sm"></i>
                      </button>
                      <button onclick="deleteExperience(${experience.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
                        <i class="ri-delete-bin-line text-sm"></i>
                      </button>
                    </div>
                  </div>
                </div>
              `;
            });
            experienceList.innerHTML = html;
          }
        }
      })
      .catch(error => console.error('Error reloading experience:', error));
  }

  function reloadSkillsList() {
    fetch('get_profile_data.php?type=skills')
      .then(response => response.json())
      .then(result => {
        if (result.success) {
          const skillsList = document.getElementById('skillsList');
          if (result.data.length === 0) {
            skillsList.innerHTML = `
              <div class="text-center py-12 text-gray-500">
                <i class="ri-lightbulb-line text-4xl mb-4 text-gray-300"></i>
                <p class="text-gray-600">No skills added yet.</p>
                <p class="text-sm text-gray-500 mt-1">Click "Add Skill" to get started.</p>
              </div>
            `;
          } else {
            let html = '';
            result.data.forEach(skill => {
              const stars = '★'.repeat(skill.skill_level) + '☆'.repeat(5 - skill.skill_level);
              
              html += `
                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
                  <div class="flex items-start justify-between">
                    <div class="flex-1">
                      <h4 class="font-semibold text-gray-900 text-base">${escapeHtml(skill.skill_name)}</h4>
                      <p class="text-secondary text-sm mt-1">${stars}</p>
                    </div>
                    <div class="flex space-x-1 ml-4">
                      <button onclick="editSkill(${skill.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
                        <i class="ri-edit-line text-sm"></i>
                      </button>
                      <button onclick="deleteSkill(${skill.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
                        <i class="ri-delete-bin-line text-sm"></i>
                      </button>
                    </div>
                  </div>
                </div>
              `;
            });
            skillsList.innerHTML = html;
          }
        }
      })
      .catch(error => console.error('Error reloading skills:', error));
  }

  // Helper function to escape HTML - make it globally available
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
  }
  
  // Expose to global scope for inline onclick handlers
  window.escapeHtml = escapeHtml;

  // Handle form submissions with AJAX
  const educationForm = document.getElementById('educationForm');
  const experienceForm = document.getElementById('experienceForm');
  const skillForm = document.getElementById('skillForm');

  if (educationForm) {
    educationForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      console.log('Submitting education form...');
      for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
      }
      
      fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
          throw new Error('HTTP error! status: ' + response.status);
        }
        return response.text();
      })
      .then(text => {
        console.log('Raw response:', text);
        alert('Backend Response: ' + text); // POPUP to force you to see the response
        try {
          const data = JSON.parse(text);
          console.log('Parsed data:', data);
          alert('Success: ' + data.success + ', ID: ' + data.id); // POPUP
          
          if (data.success) {
            // Get form data for immediate display
            const formData = new FormData(educationForm);
            const degree = formData.get('ed_degree');
            const institution = formData.get('ed_ins');
            const startYear = formData.get('ed_sy');
            const endYear = formData.get('ed_ey');
            const gpa = formData.get('ed_gpa');
            
            console.log('📝 Form values - Degree:', degree, 'Institution:', institution);
            
            // Immediately add to list for instant feedback (optimistic update)
            const educationList = document.getElementById('educationList');
            console.log('Education list element:', educationList);
            console.log('Data ID:', data.id);
            
            alert('About to add item. List exists: ' + (educationList ? 'YES' : 'NO') + ', ID: ' + data.id); // POPUP
            
            if (educationList && data.id) {
              // Create HTML-safe versions
              const safeDegree = degree ? String(degree).replace(/[&<>"']/g, function(m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
              }) : '';
              const safeInstitution = institution ? String(institution).replace(/[&<>"']/g, function(m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
              }) : '';
              const safeYears = String(startYear) + ' - ' + String(endYear);
              const safeGpa = gpa ? String(gpa).replace(/[&<>"']/g, function(m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
              }) : '';
              
              const newItem = document.createElement('div');
              newItem.className = 'bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow animate-fade-in';
              newItem.innerHTML = `
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h4 class="font-semibold text-gray-900 text-base">${safeDegree}</h4>
                    <p class="text-gray-600 mt-1 text-sm">${safeInstitution}</p>
                    <p class="text-gray-500 text-sm mt-1">
                      ${safeYears}
                      ${gpa ? ' | GPA: ' + safeGpa : ''}
                    </p>
                  </div>
                  <div class="flex space-x-1 ml-4">
                    <button onclick="editEducation(${data.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
                      <i class="ri-edit-line text-sm"></i>
                    </button>
                    <button onclick="deleteEducation(${data.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
                      <i class="ri-delete-bin-line text-sm"></i>
                    </button>
                  </div>
                </div>
              `;
              
              // Remove "no records" message if it exists
              const noRecordsMsg = educationList.querySelector('.text-center.py-12');
              if (noRecordsMsg) {
                noRecordsMsg.remove();
              }
              
              // Add new item at the top
              educationList.insertBefore(newItem, educationList.firstChild);
              console.log('✅ Education item added to DOM successfully!');
              alert('✅ SUCCESS! Item added to DOM! Check the Education section now!'); // POPUP
            } else {
              console.log('❌ Could not add education: educationList=' + educationList + ', data.id=' + data.id);
              alert('❌ FAILED! List: ' + (educationList ? 'exists' : 'null') + ', ID: ' + data.id); // POPUP
            }
            
            // Close the modal
            const educationModal = document.getElementById('educationModal');
            if (educationModal) {
              educationModal.classList.add('hidden');
            }
            
            // Show success notification
            if (typeof showNotification === 'function') {
              showNotification(data.message || 'Education saved successfully', 'success');
            }
            
            // Reset the form
            educationForm.reset();
          } else {
            if (typeof showNotification === 'function') {
              showNotification(data.message || 'Error saving education', 'error');
            }
          }
        } catch (e) {
          console.error('JSON parse error:', e);
          if (typeof showNotification === 'function') {
            showNotification('Server error: Invalid response format', 'error');
          }
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        if (typeof showNotification === 'function') {
          showNotification('Network error: ' + error.message, 'error');
        }
      });
    });
  }

  if (experienceForm) {
    experienceForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      console.log('Submitting experience form...');
      for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
      }
      
      fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
          throw new Error('HTTP error! status: ' + response.status);
        }
        return response.text();
      })
      .then(text => {
        console.log('Raw response:', text);
        try {
          const data = JSON.parse(text);
          if (data.success) {
            // Get form data for immediate display
            const formData = new FormData(experienceForm);
            const jobTitle = formData.get('job_title');
            const company = formData.get('work_comp');
            const location = formData.get('work_loc');
            const startDate = formData.get('start_date');
            const endDate = formData.get('end_date');
            const isCurrent = formData.get('is_current');
            
            // Immediately add to list for instant feedback
            const experienceList = document.getElementById('experienceList');
            console.log('Experience list element:', experienceList);
            console.log('Data ID:', data.id);
            
            if (experienceList && data.id) {
              // Create HTML-safe versions
              const safeJobTitle = jobTitle ? String(jobTitle).replace(/[&<>"']/g, function(m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
              }) : '';
              const safeCompany = company ? String(company).replace(/[&<>"']/g, function(m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
              }) : '';
              const safeLocation = location ? String(location).replace(/[&<>"']/g, function(m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
              }) : '';
              
              const startDateFormatted = new Date(startDate + '-01').toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
              const endDateFormatted = isCurrent ? 'Present' : (endDate ? new Date(endDate + '-01').toLocaleDateString('en-US', { year: 'numeric', month: 'short' }) : 'Present');
              const locationText = location ? ' | ' + safeLocation : '';
              
              const newItem = document.createElement('div');
              newItem.className = 'bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow animate-fade-in';
              newItem.innerHTML = `
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h4 class="font-semibold text-gray-900 text-base">${safeJobTitle}</h4>
                    <p class="text-gray-600 mt-1 text-sm">${safeCompany}</p>
                    <p class="text-gray-500 text-sm mt-1">${startDateFormatted} - ${endDateFormatted}${locationText}</p>
                  </div>
                  <div class="flex space-x-1 ml-4">
                    <button onclick="editExperience(${data.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
                      <i class="ri-edit-line text-sm"></i>
                    </button>
                    <button onclick="deleteExperience(${data.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
                      <i class="ri-delete-bin-line text-sm"></i>
                    </button>
                  </div>
                </div>
              `;
              
              // Remove "no records" message if it exists
              const noRecordsMsg = experienceList.querySelector('.text-center.py-12');
              if (noRecordsMsg) {
                noRecordsMsg.remove();
              }
              
              // Add new item at the top
              experienceList.insertBefore(newItem, experienceList.firstChild);
              console.log('✅ Experience item added to DOM successfully!');
            } else {
              console.log('❌ Could not add experience: experienceList=' + experienceList + ', data.id=' + data.id);
            }
            
            // Close the modal
            const experienceModal = document.getElementById('experienceModal');
            if (experienceModal) {
              experienceModal.classList.add('hidden');
            }
            
            // Show success notification
            if (typeof showNotification === 'function') {
              showNotification(data.message || 'Work experience saved successfully', 'success');
            }
            
            // Reset the form
            experienceForm.reset();
          } else {
            if (typeof showNotification === 'function') {
              showNotification(data.message || 'Error saving experience', 'error');
            }
          }
        } catch (e) {
          console.error('JSON parse error:', e);
          if (typeof showNotification === 'function') {
            showNotification('Server error: Invalid response format', 'error');
          }
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        if (typeof showNotification === 'function') {
          showNotification('Network error: ' + error.message, 'error');
        }
      });
    });
  }

  if (skillForm) {
    skillForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      
      console.log('Submitting skill form...');
      for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
      }
      
      fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
          throw new Error('HTTP error! status: ' + response.status);
        }
        return response.text();
      })
      .then(text => {
        console.log('Raw response:', text);
        try {
          const data = JSON.parse(text);
          if (data.success) {
            // Get form data for immediate display
            const formData = new FormData(skillForm);
            const skillName = formData.get('skill_name');
            const skillLevel = parseInt(formData.get('skill_level'));
            
            // Immediately add to list for instant feedback
            const skillsList = document.getElementById('skillsList');
            console.log('Skills list element:', skillsList);
            console.log('Data ID:', data.id);
            console.log('Skill level:', skillLevel);
            
            if (skillsList && data.id && skillLevel > 0) {
              // Create HTML-safe version
              const safeSkillName = skillName ? String(skillName).replace(/[&<>"']/g, function(m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
              }) : '';
              
              const stars = '★'.repeat(skillLevel) + '☆'.repeat(5 - skillLevel);
              
              const newItem = document.createElement('div');
              newItem.className = 'bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow animate-fade-in';
              newItem.innerHTML = `
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <h4 class="font-semibold text-gray-900 text-base">${safeSkillName}</h4>
                    <p class="text-secondary text-sm mt-1">${stars}</p>
                  </div>
                  <div class="flex space-x-1 ml-4">
                    <button onclick="editSkill(${data.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded transition-colors" title="Edit">
                      <i class="ri-edit-line text-sm"></i>
                    </button>
                    <button onclick="deleteSkill(${data.id})" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded transition-colors" title="Delete">
                      <i class="ri-delete-bin-line text-sm"></i>
                    </button>
                  </div>
                </div>
              `;
              
              // Remove "no records" message if it exists
              const noRecordsMsg = skillsList.querySelector('.text-center.py-12');
              if (noRecordsMsg) {
                noRecordsMsg.remove();
              }
              
              // Add new item at the top
              skillsList.insertBefore(newItem, skillsList.firstChild);
              console.log('✅ Skill item added to DOM successfully!');
            } else {
              console.log('❌ Could not add skill: skillsList=' + skillsList + ', data.id=' + data.id + ', skillLevel=' + skillLevel);
            }
            
            // Close the modal
            const skillModal = document.getElementById('skillModal');
            if (skillModal) {
              skillModal.classList.add('hidden');
            }
            
            // Show success notification
            if (typeof showNotification === 'function') {
              showNotification(data.message || 'Skill saved successfully', 'success');
            }
            
            // Reset the form and skill level buttons
            skillForm.reset();
            const skillLevelButtons = document.querySelectorAll('[data-level]');
            skillLevelButtons.forEach(btn => {
              btn.classList.remove('bg-primary');
              btn.classList.add('bg-gray-300');
            });
          } else {
            if (typeof showNotification === 'function') {
              showNotification(data.message || 'Error saving skill', 'error');
            }
          }
        } catch (e) {
          console.error('JSON parse error:', e);
          if (typeof showNotification === 'function') {
            showNotification('Server error: Invalid response format', 'error');
          }
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        if (typeof showNotification === 'function') {
          showNotification('Network error: ' + error.message, 'error');
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

<!-- Custom Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
  <div class="bg-white rounded-xl max-w-md w-full mx-4 p-6 relative z-50" onclick="event.stopPropagation()">
    <div class="text-center">
      <!-- Icon -->
      <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
        <i class="ri-delete-bin-line text-3xl text-red-600"></i>
      </div>
      
      <!-- Title -->
      <h3 class="text-xl font-semibold text-gray-900 mb-2">Confirm Delete</h3>
      
      <!-- Message -->
      <p class="text-gray-600 mb-6" id="deleteConfirmMessage">Are you sure you want to delete this item? This action cannot be undone.</p>
      
      <!-- Buttons -->
      <div class="flex gap-3">
        <button type="button" id="cancelDeleteBtn" onclick="window.handleCancelDelete(event)" class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium" style="cursor: pointer; z-index: 100; position: relative;">
          Cancel
        </button>
        <button type="button" id="confirmDeleteBtn" onclick="window.handleConfirmDelete(event)" class="flex-1 px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium" style="cursor: pointer; z-index: 100; position: relative;">
          Delete
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Education Modal -->
<div id="educationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-xl max-w-md w-full mx-4">
    <form method="POST" action="" class="p-6 space-y-4" id="educationForm">
      <input type="hidden" name="saveEducation" value="1">
      <input type="hidden" name="edit_id" id="edit_education_id" value="">
      <div class="border-b border-gray-200 flex justify-between items-center pb-4">
        <h3 class="text-lg font-semibold text-gray-900" id="educationModalTitle">Add Education</h3>
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
      <input type="hidden" name="saveExperience" value="1">
      <input type="hidden" name="edit_id" id="edit_experience_id" value="">
      <div class="border-b border-gray-200 flex justify-between items-center pb-4">
        <h3 class="text-lg font-semibold text-gray-900" id="experienceModalTitle">Add Work Experience</h3>
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
          <input type="month" name="end_date" id="end_date" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
        </div>
      </div>

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
      <input type="hidden" name="saveSkill" value="1">
      <input type="hidden" name="edit_id" id="edit_skill_id" value="">
      <div class="border-b border-gray-200 flex justify-between items-center pb-4">
        <h3 class="text-lg font-semibold text-gray-900" id="skillModalTitle">Add Skill</h3>
        <button type="button" id="closeSkillModal" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600">
          <i class="ri-close-line text-xl"></i>
        </button>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2" for="skill_name">Skill</label>
        <input type="text" name="skill_name" id="skill_name" placeholder="Enter skill name (e.g., JavaScript, Communication, Project Management)" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
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

<script id="editDeleteFunctions">
// Education Edit/Delete Functions
function editEducation(id) {
  // Fetch education data from the page
  const educationItems = <?php echo json_encode($education_data); ?>;
  const education = educationItems.find(item => item.id == id);
  
  if (!education) {
    showNotification('Education record not found', 'error');
    return;
  }
  
  // Populate form fields
  document.getElementById('edit_education_id').value = education.id;
  document.getElementById('ed_degree').value = education.degree;
  document.getElementById('ed_fs').value = education.field_of_study;
  document.getElementById('ed_ins').value = education.institution;
  document.getElementById('ed_sy').value = education.start_year;
  document.getElementById('ed_ey').value = education.end_year;
  document.getElementById('ed_gpa').value = education.gpa || '';
  
  // Update modal title and button
  document.getElementById('educationModalTitle').textContent = 'Edit Education';
  document.getElementById('saveEducationBtn').textContent = 'Update Education';
  
  // Show modal
  const modal = document.getElementById('educationModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function deleteEducation(id) {
  showDeleteConfirm('Are you sure you want to delete this education record? This action cannot be undone.', function() {
    fetch('save_profile_data.php', {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'delete_education=1&id=' + id
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showNotification(data.message, 'success');
        // Remove item from DOM immediately
        const educationItems = document.querySelectorAll('#educationList > div');
        educationItems.forEach(item => {
          if (item.querySelector(`button[onclick="deleteEducation(${id})"]`)) {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            setTimeout(() => item.remove(), 300);
          }
        });
      } else {
        showNotification('Error: ' + data.message, 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showNotification('Error deleting education record', 'error');
    });
  });
}

// Experience Edit/Delete Functions
function editExperience(id) {
  const experienceItems = <?php echo json_encode($experience_data); ?>;
  const experience = experienceItems.find(item => item.id == id);
  
  if (!experience) {
    showNotification('Experience record not found', 'error');
    return;
  }
  
  // Populate form fields
  document.getElementById('edit_experience_id').value = experience.id;
  document.getElementById('job_title').value = experience.job_title;
  document.getElementById('work_comp').value = experience.company;
  document.getElementById('work_loc').value = experience.location || '';
  
  // Format dates for month input (YYYY-MM)
  const startDate = experience.start_date.substring(0, 7);
  document.getElementById('start_date').value = startDate;
  
  if (experience.end_date) {
    const endDate = experience.end_date.substring(0, 7);
    document.getElementById('end_date').value = endDate;
  } else {
    document.getElementById('end_date').value = '';
  }
  
  // Update modal title and button
  document.getElementById('experienceModalTitle').textContent = 'Edit Work Experience';
  document.getElementById('saveExperienceBtn').textContent = 'Update Experience';
  
  // Show modal
  const modal = document.getElementById('experienceModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function deleteExperience(id) {
  showDeleteConfirm('Are you sure you want to delete this work experience? This action cannot be undone.', function() {
    fetch('save_profile_data.php', {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'delete_experience=1&id=' + id
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showNotification(data.message, 'success');
        // Remove item from DOM immediately
        const experienceItems = document.querySelectorAll('#experienceList > div');
        experienceItems.forEach(item => {
          if (item.querySelector(`button[onclick="deleteExperience(${id})"]`)) {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            setTimeout(() => item.remove(), 300);
          }
        });
      } else {
        showNotification('Error: ' + data.message, 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showNotification('Error deleting experience record', 'error');
    });
  });
}

// Skill Edit/Delete Functions
function editSkill(id) {
  const skillItems = <?php echo json_encode($skills_data); ?>;
  const skill = skillItems.find(item => item.id == id);
  
  if (!skill) {
    showNotification('Skill record not found', 'error');
    return;
  }
  
  // Populate form fields
  document.getElementById('edit_skill_id').value = skill.id;
  document.getElementById('skill_name').value = skill.skill_name;
  document.getElementById('skill_level').value = skill.skill_level;
  
  // Update skill level buttons
  const skillLevelButtons = document.querySelectorAll('.skill-level');
  skillLevelButtons.forEach((btn, index) => {
    if (index < skill.skill_level) {
      btn.classList.remove('bg-gray-300');
      btn.classList.add('bg-primary');
    } else {
      btn.classList.remove('bg-primary');
      btn.classList.add('bg-gray-300');
    }
  });
  
  // Update modal title and button
  document.getElementById('skillModalTitle').textContent = 'Edit Skill';
  document.getElementById('saveSkillBtn').textContent = 'Update Skill';
  
  // Show modal
  const modal = document.getElementById('skillModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function deleteSkill(id) {
  showDeleteConfirm('Are you sure you want to delete this skill? This action cannot be undone.', function() {
    fetch('save_profile_data.php', {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'delete_skill=1&id=' + id
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showNotification(data.message, 'success');
        // Remove item from DOM immediately
        const skillItems = document.querySelectorAll('#skillsList > div');
        skillItems.forEach(item => {
          if (item.querySelector(`button[onclick="deleteSkill(${id})"]`)) {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            setTimeout(() => item.remove(), 300);
          }
        });
      } else {
        showNotification('Error: ' + data.message, 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showNotification('Error deleting skill record', 'error');
    });
  });
}

// Reset forms when adding new items
document.addEventListener('DOMContentLoaded', function() {
  const addEducationBtn = document.getElementById('addEducationBtn');
  const addExperienceBtn = document.getElementById('addExperienceBtn');
  const addSkillBtn = document.getElementById('addSkillBtn');
  
  if (addEducationBtn) {
    addEducationBtn.addEventListener('click', function() {
      document.getElementById('educationForm').reset();
      document.getElementById('edit_education_id').value = '';
      document.getElementById('educationModalTitle').textContent = 'Add Education';
      document.getElementById('saveEducationBtn').textContent = 'Add Education';
    });
  }
  
  if (addExperienceBtn) {
    addExperienceBtn.addEventListener('click', function() {
      document.getElementById('experienceForm').reset();
      document.getElementById('edit_experience_id').value = '';
      document.getElementById('experienceModalTitle').textContent = 'Add Work Experience';
      document.getElementById('saveExperienceBtn').textContent = 'Add Experience';
    });
  }
  
  if (addSkillBtn) {
    addSkillBtn.addEventListener('click', function() {
      document.getElementById('skillForm').reset();
      document.getElementById('edit_skill_id').value = '';
      document.getElementById('skillModalTitle').textContent = 'Add Skill';
      document.getElementById('saveSkillBtn').textContent = 'Add Skill';
      
      // Reset skill level buttons
      const skillLevelButtons = document.querySelectorAll('.skill-level');
      skillLevelButtons.forEach(btn => {
        btn.classList.remove('bg-primary');
        btn.classList.add('bg-gray-300');
      });
      document.getElementById('skill_level').value = '0';
    });
  }
});
</script>

<script id="deleteConfirmModal">
// Custom delete confirmation modal functionality
let deleteCallback = null;

function showDeleteConfirm(message, callback) {
  console.log('🚨 showDeleteConfirm called');
  console.log('Message:', message);
  console.log('Callback type:', typeof callback);
  
  const modal = document.getElementById('deleteConfirmModal');
  const messageElement = document.getElementById('deleteConfirmMessage');
  
  console.log('Modal element:', modal);
  console.log('Message element:', messageElement);
  
  if (modal && messageElement) {
    messageElement.textContent = message;
    deleteCallback = callback;
    
    // Use both class and inline style for maximum compatibility
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    console.log('✅ Modal should now be visible');
    console.log('Modal display style:', modal.style.display);
    console.log('Modal classes:', modal.className);
  } else {
    console.error('❌ Modal or message element not found!');
  }
}

function hideDeleteConfirm() {
  console.log('🚪 hideDeleteConfirm called');
  const modal = document.getElementById('deleteConfirmModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    deleteCallback = null;
    console.log('✅ Modal hidden');
  } else {
    console.error('❌ Modal not found in hideDeleteConfirm');
  }
}

// Set up event listeners for delete modal - with debugging
document.addEventListener('DOMContentLoaded', function() {
  console.log('🔧 Setting up delete modal event listeners...');
  
  const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  const modal = document.getElementById('deleteConfirmModal');
  
  console.log('Cancel button:', cancelDeleteBtn);
  console.log('Confirm button:', confirmDeleteBtn);
  console.log('Modal:', modal);
  
  if (cancelDeleteBtn) {
    cancelDeleteBtn.addEventListener('click', function(e) {
      console.log('❌ Cancel button clicked');
      e.preventDefault();
      e.stopPropagation();
      hideDeleteConfirm();
    });
    console.log('✅ Cancel listener added');
  } else {
    console.error('❌ Cancel button not found!');
  }
  
  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', function(e) {
      console.log('✅ Confirm button clicked');
      e.preventDefault();
      e.stopPropagation();
      if (deleteCallback && typeof deleteCallback === 'function') {
        console.log('🗑️ Executing delete callback...');
        deleteCallback();
      } else {
        console.error('❌ No delete callback function!');
      }
      hideDeleteConfirm();
    });
    console.log('✅ Confirm listener added');
  } else {
    console.error('❌ Confirm button not found!');
  }
  
  // Close modal when clicking outside
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        console.log('🚪 Clicked outside modal, closing...');
        hideDeleteConfirm();
      }
    });
    console.log('✅ Backdrop click listener added');
  } else {
    console.error('❌ Modal not found!');
  }
  
  console.log('✅ Delete modal setup complete');
});

// Inline onclick handlers for buttons
window.handleCancelDelete = function(event) {
  console.log('🔴 handleCancelDelete called via onclick');
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  hideDeleteConfirm();
};

window.handleConfirmDelete = function(event) {
  console.log('🔴 handleConfirmDelete called via onclick');
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  
  if (deleteCallback && typeof deleteCallback === 'function') {
    console.log('🗑️ Executing delete callback...');
    deleteCallback();
  } else {
    console.error('❌ No delete callback function!');
  }
  
  hideDeleteConfirm();
};

// Make functions globally available
window.showDeleteConfirm = showDeleteConfirm;
window.hideDeleteConfirm = hideDeleteConfirm;
</script>

<script id="passwordVisibilityToggle">
// Toggle password visibility function
function togglePasswordVisibility(inputId, button) {
  const input = document.getElementById(inputId);
  const icon = button.querySelector('i');
  
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('ri-eye-line');
    icon.classList.add('ri-eye-off-line');
  } else {
    input.type = 'password';
    icon.classList.remove('ri-eye-off-line');
    icon.classList.add('ri-eye-line');
  }
}
</script>

<script id="passwordUpdate">
// Handle password update functionality
// Run immediately since page is loaded via AJAX and DOMContentLoaded may have already fired
(function() {
  console.log('Password update script loaded');
  
  // Use setTimeout to ensure DOM elements are available
  setTimeout(function() {
    const updatePasswordBtn = document.getElementById('updatePasswordBtn');
    const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
    const currentPassword = document.getElementById('currentPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    console.log('Update button found:', updatePasswordBtn);
    console.log('Password fields:', {currentPassword, newPassword, confirmPassword});
  
  if (updatePasswordBtn) {
    updatePasswordBtn.addEventListener('click', function(e) {
      e.preventDefault();
      console.log('Update password button clicked');
      
      // Get field values
      const currentPwd = currentPassword.value.trim();
      const newPwd = newPassword.value.trim();
      const confirmPwd = confirmPassword.value.trim();
      
      console.log('Password lengths:', {
        current: currentPwd.length,
        new: newPwd.length,
        confirm: confirmPwd.length
      });
      
      // Validate fields
      if (!currentPwd || !newPwd || !confirmPwd) {
        console.log('Validation failed: Empty fields');
        showToast('Please fill in all password fields', 'error');
        return;
      }
      
      // Validate password length
      if (newPwd.length < 8) {
        console.log('Validation failed: Password too short');
        showToast('New password must be at least 8 characters long', 'error');
        return;
      }
      
      // Validate password contains numbers
      if (!/[0-9]/.test(newPwd)) {
        console.log('Validation failed: Password must contain numbers');
        showToast('Password must contain at least one number', 'error');
        return;
      }
      
      // Validate password contains symbols
      if (!/[^A-Za-z0-9]/.test(newPwd)) {
        console.log('Validation failed: Password must contain symbols');
        showToast('Password must contain at least one symbol (e.g., !@#$%^&*)', 'error');
        return;
      }
      
      // Check if passwords match
      if (newPwd !== confirmPwd) {
        console.log('Validation failed: Passwords do not match');
        showToast('New passwords do not match', 'error');
        return;
      }
      
      // Check if new password is different from current
      if (currentPwd === newPwd) {
        console.log('Validation failed: Same as current password');
        showToast('New password must be different from current password', 'warning');
        return;
      }
      
      console.log('All validations passed, sending request...');
      
      // Disable button and show loading
      updatePasswordBtn.disabled = true;
      updatePasswordBtn.innerHTML = '<i class="ri-loader-4-line mr-2 animate-spin"></i>Updating...';
      
      // Send password update request
      const formData = new FormData();
      formData.append('updatePassword', '1');
      formData.append('current_password', currentPwd);
      formData.append('new_password', newPwd);
      
      console.log('Sending request to save_profile_data.php');
      
      fetch('save_profile_data.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log('Response received:', response);
        return response.json();
      })
      .then(data => {
        console.log('Response data:', data);
        if (data.success) {
          showToast(data.message, 'success');
          // Clear all password fields
          currentPassword.value = '';
          newPassword.value = '';
          confirmPassword.value = '';
          // Re-add readonly to prevent autofill
          currentPassword.setAttribute('readonly', 'readonly');
          newPassword.setAttribute('readonly', 'readonly');
          confirmPassword.setAttribute('readonly', 'readonly');
        } else {
          showToast(data.message || 'Error updating password', 'error');
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        showToast('An error occurred while updating password', 'error');
      })
      .finally(() => {
        console.log('Request completed');
        // Re-enable button
        updatePasswordBtn.disabled = false;
        updatePasswordBtn.innerHTML = '<i class="ri-lock-password-line mr-2"></i>Update Password';
      });
    });
  } else {
    console.error('Update password button not found!');
  }
  
  // Cancel button handler
  if (cancelPasswordBtn) {
    cancelPasswordBtn.addEventListener('click', function() {
      console.log('Cancel button clicked');
      // Clear all password fields
      if (currentPassword) {
        currentPassword.value = '';
        currentPassword.setAttribute('readonly', 'readonly');
      }
      if (newPassword) {
        newPassword.value = '';
        newPassword.setAttribute('readonly', 'readonly');
      }
      if (confirmPassword) {
        confirmPassword.value = '';
        confirmPassword.setAttribute('readonly', 'readonly');
      }
      showToast('Password update cancelled', 'warning');
    });
  }
  }, 100); // End setTimeout - wait 100ms for DOM to be ready
})(); // End IIFE - run immediately
</script>

</body>
</html>