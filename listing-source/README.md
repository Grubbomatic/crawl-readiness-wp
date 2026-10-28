# wordpress.org listing assets

These go in the plugin's SVN `assets/` folder (beside `trunk/` and `tags/`, not inside them). They are kept out of the plugin zip by `.gitattributes`.

| File | Where it shows |
|---|---|
| `icon-128x128.png`, `icon-256x256.png` | The plugin's icon in the directory and in wp-admin's Add Plugins screen |
| `banner-772x250.png`, `banner-1544x500.png` | The header of the plugin's page (the larger one on high-density screens) |
| `screenshot-1.png` … `screenshot-3.png` | The Screenshots tab, captioned by `== Screenshots ==` in `readme.txt`, in the same order |

## How they were made

- **Icon and banner:** `source/icon.html` and `source/banner.html`, rendered with headless Chrome at exact size: 256×256 (the 128 icon is the same page at half scale) and 772×250 (the 1544×500 banner at double scale). Fonts are Space Mono and Inter from Google Fonts, as on crawlreadiness.com.
- **Screenshots:** a fresh WordPress in WordPress Playground (Twenty Twenty-Five, the site named "Demo Site" with three short bakery posts), 1280×900. The check results come from crawlreadiness.com's own checking code, run on that site's actual robots.txt, homepage and files, and are stored through the plugin's own code. A Playground site lives in the browser, so the live service can't fetch it. The address shown, `demo-site.example`, is a reserved example domain.
  1. Before: the plugin's switches off, 82 "B AI Ready", each finding marked "A switch below fixes this".
  2. The Fixes panel with the switches on.
  3. The dashboard widget after the fixes: 100 "A AI Ready".
