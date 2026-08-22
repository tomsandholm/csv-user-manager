<?php
// Define file path
$csvFile = 'users.csv';

// Expected CSV column headers
$headers = ['username', 'uid', 'gid', 'email', 'home-directory', 'public-key', 'authorized-host'];

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

// Handle Form Submission (Updates, Deletions, and New Additions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updatedRows = [];
    
    // 1. Process "Add New User" row FIRST if the username field is filled out
    if (!empty($_POST['new_user']['username'])) {
        $newUser = $_POST['new_user'];
        $updatedRows[] = [
            $newUser['username'],
            $newUser['uid'] ?? '',
            $newUser['gid'] ?? '',
            $newUser['email'] ?? '',
            $newUser['home-directory'] ?? '',
            $newUser['public-key'] ?? '',
            $newUser['authorized-host'] ?? ''
        ];
    }

    // 2. Process existing users and check for deletion tags
    if (isset($_POST['users']) && is_array($_POST['users'])) {
        foreach ($_POST['users'] as $index => $userData) {
            // If the delete checkbox is checked, skip adding this row back to the CSV array
            if (isset($userData['delete']) && $userData['delete'] == '1') {
                continue; 
            }
            
            $updatedRows[] = [
                $userData['username'] ?? '',
                $userData['uid'] ?? '',
                $userData['gid'] ?? '',
                $userData['email'] ?? '',
                $userData['home-directory'] ?? '',
                $userData['public-key'] ?? '',
                $userData['authorized-host'] ?? ''
            ];
        }
    }

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
        } else {
            $message = "<div style='color: red; font-weight: bold; margin-bottom: 15px;'>Could not secure an exclusive lock on the file. Please try again.</div>";
        }
        fclose($handle);
    } else {
        $message = "<div style='color: red; font-weight: bold; margin-bottom: 15px;'>Error writing to CSV file. Check file permissions.</div>";
    }
}

// Pass 1: Read the CSV file to gather existing users and discover unique hostnames
$users = [];
$hosts = ['all', 'none']; // Force 'all' and 'none' options at the top of the collection

if (($handle = fopen($csvFile, 'r')) !== FALSE) {
    if (flock($handle, LOCK_SH)) { // Acquire a shared lock for safe concurrent reading
        $fileHeaders = fgetcsv($handle); // Read and discard header row
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 7) {
                $data = array_pad($data, 7, '');
            }
            
            $userRow = array_combine($headers, $data);
            $users[] = $userRow;
            
            // Dynamically extract unique hostnames into list
            $hostValue = trim($userRow['authorized-host']);
            if (!empty($hostValue) && !in_array($hostValue, $hosts)) {
                $hosts[] = $hostValue;
            }
        }
        flock($handle, LOCK_UN); // Release shared lock
    }
    fclose($handle);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSV User Manager</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f9f9f9; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background: #fff; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        input[type="text"], input[type="email"], select { width: 100%; box-sizing: border-box; padding: 4px; }
        .btn-submit { padding: 10px 20px; background-color: #007BFF; color: white; border: none; cursor: pointer; font-size: 14px; border-radius: 4px; }
        .btn-submit:hover { background-color: #0056b3; }
        .delete-col { text-align: center; width: 60px; }
        .new-user-header { padding: 10px; font-weight: bold; background: #dcdcdc; color: #333; }
        .new-user-row { background-color: #e6f7ff; }
        .existing-user-header { padding: 10px; font-weight: bold; background: #e9e9e9; color: #333; }
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
                    <td><input type="text" name="new_user[username]" placeholder="e.g. bsmith"></td>
                    <td><input type="text" name="new_user[uid]" placeholder="1003"></td>
                    <td><input type="text" name="new_user[gid]" placeholder="1003"></td>
                    <td><input type="email" name="new_user[email]" placeholder="bsmith@example.com"></td>
                    <td><input type="text" name="new_user[home-directory]" placeholder="/home/bsmith"></td>
                    <td><input type="text" name="new_user[public-key]" placeholder="ssh-rsa ..."></td>
                    <td>
                        <select name="new_user[authorized-host]">
                            <?php 
                            foreach ($hosts as $host) {
                                $selected = ($host === 'all') ? 'selected' : '';
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
                        echo '<td><input type="text" name="users['.$index.'][username]" value="' . htmlspecialchars($user['username']) . '" required></td>';
                        echo '<td><input type="text" name="users['.$index.'][uid]" value="' . htmlspecialchars($user['uid']) . '"></td>';
                        echo '<td><input type="text" name="users['.$index.'][gid]" value="' . htmlspecialchars($user['gid']) . '"></td>';
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


