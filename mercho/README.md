# Mercho: PHP fashion store

A complete online clothing store in plain PHP. It has no frameworks and doesn't need Composer. It includes a 3-step web installer, an admin panel, **PayPal**, **Stripe** and **Cash on Delivery**, and order notifications sent to **WhatsApp**.

## Requirements

- Any normal PHP hosting (cPanel, Plesk, Hostinger, Namecheap…)
- PHP 7.4 or newer (PHP 8.x recommended) with PDO, mbstring and cURL
- A MySQL / MariaDB database, **or** nothing at all (SQLite mode)
- HTTPS (SSL) for live PayPal / Stripe payments

## Install in 5 minutes

1. **Upload** the contents of this `mercho` folder to your hosting (for example into `public_html/`, or into `public_html/shop/` for a sub-folder) using File Manager or FTP. Uploading the zip and using *Extract* is fastest.
2. **Create a database** (optional): cPanel → *MySQL Databases* → create a database and a user, and add the user to the database with *All privileges*. Skip this if you want SQLite.
3. Open **`https://your-domain.com/install/`** in your browser and follow the steps:
   - Step 1 checks your server.
   - Step 2 is for the database details (or choose SQLite).
   - Step 3 is for your store name, currency, WhatsApp number and admin login.
4. **Delete the `install` folder** from your server when it's done.
5. Log in at **`https://your-domain.com/admin/`**.

The demo products and images are placeholders. Replace them with your own from **Admin → Products**.

## Payments (Admin → Settings → Payments)

You can turn on **any combination** of the three methods. Checkout only shows the ones that are enabled.

| Method | What you need |
|---|---|
| **Cash on Delivery** | Nothing. Tick *Enable*. Orders are sent to your WhatsApp (see below). |
| **PayPal** | Get a *Client ID* and *Secret* at developer.paypal.com → Apps & Credentials. Test with **Sandbox** first, then switch to **Live**. |
| **Stripe** | Get the *Secret key* at dashboard.stripe.com → Developers → API keys. Use `sk_test_…` to test and `sk_live_…` for real payments. |

Online orders are only marked **Paid** after the store checks the payment with PayPal or Stripe. If a customer leaves the PayPal or Stripe page without paying, the order stays hidden under *Orders → show unpaid*.

> PayPal only accepts certain currencies. Check that your store currency (Settings → General → Currency code) is supported.

## WhatsApp orders (Admin → Settings → WhatsApp)

Enter your number with the country code, digits only (for example `212612345678`). Then choose how Cash on Delivery orders reach you:

1. **Customer sends it (default, no setup):** after the order is placed, WhatsApp opens on the customer's phone with the full order details already written and addressed to your number. The customer taps *Send*.
2. **Automatic with CallMeBot (free):** the order is pushed to your WhatsApp without the customer doing anything. To get an API key, save **+34 644 66 32 62** in your contacts, send it *"I allow callmebot to send me messages"* on WhatsApp, and paste the key you receive.
3. **Automatic with the WhatsApp Business Cloud API (Meta):** for businesses that already use Meta's API.

Every order is also saved in **Admin → Orders**, so nothing is lost even if a WhatsApp message isn't sent. Use **Send test message** to check your setup. You can also send PayPal and Stripe orders to WhatsApp.

## Printful (print on demand)

You can sell Printful products in the store. Printful prints and ships each order to your customer, so you don't keep any stock.

1. In Printful, create a store of type **Manual order platform / API**. Design your products there and give every product a **retail price**. Set that store's currency to the same currency as this store.
2. In Printful, go to **Settings → Developers (API) → Create token**. Allow *Orders*, *Sync products*, *Webhooks* and *Stores*.
3. In this store, go to **Admin → Printful**, paste the token, tick **Enable Printful** and click **Save & test connection**.
4. Click **Sync products now**. Your Printful products appear in the store with their mockup images, sizes, colors and prices. Each size/color option keeps its own price (for example, 2XL can cost more). Click sync again any time you change products in Printful.
5. Click **Turn on automatic shipping updates** (your site needs https). When Printful ships an order, the order is marked **Shipped** and the tracking link appears for the customer on the Track Order page.

**Orders.** By default, paid PayPal/Stripe orders are sent to Printful automatically. Cash on delivery orders are sent with the **Send to Printful** button on the order page once you've confirmed them with the customer. You can change both in Admin → Printful. Orders arrive in Printful as **drafts** unless you tick *Submit orders for production immediately*. Printful charges your Printful billing method for each order it produces.

**Products.** Imported products keep your own edits to description, category, home page sections and old price. Name, images, sizes, colors and prices always come from Printful. Products you delete in Printful are hidden in the store on the next sync.

