# AHT KPI Management System

A modern, professional PHP-based management system with a beautiful **Light Theme** design, smooth animations, and clean architecture.

## 🌟 Features

- 🎨 **Professional Light Theme** - Clean, bright, corporate-friendly
- ✨ **Smooth Animations** - Count-up effects, hover animations, transitions
- 🏗️ **Modular Architecture** - Easy to extend and maintain
- 🔐 **Secure Authentication** - Password hashing, session management
- 📊 **Beautiful Dashboard** - Stats cards, data tables, modern UI
- 🔗 **Clean URLs** - SEO-friendly routing
- 📱 **Responsive Design** - Works on all devices
- 💼 **Enterprise-Ready** - Professional quality for business use

---

## 🚀 Quick Start

### 1. Start the server

```bash
cd "/Users/hyuncao/AHT KPI"
php -S localhost:8000 router.php
```

Or use the script:
```bash
./start-server.sh
```

### 2. Access the application

Open browser: **http://localhost:8000/login**

### 3. Login

- **Username:** `admin`
- **Password:** `@admin123`

⚠️ **Change the default password after first login!**

---

## 📁 Project Structure

```
AHT KPI/
├── index.php                 # Main entry point
├── router.php                # Router for PHP built-in server
├── .htaccess                 # Apache configuration (production)
├── README.md                 # This file
├── START.md                  # Quick start guide
├── CHANGELOG.md              # Version history
├── config/                   # Configuration files
│   ├── config.php           # Database & app config
│   ├── config.example.php   # Config template
│   └── database.sql         # Database schema
├── assets/                   # Static assets
│   ├── css/                 # Stylesheets (Light theme)
│   │   ├── style.css       # Login page
│   │   └── dashboard.css   # Dashboard
│   ├── js/                  # JavaScript
│   │   ├── script.js       # Login page
│   │   └── dashboard.js    # Dashboard animations
│   └── images/              # Image assets
├── modules/                  # Feature modules
│   ├── auth/                # Authentication
│   │   ├── login.php       # Login page
│   │   └── logout.php      # Logout handler
│   └── dashboard/           # Dashboard
│       └── dashboard.php   # Main dashboard
├── includes/                 # Reusable components
│   └── url_helper.php       # URL helper functions
└── public/                   # Public files
```

---

## 🎨 Design - Professional Light Theme

### Color Palette

**Background:**
- `#ffffff` - Pure white (cards)
- `#f8fafc` - Light gray (background)
- `#f1f5f9` - Subtle gray

**Text:**
- `#0f172a` - Dark (primary)
- `#64748b` - Medium gray (secondary)
- `#94a3b8` - Light gray (tertiary)

**Primary Colors:**
- `#2563eb` - Professional blue
- `#7c3aed` - Purple accent
- `#06b6d4` - Cyan highlight

**Status Colors:**
- `#10b981` - Success green
- `#ef4444` - Error red
- `#f59e0b` - Warning orange

### Design Principles

✅ **Clean & Minimal** - White backgrounds, subtle borders, soft shadows
✅ **Professional** - Corporate blue palette, consistent spacing
✅ **Modern** - Gradient accents, smooth animations, rounded corners
✅ **Accessible** - High contrast text, clear focus states, readable fonts

---

## ✨ Animations & Effects

- **Count-up numbers** - Stats animate from 0 to final value
- **Fade-in cards** - Elements appear smoothly on scroll
- **Hover effects** - Cards lift, icons rotate, smooth transitions
- **Ripple clicks** - Material design ripple effect on buttons
- **Icon animations** - Scale and rotate on hover
- **Mobile menu** - Smooth slide-in sidebar

---

## 🔗 Available URLs

| URL | Description |
|-----|-------------|
| `/` | Home (redirects to login/dashboard) |
| `/login` | Login page |
| `/dashboard` | Dashboard |
| `/logout` | Logout |

---

## ⚙️ Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser

### Setup Steps

1. **Configure database**
   
   Create MySQL database and import schema:
   ```bash
   mysql -u your_username -p your_database < config/database.sql
   ```

2. **Update configuration**
   
   Edit `config/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'your_database');
   ```

