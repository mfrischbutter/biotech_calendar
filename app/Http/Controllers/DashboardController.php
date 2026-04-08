<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $now->copy()->endOfWeek(Carbon::SUNDAY);
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $totalClients = Client::count();

        $todayAppointments = Appointment::whereBetween('start_at', [$todayStart, $todayEnd])->count();
        $thisWeekAppointments = Appointment::whereBetween('start_at', [$weekStart, $weekEnd])->count();
        $thisMonthAppointments = Appointment::whereBetween('start_at', [$monthStart, $monthEnd])->count();

        $upcomingAppointments = Appointment::with([
            'client:id,first_name,last_name,company_name',
            'employee:id,first_name,last_name',
            'status:id,name,color',
        ])
            ->whereBetween('start_at', [$now, $todayEnd])
            ->orderBy('start_at')
            ->limit(10)
            ->get();

        $recentActivity = ActivityLog::with([
            'user:id,first_name,last_name',
            'appointment:id,title',
            'comment:id,body',
        ])
            ->recent(7)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'totalClients' => $totalClients,
            'todayAppointments' => $todayAppointments,
            'thisWeekAppointments' => $thisWeekAppointments,
            'thisMonthAppointments' => $thisMonthAppointments,
            'upcomingAppointments' => $upcomingAppointments,
            'recentActivity' => $recentActivity,
        ]);
    }
}
