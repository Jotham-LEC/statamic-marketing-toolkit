# A guide for editors

This guide is for the people who write and look after the site's pages. It explains what the SEO parts of the control panel do and how to use them. No coding involved.

**SEO** (search engine optimisation) is about how your pages show up in Google and other search engines, and how they look when someone shares a link on Facebook, LinkedIn, WhatsApp or X. Most of it happens on its own: if you leave the SEO fields empty, the site fills in sensible values from your page's title, description and text.

## Tools → SEO

The overview: the latest report's score, recent 404s, redirects, the brand defaults, and, once connected, Google Search's clicks and appearances with the pages people click most, each with a button to the screen that changes it, and the files the site serves (sitemap, robots.txt).

**Connecting Google Search Console** is a one-time task for whoever looks after the site's settings: Tools → SEO lists the steps, with links to the right pages at Google. You create a key in Google Cloud and upload it, add the key's email as a user in Search Console, confirm the property (the site's address, filled in for you), then check the connection and import. If the check fails, it says what to fix.

## SEO & brand

**Globals → SEO & brand** holds what applies to the whole site. Fill it in once and come back when something changes.

**Brand tab**
- **Title separator**: what goes between the page title and the site name, with a space added on each side. Leave empty for "·".
- **Default description**: used for pages that have no description and no text to borrow one from.
- **Default share image**: shown when a page is shared and has no picture of its own. 1200 × 630 pixels works best.
- **Other site name**: a shorter name or acronym search engines may show instead of the site name.
- **X handle**: your account on X (Twitter), without the @.

**Publisher tab.** Who is behind the site, so search engines can show it correctly. Pick the most specific **type** (a Store rather than a Local business; an Educational organization), or two (Educational organization and Local business), or type any other schema.org type. Then the name, another name, when it was founded, a description, logo or portrait, phone, email, area served, profiles elsewhere and contact points. **Address**: needed for a business people visit; leave it empty for one that only delivers or serves an area. **Local business**: price range, map coordinates and opening hours. Each value only goes out where the type accepts it, so filling in more than applies does no harm.

**Shop tab** (for a site that sells; your developer adds it). The **currency** of your prices; your **return policy** (within so many days, any time, or not accepted, for a country, and/or a link to the policy page); and your **shipping rates**: one row per destination and order value (for example free over RM 300), with the delivery time in days. Search engines show these with your products.

**Share cards tab.** The background, text and accent colours of the generated share pictures, and a logo or portrait to put on every card.

**Crawlers tab**
- **Verification** codes from Google Search Console, Bing, Yandex or Pinterest, when they ask you to prove you own the site.
- **robots.txt Disallow**: parts of the site search engines shouldn't visit. Leave empty unless you're told otherwise.
- **Allow AI training**: off turns away the crawlers that gather pages to train AI models (OpenAI's GPTBot, Anthropic's ClaudeBot, Google-Extended for Gemini, Applebot-Extended, and Common Crawl's CCBot, whose open dataset AI developers train on). Google Search is unaffected.
- **Allow AI search**: off turns away the crawlers behind ChatGPT search, Claude and Perplexity answers, so the site isn't quoted there. Fetchers a person sends from those apps don't all read robots.txt.
- **robots.txt extra lines**: extra rules, for example for AI crawlers.

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

### Share cards

When a page has no share image, the site draws one: the page title and description on your brand colours. You see it in the preview. To change the words, fill in **Card title** and **Card subtitle**. To use a photo instead, upload a **Share image**.

## When a page's address changes

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
  - **Site** (only with more than one site): the site whose address this is. Leave empty for every site. A site's own redirect wins over one for every site from the same address.
- **Hits** and **Last used** show whether a redirect is still needed.
- **Import CSV** and **Export CSV**: move many redirects at once, for example from an old site. The file has the columns `source,target,status,active`, and `site` with more than one site (a site's handle, or empty for every site).

A redirect only applies when its address doesn't exist as a page. If you bring a page back at an old address, the page shows, not the redirect.

## 404s

A **404** is what visitors get when they ask for an address that doesn't exist. **Tools → SEO → 404s** lists the ones real visitors hit, most recent first, with how often and the last page that linked there. Bots and hacking attempts are left out.

Use it to catch broken links. For a missing address that should lead somewhere, open the row's **⋯** menu and choose **Create redirect**: the form opens with the address (and, with more than one site, its site) filled in, and you only add where it should go.

With more than one site, the overview, the 404s, the reports and the dashboard card are of the site chosen in the control panel's site menu; redirects list every site's, with a Site column.

## Reports

**Tools → SEO → Reports** checks every page of the site the way a search engine sees it, and gives each page a score out of 100. The site's score is the average.

Click **Run report**. A bar shows the progress; a few hundred pages take under a minute. When it's done you see:

- **The site's score**.
- **The checks**, with how many pages fail each one or get a warning. Click a check to see only the pages it flagged.
- **The pages**, lowest score first. Each one lists its problems and has a **Fix** link to its edit screen.

What the checks look for:

| Check | Counts | What to do |
|---|---|---|
| Links within the site | 3 | A link to a page that doesn't exist: fix the link, or add a redirect. A link that goes through a redirect: link straight to the new address. |
| Canonical address | 3 | Usually fine on its own; tell your developer if it fails. |
| Hidden page in the sitemap | 3 | A page set to hide from search engines but still in the sitemap: tell your developer. |
| Title length | 2 | Write an SEO title that fits. |
| Unique title | 2 | Two pages with the same title: make each one say what that page is about. |
| Description length | 2 | Write a description of the right length. |
| Unique description | 2 | The same, for descriptions. |
| One main heading | 2 | Each page should have one main heading. Usually the page template's job. |
| Structured data | 2 | Usually the developer's job. |
| Image descriptions | 1 | Describe each image in its "alt text" (in the asset's settings), for people who can't see it and for search engines. |
| Share image | 1 | Pages without a picture when shared. |
| Linked from another page | 2 | A page in the sitemap that no other page links to (a warning): link to it from a related page, so search engines and readers find it. The home page is exempt. |
| Links to other sites | 1 | Off unless your administrator turns it on, since it asks each linked site: links to pages that no longer exist (404, 410), even after a redirect, or to sites that are gone. Links to `localhost` or a private network aren't checked. |

A **warning** counts half. Pages set to hide from search engines are listed but not scored.

Who can see and run reports, which checks they include and the length targets are set by your administrator (Tools → Addons → SEO). Reports can also run on their own, daily or weekly.

## The dashboard

The **SEO** box on the dashboard (if your administrator added it) shows the latest report's score and the most recent 404s. Click through for the details.
