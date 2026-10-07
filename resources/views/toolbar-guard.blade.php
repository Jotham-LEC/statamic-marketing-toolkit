{{-- Printed by <s:mt:body /> (or <s:mt:toolbar />) for every visitor alike (Toolbar\Toolbar::guard()): the toolbar loads only for a signed-in control panel user, and never in a frame. --}}
<script{!! $nonce ? ' nonce="'.e($nonce).'"' : '' !!} data-src="{{ $src }}" data-endpoint="{{ $endpoint }}">{!! \JothamLec\MarketingToolkit\Toolbar\Toolbar::SCRIPT !!}</script>
