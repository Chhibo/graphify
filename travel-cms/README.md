# WanderLuxe Travel CMS

A PHP/MySQL travel agency website with **online booking and no online payment**. Guests reserve a trip or activity, get an instant voucher, and pay on arrival. The admin panel manages bookings, trips, activities, the blog, reviews, pages, messages and every text on the homepage.

The design follows the WanderLuxe template: hero search, featured trips with category tabs, day activities, travel stories, reviews, a "why us" section and the booking modal.

---

## Requirements

- PHP **7.4 or newer** (8.x recommended) with the `pdo_mysql` and `mbstring` extensions
- MySQL 5.7+ or MariaDB 10.3+
- Apache (shared hosting / cPanel), Nginx or LiteSpeed. No Composer, Node or command line needed.

## Installation (about 2 minutes)

1. **Upload** the contents of this folder to your hosting, either to `public_html/` (site at the domain root) or to a sub-folder such as `public_html/travel/`.
2. **Create a MySQL database** and a database user in your hosting control panel (cPanel → *MySQL® Databases*), then give the user *All privileges* on the database.
3. **Open your website** in a browser (for example `https://yourdomain.com/`). You are redirected to the installer automatically. You can also open `https://yourdomain.com/install/` directly.
4. **Follow the wizard:**
   - **Step 1:** checks your server.
   - **Step 2:** enter the database details, your website name and the admin account. Keep **Install demo content** ticked to start with sample trips, activities, posts and pages.
   - **Step 3:** done.
5. **Delete the `/install` folder** from your server.
6. Log in at `https://yourdomain.com/admin/`.

> If the installer cannot write `config.php`, it shows the file contents so you can create it yourself in the root folder.
> To reinstall, delete `config.php` and open `/install/` again. Use a different table prefix, or an empty database.

## Features

### Website
- Homepage with hero search (destination, travel style, max budget), featured trips with category tabs, activities, blog, reviews and features
- Trip / activity listing with search, filters, sorting and pagination
- Trip detail pages with overview, "What's included", day-by-day itinerary, reviews and related trips
- **Booking form** (modal on every page) with a live price estimate and pay-on-arrival messaging
- **Instant voucher page** with a printable voucher. Guests can cancel online if you allow it.
- **Manage My Booking**: guests find their voucher with their reference and email
- Visitor reviews, held for moderation by default
- Blog with tags, static pages (About, Terms, Privacy and so on) and a contact form
- Floating WhatsApp button, social links, SEO meta tags, responsive layout

### Booking rules (Settings → Booking)
- Turn online bookings on or off; auto-confirm bookings or review them first
- Minimum notice, maximum months ahead and blocked dates
- Optional daily capacity (guests per experience per day)
- Booking reference prefix (`WL-123456`), terms checkbox and guest cancellation window

### Admin panel (`/admin`)
- **Dashboard:** pending bookings, upcoming trips, expected revenue, a 14-day chart, the next 7 days of arrivals and the most-booked experiences
- **Bookings:** filters, search, bulk status changes, CSV export, and manual bookings for phone or walk-in guests. Each booking can be edited and has internal notes, email/WhatsApp/call buttons and an "email the guest" option.
- **Calendar:** a monthly view of arrivals
- **Trips & Activities:** create, edit, duplicate, hide or show, feature on the homepage; image upload or image URL
- Categories, blog posts (drafts and scheduled posts), pages, review moderation and contact messages
- **Settings:** general, every homepage text and image, booking rules, email/SMTP, contact and social links, custom code (analytics, chat widgets)
- Multiple administrators and a profile/password page

### Emails
- The guest gets a reservation email with a voucher link. The admin gets a new-booking alert.
- When an admin changes a booking's status, they can email the guest with an optional message.
- Uses PHP `mail()` by default. If your host blocks it, fill in **Settings → Email → SMTP** (Gmail, Zoho, cPanel mail and so on) and press **Send test**.

## Security
- Passwords hashed with `password_hash`, plus login throttling and an idle-session timeout
- CSRF tokens on every form; prepared statements for all SQL
- All output escaped; admin HTML content is sanitised (no scripts or event handlers)
- Uploads are checked as real images, renamed randomly, and PHP execution is blocked in `/uploads`
- Honeypot fields and throttling on the public forms
- `config.php`, `/includes` and `.sql` files are blocked from the web (Apache `.htaccess`)

**Nginx users:** add equivalent rules:

```nginx
location ~ ^/(config\.php|includes/|admin/includes/|install/schema\.sql) { deny all; }
location ~ ^/uploads/.*\.php$ { deny all; }
```

## Customising

- **Texts, images, colours of content:** almost everything is in *Admin → Settings*.
- **Templates:** public pages are the `.php` files in the root folder. Shared parts are in `includes/` (`header.php`, `footer.php`, `cards.php`).
- **Styles:** Tailwind CSS is pre-compiled to `assets/css/tailwind.min.css`, so no build step is needed. If you add new Tailwind classes, rebuild the file:
  ```bash
  npx tailwindcss@3 -c build/tailwind.config.js -i build/tailwind.src.css -o assets/css/tailwind.min.css --minify
  ```
  Extra hand-written CSS goes in `assets/css/app.css`.
- **Debug mode:** set `'debug' => true` in `config.php` to show PHP errors.

## Folder structure

```
├── index.php            Homepage
├── tours.php / tour.php Listing & detail pages
├── book.php             Booking handler (no payment)
├── voucher.php          Guest voucher / cancellation
├── my-booking.php       Booking lookup
├── blog.php / post.php  Blog
├── page.php             Static pages
├── contact.php          Contact form
├── review.php           Review submission
├── admin/               Admin panel
├── install/             Web installer (delete after install)
├── includes/            Core functions & layout
├── assets/              CSS, JS, images
├── uploads/             Uploaded images
└── build/               Tailwind config (only needed to rebuild CSS)
```
