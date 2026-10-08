# Digital Store with CPA Offer Locker

A simple store for your digital products (ebooks, templates, presets, software…).
Visitors get a product **for free** by completing **one offer** from your CPA network
(**OGAds** or **AdBlueMedia**). You choose which networks are switched on from the admin panel.

- No database to set up (uses a single SQLite file)
- Works on normal shared hosting (cPanel, Hostinger, Namecheap…)
- Admin panel: products, CPA networks (on/off, priority or random), postback log, earnings
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

## 6. Test the whole flow

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
| `index.php`, `product.php` | Store front |
| `assets/locker.js` | The offer popup (loads offers, waits for unlock) |
| `api/offers.php` | Gets offers from the active network |
| `postback.php` | Receives lead confirmations from the networks |
| `download.php` | Serves the file after a confirmed lead |
| `admin/` | Admin panel |
| `inc/networks.php` | OGAds and AdBlueMedia connectors. Add new networks here. |
| `data/` | Database and uploaded product files (protected, never public) |
