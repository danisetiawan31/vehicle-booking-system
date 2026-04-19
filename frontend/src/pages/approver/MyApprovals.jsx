// File: src/pages/approver/MyApprovals.jsx
import { useState, useEffect, useCallback } from "react";
import { CheckCircle, XCircle, AlertCircle } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import DataTable from "@/components/common/DataTable";
import { StatusBadge } from "@/components/common/StatusBadge";
import ConfirmDialog from "@/components/common/ConfirmDialog";
import FormDialog from "@/components/common/FormDialog";
import Button from "@/components/common/Button";
import bookingService from "@/services/bookingService";
import { formatDateShort } from "@/utils/utils";
import { useAuth } from "@/context/useAuth";

export default function MyApprovals() {
  const { user } = useAuth();
  const [bookings, setBookings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [fetchError, setFetchError] = useState(null);

  // Approve state
  const [approveTarget, setApproveTarget] = useState(null);
  const [approveLoading, setApproveLoading] = useState(false);

  // Reject state
  const [rejectTarget, setRejectTarget] = useState(null);
  const [rejectLoading, setRejectLoading] = useState(false);
  const [rejectNotes, setRejectNotes] = useState("");
  const [rejectNotesError, setRejectNotesError] = useState("");

  const fetchBookings = useCallback(async () => {
    setLoading(true);
    setFetchError(null);
    try {
      const data = await bookingService.getBookings();
      setBookings(data.data || []);
    } catch (_) {
      setFetchError("Gagal memuat daftar persetujuan.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchBookings();
  }, [fetchBookings]);

  // ── Approve ──────────────────────────────────────────────────────────────
  const handleApproveClick = (booking) => {
    setApproveTarget(booking);
  };

  const handleConfirmApprove = async () => {
    if (!approveTarget) return;
    setApproveLoading(true);
    try {
      await bookingService.approveBooking(approveTarget.id);
      setApproveTarget(null);
      fetchBookings();
    } finally {
      setApproveLoading(false);
    }
  };

  // ── Reject ───────────────────────────────────────────────────────────────
  const handleRejectClick = (booking) => {
    setRejectTarget(booking);
    setRejectNotes("");
    setRejectNotesError("");
  };

  const handleRejectDialogChange = (open) => {
    if (!open) {
      setRejectTarget(null);
      setRejectNotes("");
      setRejectNotesError("");
    }
  };

  const handleRejectSubmit = async (e) => {
    e.preventDefault();
    if (!rejectNotes.trim()) {
      setRejectNotesError("Alasan penolakan wajib diisi.");
      return;
    }
    setRejectNotesError("");
    setRejectLoading(true);
    try {
      await bookingService.rejectBooking(rejectTarget.id, {
        notes: rejectNotes.trim(),
      });
      setRejectTarget(null);
      fetchBookings();
    } finally {
      setRejectLoading(false);
    }
  };

  // ── Columns ───────────────────────────────────────────────────────────────
  const columns = [
    {
      key: "no",
      header: "No",
      render: (_row, index) => index + 1,
    },
    { key: "booking_code", header: "Kode Booking" },
    { key: "requester_name", header: "Pemohon" },
    {
      key: "vehicle",
      header: "Kendaraan",
      render: (row) => row.vehicle_name ?? "-",
    },
    { key: "plate_number", header: "Plat Nomor" },
    {
      key: "driver",
      header: "Driver",
      render: (row) => row.driver_name ?? "-",
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
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: "action",
      header: "Aksi",
      render: (row) => {
        const isActionable =
          (Number(user?.approval_level) === 1 &&
            row.status === "waiting_level_1") ||
          (Number(user?.approval_level) === 2 &&
            row.status === "waiting_level_2");

        if (!isActionable)
          return <span className="text-sm text-muted-foreground">-</span>;

        return (
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              className="text-green-600 hover:text-green-800"
              onClick={() => handleApproveClick(row)}
            >
              <CheckCircle className="mr-1 h-4 w-4" />
              Setujui
            </Button>
            <Button
              variant="ghost"
              size="sm"
              className="text-red-500 hover:text-red-700"
              onClick={() => handleRejectClick(row)}
            >
              <XCircle className="mr-1 h-4 w-4" />
              Tolak
            </Button>
          </div>
        );
      },
    },
  ];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold tracking-tight">Persetujuan Saya</h1>

      {fetchError && (
        <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-4 text-red-600">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span className="text-sm">{fetchError}</span>
        </div>
      )}

      <Card>
        <CardContent className="p-0">
          <DataTable
            columns={columns}
            data={bookings}
            loading={loading}
            emptyText="Tidak ada booking yang perlu disetujui."
          />
        </CardContent>
      </Card>

      {/* Approve confirm dialog */}
      <ConfirmDialog
        open={!!approveTarget}
        onOpenChange={(open) => !open && setApproveTarget(null)}
        title="Setujui Booking"
        description={`Booking ${approveTarget?.booking_code} akan disetujui. Lanjutkan?`}
        confirmText="Setujui"
        cancelText="Batal"
        onConfirm={handleConfirmApprove}
        loading={approveLoading}
      />

      {/* Reject form dialog */}
      <FormDialog
        open={!!rejectTarget}
        onOpenChange={handleRejectDialogChange}
        title="Tolak Booking"
      >
        <form onSubmit={handleRejectSubmit} className="space-y-4">
          <p className="text-sm text-muted-foreground">
            Booking{" "}
            <span className="font-medium text-foreground">
              {rejectTarget?.booking_code}
            </span>{" "}
            akan ditolak. Berikan alasan penolakan.
          </p>

          <div className="space-y-2">
            <label className="text-sm font-medium">Alasan Penolakan</label>
            <textarea
              className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              rows={4}
              placeholder="Tulis alasan penolakan di sini..."
              value={rejectNotes}
              onChange={(e) => {
                setRejectNotes(e.target.value);
                if (e.target.value.trim()) setRejectNotesError("");
              }}
            />
            {rejectNotesError && (
              <p className="text-sm text-red-500">{rejectNotesError}</p>
            )}
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => handleRejectDialogChange(false)}
            >
              Batal
            </Button>
            <Button type="submit" variant="destructive" loading={rejectLoading}>
              Tolak Booking
            </Button>
          </div>
        </form>
      </FormDialog>
    </div>
  );
}
