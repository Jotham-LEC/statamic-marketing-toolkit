{{-- Rendered by <s:seo:head /> (JothamLec\MarketingToolkit\Tracking\Tracking): Consent Mode defaults first, then the tags. --}}
@php($n = $nonce ? ' nonce="'.e($nonce).'"' : '')
@if ($consentDefaults || $ids['ga4'] || $ids['gtm'])
<script{!! $n !!}>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
@foreach ($consentDefaults as $default)
gtag('consent','default',@json($default));
@endforeach
</script>
@endif
@if ($bridge)
@include('seo::tracking-bridge', ['regions' => $consent['regions'] !== []])
@endif
@if ($ids['gtm'])
<script{!! $n !!}>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($ids['gtm']));</script>
@endif
@if ($ids['ga4'])
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $ids['ga4'] }}"{!! $n !!}></script>
<script{!! $n !!}>gtag('js',new Date());gtag('config',@json($ids['ga4']));</script>
@endif
@if ($ids['posthog'])
<script{!! $n !!}>!(function(t,e){var o,n,p,r;e.__SV||(window.posthog&&window.posthog.__loaded)||((window.posthog=e),(e._i=[]),(e.init=function(i,s,a){function g(t,e){var o=e.split('.');(2==o.length&&((t=t[o[0]]),(e=o[1])),(t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}))}(((p=t.createElement('script')).type='text/javascript'),(p.crossOrigin='anonymous'),(p.async=!0),(p.src=s.api_host.replace('.i.posthog.com','-assets.i.posthog.com')+'/static/array.js'),(r=t.getElementsByTagName('script')[0]).parentNode.insertBefore(p,r));var u=e;for(void 0!==a?(u=e[a]=[]):(a='posthog'),u.people=u.people||[],u.toString=function(t){var e='posthog';return('posthog'!==a&&(e+='.'+a),t||(e+=' (stub)'),e)},u.people.toString=function(){return u.toString(1)+'.people (stub)'},o='init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug getPageViewId'.split(' '),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])}),(e.__SV=1))})(document,window.posthog||[]);
posthog.init(@json($ids['posthog']),@json($posthogOptions));
@if ($bridge)
mtConsent(function(s){if(s.analytics_storage==='granted'){posthog.set_config({persistence:'localStorage+cookie'});posthog.opt_in_capturing();}else if(s.analytics_storage==='denied'&&posthog.has_opted_in_capturing()){posthog.opt_out_capturing();}});
@endif
</script>
@endif
@if ($ids['meta'])
<script{!! $n !!}>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
@if ($bridge)
fbq('consent','revoke');mtConsent(function(s){fbq('consent',s.ad_storage==='granted'?'grant':'revoke');});
@endif
fbq('init',@json($ids['meta']));fbq('track','PageView');</script>
@endif
@if ($ids['linkedin'])
<script{!! $n !!}>window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];window._linkedin_data_partner_ids.push(@json($ids['linkedin']));
(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}
function load(){if(load.done)return;load.done=1;var s=document.getElementsByTagName('script')[0],b=document.createElement('script');b.type='text/javascript';b.async=true;b.src='https://snap.licdn.com/li.lms-analytics/insight.min.js';s.parentNode.insertBefore(b,s)}
@if ($bridge)
mtConsent(function(s){if(s.ad_storage==='granted')load();});
@else
load();
@endif
})(window.lintrk);</script>
@endif
