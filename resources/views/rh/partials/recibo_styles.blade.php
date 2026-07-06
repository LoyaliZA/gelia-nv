<style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap');

    * { box-sizing: border-box; }

    body {
        font-family: 'Montserrat', 'DejaVu Sans', Arial, sans-serif;
        font-size: 14.5px;
        font-weight: 400;
        color: #0F172A;
        line-height: 1.45;
        margin: 0;
        padding: 24px 32px 22px;
        background: #ffffff;
    }

    .header-wrapper {
        width: 100%;
        margin-bottom: 14px;
    }

    .header-accent-rule {
        width: 100%;
        height: 2px;
        background: #8B5CF6;
        margin-bottom: 14px;
    }

    .doc-title-label {
        font-family: 'Montserrat', 'DejaVu Sans', Arial, sans-serif;
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: #8B5CF6;
        margin-bottom: 5px;
    }

    .doc-title-main {
        font-family: 'Montserrat', 'DejaVu Sans', Arial, sans-serif;
        font-size: 23px;
        font-weight: 800;
        color: #0F172A;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        line-height: 1.25;
    }

    .doc-title-meta {
        font-family: 'Montserrat', 'DejaVu Sans', Arial, sans-serif;
        font-size: 13.5px;
        font-weight: 500;
        color: #0F172A;
        margin-top: 6px;
        letter-spacing: 0.5px;
        opacity: 0.85;
    }

    .doc-title-meta strong {
        color: #0F172A;
        font-weight: 700;
        opacity: 1;
    }

    .section-divider {
        width: 100%;
        height: 1px;
        background: #8B5CF6;
        margin: 12px 0 10px;
        opacity: 0.45;
    }

    .section-title {
        font-family: 'Montserrat', 'DejaVu Sans', Arial, sans-serif;
        font-size: 15.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: #0F172A;
        margin: 0 0 8px;
    }

    .kv-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 2px;
    }

    .kv-table tr {
        border-bottom: 1px solid rgba(139, 92, 246, 0.25);
    }

    .kv-table tr:last-child {
        border-bottom: none;
    }

    .kv-table td {
        padding: 7px 4px;
        font-size: 14.5px;
        vertical-align: middle;
    }

    .kv-table .kv-label {
        font-weight: 700;
        color: #0F172A;
        text-align: left;
        width: 38%;
    }

    .kv-table .kv-value {
        font-weight: 500;
        color: #0F172A;
        text-align: right;
        width: 62%;
        opacity: 0.9;
    }

    .detail-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
        border: 1px solid rgba(139, 92, 246, 0.3);
    }

    .detail-table thead th {
        font-family: 'Montserrat', 'DejaVu Sans', Arial, sans-serif;
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: #0F172A;
        background: rgba(139, 92, 246, 0.08);
        padding: 8px 10px;
        text-align: left;
        border-bottom: 1px solid rgba(139, 92, 246, 0.35);
    }

    .detail-table tbody td {
        font-size: 14.5px;
        color: #0F172A;
        padding: 8px 10px;
        border-bottom: 1px solid rgba(139, 92, 246, 0.18);
        vertical-align: top;
    }

    .detail-table tbody tr:last-child td {
        border-bottom: none;
    }

    .detail-table .amount-row td {
        font-size: 13px;
        color: #0F172A;
        padding: 5px 10px;
        background: rgba(139, 92, 246, 0.04);
        opacity: 0.85;
    }

    .detail-table .amount-row .amount-value {
        text-align: right;
        font-weight: 600;
        color: #0F172A;
        opacity: 1;
    }

    .total-bar {
        width: 100%;
        background: #0F172A;
        padding: 11px 18px;
        margin: 12px 0 14px;
        text-align: center;
    }

    .total-bar-text {
        font-family: 'Montserrat', 'DejaVu Sans', Arial, sans-serif;
        font-size: 18.5px;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: 0.5px;
    }

    .total-bar-amount {
        color: #8B5CF6;
        font-size: 22px;
        letter-spacing: 1px;
    }

    .observaciones-box {
        padding: 10px 12px;
        border: 1px solid rgba(139, 92, 246, 0.3);
        background: #ffffff;
        min-height: 40px;
        white-space: pre-wrap;
        font-size: 14.5px;
        color: #0F172A;
        line-height: 1.5;
    }

    .observaciones-empty {
        font-style: italic;
        opacity: 0.7;
    }

    .signatures {
        width: 100%;
        border-collapse: collapse;
        margin-top: 22px;
    }

    .signatures td {
        width: 50%;
        text-align: center;
        vertical-align: bottom;
        padding: 0 20px;
    }

    .sig-image,
    .sig-spacer {
        height: 50px;
        margin-bottom: 2px;
    }

    .sig-line {
        width: 78%;
        border: none;
        border-top: 1px solid #8B5CF6;
        margin: 0 auto 6px;
        height: 1px;
    }

    .sig-title {
        font-family: 'Montserrat', 'DejaVu Sans', Arial, sans-serif;
        font-weight: 700;
        color: #0F172A;
        font-size: 12.5px;
        text-transform: uppercase;
        letter-spacing: 1.2px;
    }

    .footer {
        margin-top: 18px;
        padding-top: 8px;
        border-top: 1px solid rgba(139, 92, 246, 0.25);
        font-size: 10.5px;
        color: #0F172A;
        text-align: center;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        opacity: 0.65;
    }
</style>
