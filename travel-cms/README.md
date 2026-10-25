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

## Updating an existing site

1. Back up your database and your `uploads/` folder (from version 1.2, use *Admin → Backup*).
2. Upload the new files over the old ones. **Keep your `config.php`** and your `uploads/` folder, and don't upload the `/install` folder again.
3. Open any page of your website. The database is upgraded automatically: new tables and fields are added and your existing data is kept.

## Features

### Website
- Homepage with hero search (destination, travel style, max budget), featured trips with category tabs, destinations, activities, blog, reviews and features
- Trip / activity listing with search, destination and style filters, sorting and pagination
- Trip detail pages with a **photo gallery** (full-screen viewer), overview, "What's included", day-by-day itinerary, reviews and related trips
- **Destinations:** a destinations page and one page per destination listing its trips and activities
- **Adult / child / infant pricing:** separate prices per trip (child price defaults to the adult price, infants default to free). Children or infants can be turned off per trip.
- **Booking form** (modal on every page) with adults / children / infants and a live price estimate
- **Prices can be hidden** per trip, or for the whole site (Settings → Booking), showing "Price on request". Guests still book, and you confirm the price with them afterwards.
- **Wishlist:** a heart button on every trip saves it to "Saved Trips" (no account needed), and the saved list can be shared on WhatsApp
- **Share buttons** on trips, destinations and blog posts: WhatsApp, Facebook, X, Telegram, email, copy link and the phone's own share menu
- **Newsletter:** sign-up box in the footer, plus an optional subscribe popup (on/off, delay, and how often it shows) with welcome emails and unsubscribe links
- **Instant voucher page** with a printable voucher. Guests can cancel online if you allow it.
- **Manage My Booking**: guests find their voucher with their reference and email
- Visitor reviews, held for moderation by default
- Blog with tags, static pages (About, Terms, Privacy and so on) and a contact form
- Floating WhatsApp button, social links, responsive layout
- **Plan a custom trip:** a 4-step "Plan My Trip" form (destinations, dates, travellers, travel style, budget, accommodation, wishes). Requests get a `TR-12345` reference, the guest gets a confirmation email and you get an alert.
- **FAQ:** a general FAQ page grouped by topic, with search, plus questions per trip on each trip page. Both include Google FAQ structured data.
- **Verified reviews:** after a trip, the guest gets a private link to review the booking (stars, text, up to 3 photos). These reviews carry a "Verified booking" badge and are shown first.
- **Multi-language** with right-to-left support. Visitors choose a language from the globe menu. Arabic is included (interface, emails and WhatsApp messages), and any other language can be added. Trips, destinations, posts, pages, FAQs, categories and homepage texts can be translated in the admin panel.
- **Dark mode:** a moon/sun switch in the header (it can be turned off, and the default can be light, dark or the visitor's device setting)

### SEO
- SEO title and meta description fields for every trip, destination, blog post and page
- Canonical links, Open Graph and Twitter tags for link previews, and a default sharing image
- Google structured data: product ratings and prices on trips, breadcrumbs, travel agency details, blog articles and destinations
- XML sitemap (`/sitemap.php`, also `/sitemap.xml` on Apache) and `robots.txt` (`/robots.php`, also `/robots.txt`). When the site is installed in a sub-folder, submit the sitemap URL in Google Search Console yourself.
- A switch to hide the whole site from search engines while you build it, and a Google Search Console verification field

### Booking rules (Settings → Booking)
- Turn online bookings on or off; auto-confirm bookings or review them first
- Minimum notice, maximum months ahead and blocked dates
- Optional daily capacity (guests per experience per day)
- Booking reference prefix (`WL-123456`), terms checkbox and guest cancellation window

### Admin panel (`/admin`)
- **Dashboard:** pending bookings, upcoming trips, expected revenue, a 14-day chart, the next 7 days of arrivals and the most-booked experiences
- **Bookings:** filters, search, bulk status changes, CSV export, and manual bookings for phone or walk-in guests. Each booking can be edited and has internal notes, email/WhatsApp/call buttons and an "email the guest" option.
- **Calendar:** a monthly view of arrivals
- **Trips & Activities:** create, edit, duplicate, hide or show, feature on the homepage, adult/child/infant prices, a photo gallery (upload many photos at once, captions, order), destination link and SEO fields
- **Destinations:** add, edit, choose which trips belong to each one, and feature them on the homepage
- **Subscribers:** search, add manually, unsubscribe or delete, and export to CSV for Mailchimp, Brevo, MailerLite and similar tools. The CSV includes each subscriber's unsubscribe link.
- Categories, blog posts (drafts and scheduled posts), pages, review moderation and contact messages
- **Settings:** general, every homepage text and image, booking rules, newsletter and popup, SEO, email/SMTP, contact and social links, custom code (analytics, chat widgets)
- **Staff roles:**
  | Role | Can access |
  |---|---|
  | Administrator | Everything, including Settings and staff accounts |
  | Manager | Bookings, trip requests, trips, destinations, blog, pages, FAQs, reviews, messages, subscribers, reports |
  | Booking Agent | Bookings, trip requests, calendar and messages only (cannot delete bookings) |
- Every staff member has a profile page to change their own password
- **Trip Requests:** list, filters and CSV export of "Plan My Trip" requests. Each request has a status (New, In progress, Proposal sent, Booked, Closed), internal notes, a ready WhatsApp reply, and a **Create booking** button that fills in a booking from the request.
- **FAQs:** manage the general FAQ page and the questions of each trip (also editable in the trip editor)
- **Message Templates:** edit every email (subject and text) and the WhatsApp messages, per language, with `{placeholders}` such as `{name}`, `{trip}`, `{date}`, `{total}` and `{voucher_link}`. Send yourself a test email, or reset a template to the default.
- **WhatsApp templates on each booking:** "Booking received", "Booking confirmed", "Price confirmation", "Trip reminder", "Ask for a review" and "Booking cancelled". One click opens WhatsApp with the message ready, in the guest's language.
- **Languages:** add languages, set the direction (left-to-right or right-to-left), choose the default, hide or show them, and translate or adjust every interface text
- **Reports:** bookings, guests, revenue, cancellation rate, trip requests, subscribers and reviews for any date range (by booking date or travel date). Includes monthly charts, top experiences and destinations, status, busiest weekdays, booking language, and CSV export.
- **Backup:** one click downloads a full backup (.zip with the database and uploaded images), the database only (.sql or .sql.gz) or the images only. Backups are never stored on the server. Administrators only.

### Emails
- The guest gets a reservation email with a voucher link. The admin gets a new-booking alert.
- When an admin changes a booking's status, they can email the guest with an optional message.
- When a booking is marked **Completed**, the guest automatically gets a "How was your trip?" email with their review link (this can be turned off in Settings → Booking).
- Guests receive emails in the language they used on the website, written right-to-left for Arabic and similar languages.
- Every email text can be edited in *Admin → Message Templates*.
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
location ~ ^/(config\.php|includes/|admin/includes/) { deny all; }
location = /sitemap.xml { rewrite ^ /sitemap.php last; }
location = /robots.txt { rewrite ^ /robots.php last; }
location ~ ^/uploads/.*\.php$ { deny all; }
```

## Languages

- English is the default language and Arabic is included. Enable or add languages in *Admin → Languages*.
- **Interface texts** (buttons, labels, messages) come from `lang/xx.php`. Change any of them in *Admin → Languages → Translate interface*, so you never need to edit files. `lang/strings.php` lists every text of the website.
- **Your content** (trips, destinations, posts, pages, FAQs, categories) has a *Translations* box at the bottom of each edit page. Homepage and other site texts are translated in *Settings*, using the language buttons at the top.
- Untranslated content falls back to the default language.
- Links can carry the language: `index.php?lang=ar`. The choice is remembered, and search engines get `hreflang` links and a sitemap entry per language.

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
├── destinations.php     Destinations list (destination.php = one destination)
├── wishlist.php         Saved trips
├── subscribe.php        Newsletter sign-up (unsubscribe.php to opt out)
├── sitemap.php          XML sitemap (robots.php = robots.txt)
├── book.php             Booking handler (no payment)
├── voucher.php          Guest voucher / cancellation
├── my-booking.php       Booking lookup
├── blog.php / post.php  Blog
├── page.php             Static pages
├── contact.php          Contact form
├── review.php           Review submission (review-booking.php = verified review from a booking)
├── plan-trip.php        "Plan My Trip" custom request form
├── faq.php              General FAQ page
├── lang/                Language packs (ar.php) and the list of interface texts
├── admin/               Admin panel
├── install/             Web installer (delete after install)
├── includes/            Core functions, layout & database schema (schema.php)
├── assets/              CSS, JS, images
├── uploads/             Uploaded images
└── build/               Tailwind config (only needed to rebuild CSS)
```
