{{-- Isotipo GELIA (mismo ADN visual que GeliaLogo.jsx) --}}
@php
    $size = $size ?? 100;
    $color = $color ?? '#1e3a8a';
@endphp
<svg viewBox="0 0 100 100" style="width:{{ $size }}px; height:{{ $size }}px; display:block; margin:0 auto;" xmlns="http://www.w3.org/2000/svg">
    <g fill="{{ $color }}">
        <polygon points="30,10 70,10 30,30" opacity="1.0"/>
        <polygon points="10,30 30,30 10,70" opacity="1.0"/>
        <polygon points="30,90 30,70 70,90" opacity="1.0"/>
        <polygon points="90,70 70,70 90,50" opacity="1.0"/>
        <polygon points="70,50 70,70 50,50" opacity="1.0"/>
        <polygon points="70,10 70,30 30,30" opacity="0.55"/>
        <polygon points="30,30 30,70 10,70" opacity="0.55"/>
        <polygon points="30,70 70,70 70,90" opacity="0.55"/>
        <polygon points="70,70 70,50 90,50" opacity="0.55"/>
        <polygon points="70,10 90,30 70,30" opacity="0.25"/>
        <polygon points="30,10 30,30 10,30" opacity="0.25"/>
        <polygon points="10,70 30,70 30,90" opacity="0.25"/>
        <polygon points="70,90 70,70 90,70" opacity="0.25"/>
    </g>
</svg>