When the cart contains a Printful product, checkout asks for a postal / ZIP code, because Printful needs a full address to ship.

## Coupons, popup, delivery options and more

- **Coupons** (Admin → Coupons): percentage, fixed amount or free delivery. Each coupon can have a minimum order, a usage limit and start/end dates. Customers enter the code in the cart or at checkout. A use is counted when the order is confirmed.
- **Subscribe popup** (Settings → Popup): turn it on or off, choose the coupon to show, and edit the image, badge, title (put a word in [brackets] to color it), text and link. Visitors can copy the code or enter their email: they are added to Subscribers, the code is applied to their cart and emailed to them if your server can send email. Add `?popup=1` to any store address to preview it.
- **Delivery options** (Admin → Delivery options): e.g. Standard (free) and Express ($15). In each product you can switch delivery off (no delivery fee) or choose which options are available for it. The customer picks one at checkout. "Free delivery over" in Settings still applies.
- **Custom product options**: in a product, add options like *Material → Cotton, Silk +5*. Values with `+amount` add to the price.
- **Subcategories**: in Categories, choose a parent category. Subcategories show in the Shop menu and filters, and a main category also shows its subcategories' products.
- **Visual editor** for product descriptions and pages/blog posts: colors, font sizes, headings, lists, tables, images (uploaded to your server), videos, and an HTML view (`</>` button).
- **Product page**: short description under the title, then the **Description / Additional Information / Reviews** tabs. Customers can write reviews, which you approve in Admin → Product reviews (or publish directly: Settings → General).
- **Contact page** (`contact.php`): edit the text, image, address, phone, opening hours and an optional Google map in Settings → Contact page. Messages arrive in Admin → Messages (and by email if your server can send email).
- **Menus** (Admin → Menus): edit the header menu (with your own dropdowns, or automatic Categories/Brands dropdowns), the top bar links, the footer columns and the copyright line.
- **Back to top** button on every page.

## Selling, marketing & growth tools

- **Cash on delivery protection** (Settings → Payments): maximum COD order amount, maximum COD orders per phone per day, and an optional *"Ask the customer to confirm on WhatsApp"*. When it is on, new COD orders are marked **Awaiting confirmation** until you click *Confirm* in the order. In an order you can also **Block** the customer (phone, email and IP). Blocked customers cannot order; manage the list in Admin → **Block list**.
- **Ads pixels** (Settings → **Tracking & Cookies**): paste your **Facebook/Meta Pixel ID**, **TikTok Pixel ID** and/or **Google Analytics 4** ID (G-XXXX). The store sends *View product*, *Add to cart*, *Start checkout* and *Purchase* (with the order value) automatically. A "head code" box lets you add other codes (e.g. Google Search Console verification).
- **Cookie notice** (same tab): a bar with Accept / Decline. When it is on, the pixels only load after the visitor clicks Accept.
- **Stock per size / color** (product edit → *Stock per size & color*): tick the box and a table appears with every combination (e.g. M / Red). Set the stock and an optional price for each one. Sold-out combinations cannot be bought, and stock goes down when an order is placed.
- **Invoices, packing slips & CSV** : in an order, click **Invoice** or **Packing slip** (print or save as PDF). Customers can download their invoice from My account. Admin → Orders → **Export CSV** downloads the orders (opens in Excel).
- **Reports** (Admin → **Reports**): revenue, orders, average order and new customers for a date range (compared with the period before), a daily revenue chart, best-selling products, top cities and payment methods.
- **SEO**: each product has its own page address (slug), SEO title and description. Product pages, categories and blog posts include Google rich data and Facebook/WhatsApp share previews. `sitemap.xml` and `robots.txt` are created automatically: submit `https://yourdomain.com/sitemap.xml` to Google Search Console. **Clean links** (`/product/white-shirt` instead of `product.php?slug=…`): Settings → SEO. The store checks that your server supports them (Apache with mod_rewrite, which almost all cPanel hosting has) before turning them on.
- **Abandoned cart reminders** (Settings → General): when a logged-in customer, or a visitor who typed their email at checkout, leaves without ordering, one reminder email is sent after X hours with a button that refills the cart. You can attach a coupon to it. See them in Admin → **Abandoned carts**. Reminders are sent when you open the admin panel. To send them on time even when you don't log in, add a cron job (cPanel → Cron Jobs, every 15 minutes) with the command shown in Admin → Abandoned carts, e.g. `curl -s "https://yourdomain.com/cron.php?key=YOURKEY"`.
- **Delivery prices by zone** (Admin → Delivery options): each option can be limited to some countries and/or cities, and can have its own *free over* amount. Example: *Casablanca $3*, *Rest of Morocco $5*, *International $15*. At checkout the customer sees only the options for their address, and the price updates while they type. A city option wins over a country option, which wins over an "everywhere" option. If no option matches an address, it cannot be ordered.

