# Digital Store with CPA Offer Locker

A simple store for your digital products (ebooks, templates, presets, software…).
Visitors get a product **for free** by completing **one offer** from your CPA network
(**OGAds** or **AdBlueMedia**). You choose which networks are switched on from the admin panel.

- No database to set up (uses a single SQLite file)
- Works on normal shared hosting (cPanel, Hostinger, Namecheap…)
- Admin panel: products, categories, pages, contact messages, appearance, CPA networks (on/off, priority or random), postback log, earnings
- Home page with **Featured Items**, a section per category, and **Latest Items**
- Your own logo (with adjustable height), colors, light/dark theme and header menu
- Editable pages (Privacy Policy, Terms, About…) and a **Contact us** page
- Downloads unlock only when the network confirms the lead (server-to-server postback), so the offers can't be skipped
- The real file location is never shown; download links expire (24h by default)

---

## 1. What you need

- Hosting with **PHP 7.4 or newer** (PHP 8 is best) and the **pdo_sqlite** and **curl** extensions. Almost every host has these on by default.
- A domain with **HTTPS** (free SSL from your host is fine).

> **Do not use localhost.** OGAds and AdBlueMedia must be able to reach your postback URL from the internet.

## 2. Upload

1. Download this `digital-store` folder (on GitHub: **Code → Download ZIP**, then take the `digital-store` folder out of the ZIP).
2. In your hosting **File Manager**, open `public_html` (or a sub-folder like `public_html/free`).
3. Upload the **contents** of `digital-store` there and extract it if you uploaded a ZIP.
4. Make sure these folders are writable (permission **755**, or **775** if the installer complains):
   `data/`, `data/files/`, `uploads/`

## 3. Install

1. Open `https://your-domain.com/install.php` in your browser.
2. All checks should show ✔. Enter a store name, an admin username and a password, then click **Install**.
3. **Delete `install.php`** from your File Manager. It already locks itself, but it's safer to remove it.

## 4. Connect your CPA networks

Log in at `https://your-domain.com/admin/` and open **CPA Networks**.

### OGAds
1. Paste your **API key** (OGAds dashboard → API. It looks like `12345|abc…`).
2. Leave *Offers API URL*, *Tracking parameter* (`aff_sub4`) and *ctype* as they are.
3. Tick **On** and click **Save networks**.
4. Copy the **Postback URL** shown in the OGAds box. In OGAds go to **Postback / Global Postback**, paste it and save.
5. Click **Test this network**. You should see "X offer(s) returned".

### AdBlueMedia
1. Paste your **User ID** and **API key** (AdBlueMedia dashboard → API / Offer Feed).
2. If your AdBlueMedia API page shows a feed URL different from the default, paste it into *Offer feed URL*,
   replacing your ID, your key and the `s1` value with `{user_id}`, `{api_key}` and `{token}`.
3. Tick **On**, **Save**, then paste the **Postback URL** into AdBlueMedia's postback settings.
4. Click **Test this network**.

### Choosing networks
- **On / Off**: only networks that are on show offers. Switch them any time.
- **Priority mode**: the network with the lowest priority number is tried first. If it has no offers for a visitor's country or device, the next one is used.
- **Random mode**: visitors are split between the networks that are on, and it still falls back if one has no offers.

### Check the postback macros
The postback URL ends like this:

```
...&token={aff_sub4}&payout={payout}&offer={offer_id}
```

The words in `{ }` are **macros** the network replaces with real values. Compare them with the list on your
network's postback page. If the network uses a different name (for example `{sub1}` instead of `{s1}`), change the
**Tracking parameter** field so the offer links and the postback use the same name.

Use the network's **"Test postback"** button, then open **Admin → Postback log**:
- `unlocked` / `ignored: unknown token`: the postback arrives (a test postback has no real token, so "unknown token" is expected).
- `rejected: wrong secret`: you copied an old or incomplete postback URL.
- Nothing at all: the network can't reach your site (check HTTPS, firewall or Cloudflare "Under attack" mode).

