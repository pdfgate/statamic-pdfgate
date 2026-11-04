# PDFGate for Statamic

Export entries as PDF and compress, watermark or protect PDF assets without leaving the Statamic Control Panel, powered by the [PDFGate PDF API](https://pdfgate.com/api).

Editors get four new actions in the places they already work, and templates get one tag:

- **Export as PDF** in every collection. Pick an entry and download it as a PDF, or pick several and download a zip.
- **Compress PDF**, **Watermark PDF** and **Protect PDF** in every asset container. Each one replaces the file in place or saves a new file next to it.
- `{{ pdfgate:download_url }}` in Antlers gives visitors a "Download as PDF" link for the current page.

Rendering and processing happen on PDFGate's servers. Your site needs no headless browser, no Ghostscript and no PDF library, and it works on any host, including shared hosting and Statamic Cloud-style setups where you cannot install binaries.

![Export as PDF in the entry action menu](https://raw.githubusercontent.com/pdfgate/statamic-pdfgate/main/art/entry-export.png)

## Requirements

- Statamic 5 or 6 on Laravel 12 or 13. A Statamic 5 site still on Laravel 10 or 11 needs to upgrade Laravel first, because the bundled `pdfgate/laravel` package requires 12 or newer.
- PHP 8.2 or newer with `ext-curl` and `ext-json`
- A [PDFGate](https://pdfgate.com) account and API key

## Pricing

PDFGate is a paid service. Every plan starts with a 14-day trial, and paid plans are billed monthly with a number of conversions included and a per-conversion price beyond that. See [pdfgate.com/pricing](https://pdfgate.com/pricing) for current plans.

One conversion is one successful operation. Each entry export, each compress, watermark or protect run, and each PDF generated for a visitor through the download link counts as one. Uploads, downloads and failed requests are free. By default the download route keeps a rendered PDF for 60 minutes and serves it again to other visitors, so a popular page does not spend a conversion per click. Requests made with a `test_` key go to the sandbox and are not charged.

## Quick start

1. Install the addon:

   ```bash
   composer require pdfgate/statamic
   ```

2. Create an API key in the [PDFGate dashboard](https://dashboard.pdfgate.com) and add it to your site's `.env`:

   ```dotenv
   PDFGATE_API_KEY=live_your_api_key
   ```

   Keys that start with `live_` call the production API, keys that start with `test_` call the sandbox API. Sandbox requests are meant for testing and are not charged on a paid PDFGate plan.

3. Open a collection in the Control Panel, pick a published entry and choose **Export as PDF** from its action menu. The PDF downloads.

4. Open an asset container, pick a PDF and choose **Compress PDF**. Keep "Replace the original file" and run it. The file size drops in the listing.

That is the whole setup. The sections below explain each action and the options you can change.

Permissions follow Statamic's own rules. **Export as PDF** shows for users who can view the entry. **Compress**, **Watermark** and **Protect** show for users who can both edit assets and upload to the container, the same rights Statamic requires for its Reupload action, because both result modes write a file into the container.

## Export entries as PDF

Open a collection, pick an entry and choose **Export as PDF** from its action menu. The browser downloads `{slug}.pdf`. Select several entries in the listing to download `entries.zip` with one PDF per entry. The action is available in the list view and in the tree view of structured collections.

By default PDFGate loads the entry's public URL and renders it exactly as a visitor would see it, with your layout, stylesheets and fonts. The page must be reachable from the internet and the entry must be published. Drafts do not show the action in this mode. If the PDF comes back without styles, check that the stylesheet links use the same scheme as the page. A site served over https behind a proxy or load balancer needs Laravel's trusted proxies set, otherwise it writes http links that the renderer drops as mixed content.

For sites that are not public, such as a staging server or a site behind a login, or to export drafts, switch to HTML mode. Statamic renders the entry's template and layout and sends the HTML to PDFGate instead. Stylesheets, fonts and images are still loaded from your `APP_URL`, so they need to be public for the PDF to look like the page. Drafts are only ever exported from the Control Panel. The public download link refuses them in both modes.

```dotenv
PDFGATE_STATAMIC_SOURCE=html
```

Page size, orientation, margins and the other render options are set once in the config file, see below.

## Download link in your templates

```antlers
<a href="{{ pdfgate:download_url }}">Download as PDF</a>
```

The tag returns a signed link to the current entry's PDF, or nothing when no usable API key is set or the route is turned off. Pass `entry="..."` with an entry ID to link another entry. Only links created by the tag work, so nobody can turn your site into a PDF service for arbitrary pages, and requests are limited per IP address. Entries protected with one of Statamic's protection schemes are checked on every download, so a visitor who cannot view the page cannot download it either.

In URL mode a rendered PDF is reused for 60 minutes, because PDFGate loads the page as an anonymous visitor and the result is the same for everyone. HTML renders happen inside the visitor's own request and may contain their session or user data, so they are never shared, and neither are protected entries. Each uncached visitor download spends one conversion. The route, the limit and the cache lifetime are set in the config file. Turning the route off also disables links created earlier, since the route they point to no longer exists.

## Compress, watermark and protect PDF assets

Open an asset container, pick a PDF and choose an action from its menu. All three work on a multi-selection too. Other file types do not show the actions.

![PDF actions in the asset menu](https://raw.githubusercontent.com/pdfgate/statamic-pdfgate/main/art/asset-actions.png)

| Action | What it does | Options |
| --- | --- | --- |
| Compress PDF | Reduces the file size of PDFs uploaded by editors, often by more than half. | None. Linearization for faster web viewing can be turned on in the config file. |
| Watermark PDF | Stamps a text on every page, for drafts, confidential documents or branding. | Text, font size, opacity, rotation and colour. |
| Protect PDF | Encrypts the file with AES-256 so it can only be opened with a password. | Password to open, an optional owner password, and switches to disable printing, copying and editing. |

![Watermark dialog](https://raw.githubusercontent.com/pdfgate/statamic-pdfgate/main/art/watermark-dialog.png)

Each dialog ends with a **Result** choice:

- **Replace the original file** writes the processed PDF over the asset. Its path, metadata and every field that references it stay the same.
- **Save as a new file next to the original** creates `{name}-compressed.pdf`, `{name}-watermarked.pdf` or `{name}-protected.pdf` in the same folder and leaves the original untouched.

When a request fails, the message from PDFGate is shown in the Control Panel together with the file name, and the asset is left as it was.

## Configuration

Publish the config file to change the defaults:

```bash
php artisan vendor:publish --tag=pdfgate-statamic-config
```

| Key | Default | Description |
| --- | --- | --- |
| `entries.source` | `url` | `url` renders the entry's public URL, `html` renders the template and sends the HTML. |
| `entries.options` | A4, print background | Render options applied to every export, for example `orientation`, `margin` or `pageSizeType`. See the [HTML to PDF API reference](https://pdfgate.com/api/html-to-pdf). |
| `entries.download_route` | `true` | Registers the route used by the `{{ pdfgate:download_url }}` tag. |
| `entries.throttle` | `10` | Requests per minute per IP address allowed on the download route. |
| `entries.cache_minutes` | `60` | How long a URL-mode PDF rendered for the download route is reused for other visitors. `0` renders on every request. HTML renders and protected entries are never cached. |
| `assets.compress.linearize` | `false` | Optimise compressed PDFs for page-by-page web viewing. |
| `delete_remote_documents` | `true` | Delete the uploaded and generated documents from your PDFGate account once the file has been delivered to Statamic. |

### Webhook route from the Laravel package

The bundled `pdfgate/laravel` package registers `POST /pdfgate/webhook` for PDFGate webhook deliveries. This addon does not use webhooks. The route is harmless when unused and only answers with an error if something posts to it without a configured secret. To remove it, publish the package config with `php artisan vendor:publish --tag=pdfgate-config` and set `webhook.auto_route` to `false`.

## Need more?

PDFGate also fills PDF forms, adds form fields, flattens documents, extracts form data and sends documents out for e-signature. If you would like any of these inside Statamic, for example signing a contract generated from an entry, [open an issue](https://github.com/pdfgate/statamic-pdfgate/issues) and tell us about your use case and we can build it.

## Links

- [PDFGate](https://pdfgate.com)
- [PDF API overview](https://pdfgate.com/api)
- [API documentation](https://pdfgate.com/documentation)
- [Issues](https://github.com/pdfgate/statamic-pdfgate/issues)

## License

MIT. See [LICENSE](LICENSE).
