{{-- Rendered by <s:seo:head /> and <s:seo:favicons /> (JothamLec\MarketingToolkit\Favicons\Favicons). --}}
@foreach ($links as $link)
<link rel="{{ $link['rel'] }}" href="{{ $link['href'] }}"{!! isset($link['sizes']) ? ' sizes="'.e($link['sizes']).'"' : '' !!}{!! isset($link['type']) ? ' type="'.e($link['type']).'"' : '' !!}>
@endforeach
@if ($links && $themeColor)
<meta name="theme-color" content="{{ $themeColor }}">
@endif
