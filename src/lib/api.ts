const API_BASE = '';

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const url = `${API_BASE}${path}`;
  const res = await fetch(url, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
    ...options,
  });
  if (!res.ok) {
    const text = await res.text().catch(() => '');
    throw new Error(text || `HTTP ${res.status}`);
  }
  return res.json();
}

async function requestForm<T>(path: string, body: URLSearchParams): Promise<T> {
  const url = `${API_BASE}${path}`;
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  });
  if (!res.ok) {
    const text = await res.text().catch(() => '');
    throw new Error(text || `HTTP ${res.status}`);
  }
  return res.json();
}

async function requestMultipart<T>(path: string, formData: FormData): Promise<T> {
  const url = `${API_BASE}${path}`;
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  });
  if (!res.ok) {
    const text = await res.text().catch(() => '');
    throw new Error(text || `HTTP ${res.status}`);
  }
  return res.json();
}

export const api = {
  // Auth
  login: (email: string, password: string) =>
    request<{ success: boolean; message?: string; user?: any; user_type?: string }>('/api/login.php', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),

  register: (data: { first_name: string; last_name: string; email: string; password: string }) =>
    request<{ success: boolean; message?: string; user?: any; user_type?: string }>('/api/register.php', {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  me: () =>
    request<{ success: boolean; user?: any; user_type?: string }>('/api/me.php'),

  logout: () =>
    request<{ success: boolean }>('/api/logout.php', { method: 'POST' }),

  refreshSession: () =>
    request<any>('/admin/refresh_session.php'),

  // Dashboard
  dashboard: () =>
    request<any>('/admin/dashboard_api.php'),

  getDashboardStats: () =>
    request<any>('/admin/api/dashboard_stats.php'),

  getApplicantsStats: () =>
    request<any>('/admin/api/get_applicants_stats.php'),

  getStats: () =>
    request<any>('/admin/api/get_stats.php'),

  getChart: (params?: string) =>
    request<any>(`/admin/get_chart_data.php${params ? '?' + params : ''}`),

  getFilteredStats: (params?: string) =>
    request<any>(`/admin/get_filtered_stats.php${params ? '?' + params : ''}`),

  // Jobs
  getJobs: () =>
    request<any[]>('/admin/gets_job.php'),

  getJob: (id: number) =>
    request<any>(`/admin/gets_job.php?id=${id}`),

  addJob: (data: any) =>
    request<{ success: boolean; message?: string; job_id?: number }>('/admin/add_job.php', {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  updateJob: (data: any) =>
    request<{ success: boolean; message?: string }>('/admin/update_job.php', {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  deleteJob: (id: number) =>
    request<{ success: boolean; message?: string }>(`/admin/delete_job.php?id=${id}`, {
      method: 'DELETE',
    }),

  toggleJobStatus: (jobId: number, status: string) =>
    requestForm<{ success: boolean; message?: string; job?: any }>('/admin/toggle_job_status.php',
      new URLSearchParams({ job_id: String(jobId), status })
    ),

  // Applicants
  getApplicants: () =>
    request<any[]>('/admin/gets_applicants.php'),

  getApplicantDetail: (id: number) =>
    request<{ success: boolean; applicant?: any; education?: any[]; experience?: any[]; skills?: any }>(`/admin/view_applicant.php?id=${id}`),

  processApplicantAction: (data: any) =>
    request<{ success: boolean; message?: string }>('/admin/process_applicant_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(data).toString(),
    }),

  secretaryAction: (data: any) =>
    request<{ success: boolean; message?: string }>('/admin/api/secretary_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(data).toString(),
    }),

  getArchive: (params?: string) =>
    request<any[]>(`/admin/get_archive.php${params ? '?' + params : ''}`),

  // Users
  getUsers: () =>
    request<any[]>('/admin/api/users.php'),

  createUser: (data: any) =>
    request<{ success: boolean; message?: string; user?: any }>('/admin/api/users.php', {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  updateUser: (id: number, data: any) =>
    request<{ success: boolean; message?: string }>('/admin/api/users.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, ...data }),
    }),

  deleteUser: (id: number) =>
    request<{ success: boolean; message?: string }>(`/admin/api/users.php?id=${id}`, {
      method: 'DELETE',
    }),

  // Notifications
  getNotifications: (limit = 20, unreadOnly = false) =>
    request<{ success: boolean; notifications?: any[]; unread_count?: number }>(
      `/admin/api/admin_notifications.php?limit=${limit}&unread_only=${unreadOnly}`
    ),

  markNotificationRead: (notificationId: number) =>
    request<{ success: boolean; message?: string }>('/admin/api/admin_notifications.php', {
      method: 'POST',
      body: JSON.stringify({ notification_id: notificationId }),
    }),

  markAllNotificationsRead: () =>
    request<{ success: boolean; message?: string }>('/admin/api/admin_notifications.php', {
      method: 'PUT',
    }),

  // Profile
  updateProfilePicture: (userId: number, file: File) => {
    const fd = new FormData();
    fd.append('user_id', String(userId));
    fd.append('profile_picture', file);
    return requestMultipart<{ success: boolean; message?: string; filename?: string }>('/admin/api/update_profile_picture.php', fd);
  },

  // Public / Applicant APIs
  getHomepageJobs: () =>
    request<any[]>('/api/get_homepage_jobs.php'),

  checkBanStatus: () =>
    request<any>('/user/check_ban_status.php'),

  saveDraft: (data: any) =>
    request<{ success: boolean; message?: string }>('/user/save_draft.php', {
      method: 'POST',
      body: JSON.stringify(data),
    }),
};

export default api;
