import React, { useState, useEffect } from 'react';
import { api } from '../../lib/api';
import StatusBadge from '../../components/shared/StatusBadge';
import LoadingSpinner from '../../components/shared/LoadingSpinner';
import { FileText, Calendar } from 'lucide-react';

export default function MyApplications() {
  const [applications, setApplications] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // The applicant's applications are fetched via their user_id session
    // We use the gets_applicants endpoint which filters by session
    fetch('/admin/gets_applicants.php', { credentials: 'include' })
      .then(r => r.json())
      .then(data => setApplications(Array.isArray(data) ? data : []))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <LoadingSpinner />;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">My Applications</h1>
        <p className="text-gray-500 mt-1">Track the status of your job applications</p>
      </div>

      {applications.length === 0 ? (
        <div className="bg-white rounded-lg border p-12 text-center">
          <FileText className="w-12 h-12 text-gray-300 mx-auto mb-4" />
          <p className="text-gray-500 text-lg">No applications yet</p>
          <p className="text-gray-400 text-sm mt-1">Browse the job board and apply for positions</p>
        </div>
      ) : (
        <div className="space-y-4">
          {applications.map(app => (
            <div key={app.id} className="bg-white rounded-lg border p-6 hover:shadow-sm transition-shadow">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                  <h3 className="text-lg font-semibold text-gray-900">{app.position}</h3>
                  <div className="flex items-center gap-4 mt-1 text-sm text-gray-500">
                    {app.applied_date && (
                      <div className="flex items-center gap-1">
                        <Calendar className="w-4 h-4" />
                        Applied: {new Date(app.applied_date).toLocaleDateString()}
                      </div>
                    )}
                    {app.assigned_to_department && <span>Dept: {app.assigned_to_department}</span>}
                  </div>
                </div>
                <StatusBadge status={app.workflow_stage || app.status} />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
