# Velora Store: PHP fashion store

A complete online clothing store in plain PHP. It has no frameworks and doesn't need Composer. It includes a 3-step web installer, an admin panel, **PayPal**, **Stripe** and **Cash on Delivery**, and order notifications sent to **WhatsApp**.

## Requirements

- Any normal PHP hosting (cPanel, Plesk, Hostinger, Namecheap…)
- PHP 7.4 or newer (PHP 8.x recommended) with PDO, mbstring and cURL
- A MySQL / MariaDB database, **or** nothing at all (SQLite mode)
- HTTPS (SSL) for live PayPal / Stripe payments

## Install in 5 minutes

1. **Upload** the contents of this `velora-store` folder to your hosting (for example into `public_html/`, or into `public_html/shop/` for a sub-folder) using File Manager or FTP. Uploading the zip and using *Extract* is fastest.
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

## What you can manage in the admin

- **Orders:** view, change status (pending → processing → shipped → delivered), mark as paid, message the customer on WhatsApp
- **Products:** price, old price (shows a discount badge), sizes, colors, stock (empty = unlimited), main image and gallery. You also choose which home sections a product appears in: *New Arrivals*, *Trending Now* or *Flash Sale*.
- **Categories:** the "Browse by Dress Style" blocks
- **Reviews:** the "Our Happy Customers" section
- **Pages & Blog:** About, FAQ, Terms, Privacy… and blog posts
- **Subscribers:** newsletter emails (CSV export)
- **Settings:** logo, currency, delivery fee, free delivery limit, announcement bar, hero texts and image, flash sale end date, banner, Instagram, social links

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
