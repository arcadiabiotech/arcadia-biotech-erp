{{-- Shared A5 challan CSS — used by dispatches.print, challans.dealer and
     challans.farmer so all three printable documents keep identical company
     branding. Sizing is tuned for A5's canvas (148 x 210mm) — re-check page
     count (dompdf getCanvas()->get_page_count()) after touching any
     font-size/padding here. --}}
<style>
    @page { size: A5; margin: 3mm; }
    * { box-sizing: border-box; }
    body {
        font-family: "DejaVu Sans", Arial, sans-serif;
        color: #1e293b;
        font-size: 12px;
        line-height: 1.08;
        margin: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    table { border-collapse: separate; border-spacing: 0; }
    .items, .items th, .items td { border-collapse: collapse; }
    .full { width: 100%; }

    /* ---------- Header ---------- */
    .header {
        width: 100%;
        background: #0b3d2e;
        color: #ffffff;
        border-radius: 8px;
        padding: 5px 8px;
        margin-bottom: 4px;
    }
    .header .brand-cell { width: 60%; vertical-align: top; }
    .header .meta-cell { width: 40%; vertical-align: top; text-align: right; }
    .brand-row { width: 100%; }
    .brand-row .logo-cell { width: 34px; vertical-align: top; }
    .logo-badge {
        width: 30px; height: 30px; border-radius: 50%;
        background: #ffffff; color: #0b3d2e;
        text-align: center; vertical-align: middle;
        font-size: 13px; font-weight: bold;
    }
    .logo-img { width: 30px; height: 30px; border-radius: 50%; background: #fff; }
    .company-name { font-size: 17px; font-weight: bold; letter-spacing: 0.3px; }
    .company-tagline { font-size: 10px; color: #cfe7dc; margin-top: 1px; }
    .company-meta { font-size: 9.5px; color: #dff2e8; margin-top: 2px; }
    .doc-title {
        display: inline-block;
        font-size: 14px; font-weight: bold; letter-spacing: 0.8px;
        background: rgba(255,255,255,0.12);
        padding: 2px 6px; border-radius: 5px;
        margin-bottom: 3px;
    }
    .meta-line { font-size: 10px; color: #eafff5; margin-top: 0.5px; }
    .meta-line strong { color: #ffffff; }
    .qr-box {
        margin-top: 2px; margin-left: auto;
        width: 46px; height: 46px;
        background: #ffffff; border-radius: 6px;
        text-align: center; vertical-align: middle;
        padding: 2px;
    }
    .qr-caption { font-size: 8px; color: #dff2e8; margin-top: 1px; }

    /* ---------- Section boxes ---------- */
    .box {
        border: 1px solid #dbe4df;
        border-radius: 6px;
        padding: 3px 5px;
        vertical-align: top;
    }
    .box-title {
        font-size: 10px; font-weight: bold; text-transform: uppercase;
        letter-spacing: 0.3px; color: #0b3d2e;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 1px; margin-bottom: 1px;
    }
    .kv { width: 100%; }
    .kv td { padding: 0; font-size: 10.6px; vertical-align: top; }
    .kv td.k { color: #64748b; width: 42%; }
    .kv td.v { color: #1e293b; font-weight: bold; text-align: right; }
    .two-col { width: 100%; margin-top: 3px; }
    .two-col td { width: 50%; padding: 0; }
    .two-col td.left-pad { padding-right: 3px; }
    .two-col td.right-pad { padding-left: 3px; }

    /* ---------- Item table ---------- */
    .items { width: 100%; margin-top: 3px; border: 1px solid #dbe4df; border-radius: 6px; overflow: hidden; }
    .items th {
        background: #0b3d2e; color: #fff; font-size: 9.5px; text-transform: uppercase;
        letter-spacing: 0.2px; padding: 1.5px 4px; text-align: left;
    }
    .items td { padding: 1.5px 4px; font-size: 10.6px; border-top: 1px solid #eef2f0; }
    .items td.num, .items th.num { text-align: right; }
    .items tfoot td { font-weight: bold; background: #f4f9f6; border-top: 1px solid #dbe4df; }

    /* ---------- Summary ---------- */
    .summary { width: 100%; margin-top: 3px; }
    .summary td { width: 50%; vertical-align: top; }
    .summary td.left-pad { padding-right: 3px; }
    .summary td.right-pad { padding-left: 3px; }
    .sign-line-label { font-size: 9.5px; color: #64748b; }
    .sign-line-value { font-size: 10.8px; font-weight: bold; border-bottom: 1px solid #cbd5e1; display: block; min-height: 12px; margin-top: 0.5px; }

    /* ---------- Barcode ---------- */
    .barcode-wrap { text-align: center; margin-top: 2px; }
    .barcode-wrap svg { height: 16px; }
    .barcode-caption { font-size: 8.6px; letter-spacing: 1px; color: #64748b; margin-top: 0.5px; }

    /* ---------- Terms ---------- */
    .terms { margin-top: 3px; border-top: 1px dashed #cbd5e1; padding-top: 2px; }
    .terms-title { font-size: 10px; font-weight: bold; color: #0b3d2e; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 1px; }
    .terms table { width: 100%; }
    .terms td { width: 50%; vertical-align: top; padding: 0; }
    .terms td.right-pad { padding-left: 5px; }
    .terms ol { margin: 0; padding-left: 10px; }
    .terms li { font-size: 8.8px; color: #475569; margin-bottom: 0; }

    /* ---------- Footer signatures ---------- */
    .footer { width: 100%; margin-top: 22px; }
    .footer td { width: 25%; text-align: center; font-size: 9px; color: #64748b; padding-top: 50px; border-top: 1px solid #94a3b8; }

    .public-note { margin-top: 2px; font-size: 8.2px; color: #94a3b8; text-align: center; }
</style>
