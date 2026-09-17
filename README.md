# CSV User Manager

A small authenticated PHP web dashboard for managing users and machine-groups stored in CSV files. The application supports CRUD operations, CSV and generated-block publishing, raw-file inspection/editing, user search, user detail views, and synchronized host membership lists.

## Files

| File | Role |
| --- | --- |
| `index.php` | Application controller: starts the session, handles login/logout, reads and writes both CSV files, synchronizes host memberships, and loads the dashboard |
| `view.php` | HTML dashboard template included by `index.php`; contains the login form and authenticated users/hosts management interface |
| `users.csv` | User records |
| `users-block.txt` | Generated `/etc/passwd`-format user entries; created by the Users List publish action |
| `groups-block.txt` | Generated `/etc/group`-format primary user groups; created with `users-block.txt` |
| `hosts.csv` | Host records and calculated comma-separated member lists |
| `hosts-block.txt` | Generated `/etc/group`-format machine-group entries; created by the Hosts List publish action |
| `update-group-block.yml` | Ansible playbook that manually installs the generated machine-group block in `/etc/group` |
| `update-user-block.yml` | Ansible playbook that manually installs the generated user block in `/etc/passwd` |
| `setup-local-user-homes.yml` | Local-only Ansible playbook that creates user home directories and installs public keys |
| `Makefile` | Copies the PHP, CSV, generated block, and Ansible playbook files to `/var/www/html` and assigns them to `www-data:www-data` |

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
mary,3002,3002,tom.sandholm@gmail.com,/share/home/mary,ssh-rsa AAA...,tom2-tsand-org
```

The `authorized-host` value may be `*` to represent all machine-groups, or a specific machine-group from `hosts.csv`.

When adding a user, the form defaults UID and GID to one higher than the highest numeric value currently assigned in `users.csv`. If either value is omitted from the submitted request, `index.php` applies the same calculation server-side. Editing an existing user preserves its current UID and GID unless they are changed explicitly.

### `hosts.csv`

Each row represents a managed machine-group:

```text
machine-group,group-id,member-list
```

Example:

```csv
machine-group,group-id,member-list
tom1-tsand-org,5000,"tsandholm,ansible,mary"
tom2-tsand-org,5001,"tsandholm,ansible,mike"
tom3-tsand-org,5002,"tsandholm,ansible,iggy"
```

When a user's Auth Host is changed, the application removes that user from the previous assignment and adds it to the new assignment while preserving all other existing members in every host entry. User deletion removes only the deleted user from the applicable host entries. Saving a host entry does not recalculate or overwrite its existing member list.

When adding a machine-group, the form defaults Group ID to the next value after the highest numeric `group-id` already assigned in `hosts.csv`. Group IDs start at `5000` when no numeric IDs exist, and the server applies the same fallback when a new machine-group submission leaves the field blank. Editing an existing machine-group preserves its current Group ID unless it is changed explicitly.

## Dashboard features

- Add, edit, and delete users from the **Users List** panel
- Edit username, email, UID, GID, home directory, public key, and authorized machine-group
- View complete user details, including fields hidden from the compact list
- Search visible user rows by username, UID/GID, email, or authorized machine-group
- Add, edit, and delete machine-groups from the **Hosts List** panel
- Display calculated machine-group member lists with sticky headers and internal scrolling
- Update only the affected host memberships when a user's Auth Host changes
- View or edit raw `users.csv` and `hosts.csv` files
- Publish, view, and edit raw `hosts-block.txt`, `users-block.txt`, and `groups-block.txt` files
- Generate `hosts-block.txt` in `/etc/group` format from `hosts.csv`
- Generate `users-block.txt` in `/etc/passwd` format from `users.csv`
- Generate `groups-block.txt` in `/etc/group` format from `users-block.txt`
- Refuse to publish an empty or unreadable `users.csv` as `users-block.txt`
- Write generated user blocks atomically so a failed write cannot replace the existing file
- Suggest the next available UID and GID for new users
- Suggest the next available Group ID for new hosts, starting at `5000`
- Create empty `users.csv` and `hosts.csv` files automatically if they are missing
- Require an authenticated session for dashboard and raw-file access
- Lock raw-file saves with `LOCK_EX` and escape displayed CSV values with `htmlspecialchars`
- Keep the dashboard compact with side-by-side panels, page-level scrolling, and additional scrolling inside long lists

The user and host forms use browser-level required-field validation. The current PHP handlers write submitted rows directly and do not enforce uniqueness or additional server-side validation, so CSV content should be reviewed before production use.

## Quick Start

1. Make sure PHP is installed.
2. From this directory, start PHP's development server:

   ```sh
   php -S 127.0.0.1:8000
   ```

3. Open `http://127.0.0.1:8000/index.php`.
4. Sign in with the configured credentials.
5. Use the left panel to manage users and the right panel to manage machine-groups.

