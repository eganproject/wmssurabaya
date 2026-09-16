<?php

namespace App\Support;

use App\Models\QcScanResi;
use App\Models\QcScanResiItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QcPerformanceReport
{
    public static function dailyGroupCount($authUser): int
    {
        $query = DB::table('qc_scan_resis')
            ->join('users as scan_users', 'scan_users.id', '=', 'qc_scan_resis.scanned_by')
            ->selectRaw('DATE(qc_scan_resis.scanned_at) as report_date, qc_scan_resis.scanned_by as user_id')
            ->whereNotNull('qc_scan_resis.scanned_at')
            ->whereNotNull('qc_scan_resis.scanned_by');

        self::applyFilters($query, [], $authUser, 'qc_scan_resis', 'scan_users');

        return DB::query()->fromSub(
            $query->groupByRaw('DATE(qc_scan_resis.scanned_at)')->groupBy('qc_scan_resis.scanned_by'),
            'daily_qc',
        )->count();
    }

    public static function dailyRows(array $filters, $authUser): Collection
    {
        $hourBucket = self::hourBucketExpression('qc_scan_resis.scanned_at');
        $duration = self::durationMinutesExpression('qc_scan_resis.scanned_at', 'qc_scan_resis.completed_at');

        $resiAgg = QcScanResi::query()
            ->join('users as scan_users', 'scan_users.id', '=', 'qc_scan_resis.scanned_by')
            ->selectRaw('DATE(qc_scan_resis.scanned_at) as report_date')
            ->selectRaw('qc_scan_resis.scanned_by as user_id')
            ->selectRaw('COUNT(*) as total_resi')
            ->selectRaw("SUM(CASE WHEN qc_scan_resis.status = 'completed' THEN 1 ELSE 0 END) as completed_resi")
            ->selectRaw("SUM(CASE WHEN qc_scan_resis.status <> 'completed' THEN 1 ELSE 0 END) as pending_resi")
            ->selectRaw('MIN(qc_scan_resis.scanned_at) as first_scan_at')
            ->selectRaw('MAX(qc_scan_resis.scanned_at) as last_scan_at')
            ->selectRaw("COUNT(DISTINCT {$hourBucket}) as active_hours")
            ->selectRaw("SUM(CASE WHEN qc_scan_resis.status = 'completed' AND qc_scan_resis.completed_at IS NOT NULL AND qc_scan_resis.completed_at >= qc_scan_resis.scanned_at THEN 1 ELSE 0 END) as valid_cycle_resi")
            ->selectRaw("AVG(CASE WHEN qc_scan_resis.status = 'completed' AND qc_scan_resis.completed_at IS NOT NULL AND qc_scan_resis.completed_at >= qc_scan_resis.scanned_at THEN {$duration} ELSE NULL END) as avg_cycle_minutes")
            ->whereNotNull('qc_scan_resis.scanned_at')
            ->whereNotNull('qc_scan_resis.scanned_by');

        self::applyFilters($resiAgg, $filters, $authUser, 'qc_scan_resis', 'scan_users');
        $resiAgg->groupByRaw('DATE(qc_scan_resis.scanned_at)')->groupBy('qc_scan_resis.scanned_by');

        $itemAgg = QcScanResiItem::query()
            ->join('qc_scan_resis', 'qc_scan_resis.id', '=', 'qc_scan_resi_items.qc_scan_resi_id')
            ->join('users as scan_users', 'scan_users.id', '=', 'qc_scan_resis.scanned_by')
            ->selectRaw('DATE(qc_scan_resis.scanned_at) as report_date')
            ->selectRaw('qc_scan_resis.scanned_by as user_id')
            ->selectRaw('COUNT(*) as sku_lines')
            ->selectRaw('COALESCE(SUM(qc_scan_resi_items.required_qty), 0) as required_qty')
            ->selectRaw('COALESCE(SUM(qc_scan_resi_items.scanned_qty), 0) as scanned_qty')
            ->whereNotNull('qc_scan_resis.scanned_at')
            ->whereNotNull('qc_scan_resis.scanned_by');

        self::applyFilters($itemAgg, $filters, $authUser, 'qc_scan_resis', 'scan_users');
        $itemAgg->groupByRaw('DATE(qc_scan_resis.scanned_at)')->groupBy('qc_scan_resis.scanned_by');

        return DB::query()
            ->fromSub($resiAgg, 'r')
            ->join('users', 'users.id', '=', 'r.user_id')
            ->leftJoin('divisis', 'divisis.id', '=', 'users.divisi_id')
            ->leftJoinSub($itemAgg, 'i', function ($join) {
                $join->on('i.report_date', '=', 'r.report_date')->on('i.user_id', '=', 'r.user_id');
            })
            ->selectRaw("r.report_date, r.user_id, users.name as petugas, COALESCE(divisis.name, '-') as divisi")
            ->selectRaw('r.total_resi, r.completed_resi, r.pending_resi, r.first_scan_at, r.last_scan_at, r.active_hours, r.valid_cycle_resi, r.avg_cycle_minutes')
            ->selectRaw('COALESCE(i.sku_lines, 0) as sku_lines, COALESCE(i.required_qty, 0) as required_qty, COALESCE(i.scanned_qty, 0) as scanned_qty')
            ->orderByDesc('r.report_date')
            ->orderBy('users.name')
            ->get();
    }

    public static function hourlyRows(array $filters, $authUser): Collection
    {
        $hourLabel = self::hourLabelExpression('qc_scan_resis.scanned_at');
        $duration = self::durationMinutesExpression('qc_scan_resis.scanned_at', 'qc_scan_resis.completed_at');

        $resiAgg = QcScanResi::query()
            ->join('users as scan_users', 'scan_users.id', '=', 'qc_scan_resis.scanned_by')
            ->selectRaw('DATE(qc_scan_resis.scanned_at) as report_date')
            ->selectRaw('qc_scan_resis.scanned_by as user_id')
            ->selectRaw("{$hourLabel} as hour_label")
            ->selectRaw('COUNT(*) as total_resi')
            ->selectRaw("SUM(CASE WHEN qc_scan_resis.status = 'completed' THEN 1 ELSE 0 END) as completed_resi")
            ->selectRaw("SUM(CASE WHEN qc_scan_resis.status <> 'completed' THEN 1 ELSE 0 END) as pending_resi")
            ->selectRaw("AVG(CASE WHEN qc_scan_resis.status = 'completed' AND qc_scan_resis.completed_at IS NOT NULL AND qc_scan_resis.completed_at >= qc_scan_resis.scanned_at THEN {$duration} ELSE NULL END) as avg_cycle_minutes")
            ->whereNotNull('qc_scan_resis.scanned_at')
            ->whereNotNull('qc_scan_resis.scanned_by');

        self::applyFilters($resiAgg, $filters, $authUser, 'qc_scan_resis', 'scan_users');
        $resiAgg->groupByRaw('DATE(qc_scan_resis.scanned_at)')->groupBy('qc_scan_resis.scanned_by')->groupByRaw($hourLabel);

        $itemAgg = QcScanResiItem::query()
            ->join('qc_scan_resis', 'qc_scan_resis.id', '=', 'qc_scan_resi_items.qc_scan_resi_id')
            ->join('users as scan_users', 'scan_users.id', '=', 'qc_scan_resis.scanned_by')
            ->selectRaw('DATE(qc_scan_resis.scanned_at) as report_date')
            ->selectRaw('qc_scan_resis.scanned_by as user_id')
            ->selectRaw("{$hourLabel} as hour_label")
            ->selectRaw('COUNT(*) as sku_lines')
            ->selectRaw('COALESCE(SUM(qc_scan_resi_items.required_qty), 0) as required_qty')
            ->selectRaw('COALESCE(SUM(qc_scan_resi_items.scanned_qty), 0) as scanned_qty')
            ->whereNotNull('qc_scan_resis.scanned_at')
            ->whereNotNull('qc_scan_resis.scanned_by');

        self::applyFilters($itemAgg, $filters, $authUser, 'qc_scan_resis', 'scan_users');
        $itemAgg->groupByRaw('DATE(qc_scan_resis.scanned_at)')->groupBy('qc_scan_resis.scanned_by')->groupByRaw($hourLabel);

        return DB::query()
            ->fromSub($resiAgg, 'r')
            ->join('users', 'users.id', '=', 'r.user_id')
            ->leftJoin('divisis', 'divisis.id', '=', 'users.divisi_id')
            ->leftJoinSub($itemAgg, 'i', function ($join) {
                $join->on('i.report_date', '=', 'r.report_date')
                    ->on('i.user_id', '=', 'r.user_id')
                    ->on('i.hour_label', '=', 'r.hour_label');
            })
            ->selectRaw("r.report_date, r.user_id, users.name as petugas, COALESCE(divisis.name, '-') as divisi, r.hour_label")
            ->selectRaw('r.total_resi, r.completed_resi, r.pending_resi, r.avg_cycle_minutes')
            ->selectRaw('COALESCE(i.sku_lines, 0) as sku_lines, COALESCE(i.required_qty, 0) as required_qty, COALESCE(i.scanned_qty, 0) as scanned_qty')
            ->orderByDesc('r.report_date')
            ->orderBy('users.name')
            ->orderBy('r.hour_label')
            ->get();
    }

    public static function detailRows(array $filters, $authUser): Collection
    {
        $duration = self::durationMinutesExpression('qc_scan_resis.scanned_at', 'qc_scan_resis.completed_at');
        $itemAgg = QcScanResiItem::query()
            ->selectRaw('qc_scan_resi_id, COUNT(*) as sku_lines')
            ->selectRaw('COALESCE(SUM(required_qty), 0) as required_qty')
            ->selectRaw('COALESCE(SUM(scanned_qty), 0) as scanned_qty')
            ->groupBy('qc_scan_resi_id');

        $query = QcScanResi::query()
            ->join('users as scan_users', 'scan_users.id', '=', 'qc_scan_resis.scanned_by')
            ->leftJoin('divisis', 'divisis.id', '=', 'scan_users.divisi_id')
            ->leftJoin('resis', 'resis.id', '=', 'qc_scan_resis.resi_id')
            ->leftJoinSub($itemAgg, 'i', 'i.qc_scan_resi_id', '=', 'qc_scan_resis.id')
            ->selectRaw('qc_scan_resis.id, qc_scan_resis.scanned_at, qc_scan_resis.completed_at, qc_scan_resis.status')
            ->selectRaw("qc_scan_resis.scanned_by as user_id, scan_users.name as petugas, COALESCE(divisis.name, '-') as divisi")
            ->selectRaw("COALESCE(resis.no_resi, '-') as no_resi, COALESCE(resis.id_pesanan, '-') as id_pesanan")
            ->selectRaw('COALESCE(i.sku_lines, 0) as sku_lines, COALESCE(i.required_qty, 0) as required_qty, COALESCE(i.scanned_qty, 0) as scanned_qty')
            ->selectRaw("CASE WHEN qc_scan_resis.status = 'completed' AND qc_scan_resis.completed_at IS NOT NULL AND qc_scan_resis.completed_at >= qc_scan_resis.scanned_at THEN {$duration} ELSE NULL END as cycle_minutes")
            ->whereNotNull('qc_scan_resis.scanned_at')
            ->whereNotNull('qc_scan_resis.scanned_by');

        self::applyFilters($query, $filters, $authUser, 'qc_scan_resis', 'scan_users');

        return $query->orderByDesc('qc_scan_resis.scanned_at')->get();
    }

    public static function enrichDailyRows(Collection $dailyRows, Collection $hourlyRows): Collection
    {
        $hourlyByDayUser = $hourlyRows->groupBy(fn ($row) => $row->report_date.'|'.$row->user_id);

        return $dailyRows->map(function ($row) use ($hourlyByDayUser) {
            $hours = $hourlyByDayUser->get($row->report_date.'|'.$row->user_id, collect());
            $peak = $hours->sortBy([['total_resi', 'desc'], ['hour_label', 'asc']])->first();
            $activeHours = max(1, (int) $row->active_hours);
            $totalResi = (int) $row->total_resi;
            $completed = (int) $row->completed_resi;
            $required = (int) $row->required_qty;
            $scanned = (int) $row->scanned_qty;

            $row->completion_pct = $totalResi > 0 ? round($completed / $totalResi * 100, 1) : 0;
            $row->scan_pct = $required > 0 ? round($scanned / $required * 100, 1) : 0;
            $row->resi_per_hour = round($totalResi / $activeHours, 2);
            $row->qty_per_hour = round($scanned / $activeHours, 2);
            $row->peak_hour = $peak?->hour_label;
            $row->peak_hour_resi = (int) ($peak?->total_resi ?? 0);
            $row->avg_cycle_minutes = $row->avg_cycle_minutes !== null ? round((float) $row->avg_cycle_minutes, 1) : null;

            return $row;
        });
    }

    public static function summary(Collection $dailyRows, Collection $hourlyRows): array
    {
        $totalResi = (int) $dailyRows->sum('total_resi');
        $completed = (int) $dailyRows->sum('completed_resi');
        $required = (int) $dailyRows->sum('required_qty');
        $scanned = (int) $dailyRows->sum('scanned_qty');
        $activeHours = (int) $dailyRows->sum('active_hours');
        $cycleWeight = (int) $dailyRows->sum('valid_cycle_resi');
        $cycleTotal = $dailyRows->sum(fn ($row) => $row->avg_cycle_minutes !== null ? (float) $row->avg_cycle_minutes * (int) $row->valid_cycle_resi : 0);

        $byClockHour = $hourlyRows->groupBy('hour_label')->map(fn (Collection $rows, $hour) => [
            'hour' => $hour,
            'resi' => (int) $rows->sum('total_resi'),
            'qty' => (int) $rows->sum('scanned_qty'),
        ])->sortBy([['resi', 'desc'], ['hour', 'asc']])->values();
        $peak = $byClockHour->first();

        return [
            'petugas_count' => $dailyRows->pluck('user_id')->unique()->count(),
            'day_count' => $dailyRows->pluck('report_date')->unique()->count(),
            'resi_total' => $totalResi,
            'completed_total' => $completed,
            'pending_total' => max(0, $totalResi - $completed),
            'qty_total' => $scanned,
            'required_qty_total' => $required,
            'sku_lines_total' => (int) $dailyRows->sum('sku_lines'),
            'active_hours' => $activeHours,
            'completion_pct' => $totalResi > 0 ? round($completed / $totalResi * 100, 1) : 0,
            'scan_pct' => $required > 0 ? round($scanned / $required * 100, 1) : 0,
            'resi_per_hour' => $activeHours > 0 ? round($totalResi / $activeHours, 2) : 0,
            'qty_per_hour' => $activeHours > 0 ? round($scanned / $activeHours, 2) : 0,
            'avg_cycle_minutes' => $cycleWeight > 0 ? round($cycleTotal / $cycleWeight, 1) : null,
            'peak_hour' => $peak['hour'] ?? null,
            'peak_hour_resi' => (int) ($peak['resi'] ?? 0),
        ];
    }

    public static function perUserRows(Collection $dailyRows): Collection
    {
        return $dailyRows->groupBy('user_id')->map(function (Collection $rows) {
            $first = $rows->first();
            $total = (int) $rows->sum('total_resi');
            $completed = (int) $rows->sum('completed_resi');
            $required = (int) $rows->sum('required_qty');
            $scanned = (int) $rows->sum('scanned_qty');
            $hours = max(1, (int) $rows->sum('active_hours'));
            $cycleWeight = (int) $rows->sum('valid_cycle_resi');
            $cycleTotal = $rows->sum(fn ($row) => $row->avg_cycle_minutes !== null ? (float) $row->avg_cycle_minutes * (int) $row->valid_cycle_resi : 0);

            return (object) [
                'user_id' => (int) $first->user_id,
                'petugas' => $first->petugas,
                'divisi' => $first->divisi,
                'active_days' => $rows->pluck('report_date')->unique()->count(),
                'active_hours' => $hours,
                'total_resi' => $total,
                'completed_resi' => $completed,
                'completion_pct' => $total > 0 ? round($completed / $total * 100, 1) : 0,
                'sku_lines' => (int) $rows->sum('sku_lines'),
                'required_qty' => $required,
                'scanned_qty' => $scanned,
                'scan_pct' => $required > 0 ? round($scanned / $required * 100, 1) : 0,
                'resi_per_hour' => round($total / $hours, 2),
                'qty_per_hour' => round($scanned / $hours, 2),
                'avg_cycle_minutes' => $cycleWeight > 0 ? round($cycleTotal / $cycleWeight, 1) : null,
            ];
        })->sortByDesc('resi_per_hour')->values();
    }

    private static function applyFilters($query, array $filters, $authUser, string $qcAlias, string $userAlias): void
    {
        if ($authUser?->divisi_id !== null && (int) $authUser->divisi_id !== 1) {
            $query->where("{$userAlias}.divisi_id", $authUser->divisi_id);
        }
        if (! empty($filters['divisi_id'])) {
            $query->where("{$userAlias}.divisi_id", (int) $filters['divisi_id']);
        }
        if (($search = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->where("{$userAlias}.name", 'like', "%{$search}%");
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate("{$qcAlias}.scanned_at", '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate("{$qcAlias}.scanned_at", '<=', $filters['date_to']);
        }
    }

    private static function hourBucketExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m-%d %H')";
    }

    private static function hourLabelExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%H:00', {$column})"
            : "DATE_FORMAT({$column}, '%H:00')";
    }

    private static function durationMinutesExpression(string $start, string $end): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "((julianday({$end}) - julianday({$start})) * 1440.0)"
            : "(TIMESTAMPDIFF(SECOND, {$start}, {$end}) / 60.0)";
    }
}
