# Grubbomatic Crawl Readiness for WordPress

Check how AI crawlers see your site, then set it up the way you intend, from inside WordPress: llms.txt, AI crawler rules with a Content-Signal line, meta and Open Graph tags, JSON-LD and agents.json.

**Install:** [wordpress.org/plugins/grubbomatic-crawl-readiness](https://wordpress.org/plugins/grubbomatic-crawl-readiness/), or search for "Grubbomatic Crawl Readiness" under Plugins → Add New.

## What it does

- Runs the free readiness check from [crawlreadiness.com](https://www.crawlreadiness.com) against your site and shows the score, the verdict and the findings under Tools → AI Readiness, with a dashboard widget for the latest score.
- **llms.txt**, built from your own pages and posts and served at `/llms.txt`. Nothing is written to disk.
- **AI crawlers in robots.txt:** a Content-Signal line that states your AI preferences and, if you choose "allow AI search and assistants, block AI training", a block for each crawler that only collects training data. It never un-blocks a crawler: rules you or another plugin wrote stay in force.
- **Meta description, Open Graph tags and JSON-LD** (Organization, WebSite, Article), only when no SEO plugin already writes them.
- **agents.json**, optional, at `/.well-known/agents.json`.

The plugin talks to one external service, crawlreadiness.com, and only when you press "Check this site": it sends your home URL, and your API key if you set one. See "External services" in `readme.txt`, the [privacy policy](https://www.crawlreadiness.com/privacy) and the [terms](https://www.crawlreadiness.com/terms).

## This repository

`readme.txt` is the wordpress.org listing; this file is for GitHub and is left out of the plugin package. Pushing a version tag such as `1.0.3`, matching the plugin header and the readme's Stable tag, deploys that version to wordpress.org through `.github/workflows/deploy.yml`. Every PHP file is linted on pull requests and again before a deploy.

Support: [support@crawlreadiness.com](mailto:support@crawlreadiness.com) · License: GPLv2 or later
