// File: src/pages/admin/Dashboard.jsx
import { useEffect, useState } from "react";
import {
  Car,
  ClipboardList,
  Clock,
  CheckCircle,
  AlertCircle,
} from "lucide-react";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
} from "recharts";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import dashboardService from "@/services/dashboardService";
import { getStatusLabel, formatMonthLabel, formatCurrentMonth } from "@/utils/utils";
import { StatusBadge } from "@/components/common/StatusBadge";

const CHART_COLORS = {
  waiting_level_1: "#f59e0b",
  waiting_level_2: "#3b82f6",
  approved: "#22c55e",
  rejected: "#ef4444",
};

// ── Sub-components ────────────────────────────────────────────────────────────

function SummaryCard({ icon: Icon, label, value, subtitle, gradientFrom, accentBorder, iconBg, iconColor }) {
  return (
    <div
      className={`rounded-xl border ${accentBorder} bg-gradient-to-br ${gradientFrom} to-white p-5 shadow-sm`}
    >
      <div className="flex items-start justify-between">
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium text-muted-foreground">{label}</p>
          <p className="mt-1 text-3xl font-bold tracking-tight">{value ?? "-"}</p>
          {subtitle && (
            <p className="mt-1 text-xs text-muted-foreground truncate">{subtitle}</p>
          )}
        </div>
        <div className={`ml-3 shrink-0 rounded-full p-3 ${iconBg}`}>
          <Icon className={`h-5 w-5 ${iconColor}`} />
        </div>
      </div>
    </div>
  );
}

function CardSkeleton() {
  return (
    <div className="rounded-xl border bg-white p-5 shadow-sm">
      <div className="animate-pulse space-y-3">
        <div className="h-3 w-1/2 rounded bg-muted" />
        <div className="h-8 w-1/3 rounded bg-muted" />
        <div className="h-2 w-2/3 rounded bg-muted" />
      </div>
    </div>
  );
}

function ChartSkeleton({ height = 260 }) {
  return (
    <div className="animate-pulse rounded-md bg-muted" style={{ height }} />
  );
}

function VehicleRankList({ data }) {
  const maxValue = data[0]?.total ?? 1;
  return (
    <div className="space-y-4">
      {data.map((item, index) => (
        <div key={item.name}>
          <div className="flex items-center justify-between mb-1.5">
            <div className="flex items-center gap-2 min-w-0">
              <span className="w-5 shrink-0 text-xs font-mono text-muted-foreground">
                {index + 1}
              </span>
              <span className="text-sm font-medium truncate">{item.name}</span>
            </div>
            <span className="ml-2 shrink-0 text-sm font-bold tabular-nums">
              {item.total}
            </span>
          </div>
          <div className="ml-7 h-2 overflow-hidden rounded-full bg-muted">
            <div
              className="h-full rounded-full bg-emerald-500 transition-all duration-500"
              style={{ width: `${(item.total / maxValue) * 100}%` }}
            />
          </div>
        </div>
      ))}
    </div>
  );
}

// ── Main Component ────────────────────────────────────────────────────────────

