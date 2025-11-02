<?php
/**
 * Test Ban System - Admin Tool
 * Allows admins to test the ban system by manually setting/clearing bans
 */

require_once __DIR__ . '/../config/db.php';

// Simple password protection
$admin_password = 'test123';
$authenticated = false;

if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
    $authenticated = true;
}

if (isset($_POST['action']) && $authenticated) {
    $applicant_id = $_POST['applicant_id'] ?? 0;
    
    switch ($_POST['action']) {
        case 'set_ban':
            $months = $_POST['months'] ?? 4;
            $reason = $_POST['reason'] ?? 'Test ban';
            $banned_by = $_POST['banned_by'] ?? 'Test Admin';
            
            $ban_expires = date('Y-m-d H:i:s', strtotime("+$months months"));
            
            $stmt = $conn->prepare("UPDATE applicants 
                                   SET rejection_ban_until = ?,
                                       ban_reason = ?,
                                       banned_by = ?,
                                       rejection_count = rejection_count + 1
                                   WHERE id = ?");
            $stmt->bind_param("sssi", $ban_expires, $reason, $banned_by, $applicant_id);
            $stmt->execute();
            
            $message = "Ban set successfully! Expires: $ban_expires";
            $message_type = 'success';
            break;
            
        case 'clear_ban':
            $stmt = $conn->prepare("UPDATE applicants 
                                   SET rejection_ban_until = NULL,
                                       ban_reason = NULL,
                                       banned_by = NULL
                                   WHERE id = ?");
            $stmt->bind_param("i", $applicant_id);
            $stmt->execute();
            
            $message = "Ban cleared successfully!";
            $message_type = 'success';
            break;
            
        case 'expire_ban':
            $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
            $stmt = $conn->prepare("UPDATE applicants 
                                   SET rejection_ban_until = ?
                                   WHERE id = ?");
            $stmt->bind_param("si", $yesterday, $applicant_id);
            $stmt->execute();
            
            $message = "Ban expired successfully! Set to yesterday's date.";
            $message_type = 'success';
            break;
    }
}

// Get all applicants
$applicants = $conn->query("SELECT id, first_name, last_name, email, 
                                   rejection_ban_until, ban_reason, banned_by, rejection_count 
                            FROM applicants 
                            ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Get ban statistics
$stats = $conn->query("SELECT 
                        COUNT(*) as total_applicants,
                        SUM(CASE WHEN rejection_ban_until IS NOT NULL AND rejection_ban_until > NOW() THEN 1 ELSE 0 END) as active_bans,
                        SUM(CASE WHEN rejection_ban_until IS NOT NULL AND rejection_ban_until <= NOW() THEN 1 ELSE 0 END) as expired_bans,
                        SUM(rejection_count) as total_rejections
                       FROM applicants")->fetch_assoc();

// Get recent ban history
$ban_history = $conn->query("SELECT ab.*, a.first_name, a.last_name 
                            FROM application_bans ab
                            LEFT JOIN applicants a ON ab.applicant_id = a.id
                            ORDER BY ab.banned_date DESC 
                            LIMIT 10")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Ban System - NCHire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="ri-shield-user-line text-red-500"></i> Ban System Test Tool
            </h1>
            <p class="text-gray-600">Test the 4-month application ban system for rejected applicants</p>
        </div>

        <?php if (!$authenticated): ?>
            <!-- Password Protection -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-xl font-semibold mb-4">Admin Authentication Required</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" name="password" 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter admin password">
                        <p class="text-xs text-gray-500 mt-1">Default password: test123</p>
                    </div>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Login
                    </button>
                </form>
            </div>
        <?php else: ?>
            
            <?php if (isset($message)): ?>
                <div class="bg-<?= $message_type === 'success' ? 'green' : 'red' ?>-100 border border-<?= $message_type === 'success' ? 'green' : 'red' ?>-400 text-<?= $message_type === 'success' ? 'green' : 'red' ?>-700 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-3xl font-bold text-blue-600"><?= $stats['total_applicants'] ?></div>
                    <div class="text-sm text-gray-600">Total Applicants</div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-3xl font-bold text-red-600"><?= $stats['active_bans'] ?></div>
                    <div class="text-sm text-gray-600">Active Bans</div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-3xl font-bold text-yellow-600"><?= $stats['expired_bans'] ?></div>
                    <div class="text-sm text-gray-600">Expired Bans</div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-3xl font-bold text-gray-600"><?= $stats['total_rejections'] ?></div>
                    <div class="text-sm text-gray-600">Total Rejections</div>
                </div>
            </div>

            <!-- Applicants Table -->
            <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
                <h2 class="text-2xl font-semibold mb-6">Applicants</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left p-3">ID</th>
                                <th class="text-left p-3">Name</th>
                                <th class="text-left p-3">Email</th>
                                <th class="text-left p-3">Ban Status</th>
                                <th class="text-left p-3">Ban Expires</th>
                                <th class="text-left p-3">Rejection Count</th>
                                <th class="text-left p-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applicants as $applicant): ?>
                                <?php 
                                $is_banned = $applicant['rejection_ban_until'] && strtotime($applicant['rejection_ban_until']) > time();
                                $is_expired = $applicant['rejection_ban_until'] && strtotime($applicant['rejection_ban_until']) <= time();
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3"><?= $applicant['id'] ?></td>
                                    <td class="p-3"><?= htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($applicant['email']) ?></td>
                                    <td class="p-3">
                                        <?php if ($is_banned): ?>
                                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold">
                                                <i class="ri-close-circle-line"></i> BANNED
                                            </span>
                                        <?php elseif ($is_expired): ?>
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-semibold">
                                                <i class="ri-time-line"></i> EXPIRED
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-semibold">
                                                <i class="ri-check-line"></i> ACTIVE
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3">
                                        <?php if ($applicant['rejection_ban_until']): ?>
                                            <?= date('M j, Y g:i A', strtotime($applicant['rejection_ban_until'])) ?>
                                            <?php if ($is_banned): ?>
                                                <br><span class="text-xs text-gray-500">
                                                    <?= ceil((strtotime($applicant['rejection_ban_until']) - time()) / (60*60*24)) ?> days left
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 bg-gray-100 rounded">
                                            <?= $applicant['rejection_count'] ?>
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <form method="POST" class="inline-block space-x-2">
                                            <input type="hidden" name="password" value="<?= $admin_password ?>">
                                            <input type="hidden" name="applicant_id" value="<?= $applicant['id'] ?>">
                                            
                                            <?php if (!$is_banned): ?>
                                                <input type="hidden" name="action" value="set_ban">
                                                <input type="hidden" name="months" value="4">
                                                <input type="hidden" name="reason" value="Test ban - manual">
                                                <input type="hidden" name="banned_by" value="Test Admin">
                                                <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-xs">
                                                    <i class="ri-forbid-line"></i> Set Ban
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($is_banned): ?>
                                                <input type="hidden" name="action" value="clear_ban">
                                                <button type="submit" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs">
                                                    <i class="ri-check-line"></i> Clear Ban
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($is_banned): ?>
                                                <input type="hidden" name="action" value="expire_ban">
                                                <button type="submit" class="px-3 py-1 bg-yellow-600 text-white rounded hover:bg-yellow-700 text-xs">
                                                    <i class="ri-time-line"></i> Expire Now
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Ban History -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-semibold mb-6">Recent Ban History</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2">
                                <th class="text-left p-3">Date</th>
                                <th class="text-left p-3">Applicant</th>
                                <th class="text-left p-3">Banned By</th>
                                <th class="text-left p-3">Reason</th>
                                <th class="text-left p-3">Expires</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ban_history as $ban): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3"><?= date('M j, Y g:i A', strtotime($ban['banned_date'])) ?></td>
                                    <td class="p-3">
                                        <?= htmlspecialchars(($ban['first_name'] ?? '') . ' ' . ($ban['last_name'] ?? '')) ?>
                                        <br><span class="text-xs text-gray-500"><?= htmlspecialchars($ban['applicant_email']) ?></span>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                                            <?= htmlspecialchars($ban['banned_by_role']) ?>
                                        </span>
                                        <br><span class="text-xs text-gray-500"><?= htmlspecialchars($ban['banned_by_name']) ?></span>
                                    </td>
                                    <td class="p-3 max-w-xs">
                                        <div class="text-sm"><?= htmlspecialchars($ban['rejection_reason']) ?></div>
                                    </td>
                                    <td class="p-3">
                                        <?= date('M j, Y g:i A', strtotime($ban['ban_expires'])) ?>
                                        <?php if (strtotime($ban['ban_expires']) > time()): ?>
                                            <br><span class="text-xs text-green-600">Active</span>
                                        <?php else: ?>
                                            <br><span class="text-xs text-gray-500">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-6">
                <h3 class="text-lg font-semibold mb-4">Quick Test Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white p-4 rounded">
                        <h4 class="font-semibold mb-2">1. Set 4-Month Ban</h4>
                        <p class="text-sm text-gray-600 mb-3">Simulates a rejection by Secretary/Dept Head</p>
                        <p class="text-xs text-gray-500">Click "Set Ban" on any applicant</p>
                    </div>
                    <div class="bg-white p-4 rounded">
                        <h4 class="font-semibold mb-2">2. Expire Ban Immediately</h4>
                        <p class="text-sm text-gray-600 mb-3">Tests auto-clearing of expired bans</p>
                        <p class="text-xs text-gray-500">Click "Expire Now" on banned applicant</p>
                    </div>
                    <div class="bg-white p-4 rounded">
                        <h4 class="font-semibold mb-2">3. Clear Ban</h4>
                        <p class="text-sm text-gray-600 mb-3">Manually removes ban before expiry</p>
                        <p class="text-xs text-gray-500">Click "Clear Ban" on banned applicant</p>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
