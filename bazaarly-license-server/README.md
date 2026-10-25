# Bazaarly license server

A small private page for **you, the seller**. It keeps your purchase codes, who you sold each one to, and which website
uses it. Bazaarly asks this server when someone installs it, so **each code works on one website**.

## Set up (5 minutes)
1. Upload this folder to your own hosting, e.g. `https://yoursite.com/licenses/` (any PHP 8.1+ hosting with SQLite).
   Make sure the `data/` folder is writable (permission 755 or 775).
2. Open that address in your browser and choose a password.
3. **Import codes**: upload the `bazaarly-purchase-codes.csv` file you received. All 500 codes appear as *Free*.
4. Copy the **API address** shown under *Downloads & setup* (it ends with `/api.php`).
5. In Bazaarly, open `includes/license.php` and paste it into this line, then zip the script for your buyers:
   ```php
   const LICENSE_SERVER = 'https://yoursite.com/licenses/api.php';
   ```

## Selling
- The top of the page shows the **next free code** — copy it, send it to the buyer, and click *Mark as sold*
  with their name or email.
- When the buyer installs Bazaarly, the code switches to **In use** and shows their website.
- **Buyer moved to a new domain?** Click *Reset website* on their code, then they enter the code again in
  Bazaarly (Admin → Purchase code).
- **Refund or abuse?** Click *Block*. Their admin panel shows a warning on the next check (their public site keeps working).

## Running out of codes
Use *Generate more codes*, then *Download license-data.php* and put that file into Bazaarly's `includes/` folder
(replacing the old one) before you zip the script for new buyers. Old buyers are not affected.

## Good to know
- Test installs on `localhost`, `*.test` and `*.local` addresses do not use up a code and never contact this server.
- If this server is down, installs still work; Bazaarly registers the website automatically later.
- The API allows 60 requests per hour per IP address, so codes cannot be guessed by trying many.
- Keep `bazaarly-purchase-codes.csv` private — never put it inside the Bazaarly zip.
- Back up the `data/` folder from time to time (it holds the database).