## 5. Add products

**Admin → Products → Add product**: title, description, cover image, and either:
- **Upload a file** (limited by your hosting's upload size, often 2–128 MB), or
- **File link**: a Google Drive / Dropbox / MediaFire link for big files. Visitors are sent to it only after unlocking.

## 6. Customize your store

### Appearance (Admin → Appearance)
- **Logo**: upload a PNG/JPG/WEBP and drag the **height** slider (16–200 px). Tick "Show the store name next to the logo" if you want both.
- **Colors**: choose Automatic / Always light / Always dark, and pick your **main color** for buttons and links.
  Tick "Use my own background and text colors" to set the page, card and text colors yourself. **Reset colors** goes back to the default.
- **Header menu**: show or hide categories and the Contact link.
- **Home page**: welcome title and text, the titles of the Featured and Latest sections, how many products each shows, and products per page.
- **Custom CSS** for advanced tweaks.

### Home page layout
1. Welcome title
2. **Featured Items**: tick **Featured** when editing a product, or click the ☆ in the Products list. The newest 3 featured products are shown (change the number in Appearance).
3. **One section per category** that has **Home** switched on, with a "View all →" link.
4. **Latest Items**: every product, newest first, with page numbers.

### Categories (Admin → Categories)
Add, rename, reorder (lower number = first) or delete categories. For each one choose:
- **Home**: show a section for it on the home page
- **Header**: show it in the top menu

Pick a product's category when you add or edit it. Deleting a category keeps its products; they just have no category.

### Pages (Admin → Pages)
Privacy Policy and Terms of Use are created for you. Add pages like About or FAQ, edit them, or delete them, and choose whether each shows in the **header** or the **footer**.
Write plain text (an empty line starts a new paragraph) or HTML.

### Contact us
The **Contact us** page (`contact.php`) has a form with spam protection. Messages appear in **Admin → Messages**, and the menu shows how many are unread.
To also get them by email, open **Settings → Contact us page**, enter your email and tick "Email me a copy".

## 7. Test the whole flow

1. While logged in as admin, open a product and click **Download for free**.
2. The locker shows the offers. Under them there's an **Admin only: Simulate completed offer** button.
3. Click it. Within 5 seconds the locker turns into **Download now**.

Real visitors see the same thing after they finish an offer and the network sends the postback (usually seconds, sometimes a few minutes).

## Tips

- **Cloudflare users**: tick *"My site is behind Cloudflare"* in **Settings**, or every visitor looks like they come from a Cloudflare IP and gets the wrong country's offers.
- **Privacy**: a privacy page is linked in the footer. Visitor IPs and browser types are sent to the CPA network to pick offers, so keep that page.
- Only give away products you own or have the right to distribute, and follow each network's rules on traffic and incentives.
- **Never share** your API keys or postback URL publicly. If the postback URL leaks, use **Settings → Create new secret** and paste the new URL into your networks.

## Files

| Path | What it is |
|---|---|
| `index.php`, `product.php`, `category.php` | Store front |
| `page.php`, `contact.php` | Custom pages and the contact form |
| `assets/locker.js` | The offer popup (loads offers, waits for unlock) |
| `api/offers.php` | Gets offers from the active network |
| `postback.php` | Receives lead confirmations from the networks |
| `download.php` | Serves the file after a confirmed lead |
| `admin/` | Admin panel |
| `inc/networks.php` | OGAds and AdBlueMedia connectors. Add new networks here. |
| `data/` | Database and uploaded product files (protected, never public) |

## Updating to a new version

1. Back up your `data/` and `uploads/` folders (they hold your database, files and images).
2. Upload the new files over the old ones. **Don't upload `install.php`**, and don't delete `data/` or `uploads/`.
3. Open your store. The database upgrades itself on the first visit; your products, settings and keys are kept.
