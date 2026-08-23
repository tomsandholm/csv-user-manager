# CSV User Manager

A single PHP page that edits `users.csv` through a web form. Open it in a browser, change the table, and click **Save Changes** to rewrite the CSV.

## Files

| File | Role |
| --- | --- |
| `index.php` | Reads and writes `users.csv`; renders the editor |
| `users.csv` | User records (created with sample rows if missing) |
| `Makefile` | Copies both files to `/var/www/html` |

## CSV format

Each row is one user. The first line is the header:

```text
username,uid,gid,email,home-directory,public-key,authorized-host
```

`index.php` always writes those seven columns, in that order.

## How `index.php` works

Every request does the same three things: optionally seed the CSV, optionally save a POST, then render the current file.

1. **Seed.** If `users.csv` does not exist, the script creates it with the header row and two sample users (`jdoe`, `asmith`).

2. **Save (POST only).** The form posts two groups of fields:
   - `new_user[...]` — the blue “Add New User” row at the top. If **Username** is filled in, that row is written first.
   - `users[i][...]` — every existing row. Checking **Delete** drops that row from the rewrite.

   The file is then replaced in one pass: exclusive `flock`, truncate, write the header, write the kept rows, flush, unlock. New users therefore appear at the top of the CSV on the next load.

3. **Render.** The script opens the CSV with a shared lock, skips the header, and builds two lists:
   - `$users` — every data row
   - `$hosts` — `all` and `none`, plus every distinct `authorized-host` value already in the file

   Those feed the HTML table. Authorized Host is a `<select>` (new users default to `all`). Existing rows are editable inputs. Column headers stay pinned to the top of the viewport while you scroll.

Short rows are padded to seven fields. Output is escaped with `htmlspecialchars`.

## Using the page

- **Add** a user: fill the top row (username is required) and save.
- **Edit** a user: change any field in an existing row and save.
- **Delete** a user: check **Delete** on that row and save.

The web server user must be able to read and write `users.csv` in the same directory as `index.php`.

## Deploy

Requires PHP (for example Apache with `mod_php` or PHP-FPM). From this directory:

```sh
make push
```

That copies `index.php` and `users.csv` to `/var/www/html`. Then open the page on the web server.
