@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto; width: auto;">
    <tr>
        <td style="padding: 0 10px; text-align: center;">
            <img src="{{ asset('favicon-192.png') }}" alt="Gelia" style="height: 45px; width: auto; max-width: 100%; filter: grayscale(100%);">
        </td>
        <td style="padding: 0 10px; text-align: center;">
            <img src="{{ asset('Images/Logos/aromas_logo_negro.png') }}" alt="Aromas" style="height: 35px; width: auto; max-width: 100%;">
        </td>
        <td style="padding: 0 10px; text-align: center;">
            <img src="{{ asset('Images/Logos/bellaroma_logo_negro.png') }}" alt="Bellaroma" style="height: 35px; width: auto; max-width: 100%;">
        </td>
    </tr>
</table>
</a>
</td>
</tr>
