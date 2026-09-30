<?php
/**
 * Standalone Formal A4 Executive Report Print & Document View
 * Company: The Bridge Business Alliance (TBBA)
 * Language: Fully English
 * No Dashboard Wrappers, No Sidebar, No Navbar - Pure Document Layout
 */
require_once __DIR__ . '/../../core/Auth.php';
Auth::requireLogin();
$currentUser = Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Executive_Report_<?= $selectedMonth ?>_<?= $selectedYear ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #E2E8F0;
            color: #0F172A;
            line-height: 1.5;
            padding: 30px 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Floating Action Bar (Hidden on Print) */
        .action-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0F172A;
            color: #FFF;
            padding: 12px 20px;
            border-radius: 50px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
        }

        .action-bar a, .action-bar button {
            background: #2563EB;
            color: #FFF;
            border: none;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .action-bar a.btn-pdf {
            background: #10B981;
        }

        .action-bar a.btn-close {
            background: rgba(255,255,255,0.15);
        }

        .action-bar button:hover, .action-bar a:hover {
            opacity: 0.9;
        }

        /* A4 Sheet Container */
        .page-sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 30px auto;
            background: #FFFFFF;
            padding: 20mm 18mm;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            position: relative;
        }

        .header-top {
            border-bottom: 3px solid #0F172A;
            padding-bottom: 16px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .header-title h1 {
            font-size: 20px;
            font-weight: 800;
            color: #0F172A;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .header-title h2 {
            font-size: 13px;
            font-weight: 700;
            color: #2563EB;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header-meta {
            text-align: right;
            font-size: 11px;
            color: #64748B;
        }

        .header-meta strong {
            color: #0F172A;
        }

        /* Summary Box Grid */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .summary-card {
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            padding: 12px;
            background: #F8FAFC;
        }

        .summary-card .label {
            font-size: 10px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
        }

        .summary-card .value {
            font-size: 20px;
            font-weight: 800;
            color: #0F172A;
            margin-top: 4px;
        }

        /* Section Headings */
        .section-title {
            font-size: 13px;
            font-weight: 800;
            color: #0F172A;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1.5px solid #0F172A;
            padding-bottom: 6px;
            margin: 28px 0 12px 0;
        }

        /* Formal Tables */
        .formal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 20px;
        }

        .formal-table th {
            background: #F1F5F9;
            color: #0F172A;
            font-weight: 700;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #CBD5E1;
            text-transform: uppercase;
            font-size: 10px;
        }

        .formal-table td {
            padding: 8px 10px;
            border: 1px solid #E2E8F0;
            color: #1E293B;
            vertical-align: middle;
        }

        .formal-table tr:nth-child(even) td {
            background: #FAFBFD;
        }

        /* Signature Section */
        .signature-block {
            margin-top: 40px;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        .sig-box {
            width: 45%;
        }

        .sig-box .sig-title {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 50px;
        }

        .sig-box .sig-line {
            border-bottom: 1px solid #0F172A;
            width: 90%;
            margin-bottom: 6px;
        }

        .sig-box .sig-name {
            font-size: 12px;
            font-weight: 800;
            color: #0F172A;
        }

        .sig-box .sig-role {
            font-size: 10px;
            color: #64748B;
        }

        /* Responsive Screen View (Phones & Tablets) */
        @media screen and (max-width: 820px) {
            .action-bar {
                flex-wrap: wrap;
                justify-content: center;
                padding: 10px;
                gap: 8px;
            }
            .page-sheet {
                width: 100% !important;
                min-height: auto !important;
                padding: 16px !important;
                margin: 0 auto 16px auto !important;
                box-shadow: none !important;
                overflow-x: auto;
            }
            .summary-grid {
                grid-template-columns: 1fr 1fr !important;
                gap: 10px !important;
            }
            .header-top {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
            }
            .signature-block {
                flex-direction: column !important;
                gap: 36px !important;
            }
            .sig-box {
                width: 100% !important;
            }
            .formal-table {
                font-size: 11px !important;
            }
        }
        @media screen and (max-width: 480px) {
            .summary-grid {
                grid-template-columns: 1fr !important;
            }
        }

        /* Print Specifics */
        @media print {
            body {
                background: #FFF !important;
                padding: 0 !important;
            }
            .action-bar {
                display: none !important;
            }
            .page-sheet {
                box-shadow: none !important;
                margin: 0 !important;
                width: 100% !important;
                min-height: auto !important;
                padding: 0 !important;
            }
            .section-title {
                page-break-after: avoid;
            }
            .formal-table {
                page-break-inside: auto;
            }
            .formal-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            @page {
                size: A4 portrait;
                margin: 15mm;
            }
        }
    </style>
</head>
<body>

    <!-- Floating Control Bar -->
    <div class="action-bar">
        <span style="font-size: 12px; font-weight: 600;">Executive Document Layout</span>
        <button onclick="window.print()">
            <i class="fa-solid fa-print"></i> Print Document
        </button>
        <a href="index.php?page=reports&export=pdf&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&department_id=<?= $selectedDept ?? '' ?>" class="btn-pdf">
            <i class="fa-solid fa-file-pdf"></i> Download Native PDF
        </a>
        <a href="index.php?page=reports&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&department_id=<?= $selectedDept ?? '' ?>" class="btn-close">
            <i class="fa-solid fa-xmark"></i> Back to Dashboard
        </a>
    </div>

    <!-- A4 Document Sheet -->
    <div class="page-sheet">
        <!-- Letterhead Header -->
        <div class="header-top">
            <div class="header-title">
                <h1>THE BRIDGE BUSINESS ALLIANCE (TBBA)</h1>
                <h2>EXECUTIVE CONSOLIDATED MONTHLY REPORT</h2>
            </div>
            <div class="header-meta">
                <div>Period: <strong><?= date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)) ?></strong></div>
                <div>Generated: <strong><?= date('d M Y, H:i') ?></strong></div>
                <div>Prepared By: <strong><?= htmlspecialchars($currentUser['name']) ?></strong></div>
            </div>
        </div>

        <!-- 4 Summary KPI Boxes -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Total Personnel Assessed</div>
                <div class="value"><?= count($users) ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Avg Attendance Rate</div>
                <div class="value" style="color: <?= $avgAttendanceRate >= 90 ? '#059669' : ($avgAttendanceRate >= 80 ? '#D97706' : '#DC2626') ?>;">
                    <?= $avgAttendanceRate ?>%
                </div>
            </div>
            <div class="summary-card">
                <div class="label">Monthly Tender Revenue</div>
                <div class="value" style="color: #7C3AED; font-size: 16px;">
                    RM <?= number_format($totalTendersValue, 2) ?>
                </div>
            </div>
            <div class="summary-card">
                <div class="label">Discipline / KPI Alerts</div>
                <div class="value" style="color: #DC2626;">
                    <?= $lateAlertsCount + $zeroTenderCount ?>
                </div>
            </div>
        </div>

        <!-- Section 1: Staff Scorecard Table -->
        <div class="section-title">SECTION 1: PERSONNEL ATTENDANCE & TENDER KPI SCORECARD</div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 70px;">Staff ID</th>
                    <th>Staff Name & Role</th>
                    <th>Department</th>
                    <th style="text-align: center; width: 65px;">Present</th>
                    <th style="text-align: center; width: 50px;">Late</th>
                    <th style="text-align: center; width: 60px;">Leave/MC</th>
                    <th style="text-align: center; width: 70px;">Att Rate</th>
                    <th style="text-align: center; width: 65px;">Tenders</th>
                    <th style="text-align: right; width: 100px;">Total Value (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px; color: #64748B;">No personnel found for this reporting period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): 
                        $uid = $u['id'];
                        $att = $attendanceByUser[$uid] ?? ['total_days_worked' => 0, 'present_on_time' => 0, 'late' => 0, 'leave_mc_absent' => 0];
                        $tnd = $tendersByUser[$uid] ?? ['count' => 0, 'total_value' => 0.0];

                        $totLogged = $att['present_on_time'] + $att['late'] + $att['leave_mc_absent'];
                        $rate = $totLogged > 0 ? round(($att['present_on_time'] / $totLogged) * 100, 1) : 0;
                        $rateStr = $totLogged > 0 ? $rate . '%' : 'N/A';
                    ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: 700; color: #334155;">
                                <?= htmlspecialchars($u['employee_id'] ?: 'TBBA-'.str_pad($u['id'], 4, '0', STR_PAD_LEFT)) ?>
                            </td>
                            <td>
                                <strong style="color: #0F172A;"><?= htmlspecialchars($u['name']) ?></strong>
                                <div style="font-size: 9px; color: #64748B;"><?= strtoupper($u['role']) ?> | <?= htmlspecialchars($u['position'] ?: 'Staff') ?></div>
                            </td>
                            <td><?= htmlspecialchars($deptMap[$u['department_id'] ?? 0] ?? '-') ?></td>
                            <td style="text-align: center; font-weight: 700; color: #059669;"><?= $att['present_on_time'] ?></td>
                            <td style="text-align: center; font-weight: <?= $att['late'] > 0 ? '700' : '400' ?>; color: <?= $att['late'] > 0 ? '#DC2626' : '#64748B' ?>;"><?= $att['late'] ?></td>
                            <td style="text-align: center; color: #475569;"><?= $att['leave_mc_absent'] ?></td>
                            <td style="text-align: center; font-weight: 700; color: <?= $totLogged > 0 && $rate < 80 ? '#DC2626' : '#0F172A' ?>;"><?= $rateStr ?></td>
                            <td style="text-align: center; font-weight: 700; color: #2563EB;"><?= $tnd['count'] ?></td>
                            <td style="text-align: right; font-weight: 700; color: #0F172A;"><?= number_format($tnd['total_value'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Section 2: Detailed Tender Submissions Log -->
        <div class="section-title">SECTION 2: DETAILED TENDER & PROJECT SUBMISSION LOG</div>
        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Tender ID</th>
                    <th style="width: 130px;">Staff In Charge</th>
                    <th>Project & Client Name</th>
                    <th style="text-align: center; width: 90px;">Closing Date</th>
                    <th style="text-align: right; width: 110px;">Project Value (RM)</th>
                    <th style="text-align: center; width: 85px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tenderRows)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px; color: #64748B;">No tenders or project proposals recorded for this reporting period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tenderRows as $tRow): ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: 700; color: #334155;">
                                TND-<?= str_pad($tRow['id'], 4, '0', STR_PAD_LEFT) ?>
                            </td>
                            <td>
                                <strong style="color: #0F172A;"><?= htmlspecialchars($tRow['staff_name'] ?? 'Unknown') ?></strong>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #0F172A;"><?= htmlspecialchars($tRow['project_name']) ?></div>
                                <div style="font-size: 9px; color: #64748B;">Client: <?= htmlspecialchars($tRow['client_name']) ?></div>
                            </td>
                            <td style="text-align: center;"><?= date('d M Y', strtotime($tRow['closing_date'])) ?></td>
                            <td style="text-align: right; font-weight: 700; color: #0F172A;">RM <?= number_format((float)($tRow['project_value'] ?? 0), 2) ?></td>
                            <?php
                                $statusRaw = strtolower(trim($tRow['status'] ?? ''));
                                if ($statusRaw === 'in_progress') $statusFmt = 'IN PROGRESS';
                                else $statusFmt = strtoupper(str_replace('_', ' ', $statusRaw));

                                $statusColor = '#2563EB'; // default blue
                                if (in_array($statusRaw, ['won', 'approved'])) $statusColor = '#059669';
                                elseif (in_array($statusRaw, ['lost', 'rejected'])) $statusColor = '#DC2626';
                            ?>
                            <td style="text-align: center; font-weight: 700; color: <?= $statusColor ?>;">
                                <?= htmlspecialchars($statusFmt) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Signature Block -->
        <div class="signature-block">
            <div class="sig-box">
                <div class="sig-title">Prepared & Verified By:</div>
                <div class="sig-line"></div>
                <div class="sig-name"><?= htmlspecialchars($currentUser['name']) ?></div>
                <div class="sig-role">Super Administrator / HR Management</div>
                <div class="sig-role" style="margin-top: 2px;">Date: <?= date('d M Y') ?></div>
            </div>

            <div class="sig-box">
                <div class="sig-title">Approved By Executive Management:</div>
                <div class="sig-line"></div>
                <div class="sig-name">Managing Director / Chief Executive Officer</div>
                <div class="sig-role">The Bridge Business Alliance (TBBA)</div>
                <div class="sig-role" style="margin-top: 2px;">Date: ________________________</div>
            </div>
        </div>
    </div>

</body>
</html>
