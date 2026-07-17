<?php
session_start();

// Set test session variables (comment out if already logged in)
// Uncomment one role to test:

// Test as Secretary
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_role'] = 'Secretary';
$_SESSION['admin_id'] = 1;
$_SESSION['admin_name'] = 'Test Secretary';

// Test as Department Head (uncomment to test)
// $_SESSION['admin_logged_in'] = true;
// $_SESSION['admin_role'] = 'Department Head';
// $_SESSION['admin_department'] = 'Computer Science';
// $_SESSION['admin_id'] = 2;
// $_SESSION['admin_name'] = 'Test Department Head';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Applicants Stats API</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Test Applicants Stats API</h1>
            
            <div class="mb-6">
                <p class="text-gray-600">Current Session:</p>
                <ul class="list-disc list-inside text-sm text-gray-700 mt-2">
                    <li>Role: <strong><?php echo $_SESSION['admin_role'] ?? 'Not set'; ?></strong></li>
                    <li>Department: <strong><?php echo $_SESSION['admin_department'] ?? 'Not set'; ?></strong></li>
                    <li>Admin ID: <strong><?php echo $_SESSION['admin_id'] ?? 'Not set'; ?></strong></li>
                </ul>
            </div>

            <button onclick="testStatsAPI()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 mb-4">
                Test Stats API
            </button>

            <div id="results" class="mt-4"></div>

            <!-- Stats Cards Display -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Applicants</p>
                            <p class="text-2xl font-bold text-gray-900" data-stat="total_applicants">-</p>
                        </div>
                        <i class="fas fa-user text-2xl text-blue-500"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Interviews Scheduled</p>
                            <p class="text-2xl font-bold text-gray-900" data-stat="interview_scheduled">-</p>
                        </div>
                        <i class="fas fa-calendar text-2xl text-blue-500"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Demo Scheduled</p>
                            <p class="text-2xl font-bold text-gray-900" data-stat="demo_scheduled">-</p>
                        </div>
                        <i class="fas fa-chalkboard-teacher text-2xl text-indigo-500"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Hired</p>
                            <p class="text-2xl font-bold text-gray-900" data-stat="hired">-</p>
                        </div>
                        <i class="fas fa-check-circle text-2xl text-green-500"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <script>
        async function testStatsAPI() {
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<p class="text-gray-600">Loading stats...</p>';

            try {
                const response = await fetch('api/get_applicants_stats.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const stats = await response.json();
                
                // Update stat cards
                document.querySelectorAll('[data-stat]').forEach(element => {
                    const statKey = element.getAttribute('data-stat');
                    if (stats[statKey] !== undefined) {
                        element.textContent = stats[statKey];
                    }
                });

                // Display results
                resultsDiv.innerHTML = `
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h3 class="font-semibold text-green-900 mb-2">✅ API Response Success!</h3>
                        <pre class="text-sm text-gray-700 bg-white p-3 rounded border overflow-x-auto">${JSON.stringify(stats, null, 2)}</pre>
                    </div>
                `;

            } catch (error) {
                resultsDiv.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <h3 class="font-semibold text-red-900 mb-2">❌ Error!</h3>
                        <p class="text-sm text-red-700">${error.message}</p>
                    </div>
                `;
                console.error('Error testing stats API:', error);
            }
        }

        // Auto-test on page load
        window.addEventListener('DOMContentLoaded', () => {
            testStatsAPI();
        });
    </script>
</body>
</html>
