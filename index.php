<?php
// Define file path
$csvFile = 'users.csv';

// Expected CSV column headers
$headers = ['username', 'uid', 'gid', 'email', 'home-directory', 'public-key', 'authorized-host'];

function trim_value($value) {
    return trim((string)$value);
}

function row_from_post($userData) {
    return [
        trim_value($userData['username'] ?? ''),
        trim_value($userData['uid'] ?? ''),
        trim_value($userData['gid'] ?? ''),
        trim_value($userData['email'] ?? ''),
        trim_value($userData['home-directory'] ?? ''),
        trim_value($userData['public-key'] ?? ''),
        trim_value($userData['authorized-host'] ?? '')
    ];
}

function collect_hosts($users) {
    $hosts = ['all', 'none'];
    foreach ($users as $user) {
        $hostValue = trim_value($user['authorized-host'] ?? '');
        if ($hostValue !== '' && !in_array($hostValue, $hosts, true)) {
            $hosts[] = $hostValue;
        }
    }
    return $hosts;
}

// username, uid, and gid must be present and unique among rows that will be saved
function identity_errors($rows) {
    $errors = [];
    $invalidFields = ['username' => [], 'uid' => [], 'gid' => []];
    $fields = ['username' => 0, 'uid' => 1, 'gid' => 2];

    foreach ($fields as $name => $index) {
        $counts = [];
        $missing = false;
        foreach ($rows as $row) {
            $value = trim_value($row[$index] ?? '');
            if ($value === '') {
                $missing = true;
                $invalidFields[$name][''] = true;
                continue;
            }
            if (!isset($counts[$value])) {
                $counts[$value] = 0;
            }
            $counts[$value]++;
        }
        if ($missing) {
            $errors[] = "Each user must have a {$name}.";
        }
        $duplicates = [];
        foreach ($counts as $value => $count) {
            if ($count > 1) {
                $duplicates[] = $value;
                $invalidFields[$name][$value] = true;
            }
        }
        if ($duplicates) {
            $errors[] = "Duplicate {$name} values are not allowed: " . implode(', ', $duplicates) . '.';
        }
    }

    return [$errors, $invalidFields];
}

function field_error_class($invalidFields, $field, $value) {
    $value = trim_value($value);
    return isset($invalidFields[$field][$value]) ? ' class="field-error"' : '';
}

// Create mock file with headers if it does not exist
if (!file_exists($csvFile)) {
    $handle = fopen($csvFile, 'w');
    if ($handle !== FALSE) {
        if (flock($handle, LOCK_EX)) { // Exclusive lock for writing
            fputcsv($handle, $headers);
            fputcsv($handle, ['jdoe', '1001', '1001', 'jdoe@example.com', '/home/jdoe', 'ssh-rsa AAA...', 'server1.local']);
            fputcsv($handle, ['asmith', '1002', '1002', 'asmith@example.com', '/home/asmith', 'ssh-ed25519 AAA...', 'server2.local']);
            flock($handle, LOCK_UN); // Release lock
        }
        fclose($handle);
    }
}

$message = "";
$invalidFields = ['username' => [], 'uid' => [], 'gid' => []];
$reloadFromPost = false;
$newUserForm = [
    'username' => '',
    'uid' => '',
    'gid' => '',
    'email' => '',
    'home-directory' => '',
    'public-key' => '',
    'authorized-host' => 'all'
];

