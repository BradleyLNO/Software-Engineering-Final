<?php
require_once 'config.php';
requireUniversityUser();

$userId    = $_SESSION['user_id'];
$firstName = $_SESSION['first_name'];
$view      = sanitizeStrict($_GET['view'] ?? 'dashboard');
$view      = in_array($view, ['dashboard', 'send_alert', 'my_alerts']) ? $view : 'dashboard';

// ─── Handle Alert Submission ──────────────────────────────────────────────────
$alertError   = '';
$alertSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_alert') {
    requireCsrf();

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit("alert_{$userId}", 5, 300)) {
        $alertError = "You've sent too many alerts recently. Please wait before sending another.";
    } else {
        $emergencyType = sanitizeStrict($_POST['emergency_type'] ?? '');
        $alertText     = sanitizeStrict($_POST['alert_text'] ?? '');
        $locationMode  = $_POST['location_mode'] ?? 'dropdown';
        $locationDesc  = sanitizeStrict($_POST['location_description'] ?? '');

        // Coordinates (map mode)
        $latitude  = filter_input(INPUT_POST, 'latitude',  FILTER_VALIDATE_FLOAT) ?: null;
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT) ?: null;

        // Dropdown mode — map to placeholder coords
        if ($locationMode === 'dropdown') {
            $campusLoc   = sanitizeStrict($_POST['campus_location'] ?? '');
            $locationDesc = $locationDesc ?: array_merge(constant('CAMPUS_LOCATIONS'))[$campusLoc] ?? 'On Campus';
            // Placeholder coords — will be replaced with real indoor map data later
            $latitude  = $latitude  ?? 5.6037;   // e.g. Accra placeholder
            $longitude = $longitude ?? -0.1870;
        }

        $allowedTypes = array_keys(EMERGENCY_TYPES);
        if (!in_array($emergencyType, $allowedTypes)) {
            $alertError = "Please select a valid emergency type.";
        } elseif (empty($locationDesc) && $locationMode === 'dropdown') {
            $alertError = "Please select a campus location.";
        } elseif (strlen($alertText) > 1000) {
            $alertError = "Description is too long (max 1000 characters).";
        } elseif ($latitude === null || $longitude === null) {
            $alertError = "Location coordinates are required.";
        } else {
            $alertId = generateUUID();
            $status  = 'PENDING';

            $stmt = $conn->prepare(
                "INSERT INTO alerts
                    (alert_id, user_id, emergency_type, alert_text, latitude, longitude,
                     location_description, alert_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssddss",
                $alertId, $userId, $emergencyType, $alertText,
                $latitude, $longitude, $locationDesc, $status
            );

            if ($stmt->execute()) {
                setFlash('success', '🚨 Your alert has been sent and security has been notified!');
                header('Location: dashboard_user.php?view=my_alerts');
                exit();
            } else {
                $alertError = "Failed to send alert. Please try again.";
            }
            $stmt->close();
        }
    }
}

// ─── Fetch Stats ──────────────────────────────────────────────────────────────
$stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0];
$r = $conn->prepare(
    "SELECT alert_status, COUNT(*) as cnt FROM alerts WHERE user_id = ? GROUP BY alert_status"
);
$r->bind_param("s", $userId);
$r->execute();
$res = $r->get_result();
while ($row = $res->fetch_assoc()) {
    $stats['total'] += $row['cnt'];
    $statusKey = strtolower($row['alert_status']);
    if ($statusKey === 'pending')                   $stats['pending']     += $row['cnt'];
    if ($statusKey === 'in_progress')               $stats['in_progress'] += $row['cnt'];
    if ($statusKey === 'resolved')                  $stats['resolved']    += $row['cnt'];
}
$r->close();

// ─── Fetch My Alerts ─────────────────────────────────────────────────────────
$myAlerts = [];
$stmt = $conn->prepare(
    "SELECT a.alert_id, a.emergency_type, a.alert_text, a.location_description,
            a.alert_status, a.created_at,
            (SELECT COUNT(*) FROM alert_dispatches d WHERE d.alert_id = a.alert_id) as dispatch_count
     FROM alerts a
     WHERE a.user_id = ?
     ORDER BY a.created_at DESC
     LIMIT 50"
);
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $myAlerts[] = $row;
$stmt->close();

