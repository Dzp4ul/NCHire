<?php
/**
 * Job Listings Component
 * Displays job cards with search and filters
 */
?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Available Teaching Loads</h1>
            <p class="text-gray-600">Browse and apply for vacant teaching loads at Norzagaray College</p>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
    <div class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <div class="relative">
                <input type="text" id="jobSearch" placeholder="Search teaching loads..." 
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                <i class="ri-search-line absolute left-3 top-3 text-gray-400"></i>
            </div>
        </div>
        <select id="departmentFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
            <option value="">All Departments</option>
            <option value="Computing Studies">Computing Studies</option>
            <option value="Education">Education</option>
            <option value="Hospitality Management">Hospitality Management</option>
        </select>
        <select id="typeFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
            <option value="">All Types</option>
            <option value="Full-time">Full-time</option>
            <option value="Part-time">Part-time</option>
            <option value="Contract">Contract</option>
        </select>
    </div>
</div>

<!-- Job Cards -->
<div id="jobsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <!-- Jobs loaded via JavaScript -->
    <div class="text-center py-8 col-span-full">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
        <p class="text-gray-600 mt-4">Loading teaching loads...</p>
    </div>
</div>

<!-- Pagination -->
<div id="paginationContainer" class="mt-6 flex items-center justify-between">
    <div id="paginationInfo" class="text-sm text-gray-600"></div>
    <div class="flex gap-2">
        <button id="prevPage" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="ri-arrow-left-line"></i> Previous
        </button>
        <button id="nextPage" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
            Next <i class="ri-arrow-right-line"></i>
        </button>
    </div>
</div>
