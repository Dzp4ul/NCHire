<?php
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo '<section class="ui-content-section"><p class="text-red-700">Please sign in to view your teaching load.</p></section>';
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/helpers/recruitment.php';

$assignments = nc_get_user_teaching_assignments($conn, (int)$_SESSION['user_id']);
$current = $assignments['current'];
$previous = $assignments['previous'];

$summary = [
    'lecture_units' => 0.0,
    'laboratory_units' => 0.0,
    'teaching_hours' => 0.0,
    'weekly_compensation' => 0.0,
    'has_weekly_compensation' => false,
];
foreach ($current as $assignment) {
    $summary['lecture_units'] += (float)($assignment['lecture_units'] ?? 0);
    $summary['laboratory_units'] += (float)($assignment['laboratory_units'] ?? 0);
    $summary['teaching_hours'] += (float)($assignment['teaching_hours_per_week'] ?? 0);
    if ($assignment['projected_weekly_compensation'] !== null) {
        $summary['weekly_compensation'] += (float)$assignment['projected_weekly_compensation'];
        $summary['has_weekly_compensation'] = true;
    }
}

function assignment_money(?string $value, string $suffix = ''): string
{
    if ($value === null || $value === '') return 'Not applicable';
    return '₱' . number_format((float)$value, 2) . $suffix;
}

function render_assignment_record(array $assignment): void
{
    $lecture = $assignment['lecture_units'] !== null ? nc_format_number($assignment['lecture_units']) . ' units' : 'Not recorded';
    $laboratory = $assignment['laboratory_units'] !== null ? nc_format_number($assignment['laboratory_units']) . ' units' : 'Not recorded';
    $hours = $assignment['teaching_hours_per_week'] !== null ? nc_format_number($assignment['teaching_hours_per_week']) . ' hours/week' : 'Not recorded';
    ?>
    <article class="ui-teaching-assignment-card">
        <div class="ui-teaching-assignment-card__header">
            <div class="ui-teaching-assignment-card__labels">
                <span><?php echo htmlspecialchars($assignment['subject_code'] ?: 'Teaching load'); ?></span>
                <span class="ui-status ui-status--success">Current</span>
            </div>
            <h3><?php echo htmlspecialchars($assignment['subject_name']); ?></h3>
            <p><?php echo htmlspecialchars($assignment['program'] ?: 'Program not specified'); ?></p>
        </div>
        <dl class="ui-teaching-assignment-card__facts">
            <div><dt>Sections</dt><dd><?php echo (int)$assignment['assigned_sections']; ?></dd></div>
            <div><dt>Lecture</dt><dd><?php echo htmlspecialchars($lecture); ?></dd></div>
            <div><dt>Laboratory</dt><dd><?php echo htmlspecialchars($laboratory); ?></dd></div>
            <div><dt>Weekly Hours</dt><dd><?php echo htmlspecialchars($hours); ?></dd></div>
            <?php if (nc_normalize_employment_type($assignment['employment_type'] ?? '') === 'part_time'): ?>
                <div><dt>Hourly Rate</dt><dd><?php echo htmlspecialchars(assignment_money($assignment['projected_hourly_rate'], '/hour')); ?></dd></div>
                <div><dt>Weekly Estimate</dt><dd><?php echo htmlspecialchars(assignment_money($assignment['projected_weekly_compensation'], '/week')); ?></dd></div>
            <?php else: ?>
                <div><dt>Salary Grade</dt><dd><?php echo htmlspecialchars($assignment['salary_grade'] ?: 'Not recorded'); ?></dd></div>
            <?php endif; ?>
        </dl>
        <footer><?php echo htmlspecialchars($assignment['academic_year'] . ' · ' . $assignment['semester']); ?></footer>
    </article>
    <?php
}
?>

