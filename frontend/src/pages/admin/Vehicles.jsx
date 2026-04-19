// File: src/pages/admin/Vehicles.jsx
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
import vehicleService from "@/services/vehicleService";

export default function Vehicles() {
  const [vehicles, setVehicles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [filterStatus, setFilterStatus] = useState("");

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editTarget, setEditTarget] = useState(null);

  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const [formLoading, setFormLoading] = useState(false);
  const [formErrors, setFormErrors] = useState({});

  const [formType, setFormType] = useState("passenger");
  const [formOwnership, setFormOwnership] = useState("own");
  const [formStatus, setFormStatus] = useState("available");

  const fetchVehicles = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params =
        filterStatus && filterStatus !== "all" ? { status: filterStatus } : {};
      const data = await vehicleService.getVehicles(params);
      setVehicles(data.data || []);
    } catch (err) {
      setError("Gagal memuat data kendaraan.");
    } finally {
      setLoading(false);
    }
  }, [filterStatus]);

  useEffect(() => {
    fetchVehicles();
  }, [fetchVehicles]);

  const handleOpenAdd = () => {
    setEditTarget(null);
    setFormErrors({});
    setFormType("passenger");
    setFormOwnership("own");
    setFormStatus("available");
    setDialogOpen(true);
  };

  const handleOpenEdit = (vehicle) => {
    setEditTarget(vehicle);
    setFormErrors({});
    setFormType(vehicle.type || "passenger");
    setFormOwnership(vehicle.ownership || "own");
    setFormStatus(vehicle.status || "available");
    setDialogOpen(true);
  };

  const handleDeleteClick = (vehicle) => {
    setDeleteTarget(vehicle);
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
      plate_number: formData.get("plate_number"),
      type: formType,
      ownership: formOwnership,
      region: formData.get("region"),
      status: formStatus,
    };

    try {
      if (editTarget) {
        await vehicleService.updateVehicle(editTarget.id, data);
      } else {
        await vehicleService.createVehicle(data);
      }
      setDialogOpen(false);
      fetchVehicles();
    } catch (err) {
      if (err.response?.data?.data) {
        setFormErrors(err.response.data.data);
      } else if (err.response?.data?.errors) {
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
      await vehicleService.deleteVehicle(deleteTarget.id);
      setDeleteTarget(null);
      fetchVehicles();
    } catch (err) {
      if (err.response?.data?.errors) {
        setFormErrors(err.response.data.errors);
      }
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
    { key: "plate_number", header: "Plat Nomor" },
    {
      key: "type",
      header: "Tipe",
      render: (row) => <StatusBadge status={row.type} />,
    },
    {
      key: "ownership",
      header: "Kepemilikan",
      render: (row) => <StatusBadge status={row.ownership} />,
    },
    { key: "region", header: "Region" },
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
        <h1 className="text-2xl font-bold tracking-tight">Kendaraan</h1>
        <Button onClick={handleOpenAdd}>
          <Plus className="mr-2 h-4 w-4" /> Tambah Kendaraan
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
              <SelectItem value="available">Tersedia</SelectItem>
              <SelectItem value="maintenance">Perawatan</SelectItem>
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
          <DataTable columns={columns} data={vehicles} loading={loading} />
        </CardContent>
      </Card>

      <FormDialog
        open={dialogOpen}
        onOpenChange={handleDialogChange}
        title={editTarget ? "Edit Kendaraan" : "Tambah Kendaraan"}
      >
        <form onSubmit={handleFormSubmit} className="space-y-4">
          <InputField
            label="Nama Kendaraan"
            name="name"
            defaultValue={editTarget?.name}
            error={formErrors.name}
            placeholder="Misal: Avanza Hitam"
          />
          <InputField
            label="Plat Nomor"
            name="plate_number"
            defaultValue={editTarget?.plate_number}
            error={formErrors.plate_number}
            placeholder="Misal: B 1234 CD"
          />

          <div className="space-y-2">
            <label className="text-sm font-medium">Tipe</label>
            <Select value={formType} onValueChange={setFormType}>
              <SelectTrigger>
                <SelectValue placeholder="Pilih tipe" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="passenger">Penumpang</SelectItem>
                <SelectItem value="cargo">Kargo</SelectItem>
              </SelectContent>
            </Select>
            {formErrors.type && (
              <p className="text-sm text-red-500">{formErrors.type}</p>
            )}
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Kepemilikan</label>
            <Select value={formOwnership} onValueChange={setFormOwnership}>
              <SelectTrigger>
                <SelectValue placeholder="Pilih kepemilikan" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="own">Milik Sendiri</SelectItem>
                <SelectItem value="rental">Sewa</SelectItem>
              </SelectContent>
            </Select>
            {formErrors.ownership && (
              <p className="text-sm text-red-500">{formErrors.ownership}</p>
            )}
          </div>

          <InputField
            label="Region"
            name="region"
            defaultValue={editTarget?.region}
            error={formErrors.region}
            placeholder="Misal: Jakarta"
          />

          <div className="space-y-2">
            <label className="text-sm font-medium">Status</label>
            <Select value={formStatus} onValueChange={setFormStatus}>
              <SelectTrigger>
                <SelectValue placeholder="Pilih status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="available">Tersedia</SelectItem>
                <SelectItem value="maintenance">Perawatan</SelectItem>
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
        title="Hapus Kendaraan"
        description={`Kendaraan ${deleteTarget?.name} akan dihapus permanen.`}
        confirmText="Hapus"
        cancelText="Batal"
        onConfirm={handleConfirmDelete}
        loading={deleteLoading}
        variant="destructive"
      />
    </div>
  );
}
