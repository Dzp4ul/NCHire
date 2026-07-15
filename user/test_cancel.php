<?php
/**
 * Test page to verify application cancellation works correctly
 * This will show the current state of an application and allow testing cancellation
 */

session_start();

// Database connection
$conn = new mysqli("127.0.0.1", "root", "", "nchire");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Please log in first");
}

$user_id = $_SESSION['user_id'];

// Get user's applications
$query = "SELECT id, full_name, position, status, workflow_stage, applied_date, rejection_reason 
          FROM job_applicants 
          WHERE user_id = ? 
          ORDER BY applied_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Application Cancellation</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Test Application Cancellation</h1>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Your Applications</h2>
            
            <?php if ($result->num_rows > 0): ?>
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">ID</th>
                            <th class="text-left py-2">Position</th>
                            <th class="text-left py-2">Status</th>
                            <th class="text-left py-2">Workflow Stage</th>
                            <th class="text-left py-2">Applied Date</th>
                            <th class="text-left py-2">Reason</th>
                            <th class="text-left py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($app = $result->fetch_assoc()): ?>
                            <tr class="border-b">
                                <td class="py-2"><?= $app['id'] ?></td>
                                <td class="py-2"><?= htmlspecialchars($app['position']) ?></td>
                                <td class="py-2">
                                    <span class="px-2 py-1 rounded text-xs <?= 
                                        $app['status'] == 'Cancelled' ? 'bg-orange-100 text-orange-800' : 
                                        ($app['status'] == 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') 
                                    ?>">
                                        <?= htmlspecialchars($app['status']) ?>
                                    </span>
                                </td>
                                <td class="py-2">
                                    <span class="text-xs"><?= htmlspecialchars($app['workflow_stage']) ?></span>
                                </td>
                                <td class="py-2 text-sm"><?= date('M d, Y', strtotime($app['applied_date'])) ?></td>
                                <td class="py-2 text-sm"><?= htmlspecialchars($app['rejection_reason'] ?? '-') ?></td>
                                <td class="py-2">
                                    <?php if ($app['status'] != 'Cancelled' && $app['status'] != 'Rejected'): ?>
                                        <button onclick="testCancel(<?= $app['id'] ?>)" 
                                                class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                                            Cancel
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-sm">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-500">No applications found</p>
            <?php endif; ?>
        </div>
        
        <div id="result" class="mt-4"></div>
        
        <div class="mt-6">
            <a href="user.php" class="text-blue-600 hover:underline">← Back to Dashboard</a>
        </div>
    </div>

    <script>
        async function testCancel(id) {
            if (!confirm('Are you sure you want to cancel this application?')) {
                return;
            }
            
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">Processing...</div>';
            
            try {
                const formData = new FormData();
                formData.append('id', id);
                
                const response = await fetch('cancel_application.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">' + 
                                         data.message + '</div>';
                    
                    // Reload page after 2 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    resultDiv.innerHTML = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">' + 
                                         'Error: ' + data.error + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">' + 
                                     'Error: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
