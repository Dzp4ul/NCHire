import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import LoadingSpinner from './components/shared/LoadingSpinner';

// Public pages
import LandingPage from './pages/public/LandingPage';
import LoginPage from './pages/public/LoginPage';
import SignupPage from './pages/public/SignupPage';

// Applicant pages
import ApplicantLayout from './pages/applicant/ApplicantLayout';
import JobBoard from './pages/applicant/JobBoard';
import JobDetail from './pages/applicant/JobDetail';
import MyApplications from './pages/applicant/MyApplications';
import ProfilePage from './pages/applicant/ProfilePage';

// App (admin) pages
import AppLayout from './pages/admin/AdminLayout';
import AppDashboard from './pages/admin/Dashboard';
import JobPostings from './pages/admin/JobPostings';
import Applicants from './pages/admin/Applicants';
import Users from './pages/admin/Users';
import Archive from './pages/admin/Archive';

function ProtectedRoute({ children, requiredType }: { children: React.ReactNode; requiredType?: 'admin' | 'applicant' }) {
  const { user, userType, loading } = useAuth();
  if (loading) return <LoadingSpinner />;
  if (!user) return <Navigate to="/login" replace />;
  if (requiredType && userType !== requiredType) {
    return <Navigate to={userType === 'admin' ? '/app' : '/applicant/jobs'} replace />;
  }
  return <>{children}</>;
}

function PublicRoute({ children }: { children: React.ReactNode }) {
  const { user, userType, loading } = useAuth();
  if (loading) return <LoadingSpinner />;
  if (user) {
    return <Navigate to={userType === 'admin' ? '/app' : '/applicant/jobs'} replace />;
  }
  return <>{children}</>;
}

function AppRoutes() {
  return (
    <Routes>
      {/* Public */}
      <Route path="/" element={<LandingPage />} />
      <Route path="/login" element={<PublicRoute><LoginPage /></PublicRoute>} />
      <Route path="/signup" element={<PublicRoute><SignupPage /></PublicRoute>} />

      {/* Applicant */}
      <Route path="/applicant" element={<ProtectedRoute requiredType="applicant"><ApplicantLayout /></ProtectedRoute>}>
        <Route index element={<Navigate to="jobs" replace />} />
        <Route path="jobs" element={<JobBoard />} />
        <Route path="jobs/:id" element={<JobDetail />} />
        <Route path="applications" element={<MyApplications />} />
        <Route path="profile" element={<ProfilePage />} />
      </Route>

      {/* App (admin roles) */}
      <Route path="/app" element={<ProtectedRoute requiredType="admin"><AppLayout /></ProtectedRoute>}>
        <Route index element={<AppDashboard />} />
        <Route path="jobs" element={<JobPostings />} />
        <Route path="applicants" element={<Applicants />} />
        <Route path="archive" element={<Archive />} />
        <Route path="users" element={<Users />} />
      </Route>

      {/* Fallback */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </BrowserRouter>
  );
}