// ─── Page Setup ───────────────────────────────────────────────────────────────
$pageTitleMap = [
    'dashboard'  => 'Dashboard',
    'send_alert' => 'Send Emergency Alert',
    'my_alerts'  => 'My Alerts',
];
$pageTitle  = $pageTitleMap[$view];
$activeNav  = $view === 'send_alert' ? 'send_alert' : ($view === 'my_alerts' ? 'my_alerts' : 'dashboard');
$locations  = CAMPUS_LOCATIONS;
$emergTypes = EMERGENCY_TYPES;

include 'partials/layout_start.php';
?>

<?php if ($view === 'dashboard'): ?>
<!-- ═══════════════ DASHBOARD HOME ═══════════════ -->
<div class="mb-4">
    <h4 class="fw-bold mb-0">Welcome back, <?= $firstName ?>! 👋</h4>
    <p class="text-muted">Here's your campus safety overview.</p>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fa fa-bell"></i></div>
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Alerts Sent</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card orange">
            <div class="stat-icon orange"><i class="fa fa-clock"></i></div>
            <div class="stat-value"><?= $stats['pending'] ?></div>
            <div class="stat-label">Pending Response</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="fa fa-person-running"></i></div>
            <div class="stat-value"><?= $stats['in_progress'] ?></div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fa fa-circle-check"></i></div>
            <div class="stat-value"><?= $stats['resolved'] ?></div>
            <div class="stat-label">Resolved</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Quick Send Alert -->
    <div class="col-lg-5">
        <div class="content-card h-100">
            <div class="card-header-cs">
                <h5><i class="fa fa-bell text-danger me-2"></i>Quick Alert</h5>
            </div>
            <div class="card-body-cs text-center" style="padding:40px 24px;">
                <div style="width:80px;height:80px;border-radius:50%;background:var(--cs-red-pale);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                    <i class="fa fa-triangle-exclamation fa-2x text-danger"></i>
                </div>
                <h5 class="fw-bold">Report an Emergency</h5>
                <p class="text-muted small mb-4">Is something happening on campus? Alert security right now.</p>
                <a href="dashboard_user.php?view=send_alert" class="btn btn-danger btn-auth w-100">
                    <i class="fa fa-bell me-2"></i>Send Emergency Alert
                </a>
                <p class="text-muted mt-3 small">For life-threatening emergencies, always call <strong>112</strong> first.</p>
            </div>
        </div>
    </div>

    <!-- Recent Alerts -->
    <div class="col-lg-7">
        <div class="content-card">
            <div class="card-header-cs">
                <h5><i class="fa fa-clock-rotate-left text-danger me-2"></i>Recent Alerts</h5>
                <a href="dashboard_user.php?view=my_alerts" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body-cs" style="padding:0;">
                <?php if (empty($myAlerts)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa fa-inbox"></i></div>
                        <h5>No alerts yet</h5>
                        <p class="text-muted small">You haven't reported any incidents.</p>
                    </div>
                <?php else: ?>
                    <div style="padding: 12px 16px;">
                    <?php foreach (array_slice($myAlerts, 0, 5) as $alert): ?>
                        <div class="alert-item">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                <div>
                                    <span class="emergency-badge">
                                        <?= htmlspecialchars($emergTypes[$alert['emergency_type']] ?? $alert['emergency_type']) ?>
                                    </span>
                                    <div class="alert-location mt-1">
                                        <i class="fa fa-location-dot me-1"></i>
                                        <?= htmlspecialchars($alert['location_description'] ?? 'Unknown Location') ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="status-badge status-<?= $alert['alert_status'] ?>">
                                        <?= str_replace('_', ' ', $alert['alert_status']) ?>
                                    </span>
                                    <div class="alert-time mt-1">
                                        <?= date('M j, g:i A', strtotime($alert['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($alert['alert_text'])): ?>
                                <p class="text-muted small mb-0 mt-2" style="line-height:1.4;">
                                    <?= htmlspecialchars(substr($alert['alert_text'], 0, 100)) ?>
                                    <?= strlen($alert['alert_text']) > 100 ? '…' : '' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($view === 'send_alert'): ?>
<!-- ═══════════════ SEND ALERT FORM ═══════════════ -->
<div class="row justify-content-center">
<div class="col-lg-8 col-xl-7">

<?php if ($alertError): ?>
    <div class="alert alert-danger mb-3">
        <i class="fa fa-circle-xmark me-2"></i><?= htmlspecialchars($alertError) ?>
    </div>
<?php endif; ?>

<div class="alert-form-card p-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:50px;height:50px;border-radius:12px;background:var(--cs-red-pale);display:flex;align-items:center;justify-content:center;">
            <i class="fa fa-bell fa-lg text-danger"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0">Send Emergency Alert</h4>
            <p class="text-muted small mb-0">Security will be notified immediately.</p>
        </div>
    </div>

    <div class="alert alert-warning py-2">
        <i class="fa fa-triangle-exclamation me-1"></i>
        <strong>Life-threatening emergency?</strong> Call <strong>112</strong> immediately.
    </div>

    <form id="alertForm" method="POST" action="dashboard_user.php?view=send_alert" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="send_alert">

        <!-- Emergency Type -->
        <div class="mb-4">
            <label class="form-label fw-bold">Emergency Type <span class="text-danger">*</span></label>
            <div class="emergency-grid">
                <?php foreach ($emergTypes as $key => $label): ?>
                    <?php
                        preg_match('/^(\S+)\s(.+)$/', $label, $parts);
                        $icon    = $parts[1] ?? '📋';
                        $text    = $parts[2] ?? $label;
                    ?>
                    <input type="radio" class="emergency-opt" name="emergency_type"
                           id="et_<?= $key ?>" value="<?= $key ?>"
                           <?= ($_POST['emergency_type'] ?? '') === $key ? 'checked' : '' ?> required>
                    <label for="et_<?= $key ?>">
                        <span class="opt-icon"><?= $icon ?></span>
                        <span><?= htmlspecialchars($text) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="text-danger small" id="typeError" style="display:none;">Please select an emergency type.</div>
        </div>

        <!-- Location -->
        <div class="mb-4">
            <label class="form-label fw-bold">Location <span class="text-danger">*</span></label>

            <div class="location-mode-tabs mb-3">
                <button type="button" class="loc-tab-btn active" onclick="switchLocMode('dropdown', this)">
                    <i class="fa fa-list me-1"></i>Campus Locations
                </button>
                <button type="button" class="loc-tab-btn" onclick="switchLocMode('map', this)">
                    <i class="fa fa-map-pin me-1"></i>Use Map / GPS
                </button>
            </div>

            <input type="hidden" name="location_mode" id="locationMode" value="dropdown">
            <input type="hidden" name="latitude"      id="latInput"      value="">
            <input type="hidden" name="longitude"     id="lngInput"      value="">

            <!-- Dropdown mode -->
            <div id="locationDropdown">
                <select class="form-select" name="campus_location" id="campusLocationSelect">
                    <?php foreach ($locations as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>"
                            <?= ($_POST['campus_location'] ?? '') === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Select the nearest campus landmark or building.</div>
            </div>

            <!-- Map mode (placeholder — indoor map integration pending) -->
            <div id="locationMap" style="display:none;">
                <div class="map-placeholder">
                    <div class="map-icon"><i class="fa fa-map"></i></div>
                    <strong>Interactive Campus Map</strong>
                    <p class="text-muted small mb-2 text-center" style="max-width:280px;">
                        Indoor campus mapping coming soon. For now, tap below to use your device's GPS coordinates.
                    </p>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="getGPS()">
                        <i class="fa fa-location-crosshairs me-1"></i>Use My GPS Location
                    </button>
                    <div id="gpsStatus" class="small text-muted mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="mb-4">
            <label class="form-label fw-bold">Location Description / Additional Details</label>
            <input type="text" class="form-control" name="location_description"
                   maxlength="255" placeholder="e.g. Near the east entrance of the Science Block"
                   value="<?= htmlspecialchars($_POST['location_description'] ?? '') ?>">
            <div class="form-text">Help security find you faster with extra location details.</div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">What's Happening? <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" name="alert_text" rows="3" maxlength="1000"
                      placeholder="Briefly describe the emergency situation..."><?= htmlspecialchars($_POST['alert_text'] ?? '') ?></textarea>
            <div class="d-flex justify-content-end">
                <span class="form-text" id="charCount">0 / 1000</span>
            </div>
        </div>

        <button type="submit" class="btn btn-danger btn-auth w-100 fw-bold" id="sendBtn">
            <i class="fa fa-bell me-2"></i>Send Alert to Security
        </button>
    </form>
</div>

</div>
</div>

<?php elseif ($view === 'my_alerts'): ?>
<!-- ═══════════════ MY ALERTS TABLE ═══════════════ -->
<div class="content-card">
    <div class="card-header-cs">
        <h5><i class="fa fa-list-check text-danger me-2"></i>My Alert History</h5>
        <a href="dashboard_user.php?view=send_alert" class="btn btn-sm btn-danger">
            <i class="fa fa-plus me-1"></i>New Alert
        </a>
    </div>
    <div class="table-responsive">
        <?php if (empty($myAlerts)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fa fa-inbox"></i></div>
                <h5>No alerts yet</h5>
                <p class="text-muted">You haven't reported any incidents yet.</p>
                <a href="dashboard_user.php?view=send_alert" class="btn btn-danger">
                    <i class="fa fa-bell me-1"></i>Send Your First Alert
                </a>
            </div>
        <?php else: ?>
        <table class="cs-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Officers Dispatched</th>
                    <th>Date / Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myAlerts as $alert): ?>
                <tr>
                    <td>
                        <span class="emergency-badge">
                            <?= htmlspecialchars($emergTypes[$alert['emergency_type']] ?? $alert['emergency_type']) ?>
                        </span>
                        <?php if (!empty($alert['alert_text'])): ?>
                            <div class="text-muted small mt-1" style="max-width:220px;">
                                <?= htmlspecialchars(substr($alert['alert_text'], 0, 60)) ?>
                                <?= strlen($alert['alert_text']) > 60 ? '…' : '' ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <i class="fa fa-location-dot text-muted me-1"></i>
                        <?= htmlspecialchars($alert['location_description'] ?? '—') ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $alert['alert_status'] ?>">
                            <?= str_replace('_', ' ', $alert['alert_status']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <?php if ($alert['dispatch_count'] > 0): ?>
                            <span class="badge bg-success"><?= $alert['dispatch_count'] ?> officer(s)</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small">
                        <?= date('M j, Y', strtotime($alert['created_at'])) ?><br>
                        <?= date('g:i A', strtotime($alert['created_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include 'partials/layout_end.php'; ?>

<script>
// ─── Location mode switch ─────────────────────────────────────
function switchLocMode(mode, btn) {
    document.getElementById('locationMode').value = mode;
    document.getElementById('locationDropdown').style.display = mode === 'dropdown' ? 'block' : 'none';
    document.getElementById('locationMap').style.display      = mode === 'map'      ? 'block' : 'none';
    document.querySelectorAll('.loc-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    // Set placeholder coords for dropdown mode
    if (mode === 'dropdown') {
        document.getElementById('latInput').value = '5.6037';
        document.getElementById('lngInput').value = '-0.1870';
    } else {
        document.getElementById('latInput').value = '';
        document.getElementById('lngInput').value = '';
    }
}

// Set default coords on load (dropdown mode)
document.getElementById('latInput').value = '5.6037';
document.getElementById('lngInput').value = '-0.1870';

// ─── GPS acquisition ──────────────────────────────────────────
function getGPS() {
    const status = document.getElementById('gpsStatus');
    if (!navigator.geolocation) {
        status.textContent = 'Geolocation is not supported on this device.';
        return;
    }
    status.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Acquiring location…';
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            document.getElementById('latInput').value = pos.coords.latitude.toFixed(6);
            document.getElementById('lngInput').value = pos.coords.longitude.toFixed(6);
            status.innerHTML = '<i class="fa fa-check text-success me-1"></i>Location acquired! (' +
                pos.coords.latitude.toFixed(4) + ', ' + pos.coords.longitude.toFixed(4) + ')';
        },
        function(err) {
            status.textContent = 'Could not get location: ' + err.message;
        },
        { timeout: 10000, enableHighAccuracy: true }
    );
}

// ─── Textarea character counter ───────────────────────────────
const textarea = document.querySelector('textarea[name="alert_text"]');
const counter  = document.getElementById('charCount');
if (textarea && counter) {
    textarea.addEventListener('input', function() {
        counter.textContent = this.value.length + ' / 1000';
        counter.style.color = this.value.length > 900 ? '#E74C3C' : '#6C757D';
    });
}

// ─── Alert form validation ────────────────────────────────────
const alertForm = document.getElementById('alertForm');
if (alertForm) {
    alertForm.addEventListener('submit', function(e) {
        const selected = document.querySelector('input[name="emergency_type"]:checked');
        const typeErr  = document.getElementById('typeError');
        const latVal   = document.getElementById('latInput').value;

        if (!selected) {
            typeErr.style.display = 'block';
            e.preventDefault();
            return;
        } else {
            typeErr.style.display = 'none';
        }

        if (!latVal) {
            alert('Please select a location or acquire GPS coordinates.');
            e.preventDefault();
            return;
        }

        const btn = document.getElementById('sendBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Sending alert…';
    });
}
</script>
