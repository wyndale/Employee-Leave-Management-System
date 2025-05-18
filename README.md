EMPLOYEE-LEAVE-MANAGEMENT-SYSTEM/
│
├── access/
│
├── backend/
│   ├── controllers/
│   │   ├── EmployeeDashboardController.php
│   │   ├── LeaveSubmissionController.php
│   │   ├── LoginController.php
│   │   └── LogoutController.php
│   │
│   ├── middlewares/
│   │   └── AuthMiddleware.php
│   │
│   ├── src/
│   │   ├── Database.php
│   │   └── Session.php
│   │
│   └── utils/
│       └── redirect.php
│
├── frontend/
│   ├── assets/
│   │   ├── css/
│   │   │   ├── dashboard.css
│   │   │   └── login.css
│   │   ├── img/
│   │   │   └── login illustration image
│   │   ├── imports/
│   │   │   └── [integrated mock HR data source]
│   │   └── js/
│   │       ├── dashboard.js
│   │       ├── login.js
│   │       └── password_validation.js
│
├── models/
│   ├── Auth.php
│   └── LeaveModel.php
│
├── public/
│   └── login.php
│
├── views/
│   ├── employee_dashboard.php
│   ├── leave_submission.php
│   └── login_view.php
│
├── tests/
│
├── .gitignore
├── .htaccess
├── index.php
└── README.md
