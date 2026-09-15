# CSV User Manager

An authenticated PHP dashboard for managing users and hosts stored in CSV files. `index.php` handles login, CSV reads/writes, and host membership sync. `view.php` is the HTML for the login page and the two-column users/hosts UI.

The browser title is **CSV Core Dashboard**. After login the nav bar reads **System Infrastructure Database Dashboard**.

## Files

| File | Role |
| --- | --- |
| `index.php` | Controller: session, login/logout, CSV I/O, membership sync, then includes `view.php` |
| `view.php` | Login form and authenticated dashboard (users panel on the left, hosts panel on the right) |
| `users.csv` | User records |
| `hosts.csv` | Host records and calculated member lists |
| `Makefile` | `make push` copies `index.php`, `view.php`, `users.csv`, and `hosts.csv` into `/var/www/html` |

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

```text
fqdn,group-id,member-list
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

- users with `authorized-host` equal to that FQDN
- plus users with `authorized-host` of `*`

Duplicates are removed and names are sorted. Deleting a host does not recalculate the remaining hosts.

New hosts default Group ID to one past the highest numeric `group-id` (floor `4999`, so the first ID is `5000`). With the current file the next value is `5004`. The Group ID input has `min="5000"`. Editing a host keeps the submitted Group ID unless you change it.

## Dashboard features

- **Add User** / **Edit User** on the left; **Add New Host** / **Edit Host** on the right
- Edit and Del on each list row (delete asks for confirmation)
- User fields: username, email, UID, GID, home directory, public key, authorized host
- Host fields: FQDN, group ID; member list is displayed and recalculated
- Public keys in the users table are truncated to 10 characters (full value on hover)
- Empty tables show **Empty database.** when there is only a header row (or fewer)
- Displayed CSV values are escaped with `htmlspecialchars`
- Browser `required` attributes on the forms; PHP writes submitted rows as given and does not check uniqueness

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

## Deploy

Requires PHP (for example Apache with `mod_php` or PHP-FPM). The web server user must be able to read and write `users.csv` and `hosts.csv` in the same directory as the PHP files.

From this directory:

```sh
make push
```

That copies `index.php`, `view.php`, `users.csv`, and `hosts.csv` into `/var/www/html` (uses `sudo`). It does not copy this README. Then open `index.php` on the web server and sign in.
