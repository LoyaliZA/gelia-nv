@props(['url'])
<tr>
<td class="header" style="padding: 0;">
<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
<td class="mail-accent-bar" style="background-color: {{ config('mail.brand.accent') }}; height: 4px; line-height: 4px; font-size: 4px;">&nbsp;</td>
</tr>
<tr>
<td style="padding: 32px 24px 24px; text-align: center;">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" class="mail-logo-row" style="margin: 0 auto;">
<tr>
@foreach (config('mail.brand.logos', []) as $logo)
<td style="padding: 0 14px; text-align: center; vertical-align: middle;">
<img
    src="{{ asset($logo['src']) }}"
    alt="{{ $logo['alt'] }}"
    class="mail-logo"
    style="height: {{ $logo['height'] }}px; width: auto; max-width: 100%; display: block; margin: 0 auto;"
>
</td>
@endforeach
</tr>
</table>
</a>
</td>
</tr>
</table>
</td>
</tr>
