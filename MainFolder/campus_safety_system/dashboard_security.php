<?php
require_once 'config.php';
requireSecurityPersonnel();

$secId     = $_SESSION['user_id'];
$firstName = $_SESSION['first_name'];
$view      = sanitizeStrict($_GET['view'] ?? 'dashboard');
$view      = in_array($view, ['dashboard', 'active_alerts', 'all_alerts', 'incidents']) ? $view : 'dashboard';

// ─── Handle Actions (AJAX-friendly or form POST) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrf();
    $action  = sanitizeStrict($_POST['action']);
    $alertId = sanitizeStrict($_POST['alert_id'] ?? '');

    // Validate alert_id format
    if (!preg_match('/^[0-9a-f\-]{36}$/i', $alertId)) {
        setFlash('danger', 'Invalid alert ID.');
        header('Location: dashboard_security.php?view=' . $view);
        exit();
    }

    if ($action === 'accept_alert') {
        // Accept: create dispatch, update alert status
        $conn->begin_transaction();
        try {
            $dispatchId = generateUUID();
            $now        = date('Y-m-d H:i:s');
            $s1 = $conn->prepare(
                "INSERT INTO alert_dispatches
                    (dispatch_id, alert_id, security_id, dispatch_status, notified_at, accepted_at)
                 VALUES (?, ?, ?, 'ACCEPTED', ?, ?)
                 ON DUPLICATE KEY UPDATE dispatch_status='ACCEPTED', accepted_at=?"
            );
            $s1->bind_param("ssssss", $dispatchId, $alertId, $secId, $now, $now, $now);
            $s1->execute(); $s1->close();

            $s2 = $conn->prepare(
                "UPDATE alerts SET alert_status='ACCEPTED' WHERE alert_id=? AND alert_status IN ('PENDING','DISPATCHED')"
            );
            $s2->bind_param("s", $alertId); $s2->execute(); $s2->close();

            $conn->commit();
            setFlash('success', 'Alert accepted. Proceed to location.');
        } catch (Exception $e) {
            $conn->rollback();
            setFlash('danger', 'Action failed. Please try again.');
        }

    } elseif ($action === 'mark_in_progress') {
        $s = $conn->prepare(
            "UPDATE alerts SET alert_status='IN_PROGRESS' WHERE alert_id=?"
        );
        $s->bind_param("s", $alertId); $s->execute(); $s->close();

        $sd = $conn->prepare(
            "UPDATE alert_dispatches SET dispatch_status='ARRIVED', arrived_at=NOW()
             WHERE alert_id=? AND security_id=?"
        );
        $sd->bind_param("ss", $alertId, $secId); $sd->execute(); $sd->close();
        setFlash('success', 'Status updated to In Progress.');

    } elseif ($action === 'resolve_alert') {
        $outcome = sanitizeStrict($_POST['outcome'] ?? 'RESOLVED');
        $notes   = sanitizeStrict($_POST['notes']   ?? '');
        $allowedOutcomes = ['RESOLVED', 'FALSE_ALARM', 'ESCALATED', 'CANCELLED'];
        if (!in_array($outcome, $allowedOutcomes)) $outcome = 'RESOLVED';

        $conn->begin_transaction();
        try {
            // Update alert status
            $s1 = $conn->prepare(
                "UPDATE alerts SET alert_status='RESOLVED', resolved_at=NOW() WHERE alert_id=?"
            );
            $s1->bind_param("s", $alertId); $s1->execute(); $s1->close();

            // Update dispatch
            $s2 = $conn->prepare(
                "UPDATE alert_dispatches SET dispatch_status='COMPLETED', completed_at=NOW()
                 WHERE alert_id=? AND security_id=?"
            );
            $s2->bind_param("ss", $alertId, $secId); $s2->execute(); $s2->close();

            // Create incident record
            $recordId = generateUUID();
            $s3 = $conn->prepare(
                "INSERT INTO incident_records
                    (record_id, alert_id, handled_by_security_id, outcome, resolution_notes)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE outcome=?, resolution_notes=?, closed_at=NOW()"
            );
            $s3->bind_param("sssssss", $recordId, $alertId, $secId, $outcome, $notes, $outcome, $notes);
            $s3->execute(); $s3->close();

            $conn->commit();
            setFlash('success', 'Alert resolved and incident record filed.');
        } catch (Exception $e) {
            $conn->rollback();
            setFlash('danger', 'Failed to resolve alert. Please try again.');
        }

    } elseif ($action === 'toggle_duty') {
        $newStatus = ($_SESSION['duty_status'] === 'ON_DUTY') ? 'OFF_DUTY' : 'ON_DUTY';
        $s = $conn->prepare(
            "UPDATE security_personnel SET duty_status=? WHERE security_id=?"
        );
        $s->bind_param("ss", $newStatus, $secId); $s->execute(); $s->close();
        $_SESSION['duty_status'] = $newStatus;
        setFlash('success', 'Duty status updated to ' . str_replace('_', ' ', $newStatus) . '.');
    }

    header('Location: dashboard_security.php?view=' . $view);
    exit();
}

