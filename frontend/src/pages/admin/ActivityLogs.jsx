// File: src/pages/admin/ActivityLogs.jsx
import { useState, useEffect, useCallback } from "react";
import { AlertCircle, ChevronLeft, ChevronRight } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import Button from "@/components/common/Button";
import DataTable from "@/components/common/DataTable";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import activityLogService from "@/services/activityLogService";
import { formatDate } from "@/utils/utils";

const ENTITY_TYPES = [
  { value: "", label: "Semua Entitas" },
  { value: "booking", label: "Booking" },
  { value: "vehicle", label: "Kendaraan" },
  { value: "driver", label: "Driver" },
  { value: "user", label: "User" },
];

const DEFAULT_FILTERS = {
  entity_type: "",
  start_date: "",
  end_date: "",
};

export default function ActivityLogs() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Filters — only applied on button click
  const [filters, setFilters] = useState(DEFAULT_FILTERS);
  const [pendingFilters, setPendingFilters] = useState(DEFAULT_FILTERS);

  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState({
    total: 0,
    page: 1,
    limit: 50,
    total_pages: 1,
  });

  const fetchLogs = useCallback(async (activeFilters, activePage) => {
    setLoading(true);
    setError(null);
    try {
      const params = { page: activePage };
      if (activeFilters.entity_type)
        params.entity_type = activeFilters.entity_type;
      if (activeFilters.start_date)
        params.start_date = activeFilters.start_date;
      if (activeFilters.end_date) params.end_date = activeFilters.end_date;

      const res = await activityLogService.getActivityLogs(params);
      setLogs(res.data.logs || []);
      setPagination(res.data.pagination);
    } catch (_) {
      setError("Gagal memuat data activity log.");
    } finally {
      setLoading(false);
    }
  }, []);

  // Initial fetch on mount
  useEffect(() => {
    fetchLogs(DEFAULT_FILTERS, 1);
  }, [fetchLogs]);

  const handleApply = () => {
    setFilters(pendingFilters);
    setPage(1);
    fetchLogs(pendingFilters, 1);
  };

  const handleReset = () => {
    setPendingFilters(DEFAULT_FILTERS);
    setFilters(DEFAULT_FILTERS);
    setPage(1);
    fetchLogs(DEFAULT_FILTERS, 1);
  };

  const handlePrev = () => {
    const newPage = page - 1;
    setPage(newPage);
    fetchLogs(filters, newPage);
  };

  const handleNext = () => {
    const newPage = page + 1;
    setPage(newPage);
    fetchLogs(filters, newPage);
  };

  const columns = [
    {
      key: "created_at",
      header: "Waktu",
      render: (row) => (
        <span className="whitespace-nowrap text-sm">
          {formatDate(row.created_at)}
        </span>
      ),
    },
    { key: "user_name", header: "User" },
    { key: "action", header: "Aksi" },
    { key: "entity_type", header: "Entity Type" },
    { key: "entity_id", header: "Entity ID" },
    {
      key: "description",
      header: "Deskripsi",
      render: (row) => (
        <span className="text-sm min-w-[200px] block whitespace-normal">
          {row.description}
        </span>
      ),
    },
    { key: "ip_address", header: "IP Address" },
  ];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold tracking-tight">Activity Log</h1>

      {/* Filter card */}
      <Card>
        <CardContent className="p-6">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:flex-wrap">
            <div className="w-full sm:w-44">
              <label className="text-sm font-medium block mb-2">
                Entity Type
              </label>
              <Select
                value={pendingFilters.entity_type || "all"}
                onValueChange={(v) =>
                  setPendingFilters((f) => ({
                    ...f,
                    entity_type: v === "all" ? "" : v,
                  }))
                }
              >
                <SelectTrigger>
                  <SelectValue placeholder="Semua Entitas" />
                </SelectTrigger>
                <SelectContent>
                  {ENTITY_TYPES.map((et) => (
                    <SelectItem
                      key={et.value || "all"}
                      value={et.value || "all"}
                    >
                      {et.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium">Tanggal Awal</label>
              <input
                type="date"
                className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                value={pendingFilters.start_date}
                onChange={(e) =>
                  setPendingFilters((f) => ({
                    ...f,
                    start_date: e.target.value,
                  }))
                }
              />
            </div>

            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium">Tanggal Akhir</label>
              <input
                type="date"
                className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                value={pendingFilters.end_date}
                onChange={(e) =>
                  setPendingFilters((f) => ({ ...f, end_date: e.target.value }))
                }
              />
            </div>

            <div className="flex gap-2 sm:self-end">
              <Button onClick={handleApply} loading={loading}>
                Terapkan
              </Button>
              <Button variant="outline" onClick={handleReset}>
                Reset
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {error && (
        <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-4 text-red-600">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span className="text-sm">{error}</span>
        </div>
      )}

      <Card>
        <CardContent className="p-0">
          <DataTable
            columns={columns}
            data={logs}
            loading={loading}
            emptyText="Tidak ada activity log untuk filter yang dipilih."
          />
        </CardContent>
      </Card>

      {/* Pagination */}
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">
          Halaman {pagination.page} dari {pagination.total_pages}
          <span className="ml-2 text-xs">({pagination.total} total log)</span>
        </p>
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={handlePrev}
            disabled={page === 1 || loading}
          >
            <ChevronLeft className="h-4 w-4 mr-1" />
            Prev
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={handleNext}
            disabled={page >= pagination.total_pages || loading}
          >
            Next
            <ChevronRight className="h-4 w-4 ml-1" />
          </Button>
        </div>
      </div>
    </div>
  );
}
