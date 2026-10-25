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
- Ads: search/filter, bulk approve / reject (with reason) / feature / delete, edit any ad, set expiry — owners get an email when approved or rejected
- Categories: two levels, icons, ordering; delete safely by moving ads
- **Custom fields per category** (Brand, Year, Mileage, Bedrooms…): text, number, dropdown or yes/no; required or optional; shown as search filters
- **Locations list** (cities › areas) with “add many at once”, or let sellers type freely
- **Bulk import ads from CSV** (Excel / Google Sheets), including custom fields and photo URLs
- Users: add, edit, verify (badge), confirm email, ban, promote to admin, reset password, delete with all content
- Reports, reviews moderation, contact-form inbox, pages editor
- **Emails & SMTP**: send through your mail server, edit every email template with live preview, send a test email
- **Activity log**: who did what and when (approvals, edits, deletions, settings, sign-ins…)
- **One-click backup**: database + config + photos in a single zip, download from the browser
- Settings: branding, colours, texts, currency, approval, expiry, photo limits, ads per day, reviews, maps, phone privacy,
  sign-up & email verification, Google/Facebook login, captcha, banned words, clean URLs, PWA, ad spaces, cron, maintenance mode

## What's new in 1.2

| Feature | Where to turn it on / find it |
|---|---|
| **Multi-language** — English and Bangla (বাংলা) included, add any language, right-to-left support (Arabic, Urdu…) | Admin → Languages (switch languages on/off, translate or customise every text, import/export JSON) |
| **Shop pages for businesses** — logo, cover, opening hours, “Business” badge, shops directory | Admin → Settings → Ads & users (approval optional) · members: Dashboard → My shop |
| **Seller statistics** — daily views, saves, chats, offers and phone reveals per ad (7/30/90 days) | Dashboard → Statistics |
| **Make an offer** — buyers offer a price; sellers accept, decline or counter | Admin → Settings → Ads & users |
| **Ad expiry reminders** with a one-click “renew” link | Admin → Settings → Ads & users (days before expiry; 0 = off) |
| **Photo watermark** (text or logo, position, opacity) | Admin → Settings → Photos & video |
| **Video in ads** (YouTube / Vimeo, loads only when played) | Admin → Settings → Photos & video |
| **Price-drop alerts** to members who saved the ad | Admin → Settings → Ads & users |
| **Better chat** — live updates, “seen” ticks, photos in chat, quick replies | Automatic (photos: Settings → Photos & video; quick replies: Settings → Ads & users) |
| **Newsletter** with audiences, test email, progress bar and one-click unsubscribe | Admin → Newsletter |
| **Two-factor sign-in for admins** (authenticator app + recovery codes) | Admin → My security (2FA) · force it for all admins in Settings → Sign-up & login |
| **Duplicate ad detection** (same title or similar photos) — flag or hold for review | Admin → Settings → Ads & users · flagged ads: Admin → Ads → *Possible duplicates* |
| **Seller ID verification** — private document upload, admin review, “Verified” badge | Admin → ID verification · members: Dashboard → Get verified |

Notes
- The admin panel always stays in English; the public site and member dashboard follow the visitor's language
  (chosen from the 🌐 menu, saved to their account, or detected from the browser).
- Arabic is included switched **off** as a right-to-left example with no translations yet — translate it in
  Admin → Languages, or delete it.
- Emails (templates in Admin → Emails & SMTP) are sent in the text you write there.
- ID documents are stored in `data/private/`, which is never reachable from the web; by default they are deleted as soon as
  you approve or reject the request.

### Upgrading from 1.1 / 1.1.1
Same as below: back up, upload the new files over the old ones (keep `config.php`, `data/` and `uploads/`), then open
your site once — the database upgrades itself.

## What's new in 1.1

| Feature | Where to turn it on |
|---|---|
| Category-specific fields + filters | Admin → Categories → *Fields* icon |
| Saved searches with email alerts | On by default (Admin → Settings → Ads & users) |
| Locations list | Admin → Locations |
| Search suggestions + recently viewed | Automatic |
| Email verification on sign-up | Admin → Settings → Sign-up & login |
| Google & Facebook sign-in (optional) | Admin → Settings → Sign-up & login |
| Spam protection (reCAPTCHA / Turnstile, ads-per-day limit) | Admin → Settings → Spam protection |
| Banned words filter | Admin → Settings → Spam protection |
| Block a member from messaging you | Member's profile, a conversation, or Account settings |
| Clean URLs + sitemap | Admin → Settings → SEO, URLs & app |
| Installable app (PWA) | Admin → Settings → SEO, URLs & app (on by default, needs https) |
| Share images for WhatsApp/Facebook + rich previews | Automatic |
| Email templates editor + SMTP | Admin → Emails & SMTP |
| One-click backup | Admin → Backup |
| Activity log | Admin → Activity log |
| CSV import | Admin → Import ads (CSV) |

