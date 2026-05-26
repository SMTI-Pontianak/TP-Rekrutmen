<?php
/**
 * Security Helper Module
 * Handles rate limiting, account locking, input sanitization, and security logging
 */

// Ensure temp directory exists
$temp_dir = __DIR__ . '/temp';
if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

/**
 * Get client IP address
 * @return string
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Check if request exceeds rate limit
 * @param string $action - Action type (e.g., 'login', 'register')
 * @param int $max_attempts - Maximum attempts allowed
 * @param int $time_window - Time window in minutes
 * @return bool - true if limit exceeded, false if ok
 */
function checkRateLimit(string $action, int $max_attempts = 5, int $time_window = 5): bool {
    $ip = getClientIP();
    $temp_dir = __DIR__ . '/temp';
    $rate_limit_file = $temp_dir . '/rate_limit_' . md5($ip . $action) . '.json';
    
    $current_time = time();
    $window_start = $current_time - ($time_window * 60);
    
    $attempts = [];
    if (file_exists($rate_limit_file)) {
        $attempts = json_decode(file_get_contents($rate_limit_file), true);
    }
    
    // Remove old attempts outside the time window
    $attempts = array_filter($attempts, function($timestamp) use ($window_start) {
        return $timestamp > $window_start;
    });
    
    // Check if limit exceeded
    if (count($attempts) >= $max_attempts) {
        // Update file with current attempts
        file_put_contents($rate_limit_file, json_encode($attempts));
        logSecurityEvent('RATE_LIMIT_EXCEEDED', ['action' => $action, 'attempts' => count($attempts)]);
        return true;
    }
    
    return false;
}

/**
 * Get remaining attempts before rate limit triggered
 * @param string $action
 * @param int $max_attempts
 * @param int $time_window
 * @return int
 */
function getRateLimitRemaining(string $action, int $max_attempts = 5, int $time_window = 5): int {
    $ip = getClientIP();
    $temp_dir = __DIR__ . '/temp';
    $rate_limit_file = $temp_dir . '/rate_limit_' . md5($ip . $action) . '.json';
    
    $current_time = time();
    $window_start = $current_time - ($time_window * 60);
    
    $attempts = [];
    if (file_exists($rate_limit_file)) {
        $attempts = json_decode(file_get_contents($rate_limit_file), true);
    }
    
    $valid_attempts = array_filter($attempts, function($timestamp) use ($window_start) {
        return $timestamp > $window_start;
    });
    
    return max(0, $max_attempts - count($valid_attempts));
}

/**
 * Get wait time before next attempt allowed (in seconds)
 * @param string $action
 * @param int $max_attempts
 * @param int $time_window
 * @return int
 */
function getRateLimitWaitTime(string $action, int $max_attempts = 5, int $time_window = 5): int {
    $ip = getClientIP();
    $temp_dir = __DIR__ . '/temp';
    $rate_limit_file = $temp_dir . '/rate_limit_' . md5($ip . $action) . '.json';
    
    $current_time = time();
    $window_start = $current_time - ($time_window * 60);
    
    $attempts = [];
    if (file_exists($rate_limit_file)) {
        $attempts = json_decode(file_get_contents($rate_limit_file), true);
    }
    
    if (!empty($attempts)) {
        $oldest_attempt = min($attempts);
        $wait_until = $oldest_attempt + ($time_window * 60);
        $wait_time = $wait_until - $current_time;
        return max(0, $wait_time);
    }
    
    return 0;
}

/**
 * Record a failed login attempt for brute force protection
 * @param string $username - Username attempting to login
 * @return void
 */
function recordFailedLogin(string $username): void {
    $temp_dir = __DIR__ . '/temp';
    $failed_login_file = $temp_dir . '/failed_login_' . md5($username) . '.json';
    
    $current_time = time();
    $window_start = $current_time - (30 * 60); // 30-minute window
    
    $attempts = [];
    if (file_exists($failed_login_file)) {
        $attempts = json_decode(file_get_contents($failed_login_file), true);
    }
    
    // Remove old attempts outside the 30-minute window
    $attempts = array_filter($attempts, function($timestamp) use ($window_start) {
        return $timestamp > $window_start;
    });
    
    // Add current attempt
    $attempts[] = $current_time;
    
    // Save updated attempts
    file_put_contents($failed_login_file, json_encode($attempts));
}

