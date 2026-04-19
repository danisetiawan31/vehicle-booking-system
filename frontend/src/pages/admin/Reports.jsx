// File: src/pages/admin/Reports.jsx
import { useState } from "react";
import { FileDown, AlertCircle } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import Button from "@/components/common/Button";
import InputField from "@/components/common/InputField";
import DataTable from "@/components/common/DataTable";
import { StatusBadge } from "@/components/common/StatusBadge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import reportService from "@/services/reportService";
import { formatDateShort, getStatusLabel } from "@/utils/utils";

export default function Reports() {
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [statusFilter, setStatusFilter] = useState("");

  const [reports, setReports] = useState(null);
  const [loading, setLoading] = useState(false);
  const [exportLoading, setExportLoading] = useState(false);
  const [filterErrors, setFilterErrors] = useState({});
  const [fetchError, setFetchError] = useState(null);

  const validate = () => {
    const errors = {};
    if (!startDate) errors.start_date = "Tanggal awal wajib diisi.";
    if (!endDate) errors.end_date = "Tanggal akhir wajib diisi.";
    if (startDate && endDate && startDate > endDate) {
      errors.end_date = "Tanggal akhir harus sama atau setelah tanggal awal.";
    }
    return errors;
  };

  const handleFetch = async () => {
    setFetchError(null);
    const errors = validate();
    if (Object.keys(errors).length > 0) {
      setFilterErrors(errors);
      return;
    }
    setFilterErrors({});
    setLoading(true);
    try {
      const params = { start_date: startDate, end_date: endDate };
      if (statusFilter) params.status = statusFilter;
      const data = await reportService.getReports(params);
      setReports(data.data || []);
    } catch (_) {
      setFetchError("Gagal memuat data laporan.");
    } finally {
      setLoading(false);
    }
  };

  const handleExport = async () => {
    setExportLoading(true);
    try {
      const params = { start_date: startDate, end_date: endDate };
      if (statusFilter) params.status = statusFilter;
      const blob = await reportService.exportReports(params);
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `laporan-booking-${startDate}-${endDate}.xlsx`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (_) {
      // silent — export errors are non-critical
    } finally {
      setExportLoading(false);
    }
  };

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
      key: "approver_l1",
      header: "Approver L1",
      render: (row) => row.approver_l1_name ?? "-",
    },
    {
      key: "approver_l2",
      header: "Approver L2",
      render: (row) => row.approver_l2_name ?? "-",
    },
    { key: "admin_name", header: "Dibuat Oleh" },
  ];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold tracking-tight">Laporan Pemesanan</h1>

      {fetchError && (
        <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-4 text-red-600">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span className="text-sm">{fetchError}</span>
        </div>
      )}

      {/* Filter card */}
      <Card>
        <CardContent className="p-6">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:flex-wrap">
            <div className="grid grid-cols-2 gap-4 flex-1 min-w-0">
              <InputField
                label="Tanggal Awal"
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                error={filterErrors.start_date}
              />
              <InputField
                label="Tanggal Akhir"
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                error={filterErrors.end_date}
              />
            </div>

            <div className="w-full sm:w-48">
              <label className="text-sm font-medium block mb-2">Status</label>
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

            <div className="flex gap-2 sm:self-end">
              <Button loading={loading} onClick={handleFetch}>
                Tampilkan
              </Button>
              <Button
                variant="outline"
                disabled={!reports || reports.length === 0}
                loading={exportLoading}
                onClick={handleExport}
              >
                <FileDown className="mr-2 h-4 w-4" />
                Export Excel
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Table card */}
      {reports === null ? (
        <div className="flex items-center justify-center rounded-lg border border-dashed p-12 text-muted-foreground text-sm">
          Pilih filter dan klik Tampilkan untuk melihat laporan.
        </div>
      ) : (
        <Card>
          <CardContent className="p-0">
            <DataTable
              columns={columns}
              data={reports}
              loading={loading}
              emptyText="Tidak ada data untuk filter yang dipilih."
            />
          </CardContent>
        </Card>
      )}
    </div>
  );
}
