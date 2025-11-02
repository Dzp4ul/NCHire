# NCHire - Norzagaray College Hiring System

## System Overview

NCHire is a comprehensive web-based hiring and recruitment system designed for Norzagaray College. The system streamlines the entire hiring process from job posting to applicant management.

## Features

### For Applicants:
- User registration with email verification
- Browse and search available job postings
- Online application submission with document uploads
- Application status tracking
- Real-time notifications
- Profile management

### For Administrators:
- Dashboard with analytics
- Job posting management
- Applicant review and management
- Interview scheduling
- Application workflow management
- Email notifications
- Department-based access control

## Technology Stack

- **Frontend:** HTML5, TailwindCSS, JavaScript
- **Backend:** PHP 7.4+
- **Database:** MySQL/MariaDB
- **Email:** PHPMailer
- **Icons:** RemixIcon

## Installation

1. Install XAMPP or similar PHP/MySQL environment
2. Clone/copy files to `htdocs/FinalResearch - Copy/`
3. Import database (if applicable)
4. Configure database credentials in PHP files
5. Access via `http://localhost/FinalResearch - Copy/`

## Database Configuration

Default credentials (change in production):
- Host: 127.0.0.1
- User: root
- Password: 12345678
- Database: nchire

## File Structure

```
/
├── index.php (redirects to public/)
├── config/ (configuration files)
├── public/ (landing page, login, signup)
├── user/ (applicant dashboard)
├── admin/ (admin dashboard)
└── uploads/ (uploaded files)
```

## Default Login

### Admin:
- Email: admin@norzagaraycollege.edu.ph
- Password: admin123

## Support

For issues or questions, contact the system administrator.

## License

Proprietary - Norzagaray College
