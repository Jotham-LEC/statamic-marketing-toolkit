{{--
    Meta, LinkedIn and PostHog don't read Google's Consent Mode. This keeps
    track of it from the dataLayer (the defaults above, and each
    gtag('consent', 'update', …) a cookie banner sends) and tells them:
    mtConsent(fn) calls fn with the signals now and on every change. With
    regions, where the defaults depend on the visitor's country, it waits for
    the banner's update instead. In Google Tag Manager, consent checks do this.
    The tags' own push goes first, and a callback that throws is skipped, so
    the banner's update always reaches Google's tags. mtConsent.load(src)
    loads a tool's script once, for those that mustn't load before consent.
--}}
<script{!! $nonce ? ' nonce="'.e($nonce).'"' : '' !!}>(function(w){var s={},f=[],r={{ $regions ? 'true' : 'false' }};
function apply(c){for(var k in c){if(k!=='region'&&k!=='wait_for_update')s[k]=c[k];}for(var i=0;i<f.length;i++)call(f[i]);}
function call(fn){try{fn(s);}catch(e){}}
function read(a){if(a&&a[0]==='consent'&&a[2]&&typeof a[2]==='object'&&(a[1]==='update'||(a[1]==='default'&&!r)))apply(a[2]);}
var d=w.dataLayer=w.dataLayer||[];for(var i=0;i<d.length;i++)read(d[i]);
var p=d.push;d.push=function(){var x=p.apply(d,arguments);for(var i=0;i<arguments.length;i++)read(arguments[i]);return x;};
w.mtConsent=function(fn){f.push(fn);call(fn);};
var l={};w.mtConsent.load=function(u){if(l[u])return;l[u]=1;var t=document.getElementsByTagName('script')[0],b=document.createElement('script');b.async=true;b.src=u;t.parentNode.insertBefore(b,t);};})(window);</script>
