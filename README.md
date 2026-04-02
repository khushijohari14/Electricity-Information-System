# ⚡ Electricity Information System (EIS)

<div align="center">

![EIS Banner](https://capsule-render.vercel.app/api?type=waving&color=gradient&customColorList=12&height=200&section=header&text=Electricity%20Information%20System&fontSize=40&fontColor=fff&animation=fadeIn&fontAlignY=38&desc=PHP%20%7C%20MySQL%20%7C%20Admin%20%26%20Consumer%20Portal&descAlignY=55&descAlign=50)

[![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)](https://html.spec.whatwg.org)
[![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)](https://www.w3.org/Style/CSS)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://javascript.com)
[![XAMPP](https://img.shields.io/badge/XAMPP-FB7A24?style=for-the-badge&logo=xampp&logoColor=white)](https://apachefriends.org)

![GitHub repo size](https://img.shields.io/github/repo-size/khushijohari14/Electricity-Information-System?color=c9a96e&style=flat-square)
![GitHub last commit](https://img.shields.io/github/last-commit/khushijohari14/Electricity-Information-System?color=c9a96e&style=flat-square)
![GitHub stars](https://img.shields.io/github/stars/khushijohari14/Electricity-Information-System?color=c9a96e&style=flat-square)

</div>

---

## 📌 About The Project

The **Electricity Information System (EIS)** is a full-stack web application designed to automate and streamline electricity department operations. It digitizes the entire billing lifecycle — from consumer registration to payment recording — eliminating manual paperwork and reducing errors.

Developed as part of the **Software Engineering course** at **Lovely Professional University**, this project implements real-world concepts including session management, tariff-based billing logic, and role-based access control.

> 🪐 *Features a unique dark luxury UI with animated Saturn rings and a 3-color theme switcher — Gold, Red, and Blue.*

---

## ✨ Features

### 🛡️ Admin Portal
| Feature | Description |
|--------|-------------|
| 👥 Consumer Management | Register consumers with auto-generated Consumer ID (CON-001, CON-002...) |
| 📟 Meter Assignment | Assign meter numbers and types to registered consumers |
| 📊 Enter Readings | Record monthly meter readings with automatic unit calculation |
| 🧾 Bill Generation | Auto-generate bills based on tariff slabs per connection type |
| 💳 Record Payments | Log consumer payments with mode (Cash/UPI/Bank/Cheque) |
| 📈 Dashboard Stats | Live stats — revenue, overdue bills, units consumed, unpaid consumers |
| 🔍 Smart Search | Search consumers and meters by Consumer ID or Meter Number |
| 📋 Activity Log | Real-time log of all admin actions |
| ⚠️ Overdue Detection | Auto-marks bills as overdue past due date |

### 👤 Consumer Portal
| Feature | Description |
|--------|-------------|
| 🧾 My Bills | View all generated bills with status (Paid/Pending/Overdue) |
| 💰 Payment History | Complete payment records with transaction details |
| ⚡ Tariff Table | View applicable electricity rates by connection type |
| 📟 Meter Details | View assigned meter info and latest reading |
| 👤 My Profile | View personal account details |
| 🚨 Smart Alerts | Overdue and pending bill alerts on dashboard |

---

## 🎨 UI Highlights

- 🪐 **Saturn Ring Animation** — Canvas-based animated Saturn with glittering rings spreading across the full page
- 🌗 **3 Color Themes** — Gold (default), Red, Blue — switchable on any page, saved to localStorage
- 🖤 **Dark Luxury Design** — Deep black backgrounds with gold accents and ivory text
- ✨ **Glitter Particles** — Animated sparkle particles floating along Saturn rings
- 📱 **Responsive Layout** — Works across screen sizes

---

## 🛠️ Tech Stack

```
Frontend  →  HTML5, CSS3, JavaScript (Canvas API)
Backend   →  PHP 8 (Procedural)
Database  →  MySQL (via MySQLi)
Server    →  Apache (XAMPP)
Tools     →  VS Code, phpMyAdmin, Git, GitHub
```

---

## 🗃️ Database Schema

```
electricity_db
├── admins           → Admin login credentials
├── consumers        → Consumer details + login
├── meters           → Meter assignments
├── meter_readings   → Monthly readings
├── bills            → Generated bills
├── payments         → Payment records
├── tariffs          → Rate slabs by category
└── activity_log     → Admin action history
```

### 💡 Tariff Structure
| Connection Type | Units (kWh) | Rate per Unit |
|----------------|-------------|---------------|
| Residential | 0 – 100 | ₹3.50 |
| Residential | 101 – 300 | ₹5.00 |
| Residential | 301+ | ₹7.50 |
| Commercial | All units | ₹9.00 |
| Industrial | All units | ₹6.50 |

---

## 📁 Project Structure

```
EIS/
├── 📂 db/
│   └── eis.sql                 # Complete database schema + seed data
├── 📂 includes/
│   ├── db.php                  # MySQL database connection
│   └── session.php             # Session management helper
├── 📄 login.php                # Login page (Admin + Consumer)
├── 📄 dashboard.php            # Admin dashboard (all workflow pages)
├── 📄 consumer.php             # Consumer dashboard
├── 📄 logout.php               # Session destroy + redirect
├── 📄 search.php               # AJAX search API
├── 📄 .gitignore
└── 📄 README.md
```

---

## ⚙️ Installation & Setup

### Prerequisites
- XAMPP (Apache + MySQL)
- PHP 8.0+
- Git

### Steps

**1. Clone the repository**
```bash
git clone https://github.com/khushijohari14/Electricity-Information-System.git
```

**2. Move to XAMPP**
```
Copy the EIS folder to → C:/xampp/htdocs/EIS
```

**3. Import the database**
- Start XAMPP → Start Apache + MySQL
- Open `http://localhost/phpmyadmin`
- Create database: `electricity_db`
- Click Import → select `db/eis.sql` → click Go

**4. Configure database**
```php
// includes/db.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'electricity_db');
```

**5. Run the project**
```
http://localhost/EIS/login.php
```

---

## 🔑 Default Credentials

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `admin123` |
| Consumer | `CON-001` | set by admin during registration |

---

## 🔄 Admin Workflow

```
Register Consumer → Assign Meter → Enter Reading → Generate Bill → Record Payment
      ↓                  ↓               ↓               ↓               ↓
  Auto Consumer ID   Meter Number   Units Auto      Tariff Auto      Mark Paid
  Generated          Assigned       Calculated      Applied          + Activity Log
```

---

## 📚 Academic Details

| Field | Details |
|-------|---------|
| **Student** | Khushi Johari |
| **Registration No.** | 12301997 |
| **University** | Lovely Professional University |
| **Course** | B.Tech — Computer Science & Engineering |
| **Subject** | Software Engineering |
| **Submitted To** | Deepinder Kaur (29599) |

---

<div align="center">

### ⭐ If you found this helpful, give it a star!

Made with ❤️ by [Khushi Johari](https://github.com/khushijohari14)

![Footer](https://capsule-render.vercel.app/api?type=waving&color=gradient&customColorList=12&height=100&section=footer)

</div>