export default function AdminDashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    dashboardService
      .getDashboard()
      .then((res) => setData(res.data))
      .catch(() => setError("Gagal memuat data dashboard. Coba lagi."))
      .finally(() => setLoading(false));
  }, []);

  if (error) {
    return (
      <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-4 text-red-600">
        <AlertCircle className="h-4 w-4 shrink-0" />
        <span className="text-sm">{error}</span>
      </div>
    );
  }

  const summary = data?.summary ?? {};
  const monthLabel = formatCurrentMonth();

  // Bar chart — bookings per month
  const bookingsPerMonth = (data?.bookings_per_month ?? []).map((row) => ({
    name: formatMonthLabel(row.year, row.month),
    total: Number(row.total),
  }));

  // Donut chart — status distribution
  const statusDistribution = (data?.status_distribution ?? []).map((row) => ({
    name: getStatusLabel(row.status),
    value: Number(row.total),
    color: CHART_COLORS[row.status] ?? "#94a3b8",
    originalStatus: row.status,
  }));

  const totalBookings = statusDistribution.reduce((a, b) => a + b.value, 0);

  // Top 5 vehicles — custom list
  const topVehicles = (data?.top_vehicles ?? []).map((row) => ({
    name: `${row.vehicle_name} (${row.plate_number})`,
    total: Number(row.total),
  }));

  return (
    <div className="space-y-6">
      {/* ── Summary Cards ── */}
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {loading ? (
          Array.from({ length: 4 }).map((_, i) => <CardSkeleton key={i} />)
        ) : (
          <>
            <SummaryCard
              icon={Car}
              label="Kendaraan Aktif"
              value={summary.total_vehicles}
              subtitle="unit tersedia saat ini"
              gradientFrom="from-blue-50"
              accentBorder="border-l-4 border-l-blue-500 border-blue-100"
              iconBg="bg-blue-100"
              iconColor="text-blue-600"
            />
            <SummaryCard
              icon={ClipboardList}
              label="Booking Bulan Ini"
              value={summary.total_bookings_this_month}
              subtitle={monthLabel}
              gradientFrom="from-purple-50"
              accentBorder="border-l-4 border-l-purple-500 border-purple-100"
              iconBg="bg-purple-100"
              iconColor="text-purple-600"
            />
            <SummaryCard
              icon={Clock}
              label="Pending Approval"
              value={summary.pending_approval}
              subtitle="menunggu tindakan"
              gradientFrom="from-yellow-50"
              accentBorder="border-l-4 border-l-yellow-500 border-yellow-100"
              iconBg="bg-yellow-100"
              iconColor="text-yellow-600"
            />
            <SummaryCard
              icon={CheckCircle}
              label="Disetujui Bulan Ini"
              value={summary.approved_this_month}
              subtitle={monthLabel}
              gradientFrom="from-green-50"
              accentBorder="border-l-4 border-l-green-500 border-green-100"
              iconBg="bg-green-100"
              iconColor="text-green-600"
            />
          </>
        )}
      </div>

      {/* ── Bar Chart: Bookings per Month ── */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">
            Booking per Bulan (12 Bulan Terakhir)
          </CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <ChartSkeleton height={260} />
          ) : (
            <ResponsiveContainer width="100%" height={260}>
              <BarChart
                data={bookingsPerMonth}
                margin={{ top: 4, right: 12, left: -16, bottom: 0 }}
              >
                <defs>
                  <linearGradient id="barGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#6366f1" />
                    <stop offset="100%" stopColor="#a5b4fc" />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                <XAxis
                  dataKey="name"
                  tick={{ fontSize: 12 }}
                  tickLine={false}
                  axisLine={false}
                />
                <YAxis
                  allowDecimals={false}
                  tick={{ fontSize: 12 }}
                  tickLine={false}
                  axisLine={false}
                />
                <Tooltip
                  contentStyle={{
                    fontSize: 13,
                    borderRadius: 8,
                    border: "none",
                    boxShadow: "0 4px 12px rgba(0,0,0,0.1)",
                  }}
                  formatter={(v) => [v, "Booking"]}
                />
                <Bar dataKey="total" fill="url(#barGradient)" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>

      {/* ── Bottom Row: Donut + Top Vehicles ── */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Donut — Status Distribution */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">
              Distribusi Status Booking
            </CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <ChartSkeleton height={260} />
            ) : statusDistribution.length === 0 ? (
              <p className="py-16 text-center text-sm text-muted-foreground">
                Belum ada data
              </p>
            ) : (
              <>
                <div className="relative">
                  <ResponsiveContainer width="100%" height={260}>
                    <PieChart>
                      <Pie
                        data={statusDistribution}
                        cx="50%"
                        cy="45%"
                        innerRadius={60}
                        outerRadius={90}
                        paddingAngle={3}
                        dataKey="value"
                      >
                        {statusDistribution.map((entry, index) => (
                          <Cell key={index} fill={entry.color} />
                        ))}
                      </Pie>
                      <Tooltip formatter={(v, name) => [v, name]} />
                    </PieChart>
                  </ResponsiveContainer>
                  {/* Center label overlay — cy=45% so we mirror that offset */}
                  <div
                    className="pointer-events-none absolute left-1/2 flex -translate-x-1/2 -translate-y-1/2 flex-col items-center"
                    style={{ top: "45%" }}
                  >
                    <span className="text-2xl font-bold leading-none">{totalBookings}</span>
                    <span className="mt-1 text-xs text-muted-foreground">total booking</span>
                  </div>
                </div>
                {/* Custom legend using StatusBadge */}
                <div className="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
                  {statusDistribution.map((entry) => (
                    <div key={entry.name} className="flex items-center gap-1.5">
                      <StatusBadge status={entry.originalStatus} />
                      <span className="text-xs text-muted-foreground">({entry.value})</span>
                    </div>
                  ))}
                </div>
              </>
            )}
          </CardContent>
        </Card>

        {/* Custom list — Top 5 Vehicles */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">
              Top 5 Kendaraan Terbanyak Dipakai
            </CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <ChartSkeleton height={260} />
            ) : topVehicles.length === 0 ? (
              <p className="py-16 text-center text-sm text-muted-foreground">
                Belum ada data
              </p>
            ) : (
              <VehicleRankList data={topVehicles} />
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
