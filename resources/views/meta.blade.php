{{-- Rendered by <s:mt:meta />. Every value is resolved by JothamLec\MarketingToolkit\SiteSeo; this view only prints. --}}
<title>{{ $meta->title }}</title>
@if ($meta->description)
<meta name="description" content="{{ $meta->description }}">
@endif
<meta name="robots" content="{{ $meta->robots }}">
@if ($meta->canonical)
<link rel="canonical" href="{{ $meta->canonical }}">
@endif
@foreach ($meta->alternates as $hreflang => $href)
<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
@endforeach
@foreach ($meta->verification as $name => $content)
<meta name="{{ $name }}" content="{{ $content }}">
@endforeach
<meta property="og:type" content="{{ $meta->ogType }}">
<meta property="og:title" content="{{ $meta->ogTitle }}">
@if ($meta->description)
<meta property="og:description" content="{{ $meta->description }}">
@endif
<meta property="og:url" content="{{ $meta->url }}">
<meta property="og:site_name" content="{{ $meta->siteName }}">
<meta property="og:locale" content="{{ $meta->locale }}">
@foreach ($meta->localeAlternates as $locale)
<meta property="og:locale:alternate" content="{{ $locale }}">
@endforeach
@if ($meta->image)
<meta property="og:image" content="{{ $meta->image['url'] }}">
<meta property="og:image:width" content="{{ $meta->image['width'] }}">
<meta property="og:image:height" content="{{ $meta->image['height'] }}">
@if ($meta->image['alt'])
<meta property="og:image:alt" content="{{ $meta->image['alt'] }}">
@endif
@endif
@if ($meta->published)
<meta property="article:published_time" content="{{ $meta->published }}">
@endif
@if ($meta->modified)
<meta property="article:modified_time" content="{{ $meta->modified }}">
@endif
<meta name="twitter:card" content="{{ $meta->image ? 'summary_large_image' : 'summary' }}">
@if ($meta->twitterSite)
<meta name="twitter:site" content="{{ $meta->twitterSite }}">
@endif
<meta name="twitter:title" content="{{ $meta->ogTitle }}">
@if ($meta->description)
<meta name="twitter:description" content="{{ $meta->description }}">
@endif
@if ($meta->image)
<meta name="twitter:image" content="{{ $meta->image['url'] }}">
@if ($meta->image['alt'])
<meta name="twitter:image:alt" content="{{ $meta->image['alt'] }}">
@endif
@endif
@if ($jsonLd = $meta->jsonLd())
<script type="application/ld+json">{!! $jsonLd !!}</script>
@endif
