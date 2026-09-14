# CSV User Manager

A small PHP web dashboard for managing users and hosts stored in CSV files. The application provides an authenticated interface for creating, editing, and deleting user records and host records, and keeps host membership lists synchronized with user authorized-host mappings.

## Files

| File | Role |
| --- | --- |
| `index.php` | Application controller: starts the session, handles login/logout, reads and writes both CSV files, synchronizes host memberships, and loads the dashboard |
| `view.php` | HTML dashboard template included by `index.php`; contains the login form and authenticated users/hosts management interface |
| `users.csv` | User records |
| `hosts.csv` | Host records and calculated comma-separated member lists |
| `Makefile` | Copies `index.php`, `view.php`, `users.csv`, and `hosts.csv` to `/var/www/html` |

## Authentication

The dashboard requires an authenticated session before either CSV database is displayed or modified.

The current credentials defined in `index.php` are:

```text
Username: admin
Password: secret123
```

Change `ADMIN_USER` and `ADMIN_PASS` in `index.php` before deploying to a shared or production environment. Use the **Logout** link in the dashboard to end the session.

## CSV formats

### `users.csv`

Each row represents one user:

```text
username,uid,gid,email,home-directory,public-key,authorized-host
```

Example:

```csv
username,uid,gid,email,home-directory,public-key,authorized-host
tsandholm,3000,3000,tom.sandholm@gmail.com,/share/home/tsandholm,ssh-rsa AAA...,*
kat,3001,3001,tom.sandholm@gmail.com,/share/home/kat,ssh-rsa AAA...,*
mary,3002,3002,tom.sandholm@gmail.com,/share/home/mary,ssh-rsa AAA...,tom2.tsand.org
```

The `authorized-host` value may be `*` to represent all hosts, or a specific FQDN from `hosts.csv`.

When adding a user, the form defaults UID and GID to one higher than the highest numeric value currently assigned in `users.csv`. If either value is omitted from the submitted request, `index.php` applies the same calculation server-side. Editing an existing user preserves its current UID and GID unless they are changed explicitly.

### `hosts.csv`

Each row represents a managed host:

```text
fqdn,group-id,member-list
```

Example:

```csv
fqdn,group-id,member-list
tom1.tsand.org,5000,"tsandholm,ansible,mary"
tom2.tsand.org,5001,"tsandholm,ansible,mike"
tom3.tsand.org,5002,"tsandholm,ansible,iggy"
```

`member-list` is recalculated from `users.csv` whenever a user is saved or deleted, and whenever a host is saved. Users assigned to `*` are included on every managed host; users assigned to a specific FQDN are included only on that host. Duplicate member names are removed and the resulting list is sorted.

When adding a host, the form defaults Group ID to the next value after the highest numeric `group-id` already assigned in `hosts.csv`. Group IDs start at `5000` when no numeric IDs exist, and the server applies the same fallback when a new host submission leaves the field blank. Editing an existing host preserves its current Group ID unless it is changed explicitly.

## Dashboard features

- Add, edit, and delete users from the **Users List** panel
- Add, edit, and delete hosts from the **Hosts List** panel
- Edit user identity, contact, SSH key, home directory, and authorized host fields
- Edit host FQDN and group ID fields
- Display calculated host member lists
- Synchronize host memberships after user changes
- Suggest the next available UID and GID for new users
- Suggest the next available Group ID for new hosts, starting at `5000`
- Create empty `users.csv` and `hosts.csv` files automatically if they are missing
- Escape displayed CSV values with `htmlspecialchars`

The user and host forms use browser-level required-field validation. The current PHP handlers write submitted rows directly and do not enforce uniqueness or additional server-side validation, so CSV content should be reviewed before production use.

## Quick Start

1. Make sure PHP is installed.
2. From this directory, start PHP's development server:

   ```sh
   php -S 127.0.0.1:8000
   ```

3. Open `http://127.0.0.1:8000/index.php`.
4. Sign in with the configured credentials.
5. Use the left panel to manage users and the right panel to manage hosts.

If either CSV file is missing, `index.php` creates an empty file before loading the dashboard. Add the desired header row and records through the application or prepare the files beforehand.

## Deploy

Requires PHP, for example Apache with `mod_php` or PHP-FPM. The web server user must be able to read and write `index.php`, `view.php`, `users.csv`, and `hosts.csv`.

From this directory:

```sh
make push
```

The target copies all four application files into `/var/www/html`:

```sh
sudo cp index.php /var/www/html
sudo cp view.php /var/www/html
sudo cp users.csv /var/www/html
sudo cp hosts.csv /var/www/html
```

Then open `index.php` through the web server and sign in.