If either CSV file is missing, `index.php` creates an empty file before loading the dashboard. Add the desired header row and records through the application or prepare the files beforehand.

## Generated system-file blocks

The dashboard can create two deployment-ready text blocks:

| Button | Output | Format |
| --- | --- | --- |
| Hosts List → **Publish hosts-block.txt** | `hosts-block.txt` | `machine-group:x:group-id:member-list` (`/etc/group` style) |
| Users List → **Publish users-block.txt** | `users-block.txt` and `groups-block.txt` | `username:x:uid:gid:email:home-directory:/bin/bash` plus `username:x:gid:` (`/etc/group` style) |

After publishing, use **View Raw ...** to inspect the exact file or **Edit Raw ...** to make a direct authenticated edit. Editing a generated block does not update the source CSV, so source CSV changes should be made when the generated file needs to be regenerated.

User-block publishing requires a readable `users.csv` with at least one valid
user row. The application writes the generated content to a temporary file and
renames it into place only after the complete block has been written, preserving
the previous `users-block.txt` if generation or writing fails.
The same publish action then derives `groups-block.txt` from the generated
user block so each user's primary group name and GID are available for remote
group creation. The Users List also provides **View Raw groups-block.txt** and
**Edit Raw groups-block.txt** controls for authenticated direct inspection or
editing.

## Ansible playbooks

The playbooks read their input files from `/var/www/html` on the Ansible
controller, where `make push` deploys the CSV and block files.
The repository contains three manual Ansible playbooks. None is launched by a
dashboard Publish button.

| Playbook | Target | Purpose |
| --- | --- | --- |
| `update-group-block.yml` | Remote `virt` hosts | Reads `/var/www/html/hosts-block.txt` on the controller and replaces the managed CSV User Manager section in `/etc/group`. |
| `update-user-block.yml` | Remote `virt` hosts | Reads `/var/www/html/users-block.txt` and `/var/www/html/groups-block.txt`, creates each user's primary group with its generated GID, replaces the managed section in `/etc/passwd`, and runs `pwconv`. |
| `setup-local-user-homes.yml` | Controller `localhost` only | Reads `/var/www/html/users-block.txt` and `/var/www/html/users.csv`, creates local home directories under `/share/home`, installs each public key in `.ssh/authorized_keys`, and applies ownership and permissions. |

The first two playbooks use `become: true`, run one remote host at a time,
and create backups when changing `/etc/group` or `/etc/passwd`. The home setup
playbook uses `hosts: localhost` and `connection: local`; it never connects to
the remote `virt` hosts. Local user accounts and their primary groups must
already exist before running the home setup playbook.

## Recommended usage order

Run the workflow in this order whenever the CSV data changes:

1. Edit or save `users.csv` and `hosts.csv` in the dashboard.
2. In **Hosts List**, click **Publish hosts-block.txt**.
3. In **Users List**, click **Publish users-block.txt**. This also creates `groups-block.txt`.
4. Deploy the current files to the controller path with `make push` if the source files were changed in the repository. When the dashboard is already running from `/var/www/html`, its Publish actions write the deployed block files there directly.
5. Run `update-group-block.yml` to apply machine-groups.
6. Run `update-user-block.yml` to create primary groups, update users, and run `pwconv`.
7. Run `setup-local-user-homes.yml` on the controller to create local homes and install keys.

The home setup playbook can be run before or after the two remote playbooks as
long as the local accounts and groups already exist. Running the remote group
playbook before the remote user playbook is recommended because it establishes
the machine-group entries first.

The generated block must contain `/etc/group`-format lines, for example:

```text
tom1-tsand-org:x:5000:ansible,sudo,tsandholm
tom2-tsand-org:x:5001:ansible,sudo,mary,mikey,tsandholm
```

The Hosts List **Publish hosts-block.txt** button writes only the current
`hosts-block.txt`.
The Users List **Publish users-block.txt** button writes only the current
`users-block.txt`; neither button runs Ansible. To apply either block remotely,
run the corresponding playbook manually. The web server account does not need
to execute Ansible for publishing. Manual playbook runs require access to both
playbooks and block files, the configured inventory, SSH credentials, and
privilege escalation on the managed hosts.

Run a dry run manually first, then apply the change:

```sh
ansible-playbook -i inventory update-group-block.yml --check --diff
ansible-playbook -i inventory update-group-block.yml --diff
```

Example using a specific inventory file and limiting execution to one host:

