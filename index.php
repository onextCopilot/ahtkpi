<?php
/**
 * AHT KPI Management System
 * Entry Point
 * 
 * This file serves as the main entry point for the application.
 * It redirects users to the appropriate module based on their authentication status.
 */

// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard
    header("Location: dashboard");
    exit();
} else {
    // Redirect to login page
    header("Location: login");
    exit();
}