// Handle Form Submission (Updates, Deletions, and New Additions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updatedRows = [];
    $postedNewUser = is_array($_POST['new_user'] ?? null) ? $_POST['new_user'] : [];
    $newUserForm = array_merge($newUserForm, $postedNewUser);

    // 1. Process "Add New User" row FIRST if the username field is filled out
    if (trim_value($newUserForm['username']) !== '') {
        $updatedRows[] = row_from_post($newUserForm);
    }

    // 2. Process existing users and check for deletion tags
    $postedExisting = [];
    if (isset($_POST['users']) && is_array($_POST['users'])) {
        foreach ($_POST['users'] as $index => $userData) {
            // If the delete checkbox is checked, skip adding this row back to the CSV array
            if (isset($userData['delete']) && $userData['delete'] == '1') {
                continue;
            }

            $row = row_from_post($userData);
            $updatedRows[] = $row;
            $postedExisting[] = array_combine($headers, $row);
        }
    }

    list($identityErrors, $invalidFields) = identity_errors($updatedRows);

    if ($identityErrors) {
        $items = '';
        foreach ($identityErrors as $error) {
            $items .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        $message = "<div class='error-box'>CSV file was not updated:<ul>{$items}</ul></div>";
        $reloadFromPost = true;
        $users = $postedExisting;
        $hosts = collect_hosts($users);
        $newHost = trim_value($newUserForm['authorized-host']);
        if ($newHost !== '' && !in_array($newHost, $hosts, true)) {
            $hosts[] = $newHost;
        }
    } else {
        // Rewrite the CSV file safely with exclusive locking
        if (($handle = fopen($csvFile, 'c')) !== FALSE) {
            if (flock($handle, LOCK_EX)) { // Acquire an exclusive lock
                ftruncate($handle, 0);   // Clear the file content now that we own the lock
                rewind($handle);         // Move pointer to the beginning

                fputcsv($handle, $headers); // Re-write headers first
                foreach ($updatedRows as $row) {
                    fputcsv($handle, $row);
                }
                fflush($handle);         // Flush output before releasing lock
                flock($handle, LOCK_UN); // Release lock explicitly
                $message = "<div style='color: green; font-weight: bold; margin-bottom: 15px;'>CSV file updated successfully!</div>";
                $newUserForm = [
                    'username' => '',
                    'uid' => '',
                    'gid' => '',
                    'email' => '',
                    'home-directory' => '',
                    'public-key' => '',
                    'authorized-host' => 'all'
                ];
            } else {
                $message = "<div style='color: red; font-weight: bold; margin-bottom: 15px;'>Could not secure an exclusive lock on the file. Please try again.</div>";
            }
            fclose($handle);
        } else {
            $message = "<div style='color: red; font-weight: bold; margin-bottom: 15px;'>Error writing to CSV file. Check file permissions.</div>";
        }
    }
}

