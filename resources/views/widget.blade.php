{{-- The widget finds its form through this container and submits its token as "captchacore_token". --}}
<div data-captchacore-widget
     data-form-type="{{ $formType }}"
     @foreach($options as $key => $value) data-{{ $key }}="{{ $value }}" @endforeach
     ></div>
@once
<script src="{{ $scriptUrl }}"
        data-service-url="{{ $serviceUrl }}"
        data-site-key="{{ $siteKey }}"
        @if($nonce) nonce="{{ $nonce }}" @endif
        defer></script>
@endonce
