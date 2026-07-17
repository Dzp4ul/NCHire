import React, { useState, useEffect, useCallback } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../contexts/AuthContext';
import StatusBadge from '../../components/shared/StatusBadge';
import LoadingSpinner from '../../components/shared/LoadingSpinner';
import { Briefcase, Users, UserCheck, Clock, Calendar, Activity, ArrowUpRight } from 'lucide-react';

const SCHOOL_YEARS = ['2024-2025', '2025-2026'];
const SEMESTERS = ['First Semester', 'Second Semester'];

const SEMESTER_PARAM: Record<string, string> = {
  'First Semester': 'first_semester',
  'Second Semester': 'second_semester',
};

function getActivityColor(type: string) {
  const t = type?.toLowerCase() || '';
  if (t.includes('hire') || t.includes('approv')) return 'bg-green-100 text-green-600';
  if (t.includes('reject')) return 'bg-red-100 text-red-600';
  if (t.includes('interview') || t.includes('demo') || t.includes('psych')) return 'bg-blue-100 text-blue-600';
  if (t.includes('submit') || t.includes('apply') || t.includes('create')) return 'bg-purple-100 text-purple-600';
  return 'bg-gray-100 text-gray-600';
}

export default function AdminDashboard() {
  const { user } = useAuth();
  const role = user?.role || '';

  const [data, setData] = useState<any>(null);
  const [dashboardStats, setDashboardStats] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  const [schoolYear, setSchoolYear] = useState(SCHOOL_YEARS[1]);
  const [semester, setSemester] = useState(SEMESTERS[0]);
  const [chartData, setChartData] = useState<any[]>([]);

  const fetchAll = useCallback(async () => {
    try {
      const [dash, stats, chart] = await Promise.all([
        api.dashboard(),
        api.getDashboardStats(),
        api.getChart(`school_year=${encodeURIComponent(schoolYear)}&semester=${encodeURIComponent(SEMESTER_PARAM[semester] || semester)}`),
      ]);
      setData(dash);
      setDashboardStats(stats);
      setChartData(chart?.data || chart?.chart_data || []);
    } catch {}
    setLoading(false);
  }, [schoolYear, semester]);

  useEffect(() => {
    fetchAll();
    const interval = setInterval(fetchAll, 30000);
    return () => clearInterval(interval);
  }, [fetchAll]);

  useEffect(() => {
    api.getChart(`school_year=${encodeURIComponent(schoolYear)}&semester=${encodeURIComponent(SEMESTER_PARAM[semester] || semester)}`)
      .then(r => setChartData(r?.data || r?.chart_data || []))
      .catch(() => {});
  }, [schoolYear, semester]);

  if (loading) return <LoadingSpinner />;
  if (!data?.success) return <div className="text-center py-12 text-gray-500">Failed to load dashboard</div>;

  const stats = data.stats || {};
  const totalJobs = stats.total_jobs ?? data.recent_jobs?.length ?? 0;
  const totalApplicants = stats.total_applicants ?? 0;
  const activeUsers = dashboardStats?.active_users ?? 0;

  let pendingValue = 0;
  let pendingLabel = 'Pending Reviews';
  if (role === 'Secretary') {
    pendingValue = stats.secretary_pending ?? 0;
    pendingLabel = 'Pending Review';
  } else if (role === 'Department Head' || role === 'Dean') {
    pendingValue = stats.dept_pending ?? 0;
    pendingLabel = 'Dept. Pending';
  } else {
    pendingValue = (stats.secretary_pending ?? 0) + (stats.dept_pending ?? 0);
  }

  const statCards = [
    { title: 'Total Jobs', value: totalJobs, icon: Briefcase, color: 'bg-blue-500' },
    { title: 'Total Applicants', value: totalApplicants, icon: Users, color: 'bg-green-500' },
    { title: 'Active Users', value: activeUsers, icon: UserCheck, color: 'bg-purple-500' },
    { title: pendingLabel, value: pendingValue, icon: Clock, color: 'bg-orange-500' },
  ];

  const maxChartValue = Math.max(...chartData.map((d: any) => d.count || d.total || 0), 1);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-3xl font-bold text-gray-900">Dashboard</h1>
        <div className="text-sm text-gray-500">Last updated: {data.timestamp}</div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {statCards.map((stat, i) => (
          <div key={i} className="bg-white p-6 rounded-lg shadow-sm border hover:shadow-md transition-shadow">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">{stat.title}</p>
                <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
              </div>
              <div className={`p-3 rounded-lg ${stat.color}`}>
                <stat.icon className="w-6 h-6 text-white" />
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold">Applications Overview</h2>
            </div>
            <div className="flex gap-3">
              <select
                value={schoolYear}
                onChange={e => setSchoolYear(e.target.value)}
                className="text-sm border rounded-lg px-3 py-1.5 bg-gray-50"
              >
                {SCHOOL_YEARS.map(y => (
                  <option key={y} value={y}>{y}</option>
                ))}
              </select>
              <select
                value={semester}
                onChange={e => setSemester(e.target.value)}
                className="text-sm border rounded-lg px-3 py-1.5 bg-gray-50"
              >
                {SEMESTERS.map(s => (
                  <option key={s} value={s}>{s}</option>
                ))}
              </select>
            </div>
          </div>
          <div className="p-6">
            {chartData.length === 0 ? (
              <div className="text-center text-gray-400 py-8">No chart data available</div>
            ) : (
              <div className="flex items-end gap-2 h-48">
                {chartData.map((item: any, i: number) => {
                  const count = item.count || item.total || 0;
                  const height = Math.max((count / maxChartValue) * 100, 2);
                  return (
                    <div key={i} className="flex-1 flex flex-col items-center gap-1">
                      <span className="text-xs font-medium text-gray-600">{count}</span>
                      <div
                        className="w-full bg-blue-500 rounded-t-md transition-all duration-300 hover:bg-blue-600"
                        style={{ height: `${height}%` }}
                        title={`${item.label || item.month || item.category}: ${count}`}
                      />
                      <span className="text-xs text-gray-500 text-center leading-tight">
                        {item.label || item.month || item.category || `M${i + 1}`}
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <h2 className="text-lg font-semibold flex items-center gap-2">
              <Activity className="w-5 h-5 text-gray-400" />
              Recent Activity
            </h2>
          </div>
          <div className="divide-y max-h-96 overflow-y-auto">
            {data.recent_activity?.length === 0 && (
              <div className="px-6 py-8 text-center text-gray-400">No recent activity</div>
            )}
            {data.recent_activity?.map((act: any, i: number) => (
              <div key={i} className="px-6 py-3 flex items-start gap-3 hover:bg-gray-50">
                <div className={`p-2 rounded-full flex-shrink-0 ${getActivityColor(act.activity_type)}`}>
                  <Activity className="w-4 h-4" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm text-gray-900 truncate">{act.description}</p>
                  <p className="text-xs text-gray-500 mt-0.5">
                    {act.user_name && <span>by {act.user_name}</span>}
                    {act.created_at && (
                      <span className="ml-1">- {new Date(act.created_at).toLocaleString()}</span>
                    )}
                  </p>
                </div>
                {act.activity_type && (
                  <span className="text-xs text-gray-400 flex-shrink-0 capitalize">
                    {act.activity_type.replace(/_/g, ' ')}
                  </span>
                )}
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <h2 className="text-lg font-semibold flex items-center gap-2">
              <Briefcase className="w-5 h-5 text-gray-400" />
              Recent Job Postings
            </h2>
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
                {data.recent_jobs?.map((job: any) => (
                  <tr key={job.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4">
                      <div className="font-medium text-gray-900">{job.job_title}</div>
                      <div className="text-sm text-gray-500">{job.department_role}</div>
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-600">{job.application_count || 0}</td>
                    <td className="px-6 py-4"><StatusBadge status={job.status || 'Active'} /></td>
                  </tr>
                ))}
                {(!data.recent_jobs || data.recent_jobs.length === 0) && (
                  <tr><td colSpan={3} className="px-6 py-8 text-center text-gray-400">No jobs yet</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <h2 className="text-lg font-semibold flex items-center gap-2">
              <Users className="w-5 h-5 text-gray-400" />
              Recent Applicants
            </h2>
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
                {data.recent_applicants?.map((app: any) => (
                  <tr key={app.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 font-medium text-gray-900">{app.full_name}</td>
                    <td className="px-6 py-4 text-sm text-gray-600">{app.position}</td>
                    <td className="px-6 py-4"><StatusBadge status={app.workflow_stage || app.status} /></td>
                  </tr>
                ))}
                {(!data.recent_applicants || data.recent_applicants.length === 0) && (
                  <tr><td colSpan={3} className="px-6 py-8 text-center text-gray-400">No applicants yet</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
