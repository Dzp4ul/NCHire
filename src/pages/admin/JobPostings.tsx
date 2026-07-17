import React, { useState, useEffect } from 'react';
import { api } from '../../lib/api';
import type { Job } from '../../lib/types';
import StatusBadge from '../../components/shared/StatusBadge';
import Modal from '../../components/shared/Modal';
import LoadingSpinner from '../../components/shared/LoadingSpinner';
import { Plus, Search, Eye, Edit, Trash2, ToggleLeft, ToggleRight, Briefcase, ExternalLink } from 'lucide-react';

const DEPARTMENTS = ['Computing Studies', 'Hospitality Management', 'Education'];
const JOB_TYPES = ['Full-time', 'Part-time', 'Contract'];

const defaultForm = {
  job_title: '',
  department_role: '',
  job_type: 'Full-time',
  locations: 'Norzagaray College',
  salary_range: '',
  application_deadline: '',
  job_description: '',
  education: '',
  experience: '',
  training: '',
  eligibility: '',
  duties: '',
  competency: '',
};

export default function JobPostings() {
  const [jobs, setJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filterStatus, setFilterStatus] = useState('all');
  const [showCreate, setShowCreate] = useState(false);
  const [showEdit, setShowEdit] = useState<Job | null>(null);
  const [showDetail, setShowDetail] = useState<Job | null>(null);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<Job | null>(null);
  const [form, setForm] = useState(defaultForm);
  const [submitting, setSubmitting] = useState(false);

  const loadJobs = () => {
    api.getJobs().then(data => setJobs(data)).catch(() => {}).finally(() => setLoading(false));
  };

  useEffect(() => { loadJobs(); }, []);

  const filtered = jobs.filter(j => {
    const matchSearch = j.job_title?.toLowerCase().includes(search.toLowerCase()) || j.department_role?.toLowerCase().includes(search.toLowerCase());
    const matchFilter = filterStatus === 'all' || j.status?.toLowerCase() === filterStatus;
    return matchSearch && matchFilter;
  });

  const resetForm = () => setForm(defaultForm);

  const openEditModal = (job: Job) => {
    setForm({
      job_title: job.job_title || '',
      department_role: job.department_role || '',
      job_type: job.job_type || 'Full-time',
      locations: job.locations || 'Norzagaray College',
      salary_range: job.salary_range || '',
      application_deadline: job.application_deadline || '',
      job_description: job.job_description || '',
      education: job.education || '',
      experience: job.experience || '',
      training: job.training || '',
      eligibility: job.eligibility || '',
      duties: job.duties || '',
      competency: job.competency || '',
    });
    setShowEdit(job);
  };

  const handleCreate = async () => {
    setSubmitting(true);
    try {
      const res = await api.addJob(form);
      if (res.success) {
        setShowCreate(false);
        resetForm();
        loadJobs();
      }
    } finally {
      setSubmitting(false);
    }
  };

  const handleEdit = async () => {
    if (!showEdit) return;
    setSubmitting(true);
    try {
      const res = await api.updateJob({ id: showEdit.id, ...form });
      if (res.success) {
        setShowEdit(null);
        resetForm();
        loadJobs();
      }
    } finally {
      setSubmitting(false);
    }
  };

  const handleToggleStatus = async (job: Job) => {
    const newStatus = job.status?.toLowerCase() === 'active' ? 'closed' : 'active';
    await api.toggleJobStatus(job.id, newStatus);
    loadJobs();
  };

  const handleDelete = async () => {
    if (!showDeleteConfirm) return;
    setSubmitting(true);
    try {
      await api.deleteJob(showDeleteConfirm.id);
      setShowDeleteConfirm(null);
      loadJobs();
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) return <LoadingSpinner />;

  const renderForm = (onSubmit: () => void, submitLabel: string) => (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Job Title *</label>
        <input
          type="text"
          value={form.job_title}
          onChange={e => setForm(p => ({ ...p, job_title: e.target.value }))}
          className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="e.g., Instructor I"
        />
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Department *</label>
          <select
            value={form.department_role}
            onChange={e => setForm(p => ({ ...p, department_role: e.target.value }))}
            className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select Department</option>
            {DEPARTMENTS.map(d => <option key={d} value={d}>{d}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Job Type *</label>
          <select
            value={form.job_type}
            onChange={e => setForm(p => ({ ...p, job_type: e.target.value }))}
            className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            {JOB_TYPES.map(t => <option key={t} value={t}>{t}</option>)}
          </select>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Location</label>
          <input
            type="text"
            value={form.locations}
            onChange={e => setForm(p => ({ ...p, locations: e.target.value }))}
            className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Salary Range</label>
          <input
            type="text"
            value={form.salary_range}
            onChange={e => setForm(p => ({ ...p, salary_range: e.target.value }))}
            className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="e.g., ₱25,000 - ₱35,000"
          />
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Application Deadline</label>
        <input
          type="date"
          value={form.application_deadline}
          onChange={e => setForm(p => ({ ...p, application_deadline: e.target.value }))}
          className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Job Description</label>
        <textarea
          rows={4}
          value={form.job_description}
          onChange={e => setForm(p => ({ ...p, job_description: e.target.value }))}
          className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Describe the role, responsibilities, and qualifications..."
        />
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Education</label>
          <textarea
            rows={3}
            value={form.education}
            onChange={e => setForm(p => ({ ...p, education: e.target.value }))}
            className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Educational requirements..."
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Experience</label>
          <textarea
            rows={3}
            value={form.experience}
            onChange={e => setForm(p => ({ ...p, experience: e.target.value }))}
            className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Experience requirements..."
          />
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Training</label>
          <textarea
            rows={3}
            value={form.training}
            onChange={e => setForm(p => ({ ...p, training: e.target.value }))}
            className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Training requirements..."
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Eligibility</label>
          <textarea
            rows={3}
            value={form.eligibility}
            onChange={e => setForm(p => ({ ...p, eligibility: e.target.value }))}
            className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Eligibility requirements..."
          />
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Duties</label>
          <textarea
            rows={3}
            value={form.duties}
            onChange={e => setForm(p => ({ ...p, duties: e.target.value }))}
            className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="List of duties..."
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Competency</label>
          <textarea
            rows={3}
            value={form.competency}
            onChange={e => setForm(p => ({ ...p, competency: e.target.value }))}
            className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Required competencies..."
          />
        </div>
      </div>

      <div className="flex justify-end gap-3 pt-4 border-t">
        <button
          onClick={() => { setShowCreate(false); setShowEdit(null); resetForm(); }}
          className="px-4 py-2 border rounded-lg hover:bg-gray-50 transition-colors"
        >
          Cancel
        </button>
        <button
          onClick={onSubmit}
          disabled={submitting}
          className="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition-colors disabled:opacity-50"
        >
          {submitting ? 'Saving...' : submitLabel}
        </button>
      </div>
    </div>
  );

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Briefcase className="w-8 h-8 text-blue-900" />
          <h1 className="text-3xl font-bold text-gray-900">Job Postings</h1>
        </div>
        <button
          onClick={() => { resetForm(); setShowCreate(true); }}
          className="bg-blue-900 text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition-colors flex items-center gap-2"
        >
          <Plus className="w-4 h-4" /> Create Job
        </button>
      </div>

      <div className="bg-white p-4 rounded-lg shadow-sm border flex flex-col md:flex-row gap-4">
        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="text"
            placeholder="Search by title or department..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <select
          value={filterStatus}
          onChange={e => setFilterStatus(e.target.value)}
          className="border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="all">All Status</option>
          <option value="active">Active</option>
          <option value="closed">Closed</option>
        </select>
      </div>

      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job Details</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deadline</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {filtered.map(job => (
                <tr key={job.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-6 py-4">
                    <div className="font-medium text-gray-900">{job.job_title}</div>
                    <div className="text-sm text-gray-500">{job.locations}</div>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-700">{job.department_role}</td>
                  <td className="px-6 py-4">
                    <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                      {job.job_type}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-700">
                    {job.application_deadline
                      ? new Date(job.application_deadline).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                      : '—'}
                  </td>
                  <td className="px-6 py-4">
                    <StatusBadge status={job.status || 'Active'} />
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => setShowDetail(job)}
                        className="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition-colors"
                        title="View"
                      >
                        <Eye className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => openEditModal(job)}
                        className="p-1.5 text-gray-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition-colors"
                        title="Edit"
                      >
                        <Edit className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleToggleStatus(job)}
                        className="p-1.5 text-gray-400 hover:text-purple-600 rounded-lg hover:bg-purple-50 transition-colors"
                        title={`Toggle to ${job.status?.toLowerCase() === 'active' ? 'Closed' : 'Active'}`}
                      >
                        {job.status?.toLowerCase() === 'active'
                          ? <ToggleRight className="w-4 h-4 text-green-600" />
                          : <ToggleLeft className="w-4 h-4 text-gray-400" />}
                      </button>
                      <button
                        onClick={() => setShowDeleteConfirm(job)}
                        className="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors"
                        title="Delete"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {filtered.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-6 py-12 text-center">
                    <Briefcase className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p className="text-gray-400 text-sm">No jobs found</p>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Create Modal */}
      <Modal open={showCreate} onClose={() => { setShowCreate(false); resetForm(); }} title="Create New Job Posting" maxWidth="max-w-2xl">
        {renderForm(handleCreate, 'Publish Job')}
      </Modal>

      {/* Edit Modal */}
      <Modal open={!!showEdit} onClose={() => { setShowEdit(null); resetForm(); }} title={`Edit: ${showEdit?.job_title}`} maxWidth="max-w-2xl">
        {showEdit && renderForm(handleEdit, 'Save Changes')}
      </Modal>

      {/* View Modal */}
      <Modal open={!!showDetail} onClose={() => setShowDetail(null)} title={showDetail?.job_title} maxWidth="max-w-2xl">
        {showDetail && (
          <div className="space-y-5 text-sm">
            <div className="flex items-center gap-2 text-gray-500">
              <ExternalLink className="w-4 h-4" />
              <span>{showDetail.locations}</span>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="bg-gray-50 rounded-lg p-3">
                <span className="block text-xs font-medium text-gray-500 uppercase mb-1">Department</span>
                <span className="text-gray-900">{showDetail.department_role}</span>
              </div>
              <div className="bg-gray-50 rounded-lg p-3">
                <span className="block text-xs font-medium text-gray-500 uppercase mb-1">Job Type</span>
                <span className="text-gray-900">{showDetail.job_type}</span>
              </div>
              <div className="bg-gray-50 rounded-lg p-3">
                <span className="block text-xs font-medium text-gray-500 uppercase mb-1">Salary Range</span>
                <span className="text-green-600 font-medium">{showDetail.salary_range || '—'}</span>
              </div>
              <div className="bg-gray-50 rounded-lg p-3">
                <span className="block text-xs font-medium text-gray-500 uppercase mb-1">Deadline</span>
                <span className="text-gray-900">
                  {showDetail.application_deadline
                    ? new Date(showDetail.application_deadline).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
                    : '—'}
                </span>
              </div>
              <div className="bg-gray-50 rounded-lg p-3">
                <span className="block text-xs font-medium text-gray-500 uppercase mb-1">Status</span>
                <StatusBadge status={showDetail.status || 'Active'} />
              </div>
            </div>

            {showDetail.job_description && (
              <div>
                <h4 className="font-semibold text-gray-800 mb-1">Job Description</h4>
                <p className="text-gray-600 whitespace-pre-line bg-gray-50 rounded-lg p-3">{showDetail.job_description}</p>
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {showDetail.education && (
                <div>
                  <h4 className="font-semibold text-gray-800 mb-1">Education</h4>
                  <p className="text-gray-600 whitespace-pre-line bg-gray-50 rounded-lg p-3">{showDetail.education}</p>
                </div>
              )}
              {showDetail.experience && (
                <div>
                  <h4 className="font-semibold text-gray-800 mb-1">Experience</h4>
                  <p className="text-gray-600 whitespace-pre-line bg-gray-50 rounded-lg p-3">{showDetail.experience}</p>
                </div>
              )}
              {showDetail.training && (
                <div>
                  <h4 className="font-semibold text-gray-800 mb-1">Training</h4>
                  <p className="text-gray-600 whitespace-pre-line bg-gray-50 rounded-lg p-3">{showDetail.training}</p>
                </div>
              )}
              {showDetail.eligibility && (
                <div>
                  <h4 className="font-semibold text-gray-800 mb-1">Eligibility</h4>
                  <p className="text-gray-600 whitespace-pre-line bg-gray-50 rounded-lg p-3">{showDetail.eligibility}</p>
                </div>
              )}
              {showDetail.duties && (
                <div>
                  <h4 className="font-semibold text-gray-800 mb-1">Duties</h4>
                  <p className="text-gray-600 whitespace-pre-line bg-gray-50 rounded-lg p-3">{showDetail.duties}</p>
                </div>
              )}
              {showDetail.competency && (
                <div>
                  <h4 className="font-semibold text-gray-800 mb-1">Competency</h4>
                  <p className="text-gray-600 whitespace-pre-line bg-gray-50 rounded-lg p-3">{showDetail.competency}</p>
                </div>
              )}
            </div>

            <div className="flex justify-end pt-2">
              <button
                onClick={() => { setShowDetail(null); openEditModal(showDetail); }}
                className="flex items-center gap-2 px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition-colors"
              >
                <Edit className="w-4 h-4" /> Edit Job
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal open={!!showDeleteConfirm} onClose={() => setShowDeleteConfirm(null)} title="Delete Job Posting" maxWidth="max-w-md">
        {showDeleteConfirm && (
          <div className="space-y-4">
            <div className="flex items-start gap-3">
              <div className="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                <Trash2 className="w-5 h-5 text-red-600" />
              </div>
              <div>
                <p className="text-gray-900 font-medium">Are you sure you want to delete this job?</p>
                <p className="text-sm text-gray-500 mt-1">
                  <strong>{showDeleteConfirm.job_title}</strong> in {showDeleteConfirm.department_role} will be permanently removed. This action cannot be undone.
                </p>
              </div>
            </div>
            <div className="flex justify-end gap-3 pt-2">
              <button
                onClick={() => setShowDeleteConfirm(null)}
                className="px-4 py-2 border rounded-lg hover:bg-gray-50 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleDelete}
                disabled={submitting}
                className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50"
              >
                {submitting ? 'Deleting...' : 'Delete Job'}
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
