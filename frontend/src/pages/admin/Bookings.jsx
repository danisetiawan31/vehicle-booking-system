// File: src/pages/admin/Bookings.jsx
import { useState, useEffect, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { Plus, Eye, AlertCircle } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import DataTable from "@/components/common/DataTable";
import { StatusBadge } from "@/components/common/StatusBadge";
import Button from "@/components/common/Button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import bookingService from "@/services/bookingService";
import { getStatusLabel, formatDateShort } from "@/utils/utils";

export default function Bookings() {
  const navigate = useNavigate();
  const [bookings, setBookings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [statusFilter, setStatusFilter] = useState("");

  const fetchBookings = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params =
        statusFilter && statusFilter !== "all" ? { status: statusFilter } : {};
      const data = await bookingService.getBookings(params);
      setBookings(data.data || []);
    } catch (_) {
      setError("Gagal memuat data booking.");
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    fetchBookings();
  }, [fetchBookings]);

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
      render: (row) => (
        <div>
          <div>{row.vehicle_name}</div>
          <div className="text-xs text-muted-foreground">
            {row.plate_number}
          </div>
        </div>
      ),
    },
    { key: "driver_name", header: "Driver" },
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
      render: (row) => (
        <Button
          variant="outline"
          size="sm"
          onClick={() => navigate(`/admin/bookings/${row.id}`)}
        >
          <Eye className="mr-2 h-4 w-4" /> Detail
        </Button>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold tracking-tight">Pemesanan</h1>
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Filter:</span>
            <div className="w-48">
              <Select
                value={statusFilter || "all"}
                onValueChange={(v) => setStatusFilter(v === "all" ? "" : v)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Semua Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua Status</SelectItem>
                  <SelectItem value="waiting_level_1">
                    {getStatusLabel("waiting_level_1")}
                  </SelectItem>
                  <SelectItem value="waiting_level_2">
                    {getStatusLabel("waiting_level_2")}
                  </SelectItem>
                  <SelectItem value="approved">
                    {getStatusLabel("approved")}
                  </SelectItem>
                  <SelectItem value="rejected">
                    {getStatusLabel("rejected")}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <Button onClick={() => navigate("/admin/bookings/create")}>
            <Plus className="mr-2 h-4 w-4" /> Buat Booking
          </Button>
        </div>
      </div>

      {error && (
        <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-4 text-red-600">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span className="text-sm">{error}</span>
        </div>
      )}

      <Card>
        <CardContent className="p-0">
          <DataTable columns={columns} data={bookings} loading={loading} />
        </CardContent>
      </Card>
    </div>
  );
}
