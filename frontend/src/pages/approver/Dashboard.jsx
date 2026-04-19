// File: src/pages/approver/Dashboard.jsx
import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Clock, AlertCircle, Eye, UserCheck, CalendarCheck } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import DataTable from "@/components/common/DataTable";
import { StatusBadge } from "@/components/common/StatusBadge";
import { formatDateShort } from "@/utils/utils";
import dashboardService from "@/services/dashboardService";
import { useAuth } from "@/context/useAuth";

const COLUMNS = (navigate) => [
  {
    key: "booking_code",
    header: "Kode Booking",
  },
  {
    key: "requester_name",
    header: "Pemohon",
  },
  {
    key: "vehicle_name",
    header: "Kendaraan",
    render: (row) => (
      <span>
        {row.vehicle_name}
        <span className="ml-1 text-xs text-muted-foreground">
          ({row.plate_number})
        </span>
      </span>
    ),
  },
  {
    key: "start_date",
    header: "Tgl Mulai",
    render: (row) => formatDateShort(row.start_date),
  },
  {
    key: "end_date",
    header: "Tgl Selesai",
    render: (row) => formatDateShort(row.end_date),
  },
  {
    key: "booking_status",
    header: "Status",
    render: (row) => <StatusBadge status={row.booking_status} />,
  },
  {
    key: "action",
    header: "Aksi",
    render: (row) => (
      <Button
        variant="outline"
        size="sm"
        onClick={() => navigate(`/approver/bookings/${row.booking_id}`)}
      >
        <Eye className="mr-1.5 h-3.5 w-3.5" />
        Detail
      </Button>
    ),
  },
];

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

export default function ApproverDashboard() {
  const navigate = useNavigate();
  const { user } = useAuth();
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

  const pendingCount = data?.summary?.pending_for_me ?? 0;
  const pendingBookings = data?.pending_bookings ?? [];

  return (
    <div className="space-y-6">
      {/* ── Summary Cards ── */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {/* Card 1 — Pending Approval (from API) */}
        {loading ? (
          <CardSkeleton />
        ) : (
          <div className="rounded-xl border border-l-4 border-amber-100 border-l-amber-500 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm">
            <div className="flex items-start justify-between">
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-muted-foreground">Menunggu Persetujuan Saya</p>
                <p className="mt-1 text-3xl font-bold tracking-tight">{pendingCount}</p>
                <p className="mt-1 text-xs text-muted-foreground">booking menunggu tindakan Anda</p>
              </div>
              <div className="ml-3 shrink-0 rounded-full bg-amber-100 p-3">
                <Clock className="h-5 w-5 text-amber-600" />
              </div>
            </div>
          </div>
        )}

        {/* Card 2 — Level Approval (from auth, no skeleton needed) */}
        <div className="rounded-xl border border-l-4 border-blue-100 border-l-blue-500 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm">
          <div className="flex items-start justify-between">
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-muted-foreground">Level Approval Saya</p>
              <p className="mt-1 text-3xl font-bold tracking-tight">
                Level {user?.approval_level ?? "-"}
              </p>
              <p className="mt-1 text-xs text-muted-foreground">dari sistem berjenjang</p>
            </div>
            <div className="ml-3 shrink-0 rounded-full bg-blue-100 p-3">
              <UserCheck className="h-5 w-5 text-blue-600" />
            </div>
          </div>
        </div>

        {/* Card 3 — Status Akun (static, no skeleton needed) */}
        <div className="rounded-xl border border-l-4 border-green-100 border-l-green-500 bg-gradient-to-br from-green-50 to-white p-5 shadow-sm">
          <div className="flex items-start justify-between">
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-muted-foreground">Status Akun</p>
              <p className="mt-1 text-3xl font-bold tracking-tight">Aktif</p>
              <p className="mt-1 text-xs text-muted-foreground">approver terdaftar</p>
            </div>
            <div className="ml-3 shrink-0 rounded-full bg-green-100 p-3">
              <CalendarCheck className="h-5 w-5 text-green-600" />
            </div>
          </div>
        </div>
      </div>

      {/* ── Urgency Banner ── */}
      {!loading && pendingCount > 0 && (
        <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          <AlertCircle className="h-4 w-4 shrink-0 text-amber-500" />
          Ada {pendingCount} booking menunggu persetujuan Anda.
        </div>
      )}

      {/* ── Pending Bookings Table ── */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Booking Pending Untuk Saya</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <DataTable
            columns={COLUMNS(navigate)}
            data={pendingBookings}
            loading={loading}
            emptyText="Tidak ada booking pending untuk Anda."
          />
        </CardContent>
      </Card>
    </div>
  );
}
