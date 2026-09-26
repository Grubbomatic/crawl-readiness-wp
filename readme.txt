=== Crawl Readiness – AI Crawler Check & llms.txt ===
Contributors: crawlreadiness
Tags: ai, llms.txt, robots.txt, schema, open graph
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Check whether AI crawlers can read your site, then fix what they need in one click: llms.txt, AI crawler rules, meta and Open Graph tags, JSON-LD.

== Description ==

ChatGPT, Claude, Perplexity and Google AI answer questions by reading websites. If they cannot read yours, you are not in the answer.

**Crawl Readiness** runs the free readiness check from [crawlreadiness.com](https://www.crawlreadiness.com) against your site and shows the result in wp-admin: a score out of 100, a plain-English verdict, and the list of things AI crawlers need that your site does not have yet. Then it fixes most of them for you, from inside WordPress:

* **llms.txt** — a Markdown summary of your site, built from your own pages and posts and served at `/llms.txt`. Nothing is written to disk and nothing goes stale.
* **AI crawlers in robots.txt** — a rule for every known AI crawler (OpenAI, Anthropic, Google, Perplexity, Meta, Apple and the rest) plus a Content-Signal line. Choose "allow all" or "allow AI search and assistants, block AI training".
* **Meta description and Open Graph tags** — a description, title, image and canonical address for every page, when you do not already run an SEO plugin.
* **Structured data (JSON-LD)** — Organization and WebSite for the site, Article for posts, again only when no SEO plugin owns it.
* **agents.json** — an optional card at `/.well-known/agents.json` pointing AI systems at your llms.txt, sitemap and feed.

The check itself takes about ten seconds and can be run again any time. The dashboard shows your latest score.

= Works with your SEO plugin =

If Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework or Slim SEO is active, the meta tag and structured data switches stay off so nothing is written twice. llms.txt and the robots rules still apply.

= Going further =

Being readable is step one. [LLM Monitor](https://www.crawlreadiness.com/dashboard/monitor) asks ChatGPT, Claude, Perplexity and Google AI the questions your customers ask and tracks, week by week, whether they name you or a competitor. Paid plans start at $29 a month; the check and this plugin are free.

== External services ==

This plugin connects to **crawlreadiness.com**, a service operated by the plugin author, for one purpose: running the readiness check.

* **What is sent:** your site address (the home URL), when you press "Check this site" or "Check again". If you enter an API key in the settings, it is sent with the request. Nothing is sent automatically or in the background.
* **What comes back:** the score, verdict and list of findings, which the plugin stores in one option so the page and the dashboard widget can show them.
* **What the service does with it:** fetches your site's robots.txt, llms.txt, home page and agents.json from the outside, scores them, and keeps the result so the "Full report" link works.

[Privacy policy](https://www.crawlreadiness.com/privacy) · [Terms](https://www.crawlreadiness.com/terms)

== Installation ==

1. Install and activate the plugin.
2. Go to **Tools → AI Readiness** and press **Check this site**.
3. Turn on the fixes you want and press **Save settings**. Check again to see the difference.

An API key is optional. Without one, the check shares a free daily allowance with other sites on your network. A free account at crawlreadiness.com gives you a key with 50 checks a month.

== Frequently Asked Questions ==

= Does the plugin write files to my server? =

No. llms.txt, agents.json and the robots.txt rules are served by WordPress when they are requested. If a real llms.txt or robots.txt already exists in your web root, the web server serves that file and the plugin says so.

= Will blocking AI training crawlers hurt my score? =

The check currently scores every AI crawler the same, so blocking the training crawlers lowers the score. It is still a valid choice, and the settings page says so.

= My site is on a staging or local address. Can I check the live site instead? =

Yes. Filter the address: `add_filter( 'crawlready_check_url', function () { return 'https://www.example.com/'; } );`

= Does it slow my site down? =

No. The head tags are built from data WordPress already has, and llms.txt and agents.json are only built when something requests them.

== Screenshots ==

1. The check: score, verdict and what AI crawlers need.
2. The fixes, each a switch.
3. The dashboard widget.

== Changelog ==

= 1.0.0 =
* First release: the check, llms.txt, AI crawler rules with a Content-Signal line, meta and Open Graph tags, JSON-LD, agents.json, dashboard widget.
