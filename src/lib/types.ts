export interface User {
  id: number;
  name: string;
  email: string;
  role?: string;
  department?: string;
  first_name?: string;
  last_name?: string;
  profile_picture?: string;
}

export interface AuthState {
  user: User | null;
  userType: 'admin' | 'applicant' | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<{ success: boolean; message?: string }>;
  register: (data: { first_name: string; last_name: string; email: string; password: string }) => Promise<{ success: boolean; message?: string }>;
  logout: () => Promise<void>;
}

export interface Job {
  id: number;
  job_title: string;
  department_role: string;
  job_type: string;
  locations: string;
  salary_range: string;
  application_deadline: string;
  job_description: string;
  job_requirements: string;
  education: string;
  experience: string;
  training: string;
  eligibility: string;
  duties: string;
  competency: string;
  subject?: string;
  status?: string;
  application_count?: number;
}

export interface Applicant {
  id: number;
  full_name: string;
  applicant_email: string;
  contact_num: string;
  position: string;
  applied_date: string;
  status: string;
  workflow_stage: string;
  assigned_to_department?: string;
  secretary_id?: number;
  profile_picture?: string;
}

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  role: string;
  department: string;
  status: string;
  lastLogin: string;
  createdDate: string;
  profile_picture?: string;
  phone?: string;
}

export interface DashboardStats {
  secretary_pending: number;
  dept_pending: number;
  interviews_this_week: number;
  overall_hired: number;
  total_applicants: number;
  interview_scheduled: number;
  demo_scheduled: number;
  hired: number;
}

export interface DashboardData {
  success: boolean;
  stats: DashboardStats;
  recent_applicants: Applicant[];
  recent_jobs: Job[];
  recent_activity: ActivityLog[];
  timestamp: string;
}

export interface ActivityLog {
  activity_type: string;
  description: string;
  user_name: string;
  created_at: string;
}

export interface JobApplicantDetail {
  id: number;
  applicant_name: string;
  full_name: string;
  applicant_email: string;
  contact_num: string;
  position: string;
  applied_date: string;
  status: string;
  workflow_stage: string;
  user_id: number;
  job_id: number;
  assigned_to_department: string;
  application_letter: string;
  resume: string;
  tor: string;
  diploma: string;
  professional_license: string;
  coe: string;
  interview_date: string;
  interview_notes: string;
  demo_date: string;
  demo_notes: string;
  psych_exam_date: string;
  psych_exam_notes: string;
  initially_hired_date: string;
  initially_hired_notes: string;
  rejection_reason: string;
  rejected_date: string;
}