// ─── Fetch Stats ──────────────────────────────────────────────────────────────
$statsRow = $conn->query(
    "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN alert_status='PENDING'     THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN alert_status='IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN alert_status='RESOLVED'    THEN 1 ELSE 0 END) as resolved
     FROM alerts"
)->fetch_assoc();

// ─── Fetch Active Alerts (PENDING + ACCEPTED + IN_PROGRESS) ──────────────────
$activeAlerts = [];
$res = $conn->query(
    "SELECT a.alert_id, a.emergency_type, a.alert_text, a.location_description,
            a.alert_status, a.created_at,
            u.first_name, u.last_name, u.phone, u.university_id, u.role as user_role,
            a.latitude, a.longitude
     FROM alerts a
     JOIN university_users u ON u.user_id = a.user_id
     WHERE a.alert_status IN ('PENDING', 'ACCEPTED', 'IN_PROGRESS', 'DISPATCHED')
     ORDER BY a.created_at DESC"
);
while ($row = $res->fetch_assoc()) $activeAlerts[] = $row;

// ─── All Alerts ───────────────────────────────────────────────────────────────
$allAlerts = [];
if ($view === 'all_alerts') {
    $res = $conn->query(
        "SELECT a.alert_id, a.emergency_type, a.alert_text, a.location_description,
                a.alert_status, a.created_at, a.resolved_at,
                u.first_name, u.last_name, u.university_id,
                (SELECT COUNT(*) FROM alert_dispatches d WHERE d.alert_id=a.alert_id) as dispatch_count
         FROM alerts a
         JOIN university_users u ON u.user_id = a.user_id
         ORDER BY a.created_at DESC
         LIMIT 100"
    );
    while ($row = $res->fetch_assoc()) $allAlerts[] = $row;
}

// ─── Incident Records ─────────────────────────────────────────────────────────
$incidents = [];
if ($view === 'incidents') {
    $res = $conn->query(
        "SELECT ir.record_id, ir.outcome, ir.resolution_notes, ir.closed_at,
                a.emergency_type, a.location_description,
                sp.first_name, sp.last_name, sp.staff_id
         FROM incident_records ir
         JOIN alerts a            ON a.alert_id           = ir.alert_id
         JOIN security_personnel sp ON sp.security_id     = ir.handled_by_security_id
         ORDER BY ir.closed_at DESC
         LIMIT 100"
    );
    while ($row = $res->fetch_assoc()) $incidents[] = $row;
}

$emergTypes = EMERGENCY_TYPES;
$dutyStatus = $_SESSION['duty_status'] ?? 'OFF_DUTY';

$pageTitleMap = [
    'dashboard'    => 'Security Dashboard',
    'active_alerts'=> 'Active Alerts',
    'all_alerts'   => 'All Requests',
    'incidents'    => 'Incident Records',
];
$pageTitle = $pageTitleMap[$view];
$activeNav = $view;

include 'partials/layout_start.php';
?>

