/**
 * Admin User Management Module
 * Handles admin user CRUD operations
 */

// Load admin users
function loadAdminUsers() {
    fetch('api/users.php')
        .then(response => response.json())
        .then(users => {
            displayAdminUsers(users);
        })
        .catch(error => {
            console.error('Error loading users:', error);
            showToast('Failed to load users', 'error');
        });
}

// Display admin users
function displayAdminUsers(users) {
    const container = document.getElementById('adminUsersContainer');
    if (!container) return;
    
    if (users.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No admin users found</p>';
        return;
    }
    
    container.innerHTML = users.map(user => `
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
                ${user.profile_picture ? 
                    `<img src="../uploads/profile_pictures/${user.profile_picture}" class="w-10 h-10 rounded-full object-cover">` :
                    `<div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-semibold">${getInitials(user.full_name)}</div>`
                }
                <div>
                    <p class="font-medium text-gray-900">${escapeHtml(user.full_name)}</p>
                    <p class="text-sm text-gray-600">${escapeHtml(user.email)}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">${escapeHtml(user.role)}</span>
                <button onclick="editUser(${user.id})" class="p-2 text-gray-500 hover:text-primary">
                    <i class="ri-edit-line"></i>
                </button>
                <button onclick="deleteUser(${user.id})" class="p-2 text-gray-500 hover:text-red-600">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </div>
        </div>
    `).join('');
}

// Edit user
function editUser(userId) {
    fetch(`api/users.php?id=${userId}`)
        .then(response => response.json())
        .then(user => {
            // Populate edit modal
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUserName').value = user.full_name;
            document.getElementById('editUserEmail').value = user.email;
            document.getElementById('editUserRole').value = user.role;
            document.getElementById('editUserDepartment').value = user.department;
            
            // Open modal
            document.getElementById('editUserModal').classList.remove('hidden');
        });
}

// Delete user
function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this admin user?')) {
        fetch(`api/users.php?id=${userId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('User deleted', 'success');
                loadAdminUsers();
            } else {
                showToast(data.message || 'Failed to delete user', 'error');
            }
        });
    }
}

// Get initials from name
function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
}
