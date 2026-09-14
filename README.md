# CSV User Manager

A small PHP web app for managing a CSV-backed user directory. It lets you add, edit, and delete Linux-style user entries from a browser while validating required identifiers before writing back to disk.

## Files

| File | Role |
| --- | --- |
| `index.php` | Main PHP page that reads `users.csv`, renders the editable table, validates changes, and writes the CSV back safely |
| `users.csv` | Current user records; created automatically with sample rows if missing |
| `Makefile` | Copies the app files into `/var/www/html` |

## CSV format

Each row represents one user. The header is always:

```text
username,uid,gid,email,home-directory,public-key,authorized-host
```

The app writes those seven columns in that exact order. A row is considered valid only when:

- `username` is present
- `uid` is present
- `gid` is present
- `username`, `uid`, and `gid` are all unique among the rows being saved

UID and GID are treated independently, so two users may not share the same UID even if their GIDs differ.

## Features

- Add a new user from the blue `Add New User Entry` row at the top of the table
- Auto-fill the next available UID/GID when creating a new user and the field is left blank
- Edit any existing row directly in the browser
- Delete any row by checking the `Delete` checkbox
- Reject invalid saves instead of partially updating the CSV
- Highlight duplicate or missing `username`, `uid`, or `gid` values in the form
- Populate the authorized-host dropdown from `all`, `none`, and values already in the file
- Use file locking while reading and writing the CSV to reduce race-condition issues
- Keep table headers visible while scrolling and keep the save button fixed at the bottom-right

## How the page behaves

Each request follows the same workflow:

1. Seed the file if it does not exist
2. Process `POST` data for existing rows and the new-user form
3. Validate identity fields (`username`, `uid`, `gid`)
4. Rewrite the CSV only if the save is valid
5. Render the updated table again

When validation fails, the CSV is left unchanged and the form keeps your edits so you can correct the problem without losing data.

## Using the app

- Open the page in a browser
- Add a user by filling in the top row (username is required)
- Edit any existing field and click **Save Changes**
- Delete a row by checking the corresponding `Delete` box and saving
- If a save would create duplicate `username`, `uid`, or `gid` values, it is rejected

The PHP process must be able to read and write `users.csv` from the same directory as `index.php`.

## Quick Start

1. Make sure PHP is installed.
2. Start the project from this directory:

```sh
php -S 127.0.0.1:8000
```

3. Open `http://127.0.0.1:8000/index.php` in your browser.
4. If `users.csv` does not exist yet, the app creates it automatically with sample users.
5. Edit the table, add users, or mark rows for deletion, then click **Save Changes**.

## Screenshots

These mockups show the main editor layout and the validation state when a duplicate or missing field is detected:

![CSV User Manager overview](docs/csv-user-manager-overview.svg)

![Validation example](docs/csv-user-manager-validation.svg)

## Examples

### Example CSV

```csv
username,uid,gid,email,home-directory,public-key,authorized-host
jdoe,1001,1001,jdoe@example.com,/home/jdoe,ssh-rsa AAA...,server1.local
asmith,1002,1002,asmith@example.com,/home/asmith,ssh-ed25519 AAA...,server2.local
bsmith,1003,1003,bsmith@example.com,/home/bsmith,ssh-rsa AAA...,all
```

### Example add-user flow

1. Fill in the top `Add New User Entry` row.
2. Leave `uid` or `gid` blank if you want the app to assign the next available numeric value.
3. Click `Save Changes`.
4. If the new row creates a duplicate username, UID, or GID, the save is rejected and the invalid fields are highlighted.

### Example validation errors

```text
CSV file was not updated:
- Each user must have a username.
- Duplicate uid values are not allowed: 1002.
```

## Deploy

Requires PHP (for example Apache with `mod_php` or PHP-FPM). From this directory:

```sh
make push
```

That copies `index.php` and `users.csv` into `/var/www/html`. Then open the page on the web server.
