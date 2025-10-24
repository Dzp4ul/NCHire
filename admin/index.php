<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "12345678";
$dbname = "nchire";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get admin info from session
$admin_name = $_SESSION['admin_name'] ?? 'Admin User';
$admin_role = $_SESSION['admin_role'] ?? 'Admin';
$admin_profile_picture = $_SESSION['admin_profile_picture'] ?? '';
$admin_email = $_SESSION['admin_email'] ?? '';
$admin_department = $_SESSION['admin_department'] ?? '';

// Get initials for profile picture fallback
$initials = 'A';
if (!empty($admin_name)) {
    $name_parts = explode(' ', trim($admin_name));
    if (count($name_parts) >= 2) {
        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
    } else {
        $initials = strtoupper(substr($name_parts[0], 0, 1));
    }
}

// Get dashboard statistics from job_applicants table
$stats = [];

// Total Applications (excluding rejected/archived)
$result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status != 'Rejected'");
$stats['total_applicants'] = $result ? $result->fetch_assoc()['count'] : 0;

// Archived (Rejected) Applications
$result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Rejected'");
$stats['archived'] = $result ? $result->fetch_assoc()['count'] : 0;

// Total Jobs from job table
$result = $conn->query("SELECT COUNT(*) as count FROM job");
$stats['total_jobs'] = $result ? $result->fetch_assoc()['count'] : 0;

// Active Users (unique applicants)
$result = $conn->query("SELECT COUNT(DISTINCT full_name) as count FROM job_applicants");
$stats['active_users'] = $result ? $result->fetch_assoc()['count'] : 0;

// Pending Reviews (Under Review)
$result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Pending'");
$stats['pending_reviews'] = $result ? $result->fetch_assoc()['count'] : 0;

// Interview Scheduled
$result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Interview Scheduled'");
$stats['interview_scheduled'] = $result ? $result->fetch_assoc()['count'] : 0;

// Hired
$result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE status = 'Hired'");
$stats['hired'] = $result ? $result->fetch_assoc()['count'] : 0;

// Get recent applications (last 5)
$recent_applicants_query = "SELECT * FROM job_applicants ORDER BY applied_date DESC LIMIT 5";
$recent_applicants = $conn->query($recent_applicants_query);

// Get recent jobs from job table with application counts
$recent_jobs_query = "SELECT j.*, COUNT(ja.id) as application_count 
                      FROM job j 
                      LEFT JOIN job_applicants ja ON j.job_title = ja.position 
                      GROUP BY j.id 
                      ORDER BY j.id DESC 
                      LIMIT 5";
$recent_jobs = $conn->query($recent_jobs_query);

// Get weekly application data for chart (last 7 days)
$weekly_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE DATE(applied_date) = '$date'");
    $weekly_data[] = $result ? $result->fetch_assoc()['count'] : 0;
}

// Create admin_activity table if it doesn't exist
$create_activity_table = "CREATE TABLE IF NOT EXISTS admin_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    user_name VARCHAR(100),
    related_table VARCHAR(50),
    related_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_activity_table);

// Get chart filter from URL parameter (default to weekly)
$chart_filter = isset($_GET['chart_filter']) ? $_GET['chart_filter'] : 'weekly';

// Get chart data based on filter
$chart_data = [];
$chart_labels = [];

switch($chart_filter) {
    case 'daily':
        // Last 24 hours by hour
        for ($i = 23; $i >= 0; $i--) {
            $hour = date('Y-m-d H:00:00', strtotime("-$i hours"));
            $next_hour = date('Y-m-d H:00:00', strtotime("-" . ($i-1) . " hours"));
            $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE applied_date >= '$hour' AND applied_date < '$next_hour'");
            $chart_data[] = $result ? $result->fetch_assoc()['count'] : 0;
            $chart_labels[] = date('H:i', strtotime($hour));
        }
        break;
    case 'monthly':
        // Last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE DATE(applied_date) = '$date'");
            $chart_data[] = $result ? $result->fetch_assoc()['count'] : 0;
            $chart_labels[] = date('M j', strtotime($date));
        }
        break;
    case 'yearly':
        // Last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE DATE_FORMAT(applied_date, '%Y-%m') = '$month'");
            $chart_data[] = $result ? $result->fetch_assoc()['count'] : 0;
            $chart_labels[] = date('M Y', strtotime($month . '-01'));
        }
        break;
    default: // weekly
        // Last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $result = $conn->query("SELECT COUNT(*) as count FROM job_applicants WHERE DATE(applied_date) = '$date'");
            $chart_data[] = $result ? $result->fetch_assoc()['count'] : 0;
            $chart_labels[] = date('M j', strtotime($date));
        }
}

// Get comprehensive recent activity from multiple sources - only show applications from last 2 hours
$recent_activity_query = "
    (SELECT 'application' as activity_type, 
            CONCAT(full_name, ' applied for ', position) as description,
            full_name as user_name,
            applied_date as created_at
     FROM job_applicants 
     WHERE applied_date >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
     ORDER BY applied_date DESC LIMIT 5)
    UNION ALL
    (SELECT activity_type, description, user_name, created_at 
     FROM admin_activity 
     ORDER BY created_at DESC LIMIT 10)
    ORDER BY created_at DESC 
    LIMIT 10";
