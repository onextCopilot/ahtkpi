#!/bin/bash

# Start PHP Built-in Server with Router
# This script starts the PHP development server with clean URL support

echo "🚀 Starting AHT KPI Management System..."
echo "📍 Server: http://localhost:8000"
echo ""
echo "Available URLs:"
echo "  - http://localhost:8000/         (Home - redirects to login/dashboard)"
echo "  - http://localhost:8000/login    (Login page)"
echo "  - http://localhost:8000/dashboard (Dashboard)"
echo "  - http://localhost:8000/logout   (Logout)"
echo ""
echo "Press Ctrl+C to stop the server"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Start PHP server with router
php -S localhost:8000 router.php
