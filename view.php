<!DOCTYPE html>
<html>
<head>
    <title>CSV Core Dashboard</title>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f8f9fa; color: #333; overflow: hidden; }
        .nav { display: flex; justify-content: space-between; align-items: center; background: #212529; color: #fff; padding: 7px 18px; }
        .container { max-width: 1400px; height: calc(100vh - 52px); box-sizing: border-box; margin: 10px auto 0; padding: 0 10px; overflow: hidden; }
        .box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 280px; margin: 100px auto; }
        .panels-split { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 8px; }
        .users-list-wide { width: 100%; box-sizing: border-box; }
        .form-card { background: #fff; padding: 12px; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #ddd; margin-bottom: 10px; }
        .form-row { display: flex; margin-bottom: 7px; align-items: center; }
        .form-row label { width: 125px; font-weight: bold; color: #495057; font-size: 12px; }
        .form-row input, .form-row textarea, .form-row select { flex: 1; padding: 5px; border: 1px solid #ced4da; border-radius: 4px; font-size: 12px; box-sizing: border-box; }
        .btn-sub { background: #4e73df; color: #fff; border: none; padding: 6px 10px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; }
        .list-scroll { height: 180px; overflow: auto; border: 1px solid #ddd; border-radius: 6px; margin-top: 6px; background: #fff; }
        .users-list-scroll { overflow-x: hidden; overflow-y: auto; }
        .hosts-list-scroll { height: 220px; overflow: auto; }
        .search-row { display: flex; align-items: center; gap: 8px; margin: 0 0 6px; }
        .search-row label { font-weight: bold; color: #495057; font-size: 12px; }
        .search-row input { flex: 1; max-width: 420px; padding: 5px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; font-size: 12px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; background: #fff; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 0; margin-top: 0; }
        th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 12px; overflow-wrap: anywhere; }
        .users-list-scroll table { table-layout: fixed; }
        .users-list-scroll th:first-child, .users-list-scroll td:first-child { white-space: nowrap; overflow-wrap: normal; }
        .users-list-scroll th:last-child, .users-list-scroll td:last-child { white-space: nowrap; overflow-wrap: normal; }
        thead { position: sticky; top: 0; z-index: 2; }
        thead th { background: #f8f9fc; color: #4e73df; font-weight: bold; box-shadow: 0 2px 3px rgba(0,0,0,0.12); }
        tr:hover { background: #f8f9fc; }
        .alert { padding:10px; border-radius:4px; margin-bottom:15px; font-weight:bold; }
        .form-card h3 { margin: 0 0 8px 0 !important; padding-bottom: 5px !important; font-size: 15px; }
        .users-list-wide h3, .panels-split > div > h3 { margin: 6px 0 !important; font-size: 15px; }
        .users-list-wide > div:last-child, .panels-split > div > .list-scroll + div { margin-top: 6px !important; }
    </style>
</head>
<body>
<!-- Show the login form until index.php confirms an authenticated session. -->
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
    <!-- Authenticated users get a two-column dashboard for users and hosts. -->
    <div class="nav">
        <h2 style="margin:0; font-size:18px;">System Infrastructure Database Dashboard</h2>
        <a href="index.php?action=logout" style="background:#dc3545; color:#fff; padding:6px 12px; text-decoration:none; border-radius:4px; font-weight:bold; font-size:13px;">Logout</a>
    </div>
    <div class="container">
        <?php echo $message; ?>
        
        <div class="panels-split">
            <!-- User management: the form supports both adding and editing rows. -->
            <div>
                <div class="form-card">
                    <h3 style="margin:0 0 15px 0; color:#4e73df; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php echo $edit_user ? '📝 Edit User' : '➕ Add User'; ?></h3>
                    <form method="POST">
                        <!-- These fields tell index.php which CSV and row to update. -->
                        <input type="hidden" name="target_db" value="users">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="index" value="<?php echo $edit_user ? $row_index : -1; ?>">
                        <div class="form-row"><label>Username</label><input type="text" name="username" value="<?php echo htmlspecialchars($edit_user[0] ?? ''); ?>" required></div>
                        <div class="form-row"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($edit_user[3] ?? ''); ?>" required></div>
                        <div class="form-row"><label>UID</label><input type="number" name="uid" value="<?php echo htmlspecialchars($edit_user ? $edit_user[1] : $next_uid); ?>" required></div>
                        <div class="form-row"><label>GID</label><input type="number" name="gid" value="<?php echo htmlspecialchars($edit_user ? $edit_user[2] : $next_gid); ?>" required></div>
                        <div class="form-row"><label>Home Directory</label><input type="text" name="home-directory" value="<?php echo htmlspecialchars($edit_user[4] ?? ''); ?>" required></div>
                        <div class="form-row" style="align-items:flex-start;"><label>Public Key</label><textarea name="public-key" rows="3" style="font-family:monospace;" required><?php echo htmlspecialchars($edit_user[5] ?? ''); ?></textarea></div>
                        
                        <div class="form-row">
                            <label>Authorized Host</label>
                            <select name="authorized-host" required>
                                <option value="">-- Select Host --</option>
                                <option value="*" <?php echo (isset($edit_user) && trim($edit_user[6]) === '*') ? 'selected' : ''; ?>>* (All Hosts)</option>
                                <?php foreach ($hosts as $h): ?>
                                    <?php 
                                    if (strtolower(trim($h[0] ?? '')) === 'machine-group' || empty(trim($h[0] ?? ''))) continue;
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
                <div class="users-list-wide">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
                    <h3 style="margin: 1em 0;">Users List (users.csv)</h3>
                    <div style="display:flex; gap:8px;">
                        <a href="index.php?raw=users" target="_blank" rel="noopener" class="btn-sub" style="background:#6c757d; text-decoration:none;">View Raw CSV</a>
                        <a href="index.php?raw=users&amp;edit=1" target="_blank" rel="noopener" class="btn-sub" style="background:#2e59d9; text-decoration:none;">Edit Raw CSV</a>
                    </div>
                </div>
                <div class="search-row">
                    <label for="user-search">Search Users</label>
                    <input type="search" id="user-search" placeholder="Username, UID, email, home directory, or host">
                </div>
                <!-- Existing users are displayed with edit and delete actions. -->
                <div class="list-scroll users-list-scroll">
                <table>
                    <thead><tr><th>User</th><th>UID/GID</th><th>Email</th><th>Auth Host</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (count($users) <= 1): ?>
                            <tr><td colspan="5" style="text-align:center; color:#6c757d;">Empty database.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $idx => $u): if (strtolower(trim($u[0] ?? '')) === 'username') continue; ?>
                                <tr class="user-row">
                                    <td style="font-weight:bold; color:#4e73df;"><?php echo htmlspecialchars($u[0]); ?></td>
                                    <td><?php echo htmlspecialchars($u[1] . '/' . $u[2]); ?></td>
                                    <td><?php echo htmlspecialchars($u[3]); ?></td>
                                    <td><span style="background:#e9ecef; padding:2px 6px; border-radius:4px; font-family:monospace;"><?php echo htmlspecialchars($u[6]); ?></span></td>
                                    <td style="white-space:nowrap;">
                                        <a href="index.php?user_detail=<?php echo $idx; ?>" target="_blank" rel="noopener" style="background:#17a2b8; color:#fff; display:inline-block; padding:4px 6px; border-radius:4px; text-decoration:none; font-weight:bold; font-size:11px;">View</a>
                                        <form method="POST" style="display:inline;"><input type="hidden" name="target_db" value="users"><input type="hidden" name="index" value="<?php echo $idx; ?>"><input type="hidden" name="action" value="edit"><button type="submit" style="background:#f6c23e; border:none; padding:4px 6px; border-radius:4px; cursor:pointer; font-weight:bold; font-size:11px;">Edit</button></form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user?');"><input type="hidden" name="target_db" value="users"><input type="hidden" name="index" value="<?php echo $idx; ?>"><input type="hidden" name="action" value="delete"><button type="submit" style="background:#e74a3b; color:#fff; border:none; padding:4px 6px; border-radius:4px; cursor:pointer; font-weight:bold; font-size:11px;">Del</button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
                    <form method="POST">
                        <input type="hidden" name="target_db" value="users">
                        <input type="hidden" name="action" value="publish">
                        <button type="submit" class="btn-sub" style="background:#2e59d9;">Publish users-block.txt</button>
                    </form>
                    <a href="index.php?raw=users-block" target="_blank" rel="noopener" class="btn-sub" style="background:#6c757d; text-decoration:none;">View Raw users-block.txt</a>
                    <a href="index.php?raw=users-block&amp;edit=1" target="_blank" rel="noopener" class="btn-sub" style="background:#2e59d9; text-decoration:none;">Edit Raw users-block.txt</a>
                </div>
                </div>
            </div>

            <!-- Host management: member-list is displayed but calculated by index.php. -->
            <div>
                <div class="form-card">
                    <h3 style="margin:0 0 15px 0; color:#2e59d9; border-bottom:1px solid #ddd; padding-bottom:8px;"><?php echo $edit_host ? '📝 Edit Host Group' : '➕ Add New Host Group'; ?></h3>
                    <form method="POST">
                        <!-- These fields tell index.php which CSV and row to update. -->
                        <input type="hidden" name="target_db" value="hosts">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="index" value="<?php echo $edit_host ? $row_index : -1; ?>">
                        <div class="form-row"><label>Machine-Group</label><input type="text" name="machine-group" value="<?php echo htmlspecialchars($edit_host[0] ?? ''); ?>" placeholder="e.g. host-domain-com" required></div>
                        <div class="form-row"><label>Group ID</label><input type="number" name="group-id" min="5000" value="<?php echo htmlspecialchars($edit_host ? $edit_host[1] : $next_group_id); ?>" required></div>
                        <div class="form-row"><label>Member List</label><input type="text" name="member-list" value="<?php echo htmlspecialchars($edit_host[2] ?? ''); ?>" placeholder="Auto-calculated from users" readonly style="background:#e9ecef; cursor:not-allowed;"></div>
                        <div style="margin-left:140px; margin-top:10px;">
                            <button type="submit" class="btn-sub" style="background:#2e59d9;">Save Host</button>
                            <?php if ($edit_host): ?> <a href="index.php?target_db=hosts" style="margin-left:10px; color:#6c757d; text-decoration:none; font-size:14px;">Cancel</a><?php endif; ?>
                        </div>
                    </form>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
                    <h3 style="margin: 1em 0;">Hosts List (hosts.csv)</h3>
                    <div style="display:flex; gap:8px;">
                        <a href="index.php?raw=hosts" target="_blank" rel="noopener" class="btn-sub" style="background:#6c757d; text-decoration:none;">View Raw CSV</a>
                        <a href="index.php?raw=hosts&amp;edit=1" target="_blank" rel="noopener" class="btn-sub" style="background:#2e59d9; text-decoration:none;">Edit Raw CSV</a>
                    </div>
                </div>
                <!-- Existing hosts are displayed with edit and delete actions. -->
                <div class="list-scroll hosts-list-scroll">
                <table>
                    <thead><tr><th>Machine-Group</th><th>Group ID</th><th>Members</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (count($hosts) <= 1): ?>
                            <tr><td colspan="4" style="text-align:center; color:#6c757d;">Empty database.</td></tr>
                        <?php else: ?>
                            <?php foreach ($hosts as $idx => $h): if (strtolower(trim($h[0] ?? '')) === 'machine-group') continue; ?>
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
                <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
                    <form method="POST">
                        <input type="hidden" name="target_db" value="hosts">
                        <input type="hidden" name="action" value="publish">
                        <button type="submit" class="btn-sub" style="background:#2e59d9;">Publish</button>
                    </form>
                    <a href="index.php?raw=hosts-block" target="_blank" rel="noopener" class="btn-sub" style="background:#6c757d; text-decoration:none;">View Raw hosts-block.txt</a>
                    <a href="index.php?raw=hosts-block&amp;edit=1" target="_blank" rel="noopener" class="btn-sub" style="background:#2e59d9; text-decoration:none;">Edit Raw hosts-block.txt</a>
                </div>
            </div>
        </div>

    </div>
<?php endif; ?>
<script>
    document.getElementById('user-search')?.addEventListener('input', function () {
        const query = this.value.trim().toLowerCase();
        document.querySelectorAll('.user-row').forEach(function (row) {
            row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
        });
    });
</script>
</body>
</html>