# Bazaarly — Classifieds Listings Script

A modern, fast and responsive classified-ads website written in plain PHP.
No frameworks, no Composer, no Node — upload it and run the installer.

- ⚡ **Fast**: one CSS file, one small JS file, no external libraries or fonts. Photos are auto-resized and converted to WebP.
- 📱 **Responsive** from phones to big screens, with **dark mode**.
- 🧙 **Web installer** — works with **SQLite (zero setup)** or **MySQL/MariaDB**.

## Features

**Visitors**
- Home page with search (keyword, location, category), category grid, featured and latest ads
- Browse page with filters (category, location, price range, condition, date posted, featured), sorting, grid/list view
- Ad page with photo gallery (swipe + keyboard), seller card, “show phone”, message seller, save, share, report, map, similar ads
- Seller profiles with ratings & reviews
- Static pages (About, Terms, Privacy, Safety…), contact form, cookie notice

**Users (dashboard at `/dashboard/`)**
- Post ads with up to N photos (drag & drop, choose cover, remove photos)
- Edit, delete, mark as sold, relist and renew their ads
- Built-in messaging (inbox with conversations per ad), email notifications
- Saved ads, profile photo, profile info, change password, delete account

**Admin panel (at `/admin/`) — controls everything**
- Dashboard with stats and a 14-day chart, pending ads to approve in one click
- Ads: search/filter, bulk approve / reject (with reason) / feature / delete, edit any ad, set expiry
- Categories: two levels, icons, ordering; delete safely by moving ads
- Users: add, edit, verify (badge), ban, promote to admin, reset password, delete with all content
- Reports from users, reviews moderation, contact-form inbox
- Pages editor (show in header/footer)
- Settings: site name, logo, favicon, brand colours, default theme, home texts, currency, date format, timezone,
  registration on/off, ad approval on/off, ad expiry, photos per ad, photo size, reviews, maps, phone privacy,
  social links, **ad spaces** (AdSense/banners), custom `<head>` code, maintenance mode

## Requirements

- PHP **8.1 or newer** with `pdo`, `mbstring`, and `pdo_sqlite` **or** `pdo_mysql`
- `gd` extension recommended (photo resizing)
- Any web server: Apache, LiteSpeed, Nginx — shared hosting is fine

## Installation (5 minutes)

1. **Upload** the contents of this `bazaarly` folder to your hosting
   (e.g. into `public_html/` with your hosting File Manager or FTP).
2. Open **`https://your-domain.com/install/`** in your browser.
3. The installer checks your server. If something is red, follow the hint shown.
4. Choose the database:
   - **Easy (SQLite)** – nothing to configure. Best for beginners.
   - **MySQL** – first create a database + user in your hosting panel (cPanel → *MySQL Databases*), then enter the details.
5. Enter your site name and create your **admin account**. Keep “Add sample ads” ticked if you want demo content.
6. Click **Install now**, then **delete the `install` folder** from your server.
7. Sign in and open **Admin panel** from the account menu (top right).

> Want to reinstall? Delete `config.php` (and the database) and open `/install/` again.

### Folder permissions
`uploads/` and `data/` must be writable by PHP (usually `755` or `775`). The main folder must be writable once,
so the installer can create `config.php` — if it isn't, the installer shows the file content for you to create by hand.

### Nginx
Apache/LiteSpeed protection is already included through `.htaccess` files. On Nginx, add:

```nginx
location ~ ^/(config\.php|includes/|data/) { deny all; }
location ~ ^/uploads/.*\.(php|phtml|phar)$ { deny all; }
client_max_body_size 64M;
```

### Emails
Password-reset and new-message emails use PHP's `mail()`. Most hosts support it out of the box.
Set a “Send emails from” address on your domain in **Admin → Settings → Advanced** for best delivery.

### Bigger photo uploads
If photo uploads fail, raise `upload_max_filesize` and `post_max_size` in your hosting PHP settings
(the included `.htaccess` already asks for 16 MB / 64 MB where allowed).

## Folder structure

```
admin/       admin panel pages
dashboard/   user dashboard pages
install/     web installer (delete after installing)
includes/    core code, layouts, database schema
assets/      css, js, images
uploads/     user photos, logo (writable)
data/        SQLite database & sessions (writable, private)
```

## Security
Passwords hashed with `password_hash`, prepared statements everywhere, CSRF protection on every form,
output escaping, sign-in rate limiting, uploaded images validated and re-encoded, PHP execution disabled in `uploads/`.

## Troubleshooting
- **White page / error 500**: set `'debug' => true` in `config.php` to see the error, then set it back to `false`.
- **“Session expired”** when submitting a form: reload the page and try again (the form was open too long).
- **Forgot admin password**: use “Forgot password?” on the sign-in page, or ask another admin to set a new one in Admin → Users.