<!-- ─── Duty toggle ───────────────────────────────────────────────────────── -->
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
        <h4 class="fw-bold mb-0">Welcome, Officer <?= htmlspecialchars($firstName) ?> 👮</h4>
        <p class="text-muted mb-0">
            <?php if ($view === 'dashboard'): ?>Campus security overview and active incidents.
            <?php elseif ($view === 'active_alerts'): ?>Incoming emergency alerts requiring attention.
            <?php elseif ($view === 'all_alerts'): ?>Complete history of all campus alerts.
            <?php else: ?>Filed incident reports and resolutions.
            <?php endif; ?>
        </p>
    </div>
    <form method="POST" action="dashboard_security.php?view=<?= $view ?>" class="mb-0">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="toggle_duty">
        <button type="submit" class="btn <?= $dutyStatus === 'ON_DUTY' ? 'btn-success' : 'btn-outline-secondary' ?>">
            <i class="fa fa-circle me-1"></i>
            <?= $dutyStatus === 'ON_DUTY' ? 'ON DUTY — Click to Go Off Duty' : 'OFF DUTY — Click to Go On Duty' ?>
        </button>
    </form>
</div>

<?php if ($view === 'dashboard'): ?>
<!-- ═══════════════ SECURITY DASHBOARD HOME ═══════════════ -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fa fa-bell"></i></div>
            <div class="stat-value"><?= (int)($statsRow['total'] ?? 0) ?></div>
            <div class="stat-label">Total Alerts Today</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="fa fa-clock"></i></div>
            <div class="stat-value"><?= (int)($statsRow['pending'] ?? 0) ?></div>
            <div class="stat-label">Pending Response</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card orange">
            <div class="stat-icon orange"><i class="fa fa-person-running"></i></div>
            <div class="stat-value"><?= (int)($statsRow['in_progress'] ?? 0) ?></div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fa fa-circle-check"></i></div>
            <div class="stat-value"><?= (int)($statsRow['resolved'] ?? 0) ?></div>
            <div class="stat-label">Resolved</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Active Alerts panel -->
    <div class="col-xl-8">
        <div class="content-card">
            <div class="card-header-cs">
                <h5>
                    <i class="fa fa-bell text-danger me-2"></i>
                    Active Alerts
                    <?php if (count($activeAlerts) > 0): ?>
                        <span class="badge bg-danger ms-2"><?= count($activeAlerts) ?></span>
                    <?php endif; ?>
                </h5>
                <span class="live-indicator"><span class="notif-dot"></span>Live</span>
            </div>
            <div class="card-body-cs" style="padding:0 16px 16px;">
                <?php if (empty($activeAlerts)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa fa-shield-check"></i></div>
                        <h5>All Clear</h5>
                        <p class="text-muted">No active alerts at this time.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($activeAlerts as $alert): ?>
                    <div class="alert-item priority-<?= $alert['alert_status'] === 'PENDING' ? 'high' : 'med' ?> mt-3">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <span class="emergency-badge">
                                    <?= htmlspecialchars($emergTypes[$alert['emergency_type']] ?? $alert['emergency_type']) ?>
                                </span>
                                <div class="fw-semibold mt-1">
                                    <?= htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']) ?>
                                    <span class="text-muted small">(<?= htmlspecialchars($alert['university_id']) ?>)</span>
                                </div>
                                <div class="alert-location">
                                    <i class="fa fa-location-dot me-1"></i>
                                    <?= htmlspecialchars($alert['location_description'] ?? 'Unknown') ?>
                                </div>
                                <?php if ($alert['phone']): ?>
                                    <div class="text-muted small mt-1">
                                        <i class="fa fa-phone me-1"></i>
                                        <a href="tel:<?= htmlspecialchars($alert['phone']) ?>"><?= htmlspecialchars($alert['phone']) ?></a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <span class="status-badge status-<?= $alert['alert_status'] ?>">
                                    <?= str_replace('_', ' ', $alert['alert_status']) ?>
                                </span>
                                <div class="alert-time mt-1"><?= date('g:i A', strtotime($alert['created_at'])) ?></div>
                            </div>
                        </div>

                        <?php if (!empty($alert['alert_text'])): ?>
                            <p class="text-muted small mb-2 mt-2"><?= htmlspecialchars($alert['alert_text']) ?></p>
                        <?php endif; ?>

                        <!-- Action buttons -->
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            <?php if ($alert['alert_status'] === 'PENDING' || $alert['alert_status'] === 'DISPATCHED'): ?>
                                <form method="POST" action="dashboard_security.php?view=dashboard" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action"   value="accept_alert">
                                    <input type="hidden" name="alert_id" value="<?= htmlspecialchars($alert['alert_id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger fw-semibold">
                                        <i class="fa fa-check me-1"></i>Accept
                                    </button>
                                </form>
                            <?php elseif ($alert['alert_status'] === 'ACCEPTED'): ?>
                                <form method="POST" action="dashboard_security.php?view=dashboard" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action"   value="mark_in_progress">
                                    <input type="hidden" name="alert_id" value="<?= htmlspecialchars($alert['alert_id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-warning fw-semibold">
                                        <i class="fa fa-person-running me-1"></i>En Route
                                    </button>
                                </form>
                            <?php elseif ($alert['alert_status'] === 'IN_PROGRESS'): ?>
                                <button type="button" class="btn btn-sm btn-success fw-semibold"
                                        data-bs-toggle="modal" data-bs-target="#resolveModal"
                                        data-alertid="<?= htmlspecialchars($alert['alert_id']) ?>">
                                    <i class="fa fa-flag-checkered me-1"></i>Resolve
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Stats sidebar -->
    <div class="col-xl-4">
        <div class="content-card mb-3">
            <div class="card-header-cs">
                <h5><i class="fa fa-clock-rotate-left text-danger me-2"></i>Quick Stats</h5>
            </div>
            <div class="card-body-cs">
                <?php
                    $myDispatches = $conn->prepare(
                        "SELECT COUNT(*) as c FROM alert_dispatches WHERE security_id=? AND DATE(notified_at)=CURDATE()"
                    );
                    $myDispatches->bind_param("s", $secId);
                    $myDispatches->execute();
                    $myCount = (int)($myDispatches->get_result()->fetch_assoc()['c'] ?? 0);
                    $myDispatches->close();
                ?>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">My dispatches today</span>
                    <strong><?= $myCount ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Pending alerts</span>
                    <strong class="text-danger"><?= (int)($statsRow['pending'] ?? 0) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Resolved today</span>
                    <strong class="text-success"><?= (int)($statsRow['resolved'] ?? 0) ?></strong>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header-cs"><h5><i class="fa fa-info-circle text-danger me-2"></i>Quick Actions</h5></div>
            <div class="card-body-cs d-grid gap-2">
                <a href="dashboard_security.php?view=active_alerts" class="btn btn-outline-danger">
                    <i class="fa fa-bell me-1"></i> View All Active Alerts
                </a>
                <a href="dashboard_security.php?view=all_alerts" class="btn btn-outline-secondary">
                    <i class="fa fa-table-list me-1"></i> Full Request History
                </a>
                <a href="dashboard_security.php?view=incidents" class="btn btn-outline-secondary">
                    <i class="fa fa-clipboard me-1"></i> Incident Records
                </a>
            </div>
        </div>
    </div>
</div>

<?php elseif ($view === 'active_alerts'): ?>
<!-- ═══════════════ ACTIVE ALERTS ═══════════════ -->
<?php if (empty($activeAlerts)): ?>
    <div class="content-card">
        <div class="empty-state py-5">
            <div class="empty-icon"><i class="fa fa-shield-check"></i></div>
            <h5>All Clear — No Active Alerts</h5>
            <p class="text-muted">Campus is calm. Stand by for incoming alerts.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
    <?php foreach ($activeAlerts as $alert): ?>
        <div class="col-md-6 col-xl-4">
            <div class="content-card h-100">
                <div class="card-header-cs" style="background:<?= $alert['alert_status'] === 'PENDING' ? 'var(--cs-red-pale)' : '#FDF2E9' ?>;">
                    <h5 class="fs-6"><?= htmlspecialchars($emergTypes[$alert['emergency_type']] ?? $alert['emergency_type']) ?></h5>
                    <span class="status-badge status-<?= $alert['alert_status'] ?>"><?= str_replace('_', ' ', $alert['alert_status']) ?></span>
                </div>
                <div class="card-body-cs">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div style="width:32px;height:32px;border-radius:50%;background:var(--cs-red-pale);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;color:var(--cs-red);flex-shrink:0;">
                            <?= strtoupper(substr($alert['first_name'],0,1).substr($alert['last_name'],0,1)) ?>
                        </div>
                        <div>
                            <div class="fw-semibold small"><?= htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']) ?></div>
                            <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($alert['university_id']) ?> · <?= ucfirst($alert['user_role']) ?></div>
                        </div>
                    </div>
                    <div class="small text-muted mb-1">
                        <i class="fa fa-location-dot me-1"></i><?= htmlspecialchars($alert['location_description'] ?? '—') ?>
                    </div>
                    <?php if ($alert['phone']): ?>
                        <div class="small mb-2">
                            <i class="fa fa-phone me-1 text-muted"></i>
                            <a href="tel:<?= htmlspecialchars($alert['phone']) ?>" class="text-decoration-none">
                                <?= htmlspecialchars($alert['phone']) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($alert['alert_text'])): ?>
                        <p class="small text-muted border rounded p-2 mb-2" style="background:var(--cs-gray-50);">
                            <?= htmlspecialchars($alert['alert_text']) ?>
                        </p>
                    <?php endif; ?>
                    <div class="alert-time mb-3"><?= date('M j, Y g:i A', strtotime($alert['created_at'])) ?></div>

                    <div class="d-flex gap-2 flex-wrap">
                        <?php if (in_array($alert['alert_status'], ['PENDING', 'DISPATCHED'])): ?>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="accept_alert">
                                <input type="hidden" name="alert_id" value="<?= htmlspecialchars($alert['alert_id']) ?>">
                                <button class="btn btn-sm btn-danger"><i class="fa fa-check me-1"></i>Accept</button>
                            </form>
                        <?php elseif ($alert['alert_status'] === 'ACCEPTED'): ?>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="mark_in_progress">
                                <input type="hidden" name="alert_id" value="<?= htmlspecialchars($alert['alert_id']) ?>">
                                <button class="btn btn-sm btn-warning"><i class="fa fa-person-running me-1"></i>En Route</button>
                            </form>
                        <?php elseif ($alert['alert_status'] === 'IN_PROGRESS'): ?>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#resolveModal"
                                    data-alertid="<?= htmlspecialchars($alert['alert_id']) ?>">
                                <i class="fa fa-flag-checkered me-1"></i>Resolve
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php elseif ($view === 'all_alerts'): ?>
<!-- ═══════════════ ALL REQUESTS ═══════════════ -->
<div class="content-card">
    <div class="card-header-cs">
        <h5><i class="fa fa-table-list text-danger me-2"></i>All Alert Requests</h5>
        <span class="text-muted small"><?= count($allAlerts) ?> records</span>
    </div>
    <div class="table-responsive">
        <?php if (empty($allAlerts)): ?>
            <div class="empty-state"><div class="empty-icon"><i class="fa fa-inbox"></i></div><h5>No alerts yet</h5></div>
        <?php else: ?>
        <table class="cs-table">
            <thead><tr>
                <th>Reporter</th><th>Emergency</th><th>Location</th>
                <th>Status</th><th>Officers</th><th>Date</th>
            </tr></thead>
            <tbody>
            <?php foreach ($allAlerts as $a): ?>
            <tr>
                <td>
                    <div class="fw-semibold small"><?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?></div>
                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($a['university_id']) ?></div>
                </td>
                <td><span class="emergency-badge" style="font-size:.72rem;">
                    <?= htmlspecialchars($emergTypes[$a['emergency_type']] ?? $a['emergency_type']) ?>
                </span></td>
                <td class="text-muted small"><?= htmlspecialchars($a['location_description'] ?? '—') ?></td>
                <td><span class="status-badge status-<?= $a['alert_status'] ?>"><?= str_replace('_',' ',$a['alert_status']) ?></span></td>
                <td class="text-center">
                    <?php if ($a['dispatch_count'] > 0): ?>
                        <span class="badge bg-success"><?= $a['dispatch_count'] ?></span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="text-muted small"><?= date('M j, Y g:i A', strtotime($a['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($view === 'incidents'): ?>
<!-- ═══════════════ INCIDENT RECORDS ═══════════════ -->
<div class="content-card">
    <div class="card-header-cs">
        <h5><i class="fa fa-clipboard text-danger me-2"></i>Incident Records</h5>
        <span class="text-muted small"><?= count($incidents) ?> records</span>
    </div>
    <div class="table-responsive">
        <?php if (empty($incidents)): ?>
            <div class="empty-state"><div class="empty-icon"><i class="fa fa-clipboard"></i></div><h5>No incident records yet</h5></div>
        <?php else: ?>
        <table class="cs-table">
            <thead><tr>
                <th>Emergency</th><th>Location</th><th>Outcome</th>
                <th>Handled By</th><th>Resolution Notes</th><th>Closed At</th>
            </tr></thead>
            <tbody>
            <?php foreach ($incidents as $inc): ?>
            <tr>
                <td><span class="emergency-badge" style="font-size:.72rem;">
                    <?= htmlspecialchars($emergTypes[$inc['emergency_type']] ?? $inc['emergency_type']) ?>
                </span></td>
                <td class="text-muted small"><?= htmlspecialchars($inc['location_description'] ?? '—') ?></td>
                <td>
                    <?php
                        $oColors = ['RESOLVED'=>'success','FALSE_ALARM'=>'secondary','ESCALATED'=>'warning','CANCELLED'=>'dark'];
                        $oc = $oColors[$inc['outcome']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $oc ?>"><?= htmlspecialchars($inc['outcome']) ?></span>
                </td>
                <td>
                    <div class="small fw-semibold"><?= htmlspecialchars($inc['first_name'].' '.$inc['last_name']) ?></div>
                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($inc['staff_id']) ?></div>
                </td>
                <td class="text-muted small" style="max-width:200px;">
                    <?= htmlspecialchars(substr($inc['resolution_notes'] ?? '', 0, 80)) ?>
                    <?= strlen($inc['resolution_notes'] ?? '') > 80 ? '…' : '' ?>
                </td>
                <td class="text-muted small"><?= date('M j, Y g:i A', strtotime($inc['closed_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ═══ RESOLVE MODAL ═══════════════════════════════════════════════════════ -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fa fa-flag-checkered text-success me-2"></i>Resolve Alert</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="dashboard_security.php?view=<?= $view ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="resolve_alert">
                <input type="hidden" name="alert_id" id="resolveAlertId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Outcome <span class="text-danger">*</span></label>
                        <select class="form-select" name="outcome" required>
                            <option value="RESOLVED">✅ Resolved — Incident handled</option>
                            <option value="FALSE_ALARM">⚠️ False Alarm — No real emergency</option>
                            <option value="ESCALATED">🚨 Escalated — Referred to higher authority</option>
                            <option value="CANCELLED">❌ Cancelled — Alert withdrawn</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Resolution Notes</label>
                        <textarea class="form-control" name="notes" rows="3" maxlength="1000"
                                  placeholder="Describe how the incident was handled…"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="fa fa-check me-1"></i>File & Close
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'partials/layout_end.php'; ?>

<script>
// Pass alert ID to resolve modal
document.getElementById('resolveModal')?.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('resolveAlertId').value = btn.dataset.alertid;
});

// Auto-refresh active alerts every 30s
<?php if (in_array($view, ['dashboard', 'active_alerts'])): ?>
setTimeout(function() { location.reload(); }, 30000);
<?php endif; ?>
</script>
