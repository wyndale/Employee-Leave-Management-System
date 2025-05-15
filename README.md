/Employee-Leave-Management-System
├── access/                      → Authentication and access control scripts
├── backend/
│   ├── controllers/            → Handles business logic and request processing
│   │   ├── EmployeeDashboardController.php
│   │   ├── LoginController.php
│   │   └── LogoutController.php
│   ├── middlewares/           → Middleware logic for session/auth checking
│   │   └── AuthMiddleware.php
│   ├── src/                    → Reserved for application logic/helpers
│   └── utils/                  → Utility functions (can be expanded)
├── frontend/
│   ├── assets/
│   │   ├── css/               → CSS files like dashboard.css, login.css
│   │   ├── img/               → Image assets
│   │   ├── imports/           → CSV file for mock data from HR
│   │   └── js/                → JavaScript files
├── models/                     → PHP model files (database operations)
│   ├── Auth.php
│   └── LeaveModel.php
├── public/
│   └── login.php              → Login entry script
├── views/
│   ├── login_view.php         → Login UI view
│   └── employee_dashboard.php
│      
├── tests/ 
├── .gitignore
├── .htaccess
├── index.php
└── README.md