# Waves Platform - Project Tracking System

A professional collaboration portal for a single shared project between **Waves** (company side) and **Details** (client side).

## Features

- **Single Project Workspace** — Streamlined, focused on one project
- **Role-Based Access** — Admin, Project Manager (Waves), Client Users (Details)
- **Task Management** — Create, edit, track tasks with full lifecycle
- **3 Task Views** — List, Cards, Kanban board
- **Task Statuses** — New → In Progress → Delivered → Pending Review → Needs Revision → Completed
- **Discussion System** — Comment threads inside each task with file attachments
- **File Upload & Delivery** — Drag-and-drop file uploads per task
- **Dashboards & Statistics** — Role-specific dashboards with stats and charts
- **Notifications** — In-app + email notifications for all events
- **Reports** — Tasks by status/priority, overdue tracking, CSV export
- **Bilingual** — Arabic (RTL) and English (LTR) with language switcher
- **Responsive** — Mobile, tablet, and desktop friendly
- **Settings Panel** — Site config, SMTP email, upload settings
- **Security** — CSRF protection, password hashing, file validation, XSS prevention

## Requirements

- PHP 7.4 or higher
- MySQL 5.7+ / MariaDB 10.3+
- PDO MySQL extension
- mbstring extension
- Shared hosting compatible (Hostinger, etc.)

## Installation

1. Upload all files to your hosting (e.g., `public_html/`)
2. Create a MySQL database on your hosting panel
3. Visit `https://yourdomain.com/install.php`
4. Follow the 3-step wizard:
   - **Step 1**: System requirements check
   - **Step 2**: Enter database credentials → tables are created automatically
   - **Step 3**: Create admin account
5. Login and start using the platform
6. **Delete `install.php`** after successful installation for security

## Default Admin Credentials

Set during installation wizard (Step 3).

## User Roles

| Role | Description |
|------|-------------|
| **Admin** | Full system access, user management, settings, reports |
| **Project Manager** | Create/edit tasks, manage workflow, upload deliverables |
| **Client** | View tasks, comment, upload files, request revisions |

## Task Workflow

```
New → In Progress → Delivered → Pending Review → Completed
                                      ↓
                              Needs Revision → In Progress → ...
```

## File Structure

```
├── config.php              # Auto-generated configuration
├── install.php             # Multi-step installation wizard
├── login.php               # Authentication
├── logout.php
├── forgot_password.php     # Password recovery
├── reset_password.php
├── index.php               # Dashboard / workspace
├── tasks.php               # Task list (table/cards/kanban)
├── task_view.php           # Task details, comments, files
├── task_edit.php           # Edit task form
├── users.php               # User management (admin)
├── notifications.php       # Notification center
├── profile.php             # User profile & password
├── settings.php            # System settings (admin)
├── reports.php             # Reports & analytics
├── download.php            # Secure file download handler
├── includes/
│   ├── header.php          # Layout header + sidebar
│   ├── footer.php          # Layout footer
│   ├── auth.php            # Authentication helpers
│   ├── csrf.php            # CSRF protection
│   ├── email.php           # Email notification system
│   ├── functions.php       # Core helper functions
│   └── lang.php            # Language/localization system
├── lang/
│   ├── ar.php              # Arabic translations
│   └── en.php              # English translations
├── assets/
│   ├── css/style.css       # Main stylesheet (RTL+LTR)
│   └── js/app.js           # Frontend JavaScript
├── uploads/                # User uploads (auto-created)
│   ├── attachments/
│   ├── avatars/
│   └── .htaccess           # Security: block PHP execution
├── .htaccess               # Server config + security headers
├── installed.lock          # Installation lock file
└── README.md
```

## Database Tables

- `users` — User accounts and roles
- `tasks` — Task data (no project_id — single project)
- `task_comments` — Discussion threads per task
- `task_attachments` — Files uploaded to tasks/comments
- `notifications` — In-app notification queue
- `activity_log` — Audit trail
- `settings` — Key-value system configuration
- `password_resets` — Password recovery tokens
- `task_status_history` — Status change log per task
- `email_log` — Email delivery tracking

## Email Configuration

Configure SMTP in **Settings → SMTP Settings** for reliable email delivery.
Supports TLS/SSL encryption. Falls back to PHP `mail()` if SMTP is not configured.

## Security Notes

- All SQL queries use prepared statements (PDO)
- Output escaping with `htmlspecialchars()` everywhere
- CSRF tokens on all POST forms
- Passwords hashed with `password_hash()` (bcrypt)
- File upload validation (extension whitelist, dangerous file blocking)
- `.htaccess` blocks PHP execution in uploads directory
- Session protection with `httponly` and `samesite` cookies
- Role-based access control on all pages

## License

Proprietary — Waves & Details collaboration platform.
