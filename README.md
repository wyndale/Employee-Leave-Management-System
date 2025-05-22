# Employee Leave Management System with Data Reporting and Integration

## Table of Contents
- [Project Overview](#project-overview)
- [Objectives](#objectives)
- [Key Features](#key-features)
- [System Architecture](#system-architecture)
  - [Data Model](#data-model)
  - [Workflow](#workflow)
- [Tools and Technologies](#tools-and-technologies)
- [Installation and Setup](#installation-and-setup)
- [Code Structure](#code-structure)
  - [Key Files and Their Functionalities](#key-files-and-their-functionalities)
- [Usage](#usage)
- [Testing](#testing)
- [Future Enhancements](#future-enhancements)
- [Notes](#notes)

## Project Overview
The [Employee Leave Management System](#code-structure) is a web-based application designed to streamline leave requests, manager approvals, and reporting. It integrates with a mock HR database via CSV import, supports data management, and generates detailed leave reports. This project aligns with Information Management and System Integration and Architecture, showcasing database administration, data integration, workflow automation, and a 3-tier architecture.

## Objectives
- Develop a functional system for employees to submit and managers to approve leave requests.
- Import employee data from a mock HR system via CSV, displaying successes and errors.
- Generate monthly leave summary reports with pending, approved, rejected counts, and total leave days, filterable by year.
- Implement a 3-tier architecture with front-end, logic, and database layers.
- Automate leave request and approval workflows with database-driven notifications.

## Key Features
- **Employee Portal**: Employees submit leave requests with leave type, dates, and reasons.
- **Manager Dashboard**: Managers approve or reject requests, view filtered leave history, and access detailed reports.
- **Reporting Module**: Provides monthly leave summaries (pending, approved, rejected counts, total leave days) with year filtering and PDF download.
- **Data Integration**: Imports employee data via CSV, displaying results for successful imports or errors (e.g., invalid emails, duplicates).
- **Notifications**: Database-driven notifications displayed as toasts or dropdowns with unread counts, triggered for request submissions, approvals, or rejections, with AJAX-based mark-as-read functionality.
- **Role-Based Access Control (RBAC)**: Restricts access to `employee` or `manager` roles. Employees access leave submission and history; managers access approvals, reporting, and HR imports.
- **Secure Login**: Enforces CSRF protection, brute force lockout, audit logging, and mandatory first-time password changes.

## System Architecture
The system follows a [3-tier architecture](#key-files-and-their-functionalities):
- **Front-End**: HTML, CSS, JavaScript for user interfaces.
- **Logic Layer**: PHP for business logic, including leave processing and approvals.
- **Database Layer**: MySQL/MariaDB for data storage, managed via phpMyAdmin.

### Data Model
- **Entities** (defined in [leave_management.sql](#key-files-and-their-functionalities)):
  - `employees`: Stores employee details (ID, name, department, role).
  - `leave_requests`: Tracks leave requests (request ID, employee ID, dates, status, duration).
  - `leave_types`: Defines leave types (e.g., vacation, sick leave).
  - `leave_balances`: Manages leave balances per leave type.
  - `departments`: Stores department details.
  - `audit_logs`: Records security actions (e.g., logins, password changes).
  - `notifications`: Stores notification messages for users.
  - `remember_tokens`: Manages "Remember Me" login tokens.

### Workflow
1. Employee submits a leave request ([leave_submission.php](#key-files-and-their-functionalities)).
2. Manager reviews and approves/rejects the request ([manage_requests.php](#key-files-and-their-functionalities)).
3. System updates the request status and triggers a notification ([Notifications](#key-features)).

## Tools and Technologies
- **Frontend**: HTML, CSS, JavaScript, [Chart.js](https://www.chartjs.org/) for reports, [Font Awesome](https://fontawesome.com/) for icons.
- **Backend**: PHP 8.2.12.
- **Database**: MariaDB 10.4.32 (compatible with MySQL), managed via phpMyAdmin 5.2.1.
- **Integration**: CSV file import for employee data.
- **Version Control**: GitHub for code and documentation.
- **Server**: Apache web server (recommended for PHP deployment).

## Installation and Setup
1. **Clone the Repository**:
   ```bash
   git clone https://github.com/your-group-repo/leave-management-system.git
   ```
   - Must rename the folder (master) to employee-leave-management-system
2. **Install Dependencies**:
   - Install Apache web server and PHP 8.2.12.
   - Install MariaDB 10.4.32 (or MySQL equivalent) for the database.
   - Install phpMyAdmin 5.2.1 for database management.
3. **Database Setup**:
   - Create a `leave_management` database in MariaDB/MySQL via phpMyAdmin.
   - Import the provided [database.sql](#key-files-and-their-functionalities) (`leave_management.sql`) to set up tables and initial data, including:
     - 10 departments (e.g., Human Resources, Information Technology).
     - 10 leave types (e.g., Vacation, Sick Leave).
     - A manager account for testing (`email: user@example.com`, `password:Test@user1`, `role: manager`, `department: Information Technology`).
4. **Configure Integration**:
   - Place the mock HR CSV file (e.g., `employees.csv`) in [frontend/assets/imports/](#key-files-and-their-functionalities).
5. **Run the Application**:
   - Deploy to Apache and access via `http://localhost/employee-leave-management-system/frontend/public/login.php`.
6. **Access the System**:
   - Log in using the manager account (`user@example.com`) to test features, including CSV imports via [hr_import.php](#key-files-and-their-functionalities).

## Code Structure
The project is organized as follows:

```
EMPLOYEE-LEAVE-MANAGEMENT-SYSTEM/
├── backend/
│   ├── controllers/
│   │   ├── EmployeeDashboardController.php
│   │   ├── HRImportController.php
│   │   ├── LeaveRequestController.php
│   │   ├── LeaveSubmissionController.php
│   │   ├── LoginController.php
│   │   ├── LogoutController.php
│   │   ├── ManagerDashboardController.php
│   ├── middlewares/
│   │   ├── AuthMiddleware.php
│   ├── models/
│   │   ├── Auth.php
│   │   ├── LeaveModel.php
│   ├── services/
│   │   ├── generate_report.php
│   ├── src/
│   │   ├── Database.php
│   │   ├── Session.php
│   ├── utils/
│   │   ├── redirect.php
│   ├── vendor/
├── frontend/
│   ├── assets/
│   │   ├── css/
│   │   │   ├── dashboard.css
│   │   │   ├── login.css
│   │   │   ├── manager_dashboard.css
│   │   ├── img/
│   │   │   ├── employees-ill.png
│   │   ├── imports/
│   │   │   ├── employees.csv
│   │   ├── js/
│   │   │   ├── dashboard.js
│   │   │   ├── login.js
│   │   │   ├── manager_dashboard.js
│   │   │   ├── password_validation.js
│   ├── public/
│   │   ├── login.php
│   ├── views/
│   │   ├── manager/
│   │   │   ├── hr_import.php
│   │   │   ├── leave_history.php
│   │   │   ├── manage_requests.php
│   │   │   ├── manager_dashboard.php
│   │   │   ├── reporting.php
│   │   ├── employee_dashboard.php
│   │   ├── leave_history.php
│   │   ├── leave_submission.php
│   │   ├── login_view.php
├── diagrams/
│   ├── Architecture.pdf
│   ├── DFD.pdf
│   ├── ERD.pdf
├── report/
│   ├── IM and SIA Project Report.pdf
├── presentation-slide/
│   ├── Presentation Slides.odp
│   ├── Presentation Slides.pdf
├── logs/
│   ├── imported_default_password.log
├── tests/
├── leave_management.sql
├── .gitignore
├── index.php
├── README.md
```

### Key Files and Their Functionalities
- **[index.php](frontend/public/index.php)**: Routes requests to login, logout, or dashboards, redirecting unmatched routes to the login page.
- **[login.php](frontend/public/login.php)**: Entry point for the login page, invoking [LoginController.php](#key-files-and-their-functionalities) and rendering [login_view.php](#key-files-and-their-functionalities).
- **[login_view.php](frontend/views/login_view.php)**: HTML for login and password change forms, supporting email/password input, "Remember Me," and validation feedback.
- **[LoginController.php](backend/controllers/LoginController.php)**: Manages authentication, session handling, CSRF protection, brute force lockout, first-time password changes, and audit logging.
- **[login.css](frontend/assets/css/login.css)**: Styles the login page with responsive design, animated shapes, and a modern login card.
- **[login.js](frontend/assets/js/login.js)**: Handles client-side login form validation, toggling the `filled` class for inputs.
- **[password_validation.js](frontend/assets/js/password_validation.js)**: Validates password changes client-side, enforcing requirements (length, uppercase, number, special character) with a strength bar.
- **[employee_dashboard.php](frontend/views/employee_dashboard.php)**: Renders the employee dashboard with navigation (Dashboard, Leave Submission, Leave History, Settings), showing leave balances, request counts, and notifications.
- **[EmployeeDashboardController.php](backend/controllers/EmployeeDashboardController.php)**: Fetches employee data, leave requests, balances, and notifications, managing AJAX-based notification actions and leave history.
- **[LogoutController.php](backend/controllers/LogoutController.php)**: Clears session data and remember tokens, redirecting to the homepage.
- **[dashboard.css](frontend/assets/css/dashboard.css)**: Styles the employee dashboard with a responsive sidebar, cards, and toast notifications.
- **[dashboard.js](frontend/assets/js/dashboard.js)**: Manages client-side dashboard interactions, including sidebar toggling, dropdowns, and AJAX notification updates.
- **[redirect.php](backend/utils/redirect.php)**: Utility for redirects with optional success/error messages.
- **[Database.php](backend/src/Database.php)**: Singleton for secure MySQL/MariaDB PDO connections with error handling.
- **[Session.php](backend/src/Session.php)**: Manages secure PHP sessions (HTTPS, HTTP-only, SameSite=Strict), handling role checks and authentication.
- **[Auth.php](backend/models/Auth.php)**: Handles login, password updates, remember tokens, and logout, integrating with [Session.php](#key-files-and-their-functionalities) for role validation.
- **[AuthMiddleware.php](backend/middlewares/AuthMiddleware.php)**: Enforces authentication and role-based access, redirecting unauthenticated users and ensuring password changes.
- **[LeaveModel.php](backend/models/LeaveModel.php)**: Manages leave-related database operations (leave types, requests, balances, history).
- **[leave_submission.php](frontend/views/leave_submission.php)**: Renders the leave request form, allowing employees to select leave types, dates, and reasons, with notifications.
- **[LeaveSubmissionController.php](backend/controllers/LeaveSubmissionController.php)**: Processes leave request submissions via AJAX, validating CSRF tokens, dates, and balances, and managing notifications.
- **[leave_history.php (Employee)](frontend/views/leave_history.php)**: Displays an employee’s leave history with status, date, and leave type filters, plus pagination.
- **[leave_history.php (Manager)](frontend/views/manager/leave_history.php)**: Shows approved/rejected leave requests with filters for employee name, leave type, status, and date range, plus pagination, restricted to managers.
- **[manager_dashboard.php](frontend/views/manager/manager_dashboard.php)**: Renders the manager dashboard with navigation (Home, Manage Requests, Leave History, Reporting, HR Import, Settings), showing statistics, trends, and notifications.
- **[ManagerDashboardController.php](backend/controllers/ManagerDashboardController.php)**: Fetches manager-specific data (statistics, trends, notifications, leave history), handling AJAX-based notification updates and audit logging.
- **[manager_dashboard.js](frontend/assets/js/manager_dashboard.js)**: Manages client-side manager dashboard interactions, including Chart.js visualizations and AJAX notification updates.
- **[manager_dashboard.css](frontend/assets/css/manager_dashboard.css)**: Styles the manager dashboard with responsive layouts for cards, charts, and notifications.
- **[manage_requests.php](frontend/views/manager/manage_requests.php)**: Allows managers to review and action pending leave requests with a paginated table and approval/rejection modals.
- **[LeaveRequestController.php](backend/controllers/LeaveRequestController.php)**: Processes manager approvals/rejections, validating department and balances, sending notifications, and logging actions.
- **[HRImportController.php](backend/controllers/HRImportController.php)**: Handles CSV employee imports, validating headers, emails, and roles, generating passwords, and displaying import results.
- **[hr_import.php](frontend/views/manager/hr_import.php)**: Renders the HR import page, allowing CSV uploads and showing import results.
- **[reporting.php](frontend/views/manager/reporting.php)**: Displays a monthly leave summary table (pending, approved, rejected requests, total leave days) with year filtering and PDF download, restricted to managers.
- **[database.sql](database.sql)**: SQL script to create the `leave_management` database and tables (`employees`, `leave_requests`, `leave_types`, `leave_balances`, `departments`, `audit_logs`, `notifications`, `remember_tokens`).
- **[employees.csv](frontend/assets/imports/employees.csv)**: Mock HR data for CSV imports.

## Usage
- **Employee**:
  - Log in at [login.php](#key-files-and-their-functionalities). First-time users must change their password.
  - Access the dashboard ([employee_dashboard.php](#key-files-and-their-functionalities)) to submit leave requests, view history, check balances, or manage notifications.
- **Manager**:
  - Log in using the provided manager account (`email: user@example.com`) at [login.php](#key-files-and-their-functionalities). If the password is unknown, reset it via phpMyAdmin or use the known credentials.
  - Access the dashboard ([manager_dashboard.php](#key-files-and-their-functionalities)) to:
    - Approve/reject requests ([manage_requests.php](#key-files-and-their-functionalities)).
    - View leave history with filters for employee name, leave type, status, and dates ([leave_history.php (Manager)](#key-files-and-their-functionalities)).
    - Access monthly leave summaries, filter by year, and download PDFs ([reporting.php](#key-files-and-their-functionalities)).
    - Import employee data via CSV, viewing import results ([hr_import.php](#key-files-and-their-functionalities)).
- **Reports**:
  - Managers use [reporting.php](#key-files-and-their-functionalities) for monthly leave summaries.

## Testing
- **Unit Testing**: Validates form submissions and inputs (e.g., login, password validation).
- **Integration Testing**: Ensures CSV imports work and display accurate results.
- **User Testing**: Verifies manager workflows (approvals, reporting, leave history filtering) and notification usability.
- **Instructor Testing**:
  - Use the manager account (`user@example.com`) to log in and test CSV imports via [hr_import.php](#key-files-and-their-functionalities).
  - Verify reporting and leave history features using [reporting.php](#key-files-and-their-functionalities) and [leave_history.php (Manager)](#key-files-and-their-functionalities).

## Future Enhancements
Based on the current implementation, the following enhancements can improve functionality, usability, and scalability:
- **File Upload for Leave Requests**: Enhance leave_submission.php to allow employees to upload supporting documents (e.g., medical certificates for Sick Leave or travel plans for Vacation) when submitting leave requests. This would involve adding a file input field to the form, validating file types (e.g., PDF, JPG) and size limits in LeaveSubmissionController.php, and storing files securely in a designated directory with references in the leave_requests table. Managers could view these files via manage_requests.php to verify requirements for specific leave types (e.g., Maternity Leave), ensuring compliance with organizational policies.
- **Real-Time Notifications**: Replace simulated notifications with email and SMS alerts for request submissions, approvals, and rejections, integrating with services like SendGrid or Twilio.
- **Advanced Reporting Analytics**: Add predictive leave trend analysis, cross-department comparisons, and yearly summaries, with interactive dashboards using tools like D3.js.
- **Progressive Web App (PWA)**: Implement a PWA to leverage the responsive design, enabling offline access, push notifications, and a native-like experience on mobile devices without separate app development.
- **Multi-Language Support**: Implement internationalization (i18n) for accessibility, supporting languages like Filipino and Spanish.
- **Enhanced Security**: Add two-factor authentication (2FA) via email or authenticator apps, and encrypt sensitive data (e.g., passwords) with stronger algorithms.
- **API Integration**: Expose a RESTful API for third-party HR systems to sync employee data or leave requests, secured with OAuth 2.0.
- **Audit Trail Dashboard**: Create a manager-accessible dashboard to view and export audit logs (`audit_logs` table) for compliance and monitoring.
- **Automated Leave Balance Adjustments**: Implement scheduled tasks to update leave balances based on hire dates, policies, or accruals, reducing manual updates via phpMyAdmin.
- **User Profile Management**: Allow users to update profiles (e.g., contact info, profile pictures) and manage notification preferences.
- **Offline CSV Import Support**: Enable queued imports for offline processing, handling large datasets without timeouts.
- **Database Optimization**: Add indexes to `leave_requests` and `notifications` tables, and implement caching (e.g., Redis) for frequent queries to improve performance.
- **Accessibility Compliance**: Ensure WCAG 2.1 compliance with screen reader support and keyboard navigation for all UI components.
- **Bulk Request Management**: Allow managers to approve/reject multiple leave requests simultaneously via the manager dashboard.
- **Custom Leave Policies**: Support configurable leave policies per department (e.g., different accrual rates, approval workflows).
- **Employee Self-Service Portal**: Add features for employees to view payslips, tax forms, or HR policies, integrating with the mock HR system.
- **Cloud Deployment**: Deploy to a cloud platform like AWS or Azure for scalability, with load balancing and auto-scaling for high traffic.

## Notes
- Follow modular coding, commenting, and version control practices.
- Ensure repository organization with clear folders.
- Maintain data integrity and usability.
- **Security**: CSRF protection, brute force lockout, audit logging, and mandatory password changes enhance security ([LoginController.php](#key-files-and-their-functionalities)).
- **Notifications**: Stored in `notifications` table, fetched via controllers, displayed as toasts/dropdowns with unread badges, and updated via AJAX ([ManagerDashboardController.php](#key-files-and-their-functionalities)).
- **RBAC**: Uses `employee` and `manager` roles, enforced by [AuthMiddleware.php](#key-files-and-their-functionalities) and [Session.php](#key-files-and-their-functionalities). Managers access exclusive features; employees are restricted.
- Database operations use phpMyAdmin, with [Auth.php](#key-files-and-their-functionalities) for authentication and [LeaveModel.php](#key-files-and-their-functionalities) for leave queries.
- The manager account (`user@example.com`) is included in the database for testing, ensuring your instructor can log in and import employees via CSV without creating a new account.