<div class="ui-page-stack ui-teaching-load-page">
    <header class="ui-page-header">
        <div class="ui-page-header__main">
            <p class="ui-eyebrow">Instructor workspace</p>
            <h1 class="ui-page-title">My Teaching Load</h1>
            <p class="ui-page-description">Review active assignments, workload totals, and previous academic periods.</p>
        </div>
    </header>

    <?php if (!$current && !$previous): ?>
        <section class="ui-content-section ui-compact-empty">
            <i class="ri-book-open-line"></i>
            <div><strong>No teaching load has been assigned yet</strong><p>Your assigned teaching loads will appear here after the recruitment process is completed.</p></div>
        </section>
    <?php else: ?>
        <?php if ($current): ?>
        <section class="ui-teaching-summary" aria-label="Current semester summary">
            <div class="ui-teaching-summary__period"><p class="ui-eyebrow">Current semester</p><h2><?php echo htmlspecialchars(nc_current_academic_year()); ?></h2><span><?php echo htmlspecialchars(nc_current_semester()); ?></span></div>
            <dl class="ui-teaching-summary__metrics">
                <div><dt>Assigned Subjects</dt><dd><?php echo count($current); ?></dd></div>
                <div><dt>Lecture Units</dt><dd><?php echo htmlspecialchars(nc_format_number($summary['lecture_units'])); ?></dd></div>
                <div><dt>Laboratory Units</dt><dd><?php echo htmlspecialchars(nc_format_number($summary['laboratory_units'])); ?></dd></div>
                <div><dt>Weekly Hours</dt><dd><?php echo htmlspecialchars(nc_format_number($summary['teaching_hours'])); ?></dd></div>
                <?php if ($summary['has_weekly_compensation']): ?><div><dt>Weekly Estimate</dt><dd><?php echo htmlspecialchars('₱' . number_format($summary['weekly_compensation'], 2)); ?></dd></div><?php endif; ?>
            </dl>
        </section>
        <?php endif; ?>

        <section class="ui-teaching-current">
            <div class="ui-section-header"><p class="ui-eyebrow">Active assignments</p><h2>Current Teaching Assignments</h2><p>Subjects assigned for the active academic period.</p></div>
            <?php if ($current): ?>
                <div class="ui-teaching-assignment-grid"><?php foreach ($current as $assignment) render_assignment_record($assignment); ?></div>
            <?php else: ?>
                <div class="ui-compact-empty"><i class="ri-calendar-close-line"></i><div><strong>No current assignment</strong><p>No active teaching load is recorded for the current academic period.</p></div></div>
            <?php endif; ?>
        </section>

        <details class="ui-teaching-history"<?php echo !$current ? ' open' : ''; ?>>
            <summary><span><span class="ui-eyebrow">Assignment history</span><strong>Previous Teaching Loads</strong><small><?php echo count($previous); ?> <?php echo count($previous) === 1 ? 'record' : 'records'; ?></small></span><i class="ri-arrow-down-s-line"></i></summary>
            <?php if ($previous): ?>
                <div class="ui-teaching-history__table" role="table" aria-label="Previous teaching loads">
                    <div class="ui-teaching-history__head" role="row"><span>Subject</span><span>Academic Period</span><span>Sections</span><span>Weekly Hours</span></div>
                    <?php foreach ($previous as $assignment): ?>
                    <div class="ui-teaching-history__row" role="row">
                        <div><strong><?php echo htmlspecialchars(trim(($assignment['subject_code'] ? $assignment['subject_code'] . ' - ' : '') . $assignment['subject_name'])); ?></strong><span><?php echo htmlspecialchars($assignment['program'] ?: 'Program not specified'); ?></span></div>
                        <span><?php echo htmlspecialchars($assignment['academic_year'] . ' · ' . $assignment['semester']); ?></span>
                        <span><?php echo (int)$assignment['assigned_sections']; ?></span>
                        <span><?php echo $assignment['teaching_hours_per_week'] !== null ? htmlspecialchars(nc_format_number($assignment['teaching_hours_per_week']) . ' hours') : 'Not recorded'; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="ui-teaching-history__empty">No previous teaching loads are recorded.</p>
            <?php endif; ?>
        </details>
    <?php endif; ?>
</div>
