<?php

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function has_role(string $role): bool {
    return user_role() === $role;
}

function require_login(): void {
    if (!is_logged_in()) {
        header("Location: " . url("index.php?p=login"));
        exit;
    }
}


function require_role(string $role): void {
    if (!is_logged_in() || !has_role($role)) {
        header("Location: " . url("index.php?p=unauthorized"));
        exit;
    }
}

/**
 * Redirect logged-in users away from guest pages (login/signup)
 * Redirects to appropriate dashboard based on role
 */
function redirect_if_authenticated(): void {
    if (is_logged_in()) {
        $role = user_role();
        
        // Log the redirect for debugging
        error_log("Redirecting authenticated user with role: " . ($role ?? 'null'));
        
        // Redirect based on role
        switch ($role) {
            case 'superadmin':
            case 'super_admin':
                header("Location: " . url("index.php?p=dashboard&page=superAdminDashboard"));
                break;
            case 'admin':
                header("Location: " . url("index.php?p=dashboard&page=adminDashboard"));
                break;
            default:
                header("Location: " . url("index.php?p=dashboard&page=userDashboard"));
                break;
        }
        exit;
    }
}
