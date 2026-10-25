# Digital Store with CPA Offer Locker

## Overview

A complete, self-hosted store for giving away digital products (ebooks, templates, presets, graphics, software, any file) in exchange for completing **one offer** from your CPA network.

Visitors pick a product, complete a short offer (a survey, an app install, a sign-up), and the download unlocks automatically as soon as the network confirms the lead through a server-to-server postback. You earn the CPA payout; the visitor gets the file for free.

- Built for **OGAds** and **AdBlueMedia**. Switch each network on or off at any time, use them by priority or at random, and route visitors to the best network for their country.
- **No database server needed.** Everything runs on PHP with a single SQLite file, so it installs on ordinary shared hosting (cPanel, Hostinger, Namecheap…) in a few minutes.
- **Modern, mobile-friendly design** with light and dark mode, 8 color themes, 7 fonts and your own logo.
- **Full admin panel** with a dashboard, earnings reports, product management, a link locker, an email list, Telegram alerts and more.
- **Optional demo content** at install, so you can see the finished store before adding your own products, then remove it in one click.

---

## 🌐 Public Site Features

### Store & navigation
- Sticky header with your logo (adjustable height), category menu, custom pages, a search box and a Contact link
- Optional **announcement bar** at the top of every page, with a link
- Mobile menu, a back-to-top button and a light/dark mode switch for visitors
- Multi-column footer with an about text, categories, pages and social media icons (Facebook, Instagram, YouTube, X, Telegram, TikTok)

### Home page
- **Hero banner** with title, text, a large search box and live stats (items, categories, downloads); gradient or your own background image
- **Category buttons** with item counts
- **Featured Items** section (choose how many)
- **"How it works"** three-step strip
- **One section per category**, each with a "View all" link
- **Latest Items** with page numbers

### Products
- Product cards with cover image, category, **badges** (NEW, HOT, BESTSELLER…), a featured star, download count and likes
- Product page with breadcrumb, description (plain text or HTML), **picture gallery** (up to 8 extra images), **YouTube video preview** and a **details table** (file size, format, version, works with)
- **Like button**, share buttons (Facebook, X, WhatsApp, Telegram, copy link) and **"You may also like"** related products
- **Search** across titles, descriptions and categories
- Category pages with page numbers
- **Readable links** such as `/product/golden-hour-presets` (optional)

### Offer locker
- Animated popup with the product picture and a **1-2-3 step indicator** (Pick an offer → Complete it → Download)
- Shows offers matched to the visitor's **country and device**
- Unlocks **automatically** when the network confirms the lead; visitors can finish the offer in another tab and come back
- Remembers progress if the page is refreshed
- Secure download: the real file location is never shown, and download links expire (24 hours by default)
- Works with uploaded files or external links (Google Drive, Dropbox, MediaFire…) for big files

### Link locker
- Lock **any link** (Mega, Google Drive, a Discord invite, a coupon page…) behind an offer
- A short, shareable address for TikTok, YouTube, Telegram or Instagram (`/l/your-code`)
- The hidden link is only revealed after the offer is confirmed

### Pages & contact
- Editable pages (Privacy Policy and Terms of Use included; add About, FAQ or anything else)
- **Contact us** page with a form, spam protection and your social links
- **Email signup box** ("Get new freebies first")
- **Cookie consent banner** that can hold back your analytics code until the visitor accepts

### SEO & performance
- Page titles, meta descriptions, Open Graph and Twitter preview tags for social sharing
- Canonical links and an automatic **XML sitemap** (`sitemap.php`) for Google Search Console
- Lightweight: no frameworks, fast on cheap hosting
- Accessible markup, keyboard support and reduced-motion support

### Ads
- Three **ad slots** (top of every page, product pages, bottom of every page) for AdSense, Adsterra, Monetag or any ad code. Ads never appear inside the offer popup.

---

## ⚡ Admin Panel Features

### Dashboard
- Stat cards: product views, lockers opened, offers completed, conversion rate and earnings
- **Chart** of completed offers for the last 14 days, with hover details
- **Getting started checklist** that tracks your setup progress
- Top products, earnings by network and the latest unlocks with country flags
- Reminders for unread messages, demo content and networks that are switched off