// Pass 1: Read the CSV file to gather existing users and discover unique hostnames
if (!$reloadFromPost) {
    $users = [];
    $hosts = collect_hosts([]);

    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        if (flock($handle, LOCK_SH)) { // Acquire a shared lock for safe concurrent reading
            $fileHeaders = fgetcsv($handle); // Read and discard header row

            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) < 7) {
                    $data = array_pad($data, 7, '');
                }

                $userRow = array_combine($headers, $data);
                $users[] = $userRow;
            }
            flock($handle, LOCK_UN); // Release shared lock
        }
        fclose($handle);
    }
    $hosts = collect_hosts($users);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSV User Manager</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; padding-bottom: 80px; background-color: #f9f9f9; }
        /* separate borders are required for position:sticky on <th> to work */
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; background: #fff; }
        th, td { border-bottom: 1px solid #ccc; border-right: 1px solid #ccc; padding: 8px; text-align: left; }
        th:first-child, td:first-child { border-left: 1px solid #ccc; }
        thead th {
            border-top: 1px solid #ccc;
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.12);
        }
        input[type="text"], input[type="email"], select { width: 100%; box-sizing: border-box; padding: 4px; }
        .save-float {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 20;
        }
        .btn-submit {
            padding: 12px 24px;
            background-color: #007BFF;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }
        .btn-submit:hover { background-color: #0056b3; }
        .delete-col { text-align: center; width: 60px; }
        .new-user-header { padding: 10px; font-weight: bold; background: #dcdcdc; color: #333; }
        .new-user-row { background-color: #e6f7ff; }
        .existing-user-header { padding: 10px; font-weight: bold; background: #e9e9e9; color: #333; }
        .field-error { outline: 2px solid #c00; background-color: #ffe6e6; }
        .error-box { color: #c00; font-weight: bold; margin-bottom: 15px; }
        .error-box ul { margin: 8px 0 0 20px; }
    </style>
</head>
<body>

    <h2>CSV User Management System</h2>
    
    <?php echo $message; ?>

    <form method="POST" action="">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>UID</th>
                    <th>GID</th>
                    <th>Email</th>
                    <th>Home Directory</th>
                    <th>Public Key</th>
                    <th>Authorized Host</th>
                    <th class="delete-col">Delete</th>
                </tr>
            </thead>
            <tbody>
                <!-- Create/Add New User Row Entry Placement at the TOP -->
                <tr class="new-user-row">
                    <td colspan="8" class="new-user-header">Add New User Entry:</td>
                </tr>
                <tr class="new-user-row">
                    <td><input type="text" name="new_user[username]" placeholder="e.g. bsmith" value="<?php echo htmlspecialchars($newUserForm['username']); ?>"<?php echo field_error_class($invalidFields, 'username', $newUserForm['username']); ?>></td>
                    <td><input type="text" name="new_user[uid]" placeholder="1003" value="<?php echo htmlspecialchars($newUserForm['uid']); ?>"<?php echo field_error_class($invalidFields, 'uid', $newUserForm['uid']); ?>></td>
                    <td><input type="text" name="new_user[gid]" placeholder="1003" value="<?php echo htmlspecialchars($newUserForm['gid']); ?>"<?php echo field_error_class($invalidFields, 'gid', $newUserForm['gid']); ?>></td>
                    <td><input type="email" name="new_user[email]" placeholder="bsmith@example.com" value="<?php echo htmlspecialchars($newUserForm['email']); ?>"></td>
                    <td><input type="text" name="new_user[home-directory]" placeholder="/home/bsmith" value="<?php echo htmlspecialchars($newUserForm['home-directory']); ?>"></td>
                    <td><input type="text" name="new_user[public-key]" placeholder="ssh-rsa ..." value="<?php echo htmlspecialchars($newUserForm['public-key']); ?>"></td>
                    <td>
                        <select name="new_user[authorized-host]">
                            <?php 
                            foreach ($hosts as $host) {
                                $selected = ($host === $newUserForm['authorized-host']) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($host) . '" ' . $selected . '>';
                                echo htmlspecialchars($host);
                                echo '</option>';
                            }
                            ?>
                        </select>
                    </td>
                    <td class="delete-col" style="color: #999; font-size: 11px;">N/A</td>
                </tr>

                <!-- Section Divider for Existing Records -->
                <tr>
                    <td colspan="8" class="existing-user-header">Existing Users:</td>
                </tr>

                <!-- Existing Users Loop -->
                <?php 
                if (empty($users)) {
                    echo '<tr><td colspan="8" style="text-align: center; color: #666;">No active users found.</td></tr>';
                } else {
                    foreach ($users as $index => $user) {
                        echo '<tr>';
                        echo '<td><input type="text" name="users['.$index.'][username]" value="' . htmlspecialchars($user['username']) . '" required' . field_error_class($invalidFields, 'username', $user['username']) . '></td>';
                        echo '<td><input type="text" name="users['.$index.'][uid]" value="' . htmlspecialchars($user['uid']) . '"' . field_error_class($invalidFields, 'uid', $user['uid']) . '></td>';
                        echo '<td><input type="text" name="users['.$index.'][gid]" value="' . htmlspecialchars($user['gid']) . '"' . field_error_class($invalidFields, 'gid', $user['gid']) . '></td>';
                        echo '<td><input type="email" name="users['.$index.'][email]" value="' . htmlspecialchars($user['email']) . '"></td>';
                        echo '<td><input type="text" name="users['.$index.'][home-directory]" value="' . htmlspecialchars($user['home-directory']) . '"></td>';
                        echo '<td><input type="text" name="users['.$index.'][public-key]" value="' . htmlspecialchars($user['public-key']) . '"></td>';
                        echo '<td>';
                        echo '<select name="users['.$index.'][authorized-host]">';
                        foreach ($hosts as $host) {
                            $selected = ($user['authorized-host'] === $host) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($host) . '" ' . $selected . '>';
                            echo htmlspecialchars($host);
                            echo '</option>';
                        }
                        echo '</select>';
                        echo '</td>';
                        echo '<td class="delete-col">';
                        echo '<input type="checkbox" name="users['.$index.'][delete]" value="1">';
                        echo '</td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
        <div class="save-float">
            <button type="submit" class="btn-submit">Save Changes</button>
        </div>
    </form>
</body>
</html>