```sh
ansible-playbook -i inventory update-group-block.yml --limit tom1.tsand.org --check --diff
ansible-playbook -i inventory update-group-block.yml --limit tom1.tsand.org --diff
```

The playbook manages only the section between these markers and leaves all
other `/etc/group` entries unchanged:

```text
# BEGIN CSV User Manager managed groups
# END CSV User Manager managed groups
```

`update-user-block.yml` similarly writes the current user block when run
manually against the `virt` group. It replaces or creates each user's primary
group first, then replaces only the managed section between these markers in
`/etc/passwd`:

```text
# BEGIN CSV User Manager managed users
# END CSV User Manager managed users
```

The user playbook can also be run manually:

```sh
ansible-playbook -i inventory update-user-block.yml --check --diff
ansible-playbook -i inventory update-user-block.yml --diff
```

To apply both remote blocks in the recommended order:

```sh
ansible-playbook -i inventory update-group-block.yml --check --diff
ansible-playbook -i inventory update-group-block.yml --diff
ansible-playbook -i inventory update-user-block.yml --check --diff
ansible-playbook -i inventory update-user-block.yml --diff
```

## Create local user homes and SSH access

`setup-local-user-homes.yml` is intentionally restricted to the Ansible
controller:

```yaml
hosts: localhost
connection: local
```

It reads usernames and home directories from `/var/www/html/users-block.txt`,
matches each user to the public key in `/var/www/html/users.csv`, and maps source paths under
`/share/home` to `/share/home` on the controller. It creates each local
home directory and writes the key to `.ssh/authorized_keys`. Home directories use mode `0700`;
`authorized_keys` uses mode `0600`. The local system must already contain each
user and its primary group.

Run it from the repository directory:

```sh
ansible-playbook -i localhost, setup-local-user-homes.yml --check --diff
ansible-playbook -i localhost, setup-local-user-homes.yml
```

The playbook uses `become: true` because it changes ownership and writes into
user-owned local home directories. It never targets the remote `virt` hosts.

Example of running the local-only playbook with an explicit local connection:

```sh
ansible-playbook -i localhost, setup-local-user-homes.yml \
  --connection=local --limit localhost --check --diff
```

## End-to-end example

After changing a user's Auth Host or adding a machine-group:

```sh
# 1. Publish both generated blocks in the dashboard.
# 2. If repository files are the source of deployment, copy them to /var/www/html.
make push

# 3. Preview and apply machine-groups on the remote virt hosts.
ansible-playbook -i inventory update-group-block.yml --check --diff
ansible-playbook -i inventory update-group-block.yml --diff

# 4. Preview and apply users and primary groups on the remote virt hosts.
ansible-playbook -i inventory update-user-block.yml --check --diff
ansible-playbook -i inventory update-user-block.yml --diff

# 5. Preview and apply local homes and SSH authorized keys on the controller.
ansible-playbook -i localhost, setup-local-user-homes.yml --check --diff
ansible-playbook -i localhost, setup-local-user-homes.yml
```

## Deploy

Requires PHP, for example Apache with `mod_php` or PHP-FPM. The web server user must be able to read and write `index.php`, `view.php`, `users.csv`, and `hosts.csv`. It must also be able to create or update `hosts-block.txt`, `users-block.txt`, and `groups-block.txt`. The web server does not need to execute `ansible-playbook`; the playbooks are run manually from the controller.

From this directory:

```sh
make push
```

The target copies the PHP application, source CSV files, generated block files,
and Ansible playbooks into `/var/www/html`, then assigns all copied files to
`www-data:www-data`:

```sh
sudo cp index.php /var/www/html
sudo cp view.php /var/www/html
sudo cp users.csv /var/www/html
sudo cp hosts.csv /var/www/html
sudo cp hosts-block.txt /var/www/html
sudo cp users-block.txt /var/www/html
sudo cp groups-block.txt /var/www/html
sudo cp update-group-block.yml /var/www/html
sudo cp update-user-block.yml /var/www/html
sudo cp setup-local-user-homes.yml /var/www/html
sudo chown www-data:www-data /var/www/html/index.php /var/www/html/view.php /var/www/html/users.csv /var/www/html/hosts.csv /var/www/html/hosts-block.txt /var/www/html/users-block.txt /var/www/html/groups-block.txt /var/www/html/update-group-block.yml /var/www/html/update-user-block.yml /var/www/html/setup-local-user-homes.yml
```

Then open `index.php` through the web server and sign in. Publishing from the
dashboard only writes generated block files; it does not execute
`ansible-playbook`. Run the playbooks manually from the controller after
publishing and deploying the files.