### Earnings & tracking
- **Reports** for the last 7/30/90 days, last year or custom dates, broken down by **day, product/link, network, country and offer**
- **Offer click tracking**: see which offers visitors click and which ones complete
- **CSV export** of every report
- **Postback log** showing every call from your networks, to troubleshoot unlocks

### CPA networks
- **OGAds** and **AdBlueMedia** built in, each with an **On/Off switch**
- **Priority** mode (fall back to the next network when one has no offers) or **Random** mode (split traffic)
- **Country routing**: "Only for these countries" and "Never for these countries" for each network
- A ready-to-copy **postback URL** for each network, and a **Test** button that fetches live offers
- Editable API addresses and tracking macros, so you can adapt to changes without editing code

### Products & content
- Products list with search, category filter, thumbnails, **views, unlocks, conversion rate and downloads** for each product, a one-click featured star and show/hide
- Product editor with a live image preview, badge, category, **gallery**, **YouTube video**, file details, readable address and **Publish later** scheduling
- File upload (stored privately with a random name) or an external download link; file size and format filled in automatically
- **CSV import** for adding many products at once: a downloadable template, cover pictures fetched from links, and new categories created automatically
- **Categories**: add, rename, reorder and delete; choose for each one whether it gets a home page section and a header menu link
- **Pages**: add, edit and delete pages; show each in the header and/or footer
- **Link locker** manager with views, unlocks, conversion and earnings for each link

### Audience
- **Messages** inbox for the contact form, with read/unread, reply by email and an unread counter in the menu
- Optional email copies of new messages
- **Subscribers** list with search, delete and CSV export (for Mailchimp, Brevo, MailerLite…)

### Alerts, ads & email
- **Telegram alerts** for completed offers (payout, product, network, country), new messages and new subscribers, with a test button
- Ad slot code boxes
- Email signup box settings
- Cookie banner settings

### Appearance
- Logo upload with a height slider, plus favicon
- **8 one-click color themes** or your own main and second colors
- Automatic, always-light or always-dark theme; optional custom background, card and text colors
- **7 fonts** and adjustable corner roundness
- Hero title, text, label and background image
- Switches for each home page section, section titles and items per page
- Announcement bar, footer copyright line and a custom CSS box

### Settings
- Store name, footer about text and site language
- Contact page text and email
- Offer popup texts, number of offers and download link lifetime
- SEO description, social media links and **tracking code** (Google Analytics, Facebook Pixel, Microsoft Clarity…)
- **Readable links** with an automatic test of your hosting before they are switched on
- **Database backup** download at any time
- **Demo content**: add or remove the 12 sample products, 4 categories and About/FAQ pages

### Security & protection
- Secret-protected postback URL (with an optional IP allow list), which you can regenerate at any time
- **Login lockout** after 5 wrong passwords (15 minutes)
- **Two-step login** with Google Authenticator, Microsoft Authenticator or Authy, with a simple recovery if you lose your phone
- **Abuse limits**: maximum lockers per visitor per hour, and **blocked countries**
- Form protection (CSRF tokens) on every admin action, hashed passwords, and spam protection on public forms
- The database file gets a random name, and product files are stored outside public reach
- Cloudflare support for real visitor IPs and countries

### Installation & updates
- One-page **installer** with a server check, admin account setup and optional demo content
- **Automatic database upgrades**: upload a new version over the old one and your products, settings, keys and stats are kept

---

## 💻 System Requirements

| Requirement | Details |
|---|---|
| **PHP** | 7.4 or newer (PHP 8.1+ recommended) |
| **PHP extensions** | `pdo_sqlite`, `curl`, `mbstring` (enabled by default on almost every host) |
| **Database** | None needed. Uses a built-in SQLite file (no MySQL) |
| **Web server** | Apache or LiteSpeed (most cPanel hosts) for the built-in `.htaccess` protection and readable links. Nginx works too; readable links and folder protection then need the rules added by your host |
| **Writable folders** | `data/`, `data/files/` and `uploads/` (permission 755 or 775) |
| **HTTPS** | Strongly recommended (free SSL from your host or Cloudflare) |
| **Outgoing connections** | The server must be able to call the CPA network APIs (and Telegram, if you use alerts) |
| **Public address** | A real domain or subdomain. CPA networks must reach your postback URL, so `localhost` won't work |
| **Upload size** | Limited by your host's `upload_max_filesize`; use external links (Drive, Dropbox…) for bigger files |
| **CPA account** | An OGAds and/or AdBlueMedia publisher account with API access |
| **Optional** | Cloudflare (or a host with GeoIP) for country routing, blocked countries and country reports |

