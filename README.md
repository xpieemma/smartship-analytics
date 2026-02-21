# SmartShip Analytics 🚢

An intelligent freight audit and business intelligence dashboard that automatically detects shipping overcharges, late deliveries, and billing errors.

![Dashboard Preview](https://via.placeholder.com/800x400?text=SmartShip+Dashboard+Preview)

## 📋 Table of Contents
- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Installation](#installation)
- [Database Setup](#database-setup)
- [Running the Application](#running-the-application)
- [Testing](#testing)
- [API Documentation](#api-documentation)
- [Project Structure](#project-structure)
- [Screenshots](#screenshots)
- [Future Enhancements](#future-enhancements)
- [Contributing](#contributing)
- [License](#license)

## 🎯 Overview

SmartShip Analytics is a full-stack web application that helps logistics companies automatically audit their freight invoices. It detects discrepancies in billing, identifies late deliveries eligible for service credits, and visualizes exception patterns to help recover overpayments.

**The Problem:** Freight carriers overbill 10-20% of the time through weight discrepancies, rate abuses, and hidden fees. Manual auditing is slow and misses many errors.

**The Solution:** Automated exception detection with real-time dashboards that highlight potential savings and problem areas.

## ✨ Features

### 🔍 Intelligent Audit Engine
- **Weight Discrepancy Detection** - Catches when carriers bill for more weight than shipped
- **Late Delivery Identification** - Flags shipments delivered after the guaranteed date
- **Rate Abuse Detection** - Identifies when incorrect rates are applied
- **Duplicate Invoice Detection** - Finds multiple bills for the same shipment
- **Fuel Surcharge Validation** - Verifies fuel surcharges against contract rates

### 📊 Interactive Dashboard
- **Real-time KPIs** - Total spend, exception count, potential savings
- **Exception Heat Map** - Visualize problem shipping lanes
- **Trend Analysis** - Track exception patterns over time
- **Filterable Data Table** - Sort and filter exceptions by type, severity, status
- **Drill-down Details** - Click any exception for full details

### 🧪 Comprehensive Testing
- Database integrity tests
- API endpoint validation
- Audit engine unit tests
- Frontend component tests

## 🛠️ Tech Stack

### Backend
- **PHP 8.3** - Core application logic
- **MySQL** - Database with JSON support for flexible exception details
- **PDO** - Secure database connections with prepared statements

### Frontend
- **HTML5/CSS3** - Responsive, modern UI
- **JavaScript (Vanilla)** - Core application logic
- **jQuery** - DOM manipulation and AJAX
- **Chart.js** - Interactive data visualizations
- **Font Awesome** - Icons and UI elements

### Development & Testing
- **Custom Test Suite** - 100+ tests across all components
- **Git** - Version control
- **XAMPP/Laragon** - Local development environment

## 📦 Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (optional)

### Step 1: Clone the Repository
```bash
git clone https://github.com/xpieemma/smartship-analytics.git
cd smartship-analytics



Step 2: Configure Database
Create a MySQL database named smartship:

sql
CREATE DATABASE smartship CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
Step 3: Update Database Credentials
Edit src/bootstrap.php with your database settings:

php
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartship');
define('DB_USER', 'root');      // Your MySQL username
define('DB_PASS', '');           // Your MySQL password
Step 4: Set Up Database Schema
bash
# Via MySQL command line
mysql -u root -p smartship < database/schema.sql

# Or import database/schema.sql using phpMyAdmin
Step 5: Seed the Database
bash
php database/seed.php
Step 6: Configure Web Server
Point your web server to the project root. For XAMPP:

Copy project to C:\xampp\htdocs\smartship\

Access at http://localhost/smartship/

🚀 Running the Application
Start your web server (Apache/MySQL)

Open your browser and navigate to:

text
http://localhost/smartship/
Log in with demo credentials (if authentication is implemented)

🧪 Testing
The project includes a comprehensive test suite. Run all tests:

bash
# Navigate to the tests directory
http://localhost/smartship/tests/
Individual Test Suites
Test	Purpose	URL
Database Tests	Verify schema and data integrity	/tests/test_database.php
API Tests	Validate all endpoints	/tests/test_api.php
Audit Engine Tests	Test business logic	/tests/test_audit_engine.php
Frontend Tests	Check JavaScript components	/tests/test_frontend.html
📡 API Documentation
Base URL
text
http://localhost/smartship/api/
Endpoints
GET /test.php
Health check endpoint.

json
{
    "success": true,
    "message": "API is working",
    "time": "2026-02-21 12:34:56",
    "php_version": "8.3.30"
}
GET /dashboard-data.php
Get all dashboard data in one request.

json
{
    "success": true,
    "metrics": {...},
    "lanes": [...],
    "exceptions_by_type": {...},
    "exceptions": [...],
    "charts": {...}
}
GET /audit.php?action=dashboard
Get audit metrics only.

json
{
    "success": true,
    "data": {
        "total_shipments": 50,
        "total_spend": "38638.24",
        "total_exceptions": 25,
        ...}
}
GET /audit.php?action=audit_batch
Run audit on random shipment batch.

json
{
    "success": true,
    "data": [...]
}
📁 Project Structure
text
smartship/
├── index.html                 # Main dashboard
├── css/
│   └── style.css              # All styling
├── js/
│   └── dashboard.js           # Frontend logic
├── api/
│   ├── test.php                # API health check
│   ├── audit.php                # Audit endpoints
│   └── dashboard-data.php       # Main data endpoint
├── src/
│   ├── bootstrap.php            # App initialization
│   └── Audit/
│       └── AuditEngine.php      # Core audit logic
├── database/
│   ├── schema.sql               # Database structure
│   └── seed.php                 # Sample data generator
├── tests/
│   ├── index.php                 # Test suite home
│   ├── test_database.php         # DB integrity tests
│   ├── test_api.php               # API validation
│   ├── test_audit_engine.php      # Business logic tests
│   └── test_frontend.html        # Frontend tests
└── README.md                     # You are here
📸 Screenshots
Dashboard Overview
https://via.placeholder.com/800x400?text=Dashboard+View

Exception Heat Map
https://via.placeholder.com/800x400?text=Heat+Map

Exception Details Modal
https://via.placeholder.com/800x400?text=Exception+Details

🚀 Future Enhancements
User Authentication - Multi-tenant support for different companies

Email Alerts - Notify when high-value exceptions are found

Automated Dispute Generation - Create dispute letters for carriers

Machine Learning - Predict which shipments are likely to have exceptions

Export Reports - PDF/Excel exports for client presentations

API Rate Limiting - Prevent abuse

Caching Layer - Redis/Memcached for faster dashboard loading

Mobile App - React Native version for on-the-go auditing

🤝 Contributing
Contributions are welcome! Please follow these steps:

Fork the repository

Create a feature branch (git checkout -b feature/AmazingFeature)

Commit your changes (git commit -m 'Add some AmazingFeature')

Push to the branch (git push origin feature/AmazingFeature)

Open a Pull Request

Coding Standards
PHP: PSR-12

JavaScript: ESLint with Airbnb config

CSS: BEM naming convention

📝 License
This project is licensed under the MIT License - see the LICENSE file for details.

👏 Acknowledgments
Inspired by Intelligent Audit - leaders in freight audit

Chart.js team for amazing visualization library

Font Awesome for beautiful icons

📧 Contact
Your Name - @yourtwitter - email@example.com

Project Link: https://github.com/yourusername/smartship-analytics

Built with ❤️ for the Junior Developer position at Intelligent Audit

text

## 🎨 **Optional: Add a Logo**

If you want to add a logo, create an `assets/` folder and add:
smartship/
├── assets/
│ ├── logo.svg
│ └── screenshots/
│ ├── dashboard.png
│ ├── heatmap.png
│ └── details.png

text

Then update the screenshot links in the README.

## ✅ **README Checklist**

| Section | Status |
|---------|--------|
| Overview | ✅ |
| Features | ✅ |
| Tech Stack | ✅ |
| Installation | ✅ |
| Running | ✅ |
| Testing | ✅ |
| API Docs | ✅ |
| Project Structure | ✅ |
| Screenshots | ⚠️ (Add your own) |
| Future Enhancements | ✅ |
| Contributing | ✅ |
| License | ✅ |
| Contact | ⚠️ (Add your info) |