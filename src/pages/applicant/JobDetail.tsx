import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import type { Job } from '../../lib/types';
import LoadingSpinner from '../../components/shared/LoadingSpinner';
import { ArrowLeft, MapPin, Clock, Briefcase, CheckCircle } from 'lucide-react';

export default function JobDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [job, setJob] = useState<Job | null>(null);
  const [loading, setLoading] = useState(true);
  const [applying, setApplying] = useState(false);
  const [applied, setApplied] = useState(false);
  const [showWizard, setShowWizard] = useState(false);
  const [step, setStep] = useState(1);
  const [formData, setFormData] = useState({
    application_letter: '', contact_num: '', address: '', letter_of_intent: ''
  });

  useEffect(() => {
    if (id) {
      api.getJob(Number(id)).then(data => {
        if (!data.error) setJob(data);
      }).catch(() => {}).finally(() => setLoading(false));
    }
  }, [id]);

  const handleApply = async () => {
    setApplying(true);
    try {
      await api.saveDraft({ job_id: Number(id), ...formData });
      setApplied(true);
      setShowWizard(false);
    } catch {}
    setApplying(false);
  };

  if (loading) return <LoadingSpinner />;
  if (!job) return <div className="text-center py-12 text-gray-500">Job not found</div>;

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <button onClick={() => navigate(-1)} className="flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors">
        <ArrowLeft className="w-5 h-5" /> Back to Jobs
      </button>

      <div className="bg-white rounded-lg border p-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-2">{job.job_title}</h1>
        <p className="text-blue-900 font-semibold text-lg mb-6">{job.department_role}</p>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
          {job.job_type && <div className="flex items-center gap-2 text-gray-600"><Briefcase className="w-5 h-5" /><span>{job.job_type}</span></div>}
          {job.locations && <div className="flex items-center gap-2 text-gray-600"><MapPin className="w-5 h-5" /><span>{job.locations}</span></div>}
          {job.application_deadline && <div className="flex items-center gap-2 text-gray-600"><Clock className="w-5 h-5" /><span>Deadline: {new Date(job.application_deadline).toLocaleDateString()}</span></div>}
          {job.salary_range && <div className="text-green-600 font-semibold text-lg">{job.salary_range}</div>}
        </div>

        {job.job_description && (
          <div className="mb-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-3">Job Description</h2>
            <p className="text-gray-600 whitespace-pre-line">{job.job_description}</p>
          </div>
        )}

        {job.job_requirements && (
          <div className="mb-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-3">Requirements</h2>
            <p className="text-gray-600 whitespace-pre-line">{job.job_requirements}</p>
          </div>
        )}

        {job.education && (
          <div className="mb-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-3">Education</h2>
            <p className="text-gray-600 whitespace-pre-line">{job.education}</p>
          </div>
        )}

        {job.experience && (
          <div className="mb-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-3">Experience</h2>
            <p className="text-gray-600 whitespace-pre-line">{job.experience}</p>
          </div>
        )}

        {job.duties && (
          <div className="mb-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-3">Duties</h2>
            <p className="text-gray-600 whitespace-pre-line">{job.duties}</p>
          </div>
        )}

        {applied ? (
          <div className="bg-green-50 text-green-700 p-4 rounded-lg flex items-center gap-3">
            <CheckCircle className="w-6 h-6" />
            <div>
              <p className="font-semibold">Application Submitted!</p>
              <p className="text-sm">Your application is being reviewed by the secretary.</p>
            </div>
          </div>
        ) : showWizard ? (
          <div className="bg-gray-50 border rounded-lg p-6 space-y-4">
            <h3 className="text-lg font-semibold">Apply for {job.job_title}</h3>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Contact Number *</label>
              <input type="tel" value={formData.contact_num} onChange={e => setFormData(p => ({ ...p, contact_num: e.target.value }))} className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="+63 9XX XXX XXXX" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Address</label>
              <input type="text" value={formData.address} onChange={e => setFormData(p => ({ ...p, address: e.target.value }))} className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Application Letter *</label>
              <textarea rows={5} value={formData.application_letter} onChange={e => setFormData(p => ({ ...p, application_letter: e.target.value }))} className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Write your application letter..." />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Letter of Intent</label>
              <textarea rows={3} value={formData.letter_of_intent} onChange={e => setFormData(p => ({ ...p, letter_of_intent: e.target.value }))} className="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div className="flex gap-3">
              <button onClick={() => setShowWizard(false)} className="px-4 py-2 border rounded-lg hover:bg-gray-100 transition-colors">Cancel</button>
              <button onClick={handleApply} disabled={applying || !formData.contact_num || !formData.application_letter} className="px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition-colors disabled:opacity-50">
                {applying ? 'Submitting...' : 'Submit Application'}
              </button>
            </div>
          </div>
        ) : (
          <button onClick={() => setShowWizard(true)} className="bg-blue-900 text-white px-8 py-3 rounded-lg hover:bg-blue-800 transition-colors font-semibold text-lg">
            Apply for This Position
          </button>
        )}
      </div>
    </div>
  );
}
