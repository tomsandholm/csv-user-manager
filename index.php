<?php
session_start();
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'secret123');

// Log out by clearing the session before redirecting back to the login page.
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset(); session_destroy(); header("Location: index.php"); exit;
}

$login_error = '';
// Authenticate before exposing the dashboard or allowing CSV changes.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    if (trim($_POST['auth_user'] ?? '') === ADMIN_USER && trim($_POST['auth_pass'] ?? '') === ADMIN_PASS) {
        $_SESSION['authenticated'] = true; header("Location: index.php"); exit;
    } else { $login_error = "Invalid credentials."; }
}

// The view contains the login form, so unauthenticated requests stop here.
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    include 'view.php'; exit;
}

$csv_users = 'users.csv';
$csv_hosts = 'hosts.csv';
// Create empty data files on first use so the dashboard can load safely.
if (!file_exists($csv_users)) { touch($csv_users); }
if (!file_exists($csv_hosts)) { touch($csv_hosts); }

// Read a CSV file and normalize every row to the expected column count.
function read_csv($file, $expected_cols) {
    $rows = [];
    if (($handle = fopen($file, 'r')) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rows[] = array_slice(count($data) < $expected_cols ? array_pad($data, $expected_cols, '') : $data, 0, $expected_cols);
        }
        fclose($handle);
    }
    return $rows;
}

// Replace a CSV file with the supplied rows.
function write_csv($file, $rows) {
    if (($handle = fopen($file, 'w')) !== FALSE) {
        foreach ($rows as $row) { fputcsv($handle, $row); }
        fclose($handle); return true;
    }
    return false;
}

// Recalculate each host's member list from the current user authorized-host mappings.
function sync_hosts_members($csv_users, $csv_hosts) {
    $users = read_csv($csv_users, 7);
    $hosts = read_csv($csv_hosts, 3);
    
    // Build a lookup of users grouped by their selected host.
    $host_mappings = [];
    foreach ($users as $u) {
        if (strtolower(trim($u[0] ?? '')) === 'username' || empty(trim($u[0] ?? ''))) continue;
        $username = trim($u[0]);
        $auth_host = trim($u[6]);
        
        if (!empty($auth_host)) {
            $host_mappings[$auth_host][] = $username;
        }
    }
    
    // Include both explicitly assigned users and users assigned to every host.
    foreach ($hosts as $idx => $h) {
        if (strtolower(trim($h[0] ?? '')) === 'fqdn' || empty(trim($h[0] ?? ''))) continue;
        $fqdn = trim($h[0]);
        
        $members = [];
        if (isset($host_mappings[$fqdn])) {
            $members = array_merge($members, $host_mappings[$fqdn]);
        }
        if (isset($host_mappings['*'])) {
            $members = array_merge($members, $host_mappings['*']);
        }
        
        // Keep the generated member list deterministic and duplicate-free.
        $members = array_unique($members);
        sort($members);
        
        $hosts[$idx][2] = implode(',', $members);
    }
    
    write_csv($csv_hosts, $hosts);
}

$message = ''; 
$target_db = $_POST['target_db'] ?? ($_GET['target_db'] ?? 'users');
$action = $_POST['action'] ?? ''; 
$row_index = isset($_POST['index']) ? (int)$_POST['index'] : -1;

// Route submitted changes to either the users or hosts CSV.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login_submit'])) {
    if ($target_db === 'users') {
        $current_data = read_csv($csv_users, 7);
        // Delete the selected row, then refresh calculated host memberships.
        if ($action === 'delete' && $row_index >= 0) {
            unset($current_data[$row_index]); $current_data = array_values($current_data);
            if (write_csv($csv_users, $current_data)) {
                sync_hosts_members($csv_users, $csv_hosts);
                $message = "<div class='alert' style='color:#155724; background:#d4edda;'>User deleted and hosts synced.</div>";
            }
        } 
        elseif ($action === 'save') {
            // Replace the selected row when editing, or append a new row.
            $submitted_row = [
                trim($_POST['username'] ?? ''), trim($_POST['uid'] ?? ''), trim($_POST['gid'] ?? ''),
                trim($_POST['email'] ?? ''), trim($_POST['home-directory'] ?? ''), 
                trim($_POST['public-key'] ?? ''), trim($_POST['authorized-host'] ?? '')
            ];
            if ($row_index >= 0) { $current_data[$row_index] = $submitted_row; } else { $current_data[] = $submitted_row; }
            if (write_csv($csv_users, $current_data)) {
                sync_hosts_members($csv_users, $csv_hosts);
                $message = "<div class='alert' style='color:#155724; background:#d4edda;'>User saved and hosts synced.</div>";
            }
            $row_index = -1;
        }
    } 
    elseif ($target_db === 'hosts') {
        $current_data = read_csv($csv_hosts, 3);
        // Host deletion does not require a membership recalculation.
        if ($action === 'delete' && $row_index >= 0) {
            unset($current_data[$row_index]); $current_data = array_values($current_data);
            if (write_csv($csv_hosts, $current_data)) $message = "<div class='alert' style='color:#155724; background:#d4edda;'>Host deleted successfully.</div>";
        } 
        elseif ($action === 'save') {
            // Replace the selected row when editing, or append a new host.
            $submitted_row = [
                trim($_POST['fqdn'] ?? ''), 
                trim($_POST['group-id'] ?? ''), 
                trim($_POST['member-list'] ?? '')
            ];
            if ($row_index >= 0) { $current_data[$row_index] = $submitted_row; } else { $current_data[] = $submitted_row; }
            if (write_csv($csv_hosts, $current_data)) {
                sync_hosts_members($csv_users, $csv_hosts);
                $message = "<div class='alert' style='color:#155724; background:#d4edda;'>Host saved and members synced from users database.</div>";
            }
            $row_index = -1;
        }
    }
}

// Reload both datasets after any changes so the dashboard shows current values.
$users = read_csv($csv_users, 7);
$hosts = read_csv($csv_hosts, 3);

// Resolve the row being edited, if the request came from an Edit action.
$edit_user = ($target_db === 'users' && $action === 'edit' && $row_index >= 0 && isset($users[$row_index])) ? $users[$row_index] : null;
$edit_host = ($target_db === 'hosts' && $action === 'edit' && $row_index >= 0 && isset($hosts[$row_index])) ? $hosts[$row_index] : null;

// Render the login form or authenticated dashboard.
include 'view.php';
?>