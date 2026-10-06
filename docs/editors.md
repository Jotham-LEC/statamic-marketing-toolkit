# A guide for editors

This guide is for the people who write and look after the site's pages. It explains what the SEO parts of the control panel do and how to use them. No coding involved.

**SEO** (search engine optimisation) is about how your pages show up in Google and other search engines, and how they look when someone shares a link on Facebook, LinkedIn, WhatsApp or X. Most of it happens on its own: if you leave the SEO fields empty, the site fills in sensible values from your page's title, description and text.

Some parts are in **Marketing Toolkit Pro** only, marked *(Pro)* below. On the free edition, Tools → SEO shows a card for each of them instead.

## Tools → SEO

The overview: the latest link check *(Pro)*, recent 404s *(Pro)*, redirects, the brand defaults, and, once connected, Google Search's clicks and appearances with the pages people click most *(Pro)*, each with a button to the screen that changes it, and the files the site serves (sitemap, robots.txt).

**Connecting Google Search Console** *(Pro)* is a one-time task for whoever looks after the site's settings: **Tools → SEO → Search Console** lists the steps, with links to the right pages at Google. You create a key in Google Cloud and upload it, add the key's email as a user in Search Console, confirm the property (the site's address, filled in for you), then check the connection and import. If the check fails, it says what to fix. With several sites, do the property, the check and the import once per site, with that site chosen in the control panel's site menu; the key is uploaded once.

## SEO & brand

**Globals → SEO & brand** holds what applies to the whole site. Fill it in once and come back when something changes.

**Brand tab**
- **Title separator**: what goes between the page title and the site name, with a space added on each side. Leave empty for "·".
- **Default description**: used for pages that have no description and no text to borrow one from.
- **Default share image**: shown when a page is shared and has no picture of its own. 1200 × 630 pixels works best.
- **Other site name**: a shorter name or acronym search engines may show instead of the site name.
- **X handle**: your account on X (Twitter), without the @.
- **Icon**: the site's icon in browser tabs, bookmarks and phone home screens. Upload one square image, 512 × 512 pixels or more (PNG, or SVG to stay sharp); every size is made from it. **Theme colour** tints the browser's bar on phones; **Icon background** sits behind the icon on an iPhone home screen (white unless set).

**Publisher tab.** Who is behind the site, so search engines can show it correctly. Pick the most specific **type** (a Store rather than a Local business; an Educational organization), or two (Educational organization and Local business), or type any other schema.org type. Then the name, another name, when it was founded, a description, logo or portrait, phone, email, area served, profiles elsewhere and contact points. **Address**: needed for a business people visit; leave it empty for one that only delivers or serves an area. **Local business**: price range, map coordinates and opening hours. Each value only goes out where the type accepts it, so filling in more than applies does no harm.

**Tracking tab.** Paste the ID of each tool you use: **Google Tag Manager**, **Google Analytics 4**, **PostHog** (and its host, for an EU project), the **Meta Pixel** and the **LinkedIn Insight Tag**. Leave the others empty. They load on the live site only, never while you edit. With Google Tag Manager, add every other tool as a tag inside GTM rather than here: a tool loaded both ways counts each visit twice, so the tab and Tools → SEO warn you when that happens. An ID your developer set in `.env` wins over the one here.

**Leads** *(Pro)* (same tab): **Send form submissions as leads** sends every form sent on the site to your tools as a lead (and to a LinkedIn conversion, if you paste its ID); **Save where each lead came from** adds the campaign, the site that sent them and their first page to each submission, under **Forms**. See [tracking.md](tracking.md#leads-pro).

**Consent Mode** (same tab), for a cookie banner you already have: what Google's tags may do before a visitor answers it. Turn it on, choose what's denied until the visitor agrees (everything, by default), and how long to wait for the banner. *(Pro)* **Only in these regions**: the defaults apply there (for example the EEA, the UK and Switzerland), and everything is granted elsewhere. See [tracking.md](tracking.md) for how the banner passes on the answer.

**Shop tab** (for a site that sells; your developer adds it). The **currency** of your prices; your **return policy** (within so many days, any time, or not accepted, for a country, and/or a link to the policy page); and your **shipping rates**: one row per destination and order value (for example free over RM 300), with the delivery time in days. Search engines show these with your products.

**Share cards tab.** The background, text and accent colours of the generated share pictures, and a logo or portrait to put on every card.

**Crawlers tab**
- **Verification** codes from Google Search Console, Bing, Yandex or Pinterest, when they ask you to prove you own the site.
- **robots.txt Disallow**: parts of the site search engines shouldn't visit. Leave empty unless you're told otherwise.
- **Allow AI training**: off turns away the crawlers that gather pages to train AI models (OpenAI's GPTBot, Anthropic's ClaudeBot, Google-Extended for Gemini, Applebot-Extended, and Common Crawl's CCBot, whose open dataset AI developers train on). Google Search is unaffected.
- **Allow AI search**: off turns away the crawlers behind ChatGPT search, Claude and Perplexity answers, so the site isn't quoted there. Fetchers a person sends from those apps don't all read robots.txt.
- **robots.txt extra lines**: extra rules, for example for AI crawlers.
- **ads.txt**: only for a site that sells ad space (AdSense and others): paste the lines your ad network gives you; they're served at `/ads.txt`.

The site also serves **/llms.txt**, a list of its pages with their descriptions for AI assistants, made from the same pages as the sitemap.

## A page's SEO tab

Every page with SEO fields has them on its SEO tab.

### The search and share preview

At the top is a live preview:

- **Google**: how the page appears in search results, with its title, address and description.
- **Facebook, LinkedIn, WhatsApp** and **X**: how the link looks when shared.

It updates as you type, before you save. Values you leave empty show the defaults the site will really use, so what you see is what people get.

Above the Google result are two counters:

- **Title**: Google shows about 60 characters; longer titles get cut off with "…".
- **Description**: aim for 50 to 160 characters.

Green is a good length, amber is too short, red is too long.

If you see *"Hidden from search engines: this result will not appear"*, the page is set not to show in search results. That's expected on a test copy of the site; on the live site, check the "Hide from search engines" switch below.

### The fields

All optional. Empty means "use the default".

| Field | What it does | When it's empty |
|---|---|---|
| **Title** | Replaces the whole title in Google and the browser tab, exactly as you type it. | The page title, followed by the site name when there's room. |
| **Description** | The text under the title in Google, and on share cards. | The page's own description, or its first paragraph. |
| **Share image** | The picture when the page is shared. Cropped to 1200 × 630. | A share card is drawn for the page automatically (see below). |
| **Card title** / **Card subtitle** | The words on the generated share card. | The title and the description. |
| **Canonical URL** | Only for a piece first published on another site: the original's address, so search engines credit it. | This page's own address. |
| **Hide from search engines** | Keeps the page out of Google. | The page can be found. |
| **Do not follow links** | Tells search engines not to follow the links on this page. | Links are followed. |
| **No snippet** | Shows no text from this page in Google's results, nor in its AI Overviews and AI Mode. | Text is shown. |
| **Snippet length** | At most this many characters are quoted. | No limit. |
| **In sitemap** | Lists the page in the sitemap search engines read. | On. |
| **Extra JSON-LD** | Structured data for search engines. Leave it to your developer. | Nothing extra. |

### Several languages *(Pro)*

On a site in several languages, each translation of a page has its own SEO fields: give each language its own title and description. Search engines are told about the other languages by themselves (with "hreflang" links), so a French visitor is sent to the French page. A translation left as a draft, or hidden from search engines, is left out of those links.

### Share cards *(Pro)*

When a page has no share image, the site draws one: the page title and description on your brand colours. You see it in the preview. To change the words, fill in **Card title** and **Card subtitle**. To use a photo instead, upload a **Share image**.

## When a page's address changes *(Pro)*

A page's address (its URL) changes when you change its slug, when its date changes on a dated page such as a news article, or when you move it to another place in a page tree. Old links to it, from Google, other sites or your own emails, would then lead nowhere. So the site adds a **redirect**: anyone visiting the old address is sent to the new one.

If you can manage redirects, saving a page whose address changes asks first:

> **This page's address changes.** Saving moves the page from /old-address → /new-address. Add a 301 redirect from the old address, so links to it keep working?

- **Add redirect**: almost always the right answer.
- **Don't add**: only if the old address was never shared (a page you just created and renamed straight away).
- **Don't save yet**: go back to the page without saving.

If you can't manage redirects, the question isn't asked and the redirect is added for you. Moving pages around in a tree adds redirects for the page and everything under it, without asking.

## Redirects

**Tools → SEO → Redirects** lists every redirect. Those marked **Automatic** were added when a page moved.

- **Create redirect**: send one address somewhere else.
  - **From**: an address on this site, starting with `/`, for example `/old-page`. A `*` matches anything: `/blog/*` covers every address under `/blog/`. An address copied from the browser (`/caf%C3%A9`) works too. Capitals count: `/About` and `/about` are two addresses, unless your developer has set redirects to ignore them.
  - **To**: where to send visitors: `/new-page`, or a full address on another site. With a `*` in From, `$1` stands for whatever it matched: from `/blog/*` to `/articles/$1` sends `/blog/my-post` to `/articles/my-post`. A `#section` at the end is kept. A redirect that would send visitors back where they came from, straight away or through another redirect, isn't accepted.
  - **Type**: *301 Moved for good* (the usual one), *302 Moved for now* (temporary, e.g. during a sale), or *410 Gone* (removed for good; leave To empty).
  - **Active**: switch off to pause a redirect without deleting it.
  - **Campaign link** *(Pro)*: for a short address you share in a campaign or print on a flyer, like `/go/linkedin`. Fill in the UTM tags (source, medium, campaign; content and term if you use them) and they're added to where it goes, so Google Analytics and each lead's source show the campaign. Choose *302*, so browsers don't remember it and every click is counted in the list.
  - **Site** (only with more than one site): the site whose address this is. Leave empty for every site. A site's own redirect wins over one for every site from the same address.
- **Hits** and **Last used** show whether a redirect is still needed.
- **Import CSV** and **Export CSV** *(Pro)*: move many redirects at once, for example from an old site. The file has the columns `source,target,status,active`, and `site` with more than one site (a site's handle, or empty for every site).

A redirect only applies when its address doesn't exist as a page. If you bring a page back at an old address, the page shows, not the redirect.

## 404s *(Pro)*

A **404** is what visitors get when they ask for an address that doesn't exist. **Tools → SEO → 404s** lists the ones real visitors hit, most recent first, with how often and the last page that linked there. Bots and hacking attempts are left out.

Use it to catch broken links. For a missing address that should lead somewhere, open the row's **⋯** menu and choose **Create redirect**: the form opens with the address (and, with more than one site, its site) filled in, and you only add where it should go.

With more than one site, the overview, the 404s, the link checks and the dashboard card are of the site chosen in the control panel's site menu; redirects list every site's, with a Site column.

## Link check *(Pro)*

**Tools → SEO → Link check** opens every published page, as a visitor would, and lists the pages with something to fix:

| Check | What to do |
|---|---|
| Broken links | A link to a page of this site that doesn't exist: fix the link, or add a redirect. A link that goes through a redirect (a warning): link straight to the new address. |
| Broken links to other sites | A link to a page that no longer exists (404, 410), even after a redirect, or to a site that's gone. Links to `localhost` or a private network aren't checked. |
| Description | The page has no description, so Google picks text from the page for its search result. Write one on the page's SEO tab. |
| Share image | Links to the page on LinkedIn, WhatsApp or Slack show no picture. Upload one on the page's SEO tab, or set a default in **SEO & brand**. |

It runs every week on its own (Monday, 03:00). Click **Check now** to run it straight away; a few hundred pages take under a minute. Click a check to see only the pages it flagged; each page has a **Fix** link to its edit screen. Pages hidden from search engines are checked for broken links only.

## The dashboard *(Pro)*

The **SEO** box on the dashboard (if your administrator added it) shows how many pages the latest link check found to fix, and the most recent 404s. Click through for the details.