/**
 * Check if account is locked due to brute force protection
 * @param string $username - Username to check
 * @param int $lockout_threshold - Number of failed attempts before lockout
 * @param int $lockout_duration - Lockout duration in minutes
 * @return bool - true if account is locked, false if ok
 */
function isAccountLocked(string $username, int $lockout_threshold = 5, int $lockout_duration = 15): bool {
    $temp_dir = __DIR__ . '/temp';
    $failed_login_file = $temp_dir . '/failed_login_' . md5($username) . '.json';
    
    if (!file_exists($failed_login_file)) {
        return false;
    }
    
    $current_time = time();
    $attempts = json_decode(file_get_contents($failed_login_file), true);
    
    // Remove old attempts outside the 30-minute window
    $window_start = $current_time - (30 * 60);
    $attempts = array_filter($attempts, function($timestamp) use ($window_start) {
        return $timestamp > $window_start;
    });
    
    // Check if lockout threshold reached
    if (count($attempts) >= $lockout_threshold) {
        // Check if still within lockout period
        $oldest_attempt = min($attempts);
        $lockout_end = $oldest_attempt + ($lockout_duration * 60);
        
        if ($current_time < $lockout_end) {
            return true;
        } else {
            // Lockout period expired, clear attempts
            clearFailedLogins($username);
            return false;
        }
    }
    
    return false;
}

/**
 * Get remaining lockout time in seconds
 * @param string $username
 * @param int $lockout_threshold
 * @param int $lockout_duration
 * @return int
 */
function getAccountLockTime(string $username, int $lockout_threshold = 5, int $lockout_duration = 15): int {
    $temp_dir = __DIR__ . '/temp';
    $failed_login_file = $temp_dir . '/failed_login_' . md5($username) . '.json';
    
    if (!file_exists($failed_login_file)) {
        return 0;
    }
    
    $current_time = time();
    $attempts = json_decode(file_get_contents($failed_login_file), true);
    
    $window_start = $current_time - (30 * 60);
    $attempts = array_filter($attempts, function($timestamp) use ($window_start) {
        return $timestamp > $window_start;
    });
    
    if (count($attempts) >= $lockout_threshold) {
        $oldest_attempt = min($attempts);
        $lockout_end = $oldest_attempt + ($lockout_duration * 60);
        $remaining = $lockout_end - $current_time;
        return max(0, $remaining);
    }
    
    return 0;
}

/**
 * Clear failed login attempts on successful login
 * @param string $username
 * @return void
 */
function clearFailedLogins(string $username): void {
    $temp_dir = __DIR__ . '/temp';
    $failed_login_file = $temp_dir . '/failed_login_' . md5($username) . '.json';
    
    if (file_exists($failed_login_file)) {
        unlink($failed_login_file);
    }
}

/**
 * Add failed attempt to IP rate limit tracker
 * @param string $action
 * @return void
 */
function recordRateLimitAttempt(string $action): void {
    $ip = getClientIP();
    $temp_dir = __DIR__ . '/temp';
    $rate_limit_file = $temp_dir . '/rate_limit_' . md5($ip . $action) . '.json';
    
    $current_time = time();
    
    $attempts = [];
    if (file_exists($rate_limit_file)) {
        $attempts = json_decode(file_get_contents($rate_limit_file), true);
    }
    
    // Add current attempt
    $attempts[] = $current_time;
    
    // Save updated attempts
    file_put_contents($rate_limit_file, json_encode($attempts));
}

/**
 * Sanitize user input to prevent XSS attacks
 * @param string $input
 * @return string
 */
function sanitizeInput(string $input): string {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * Log security events to file for audit trail
 * @param string $event_type
 * @param array $details
 * @return void
 */
function logSecurityEvent(string $event_type, array $details = []): void {
    $temp_dir = __DIR__ . '/temp';
    $log_file = $temp_dir . '/security_log.txt';
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = getClientIP();
    $details_json = json_encode($details);
    
    $log_entry = "[{$timestamp}] IP: {$ip} | Event: {$event_type} | Details: {$details_json}\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Format seconds to readable duration
 * @param int $seconds
 * @return string
 */
function formatDuration(int $seconds): string {
    if ($seconds < 60) {
        return $seconds . ' detik';
    }
    $minutes = floor($seconds / 60);
    return $minutes . ' menit';
}
?>