## Speed, import, staff & backup

- **Faster images**: uploaded photos are resized automatically (1600 px wide by default), turned the right way up and compressed. A 6 MB phone photo becomes about 400 KB. Settings → General → *Images & speed*. The `.htaccess` file also turns on compression and browser caching.
- **Bulk import / export** (Admin → **Import / Export**): download the sample CSV, fill it in Excel or Google Sheets (one product per row), and upload it. Products with the same slug or name are updated, others are created. Images can be links and are downloaded to your server. Categories like `Women > Dresses` are created automatically. **Export** downloads all your products in the same format.
- **Staff accounts** (Admin → **Staff**, owner only): add team members with their own login and choose what they can see: Orders, Customers, Products, Marketing, Content, Reports, Settings. For example, give an order-confirmation agent only *Orders*. Staff never see Staff or Backup. Revenue figures are only shown to people with *Reports*.
- **Backup** (Admin → **Backup**, owner only): download the database as a `.sql` file (import it in phpMyAdmin to restore), the SQLite file if you use SQLite, and a `.zip` of your uploaded images. Make a backup before big changes and keep a copy on your computer.

**Updating from an older version**: upload the new files over the old ones (keep `config.php`, `data/` and `uploads/`). The database is updated automatically the first time you open the store. Existing admin accounts become owners.

## Customer accounts & emails

- **Accounts**: customers can register, log in, reset a forgotten password and use **My account** (orders with status and tracking, account details, saved address, password). Their details are filled in automatically at checkout. You can see them in **Admin → Customers**, where you can disable an account or set a new password.
- **Guest checkout**: Settings → General → *Allow guest checkout*. When it is off, customers must log in or create an account before they can order. You can also turn customer accounts off completely.
- **Email (SMTP)**: Settings → **Email**. Choose *SMTP* and enter your hosting email account (cPanel → Email Accounts → Connect Devices shows the values):
  - Host: usually `mail.yourdomain.com`
  - Encryption **SSL** with port **465** (or TLS with port 587)
  - Username: your full email address, plus its password
  - Sender email: the same address

  Click **Send test email** to check the settings. If something is wrong, the server's answer is shown.
- **Notifications** (each can be turned off): order confirmation to the customer, new order to you, order status changes to the customer (also when Printful ships), welcome email, password reset, contact messages and new reviews to you.

## What you can manage in the admin

- **Orders:** view, change status (awaiting confirmation → pending → processing → shipped → delivered), mark as paid, message the customer on WhatsApp, print invoice/packing slip, block a customer, export CSV
- **Products:** price, old price (shows a discount badge), sizes, colors, stock (empty = unlimited), main image and gallery. You also choose which home sections a product appears in: *New Arrivals*, *Trending Now* or *Deal of the Days* (the first 2 are shown).
- **Categories:** the "Best For Your Categories" slider and the Shop menu
- **Reviews:** the "Our Happy Customers" section
- **Pages & Blog:** About, FAQ, Terms, Privacy… and blog posts
- **Subscribers:** newsletter emails (CSV export)
- **Settings:** logo, **main color of the website** (red by default, any color with one click), currency, delivery fee, free delivery limit, announcement bar, header promo box ("Get 30% Discount Now · SALE"), hero texts and image, categories slider, Deal of the Days (title, text, expiry date), Instagram, social links. The "Elevate Your Wardrobe" banner and the newsletter box are hidden by default, and you can switch them back on in Settings → Home page.

## Customers can

Browse and filter products by style, brand, price and sale. They can search, add products to a wishlist, use the cart, check out as a guest and track an order by order number and phone. The store also has a dark mode and is fully mobile friendly.

## Files

```
index.php, shop.php, product.php, cart.php, checkout.php, pay.php, order-success.php, track.php …
admin/        admin panel
install/      installer (delete after installing)
includes/     core code (protected)
assets/       css, js, demo images
uploads/      your uploaded images (scripts blocked)
data/         SQLite database if used (protected)
config.php    created by the installer
```

## Reinstall / move

- **Reinstall:** delete `config.php` and upload the `install` folder again.
- **Moved to another folder or domain:** edit `BASE_PATH` in `config.php` (`''` for the domain root, `'/shop'` for a sub-folder).
- **Nginx:** see `nginx.conf.example`. Apache uses the included `.htaccess` files.