### Upgrading from 1.0
1. **Make a backup** of your files and database (or download them with your hosting File Manager / phpMyAdmin).
2. Upload the new files over the old ones. **Do not** upload `install/` if you already deleted it, and keep your `config.php`, `data/` and `uploads/` folders.
3. Open your website — the database upgrades itself automatically on the first visit. Nothing else to do.

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
location / { try_files $uri $uri/ /router.php?$query_string; }   # clean URLs
client_max_body_size 64M;
```

### Emails (important for email verification)
By default emails are sent with PHP's `mail()`. For reliable delivery go to **Admin → Emails & SMTP**, choose **SMTP** and
enter the details of an email account (your hosting email, Gmail with an App Password, Brevo, Mailgun, SendGrid…),
then click **Send test**. Only switch on *email verification* once the test email arrives.

### Scheduled jobs (cron)
Expiring old ads, saved-search alerts, expiry reminders, price-drop alerts and newsletters are sent automatically while people visit your site.
For exact timing, add a cron job in your hosting panel (cPanel → Cron Jobs) to run every 15 minutes:

```
php /home/USER/public_html/cron.php
```
or, if your host only allows URLs: `https://your-domain.com/cron.php?key=YOUR_KEY` (the exact line is shown in
Admin → Settings → Advanced). Then you can switch off “Run scheduled jobs automatically during visits”.

### Google & Facebook sign-in
1. **Google**: console.cloud.google.com → APIs & Services → Credentials → *Create OAuth client ID* → Web application.
   Add the redirect URL shown in Admin → Settings → Sign-up & login (`https://your-domain.com/oauth.php?provider=google`).
2. **Facebook**: developers.facebook.com → Create app → add *Facebook Login* → set the redirect URL
   (`https://your-domain.com/oauth.php?provider=facebook`). Switch the app to *Live* mode.
3. Paste the IDs/secrets in Admin → Settings → Sign-up & login and switch on *Enable social login*.
The buttons appear on the sign-in and sign-up pages only when social login is enabled and the keys are filled in.

### Captcha
Get free keys from google.com/recaptcha (choose **reCAPTCHA v2 – “I'm not a robot” checkbox**) or Cloudflare
Turnstile, paste them in Admin → Settings → Spam protection, and choose which forms use it.

### Clean URLs and the .htaccess files
Turn on in Admin → Settings → SEO, URLs & app. Works on Apache and LiteSpeed (almost all shared hosting).

The `.htaccess` files are **hidden files** (their name starts with a dot), so many computers and file managers don't
show them and they often don't get uploaded. **You don't need to worry about it:** the site creates any missing
`.htaccess` file automatically (with the right settings for the folder it is installed in), and the Clean URLs switch
repairs and re-tests it for you. You can also click **Create / repair .htaccess files** in the same settings tab
— useful after moving the site to another folder or domain. Anything your host put in `.htaccess`
(e.g. cPanel's PHP version lines) is kept.

Submit `https://your-domain.com/sitemap.xml` to Google Search Console.

### Bigger photo uploads
If photo uploads fail, raise `upload_max_filesize` and `post_max_size` in your hosting PHP settings
(the included `.htaccess` already asks for 16 MB / 64 MB where allowed).

## Folder structure

```
admin/       admin panel pages
dashboard/   user dashboard pages
install/     web installer (delete after installing)
includes/    core code, layouts, database schema
includes/lang/  translation files (bn.php = Bangla)
assets/      css, js, images
uploads/     user photos, logo (writable)
data/        SQLite database, sessions, backups & private ID documents (writable, private)
router.php   clean-URL router · cron.php scheduled jobs · sw.js app service worker
```

## Security
Passwords hashed with `password_hash`, prepared statements everywhere, CSRF protection on every form,
output escaping, sign-in rate limiting, uploaded images validated and re-encoded, PHP execution disabled in `uploads/`,
optional captcha, banned-words filter, ads-per-day limit, member blocking, duplicate-ad detection, two-factor sign-in for admins
and an admin activity log.

### Local testing
`php -S localhost:8000 router.php` from inside this folder (needs PHP 8.1+), then open http://localhost:8000/install/.

## Credits
Icons based on Lucide (ISC). Share-image font: DejaVu Sans (see `assets/fonts/LICENSE-DejaVu.txt`).

## Troubleshooting
- **“500 Internal Server Error” on every page after uploading version 1.0's `.htaccess`**: that old file used a format some
  Apache servers reject. Delete the `.htaccess` file in the main folder (and in `uploads/`) — version 1.1 recreates
  correct ones automatically on the next visit.
- **White page / error 500**: set `'debug' => true` in `config.php` to see the error, then set it back to `false`.
- **“Session expired”** when submitting a form: reload the page and try again (the form was open too long).
- **Forgot admin password**: use “Forgot password?” on the sign-in page, or ask another admin to set a new one in Admin → Users.
