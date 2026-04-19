// File: src/pages/admin/BookingCreate.jsx
import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { ArrowLeft, AlertCircle } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
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
import driverService from "@/services/driverService";
import userService from "@/services/userService";
import bookingService from "@/services/bookingService";

function toBackendDate(datetimeLocal) {
  if (!datetimeLocal) return "";
  return datetimeLocal.replace("T", " ") + ":00";
}

export default function BookingCreate() {
  const navigate = useNavigate();

  const [vehicles, setVehicles] = useState([]);
  const [drivers, setDrivers] = useState([]);
  const [approvers1, setApprovers1] = useState([]);
  const [approvers2, setApprovers2] = useState([]);
  const [loading, setLoading] = useState(true);

  const [formLoading, setFormLoading] = useState(false);
  const [formErrors, setFormErrors] = useState({});
  const [conflictError, setConflictError] = useState(null);

  const [vehicleId, setVehicleId] = useState("");
  const [driverId, setDriverId] = useState("");
  const [approver1Id, setApprover1Id] = useState("");
  const [approver2Id, setApprover2Id] = useState("");

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      try {
        const [vehiclesRes, driversRes, usersRes] = await Promise.all([
          vehicleService.getVehicles({ status: "available" }),
          driverService.getDrivers({ status: "active" }),
          userService.getUsers(),
        ]);
        setVehicles(vehiclesRes.data || []);
        setDrivers(driversRes.data || []);
        const allUsers = usersRes.data || [];
        setApprovers1(
          allUsers.filter(
            (u) => u.role === "approver" && Number(u.approval_level) === 1,
          ),
        );
        setApprovers2(
          allUsers.filter(
            (u) => u.role === "approver" && Number(u.approval_level) === 2,
          ),
        );
      } catch (_) {
        // Dropdowns will just be empty; user will see empty selects
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormErrors({});
    setConflictError(null);
    setFormLoading(true);

    const formData = new FormData(e.target);

    const payload = {
      vehicle_id: parseInt(vehicleId, 10),
      driver_id: parseInt(driverId, 10),
      requester_name: formData.get("requester_name"),
      purpose: formData.get("purpose"),
      destination: formData.get("destination"),
      start_date: toBackendDate(formData.get("start_date")),
      end_date: toBackendDate(formData.get("end_date")),
      approver_level1_id: parseInt(approver1Id, 10),
      approver_level2_id: parseInt(approver2Id, 10),
    };

    try {
      await bookingService.createBooking(payload);
      navigate("/admin/bookings");
    } catch (err) {
      if (err.response?.status === 422) {
        setFormErrors(err.response.data.errors || {});
      } else if (err.response?.status === 409) {
        setConflictError(
          err.response.data.message || "Terjadi konflik jadwal booking.",
        );
      }
    } finally {
      setFormLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Button variant="outline" size="icon" onClick={() => navigate(-1)}>
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <h1 className="text-2xl font-bold tracking-tight">Buat Booking</h1>
      </div>

      {conflictError && (
        <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-4 text-red-600">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span className="text-sm">{conflictError}</span>
        </div>
      )}

      <Card>
        <CardContent className="p-6">
          <form onSubmit={handleSubmit} className="space-y-0">
            {/* Informasi Pemohon */}
            <p className="text-sm font-semibold text-muted-foreground mb-3">
              Informasi Pemohon
            </p>
            <div className="space-y-4">
              <InputField
                label="Nama Pemohon"
                name="requester_name"
                placeholder="Misal: John Doe"
                error={formErrors.requester_name}
                required
              />
              <InputField
                label="Keperluan"
                name="purpose"
                placeholder="Misal: Kunjungan klien"
                error={formErrors.purpose}
                required
              />
              <InputField
                label="Tujuan"
                name="destination"
                placeholder="Misal: Jakarta Selatan"
                error={formErrors.destination}
                required
              />
            </div>

            <hr className="my-6" />

            {/* Waktu */}
            <p className="text-sm font-semibold text-muted-foreground mb-3">
              Waktu
            </p>
            <div className="grid grid-cols-2 gap-4">
              <InputField
                label="Tanggal Mulai"
                name="start_date"
                type="datetime-local"
                error={formErrors.start_date}
                required
              />
              <InputField
                label="Tanggal Selesai"
                name="end_date"
                type="datetime-local"
                error={formErrors.end_date}
                required
              />
            </div>

            <hr className="my-6" />

            {/* Kendaraan & Driver */}
            <p className="text-sm font-semibold text-muted-foreground mb-3">
              Kendaraan & Driver
            </p>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <label className="text-sm font-medium">Kendaraan</label>
                <Select
                  value={vehicleId}
                  onValueChange={setVehicleId}
                  disabled={loading}
                >
                  <SelectTrigger>
                    <SelectValue
                      placeholder={
                        loading ? "Memuat data..." : "Pilih kendaraan"
                      }
                    />
                  </SelectTrigger>
                  <SelectContent>
                    {vehicles.map((v) => (
                      <SelectItem key={v.id} value={String(v.id)}>
                        {v.name} ({v.plate_number})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {formErrors.vehicle_id && (
                  <p className="text-sm text-red-500">
                    {formErrors.vehicle_id}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium">Driver</label>
                <Select
                  value={driverId}
                  onValueChange={setDriverId}
                  disabled={loading}
                >
                  <SelectTrigger>
                    <SelectValue
                      placeholder={loading ? "Memuat data..." : "Pilih driver"}
                    />
                  </SelectTrigger>
                  <SelectContent>
                    {drivers.map((d) => (
                      <SelectItem key={d.id} value={String(d.id)}>
                        {d.name} — {d.phone}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {formErrors.driver_id && (
                  <p className="text-sm text-red-500">{formErrors.driver_id}</p>
                )}
              </div>
            </div>

            <hr className="my-6" />

            {/* Approver */}
            <p className="text-sm font-semibold text-muted-foreground mb-3">
              Approver
            </p>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <label className="text-sm font-medium">Approver Level 1</label>
                <Select
                  value={approver1Id}
                  onValueChange={setApprover1Id}
                  disabled={loading}
                >
                  <SelectTrigger>
                    <SelectValue
                      placeholder={
                        loading ? "Memuat data..." : "Pilih approver L1"
                      }
                    />
                  </SelectTrigger>
                  <SelectContent>
                    {approvers1.map((u) => (
                      <SelectItem key={u.id} value={String(u.id)}>
                        {u.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {formErrors.approver_level1_id && (
                  <p className="text-sm text-red-500">
                    {formErrors.approver_level1_id}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium">Approver Level 2</label>
                <Select
                  value={approver2Id}
                  onValueChange={setApprover2Id}
                  disabled={loading}
                >
                  <SelectTrigger>
                    <SelectValue
                      placeholder={
                        loading ? "Memuat data..." : "Pilih approver L2"
                      }
                    />
                  </SelectTrigger>
                  <SelectContent>
                    {approvers2.map((u) => (
                      <SelectItem key={u.id} value={String(u.id)}>
                        {u.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {formErrors.approver_level2_id && (
                  <p className="text-sm text-red-500">
                    {formErrors.approver_level2_id}
                  </p>
                )}
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-8">
              <Button
                type="button"
                variant="outline"
                onClick={() => navigate(-1)}
              >
                Batal
              </Button>
              <Button type="submit" loading={formLoading}>
                Buat Booking
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
