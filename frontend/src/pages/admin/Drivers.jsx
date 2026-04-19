// File: src/pages/admin/Drivers.jsx
import { useState, useEffect, useCallback } from "react";
import { Plus, Pencil, Trash2, AlertCircle } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import DataTable from "@/components/common/DataTable";
import { StatusBadge } from "@/components/common/StatusBadge";
import FormDialog from "@/components/common/FormDialog";
import ConfirmDialog from "@/components/common/ConfirmDialog";
import Button from "@/components/common/Button";
import InputField from "@/components/common/InputField";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import driverService from "@/services/driverService";

export default function Drivers() {
  const [drivers, setDrivers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [filterStatus, setFilterStatus] = useState("");

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editTarget, setEditTarget] = useState(null);

  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const [formLoading, setFormLoading] = useState(false);
  const [formErrors, setFormErrors] = useState({});

  const fetchDrivers = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params =
        filterStatus && filterStatus !== "all" ? { status: filterStatus } : {};
      const data = await driverService.getDrivers(params);
      setDrivers(data.data || []);
    } catch (_) {
      setError("Gagal memuat data driver.");
    } finally {
      setLoading(false);
    }
  }, [filterStatus]);

  useEffect(() => {
    fetchDrivers();
  }, [fetchDrivers]);

  const handleOpenAdd = () => {
    setEditTarget(null);
    setFormErrors({});
    setDialogOpen(true);
  };

  const handleOpenEdit = (driver) => {
    setEditTarget(driver);
    setFormErrors({});
    setDialogOpen(true);
  };

  const handleDeleteClick = (driver) => {
    setDeleteTarget(driver);
  };

  const handleDialogChange = (open) => {
    if (!open) {
      setEditTarget(null);
      setFormErrors({});
    }
    setDialogOpen(open);
  };

  const handleFormSubmit = async (e) => {
    e.preventDefault();
    setFormErrors({});
    setFormLoading(true);

    const formData = new FormData(e.target);
    const data = {
      name: formData.get("name"),
      license_number: formData.get("license_number"),
      phone: formData.get("phone"),
      status: formData.get("status"),
    };

    try {
      if (editTarget) {
        await driverService.updateDriver(editTarget.id, data);
      } else {
        await driverService.createDriver(data);
      }
      setDialogOpen(false);
      fetchDrivers();
    } catch (err) {
      if (err.response?.data?.errors) {
        setFormErrors(err.response.data.errors);
      }
    } finally {
      setFormLoading(false);
    }
  };

  const handleConfirmDelete = async () => {
    if (!deleteTarget) return;
    setDeleteLoading(true);
    try {
      await driverService.deleteDriver(deleteTarget.id);
      setDeleteTarget(null);
      fetchDrivers();
    } catch (_) {
      // Ignore or show toast in future
    } finally {
      setDeleteLoading(false);
    }
  };

  const columns = [
    {
      key: "no",
      header: "No",
      render: (_row, index) => index + 1,
    },
    { key: "name", header: "Nama" },
    { key: "license_number", header: "No. Lisensi" },
    { key: "phone", header: "No. Telepon" },
    {
      key: "status",
      header: "Status",
      render: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: "action",
      header: "Aksi",
      render: (row) => (
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" onClick={() => handleOpenEdit(row)}>
            <Pencil className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="text-red-500 hover:text-red-700"
            onClick={() => handleDeleteClick(row)}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold tracking-tight">Driver</h1>
        <Button onClick={handleOpenAdd}>
          <Plus className="mr-2 h-4 w-4" /> Tambah Driver
        </Button>
      </div>

      <div className="flex items-center gap-2">
        <span className="text-sm text-muted-foreground">Filter:</span>
        <div className="w-[200px]">
          <Select
            value={filterStatus || "all"}
            onValueChange={(v) => setFilterStatus(v === "all" ? "" : v)}
          >
            <SelectTrigger>
              <SelectValue placeholder="Semua Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Semua</SelectItem>
              <SelectItem value="active">Aktif</SelectItem>
              <SelectItem value="inactive">Tidak Aktif</SelectItem>
            </SelectContent>
          </Select>
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
          <DataTable columns={columns} data={drivers} loading={loading} />
        </CardContent>
      </Card>

      <FormDialog
        open={dialogOpen}
        onOpenChange={handleDialogChange}
        title={editTarget ? "Edit Driver" : "Tambah Driver"}
      >
        <form onSubmit={handleFormSubmit} className="space-y-4">
          <InputField
            label="Nama Driver"
            name="name"
            defaultValue={editTarget?.name}
            error={formErrors.name}
            placeholder="Misal: Budi Santoso"
            required
          />
          <InputField
            label="No. Lisensi"
            name="license_number"
            defaultValue={editTarget?.license_number}
            error={formErrors.license_number}
            placeholder="Misal: SIM A - 123456"
            required
          />
          <InputField
            label="No. Telepon"
            name="phone"
            defaultValue={editTarget?.phone}
            error={formErrors.phone}
            placeholder="Misal: 08123456789"
            required
          />

          <div className="space-y-2">
            <label className="text-sm font-medium">Status</label>
            <Select
              name="status"
              defaultValue={editTarget?.status || "active"}
            >
              <SelectTrigger>
                <SelectValue placeholder="Pilih status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="active">Aktif</SelectItem>
                <SelectItem value="inactive">Tidak Aktif</SelectItem>
              </SelectContent>
            </Select>
            {formErrors.status && (
              <p className="text-sm text-red-500">{formErrors.status}</p>
            )}
          </div>

          <div className="flex justify-end gap-2 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => handleDialogChange(false)}
            >
              Batal
            </Button>
            <Button type="submit" loading={formLoading}>
              Simpan
            </Button>
          </div>
        </form>
      </FormDialog>

      <ConfirmDialog
        open={!!deleteTarget}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title="Hapus Driver"
        description={`Driver ${deleteTarget?.name} akan dihapus permanen.`}
        confirmText="Hapus"
        cancelText="Batal"
        onConfirm={handleConfirmDelete}
        loading={deleteLoading}
        variant="destructive"
      />
    </div>
  );
}
