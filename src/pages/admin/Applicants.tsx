import React, { useState, useEffect, useCallback } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../contexts/AuthContext';
import type { Applicant } from '../../lib/types';
import StatusBadge from '../../components/shared/StatusBadge';
import Modal from '../../components/shared/Modal';
import LoadingSpinner from '../../components/shared/LoadingSpinner';
import {
  Search, Eye, User, Clock, CheckCircle, Calendar, ArrowLeft,
  FileText, GraduationCap, Briefcase, Award, MapPin, Phone, Mail,
  ExternalLink, Send, AlertTriangle, UserCheck, Presentation,
  RotateCcw, ChevronLeft, ChevronRight, Building, Star,
  UserPlus, CalendarDays, ClipboardCheck, X
} from 'lucide-react';

interface ApplicantDetailData {
  id: number;
  full_name: string;
  applicant_name: string;
  applicant_email: string;
  contact_num: string;
  position: string;
  department?: string;
  applied_date: string;
  status: string;
  workflow_stage: string;
  user_id: number;
  job_id: number;
  assigned_to_department: string;
  profile_picture?: string;
  address?: string;
  application_letter?: string;
  resume?: string;
  tor?: string;
  diploma?: string;
  professional_license?: string;
  coe?: string;
  seminars_trainings?: string;
  masteral_cert?: string;
  letter_of_intent?: string;
  interview_date?: string;
  interview_time?: string;
  interview_location?: string;
  interview_room?: string;
  interview_notes?: string;
  demo_date?: string;
  demo_time?: string;
  demo_location?: string;
  demo_room?: string;
  demo_notes?: string;
  rejection_reason?: string;
  rejected_date?: string;
  [key: string]: any;
}

const DOCUMENT_FIELDS = [
  { key: 'application_letter', label: 'Application Letter', icon: FileText },
  { key: 'resume', label: 'Resume / CV', icon: FileText },
  { key: 'tor', label: 'Transcript of Records', icon: FileText },
  { key: 'diploma', label: 'Diploma', icon: GraduationCap },
  { key: 'professional_license', label: 'Professional License', icon: Award },
  { key: 'coe', label: 'Certificate of Employment', icon: Briefcase },
  { key: 'seminars_trainings', label: 'Seminars / Trainings', icon: Star },
  { key: 'masteral_cert', label: 'Masteral Certificate', icon: GraduationCap },
  { key: 'letter_of_intent', label: 'Letter of Intent', icon: Mail },
];

const ITEMS_PER_PAGE = 10;

