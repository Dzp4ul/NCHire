import React from 'react';
import { TrendingUp, Users, Briefcase, UserCheck, Eye, Edit, Trash2 } from 'lucide-react';

const Dashboard: React.FC = () => {
  const stats = [
    { title: 'Total Jobs', value: '24', change: '+12%', icon: Briefcase, color: 'bg-blue-500' },
    { title: 'Total Applicants', value: '158', change: '+8%', icon: UserCheck, color: 'bg-green-500' },
    { title: 'Active Users', value: '89', change: '+15%', icon: Users, color: 'bg-purple-500' },
    { title: 'Pending Reviews', value: '42', change: '+3%', icon: Eye, color: 'bg-orange-500' },
  ];

  const recentJobs = [
    { id: 1, title: 'Part-time Instructor', department: 'Computer Science', applications: 15, status: 'Active', posted: '2 days ago' },
    { id: 2, title: 'Office Secretary', department: 'Administration', applications: 8, status: 'Active', posted: '5 days ago' },
    { id: 3, title: 'Utility Staff', department: 'Facilities', applications: 12, status: 'Draft', posted: '1 week ago' },
    { id: 4, title: 'Laboratory Assistant', department: 'Chemistry', applications: 6, status: 'Active', posted: '1 week ago' },
  ];

  const recentApplicants = [
    { id: 1, name: 'Maria Santos', position: 'Part-time Instructor', status: 'Under Review', applied: '1 day ago' },
    { id: 2, name: 'John Dela Cruz', position: 'Office Secretary', status: 'Interview Scheduled', applied: '2 days ago' },
    { id: 3, name: 'Sarah Johnson', position: 'Utility Staff', status: 'Application Received', applied: '3 days ago' },
    { id: 4, name: 'Michael Brown', position: 'Laboratory Assistant', status: 'Under Review', applied: '4 days ago' },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-3xl font-bold text-gray-900">Dashboard</h1>
        <div className="text-sm text-gray-500">Last updated: Today, 2:30 PM</div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat, index) => {
          const Icon = stat.icon;
          return (
            <div key={index} className="bg-white p-6 rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">{stat.title}</p>
                  <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
                  <p className="text-sm text-green-600 flex items-center gap-1 mt-1">
                    <TrendingUp className="w-3 h-3" />
                    {stat.change}
                  </p>
                </div>
                <div className={`p-3 rounded-lg ${stat.color}`}>
                  <Icon className="w-6 h-6 text-white" />
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Applications Chart */}
        <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Applications This Month</h2>
          <div className="h-64 flex items-end justify-between gap-2">
            {[45, 38, 52, 61, 47, 39, 58].map((height, index) => (
              <div key={index} className="flex-1 flex flex-col items-center">
                <div 
                  className="w-full bg-primary rounded-t opacity-80 hover:opacity-100 transition-opacity"
                  style={{ height: `${(height / 61) * 200}px` }}
                />
                <span className="text-xs text-gray-500 mt-2">
                  {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][index]}
                </span>
              </div>
            ))}
          </div>
        </div>

        {/* Job Status Pie Chart */}
        <div className="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Job Status Distribution</h2>
          <div className="flex items-center justify-center h-64">
            <div className="relative w-48 h-48">
              <svg viewBox="0 0 100 100" className="w-full h-full transform -rotate-90">
                <circle cx="50" cy="50" r="40" fill="none" stroke="#e5e7eb" strokeWidth="8" />
                <circle cx="50" cy="50" r="40" fill="none" stroke="#1e3a8a" strokeWidth="8" 
                  strokeDasharray="150 251" strokeLinecap="round" />
                <circle cx="50" cy="50" r="40" fill="none" stroke="#fbbf24" strokeWidth="8" 
                  strokeDasharray="75 251" strokeDashoffset="-150" strokeLinecap="round" />
                <circle cx="50" cy="50" r="40" fill="none" stroke="#ef4444" strokeWidth="8" 
                  strokeDasharray="25 251" strokeDashoffset="-225" strokeLinecap="round" />
              </svg>
              <div className="absolute inset-0 flex items-center justify-center">
                <div className="text-center">
                  <div className="text-2xl font-bold text-gray-900">24</div>
                  <div className="text-sm text-gray-500">Total Jobs</div>
                </div>
              </div>
            </div>
          </div>
          <div className="flex justify-center gap-6 mt-4">
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 bg-primary rounded-full"></div>
              <span className="text-sm text-gray-600">Active (60%)</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 bg-secondary rounded-full"></div>
              <span className="text-sm text-gray-600">Draft (30%)</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 bg-red-500 rounded-full"></div>
              <span className="text-sm text-gray-600">Closed (10%)</span>
            </div>
          </div>
        </div>
      </div>

      {/* Tables Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Jobs */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200">
          <div className="p-6 border-b border-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">Recent Job Postings</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Job Title</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applications</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {recentJobs.map((job) => (
                  <tr key={job.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4">
                      <div>
                        <div className="font-medium text-gray-900">{job.title}</div>
                        <div className="text-sm text-gray-500">{job.department}</div>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-900">{job.applications}</td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                        job.status === 'Active' 
                          ? 'bg-green-100 text-green-800'
                          : 'bg-yellow-100 text-yellow-800'
                      }`}>
                        {job.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Recent Applicants */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200">
          <div className="p-6 border-b border-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">Recent Applicants</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {recentApplicants.map((applicant) => (
                  <tr key={applicant.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4">
                      <div className="font-medium text-gray-900">{applicant.name}</div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm text-gray-900">{applicant.position}</div>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                        applicant.status === 'Under Review' 
                          ? 'bg-yellow-100 text-yellow-800'
                          : applicant.status === 'Interview Scheduled'
                          ? 'bg-blue-100 text-blue-800'
                          : 'bg-gray-100 text-gray-800'
                      }`}>
                        {applicant.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;