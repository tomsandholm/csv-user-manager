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

// Allow authenticated users to inspect or edit raw CSV and generated block files.
if (isset($_GET['raw']) && in_array($_GET['raw'], ['users', 'hosts', 'hosts-block', 'users-block', 'groups-block'], true)) {
    $raw_files = [
        'users' => [$csv_users, 'users.csv'],
        'hosts' => [$csv_hosts, 'hosts.csv'],
        'hosts-block' => ['hosts-block.txt', 'hosts-block.txt'],
        'users-block' => ['users-block.txt', 'users-block.txt'],
        'groups-block' => ['groups-block.txt', 'groups-block.txt'],
    ];
    [$raw_file, $raw_filename] = $raw_files[$_GET['raw']];
    $raw_contents = file_exists($raw_file) ? file_get_contents($raw_file) : '';
    if ($raw_contents === false) {
        http_response_code(500);
        echo 'Unable to read ' . $raw_filename . '.';
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['raw_csv'])) {
        $submitted_contents = (string)$_POST['raw_csv'];
        if (file_put_contents($raw_file, $submitted_contents, LOCK_EX) === false) {
            http_response_code(500);
            echo 'Unable to save ' . $raw_filename . '.';
            exit;
        }
        $raw_contents = $submitted_contents;
        $save_message = $raw_filename . ' saved successfully.';
    }

    if (isset($_GET['edit']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: text/html; charset=UTF-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Edit <?php echo htmlspecialchars($raw_filename); ?></title>
            <style>
                body { font-family: sans-serif; margin: 24px; background: #f8f9fa; color: #333; }
                main { max-width: 1100px; margin: 0 auto; }
                textarea { width: 100%; min-height: 420px; padding: 12px; box-sizing: border-box; font: 14px monospace; }
                .actions { display: flex; gap: 10px; margin-top: 12px; }
                .button { display: inline-block; padding: 9px 14px; border: 0; border-radius: 4px; background: #007bff; color: #fff; text-decoration: none; cursor: pointer; }
                .secondary { background: #6c757d; }
                .success { padding: 10px; margin-bottom: 12px; color: #155724; background: #d4edda; border-radius: 4px; }
            </style>
        </head>
        <body>
            <main>
                <h1>Edit <?php echo htmlspecialchars($raw_filename); ?></h1>
                <?php if (!empty($save_message)): ?>
                    <div class="success"><?php echo htmlspecialchars($save_message); ?></div>
                <?php endif; ?>
                <form method="POST" action="index.php?raw=<?php echo htmlspecialchars($_GET['raw']); ?>&amp;edit=1">
                    <textarea name="raw_csv" spellcheck="false"><?php echo htmlspecialchars($raw_contents); ?></textarea>
                    <div class="actions">
                        <button class="button" type="submit">Save <?php echo htmlspecialchars($raw_filename); ?></button>
                        <a class="button secondary" href="index.php">Back to Dashboard</a>
                    </div>
                </form>
            </main>
        </body>
        </html>
        <?php
        exit;
    }

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $raw_filename . '"');
    echo $raw_contents;
    exit;
}

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

// Render a full user record separately from the compact Users List.
if (isset($_GET['user_detail']) && ctype_digit((string)$_GET['user_detail'])) {
    $detail_index = (int)$_GET['user_detail'];
    $detail_users = read_csv($csv_users, 7);
    $detail_user = $detail_users[$detail_index] ?? null;
    if ($detail_user === null || strtolower(trim($detail_user[0] ?? '')) === 'username') {
        http_response_code(404);
        echo 'User not found.';
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>User Details</title>
        <style>
            body { font-family: sans-serif; margin: 24px; background: #f8f9fa; color: #333; }
            main { max-width: 900px; margin: 0 auto; }
            .card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 24px; }
            dt { font-weight: bold; color: #495057; margin-top: 14px; }
            dd { margin: 4px 0 0; padding: 9px; background: #f1f3f5; border-radius: 4px; overflow-wrap: anywhere; }
            .button { display: inline-block; margin-top: 20px; padding: 9px 14px; border-radius: 4px; background: #6c757d; color: #fff; text-decoration: none; }
        </style>
    </head>
    <body>
        <main>
            <div class="card">
                <h1>User Details</h1>
                <dl>
                    <?php
                    $detail_labels = [
                        'Username', 'UID', 'GID', 'Email',
                        'Home Directory', 'Public Key', 'Authorized Machine-Group'
                    ];
                    foreach ($detail_labels as $detail_field => $detail_label):
                    ?>
                        <dt><?php echo htmlspecialchars($detail_label); ?></dt>
                        <dd><?php echo htmlspecialchars($detail_user[$detail_field] ?? ''); ?></dd>
                    <?php endforeach; ?>
                </dl>
                <a class="button" href="index.php">Back to Dashboard</a>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Return the next integer after the highest numeric value in a users.csv column.
function next_user_id($rows, $column) {
    $highest = 0;
    foreach ($rows as $row) {
        $value = trim($row[$column] ?? '');
        if (ctype_digit($value)) {
            $highest = max($highest, (int)$value);
        }
    }
    return (string)($highest + 1);
}

// Return the next group ID after the highest numeric value in hosts.csv.
function next_host_group_id($rows) {
    $highest = 4999;
    foreach ($rows as $row) {
        $value = trim($row[1] ?? '');
        if (ctype_digit($value)) {
            $highest = max($highest, (int)$value);
        }
    }
    return (string)($highest + 1);
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
        if (strtolower(trim($h[0] ?? '')) === 'machine-group' || empty(trim($h[0] ?? ''))) continue;
        $machine_group = trim($h[0]);
        
        $members = [];
        if (isset($host_mappings[$machine_group])) {
            $members = array_merge($members, $host_mappings[$machine_group]);
        }
        if (isset($host_mappings['*'])) {
            $members = array_merge($members, $host_mappings['*']);
        }
        
        // Keep user members deterministic and duplicate-free, then put ansible and sudo first.
        $members = array_unique($members);
        sort($members);
        $members = array_values(array_filter($members, function ($name) {
            return $name !== 'ansible' && $name !== 'sudo';
        }));
        $hosts[$idx][2] = implode(',', array_merge(['ansible', 'sudo'], $members));
    }
    
    write_csv($csv_hosts, $hosts);
}

// Write an /etc/group block from hosts.csv: machine-groupname:x:gid:member-list
function publish_hosts_block($csv_hosts, $output_file = 'hosts-block.txt') {
    $hosts = read_csv($csv_hosts, 3);
    $lines = [];
    foreach ($hosts as $h) {
        if (strtolower(trim($h[0] ?? '')) === 'machine-group' || trim($h[0] ?? '') === '') {
            continue;
        }
        $group_name = trim($h[0]);
        $gid = trim($h[1] ?? '');
        $members = trim($h[2] ?? '');
        $lines[] = $group_name . ':x:' . $gid . ':' . $members;
    }
    $content = $lines ? implode("\n", $lines) . "\n" : '';
    if (($handle = fopen($output_file, 'w')) === FALSE) {
        return false;
    }
    fwrite($handle, $content);
    fclose($handle);
    return true;
}

// Write a /etc/passwd-format block from users.csv.
function publish_users_block($csv_users, $output_file = 'users-block.txt') {
    if (!is_readable($csv_users)) {
        return false;
    }
    $users = read_csv($csv_users, 7);
    $lines = [];
    foreach ($users as $user) {
        if (strtolower(trim($user[0] ?? '')) === 'username' || trim($user[0] ?? '') === '') {
            continue;
        }
        $username = trim($user[0]);
        $uid = trim($user[1] ?? '');
        $gid = trim($user[2] ?? '');
        $email = trim($user[3] ?? '');
        $home_directory = trim($user[4] ?? '');
        $lines[] = $username . ':x:' . $uid . ':' . $gid . ':' . $email . ':' . $home_directory . ':/bin/bash';
    }
    if (!$lines) {
        return false;
    }
    $content = $lines ? implode("\n", $lines) . "\n" : '';
    $temporary_file = $output_file . '.tmp.' . getmypid();
    if (($handle = fopen($temporary_file, 'x')) === FALSE) {
        return false;
    }
    if (fwrite($handle, $content) === false) {
        fclose($handle);
        unlink($temporary_file);
        return false;
    }
    fclose($handle);
    if (!rename($temporary_file, $output_file)) {
        unlink($temporary_file);
        return false;
    }
    return true;
}

// Write primary user groups in /etc/group format from users-block.txt.
function publish_groups_block($users_block_file, $output_file = 'groups-block.txt') {
    if (!is_readable($users_block_file)) {
        return false;
    }
    $lines = [];
    foreach (file($users_block_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $user_line) {
        $fields = explode(':', $user_line);
        if (count($fields) < 4 || trim($fields[0]) === '' || !ctype_digit(trim($fields[3]))) {
            continue;
        }
        $lines[] = trim($fields[0]) . ':x:' . trim($fields[3]) . ':';
    }
    if (!$lines) {
        return false;
    }
    $temporary_file = $output_file . '.tmp.' . getmypid();
    if (($handle = fopen($temporary_file, 'x')) === false) {
        return false;
    }
    $content = implode("\n", $lines) . "\n";
    if (fwrite($handle, $content) === false) {
        fclose($handle);
        unlink($temporary_file);
        return false;
    }
    fclose($handle);
    if (!rename($temporary_file, $output_file)) {
        unlink($temporary_file);
        return false;
    }
    return true;
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
            if ($row_index < 0) {
                if ($submitted_row[1] === '') {
                    $submitted_row[1] = next_user_id($current_data, 1);
                }
                if ($submitted_row[2] === '') {
                    $submitted_row[2] = next_user_id($current_data, 2);
                }
            }
            if ($row_index >= 0) { $current_data[$row_index] = $submitted_row; } else { $current_data[] = $submitted_row; }
            if (write_csv($csv_users, $current_data)) {
                sync_hosts_members($csv_users, $csv_hosts);
                $message = "<div class='alert' style='color:#155724; background:#d4edda;'>User saved and hosts synced.</div>";
            }
            $row_index = -1;
        }
        elseif ($action === 'publish') {
            if (publish_users_block($csv_users) && publish_groups_block('users-block.txt')) {
                $message = "<div class='alert' style='color:#155724; background:#d4edda;'>Published users-block.txt and groups-block.txt.</div>";
            } else {
                $message = "<div class='alert' style='color:#721c24; background:#f8d7da;'>Could not write users-block.txt or groups-block.txt. Check file permissions and source data.</div>";
            }
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
                trim($_POST['machine-group'] ?? ''),
                trim($_POST['group-id'] ?? ''), 
                trim($_POST['member-list'] ?? '')
            ];
            if ($row_index < 0 && $submitted_row[1] === '') {
                $submitted_row[1] = next_host_group_id($current_data);
            }
            if ($row_index >= 0) { $current_data[$row_index] = $submitted_row; } else { $current_data[] = $submitted_row; }
            if (write_csv($csv_hosts, $current_data)) {
                sync_hosts_members($csv_users, $csv_hosts);
                $message = "<div class='alert' style='color:#155724; background:#d4edda;'>Host saved and members synced from users database.</div>";
            }
            $row_index = -1;
        }
        elseif ($action === 'publish') {
            if (publish_hosts_block($csv_hosts)) {
                $message = "<div class='alert' style='color:#155724; background:#d4edda;'>Published hosts-block.txt.</div>";
            } else {
                $message = "<div class='alert' style='color:#721c24; background:#f8d7da;'>Could not write hosts-block.txt. Check file permissions.</div>";
            }
        }
    }
}

// Reload both datasets after any changes so the dashboard shows current values.
$users = read_csv($csv_users, 7);
$hosts = read_csv($csv_hosts, 3);

// Resolve the row being edited, if the request came from an Edit action.
$edit_user = ($target_db === 'users' && $action === 'edit' && $row_index >= 0 && isset($users[$row_index])) ? $users[$row_index] : null;
$edit_host = ($target_db === 'hosts' && $action === 'edit' && $row_index >= 0 && isset($hosts[$row_index])) ? $hosts[$row_index] : null;

// New-user fields default to the next values after the current users.csv contents.
$next_uid = next_user_id($users, 1);
$next_gid = next_user_id($users, 2);
$next_group_id = next_host_group_id($hosts);

// Render the login form or authenticated dashboard.
include 'view.php';
?>