3. **Start server**
   ```bash
   php -S localhost:8000 router.php
   ```

4. **Access application**
   
   Open: http://localhost:8000/login

---

## 🛠️ Development vs Production

### Development (PHP Built-in Server)

```bash
php -S localhost:8000 router.php
```

✅ Quick setup, no Apache needed  
✅ Uses `router.php` for clean URLs  
❌ Not suitable for production

### Production (Apache Server)

1. Copy to web directory:
   ```bash
   sudo cp -r "AHT KPI" /var/www/html/
   ```

2. Enable mod_rewrite:
   ```bash
   sudo a2enmod rewrite
   sudo service apache2 restart
   ```

3. Access: `http://yourdomain.com/AHT%20KPI/`

✅ High performance  
✅ Uses `.htaccess` for clean URLs  
✅ Production ready

---

## 🔧 Adding New Modules

### 1. Create module directory

```bash
mkdir -p modules/your_module
```

### 2. Create module file

`modules/your_module/index.php`:
```php
<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
    <h1>Your Module</h1>
</body>
</html>
```

### 3. Add route

**In `router.php`:**
```php
'/your-module' => 'modules/your_module/index.php',
```

**In `.htaccess` (for Apache):**
```apache
RewriteRule ^your-module/?$ modules/your_module/index.php [L]
```

### 4. Access

http://localhost:8000/your-module

---

## 🐛 Troubleshooting

### Port already in use

```bash
kill -9 $(lsof -ti:8000)
# Or use different port
php -S localhost:8001 router.php
```

### ERR_TOO_MANY_REDIRECTS

1. Clear browser cookies (F12 → Application → Cookies → Clear)
2. Or use Incognito mode
3. Restart server

### CSS/JS not loading

```bash
# Check if server is running
lsof -ti:8000

# If not, start server
php -S localhost:8000 router.php
```

### Database connection error

1. Check MySQL is running
2. Verify credentials in `config/config.php`
3. Ensure database exists and schema is imported

---

## 📝 Default Credentials

**Admin Account:**
- Username: `admin`
- Password: `@admin123`

⚠️ **Important:** Change immediately after first login!

---

## 🔒 Security Features

- ✅ Password hashing with `password_hash()`
- ✅ SQL injection prevention (prepared statements)
- ✅ Session management
- ✅ XSS protection (`htmlspecialchars()`)
- ✅ Directory browsing disabled
- ✅ Config directory protected
- ⚠️ Add CSRF protection (recommended)

---

## 📚 Technology Stack

- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Design:** Professional Light Theme
- **Fonts:** Inter (Google Fonts)

---

## 💼 Perfect For

✅ Corporate environments  
✅ Professional dashboards  
✅ Business applications  
✅ Data visualization  
✅ Admin panels  
✅ Management systems  

---

## 📄 Documentation

- **README.md** - This file (complete documentation)
- **START.md** - Quick start guide
- **CHANGELOG.md** - Version history

---

## 💡 Quick Commands

```bash
# Start server
php -S localhost:8000 router.php

# Kill server on port 8000
kill -9 $(lsof -ti:8000)

# Import database
mysql -u root -p your_database < config/database.sql

# Check if server is running
lsof -ti:8000
```

---

## 📊 What's New in v2.0

### Light Theme
- ✨ Professional light color scheme
- 🌟 Bright, clean interface
- 💼 Corporate-friendly design
- 📝 High readability

### Enhanced Animations
- ✨ Count-up effect for numbers
- ✨ Fade-in animations on scroll
- ✨ Hover effects with 3D transforms
- ✨ Ripple effect on clicks
- ✨ Icon scale & rotate animations

### Improved UX
- ✨ Smooth transitions everywhere
- ✨ Mobile menu with animations
- ✨ Better responsive design
- ✨ Professional quality

---

## 📄 License

This project is proprietary software. All rights reserved.

---

## 👥 Support

For support, please contact the development team.

---

**Version:** 2.0.0 (Light Theme)  
**Last Updated:** February 12, 2026  
**Developed by:** ArrowHitech

---

**Happy coding! 🎉**
