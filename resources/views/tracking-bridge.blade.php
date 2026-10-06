{{--
    Meta, LinkedIn and PostHog don't read Google's Consent Mode. This keeps
    track of it from the dataLayer (the defaults above, and each
    gtag('consent', 'update', …) a cookie banner sends) and tells them:
    mtConsent(fn) calls fn with the signals now and on every change. With
    regions, where the defaults depend on the visitor's country, it waits for
    the banner's update instead. In Google Tag Manager, consent checks do this.
--}}
<script{!! $nonce ? ' nonce="'.e($nonce).'"' : '' !!}>(function(w){var s={},f=[],r={{ $regions ? 'true' : 'false' }};
function apply(c){for(var k in c){if(k!=='region'&&k!=='wait_for_update')s[k]=c[k];}for(var i=0;i<f.length;i++)f[i](s);}
function read(a){if(a&&a[0]==='consent'&&a[2]&&typeof a[2]==='object'&&(a[1]==='update'||(a[1]==='default'&&!r)))apply(a[2]);}
var d=w.dataLayer=w.dataLayer||[];for(var i=0;i<d.length;i++)read(d[i]);
var p=d.push;d.push=function(){for(var i=0;i<arguments.length;i++)read(arguments[i]);return p.apply(d,arguments);};
w.mtConsent=function(fn){f.push(fn);fn(s);};})(window);</script>
