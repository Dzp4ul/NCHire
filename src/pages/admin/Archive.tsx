import React, { useState, useEffect, useCallback } from 'react';
import { api } from '../../lib/api';
import StatusBadge from '../../components/shared/StatusBadge';
import LoadingSpinner from '../../components/shared/LoadingSpinner';
import {
  Archive as ArchiveIcon, Search, Eye, ArrowLeft,
  User, Mail, Calendar, AlertTriangle, X, FileText,
  GraduationCap, Briefcase, Award, MapPin, Phone, ExternalLink,
  Star, ClipboardCheck,
} from 'lucide-react';

interface ArchivedApplicant {
  id: number;
  full_name: string;
  applicant_email: string;
  position: string;
  applied_date: string;
  rejected_date: string;
  rejection_reason: string;
  status: string;
  workflow_stage: string;
  assigned_to_department: string;
  profile_picture?: string;
  [key: string]: any;
}

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

export default function Archive() {
  const [archived, setArchived] = useState<ArchivedApplicant[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filterStatus, setFilterStatus] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<ApplicantDetailData | null>(null);
  const [education, setEducation] = useState<any[]>([]);
  const [experience, setExperience] = useState<any[]>([]);
  const [skills, setSkills] = useState<any>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  useEffect(() => {
    api.getArchive()
      .then((data: any) => setArchived(Array.isArray(data) ? data : []))
      .catch(() => {})
      .finally(() => setLoading(false));
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

  useEffect(() => {
    if (selectedId !== null) fetchDetail(selectedId);
  }, [selectedId, fetchDetail]);

  const filtered = archived.filter((a) => {
    const matchSearch =
      (a.full_name || '').toLowerCase().includes(search.toLowerCase()) ||
      (a.applicant_email || '').toLowerCase().includes(search.toLowerCase());
    const matchStatus =
      filterStatus === 'all' ||
      (filterStatus === 'Rejected' && (a.status === 'Rejected' || a.workflow_stage === 'rejected')) ||
      (filterStatus === 'Cancelled' && (a.status === 'Cancelled' || a.workflow_stage === 'cancelled'));
    return matchSearch && matchStatus;
  });

  const totalPages = Math.ceil(filtered.length / ITEMS_PER_PAGE);
  const paginated = filtered.slice(
    (currentPage - 1) * ITEMS_PER_PAGE,
    currentPage * ITEMS_PER_PAGE,
  );

  const goBack = () => {
    setSelectedId(null);
    setDetail(null);
    setEducation([]);
    setExperience([]);
    setSkills(null);
  };

  if (loading) return <LoadingSpinner />;

  return (
    <div className="space-y-6">
      {selectedId === null ? (
        <>
          <div className="flex items-center gap-3">
            <ArchiveIcon className="w-7 h-7 text-blue-900" />
            <h1 className="text-3xl font-bold text-gray-900">Archive</h1>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-1 gap-6">
            <div className="bg-white p-6 rounded-lg shadow-sm border">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">Total Archived Applicants</p>
                  <p className="text-2xl font-bold text-gray-900">{archived.length}</p>
                </div>
                <div className="bg-gray-50 p-3 rounded-full">
                  <ArchiveIcon className="w-6 h-6 text-gray-500" />
                </div>
              </div>
            </div>
          </div>

          <div className="bg-white p-4 rounded-lg shadow-sm border flex flex-col lg:flex-row gap-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                type="text"
                placeholder="Search by name or email..."
                value={search}
                onChange={(e) => { setSearch(e.target.value); setCurrentPage(1); }}
                className="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
              />
            </div>
            <select
              value={filterStatus}
              onChange={(e) => { setFilterStatus(e.target.value); setCurrentPage(1); }}
              className="border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            >
              <option value="all">All Status</option>
              <option value="Rejected">Rejected Only</option>
              <option value="Cancelled">Cancelled Only</option>
            </select>
          </div>

          <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applied Date</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Archive Date</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {paginated.map((item) => (
                    <tr key={item.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          {item.profile_picture ? (
                            <img src={item.profile_picture} alt="" className="w-10 h-10 rounded-full object-cover" />
                          ) : (
                            <div className="w-10 h-10 bg-blue-900 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                              {(item.full_name || '?')[0]}
                            </div>
                          )}
                          <div>
                            <div className="font-medium text-gray-900">{item.full_name}</div>
                            <div className="text-sm text-gray-500">{item.applicant_email}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-700">{item.position}</td>
                      <td className="px-6 py-4">
                        <StatusBadge status={item.workflow_stage || item.status} />
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-700">
                        {item.applied_date ? new Date(item.applied_date).toLocaleDateString() : '-'}
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-700">
                        {item.rejected_date ? new Date(item.rejected_date).toLocaleDateString() : '-'}
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-700 max-w-xs truncate">
                        {item.rejection_reason || '-'}
                      </td>
                      <td className="px-6 py-4">
                        <button
                          onClick={() => setSelectedId(item.id)}
                          className="text-blue-600 hover:text-blue-800 flex items-center gap-1 text-sm font-medium"
                        >
                          <Eye className="w-4 h-4" /> View
                        </button>
                      </td>
                    </tr>
                  ))}
                  {paginated.length === 0 && (
                    <tr>
                      <td colSpan={7} className="px-6 py-12 text-center text-gray-400">
                        <User className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                        <p className="text-sm">No archived applicants found</p>
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
                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                    disabled={currentPage === 1}
                    className="px-3 py-1 text-sm rounded border hover:bg-gray-100 disabled:opacity-40"
                  >
                    Prev
                  </button>
                  <span className="text-sm text-gray-600">
                    Page {currentPage} of {totalPages}
                  </span>
                  <button
                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                    disabled={currentPage === totalPages}
                    className="px-3 py-1 text-sm rounded border hover:bg-gray-100 disabled:opacity-40"
                  >
                    Next
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
              <button onClick={goBack} className="mt-4 text-blue-600 hover:underline text-sm">
                Back to list
              </button>
            </div>
          ) : (
            <>
              <div className="flex items-center gap-4">
                <button
                  onClick={goBack}
                  className="flex items-center gap-2 text-gray-600 hover:text-gray-900 text-sm font-medium"
                >
                  <ArrowLeft className="w-5 h-5" /> Back to Archive
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
                      {DOCUMENT_FIELDS.map((doc) => {
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

                  <div className="bg-white rounded-lg shadow-sm border p-6">
                    <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                      <ClipboardCheck className="w-5 h-5 text-gray-500" /> Summary
                    </h3>
                    <div className="space-y-3 text-sm">
                      <div className="flex justify-between">
                        <span className="text-gray-500">Position</span>
                        <span className="font-medium text-gray-900">{detail.position}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-500">Department</span>
                        <span className="font-medium text-gray-900">{detail.assigned_to_department || '-'}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-500">Applied</span>
                        <span className="font-medium text-gray-900">
                          {detail.applied_date ? new Date(detail.applied_date).toLocaleDateString() : '-'}
                        </span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-gray-500">Archived</span>
                        <span className="font-medium text-gray-900">
                          {detail.rejected_date ? new Date(detail.rejected_date).toLocaleDateString() : '-'}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}
        </>
      )}
    </div>
  );
}