export default function Applicants() {
  const { user } = useAuth();
  const role = user?.role || '';

  const [applicants, setApplicants] = useState<Applicant[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filterStatus, setFilterStatus] = useState('all');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [currentPage, setCurrentPage] = useState(1);

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<ApplicantDetailData | null>(null);
  const [education, setEducation] = useState<any[]>([]);
  const [experience, setExperience] = useState<any[]>([]);
  const [skills, setSkills] = useState<any>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const [actionLoading, setActionLoading] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');
  const [errorMsg, setErrorMsg] = useState('');

  const [modals, setModals] = useState({
    scheduleInterview: false,
    approveInterview: false,
    rescheduleInterview: false,
    scheduleDemo: false,
    approveDemo: false,
    rescheduleDemo: false,
    hire: false,
    transferToDean: false,
    resubmission: false,
    reject: false,
  });

  const [interviewForm, setInterviewForm] = useState({ date: '', time: '', room: '', notes: '' });
  const [approveInterviewForm, setApproveInterviewForm] = useState({ notes: '' });
  const [reschedInterviewForm, setReschedInterviewForm] = useState({ date: '', time: '', room: '', reason: '' });
  const [demoForm, setDemoForm] = useState({ date: '', time: '', room: '', notes: '' });
  const [approveDemoForm, setApproveDemoForm] = useState({ notes: '' });
  const [reschedDemoForm, setReschedDemoForm] = useState({ date: '', time: '', room: '', reason: '' });
  const [hireForm, setHireForm] = useState({ notes: '' });
  const [transferForm, setTransferForm] = useState({ notes: '' });
  const [resubmitDocs, setResubmitDocs] = useState<Record<string, boolean>>({
    application_letter: false, letter_of_intent: false, resume: false,
    tor: false, diploma: false, professional_license: false,
    coe: false, seminars_trainings: false, masteral_cert: false,
  });
  const [resubmitReason, setResubmitReason] = useState('');
  const [rejectReason, setRejectReason] = useState('');

  const openModal = (key: keyof typeof modals) => setModals(prev => ({ ...prev, [key]: true }));

  const closeAllModals = () => {
    setModals({
      scheduleInterview: false, approveInterview: false, rescheduleInterview: false,
      scheduleDemo: false, approveDemo: false, rescheduleDemo: false,
      hire: false, transferToDean: false, resubmission: false, reject: false,
    });
  };

  const resetForms = () => {
    setInterviewForm({ date: '', time: '', room: '', notes: '' });
    setApproveInterviewForm({ notes: '' });
    setReschedInterviewForm({ date: '', time: '', room: '', reason: '' });
    setDemoForm({ date: '', time: '', room: '', notes: '' });
    setApproveDemoForm({ notes: '' });
    setReschedDemoForm({ date: '', time: '', room: '', reason: '' });
    setHireForm({ notes: '' });
    setTransferForm({ notes: '' });
    setResubmitDocs({
      application_letter: false, letter_of_intent: false, resume: false,
      tor: false, diploma: false, professional_license: false,
      coe: false, seminars_trainings: false, masteral_cert: false,
    });
    setResubmitReason('');
    setRejectReason('');
  };

  const fetchList = useCallback(async () => {
    try {
      setLoading(true);
      const data = await api.getApplicants();
      setApplicants(Array.isArray(data) ? data : []);
    } catch {
      setApplicants([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const fetchDetail = useCallback(async (id: number) => {
    try {
      setDetailLoading(true);
      const res = await api.getApplicantDetail(id);
      if (res.success && res.applicant) {
        setDetail(res.applicant);
        setEducation(res.education || []);
        setExperience(res.experience || []);
        setSkills(res.skills || null);
      }
    } catch {
      setDetail(null);
    } finally {
      setDetailLoading(false);
    }
  }, []);

  useEffect(() => { fetchList(); }, [fetchList]);
  useEffect(() => { if (selectedId !== null) fetchDetail(selectedId); }, [selectedId, fetchDetail]);

  const handleAction = async (data: Record<string, string>, useSecretary = false) => {
    try {
      setActionLoading(true);
      setErrorMsg('');
      const fn = useSecretary ? api.secretaryAction : api.processApplicantAction;
      const res = await fn(data);
      if (res.success) {
        setSuccessMsg(res.message || 'Action completed successfully');
        setTimeout(() => setSuccessMsg(''), 5000);
        closeAllModals();
        resetForms();
        if (selectedId !== null) await fetchDetail(selectedId);
      } else {
        setErrorMsg(res.message || 'Action failed');
        setTimeout(() => setErrorMsg(''), 5000);
      }
    } catch (err: any) {
      setErrorMsg(err.message || 'An error occurred');
      setTimeout(() => setErrorMsg(''), 5000);
    } finally {
      setActionLoading(false);
    }
  };

  const submitScheduleInterview = () => {
    handleAction({
      action: 'schedule_interview',
      applicant_id: String(selectedId),
      interview_date: interviewForm.date,
      interview_time: interviewForm.time,
      interview_location: 'Norzagaray College',
      interview_room: interviewForm.room,
      interview_notes: interviewForm.notes,
    });
  };

  const submitApproveInterview = () => {
    handleAction({
      action: 'approve_interview',
      applicant_id: String(selectedId),
      evaluation_notes: approveInterviewForm.notes,
    });
  };

  const submitRescheduleInterview = () => {
    handleAction({
      action: 'reschedule_interview',
      applicant_id: String(selectedId),
      interview_date: reschedInterviewForm.date,
      interview_time: reschedInterviewForm.time,
      interview_location: 'Norzagaray College',
      interview_room: reschedInterviewForm.room,
      reason: reschedInterviewForm.reason,
    });
  };

  const submitScheduleDemo = () => {
    handleAction({
      action: 'schedule_demo',
      applicant_id: String(selectedId),
      demo_date: demoForm.date,
      demo_time: demoForm.time,
      demo_location: 'Norzagaray College',
      demo_room: demoForm.room,
      demo_notes: demoForm.notes,
    });
  };

  const submitApproveDemo = () => {
    handleAction({
      action: 'approve_demo',
      applicant_id: String(selectedId),
      evaluation_notes: approveDemoForm.notes,
    });
  };

  const submitRescheduleDemo = () => {
    handleAction({
      action: 'reschedule_demo',
      applicant_id: String(selectedId),
      demo_date: reschedDemoForm.date,
      demo_time: reschedDemoForm.time,
      demo_location: 'Norzagaray College',
      demo_room: reschedDemoForm.room,
      reason: reschedDemoForm.reason,
    });
  };

  const submitHire = () => {
    handleAction({
      action: 'hire_applicant',
      applicant_id: String(selectedId),
      notes: hireForm.notes,
    });
  };

  const submitTransfer = () => {
    handleAction({
      action: 'transfer_to_dept_head',
      applicant_id: String(selectedId),
      notes: transferForm.notes,
    }, true);
  };

  const submitResubmission = () => {
    const docs = Object.entries(resubmitDocs).filter(([, v]) => v).map(([k]) => k);
    handleAction({
      action: 'request_resubmission',
      applicant_id: String(selectedId),
      documents: docs.join(','),
      notes: resubmitReason,
    });
  };

  const submitReject = () => {
    handleAction({
      action: 'reject_application',
      applicant_id: String(selectedId),
      rejection_reason: rejectReason,
    });
  };

  const filtered = applicants.filter(a => {
    const matchSearch = !search ||
      (a.full_name || '').toLowerCase().includes(search.toLowerCase()) ||
      (a.applicant_email || '').toLowerCase().includes(search.toLowerCase());
    const matchStatus = filterStatus === 'all' || a.workflow_stage === filterStatus || a.status === filterStatus;
    let matchDate = true;
    if (dateFrom) matchDate = matchDate && new Date(a.applied_date) >= new Date(dateFrom);
    if (dateTo) matchDate = matchDate && new Date(a.applied_date) <= new Date(dateTo + 'T23:59:59');
    return matchSearch && matchStatus && matchDate;
  });

  const totalPages = Math.ceil(filtered.length / ITEMS_PER_PAGE);
  const paginated = filtered.slice((currentPage - 1) * ITEMS_PER_PAGE, currentPage * ITEMS_PER_PAGE);

  const stats = {
    total: applicants.length,
    interviews: applicants.filter(a => ['interview_scheduled', 'interview_completed'].includes(a.workflow_stage)).length,
    demos: applicants.filter(a => ['demo_scheduled', 'demo_completed'].includes(a.workflow_stage)).length,
    hired: applicants.filter(a => ['initially_hired', 'permanently_hired', 'hired'].includes(a.workflow_stage)).length,
  };

  const stage = detail?.workflow_stage || '';

  const FormField = ({ label, children }: { label: string; children: React.ReactNode }) => (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      {children}
    </div>
  );

  const inputCls = "w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500";
  const textareaCls = `${inputCls} resize-none`;

  const ActionButton = ({
    label, icon: Icon, color, onClick, disabled,
  }: {
    label: string; icon: any; color: string; onClick: () => void; disabled?: boolean;
  }) => (
    <button
      onClick={onClick}
      disabled={disabled || actionLoading}
      className={`w-full flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-white transition-colors ${color} disabled:opacity-50 disabled:cursor-not-allowed`}
    >
      <Icon className="w-4 h-4" />
      {label}
    </button>
  );

  if (loading) return <LoadingSpinner />;

  return (
    <div className="space-y-6">
      {successMsg && (
        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <CheckCircle className="w-5 h-5 flex-shrink-0" />
          <span className="text-sm font-medium">{successMsg}</span>
          <button onClick={() => setSuccessMsg('')} className="ml-auto"><X className="w-4 h-4" /></button>
        </div>
      )}
      {errorMsg && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <AlertTriangle className="w-5 h-5 flex-shrink-0" />
          <span className="text-sm font-medium">{errorMsg}</span>
          <button onClick={() => setErrorMsg('')} className="ml-auto"><X className="w-4 h-4" /></button>
        </div>
      )}

      {selectedId === null ? (
        <>
          <div className="flex items-center justify-between">
            <h1 className="text-3xl font-bold text-gray-900">Applicants</h1>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
            {[
              { label: 'Total Applicants', value: stats.total, icon: User, color: 'text-blue-500', bg: 'bg-blue-50' },
              { label: 'Interviews Scheduled', value: stats.interviews, icon: Calendar, color: 'text-purple-500', bg: 'bg-purple-50' },
              { label: 'Demo Scheduled', value: stats.demos, icon: Presentation, color: 'text-indigo-500', bg: 'bg-indigo-50' },
              { label: 'Hired', value: stats.hired, icon: CheckCircle, color: 'text-green-500', bg: 'bg-green-50' },
            ].map((s, i) => (
              <div key={i} className="bg-white p-6 rounded-lg shadow-sm border">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">{s.label}</p>
                    <p className="text-2xl font-bold text-gray-900">{s.value}</p>
                  </div>
                  <div className={`${s.bg} p-3 rounded-full`}>
                    <s.icon className={`w-6 h-6 ${s.color}`} />
                  </div>
                </div>
              </div>
            ))}
          </div>

          <div className="bg-white p-4 rounded-lg shadow-sm border flex flex-col lg:flex-row gap-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                type="text"
                placeholder="Search by name or email..."
                value={search}
                onChange={e => { setSearch(e.target.value); setCurrentPage(1); }}
                className="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
              />
            </div>
            <select
              value={filterStatus}
              onChange={e => { setFilterStatus(e.target.value); setCurrentPage(1); }}
              className="border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            >
              <option value="all">All Status</option>
              <option value="secretary_review">Pending Review</option>
              <option value="department_head_review">Dept Head Review</option>
              <option value="interview_scheduled">Interview Scheduled</option>
              <option value="interview_completed">Interview Completed</option>
              <option value="demo_scheduled">Demo Scheduled</option>
              <option value="demo_completed">Demo Completed</option>
              <option value="initially_hired">Initially Hired</option>
              <option value="hired">Hired</option>
              <option value="rejected">Rejected</option>
            </select>
            <input
              type="date"
              value={dateFrom}
              onChange={e => { setDateFrom(e.target.value); setCurrentPage(1); }}
              placeholder="From date"
              className="border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            />
            <input
              type="date"
              value={dateTo}
              onChange={e => { setDateTo(e.target.value); setCurrentPage(1); }}
              placeholder="To date"
              className="border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            />
          </div>

          <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applied</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {paginated.map(app => (
                    <tr key={app.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          {app.profile_picture ? (
                            <img src={app.profile_picture} alt="" className="w-10 h-10 rounded-full object-cover" />
                          ) : (
                            <div className="w-10 h-10 bg-blue-900 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                              {(app.full_name || '?')[0]}
                            </div>
                          )}
                          <div>
                            <div className="font-medium text-gray-900">{app.full_name}</div>
                            <div className="text-sm text-gray-500">{app.applicant_email}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-700">{app.position}</td>
                      <td className="px-6 py-4 text-sm text-gray-700">{app.assigned_to_department || '-'}</td>
                      <td className="px-6 py-4 text-sm text-gray-700">
                        {app.applied_date ? new Date(app.applied_date).toLocaleDateString() : '-'}
                      </td>
                      <td className="px-6 py-4">
                        <StatusBadge status={app.workflow_stage || app.status} />
                      </td>
                      <td className="px-6 py-4">
                        <button
                          onClick={() => setSelectedId(app.id)}
                          className="text-blue-600 hover:text-blue-800 flex items-center gap-1 text-sm font-medium"
                        >
                          <Eye className="w-4 h-4" /> View
                        </button>
                      </td>
                    </tr>
                  ))}
                  {paginated.length === 0 && (
                    <tr>
                      <td colSpan={6} className="px-6 py-12 text-center text-gray-400">
                        <User className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                        <p className="text-sm">No applicants found</p>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            {totalPages > 1 && (
              <div className="flex items-center justify-between px-6 py-3 border-t bg-gray-50">
                <span className="text-sm text-gray-600">
                  Showing {((currentPage - 1) * ITEMS_PER_PAGE) + 1} to{' '}
                  {Math.min(currentPage * ITEMS_PER_PAGE, filtered.length)} of {filtered.length}
                </span>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                    disabled={currentPage === 1}
                    className="p-1 rounded hover:bg-gray-200 disabled:opacity-40"
                  >
                    <ChevronLeft className="w-5 h-5" />
                  </button>
                  {Array.from({ length: totalPages }, (_, i) => i + 1).map(page => (
                    <button
                      key={page}
                      onClick={() => setCurrentPage(page)}
                      className={`w-8 h-8 rounded text-sm font-medium ${
                        page === currentPage ? 'bg-blue-600 text-white' : 'hover:bg-gray-200 text-gray-700'
                      }`}
                    >
                      {page}
                    </button>
                  ))}
                  <button
                    onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                    disabled={currentPage === totalPages}
                    className="p-1 rounded hover:bg-gray-200 disabled:opacity-40"
                  >
                    <ChevronRight className="w-5 h-5" />
                  </button>
                </div>
              </div>
            )}
          </div>
        </>
      ) : (
        <>
          {detailLoading ? (
            <LoadingSpinner />
          ) : !detail ? (
            <div className="text-center py-12 text-gray-500">
              <p>Applicant not found.</p>
              <button onClick={() => setSelectedId(null)} className="mt-4 text-blue-600 hover:underline text-sm">
                Back to list
              </button>
            </div>
          ) : (
            <>
              <div className="flex items-center gap-4">
                <button
                  onClick={() => { setSelectedId(null); setDetail(null); }}
                  className="flex items-center gap-2 text-gray-600 hover:text-gray-900 text-sm font-medium"
                >
                  <ArrowLeft className="w-5 h-5" /> Back to List
                </button>
                <div className="flex-1" />
                <StatusBadge status={detail.workflow_stage || detail.status} />
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                  <div className="bg-white rounded-lg shadow-sm border p-6">
                    <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                      <User className="w-5 h-5 text-blue-500" /> Personal Information
                    </h3>
                    <div className="flex items-start gap-6">
                      {detail.profile_picture ? (
                        <img src={detail.profile_picture} alt="" className="w-20 h-20 rounded-full object-cover" />
                      ) : (
                        <div className="w-20 h-20 bg-blue-900 rounded-full flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
                          {(detail.full_name || detail.applicant_name || '?')[0]}
                        </div>
                      )}
                      <div className="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <p className="text-xs text-gray-500 uppercase">Full Name</p>
                          <p className="text-sm font-medium text-gray-900">{detail.full_name || detail.applicant_name}</p>
                        </div>
                        <div>
                          <p className="text-xs text-gray-500 uppercase">Email</p>
                          <p className="text-sm font-medium text-gray-900 flex items-center gap-1">
                            <Mail className="w-3 h-3" /> {detail.applicant_email}
                          </p>
                        </div>
                        <div>
                          <p className="text-xs text-gray-500 uppercase">Phone</p>
                          <p className="text-sm font-medium text-gray-900 flex items-center gap-1">
                            <Phone className="w-3 h-3" /> {detail.contact_num || '-'}
                          </p>
                        </div>
                        <div>
                          <p className="text-xs text-gray-500 uppercase">Address</p>
                          <p className="text-sm font-medium text-gray-900 flex items-center gap-1">
                            <MapPin className="w-3 h-3" /> {detail.address || '-'}
                          </p>
                        </div>
                        <div>
                          <p className="text-xs text-gray-500 uppercase">Position Applied</p>
                          <p className="text-sm font-medium text-gray-900">{detail.position}</p>
                        </div>
                        <div>
                          <p className="text-xs text-gray-500 uppercase">Department</p>
                          <p className="text-sm font-medium text-gray-900">{detail.assigned_to_department || detail.department || '-'}</p>
                        </div>
                        <div>
                          <p className="text-xs text-gray-500 uppercase">Applied Date</p>
                          <p className="text-sm font-medium text-gray-900">
                            {detail.applied_date ? new Date(detail.applied_date).toLocaleDateString() : '-'}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>

                  {education.length > 0 && (
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                      <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <GraduationCap className="w-5 h-5 text-purple-500" /> Education
                      </h3>
                      <div className="space-y-4">
                        {education.map((edu: any, i: number) => (
                          <div key={edu.id || i} className="border-l-4 border-purple-200 pl-4 py-2">
                            <p className="font-medium text-gray-900">{edu.school_name || edu.institution || edu.school}</p>
                            <p className="text-sm text-gray-600">{edu.degree || edu.course || ''}{edu.year_start ? ` (${edu.year_start} - ${edu.year_end || 'Present'})` : ''}</p>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {experience.length > 0 && (
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                      <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <Briefcase className="w-5 h-5 text-blue-500" /> Work Experience
                      </h3>
                      <div className="space-y-4">
                        {experience.map((exp: any, i: number) => (
                          <div key={exp.id || i} className="border-l-4 border-blue-200 pl-4 py-2">
                            <p className="font-medium text-gray-900">{exp.position || exp.job_title}</p>
                            <p className="text-sm text-gray-600">{exp.company_name || exp.company}</p>
                            <p className="text-xs text-gray-500">
                              {exp.start_date || exp.year_start} - {exp.end_date || exp.year_end || 'Present'}
                            </p>
                            {exp.description && <p className="text-sm text-gray-600 mt-1">{exp.description}</p>}
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {skills && (
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                      <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <Award className="w-5 h-5 text-amber-500" /> Skills
                      </h3>
                      {Array.isArray(skills) ? (
                        <div className="flex flex-wrap gap-2">
                          {skills.map((s: any, i: number) => (
                            <span key={i} className="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                              {typeof s === 'string' ? s : s.skill_name || s.name || JSON.stringify(s)}
                            </span>
                          ))}
                        </div>
                      ) : typeof skills === 'object' ? (
                        Object.entries(skills).map(([category, items]: [string, any]) => (
                          <div key={category} className="mb-3">
                            <p className="text-sm font-medium text-gray-700 mb-1">{category}</p>
                            <div className="flex flex-wrap gap-2">
                              {(Array.isArray(items) ? items : []).map((s: any, i: number) => (
                                <span key={i} className="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                                  {typeof s === 'string' ? s : s.skill_name || s.name || ''}
                                </span>
                              ))}
                            </div>
                          </div>
                        ))
                      ) : (
                        <p className="text-sm text-gray-500">{String(skills)}</p>
                      )}
                    </div>
                  )}

                  <div className="bg-white rounded-lg shadow-sm border p-6">
                    <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                      <FileText className="w-5 h-5 text-green-500" /> Submitted Documents
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                      {DOCUMENT_FIELDS.map(doc => {
                        const val = detail[doc.key];
                        return (
                          <div key={doc.key} className="border rounded-lg p-3">
                            <div className="flex items-center gap-2 mb-1">
                              <doc.icon className="w-4 h-4 text-gray-500" />
                              <span className="text-xs font-medium text-gray-700">{doc.label}</span>
                            </div>
                            {val ? (
                              <a
                                href={val}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-sm text-blue-600 hover:underline flex items-center gap-1"
                              >
                                <ExternalLink className="w-3 h-3" /> View Document
                              </a>
                            ) : (
                              <span className="text-xs text-gray-400">Not uploaded</span>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  </div>
                </div>

                <div className="space-y-6">
                  <div className="bg-white rounded-lg shadow-sm border p-6">
                    <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                      <ClipboardCheck className="w-5 h-5 text-gray-500" /> Actions
                    </h3>
                    <div className="space-y-3">
                      {role === 'secretary' && stage === 'secretary_review' && (
                        <ActionButton
                          label="Transfer to Dept Head"
                          icon={Send}
                          color="bg-green-600 hover:bg-green-700"
                          onClick={() => openModal('transferToDean')}
                        />
                      )}
                      {stage === 'department_head_review' && (
                        <ActionButton
                          label="Schedule Interview"
                          icon={Calendar}
                          color="bg-blue-600 hover:bg-blue-700"
                          onClick={() => openModal('scheduleInterview')}
                        />
                      )}
                      {(stage === 'interview_completed' || stage === 'interview_scheduled') && (
                        <ActionButton
                          label="Approve Interview"
                          icon={CheckCircle}
                          color="bg-teal-600 hover:bg-teal-700"
                          onClick={() => openModal('approveInterview')}
                        />
                      )}
                      {stage === 'interview_scheduled' && (
                        <ActionButton
                          label="Reschedule Interview"
                          icon={RotateCcw}
                          color="bg-amber-500 hover:bg-amber-600"
                          onClick={() => openModal('rescheduleInterview')}
                        />
                      )}
                      {stage === 'interview_completed' && (
                        <ActionButton
                          label="Schedule Demo Teaching"
                          icon={Presentation}
                          color="bg-indigo-600 hover:bg-indigo-700"
                          onClick={() => openModal('scheduleDemo')}
                        />
                      )}
                      {(stage === 'demo_completed' || stage === 'demo_scheduled') && (
                        <ActionButton
                          label="Approve Demo"
                          icon={CheckCircle}
                          color="bg-emerald-600 hover:bg-emerald-700"
                          onClick={() => openModal('approveDemo')}
                        />
                      )}
                      {stage === 'demo_scheduled' && (
                        <ActionButton
                          label="Reschedule Demo"
                          icon={RotateCcw}
                          color="bg-amber-500 hover:bg-amber-600"
                          onClick={() => openModal('rescheduleDemo')}
                        />
                      )}
                      {stage === 'demo_completed' && (
                        <ActionButton
                          label="Hire Applicant"
                          icon={UserCheck}
                          color="bg-green-600 hover:bg-green-700"
                          onClick={() => openModal('hire')}
                        />
                      )}
                      <ActionButton
                        label="Request Resubmission"
                        icon={RotateCcw}
                        color="bg-orange-500 hover:bg-orange-600"
                        onClick={() => openModal('resubmission')}
                      />
                      <ActionButton
                        label="Reject Application"
                        icon={X}
                        color="bg-red-600 hover:bg-red-700"
                        onClick={() => openModal('reject')}
                      />
                    </div>
                  </div>

                  {detail.interview_date && (
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                      <h3 className="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <Calendar className="w-5 h-5 text-blue-500" /> Interview Details
                      </h3>
                      <div className="space-y-2 text-sm">
                        <div className="flex justify-between">
                          <span className="text-gray-500">Date</span>
                          <span className="font-medium text-gray-900">{new Date(detail.interview_date).toLocaleDateString()}</span>
                        </div>
                        {detail.interview_time && (
                          <div className="flex justify-between">
                            <span className="text-gray-500">Time</span>
                            <span className="font-medium text-gray-900">{detail.interview_time}</span>
                          </div>
                        )}
                        {detail.interview_location && (
                          <div className="flex justify-between">
                            <span className="text-gray-500">Location</span>
                            <span className="font-medium text-gray-900">{detail.interview_location}</span>
                          </div>
                        )}
                        {detail.interview_room && (
                          <div className="flex justify-between">
                            <span className="text-gray-500">Room</span>
                            <span className="font-medium text-gray-900">{detail.interview_room}</span>
                          </div>
                        )}
                        {detail.interview_notes && (
                          <div className="pt-2 border-t">
                            <p className="text-gray-500 mb-1">Notes</p>
                            <p className="text-gray-700">{detail.interview_notes}</p>
                          </div>
                        )}
                      </div>
                    </div>
                  )}

                  {detail.demo_date && (
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                      <h3 className="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <Presentation className="w-5 h-5 text-indigo-500" /> Demo Teaching Details
                      </h3>
                      <div className="space-y-2 text-sm">
                        <div className="flex justify-between">
                          <span className="text-gray-500">Date</span>
                          <span className="font-medium text-gray-900">{new Date(detail.demo_date).toLocaleDateString()}</span>
                        </div>
                        {detail.demo_time && (
                          <div className="flex justify-between">
                            <span className="text-gray-500">Time</span>
                            <span className="font-medium text-gray-900">{detail.demo_time}</span>
                          </div>
                        )}
                        {detail.demo_location && (
                          <div className="flex justify-between">
                            <span className="text-gray-500">Location</span>
                            <span className="font-medium text-gray-900">{detail.demo_location}</span>
                          </div>
                        )}
                        {detail.demo_room && (
                          <div className="flex justify-between">
                            <span className="text-gray-500">Room</span>
                            <span className="font-medium text-gray-900">{detail.demo_room}</span>
                          </div>
                        )}
                        {detail.demo_notes && (
                          <div className="pt-2 border-t">
                            <p className="text-gray-500 mb-1">Notes</p>
                            <p className="text-gray-700">{detail.demo_notes}</p>
                          </div>
                        )}
                      </div>
                    </div>
                  )}

                  {detail.rejection_reason && (
                    <div className="bg-red-50 rounded-lg border border-red-200 p-6">
                      <h3 className="text-lg font-semibold text-red-900 mb-2 flex items-center gap-2">
                        <AlertTriangle className="w-5 h-5" /> Rejection Info
                      </h3>
                      <p className="text-sm text-red-700">{detail.rejection_reason}</p>
                      {detail.rejected_date && (
                        <p className="text-xs text-red-500 mt-1">
                          Rejected on {new Date(detail.rejected_date).toLocaleDateString()}
                        </p>
                      )}
                    </div>
                  )}
                </div>
              </div>
            </>
          )}
        </>
      )}

      {/* Schedule Interview Modal */}
      <Modal open={modals.scheduleInterview} onClose={closeAllModals} title="Schedule Interview">
        <div className="space-y-4">
          <FormField label="Date">
            <input type="date" value={interviewForm.date} onChange={e => setInterviewForm(f => ({ ...f, date: e.target.value }))} className={inputCls} />
          </FormField>
          <FormField label="Time">
            <input type="time" value={interviewForm.time} onChange={e => setInterviewForm(f => ({ ...f, time: e.target.value }))} className={inputCls} />
          </FormField>
          <FormField label="Location">
            <input type="text" value="Norzagaray College" readOnly className={`${inputCls} bg-gray-100`} />
          </FormField>
          <FormField label="Room">
            <input type="text" value={interviewForm.room} onChange={e => setInterviewForm(f => ({ ...f, room: e.target.value }))} className={inputCls} placeholder="e.g. Room 301" />
          </FormField>
          <FormField label="Notes">
            <textarea rows={3} value={interviewForm.notes} onChange={e => setInterviewForm(f => ({ ...f, notes: e.target.value }))} className={textareaCls} placeholder="Optional notes..." />
          </FormField>
          <div className="flex justify-end gap-3 pt-2">
            <button onClick={closeAllModals} className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button onClick={submitScheduleInterview} disabled={actionLoading || !interviewForm.date} className="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
              {actionLoading ? 'Scheduling...' : 'Schedule'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Approve Interview Modal */}
      <Modal open={modals.approveInterview} onClose={closeAllModals} title="Approve Interview">
        <div className="space-y-4">
          <FormField label="Evaluation Notes">
            <textarea rows={4} value={approveInterviewForm.notes} onChange={e => setApproveInterviewForm({ notes: e.target.value })} className={textareaCls} placeholder="Enter evaluation notes..." />
          </FormField>
          <div className="flex justify-end gap-3 pt-2">
            <button onClick={closeAllModals} className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button onClick={submitApproveInterview} disabled={actionLoading} className="px-4 py-2 text-sm text-white bg-teal-600 rounded-lg hover:bg-teal-700 disabled:opacity-50">
              {actionLoading ? 'Approving...' : 'Approve'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Reschedule Interview Modal */}
      <Modal open={modals.rescheduleInterview} onClose={closeAllModals} title="Reschedule Interview">
        <div className="space-y-4">
          <FormField label="New Date">
            <input type="date" value={reschedInterviewForm.date} onChange={e => setReschedInterviewForm(f => ({ ...f, date: e.target.value }))} className={inputCls} />
          </FormField>
          <FormField label="New Time">
            <input type="time" value={reschedInterviewForm.time} onChange={e => setReschedInterviewForm(f => ({ ...f, time: e.target.value }))} className={inputCls} />
          </FormField>
          <FormField label="Location">
            <input type="text" value="Norzagaray College" readOnly className={`${inputCls} bg-gray-100`} />
          </FormField>
          <FormField label="Room">
            <input type="text" value={reschedInterviewForm.room} onChange={e => setReschedInterviewForm(f => ({ ...f, room: e.target.value }))} className={inputCls} placeholder="e.g. Room 301" />
          </FormField>
          <FormField label="Reason for Rescheduling">
            <textarea rows={3} value={reschedInterviewForm.reason} onChange={e => setReschedInterviewForm(f => ({ ...f, reason: e.target.value }))} className={textareaCls} placeholder="Enter reason..." />
          </FormField>
          <div className="flex justify-end gap-3 pt-2">
            <button onClick={closeAllModals} className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button onClick={submitRescheduleInterview} disabled={actionLoading || !reschedInterviewForm.date} className="px-4 py-2 text-sm text-white bg-amber-500 rounded-lg hover:bg-amber-600 disabled:opacity-50">
              {actionLoading ? 'Rescheduling...' : 'Reschedule'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Schedule Demo Modal */}
      <Modal open={modals.scheduleDemo} onClose={closeAllModals} title="Schedule Demo Teaching">
        <div className="space-y-4">
          <FormField label="Date">
            <input type="date" value={demoForm.date} onChange={e => setDemoForm(f => ({ ...f, date: e.target.value }))} className={inputCls} />
          </FormField>
          <FormField label="Time">
            <input type="time" value={demoForm.time} onChange={e => setDemoForm(f => ({ ...f, time: e.target.value }))} className={inputCls} />
          </FormField>
          <FormField label="Location">
            <input type="text" value="Norzagaray College" readOnly className={`${inputCls} bg-gray-100`} />
          </FormField>
          <FormField label="Room">
            <input type="text" value={demoForm.room} onChange={e => setDemoForm(f => ({ ...f, room: e.target.value }))} className={inputCls} placeholder="e.g. Room 301" />
          </FormField>
          <FormField label="Notes">
            <textarea rows={3} value={demoForm.notes} onChange={e => setDemoForm(f => ({ ...f, notes: e.target.value }))} className={textareaCls} placeholder="Optional notes..." />
          </FormField>
          <div className="flex justify-end gap-3 pt-2">
            <button onClick={closeAllModals} className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button onClick={submitScheduleDemo} disabled={actionLoading || !demoForm.date} className="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {actionLoading ? 'Scheduling...' : 'Schedule'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Approve Demo Modal */}
      <Modal open={modals.approveDemo} onClose={closeAllModals} title="Approve Demo Teaching">
        <div className="space-y-4">
          <FormField label="Evaluation Notes">
            <textarea rows={4} value={approveDemoForm.notes} onChange={e => setApproveDemoForm({ notes: e.target.value })} className={textareaCls} placeholder="Enter evaluation notes..." />
          </FormField>
          <div className="flex justify-end gap-3 pt-2">
            <button onClick={closeAllModals} className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button onClick={submitApproveDemo} disabled={actionLoading} className="px-4 py-2 text-sm text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50">
              {actionLoading ? 'Approving...' : 'Approve'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Reschedule Demo Modal */}
      <Modal open={modals.rescheduleDemo} onClose={closeAllModals} title="Reschedule Demo Teaching">
        <div className="space-y-4">
          <FormField label="New Date">
            <input type="date" value={reschedDemoForm.date} onChange={e => setReschedDemoForm(f => ({ ...f, date: e.target.value }))} className={inputCls} />
          </FormField>
          <FormField label="New Time">
            <input type="time" value={reschedDemoForm.time} onChange={e => setReschedDemoForm(f => ({ ...f, time: e.target.value }))} className={inputCls} />
          </FormField>
          <FormField label="Location">
            <input type="text" value="Norzagaray College" readOnly className={`${inputCls} bg-gray-100`} />
          </FormField>
          <FormField label="Room">
            <input type="text" value={reschedDemoForm.room} onChange={e => setReschedDemoForm(f => ({ ...f, room: e.target.value }))} className={inputCls} placeholder="e.g. Room 301" />
          </FormField>
          <FormField label="Reason for Rescheduling">
            <textarea rows={3} value={reschedDemoForm.reason} onChange={e => setReschedDemoForm(f => ({ ...f, reason: e.target.value }))} className={textareaCls} placeholder="Enter reason..." />
          </FormField>
          <div className="flex justify-end gap-3 pt-2">
            <button onClick={closeAllModals} className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button onClick={submitRescheduleDemo} disabled={actionLoading || !reschedDemoForm.date} className="px-4 py-2 text-sm text-white bg-amber-500 rounded-lg hover:bg-amber-600 disabled:opacity-50">
              {actionLoading ? 'Rescheduling...' : 'Reschedule'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Hire Modal */}
      <Modal open={modals.hire} onClose={closeAllModals} title="Hire Applicant">
        <div className="space-y-4">
          <div className="bg-green-50 border border-green-200 rounded-lg p-4">
            <p className="text-sm text-green-800">
              Are you sure you want to hire <strong>{detail?.full_name || detail?.applicant_name}</strong> for the position of <strong>{detail?.position}</strong>?
            </p>
          </div>
          <FormField label="Notes (optional)">
            <textarea rows={3} value={hireForm.notes} onChange={e => setHireForm({ notes: e.target.value })} className={textareaCls} placeholder="Optional notes..." />
          </FormField>
          <div className="flex justify-end gap-3 pt-2">
            <button onClick={closeAllModals} className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button onClick={submitHire} disabled={actionLoading} className="px-4 py-2 text-sm text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">
              {actionLoading ? 'Processing...' : 'Confirm Hire'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Transfer to Dept Head Modal */}
      <Modal open={modals.transferToDean} onClose={closeAllModals} title="Transfer to Department Head">
        <div className="space-y-4">
          <FormField label="Notes (optional)">
            <textarea rows={3} value={transferForm.notes} onChange={e => setTransferForm({ notes: e.target.value })} className={textareaCls} placeholder="Optional notes for the department head..." />
          </FormField>
          <div className="flex justify-end gap-3 pt-2">
            <button onClick={closeAllModals} className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button onClick={submitTransfer} disabled={actionLoading} className="px-4 py-2 text-sm text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">
              {actionLoading ? 'Transferring...' : 'Transfer'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Request Resubmission Modal */}
      <Modal open={modals.resubmission} onClose={closeAllModals} title="Request Document Resubmission" maxWidth="max-w-xl">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">Select the documents you want the applicant to resubmit:</p>
          <div className="grid grid-cols-2 gap-2">
            {DOCUMENT_FIELDS.map(doc => (
              <label key={doc.key} className="flex items-center gap-2 p-2 rounded hover:bg-gray-50 cursor-pointer">
                <input
                  type="checkbox"
                  checked={resubmitDocs[doc.key] || false}
                  onChange={e => setResubmitDocs(prev => ({ ...prev, [doc.key]: e.target.checked }))}
                  className="w-4 h-4 text-orange-500 rounded"
                />
                <span className="text-sm text-gray-700">{doc.label}</span>
              </label>
            ))}
          </div>
          <FormField label="Reason">
            <textarea rows={3} value={resubmitReason} onChange={e => setResubmitReason(e.target.value)} className={textareaCls} placeholder="Explain why resubmission is needed..." />
          </FormField>
          <div className="flex justify-end gap-3 pt-2">
            <button onClick={closeAllModals} className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button
              onClick={submitResubmission}
              disabled={actionLoading || !resubmitReason || !Object.values(resubmitDocs).some(Boolean)}
              className="px-4 py-2 text-sm text-white bg-orange-500 rounded-lg hover:bg-orange-600 disabled:opacity-50"
            >
              {actionLoading ? 'Sending...' : 'Send Request'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Reject Modal */}
      <Modal open={modals.reject} onClose={closeAllModals} title="Reject Application">
        <div className="space-y-4">
          <div className="bg-red-50 border border-red-200 rounded-lg p-4">
            <p className="text-sm text-red-800">
              Are you sure you want to reject the application of <strong>{detail?.full_name || detail?.applicant_name}</strong>?
            </p>
          </div>
          <FormField label="Reason for Rejection">
            <textarea rows={4} value={rejectReason} onChange={e => setRejectReason(e.target.value)} className={textareaCls} placeholder="Enter the reason for rejection..." />
          </FormField>
          <div className="flex justify-end gap-3 pt-2">
            <button onClick={closeAllModals} className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button onClick={submitReject} disabled={actionLoading || !rejectReason} className="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
              {actionLoading ? 'Rejecting...' : 'Reject'}
            </button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
