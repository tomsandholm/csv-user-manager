<!DOCTYPE html>
<html>
<head>
    <title>CSV Core Dashboard</title>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f8f9fa; color: #333; padding-bottom: 40px; }
        .nav { display: flex; justify-content: space-between; align-items: center; background: #212529; color: #fff; padding: 10px 25px; }
        .container { max-width: 1200px; margin: 25px auto; padding: 0 15px; }
        .box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 280px; margin: 100px auto; }
        .panels-split { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 20px; }
        .form-card { background: #fff; padding: 20px; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #ddd; margin-bottom: 20px; }
        .form-row { display: flex; margin-bottom: 12px; align-items: center; }
        .form-row label { width: 140px; font-weight: bold; color: #495057; font-size: 14px; }
        .form-row input, .form-row textarea, .form-row select { flex: 1; padding: 7px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
        .btn-sub { background: #4e73df; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #ddd; margin-top: 10px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; }
        th { background: #f8f9fc; color: #4e73df; font-weight: bold; }
        tr:hover { background: #f8f9fc; }
        .alert { padding:10px; border-radius:4px; margin-bottom:15px; font-weight:bold; }
    </style>
</head>
<body>
<?php if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true): ?>
    <div class="box">
        <h2 style="margin:0 0 10px 0; text-align:center;">Admin Login</h2>
        <?php if (!empty($login_error)): ?><p style="color:red; text-align:center;"><?php echo $login_error; ?></p><?php endif; ?>
        <form method="POST">
            <input type="text" name="auth_user" placeholder="Username" style="width:100%; padding:10px; margin-top:10px; box-sizing:border-box;" required autofocus><br>
            <input type="password" name="auth_pass" placeholder="Password" style="width:100%; padding:10px; margin-top:10px; box-sizing:border-box;" required><br>
            <button type="submit" name="login_submit" style="width:100%; padding:10px; margin-top:15px; background:#007bff; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Log In</button>
        </form>
    </div>
<?php else: ?>
    <div class="nav">
        <h2 style="margin:0; font-size:18px;">System Infrastructure Database Dashboard</h2>
        <a href="index.php?action=logout" style="background:#dc3545; color:#fff; padding:6px 12px; text-decoration:none; border-radius:4px; font-weight:bold; font-size:13px;">Logout</a>
    </div>
    <div class="container">
        <?php echo $message; ?>
        
        <div class="panels-split">
            <!-- LEFT PANEL: USERS MANAGEMENT ENGINE -->
            <div>
                <div class="form-card">
                    <h3 style="margin:0 0 15px 0; color:#4e73df; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php echo $edit_user ? '📝 Edit User' : '➕ Add User'; ?></h3>
                    <form method="POST">
                        <input type="hidden" name="target_db" value="users">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="index" value="<?php echo $edit_user ? $row_index : -1; ?>">
                        <div class="form-row"><label>Username</label><input type="text" name="username" value="<?php echo htmlspecialchars($edit_user[0] ?? ''); ?>" required></div>
                        <div class="form-row"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($edit_user[3] ?? ''); ?>" required></div>
                        <div class="form-row"><label>UID</label><input type="number" name="uid" value="<?php echo htmlspecialchars($edit_user[1] ?? ''); ?>" required></div>
                        <div class="form-row"><label>GID</label><input type="number" name="gid" value="<?php echo htmlspecialchars($edit_user[2] ?? ''); ?>" required></div>
                        <div class="form-row"><label>Home Directory</label><input type="text" name="home-directory" value="<?php echo htmlspecialchars($edit_user[4] ?? ''); ?>" required></div>
                        <div class="form-row" style="align-items:flex-start;"><label>Public Key</label><textarea name="public-key" rows="3" style="font-family:monospace;" required><?php echo htmlspecialchars($edit_user[5] ?? ''); ?></textarea></div>
                        
                        <div class="form-row">
                            <label>Authorized Host</label>
                            <select name="authorized-host" required>
                                <option value="">-- Select Host --</option>
                                <option value="*" <?php echo (isset($edit_user) && trim($edit_user[6]) === '*') ? 'selected' : ''; ?>>* (All Hosts)</option>
                                <?php foreach ($hosts as $h): ?>
                                    <?php 
                                    if (strtolower(trim($h[0] ?? '')) === 'fqdn' || empty(trim($h[0] ?? ''))) continue; 
                                    $host_val = trim($h[0]);
                                    $selected = (isset($edit_user) && trim($edit_user[6]) === $host_val) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo htmlspecialchars($host_val); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($host_val); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="margin-left:140px; margin-top:10px;">
                            <button type="submit" class="btn-sub">Save User</button>
                            <?php if ($edit_user): ?> <a href="index.php?target_db=users" style="margin-left:10px; color:#6c757d; text-decoration:none; font-size:14px;">Cancel</a><?php endif; ?>
                        </div>
                    </form>
                </div>
                <h3>Users List (users.csv)</h3>
                <table>
                    <thead><tr><th>User</th><th>UID/GID</th><th>Email</th><th>Public Key</th><th>Auth Host</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (count($users) <= 1): ?>
                            <tr><td colspan="6" style="text-align:center; color:#6c757d;">Empty database.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $idx => $u): if (strtolower(trim($u[0] ?? '')) === 'username') continue; ?>
                                <tr>
                                    <td style="font-weight:bold; color:#4e73df;"><?php echo htmlspecialchars($u[0]); ?></td>
                                    <td><?php echo htmlspecialchars($u[1] . '/' . $u[2]); ?></td>
                                    <td><?php echo htmlspecialchars($u[3]); ?></td>
                                    <td title="<?php echo htmlspecialchars($u[5]); ?>"><?php echo htmlspecialchars(substr($u[5], 0, 10)) . '...'; ?></td>
                                    <td><span style="background:#e9ecef; padding:2px 6px; border-radius:4px; font-family:monospace;"><?php echo htmlspecialchars($u[6]); ?></span></td>
                                    <td style="white-space:nowrap;">
                                        <form method="POST" style="display:inline;"><input type="hidden" name="target_db" value="users"><input type="hidden" name="index" value="<?php echo $idx; ?>"><input type="hidden" name="action" value="edit"><button type="submit" style="background:#f6c23e; border:none; padding:4px 6px; border-radius:4px; cursor:pointer; font-weight:bold; font-size:11px;">Edit</button></form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user?');"><input type="hidden" name="target_db" value="users"><input type="hidden" name="index" value="<?php echo $idx; ?>"><input type="hidden" name="action" value="delete"><button type="submit" style="background:#e74a3b; color:#fff; border:none; padding:4px 6px; border-radius:4px; cursor:pointer; font-weight:bold; font-size:11px;">Del</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- RIGHT PANEL: HOSTS MANAGEMENT ENGINE -->
            <div>
                <div class="form-card">
                    <h3 style="margin:0 0 15px 0; color:#2e59d9; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php echo $edit_host ? '📝 Edit Host' : '➕ Add New Host'; ?></h3>
                    <form method="POST">
                        <input type="hidden" name="target_db" value="hosts">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="index" value="<?php echo $edit_host ? $row_index : -1; ?>">
                        <div class="form-row"><label>FQDN</label><input type="text" name="fqdn" value="<?php echo htmlspecialchars($edit_host[0] ?? ''); ?>" placeholder="e.g. host.domain.com" required></div>
                        <div class="form-row"><label>Group ID</label><input type="number" name="group-id" min="5000" value="<?php echo htmlspecialchars($edit_host[1] ?? '5000'); ?>" required></div>
                        <div class="form-row"><label>Member List</label><input type="text" name="member-list" value="<?php echo htmlspecialchars($edit_host[2] ?? ''); ?>" placeholder="Auto-calculated from users" readonly style="background:#e9ecef; cursor:not-allowed;"></div>
                        <div style="margin-left:140px; margin-top:10px;">
                            <button type="submit" class="btn-sub" style="background:#2e59d9;">Save Host</button>
                            <?php if ($edit_host): ?> <a href="index.php?target_db=hosts" style="margin-left:10px; color:#6c757d; text-decoration:none; font-size:14px;">Cancel</a><?php endif; ?>
                        </div>
                    </form>
                </div>
                <h3>Hosts List (hosts.csv)</h3>
                <table>
                    <thead><tr><th>FQDN</th><th>Group ID</th><th>Members</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (count($hosts) <= 1): ?>
                            <tr><td colspan="4" style="text-align:center; color:#6c757d;">Empty database.</td></tr>
                        <?php else: ?>
                            <?php foreach ($hosts as $idx => $h): if (strtolower(trim($h[0] ?? '')) === 'fqdn') continue; ?>
                                <tr>
                                    <td style="font-weight:bold; color:#2e59d9;"><?php echo htmlspecialchars($h[0]); ?></td>
                                    <td><span style="background:#e8f0fe; padding:2px 6px; border-radius:4px; font-family:monospace;"><?php echo htmlspecialchars($h[1]); ?></span></td>
                                    <td style="font-size:13px; color:#555; max-width:200px; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($h[2]); ?>"><?php echo htmlspecialchars($h[2]); ?></td>
                                    <td style="white-space:nowrap;">
                                        <form method="POST" style="display:inline;"><input type="hidden" name="target_db" value="hosts"><input type="hidden" name="index" value="<?php echo $idx; ?>"><input type="hidden" name="action" value="edit"><button type="submit" style="background:#f6c23e; border:none; padding:4px 6px; border-radius:4px; cursor:pointer; font-weight:bold; font-size:11px;">Edit</button></form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete host?');"><input type="hidden" name="target_db" value="hosts"><input type="hidden" name="index" value="<?php echo $idx; ?>"><input type="hidden" name="action" value="delete"><button type="submit" style="background:#e74a3b; color:#fff; border:none; padding:4px 6px; border-radius:4px; cursor:pointer; font-weight:bold; font-size:11px;">Del</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
<?php endif; ?>
</body>
</html>