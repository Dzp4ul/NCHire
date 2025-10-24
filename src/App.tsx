import  { useState } from 'react';
import AdminLayout from './components/AdminLayout';
import Dashboard from './components/admin/Dashboard';
import JobPostings from './components/admin/JobPostings';
import Applicants from './components/admin/Applicants';
import Users from './components/admin/Users';

function App() {
  const [activeTab, setActiveTab] = useState('dashboard');

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard':
        return <Dashboard />;
      case 'jobs':
        return <JobPostings />;
      case 'applicants':
        return <Applicants />;
      case 'users':
        return <Users />;
      default:
        return <Dashboard />;
    }
  };

  return (
    <AdminLayout activeTab={activeTab} onTabChange={setActiveTab}>
      {renderContent()}
    </AdminLayout>
  );
}

export default App;