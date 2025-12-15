<?php

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function user_role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

function has_role(string $role): bool
{
    return user_role() === $role;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header("Location: " . url("public/index.php?p=login"));
        exit;
    }
}


function require_role(string $role): void
{
    if (!is_logged_in() || !has_role($role)) {
        header("Location: " . url("public/index.php?p=unauthorized"));
        exit;
    }
}

/**
 * Redirect logged-in users away from guest pages (login/signup)
 * Redirects to appropriate dashboard based on role
 */
function redirect_if_authenticated(): void
{
    if (is_logged_in()) {
        $role = user_role();

        // Log the redirect for debugging
        error_log("Redirecting authenticated user with role: " . ($role ?? 'null'));

        // Redirect based on role
        switch ($role) {
            case 'superadmin':
            case 'super_admin':
                header("Location: " . url("public/index.php?p=dashboard&page=superAdminDashboard"));
                break;
            case 'admin':
                header("Location: " . url("public/index.php?p=dashboard&page=adminDashboard"));
                break;
            default:
                header("Location: " . url("public/index.php?p=dashboard&page=userDashboard"));
                break;
        }
        exit;
    }
}

/**
 * Require profile completion before allowing access to certain pages.
 * Checks if the user has updated their name from the default (email prefix).
 */
function require_profile_completion(): void
{
    if (!is_logged_in()) {
        require_login();
    }

    $user = current_user();

    // Check if name has the 'Pending: ' prefix we added at signup
    // or if it is empty
    if (empty($user['name']) || strpos($user['name'], 'Pending: ') === 0) {
        // Set a flash message if not already set (to avoid loop msg)
        // We can't easily check flash_message session key directly safely without helper, 
        // so we'll just set it. It might show up twice if they navigate around, but acceptable.

        // Avoid infinite loop if we are already redirecting
        $currentPage = $_GET['page'] ?? '';
        if ($currentPage !== 'profile' && $currentPage !== 'profile_update') {
            // We need to include flash helper if not already included, but safer to rely on where this is called
            if (function_exists('setWarningMessage')) {
                setWarningMessage("Please complete your profile details (Full Name) before proceeding.");
            }
            header("Location: " . url("public/index.php?p=dashboard&page=profile"));
            exit;
        }
    }
}