$recent_activity = $conn->query($recent_activity_query);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NCHire Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e3a8a',
                        secondary: '#fbbf24'
                    }
                }
            }
        }
    </script>
    <style>
        .sidebar-transition {
            transition: transform 0.3s ease-in-out;
        }
        .chart-bar {
            transition: all 0.3s ease;
        }
        .chart-bar:hover {
            opacity: 1 !important;
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        
        /* Status Tab Styles */
        .status-tab {
            border-bottom: 2px solid transparent;
            color: #6b7280;
            transition: all 0.2s ease;
        }
        
        .status-tab:hover {
            color: #374151;
            border-bottom-color: #d1d5db;
        }
        
        .status-tab.active-tab {
            color: #1e40af;
            border-bottom-color: #1e40af;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .nav-item {
            transition: all 0.2s ease;
        }
        .nav-item:hover {
            transform: translateX(4px);
        }
        .nav-item.active {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile sidebar overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 z-40 lg:hidden bg-black bg-opacity-50 hidden"></div>

    <!-- Sidebar -->
    <div id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform -translate-x-full lg:translate-x-0 sidebar-transition">
        <div class="flex items-center justify-between h-16 px-6 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <img src="https://static.readdy.ai/image/2d44f09b25f25697de5dc274e7f0a5a3/04242d6bffded145c33d09c9dcfae98c.png" 
                     alt="NCHire Logo" class="w-8 h-8 object-contain">
                <span class="text-xl font-bold text-primary">NCHire Admin</span>
            </div>
            <button id="closeSidebar" class="lg:hidden text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <nav class="mt-8 px-4">
            <button onclick="showSection('dashboard')" class="nav-item w-full flex items-center gap-3 px-4 py-3 text-left rounded-lg mb-2 text-gray-700 hover:bg-gray-100 active">
                <i class="fas fa-chart-line w-5 h-5"></i>
                Dashboard
            </button>
            <button onclick="showSection('jobs')" class="nav-item w-full flex items-center gap-3 px-4 py-3 text-left rounded-lg mb-2 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-briefcase w-5 h-5"></i>
                Job Postings
            </button>
            <button onclick="showSection('applicants')" class="nav-item w-full flex items-center gap-3 px-4 py-3 text-left rounded-lg mb-2 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-user-check w-5 h-5"></i>
                Applicants
            </button>
            <button onclick="showSection('archive')" class="nav-item w-full flex items-center gap-3 px-4 py-3 text-left rounded-lg mb-2 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-archive w-5 h-5"></i>
                Archive
            </button>
            <button onclick="showSection('users')" class="nav-item w-full flex items-center gap-3 px-4 py-3 text-left rounded-lg mb-2 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-users w-5 h-5"></i>
                Users
            </button>
        </nav>
    </div>

    <!-- Main content -->
    <div class="lg:ml-64">
        <!-- Top header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between h-16 px-6">
                <div class="flex items-center gap-4">
                    <button id="openSidebar" class="lg:hidden text-gray-500 hover:text-gray-700">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <button id="notificationBtn" class="relative text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-bell text-xl"></i>
                            <span id="notificationBadge" class="hidden absolute top-0 right-0 w-5 h-5 bg-red-500 rounded-full text-white text-xs flex items-center justify-center font-semibold">0</span>
                        </button>
                        
                        <!-- Notifications Dropdown -->
                        <div id="notificationsDropdown" class="hidden absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-96 overflow-hidden flex flex-col">
                            <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gradient-to-r from-blue-600 to-blue-700">
                                <h3 class="font-semibold text-white flex items-center gap-2">
                                    <i class="fas fa-bell"></i>
                                    Notifications
                                </h3>
                                <button onclick="markAllAsRead()" class="text-xs text-blue-100 hover:text-white transition-colors">
                                    Mark all as read
                                </button>
                            </div>
                            <div id="notificationsList" class="overflow-y-auto flex-1">
                                <div class="p-4 text-center text-gray-500">
                                    <i class="fas fa-bell-slash text-3xl mb-2"></i>
                                    <p>Loading notifications...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="relative">
                        <button id="profileDropdownBtn" class="flex items-center gap-3 hover:bg-gray-50 rounded-lg px-3 py-2 transition-colors">
                            <?php if (!empty($admin_profile_picture) && file_exists("../uploads/profile_pictures/" . $admin_profile_picture)): ?>
                                <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($admin_profile_picture); ?>" 
                                     alt="<?php echo htmlspecialchars($admin_name); ?>" 
                                     class="w-8 h-8 rounded-full object-cover border-2 border-primary">
                            <?php else: ?>
                                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white font-semibold">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex flex-col text-left">
                                <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($admin_name); ?></span>
                                <span class="text-xs text-gray-500"><?php echo htmlspecialchars($admin_role); ?></span>
                            </div>
                            <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                        </button>
                        
                        <!-- Profile Dropdown Menu -->
                        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <div class="p-3 border-b border-gray-200">
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($admin_name); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($admin_email); ?></p>
                            </div>
                            <div class="py-2">
                                <button onclick="openMyProfileModal()" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fas fa-user-circle w-4"></i>
                                    <span>My Profile</span>
                                </button>
                                <hr class="my-2">
                                <a href="#" onclick="confirmLogout(event)" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt w-4"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page content -->
        <main class="p-6">
            <!-- Dashboard Section -->
            <div id="dashboardSection" class="section">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Jobs</p>
                                <p class="text-2xl font-bold text-gray-900" data-stat="total_jobs"><?php echo $stats['total_jobs']; ?></p>
                                
                            </div>
                            <div class="p-3 rounded-lg bg-blue-500">
                                <i class="fas fa-briefcase text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Applicants</p>
                                <p class="text-2xl font-bold text-gray-900" data-stat="total_applicants"><?php echo $stats['total_applicants']; ?></p>
                                
                            </div>
                            <div class="p-3 rounded-lg bg-green-500">
                                <i class="fas fa-user-check text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Active Users</p>
                                <p class="text-2xl font-bold text-gray-900" data-stat="active_users"><?php echo $stats['active_users']; ?></p>
                                
                            </div>
                            <div class="p-3 rounded-lg bg-purple-500">
                                <i class="fas fa-users text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Pending Reviews</p>
                                <p class="text-2xl font-bold text-gray-900" data-stat="pending_reviews"><?php echo $stats['pending_reviews']; ?></p>
                                
                            </div>
                            <div class="p-3 rounded-lg bg-orange-500">
                                <i class="fas fa-eye text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Applications Chart -->
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Applications Overview</h2>
                            <select id="chartFilter" onchange="updateChart()" class="text-sm border border-gray-300 rounded-md px-3 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="daily" <?php echo $chart_filter == 'daily' ? 'selected' : ''; ?>>Daily (24h)</option>
                                <option value="weekly" <?php echo $chart_filter == 'weekly' ? 'selected' : ''; ?>>Weekly (7d)</option>
                                <option value="monthly" <?php echo $chart_filter == 'monthly' ? 'selected' : ''; ?>>Monthly (30d)</option>
                                <option value="yearly" <?php echo $chart_filter == 'yearly' ? 'selected' : ''; ?>>Yearly (12m)</option>
                            </select>
                        </div>
                        <div class="h-80 overflow-x-auto overflow-y-hidden flex items-end pb-8" id="chartContainer">
                            <div class="flex items-end justify-start gap-3 h-5/6 min-w-max px-4">
                                <?php 
                                $max_value = max($chart_data) ?: 1; // Avoid division by zero
                                for ($i = 0; $i < count($chart_data); $i++): 
                                    $height = ($chart_data[$i] / $max_value) * 280; // Scale to max 280px
                                    $height = max($height, 20); // Minimum height for visibility
                                ?>
                                    <div class="flex flex-col items-center" style="min-width: 80px;">
                                        <div class="w-16 bg-primary rounded-t chart-bar opacity-80 hover:opacity-100 relative group transition-all duration-200" style="height: <?php echo $height; ?>px;">
                                            <div class="absolute -top-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs px-3 py-2 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 shadow-lg">
                                                <?php echo $chart_data[$i]; ?> applications
                                            </div>
                                        </div>
                                        <span class="text-sm text-gray-600 mt-3 text-center font-medium break-words" style="width: 70px;"><?php echo $chart_labels[$i]; ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h2>
                        <div class="h-80 overflow-y-auto space-y-4 pr-2" id="recentActivityContainer">
                            <?php if ($recent_activity && $recent_activity->num_rows > 0): ?>
                                <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                    <div class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg transition-colors">
                                        <?php
                                        $icon_class = 'fas fa-user-plus text-green-600';
                                        $bg_class = 'bg-green-100';
                                        $activity_title = 'New application received';
                                        
                                        switch($activity['activity_type']) {
                                            case 'job_created':
                                                $icon_class = 'fas fa-briefcase text-blue-600';
                                                $bg_class = 'bg-blue-100';
                                                $activity_title = 'Job posting created';
                                                break;
                                            case 'job_edited':
                                                $icon_class = 'fas fa-edit text-orange-600';
                                                $bg_class = 'bg-orange-100';
                                                $activity_title = 'Job posting updated';
                                                break;
                                            case 'job_deleted':
                                                $icon_class = 'fas fa-trash text-red-600';
                                                $bg_class = 'bg-red-100';
                                                $activity_title = 'Job posting deleted';
                                                break;
                                            case 'status_changed':
                                                $icon_class = 'fas fa-exchange-alt text-purple-600';
                                                $bg_class = 'bg-purple-100';
                                                $activity_title = 'Application status changed';
                                                break;
                                            case 'admin_login':
                                                $icon_class = 'fas fa-sign-in-alt text-indigo-600';
                                                $bg_class = 'bg-indigo-100';
                                                $activity_title = 'Admin logged in';
                                                break;
                                            case 'data_export':
                                                $icon_class = 'fas fa-download text-teal-600';
                                                $bg_class = 'bg-teal-100';
                                                $activity_title = 'Data exported';
                                                break;
                                        }
                                        ?>
                                        <div class="w-8 h-8 <?php echo $bg_class; ?> rounded-full flex items-center justify-center flex-shrink-0">
                                            <i class="<?php echo $icon_class; ?> text-sm"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate"><?php echo $activity_title; ?></p>
                                            <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($activity['description']); ?></p>
                                        </div>
                                        <span class="text-xs text-gray-400 flex-shrink-0"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></span>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <p class="text-gray-500">No recent activity</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Tables -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Jobs -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Recent Job Postings</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Job Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applications</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200" id="recentJobsContainer">
                                    <?php if ($recent_jobs && $recent_jobs->num_rows > 0): ?>
                                        <?php while ($job = $recent_jobs->fetch_assoc()): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4">
                                                    <div>
                                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($job['job_title']); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars(string: $job['department_role'] ?? 'General'); ?></div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo $job['application_count']; ?></td>
                                                <td class="px-6 py-4">
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        Active
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">No jobs found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Applicants -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Recent Applicants</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if ($recent_applicants && $recent_applicants->num_rows > 0): ?>
                                        <?php while ($applicant = $recent_applicants->fetch_assoc()): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4">
                                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($applicant['full_name']); ?></div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($applicant['position']); ?></div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php
                                                    $status = $applicant['status'] ?? 'Pending';
                                                    $statusClass = '';
                                                    $statusText = '';
                                                    
                                                    switch($status) {
                                                        case 'Approved':
                                                            $statusClass = 'bg-green-100 text-green-800';
                                                            $statusText = 'Approved';
                                                            break;
                                                        case 'Rejected':
                                                            $statusClass = 'bg-red-100 text-red-800';
                                                            $statusText = 'Rejected';
                                                            break;
                                                        case 'Interview':
                                                            $statusClass = 'bg-blue-100 text-blue-800';
                                                            $statusText = 'Interview Scheduled';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                                            $statusText = 'Under Review';
                                                    }
                                                    ?>
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">No recent applicants</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job Postings Section -->
            <div id="jobsSection" class="section hidden">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Job Postings</h1>
                    <button onclick="openJobTypeSelectionModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition-colors flex items-center gap-2">
    <i class="fas fa-plus"></i>
    Create Job
</button>
                </div>

                <!-- Filters -->
                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text" placeholder="Search jobs..." 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <select class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="all">All Status</option>
                                <option value="active">Active</option>
                                <option value="draft">Draft</option>
                                <option value="closed">Closed</option>
                            </select>
                            <button class="border border-gray-300 rounded-lg px-3 py-2 hover:bg-gray-50 transition-colors flex items-center gap-2">
                                <i class="fas fa-filter"></i>
                                More Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Jobs Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Job Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department / Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application Deadline</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200" id="jobsTableBody">
                                <!-- Jobs will be loaded here via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Applicants Section -->
            <div id="applicantsSection" class="section hidden">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Applicants</h1>
                    <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <i class="fas fa-download"></i>
                        Export Data
                    </button>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Applicants</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_applicants']; ?></p>
                            </div>
                            <i class="fas fa-user text-2xl text-blue-500"></i>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Under Review</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['pending_reviews']; ?></p>
                            </div>
                            <i class="fas fa-clock text-2xl text-yellow-500"></i>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Interviews Scheduled</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['interview_scheduled']; ?></p>
                            </div>
                            <i class="fas fa-calendar text-2xl text-blue-500"></i>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Hired</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['hired']; ?></p>
                            </div>
                            <i class="fas fa-check-circle text-2xl text-green-500"></i>
                        </div>
                    </div>
                </div>

                <!-- Filter -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6 p-4">
                    <div class="flex items-center gap-2">
                        <label for="statusFilter" class="text-sm font-medium text-gray-700">Filter by Status:</label>
                        <select id="statusFilter" onchange="filterApplicantsByStatus(this.value)" 
                                class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">All Applicants</option>
                            <option value="Pending">Pending</option>
                            <option value="Interview Scheduled">Interview Scheduled</option>
                            <option value="Resubmission Required">Resubmission Required</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Hired">Hired</option>
                        </select>
                    </div>
                </div>

                <!-- Applicants Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applied Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200" id="applicantsTableBody">
                                <!-- Applicants will be loaded here via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Applicant Details Section -->
            <div id="applicantDetailsSection" class="section hidden">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <button onclick="showSection('applicants')" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-arrow-left text-xl"></i>
                        </button>
                        <h1 class="text-2xl font-bold text-gray-900">Applicant Details</h1>
                    </div>
                    <div id="applicantStatus" class="flex items-center gap-3">
                        <!-- Status badge will be inserted here -->
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column - Applicant Info -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Personal Information -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Personal Information</h2>
                            <div id="personalInfo" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Personal info will be loaded here -->
                            </div>
                        </div>

                        <!-- Education -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Education</h2>
                            <div id="educationInfo" class="space-y-4">
                                <!-- Education info will be loaded here -->
                            </div>
                        </div>

                        <!-- Work Experience -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Work Experience</h2>
                            <div id="experienceInfo" class="space-y-4">
                                <!-- Experience info will be loaded here -->
                            </div>
                        </div>

                        <!-- Skills -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Skills</h2>
                            <div id="skillsInfo" class="space-y-4">
                                <!-- Skills info will be loaded here -->
                            </div>
                        </div>

                        <!-- Submitted Documents -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Submitted Documents</h2>
                            <div id="documentsGrid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Documents will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Actions -->
                    <div class="space-y-6">
                        <!-- Action Buttons -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Actions</h2>
                            <div id="actionButtons" class="space-y-3">
                                <button id="scheduleBtn" onclick="openScheduleModal()" 
                                        class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                                    <i class="fas fa-calendar-alt"></i>
                                    Schedule Interview
                                </button>
                                
                                <button id="approveInterviewBtn" onclick="openApproveInterviewModal()" 
                                        class="w-full bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700 transition-colors flex items-center justify-center gap-2 hidden">
                                    <i class="fas fa-user-check"></i>
                                    Approve Interview
                                </button>
                                
                                <button id="rescheduleInterviewBtn" onclick="openRescheduleInterviewModal()" 
                                        class="w-full bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-colors flex items-center justify-center gap-2 hidden">
                                    <i class="fas fa-calendar-edit"></i>
                                    Reschedule Interview
                                </button>
                                
                                <button id="scheduleDemoBtn" onclick="openDemoScheduleModal()" 
                                        class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2 hidden">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    Schedule Demo Teaching
                                </button>
                                
                                <button id="approveDemoBtn" onclick="openApproveDemoModal()" 
                                        class="w-full bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2 hidden">
                                    <i class="fas fa-check-double"></i>
                                    Approve Demo
                                </button>
                                
                                <button id="rescheduleDemoBtn" onclick="openRescheduleDemoModal()" 
                                        class="w-full bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-colors flex items-center justify-center gap-2 hidden">
                                    <i class="fas fa-calendar-edit"></i>
                                    Reschedule Demo Teaching
                                </button>
                                
                                <button id="hireBtn" onclick="openHireModal()" 
                                        class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center gap-2 hidden">
                                    <i class="fas fa-check-circle"></i>
                                    Initially Hire Applicant
                                </button>
                                
                                <button id="permanentHireBtn" onclick="openPermanentHireModal()" 
                                        class="w-full bg-green-700 text-white px-4 py-2 rounded-lg hover:bg-green-800 transition-colors flex items-center justify-center gap-2 hidden">
                                    <i class="fas fa-user-tie"></i>
                                    Permanently Hire
                                </button>
                                
                                <button id="resubmitBtn" onclick="openResubmitModal()" 
                                        class="w-full bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-colors flex items-center justify-center gap-2">
                                    <i class="fas fa-redo"></i>
                                    Request Resubmission
                                </button>
                                
                                <button id="rejectBtn" onclick="openRejectModal()" 
                                        class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                                    <i class="fas fa-times"></i>
                                    Reject Application
                                </button>
                            </div>
                        </div>

                        <!-- Interview Information -->
                        <div id="interviewInfo" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" style="display: none;">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-calendar-check text-blue-600"></i>
                                Interview Details
                            </h2>
                            <div id="interviewDetails" class="space-y-3">
                                <!-- Interview details will be loaded here -->
                            </div>
                        </div>
                        
                        <!-- Demo Teaching Information -->
                        <div id="demoInfo" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" style="display: none;">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chalkboard-teacher text-indigo-600"></i>
                                Demo Teaching Details
                            </h2>
                            <div id="demoDetails" class="space-y-3">
                                <!-- Demo details will be loaded here -->
                            </div>
                        </div>
                        
                        <!-- Psychological Exam Receipt -->
                        <div id="psychReceiptInfo" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" style="display: none;">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-brain text-purple-600"></i>
                                Psychological Exam Receipt
                            </h2>
                            <div id="psychReceiptDetails" class="space-y-3">
                                <!-- Receipt details will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Archive Section -->
            <div id="archiveSection" class="section hidden">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Archive</h1>
                    <p class="text-gray-600">Rejected applicants</p>
                </div>

                <!-- Archive Stats -->
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Archived Applicants</p>
                            <p class="text-2xl font-bold text-gray-900" id="archivedCount">0</p>
                        </div>
                        <i class="fas fa-archive text-3xl text-gray-400"></i>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text" id="archiveSearch" placeholder="Search archived applicants..." 
                                       onkeyup="searchArchive(this.value)"
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Archived Applicants Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applied Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rejected Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200" id="archiveTableBody">
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-gray-500">
                                        <i class="fas fa-archive text-4xl text-gray-300 mb-3"></i>
                                        <p>No archived applicants</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Users Section -->
            <div id="usersSection" class="section hidden">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Users</h1>
                    <button onclick="openCreateUserModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition-colors flex items-center gap-2">
                        <i class="fas fa-plus"></i>
                        Create User
                    </button>
                </div>

                <!-- Users Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200" id="usersTableBody">
                                <!-- Users will be loaded here via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
<!-- Job Type Selection Modal -->
<div id="jobTypeSelectionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg w-full max-w-md m-4">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Select Job Type</h2>
        </div>
        
        <div class="p-6 space-y-4">
            <button onclick="openCreateJobModal('Instructor')" class="w-full bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition-colors">
                Instructor
            </button>
           
            <button type="button" onclick="closeJobTypeSelectionModal()" class="w-full border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>


    
    <!-- Create Job Modal -->
    <div id="createJobModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto m-4">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Create New Job Posting</h2>
            </div>
            
            <form class="p-6 space-y-4" onsubmit="createJob(event)">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                        <input type="text" name="title" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Enter job title">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="department" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select department</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Hospitality Management">Hospitality Management</option>
                            <option value="Education">Education</option>
                            
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Type</label>
                        <select name="type" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" name="location" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Job location">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Range</label>
                        <input type="text" name="salary" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="e.g., ₱25,000 - ₱35,000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Application Deadline</label>
                        <input type="date" name="deadline" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Job Description</label>
                    <textarea name="description" rows="4" required
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                              placeholder="Enter detailed job description"></textarea>
                </div>

                <!-- Minimum Qualifications Section -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">MINIMUM QUALIFICATIONS</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Education</label>
                            <textarea name="education" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., Elementary School Graduate"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Experience</label>
                            <textarea name="experience" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., with no experience required"></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Training</label>
                            <textarea name="training" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., No training required"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Eligibility</label>
                            <textarea name="eligibility" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., None required (MC 11 s. 1996, as amended, Category III)"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Additional Details Section -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">ADDITIONAL DETAILS</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Competency</label>
                        <textarea name="competency" rows="3"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                  placeholder="e.g., Core (Basic): Exemplifying Integrity and Professionalism; Delivering Service Excellence; Demonstrating Personal Effectiveness; Teamwork and Collaboration"></textarea>
                    </div>

                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeCreateJobModal()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        Create Job
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- Create Utility Job Modal -->
    <div id="createutilityJobModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto m-4">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Create New Job Posting</h2>
            </div>
            
            <form class="p-6 space-y-4" onsubmit="createJob(event)">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                        <input type="text" name="uti" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Enter job title">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="department" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Role</option>
                            <option value="Staff">Staff</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Security">Security</option>
                            
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Type</label>
                        <select name="type" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" name="location" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Job location">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Range</label>
                        <input type="text" name="salary" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="e.g., ₱25,000 - ₱35,000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Application Deadline</label>
                        <input type="date" name="deadline" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Job Description</label>
                    <textarea name="description" rows="4" required
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                              placeholder="Enter detailed job description"></textarea>
                </div>

                <!-- Minimum Qualifications Section -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">MINIMUM QUALIFICATIONS</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Education</label>
                            <textarea name="education" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., Elementary School Graduate"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Experience</label>
                            <textarea name="experience" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., with no experience required"></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Training</label>
                            <textarea name="training" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., No training required"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Eligibility</label>
                            <textarea name="eligibility" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., None required (MC 11 s. 1996, as amended, Category III)"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Additional Details Section -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">ADDITIONAL DETAILS</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Competency</label>
                        <textarea name="competency" rows="3"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                  placeholder="e.g., Core (Basic): Exemplifying Integrity and Professionalism; Delivering Service Excellence; Demonstrating Personal Effectiveness; Teamwork and Collaboration"></textarea>
                    </div>

                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeCreateutilityJobModal()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        Create Job
                    </button>
                </div>
            </form>
        </div>
    </div>

<!-- Create secretary Job Modal -->
    <div id="createsecJobModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto m-4">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Create New Job Posting</h2>
            </div>
            
            <form class="p-6 space-y-4" onsubmit="createJob(event)">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                        <input type="text" name="sec" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Enter job title">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="department" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Role</option>
                            <option value="Office Secretary">Office Secretary</option>
                            
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Type</label>
                        <select name="type" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" name="location" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Job location">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Range</label>
                        <input type="text" name="salary" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="e.g., ₱25,000 - ₱35,000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Application Deadline</label>
                        <input type="date" name="deadline" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Job Description</label>
                    <textarea name="description" rows="4" required
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                              placeholder="Enter detailed job description"></textarea>
                </div>

                <!-- Minimum Qualifications Section -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">MINIMUM QUALIFICATIONS</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Education</label>
                            <textarea name="education" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., Elementary School Graduate"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Experience</label>
                            <textarea name="experience" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., with no experience required"></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Training</label>
                            <textarea name="training" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., No training required"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Eligibility</label>
                            <textarea name="eligibility" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                      placeholder="e.g., None required (MC 11 s. 1996, as amended, Category III)"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Additional Details Section -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">ADDITIONAL DETAILS</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Competency</label>
                        <textarea name="competency" rows="3"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                  placeholder="e.g., Core (Basic): Exemplifying Integrity and Professionalism; Delivering Service Excellence; Demonstrating Personal Effectiveness; Teamwork and Collaboration"></textarea>
                    </div>

                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeCreatesecJobModal()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        Create Job
                    </button>
                </div>
            </form>
        </div>
    </div>






     <!-- edit Job Modal -->
    <div id="editJobModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto m-4">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Create New Job Posting</h2>
            </div>
            
            <form class="p-6 space-y-4" onsubmit="saveJob(event)">
                <input type="hidden" name="id" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                        <input type="text" name="title" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Enter job title">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="department" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select department</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Hospitality Management">Hospitality Management</option>
                            <option value="Education">Education</option>
                            
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Type</label>
                        <select name="type" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" name="location" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Job location">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Range</label>
                        <input type="text" name="salary" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="e.g., ₱25,000 - ₱35,000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Application Deadline</label>
                        <input type="date" name="deadline" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Job Description</label>
                    <textarea name="description" rows="4" required
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                              placeholder="Enter detailed job description"></textarea>
                </div>


                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeeditJobModal()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- edit Utility Job Modal -->
    <div id="editutilityJobModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto m-4">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Create New Job Posting</h2>
            </div>
            
            <form class="p-6 space-y-4" onsubmit="saveJob(event)">
                <input type="hidden" name="id" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                        <input type="text" name="uti" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Enter job title">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="department" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Role</option>
                            <option value="Staff">Staff</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Security">Security</option>
                            
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Type</label>
                        <select name="type" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" name="location" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Job location">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Range</label>
                        <input type="text" name="salary" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="e.g., ₱25,000 - ₱35,000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Application Deadline</label>
                        <input type="date" name="deadline" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Job Description</label>
                    <textarea name="description" rows="4" required
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                              placeholder="Enter detailed job description"></textarea>
                </div>


                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeeditutilityJobModal()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

<!-- edit secretary Job Modal -->
    <div id="editsecJobModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto m-4">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Create New Job Posting</h2>
            </div>
            
            <form class="p-6 space-y-4" onsubmit="saveJob(event)">
                <input type="hidden" name="id" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                        <input type="text" name="sec" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Enter job title">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="department" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Role</option>
                            <option value="Office Secretary">Office Secretary</option>
                            
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Job Type</label>
                        <select name="type" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" name="location" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="Job location">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Range</label>
                        <input type="text" name="salary" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="e.g., ₱25,000 - ₱35,000">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Application Deadline</label>
                        <input type="date" name="deadline" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Job Description</label>
                    <textarea name="description" rows="4" required
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                              placeholder="Enter detailed job description"></textarea>
                </div>


                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeeditsecJobModal()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

<div id="viewJobModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
  <div class="bg-white p-6 rounded-lg shadow-lg w-1/3">
    <h2 class="text-xl font-bold mb-4 job-title"></h2>
    <p><strong>Department:</strong> <span class="job-dept"></span></p>
    <p><strong>Type:</strong> <span class="job-type"></span></p>
    <p><strong>Location:</strong> <span class="job-loc"></span></p>
    <p><strong>Salary:</strong> <span class="job-salary"></span></p>
    <p><strong>Deadline:</strong> <span class="job-deadline"></span></p>
    <p><strong>Description:</strong> <span class="job-desc"></span></p>
    <button onclick="closeViewJobModal()" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded">Close</button>
  </div>
</div>

<div id="deleteJobModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
  <div class="bg-white p-6 rounded-lg shadow-lg w-1/3">
    <h2 class="text-xl font-bold mb-4">Delete Job</h2>
    <p>Are you sure you want to delete <span class="job-title font-semibold"></span>?</p>
    <div class="mt-4 flex justify-end gap-2">
      <button onclick="cancelDeleteJob()" class="bg-gray-300 px-4 py-2 rounded">Cancel</button>
      <button onclick="confirmDeleteJob()" class="bg-red-500 text-white px-4 py-2 rounded">Delete</button>
    </div>
  </div>
</div>





    <!-- Create User Modal -->
    <div id="createUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl m-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-900">Create New Admin User</h2>
                    <button onclick="closeCreateUserModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <form class="p-6 space-y-4" onsubmit="createUser(event)" autocomplete="off">
                <!-- Profile Picture Upload - Centered at Top -->
                <div class="flex flex-col items-center mb-6">
                    <div id="profilePreview" class="w-32 h-32 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden border-4 border-gray-300 mb-3">
                        <i class="fas fa-user text-gray-400 text-4xl"></i>
                    </div>
                    <input type="file" name="profile_picture" id="profilePictureInput" accept="image/jpeg,image/png,image/jpg,image/gif" class="hidden">
                    <button type="button" onclick="document.getElementById('profilePictureInput').click()" 
                            class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        <i class="fas fa-camera mr-2"></i>Upload Photo
                    </button>
                    <p class="text-xs text-gray-500 mt-2">JPG, PNG or GIF (max 5MB)</p>
                </div>

                <!-- Personal Information Section -->
                <div class="rounded-lg p-4 mb-4 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-user text-primary mr-2"></i>
                        Personal Information
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" name="name" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter full name">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input type="email" name="email" required autocomplete="off"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter email address">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                            <div class="relative">
                                <input type="password" name="password" id="createUserPassword" required minlength="6" autocomplete="new-password"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Minimum 6 characters">
                                <button type="button" onclick="togglePasswordVisibility('createUserPassword', 'createPasswordIcon')" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700">
                                    <i id="createPasswordIcon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" name="phone"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter phone number">
                        </div>
                    </div>
                </div>

                <!-- Role & Department Section -->
                <div class="rounded-lg p-4 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-briefcase text-primary mr-2"></i>
                        Role & Department
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                            <select name="role" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select role</option>
                                <option value="Admin">Admin</option>
                                <option value="Department Head">Department Head</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Department *</label>
                            <select name="department" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select department</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Education">Education</option>
                                <option value="Hospitality Management">Hospitality Management</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeCreateUserModal()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        <i class="fas fa-user-plus mr-2"></i>Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl m-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-900">Edit Admin User</h2>
                    <button onclick="closeEditUserModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <form class="p-6 space-y-4" onsubmit="updateUser(event)" autocomplete="off" id="editUserForm">
                <input type="hidden" name="user_id" id="editUserId">
                
                <!-- Profile Picture Upload - Centered at Top -->
                <div class="flex flex-col items-center mb-6">
                    <div id="editProfilePreview" class="w-32 h-32 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden border-4 border-gray-300 mb-3">
                        <i class="fas fa-user text-gray-400 text-4xl"></i>
                    </div>
                    <input type="file" name="profile_picture" id="editProfilePictureInput" accept="image/jpeg,image/png,image/jpg,image/gif" class="hidden">
                    <button type="button" onclick="document.getElementById('editProfilePictureInput').click()" 
                            class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        <i class="fas fa-camera mr-2"></i>Change Photo
                    </button>
                    <p class="text-xs text-gray-500 mt-2">JPG, PNG or GIF (max 5MB)</p>
                </div>

                <!-- Personal Information Section -->
                <div class="rounded-lg p-4 mb-4 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-user text-primary mr-2"></i>
                        Personal Information
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" name="name" id="editUserName" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter full name">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input type="text" name="user_email_edit" id="editUserEmail" required autocomplete="off" autocomplete="nope"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter email address">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <div class="relative">
                                <input type="text" name="user_pass_edit" id="editUserPassword" minlength="6" autocomplete="off" autocomplete="nope"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Leave blank to keep current">
                                <button type="button" onclick="togglePasswordVisibility('editUserPassword', 'editPasswordIcon')" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700">
                                    <i id="editPasswordIcon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" name="phone" id="editUserPhone"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter phone number">
                        </div>
                    </div>
                </div>

                <!-- Role & Department Section -->
                <div class="rounded-lg p-4 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-briefcase text-primary mr-2"></i>
                        Role & Department
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                            <select name="role" id="editUserRole" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select role</option>
                                <option value="Admin">Admin</option>
                                <option value="HR Manager">HR Manager</option>
                                <option value="Department Head">Department Head</option>
                                <option value="Recruiter">Recruiter</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Department *</label>
                            <select name="department" id="editUserDepartment" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select department</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Education">Education</option>
                                <option value="Hospitality Management">Hospitality Management</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                            <select name="status" id="editUserStatus" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeEditUserModal()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        <i class="fas fa-save mr-2"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- My Profile Modal -->
    <div id="myProfileModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl m-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-900">My Profile</h2>
                    <button onclick="closeMyProfileModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <form class="p-6 space-y-4" onsubmit="updateMyProfile(event)" autocomplete="off" id="myProfileForm">
                <!-- Profile Picture Upload - Centered at Top -->
                <div class="flex flex-col items-center mb-6">
                    <div id="myProfilePreview" class="w-32 h-32 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden border-4 border-gray-300 mb-3">
                        <?php if (!empty($admin_profile_picture) && file_exists("../uploads/profile_pictures/" . $admin_profile_picture)): ?>
                            <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($admin_profile_picture); ?>" 
                                 alt="Profile" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-user text-gray-400 text-4xl"></i>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="profile_picture" id="myProfilePictureInput" accept="image/jpeg,image/png,image/jpg,image/gif" class="hidden">
                    <button type="button" onclick="document.getElementById('myProfilePictureInput').click()" 
                            class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        <i class="fas fa-camera mr-2"></i>Change Photo
                    </button>
                    <p class="text-xs text-gray-500 mt-2">JPG, PNG or GIF (max 5MB)</p>
                </div>

                <!-- Personal Information Section -->
                <div class="rounded-lg p-4 mb-4 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-user text-primary mr-2"></i>
                        Personal Information
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" name="name" id="myProfileName" required
                                   value="<?php echo htmlspecialchars($admin_name); ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter full name">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input type="email" name="email" id="myProfileEmail" required readonly
                                   value="<?php echo htmlspecialchars($admin_email); ?>"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100 text-gray-600 cursor-not-allowed"
                                   placeholder="Enter email address">
                            <p class="text-xs text-gray-500 mt-1"><i class="fas fa-info-circle mr-1"></i>Email cannot be changed</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <div class="relative">
                                <input type="password" name="password" id="myProfilePassword" minlength="6"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Leave blank to keep current">
                                <button type="button" onclick="togglePasswordVisibility('myProfilePassword', 'myProfilePasswordIcon')" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700">
                                    <i id="myProfilePasswordIcon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" name="phone" id="myProfilePhone"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter phone number">
                        </div>
                    </div>
                </div>

                <!-- Role & Department Section (Read-only display) -->
                <div class="rounded-lg p-4 border border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-briefcase text-primary mr-2"></i>
                        Role & Department
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <input type="text" value="<?php echo htmlspecialchars($admin_role); ?>" readonly
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100 text-gray-600 cursor-not-allowed">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <input type="text" value="<?php echo htmlspecialchars(!empty($admin_department) ? $admin_department : 'Not Assigned'); ?>" readonly
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100 text-gray-600 cursor-not-allowed">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2"><i class="fas fa-info-circle mr-1"></i>Contact an administrator to change your role or department</p>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeMyProfileModal()"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-800 transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="admin.js"></script>
    
    <script>
        // Real-time dashboard functionality
        let refreshInterval;
        let isAutoRefreshEnabled = true;
        
        // Update chart based on filter selection
        function updateChart() {
            const filter = document.getElementById('chartFilter').value;
            window.location.href = `?chart_filter=${filter}`;
        }
        
        // Auto-refresh dashboard data
        function refreshDashboard() {
            if (!isAutoRefreshEnabled) return;
            
            fetch('dashboard_api.php')
                .then(response => response.json())
                .then(data => {
                    // Update statistics
                    updateStatistics(data.stats);
                    
                    // Update recent activity
                    updateRecentActivity(data.recent_activity);
                    
                    // Update recent jobs and applicants
                    updateRecentTables(data.recent_jobs, data.recent_applicants);
                    
                    // Update last updated timestamp
                    document.getElementById('lastUpdated').textContent = `Last updated: ${new Date().toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    })}`;
                })
                .catch(error => {
                    console.error('Error refreshing dashboard:', error);
                });
        }
        
        // Update statistics cards
        function updateStatistics(stats) {
            const statCards = document.querySelectorAll('.text-2xl.font-bold.text-gray-900');
            if (statCards.length >= 4) {
                statCards[0].textContent = stats.total_jobs;
                statCards[1].textContent = stats.total_applicants;
                statCards[2].textContent = stats.active_users;
                statCards[3].textContent = stats.pending_reviews;
            }
        }
        
        // Update recent activity section
        function updateRecentActivity(activities) {
            const container = document.getElementById('recentActivityContainer');
            if (!activities || activities.length === 0) {
                container.innerHTML = '<div class="text-center py-4"><p class="text-gray-500">No recent activity</p></div>';
                return;
            }
            
            let html = '';
            activities.forEach(activity => {
                const iconInfo = getActivityIcon(activity.activity_type);
                const timeAgo = getTimeAgo(activity.created_at);
                
                html += `
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 ${iconInfo.bgClass} rounded-full flex items-center justify-center">
                            <i class="${iconInfo.iconClass} text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">${iconInfo.title}</p>
                            <p class="text-xs text-gray-500">${activity.description}</p>
                        </div>
                        <span class="text-xs text-gray-400">${timeAgo}</span>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Get activity icon and styling
        function getActivityIcon(activityType) {
            const icons = {
                'application': {
                    iconClass: 'fas fa-user-plus text-green-600',
                    bgClass: 'bg-green-100',
                    title: 'New application received'
                },
                'job_created': {
                    iconClass: 'fas fa-briefcase text-blue-600',
                    bgClass: 'bg-blue-100',
                    title: 'Job posting created'
                },
                'job_updated': {
                    iconClass: 'fas fa-edit text-orange-600',
                    bgClass: 'bg-orange-100',
                    title: 'Job posting updated'
                },
                'status_changed': {
                    iconClass: 'fas fa-exchange-alt text-purple-600',
                    bgClass: 'bg-purple-100',
                    title: 'Application status changed'
                },
                'admin_login': {
                    iconClass: 'fas fa-sign-in-alt text-indigo-600',
                    bgClass: 'bg-indigo-100',
                    title: 'Admin logged in'
                },
                'data_export': {
                    iconClass: 'fas fa-download text-teal-600',
                    bgClass: 'bg-teal-100',
                    title: 'Data exported'
                }
            };
            
            return icons[activityType] || icons['application'];
        }
        
        // Calculate time ago
        function getTimeAgo(timestamp) {
            const now = new Date();
            const time = new Date(timestamp);
            const diffInSeconds = Math.floor((now - time) / 1000);
            
            if (diffInSeconds < 60) return `${diffInSeconds}s ago`;
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
            
            return time.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }
        
        // Update recent tables
        function updateRecentTables(jobs, applicants) {
            // Update jobs table
            const jobsTable = document.querySelector('#dashboardSection .grid.grid-cols-1.lg\\:grid-cols-2 .bg-white:first-child tbody');
            if (jobsTable && jobs) {
                let jobsHtml = '';
                jobs.forEach(job => {
                    jobsHtml += `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium text-gray-900">${job.job_title}</div>
                                    <div class="text-sm text-gray-500">${job.department_role || 'General'}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">${job.application_count}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    Active
                                </span>
                            </td>
                        </tr>
                    `;
                });
                jobsTable.innerHTML = jobsHtml || '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No jobs found</td></tr>';
            }
            
            // Update applicants table
            const applicantsTable = document.querySelector('#dashboardSection .grid.grid-cols-1.lg\\:grid-cols-2 .bg-white:last-child tbody');
            if (applicantsTable && applicants) {
                let applicantsHtml = '';
                applicants.forEach(applicant => {
                    const statusInfo = getStatusBadge(applicant.status);
                    applicantsHtml += `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">${applicant.full_name}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">${applicant.position}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusInfo.class}">
                                    ${statusInfo.text}
                                </span>
                            </td>
                        </tr>
                    `;
                });
                applicantsTable.innerHTML = applicantsHtml || '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No recent applicants</td></tr>';
            }
        }
        
        // Get status badge styling
        function getStatusBadge(status) {
            const badges = {
                'Approved': { class: 'bg-green-100 text-green-800', text: 'Approved' },
                'Rejected': { class: 'bg-red-100 text-red-800', text: 'Rejected' },
                'Interview': { class: 'bg-blue-100 text-blue-800', text: 'Interview Scheduled' },
                'Pending': { class: 'bg-yellow-100 text-yellow-800', text: 'Under Review' }
            };
            
            return badges[status] || badges['Pending'];
        }
        
        // Toggle auto-refresh
        function toggleAutoRefresh() {
            isAutoRefreshEnabled = !isAutoRefreshEnabled;
            const button = document.getElementById('autoRefreshToggle');
            
            if (isAutoRefreshEnabled) {
                button.textContent = 'Disable Auto-Refresh';
                button.className = 'px-3 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600 transition-colors';
                startAutoRefresh();
            } else {
                button.textContent = 'Enable Auto-Refresh';
                button.className = 'px-3 py-1 text-xs bg-green-500 text-white rounded hover:bg-green-600 transition-colors';
                stopAutoRefresh();
            }
        }
        
        // Start auto-refresh
        function startAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
            refreshInterval = setInterval(refreshDashboard, 30000); // Refresh every 30 seconds
        }
        
        // Stop auto-refresh
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Start auto-refresh automatically
            startAutoRefresh();
            
            // Log admin login activity only once per session
            if (!sessionStorage.getItem('adminLoginLogged')) {
                logActivity('admin_login', 'Admin accessed dashboard');
                sessionStorage.setItem('adminLoginLogged', 'true');
            }
        });
        
        // Log activity function
        function logActivity(type, description, relatedTable = null, relatedId = null) {
            fetch('log_activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    activity_type: type,
                    description: description,
                    related_table: relatedTable,
                    related_id: relatedId
                })
            }).catch(error => console.error('Error logging activity:', error));
        }
        
        // Override existing functions to log activities
        const originalSaveJob = window.saveJob;
        if (originalSaveJob) {
            window.saveJob = function(event) {
                const result = originalSaveJob(event);
                logActivity('job_created', 'New job posting created');
                return result;
            };
        }
    </script>

    <!-- Schedule Interview Modal -->
    <div id="scheduleModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Schedule Interview</h3>
                <button onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="scheduleForm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Interview Date</label>
                        <input type="date" id="interviewDate" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Interview Time</label>
                        <input type="time" id="interviewTime" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea id="interviewNotes" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Additional notes for the interview..."></textarea>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeScheduleModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Schedule Interview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reschedule Interview Modal -->
    <div id="rescheduleInterviewModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Reschedule Interview</h3>
                <button onclick="closeRescheduleInterviewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="rescheduleInterviewForm">
                <div class="space-y-4">
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-amber-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            This will update the interview schedule and notify the applicant.
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Interview Date</label>
                        <input type="date" id="rescheduleInterviewDate" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Interview Time</label>
                        <input type="time" id="rescheduleInterviewTime" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Rescheduling</label>
                        <textarea id="rescheduleInterviewNotes" rows="3" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                                  placeholder="Please explain the reason for rescheduling..."></textarea>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeRescheduleInterviewModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700">
                        Reschedule Interview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reschedule Demo Teaching Modal -->
    <div id="rescheduleDemoModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Reschedule Demo Teaching</h3>
                <button onclick="closeRescheduleDemoModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="rescheduleDemoForm">
                <div class="space-y-4">
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-amber-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            This will update the demo teaching schedule and notify the applicant.
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Demo Date</label>
                        <input type="date" id="rescheduleDemoDate" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Demo Time</label>
                        <input type="time" id="rescheduleDemoTime" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Rescheduling</label>
                        <textarea id="rescheduleDemoNotes" rows="3" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
                                  placeholder="Please explain the reason for rescheduling..."></textarea>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeRescheduleDemoModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700">
                        Reschedule Demo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Resubmission Modal -->
    <div id="resubmitModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Request Resubmission</h3>
                <button onclick="closeResubmitModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="resubmitForm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Documents to Resubmit</label>
                        <div class="space-y-2" id="documentCheckboxes">
                            <label class="flex items-center">
                                <input type="checkbox" name="resubmit_documents" value="application_letter" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Application Letter</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="resubmit_documents" value="resume" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Updated and Comprehensive Resume</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="resubmit_documents" value="tor" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Transcript of Record (TOR)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="resubmit_documents" value="diploma" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Diploma</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="resubmit_documents" value="professional_license" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Professional License</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="resubmit_documents" value="coe" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Certificate of Employment (COE)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="resubmit_documents" value="seminars_trainings" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Seminar/Training Certificates</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="resubmit_documents" value="masteral_cert" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Masteral Certificate</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Resubmission</label>
                        <textarea id="resubmitNotes" rows="3" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Please explain why resubmission is needed..."></textarea>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeResubmitModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                        Request Resubmission
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Application Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Reject Application</h3>
                <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="rejectForm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Rejection</label>
                        <textarea id="rejectionReason" rows="4" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                                  placeholder="Please provide a reason for rejecting this application..."></textarea>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeRejectModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Reject Application
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hire Applicant Modal -->
    <div id="hireModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Hire Applicant</h3>
                <button onclick="closeHireModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="hireForm">
                <div class="space-y-4">
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                        <p class="text-gray-700 mb-4">Are you sure you want to hire this applicant?</p>
                        <p class="text-sm text-gray-500">This action will mark the applicant as "Hired" and send them a notification.</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes (Optional)</label>
                        <textarea id="hireNotes" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                  placeholder="Congratulations message or additional information..."></textarea>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeHireModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Hire Applicant
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Permanent Hire Modal -->
    <div id="permanentHireModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 w-full max-w-lg mx-4">
            <div class="text-center">
                <!-- Success Icon Animation -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <i class="fas fa-user-tie text-3xl text-green-600"></i>
                </div>
                
                <h3 class="text-2xl font-semibold text-gray-900 mb-2">Permanently Hire Applicant</h3>
                <p class="text-gray-600 mb-6">Finalize the hiring process and mark this applicant as a permanent employee</p>
            </div>
            
            <form id="permanentHireForm">
                <div class="space-y-4">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h4 class="font-semibold text-green-900 mb-2 flex items-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            Confirm Permanent Hiring
                        </h4>
                        <p class="text-sm text-green-800">
                            By permanently hiring this applicant, you confirm that they have successfully completed all requirements and are now a regular employee.
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hiring Notes (Optional)</label>
                        <textarea id="permanentHireNotes" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                                  placeholder="Add employment details, start date, position confirmation, etc..."></textarea>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-900 mb-2 flex items-center gap-2">
                            <i class="fas fa-info-circle"></i>
                            Final Status
                        </h4>
                        <p class="text-sm text-blue-800">
                            After confirmation, the applicant's status will be updated to "Hired" and they will receive a congratulatory notification.
                        </p>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closePermanentHireModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-green-700 text-white rounded-lg hover:bg-green-800 flex items-center justify-center gap-2">
                        <i class="fas fa-user-tie"></i>
                        Confirm Permanent Hire
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Approve Demo Modal -->
    <div id="approveDemoModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 w-full max-w-lg mx-4">
            <div class="text-center">
                <!-- Success Icon Animation -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 mb-4">
                    <i class="fas fa-check-double text-3xl text-emerald-600"></i>
                </div>
                
                <h3 class="text-2xl font-semibold text-gray-900 mb-2">Approve Demo Teaching</h3>
                <p class="text-gray-600 mb-6">Confirm that the applicant has successfully passed the demo teaching session</p>
            </div>
            
            <form id="approveDemoForm">
                <div class="space-y-4">
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                        <h4 class="font-semibold text-emerald-900 mb-2 flex items-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            Demo Teaching Evaluation
                        </h4>
                        <p class="text-sm text-emerald-800">
                            By approving this demo, you confirm that the applicant has demonstrated the required teaching skills and competencies.
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Evaluation Notes (Optional)</label>
                        <textarea id="approveDemoNotes" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                  placeholder="Add any evaluation notes or feedback about the demo teaching..."></textarea>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-900 mb-2 flex items-center gap-2">
                            <i class="fas fa-info-circle"></i>
                            Next Steps
                        </h4>
                        <p class="text-sm text-blue-800">
                            After approval, the applicant's status will be updated to "Demo Passed" and you'll be able to proceed with hiring.
                        </p>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeApproveDemoModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 flex items-center justify-center gap-2">
                        <i class="fas fa-check-double"></i>
                        Approve Demo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Approve Interview Modal -->
    <div id="approveInterviewModal" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 w-full max-w-lg mx-4">
            <div class="text-center">
                <!-- Success Icon Animation -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-teal-100 mb-4">
                    <i class="fas fa-user-check text-3xl text-teal-600"></i>
                </div>
                
                <h3 class="text-2xl font-semibold text-gray-900 mb-2">Approve Interview</h3>
                <p class="text-gray-600 mb-6">Confirm that the applicant has successfully passed the interview</p>
            </div>
            
            <form id="approveInterviewForm">
                <div class="space-y-4">
                    <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
                        <h4 class="font-semibold text-teal-900 mb-2 flex items-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            Interview Evaluation
                        </h4>
                        <p class="text-sm text-teal-800">
                            By approving this interview, you confirm that the applicant has met the required qualifications and is ready to proceed to the demo teaching stage.
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Evaluation Notes (Optional)</label>
                        <textarea id="approveInterviewNotes" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                                  placeholder="Add any evaluation notes or feedback about the interview..."></textarea>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-900 mb-2 flex items-center gap-2">
                            <i class="fas fa-info-circle"></i>
                            Next Steps
                        </h4>
                        <p class="text-sm text-blue-800">
                            After approval, the applicant's status will be updated to "Interview Passed" and you'll be able to schedule the demo teaching.
                        </p>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeApproveInterviewModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 flex items-center justify-center gap-2">
                        <i class="fas fa-user-check"></i>
                        Approve Interview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Document Viewer Modal -->
    <div id="documentViewerModal" class="fixed inset-0 bg-black bg-opacity-75 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                <h3 id="documentModalTitle" class="text-lg font-semibold text-gray-900">Document Viewer</h3>
                <button onclick="closeDocumentViewer()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="documentModalContent" class="p-6 overflow-auto max-h-[calc(90vh-80px)]">
                <!-- Document content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- New Edit Job Modal -->
    <div id="newEditJobModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[95vh] overflow-hidden m-4 shadow-2xl">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold">Edit Job Posting</h2>
                        <p class="text-blue-100 text-sm mt-1">Update job information and requirements</p>
                    </div>
                    <button onclick="closeNewEditJobModal()" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Content -->
            <div class="overflow-y-auto max-h-[calc(95vh-120px)]">
                <form id="newEditJobForm" class="p-6 space-y-6" onsubmit="submitEditJob(event)">
                    <input type="hidden" name="job_id" id="editJobId" value="">
                    
                    <!-- Basic Information Section -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                            Basic Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Job Title *</label>
                                <input type="text" name="job_title" id="editJobTitle" required
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                       placeholder="Enter job title">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Department *</label>
                                <select name="department_role" id="editDepartment" required
                                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                    <option value="">Select department</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Hospitality Management">Hospitality Management</option>
                                    <option value="Education">Education</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Job Type *</label>
                                <select name="job_type" id="editJobType" required
                                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Location *</label>
                                <input type="text" name="locations" id="editLocation" required
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                       placeholder="Job location">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Salary Range *</label>
                                <input type="text" name="salary_range" id="editSalary" required
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                       placeholder="e.g., ₱25,000 - ₱35,000">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Application Deadline *</label>
                                <input type="date" name="application_deadline" id="editDeadline" required
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Job Description *</label>
                            <textarea name="job_description" id="editDescription" rows="4" required
                                      class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                      placeholder="Enter detailed job description"></textarea>
                        </div>
                    </div>

                    <!-- Qualifications Section -->
                    <div class="bg-yellow-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-graduation-cap text-yellow-600 mr-2"></i>
                            Minimum Qualifications
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Education</label>
                                <textarea name="education" id="editEducation" rows="3"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                                          placeholder="Educational requirements"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Experience</label>
                                <textarea name="experience" id="editExperience" rows="3"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                                          placeholder="Experience requirements"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Training</label>
                                <textarea name="training" id="editTraining" rows="3"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                                          placeholder="Training requirements"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Eligibility</label>
                                <textarea name="eligibility" id="editEligibility" rows="3"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                                          placeholder="Eligibility requirements"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Job Details Section -->
                    <div class="bg-green-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-tasks text-green-600 mr-2"></i>
                            Job Details
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Job Requirements</label>
                                <textarea name="job_requirements" id="editRequirements" rows="4"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                          placeholder="Specific job requirements"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Duties & Responsibilities</label>
                                <textarea name="duties" id="editDuties" rows="4"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                          placeholder="Main duties and responsibilities"></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Competency</label>
                            <textarea name="competency" id="editCompetency" rows="3"
                                      class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                      placeholder="Required competencies and skills"></textarea>
                        </div>
                    </div>
                    
                    <!-- Form Buttons -->
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-between items-center rounded-lg">
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Fields marked with * are required
                        </div>
                        <div class="flex gap-3">
                            <button type="button" onclick="closeNewEditJobModal()"
                                    class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors font-medium">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                <i class="fas fa-save mr-2"></i>Update Job
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Secretary Job Edit Modal -->
    <div id="editSecretaryJobModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[95vh] overflow-hidden m-4 shadow-2xl">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold">Edit Secretary Position</h2>
                        <p class="text-blue-100 text-sm mt-1">Update secretary job information and requirements</p>
                    </div>
                    <button onclick="closeSecretaryEditModal()" class="text-white hover:text-gray-200 transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Content -->
            <div class="overflow-y-auto max-h-[calc(95vh-120px)]">
                <form id="editSecretaryJobForm" class="p-6 space-y-6" onsubmit="submitSecretaryEditJob(event)">
                    <input type="hidden" name="job_id" id="editSecretaryJobId" value="">
                    
                    <!-- Basic Information Section -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-user-tie text-blue-600 mr-2"></i>
                            Secretary Position Details
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Position Title *</label>
                                <input type="text" name="job_title" id="editSecretaryJobTitle" required
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                       placeholder="e.g., Office Secretary, Executive Secretary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Role/Department *</label>
                                <select name="department_role" id="editSecretaryRole" required
                                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                    <option value="">Select Role</option>
                                    <option value="Office Secretary">Office Secretary</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Employment Type *</label>
                                <select name="job_type" id="editSecretaryJobType" required
                                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Work Location *</label>
                                <input type="text" name="locations" id="editSecretaryLocation" required
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                       placeholder="Office location">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Salary Range *</label>
                                <input type="text" name="salary_range" id="editSecretarySalary" required
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                       placeholder="e.g., ₱18,000 - ₱25,000">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Application Deadline *</label>
                                <input type="date" name="application_deadline" id="editSecretaryDeadline" required
                                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Job Description *</label>
                            <textarea name="job_description" id="editSecretaryDescription" rows="4" required
                                      class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                      placeholder="Describe the secretary position responsibilities and work environment"></textarea>
                        </div>
                    </div>

                    <!-- Qualifications Section -->
                    <div class="bg-yellow-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-graduation-cap text-yellow-600 mr-2"></i>
                            Secretary Qualifications
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Educational Background</label>
                                <textarea name="education" id="editSecretaryEducation" rows="3"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                                          placeholder="e.g., High School Graduate, Business Administration, Office Management"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Work Experience</label>
                                <textarea name="experience" id="editSecretaryExperience" rows="3"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                                          placeholder="e.g., 1-2 years office experience, Fresh graduates welcome"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Required Training</label>
                                <textarea name="training" id="editSecretaryTraining" rows="3"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                                          placeholder="e.g., Computer literacy, Office software training"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Eligibility Requirements</label>
                                <textarea name="eligibility" id="editSecretaryEligibility" rows="3"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all"
                                          placeholder="e.g., Civil Service Eligibility (if applicable)"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Secretary Specific Skills Section -->
                    <div class="bg-green-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-clipboard-list text-green-600 mr-2"></i>
                            Secretary Skills & Responsibilities
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Key Requirements</label>
                                <textarea name="job_requirements" id="editSecretaryRequirements" rows="4"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                          placeholder="e.g., Excellent communication skills, Computer proficiency, Filing systems"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Daily Duties</label>
                                <textarea name="duties" id="editSecretaryDuties" rows="4"
                                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                          placeholder="e.g., Answer phones, Schedule appointments, Maintain records, Assist visitors"></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Core Competencies</label>
                            <textarea name="competency" id="editSecretaryCompetency" rows="3"
                                      class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                      placeholder="e.g., Organization skills, Time management, Confidentiality, Professional communication"></textarea>
                        </div>
                    </div>
                    
                    <!-- Form Buttons -->
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-between items-center rounded-lg">
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Fields marked with * are required
                        </div>
                        <div class="flex gap-3">
                            <button type="button" onclick="closeSecretaryEditModal()"
                                    class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors font-medium">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                <i class="fas fa-save mr-2"></i>Update Secretary Position
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                <i class="fas fa-sign-out-alt text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Confirm Logout</h3>
            <p class="text-sm text-gray-600 text-center mb-6">Are you sure you want to logout? You will need to sign in again to access the admin panel.</p>
            <div class="flex gap-3">
                <button onclick="closeLogoutModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    Cancel
                </button>
                <button onclick="proceedLogout()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium">
                    Logout
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Store current admin ID for self-deletion protection
const CURRENT_ADMIN_ID = <?php echo $_SESSION['admin_id'] ?? 0; ?>;

// Notification button handler and profile dropdown
document.addEventListener('DOMContentLoaded', function() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const profileDropdownBtn = document.getElementById('profileDropdownBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    
    // Notification button click handler
    if (notificationBtn && notificationsDropdown) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationsDropdown.classList.toggle('hidden');
            // Load notifications when opened
            if (!notificationsDropdown.classList.contains('hidden')) {
                loadNotifications();
            }
        });
        
        // Close notifications dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationsDropdown.classList.contains('hidden') && 
                !notificationsDropdown.contains(e.target) && 
                !notificationBtn.contains(e.target)) {
                notificationsDropdown.classList.add('hidden');
            }
        });
    }
    
    // Load notifications on page load
    loadNotifications();
    
    // Auto-refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    
    // Profile dropdown toggle
    if (profileDropdownBtn && profileDropdown) {
        profileDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileDropdown.classList.contains('hidden') && 
                !profileDropdown.contains(e.target) && 
                !profileDropdownBtn.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });
    }
    
    // Profile picture preview for My Profile modal
    const myProfilePictureInput = document.getElementById('myProfilePictureInput');
    if (myProfilePictureInput) {
        myProfilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('myProfilePreview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="w-full h-full object-cover">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Load current admin's phone number
    loadMyProfilePhone();
});

// My Profile Modal Functions
function openMyProfileModal() {
    // Close profile dropdown
    const profileDropdown = document.getElementById('profileDropdown');
    if (profileDropdown) {
        profileDropdown.classList.add('hidden');
    }
    
    // Load phone number
    loadMyProfilePhone();
    
    // Open modal
    document.getElementById('myProfileModal').classList.remove('hidden');
}

function closeMyProfileModal() {
    document.getElementById('myProfileModal').classList.add('hidden');
}

// Load current admin's phone number from database
async function loadMyProfilePhone() {
    try {
        const response = await fetch('api/users.php');
        const users = await response.json();
        
        // Get current admin ID from session (PHP embedded)
        const currentAdminId = <?php echo $_SESSION['admin_id'] ?? 0; ?>;
        
        const currentUser = users.find(u => u.id == currentAdminId);
        if (currentUser && currentUser.phone) {
            document.getElementById('myProfilePhone').value = currentUser.phone;
        }
    } catch (error) {
        console.error('Error loading phone number:', error);
    }
}

// Update My Profile
async function updateMyProfile(event) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('id', <?php echo $_SESSION['admin_id'] ?? 0; ?>);
    formData.append('name', document.getElementById('myProfileName').value);
    // Email is read-only, use current email from session
    formData.append('email', '<?php echo htmlspecialchars($admin_email); ?>');
    formData.append('phone', document.getElementById('myProfilePhone').value);
    
    // Add password only if provided
    const password = document.getElementById('myProfilePassword').value;
    if (password) {
        formData.append('password', password);
    }
    
    // Add profile picture if selected
    const fileInput = document.getElementById('myProfilePictureInput');
    if (fileInput.files.length > 0) {
        formData.append('profile_picture', fileInput.files[0]);
    }
    
    // Keep current role, department, and status
    formData.append('role', '<?php echo htmlspecialchars($admin_role); ?>');
    formData.append('department', '<?php echo htmlspecialchars($admin_department); ?>');
    formData.append('status', 'Active');
    
    try {
        const response = await fetch('api/users.php', {
            method: 'PUT',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Profile updated successfully!', 'success');
            closeMyProfileModal();
            
            // Refresh session data before reloading
            try {
                await fetch('refresh_session.php');
            } catch (error) {
                console.error('Error refreshing session:', error);
            }
            
            // Reload page to update header display
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(result.message || 'Failed to update profile', 'error');
        }
    } catch (error) {
        console.error('Error updating profile:', error);
        showToast('An error occurred while updating profile', 'error');
    }
}

// Admin Notification Functions
async function loadNotifications() {
    try {
        const response = await fetch('api/admin_notifications.php?limit=20');
        const data = await response.json();
        
        if (data.success) {
            updateNotificationBadge(data.unread_count);
            displayNotifications(data.notifications);
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
}

function displayNotifications(notifications) {
    const container = document.getElementById('notificationsList');
    if (!container) return;
    
    if (notifications.length === 0) {
        container.innerHTML = `
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-bell-slash text-4xl mb-3 text-gray-300"></i>
                <p class="text-sm">No notifications</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = notifications.map(notif => {
        const typeColors = {
            'info': 'bg-blue-50 border-blue-200 text-blue-800',
            'success': 'bg-green-50 border-green-200 text-green-800',
            'warning': 'bg-orange-50 border-orange-200 text-orange-800',
            'danger': 'bg-red-50 border-red-200 text-red-800'
        };
        const iconColors = {
            'info': 'text-blue-600',
            'success': 'text-green-600',
            'warning': 'text-orange-600',
            'danger': 'text-red-600'
        };
        const icons = {
            'interview_scheduled': 'fa-calendar-check',
            'demo_scheduled': 'fa-chalkboard-teacher',
            'hired': 'fa-user-check',
            'interview_rescheduled': 'fa-calendar-alt',
            'demo_rescheduled': 'fa-calendar-alt'
        };
        
        const bgClass = typeColors[notif.type] || typeColors['info'];
        const iconColor = iconColors[notif.type] || iconColors['info'];
        const icon = icons[notif.action_type] || 'fa-bell';
        const timeAgo = getTimeAgo(notif.created_at);
        
        return `
            <div class="border-b border-gray-100 hover:bg-gray-50 transition-colors ${notif.is_read == 0 ? 'bg-blue-50' : ''}">
                <div class="p-4 cursor-pointer" onclick="markNotificationAsRead(${notif.id})">
                    <div class="flex gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-full ${bgClass} flex items-center justify-center">
                                <i class="fas ${icon} ${iconColor}"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold text-gray-900 text-sm">${escapeHtml(notif.title)}</p>
                                ${notif.is_read == 0 ? '<span class="flex-shrink-0 w-2 h-2 bg-blue-600 rounded-full"></span>' : ''}
                            </div>
                            <p class="text-sm text-gray-600 mt-1 line-clamp-2">${escapeHtml(notif.message)}</p>
                            ${notif.applicant_name ? `<p class="text-xs text-gray-500 mt-1"><i class="fas fa-user mr-1"></i>${escapeHtml(notif.applicant_name)}</p>` : ''}
                            <p class="text-xs text-gray-400 mt-2"><i class="fas fa-clock mr-1"></i>${timeAgo}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

async function markNotificationAsRead(notificationId) {
    try {
        const response = await fetch('api/admin_notifications.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({notification_id: notificationId})
        });
        
        const data = await response.json();
        if (data.success) {
            loadNotifications(); // Reload to update UI
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

async function markAllAsRead() {
    try {
        const response = await fetch('api/admin_notifications.php', {
            method: 'PUT'
        });
        
        const data = await response.json();
        if (data.success) {
            showToast('All notifications marked as read', 'success');
            loadNotifications(); // Reload to update UI
        }
    } catch (error) {
        console.error('Error marking all as read:', error);
    }
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const past = new Date(timestamp);
    const diffMs = now - past;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    return past.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Logout confirmation modal
function confirmLogout(event) {
    event.preventDefault();
    document.getElementById('logoutModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function proceedLogout() {
    window.location.href = 'logout.php';
}
</script>
</body>
</html>