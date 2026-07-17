const colors: Record<string, string> = {
  'Active': 'bg-green-100 text-green-800',
  'Closed': 'bg-red-100 text-red-800',
  'Draft': 'bg-yellow-100 text-yellow-800',
  'Pending': 'bg-yellow-100 text-yellow-800',
  'secretary_review': 'bg-blue-100 text-blue-800',
  'secretary_approved': 'bg-blue-100 text-blue-800',
  'department_head_review': 'bg-purple-100 text-purple-800',
  'Interview Scheduled': 'bg-blue-100 text-blue-800',
  'interview_scheduled': 'bg-blue-100 text-blue-800',
  'interview_completed': 'bg-blue-100 text-blue-800',
  'Demo Scheduled': 'bg-indigo-100 text-indigo-800',
  'demo_scheduled': 'bg-indigo-100 text-indigo-800',
  'demo_completed': 'bg-indigo-100 text-indigo-800',
  'Psychological Exam': 'bg-orange-100 text-orange-800',
  'psych_scheduled': 'bg-orange-100 text-orange-800',
  'psych_completed': 'bg-orange-100 text-orange-800',
  'Initially Hired': 'bg-emerald-100 text-emerald-800',
  'initially_hired': 'bg-emerald-100 text-emerald-800',
  'Permanently Hired': 'bg-green-100 text-green-800',
  'permanently_hired': 'bg-green-100 text-green-800',
  'Hired': 'bg-green-100 text-green-800',
  'hired': 'bg-green-100 text-green-800',
  'Rejected': 'bg-red-100 text-red-800',
  'rejected': 'bg-red-100 text-red-800',
  'Cancelled': 'bg-gray-100 text-gray-800',
  'cancelled': 'bg-gray-100 text-gray-800',
  'Resubmission Required': 'bg-amber-100 text-amber-800',
  'Resubmitted': 'bg-cyan-100 text-cyan-800',
  'Inactive': 'bg-red-100 text-red-800',
  'Suspended': 'bg-red-100 text-red-800',
  'Admin': 'bg-red-100 text-red-800',
  'SuperAdmin': 'bg-red-100 text-red-800',
  'HR Manager': 'bg-purple-100 text-purple-800',
  'Department Head': 'bg-blue-100 text-blue-800',
  'Dean': 'bg-blue-100 text-blue-800',
  'Secretary': 'bg-teal-100 text-teal-800',
  'Recruiter': 'bg-gray-100 text-gray-800',
};

export default function StatusBadge({ status }: { status: string }) {
  const cls = colors[status] || 'bg-gray-100 text-gray-800';
  const label = status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  return (
    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${cls}`}>
      {label}
    </span>
  );
}
