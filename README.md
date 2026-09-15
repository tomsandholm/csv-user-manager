# CSV User Manager

An authenticated PHP dashboard for managing users and hosts stored in CSV files. `index.php` handles login, CSV reads/writes, and host membership sync. `view.php` is the HTML for the login page and the two-column users/hosts UI.

The browser title is **CSV Core Dashboard**. After login the nav bar reads **System Infrastructure Database Dashboard**.

## Files

| File | Role |
| --- | --- |
| `index.php` | Controller: session, login/logout, CSV I/O, membership sync, then includes `view.php` |
| `view.php` | Login form and authenticated dashboard (users panel on the left, hosts panel on the right) |
| `users.csv` | User records |
| `hosts.csv` | Host records and calculated comma-separated member lists |
| `check-remote-groups.sh` | Uses SSH to check FQDN-derived machine groups and configured members on each host |
| `Makefile` | Copies `index.php`, `view.php`, `users.csv`, and `hosts.csv` to `/var/www/html` |

## Authentication

The dashboard is not shown and CSV files are not modified until the session is authenticated.

Credentials currently defined in `index.php`:

```text
Username: admin
Password: secret123
```

Change `ADMIN_USER` and `ADMIN_PASS` in `index.php` before deploying to a shared or production host. Failed logins show **Invalid credentials.** Use **Logout** to end the session (`index.php?action=logout`).

## CSV formats

`read_csv()` pads short rows and truncates extra columns to the expected width. Header rows are skipped in the UI when the first cell is `username` or `fqdn`.

If a CSV file is missing, `index.php` creates an empty file with `touch`. The checked-in files already include headers.

### `users.csv`

```text
username,uid,gid,email,home-directory,public-key,authorized-host
```

Current records (keys abbreviated):

```csv
username,uid,gid,email,home-directory,public-key,authorized-host
tsandholm,3000,3000,tom.sandholm@gmail.com,/share/home/tsandholm,ssh-rsa AAA...,*
kat,3001,3001,tom.sandholm@gmail.com,/share/home/kat,ssh-rsa AAA...,tom4.tsand.org
mary,3002,3002,tom.sandholm@gmail.com,/share/home/mary,ssh-rsa AAA...,tom2.tsand.org
mikey,3003,3003,tom.sandholm@gmail.com,/share/home/mikey,ssh-rsa AAA...,tom2.tsand.org
iggy,3004,3004,tom.sandholm@gmail.com,/share/home/iggy,ssh-rsa AAA...,tom3.tsand.org
```

`authorized-host` is either `*` (every host) or one FQDN from `hosts.csv`. The add/edit form dropdown is built from those host rows plus `*`.

New users default UID and GID to one higher than the highest numeric values in the file (currently `3005`). The form pre-fills those values. If a new-user POST still leaves UID or GID blank, `index.php` applies the same calculation. Editing a user keeps the submitted UID/GID.

### `hosts.csv`

Each row represents a managed machine-group:

```text
machine-group,group-id,member-list
```

Current records:

```csv
fqdn,group-id,member-list
tom1.tsand.org,5000,tsandholm
tom2.tsand.org,5001,"mary,mikey,tsandholm"
tom3.tsand.org,5002,"iggy,tsandholm"
tom4.tsand.org,5003,"kat,tsandholm"
```

`member-list` is calculated, not typed. The form field is read-only. After a user save/delete or a host save, `sync_hosts_members()` rebuilds each host’s list from `users.csv`:

When adding a machine-group, the form defaults Group ID to the next value after the highest numeric `group-id` already assigned in `hosts.csv`. Group IDs start at `5000` when no numeric IDs exist, and the server applies the same fallback when a new machine-group submission leaves the field blank. Editing an existing machine-group preserves its current Group ID unless it is changed explicitly.

## Dashboard features

- Add, edit, and delete users from the **Users List** panel
- View full user details, including Home Directory and Public Key, from the Users List
- Search Users by username, UID/GID, email, home directory, public key, or machine-group
- Add, edit, and delete machine-groups from the **Hosts List** panel
- Edit user identity, contact, SSH key, home directory, and authorized host fields
- Edit machine-group and group ID fields
- Display calculated host member lists
- Synchronize host memberships after user changes
- View or edit the raw `users.csv` and `hosts.csv` contents from their lists
- Suggest the next available UID and GID for new users
- Suggest the next available Group ID for new hosts, starting at `5000`
- Create empty `users.csv` and `hosts.csv` files automatically if they are missing
- Escape displayed CSV values with `htmlspecialchars`

## How a request is handled

1. Start the session and handle logout if requested
2. Process login POST, or stop and render the login form
3. Ensure `users.csv` and `hosts.csv` exist
4. On POST, save or delete the targeted users or hosts row
5. Sync host member lists after user save/delete and after host save
6. Reload both CSVs and include `view.php`

## Quick start

1. Install PHP.
2. From this directory:

```sh
php -S 127.0.0.1:8000
```

3. Open `http://127.0.0.1:8000/index.php`.
4. Sign in with the configured credentials.
5. Manage users on the left and hosts on the right.

## Check remote machine groups

Run the SSH checker from this directory:

```sh
./check-remote-groups.sh
```

The script reads the first column of `hosts.csv` as both the SSH target and remote machine-group name. For example, `tom1-tsand-org` checks the `tom1-tsand-org` group in the remote `/etc/group`.

The Users List and Hosts List provide **View Raw CSV** and **Edit Raw CSV** buttons. Each editor displays the exact file contents in a textarea and saves the contents only when the corresponding **Save ...csv** button is clicked.

It reports whether the group was found and, when present, checks each user in the final `member-list` column individually. The script is report-only by default and does not modify remote systems. An alternate CSV path can be supplied:

```sh
./check-remote-groups.sh /path/to/hosts.csv
```

To append only users that are missing from an existing remote group, use:

```sh
./check-remote-groups.sh --apply
```

Apply mode uses `sudo gpasswd --add` for each missing user and verifies the user appears in the group after each append. It never removes existing members or replaces the complete member list. Missing groups are reported but not created. The member-list column contains usernames; `/etc/group` stores usernames rather than numeric user IDs.

## Deploy

Requires PHP (for example Apache with `mod_php` or PHP-FPM). The web server user must be able to read and write `users.csv` and `hosts.csv` in the same directory as the PHP files.

From this directory:

```sh
make push
```

That copies `index.php`, `view.php`, `users.csv`, and `hosts.csv` into `/var/www/html` (uses `sudo`). It does not copy this README. Then open `index.php` on the web server and sign in.