---

## Instructions

### 1. Upload
1. Download the `digital-store` folder (or the ZIP file).
2. In your hosting **File Manager**, open `public_html` (or a sub-folder such as `public_html/free`).
3. Upload the files and extract them.
4. Make sure `data/`, `data/files/` and `uploads/` are writable (755, or 775 if the installer asks).

### 2. Install
1. Open `https://your-domain.com/install.php`.
2. Check that every item shows ✔.
3. Enter your store name, an admin username and a password (8+ characters).
4. Leave **"Add demo products"** ticked to see the finished store right away.
5. Click **Install**, then **delete `install.php`** from your hosting.

### 3. Connect your CPA networks
1. Log in at `https://your-domain.com/admin/`.
2. Open **CPA Networks**.
3. **OGAds**: paste your API key and switch it **On**.
4. **AdBlueMedia**: paste your User ID and API key and switch it **On**.
5. Click **Save networks**, then **Test this network**. You should see offers listed.
6. Copy each network's **postback URL** into that network's dashboard (Postback / Global Postback).
7. Use the network's "Test postback" button, then check **Admin → Postback log**.
8. Optional: choose **Priority** or **Random** mode, and fill in country routing.

### 4. Add your products
- **One by one**: **Products → Add product**. Fill in the title, description, cover image and file (upload, or a Drive/Dropbox link), then the category, badge, gallery and video.
- **In bulk**: **Import products**. Download the template, fill it in Excel or Google Sheets, and upload it.
- **Locked links**: **Link locker → New locked link**, then share the short address.

### 5. Make it yours
- **Appearance**: logo, colors, font, hero banner, home page sections and the announcement bar.
- **Categories** and **Pages**: organize your store and edit Privacy Policy, Terms, About and FAQ.
- **Settings**: SEO description, social links, tracking code and contact email.
- **Alerts, ads & email**: Telegram alerts, ad slots, the email signup box and the cookie banner.

### 6. Test the whole flow
1. While logged in as admin, open any product and click **Download for free**.
2. Click **"Simulate completed offer"** (shown only to admins).
3. The popup should switch to **Download now** within a few seconds.

### 7. Before you go live
- Remove the demo content: **Settings → Demo content → Remove demo content**.
- If your site is behind Cloudflare, tick **"My site is behind Cloudflare"** in Settings.
- Switch on **Two-step login** in Settings.
- Optional: turn on **Readable links**, and submit `https://your-domain.com/sitemap.php` to Google Search Console.
- Review your Privacy Policy, and turn on the cookie banner if you have EU/UK visitors.
- Check your CPA networks' rules on traffic sources, incentives and showing other ads.

### Updating to a new version
1. Download a **backup** (**Settings → Backup**) and copy your `data/` and `uploads/` folders.
2. Upload the new files over the old ones. Don't delete `data/` or `uploads/`, and don't upload `install.php` again.
3. Open your store. The database upgrades itself automatically.

### Troubleshooting
| Problem | What to do |
|---|---|
| No offers in the popup | Check that a network is **On** and its key is correct, then use **Test this network**. Logged-in admins see the exact error in the popup. |
| Download doesn't unlock | Open **Postback log**. "wrong secret" means an old or incomplete postback URL; "unknown token" means the tracking macro doesn't match. If nothing appears, the network can't reach your site. |
| Everyone sees offers for the wrong country | Tick **"My site is behind Cloudflare"** in Settings. |
| Readable links show "Not Found" | Switch them off in **Settings → Readable links**. Your host doesn't support the rewrite rules. |
| Lost your two-step login phone | Create an empty file named `disable-2fa` in the `data/` folder with your File Manager, then open the login page. |
| Upload fails for big files | Use a Google Drive, Dropbox or MediaFire link instead of uploading. |
