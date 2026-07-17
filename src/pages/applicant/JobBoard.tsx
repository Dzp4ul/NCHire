import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import type { Job } from '../../lib/types';
import LoadingSpinner from '../../components/shared/LoadingSpinner';
import { Search, MapPin, Clock, Briefcase, ChevronRight, Filter } from 'lucide-react';

export default function JobBoard() {
  const [jobs, setJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filterType, setFilterType] = useState('all');
  const navigate = useNavigate();

  useEffect(() => {
    api.getHomepageJobs().then(data => setJobs(data)).catch(() => {}).finally(() => setLoading(false));
  }, []);

  const filtered = jobs.filter(j => {
    const matchSearch = j.job_title?.toLowerCase().includes(search.toLowerCase()) || j.department_role?.toLowerCase().includes(search.toLowerCase());
    const matchType = filterType === 'all' || j.job_type?.toLowerCase() === filterType;
    return matchSearch && matchType;
  });

  if (loading) return <LoadingSpinner />;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Job Board</h1>
        <p className="text-gray-500 mt-1">Explore open positions at Norzagaray College</p>
      </div>

      <div className="bg-white p-4 rounded-lg shadow-sm border flex flex-col md:flex-row gap-4">
        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input type="text" placeholder="Search jobs..." value={search} onChange={e => setSearch(e.target.value)} className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
        </div>
        <select value={filterType} onChange={e => setFilterType(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="all">All Types</option>
          <option value="full-time">Full-time</option>
          <option value="part-time">Part-time</option>
          <option value="contract">Contract</option>
        </select>
      </div>

      {filtered.length === 0 ? (
        <div className="bg-white rounded-lg border p-12 text-center text-gray-500">No jobs found</div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filtered.map(job => (
            <div key={job.id} className="bg-white border rounded-lg p-6 hover:shadow-md transition-shadow">
              <h3 className="text-lg font-semibold text-gray-900 mb-1">{job.job_title}</h3>
              <p className="text-blue-900 font-medium text-sm mb-3">{job.department_role}</p>
              <div className="space-y-2 text-sm text-gray-500 mb-4">
                {job.job_type && <div className="flex items-center gap-2"><Briefcase className="w-4 h-4" />{job.job_type}</div>}
                {job.locations && <div className="flex items-center gap-2"><MapPin className="w-4 h-4" />{job.locations}</div>}
                {job.application_deadline && <div className="flex items-center gap-2"><Clock className="w-4 h-4" />Deadline: {new Date(job.application_deadline).toLocaleDateString()}</div>}
                {job.salary_range && <div className="text-green-600 font-medium">{job.salary_range}</div>}
              </div>
              <button onClick={() => navigate(`/applicant/jobs/${job.id}`)} className="w-full bg-blue-900 text-white py-2 rounded-lg hover:bg-blue-800 transition-colors text-sm font-medium flex items-center justify-center gap-1">
                View Details <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
