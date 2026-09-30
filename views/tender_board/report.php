<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Monthly Tender Report - <?= htmlspecialchars($periodLabel) ?></title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0;background:#E2E8F0;color:#0F172A;font-family:Arial,Helvetica,sans-serif; }
        .report-toolbar { max-width:1180px;margin:20px auto 0;padding:0 18px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap; }
        .report-actions { display:flex;gap:8px;flex-wrap:wrap; }
        .report-button { display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 14px;border:1px solid #CBD5E1;border-radius:9px;background:#FFF;color:#334155;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer; }
        .report-button.primary { background:#2563EB;border-color:#2563EB;color:#FFF; }
        .report-sheet { width:min(1180px,calc(100% - 36px));min-height:calc(100vh - 110px);margin:14px auto 28px;padding:38px;background:#FFF;box-shadow:0 12px 35px rgba(15,23,42,.12); }
        .report-head { display:flex;align-items:center;justify-content:space-between;gap:22px;padding-bottom:22px;border-bottom:3px solid #0F172A; }
        .report-brand { display:flex;align-items:center;gap:14px; }
        .report-logo { width:58px;height:58px;object-fit:contain;border:1px solid #E2E8F0;border-radius:12px;padding:5px; }
        .report-head h1 { margin:0 0 5px;font-size:21px;letter-spacing:.2px; }
        .report-head p { margin:0;color:#64748B;font-size:12px; }
        .period { text-align:right; }
        .period strong { display:block;color:#2563EB;font-size:17px;margin-bottom:4px; }
        .staff-panel { margin:22px 0;padding:16px 18px;border:1px solid #DBEAFE;border-radius:12px;background:#EFF6FF;display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap; }
        .staff-panel strong { display:block;font-size:15px;margin-bottom:4px; }
        .staff-panel span { color:#64748B;font-size:12px; }
        .summary-grid { display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:24px; }
        .summary-card { padding:15px;border:1px solid #E2E8F0;border-radius:11px;background:#F8FAFC; }
        .summary-card span { display:block;color:#64748B;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px; }
        .summary-card strong { font-size:18px; }
        .table-wrap { overflow-x:auto;border:1px solid #E2E8F0;border-radius:11px; }
        table { width:100%;border-collapse:collapse;font-size:11px; }
        th { padding:11px 10px;background:#0F172A;color:#FFF;text-align:left;white-space:nowrap; }
        td { padding:11px 10px;border-bottom:1px solid #E2E8F0;vertical-align:top; }
        tbody tr:last-child td { border-bottom:0; }
        .money { text-align:right;white-space:nowrap; }
        .status { display:inline-block;padding:4px 8px;border-radius:999px;background:#DBEAFE;color:#1D4ED8;font-size:9px;font-weight:800;text-transform:uppercase; }
        .file-link { color:#2563EB;text-decoration:none;font-weight:700; }
        .empty { padding:42px 20px;text-align:center;color:#64748B; }
        .postmortem-section { margin-top:26px; }
        .postmortem-section h2 { margin:0 0 13px;font-size:16px; }
        .postmortem-card { margin-bottom:13px;border:1px solid #CBD5E1;border-radius:11px;overflow:hidden;break-inside:avoid; }
        .postmortem-head { padding:11px 13px;background:#EFF6FF;color:#1E40AF;font-size:12px;font-weight:800; }
        .postmortem-body { padding:13px;font-size:11px;line-height:1.55; }
        .postmortem-row { display:grid;grid-template-columns:125px 1fr;gap:10px;margin-bottom:8px; }
        .postmortem-row:last-child { margin-bottom:0; }
        .postmortem-row strong { color:#475569; }
        .swot-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-top:11px; }
        .swot-box { padding:10px;border-radius:8px;background:#F8FAFC;border:1px solid #E2E8F0; }
        .swot-box strong { display:block;margin-bottom:4px;font-size:10px;text-transform:uppercase;color:#475569; }
        .report-footer { display:flex;justify-content:space-between;gap:16px;margin-top:30px;padding-top:14px;border-top:1px solid #CBD5E1;color:#64748B;font-size:10px; }
        @media (max-width:780px) { .report-sheet{padding:22px}.summary-grid{grid-template-columns:repeat(2,1fr)}.report-head{align-items:flex-start}.period{text-align:left}.report-head{flex-direction:column}.swot-grid{grid-template-columns:1fr} }
        @media (max-width:480px) { .report-sheet{width:100%;margin:0;padding:18px;box-shadow:none}.report-toolbar{margin:12px 0}.summary-grid{grid-template-columns:1fr}.report-button{flex:1}.report-actions{width:100%} }
        @media print {
            @page { size:A4 landscape;margin:10mm; }
            body { background:#FFF; }
            .report-toolbar { display:none!important; }
            .report-sheet { width:100%;min-height:0;margin:0;padding:0;box-shadow:none; }
            .table-wrap { overflow:visible; }
            thead { display:table-header-group; }
            tr { break-inside:avoid; }
            .report-footer { position:fixed;bottom:0;left:0;right:0; }
        }
    </style>
</head>
<body>
    <div class="report-toolbar">
        <a class="report-button" href="index.php?page=tender_board">Back to Tender Board</a>
        <div class="report-actions">
            <a class="report-button" href="index.php?page=tender_board_report&amp;month=<?= urlencode($monthYear) ?>&amp;format=csv">Download CSV</a>
            <a class="report-button" href="index.php?page=tender_board_report&amp;month=<?= urlencode($monthYear) ?>&amp;format=pdf">Download PDF</a>
            <button class="report-button primary" type="button" onclick="window.print()">Print Report</button>
        </div>
    </div>

    <main class="report-sheet">
        <header class="report-head">
            <div class="report-brand">
                <img class="report-logo" src="<?= htmlspecialchars(Helper::url('assets/images/logo.png')) ?>" alt="TBBA logo">
                <div>
                    <h1>The Bridge Business Alliance (TBBA)</h1>
                    <p>Personal Monthly Tender Report</p>
                </div>
            </div>
            <div class="period">
                <strong><?= htmlspecialchars($periodLabel) ?></strong>
                <span>Generated <?= date('d M Y, h:i A') ?></span>
            </div>
        </header>

        <section class="staff-panel">
            <div>
                <strong><?= htmlspecialchars((string)($user['name'] ?? 'Staff')) ?></strong>
                <span><?= htmlspecialchars((string)($user['email'] ?? '')) ?></span>
            </div>
            <div>
                <strong><?= htmlspecialchars((string)($rows[0]['position_title'] ?? 'Staff Member')) ?></strong>
                <span><?= htmlspecialchars((string)($rows[0]['department_name'] ?? 'Department not assigned')) ?></span>
            </div>
        </section>

        <section class="summary-grid">
            <div class="summary-card"><span>Joined Tenders</span><strong><?= (int)$summary['tender_count'] ?></strong></div>
            <div class="summary-card"><span>Priced Tenders</span><strong><?= (int)$summary['priced_count'] ?></strong></div>
            <div class="summary-card"><span>Post-Mortems</span><strong><?= (int)$summary['post_mortem_count'] ?></strong></div>
            <div class="summary-card"><span>Total Selling Price</span><strong>RM <?= number_format((float)$summary['selling_total'], 2) ?></strong></div>
            <div class="summary-card"><span>Gross Margin</span><strong style="color:<?= $summary['gross_margin'] < 0 ? '#B91C1C' : '#047857' ?>;">RM <?= number_format((float)$summary['gross_margin'], 2) ?></strong></div>
        </section>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>QT Number</th><th>Tender Title</th><th>Joined</th><th>Closing Date</th>
                        <th class="money">Indicative (RM)</th><th class="money">Cost (RM)</th><th class="money">Selling (RM)</th><th class="money">Margin (RM)</th><th>Status</th><th>File</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                    <tr><td colspan="11" class="empty">No joined tenders were recorded for this month.</td></tr>
                    <?php else: ?>
                    <?php foreach ($rows as $index => $row): ?>
                    <?php $margin = (float)($row['selling_price'] ?? 0) - (float)($row['cost'] ?? 0); ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><strong><?= htmlspecialchars($row['qt_number']) ?></strong></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= date('d M Y', strtotime($row['joined_at'])) ?></td>
                        <td><?= date('d M Y', strtotime($row['closing_date'])) ?></td>
                        <td class="money"><?= $row['indicative_price'] === null ? '-' : number_format((float)$row['indicative_price'], 2) ?></td>
                        <td class="money"><?= $row['cost'] === null ? '-' : number_format((float)$row['cost'], 2) ?></td>
                        <td class="money"><?= $row['selling_price'] === null ? '-' : number_format((float)$row['selling_price'], 2) ?></td>
                        <td class="money" style="color:<?= $margin < 0 ? '#B91C1C' : '#047857' ?>;"><?= $row['selling_price'] === null || $row['cost'] === null ? '-' : number_format($margin, 2) ?></td>
                        <td><span class="status"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['status']))) ?></span></td>
                        <td>
                            <?php if ($row['pricing_attachment_name']): ?>
                            <a class="file-link" href="index.php?action=download_tender_pricing_file&amp;id=<?= (int)$row['opportunity_id'] ?>">Download</a>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $postMortemRows = array_values(array_filter($rows, static function ($row) {
            foreach (['post_mortem_summary', 'post_mortem_factors', 'swot_s', 'swot_w', 'swot_o', 'swot_t'] as $field) {
                if (trim((string)($row[$field] ?? '')) !== '') return true;
            }
            return false;
        }));
        ?>
        <section class="postmortem-section">
            <h2>Post-Mortem &amp; SWOT Details</h2>
            <?php if (!$postMortemRows): ?>
            <div class="empty" style="border:1px solid #E2E8F0;border-radius:11px;">No completed post-mortem or SWOT details were recorded for this month.</div>
            <?php else: ?>
            <?php foreach ($postMortemRows as $row): ?>
            <article class="postmortem-card">
                <div class="postmortem-head"><?= htmlspecialchars($row['qt_number']) ?> — <?= htmlspecialchars($row['title']) ?></div>
                <div class="postmortem-body">
                    <div class="postmortem-row"><strong>Status</strong><span><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['status']))) ?></span></div>
                    <div class="postmortem-row"><strong>Key Factors</strong><span><?= nl2br(htmlspecialchars((string)($row['post_mortem_factors'] ?: '-'))) ?></span></div>
                    <div class="postmortem-row"><strong>Summary</strong><span><?= nl2br(htmlspecialchars((string)($row['post_mortem_summary'] ?: '-'))) ?></span></div>
                    <div class="swot-grid">
                        <div class="swot-box"><strong>Strengths</strong><?= nl2br(htmlspecialchars((string)($row['swot_s'] ?: '-'))) ?></div>
                        <div class="swot-box"><strong>Weaknesses</strong><?= nl2br(htmlspecialchars((string)($row['swot_w'] ?: '-'))) ?></div>
                        <div class="swot-box"><strong>Opportunities</strong><?= nl2br(htmlspecialchars((string)($row['swot_o'] ?: '-'))) ?></div>
                        <div class="swot-box"><strong>Threats</strong><?= nl2br(htmlspecialchars((string)($row['swot_t'] ?: '-'))) ?></div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <footer class="report-footer">
            <span>Confidential - Internal Use Only</span>
            <span>TBBA Enterprise Management System</span>
        </footer>
    </main>
</body>
</html>
