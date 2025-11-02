<?php
/**
 * User Header Component
 * Navigation and notifications for user dashboard
 */

// Get user data
$user_name = $_SESSION['first_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? '';
$profile_picture = $user_profile_data['profile_picture'] ?? '';
$initials = strtoupper(substr($user_name, 0, 1));
?>

<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-6 py-4">
        <div class="flex items-center justify-between">
            <!-- Logo -->
            <div class="flex items-center gap-3">
                <img src="../public/assets/images/image-removebg-preview (1).png" alt="NCHire Logo" class="h-10">
                <span class="text-2xl font-bold text-primary">NCHire</span>
            </div>

            <!-- Navigation -->
            <nav class="hidden md:flex items-center gap-6">
                <a href="user.php" class="nav-link text-gray-600 hover:text-primary transition-colors">
                    <i class="ri-dashboard-line mr-1"></i> Dashboard
                </a>
                <a href="#" onclick="showMyApplications(); return false;" class="nav-link text-gray-600 hover:text-primary transition-colors">
                    <i class="ri-file-list-line mr-1"></i> My Applications
                </a>
                <a href="user_profile.php" class="nav-link text-gray-600 hover:text-primary transition-colors">
                    <i class="ri-user-line mr-1"></i> Profile
                </a>
            </nav>

            <!-- Right Side -->
            <div class="flex items-center gap-4">
                <!-- Notifications -->
                <div class="relative">
                    <button id="notificationBell" class="relative p-2 text-gray-600 hover:text-primary hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="ri-notification-line text-xl"></i>
                        <span id="notificationBadge" class="hidden absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    </button>
                    
                    <!-- Notification Dropdown -->
                    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 max-h-96 overflow-y-auto">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="font-semibold text-gray-900">Notifications</h3>
                        </div>
                        <div id="notificationList" class="divide-y divide-gray-200">
                            <!-- Notifications loaded via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <button id="profileButton" class="flex items-center gap-2 p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <?php if ($profile_picture): ?>
                            <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($profile_picture); ?>" 
                                 alt="Profile" class="w-8 h-8 rounded-full object-cover">
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($user_name); ?></span>
                        <i class="ri-arrow-down-s-line text-gray-400"></i>
                    </button>
                    
                    <!-- Profile Dropdown Menu -->
                    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200">
                        <a href="user_profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="ri-user-line mr-2"></i> My Profile
                        </a>
                        <a href="#" onclick="confirmLogout(); return false;" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                            <i class="ri-logout-box-line mr-2"></i> Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
// Toggle profile dropdown
document.getElementById('profileButton')?.addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('profileDropdown')?.classList.toggle('hidden');
    document.getElementById('notificationDropdown')?.classList.add('hidden');
});

// Toggle notification dropdown
document.getElementById('notificationBell')?.addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('notificationDropdown')?.classList.toggle('hidden');
    document.getElementById('profileDropdown')?.classList.add('hidden');
});

// Close dropdowns when clicking outside
document.addEventListener('click', function() {
    document.getElementById('profileDropdown')?.classList.add('hidden');
    document.getElementById('notificationDropdown')?.classList.add('hidden');
});

// Show My Applications
function showMyApplications() {
    fetch('user_application.php')
        .then(response => response.text())
        .then(html => {
            document.getElementById('mainContent').innerHTML = html;
        });
}

// Logout confirmation
function confirmLogout() {
    if (confirm('Are you sure you want to sign out?')) {
        window.location.href = '../index.php?logout=1';
    }
}
</script>
