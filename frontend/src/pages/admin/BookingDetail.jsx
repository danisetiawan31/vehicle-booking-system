// File: src/pages/admin/BookingDetail.jsx
import { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { ArrowLeft, AlertCircle } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { StatusBadge } from "@/components/common/StatusBadge";
import Button from "@/components/common/Button";
import bookingService from "@/services/bookingService";
import { formatDate, formatDateShort } from "@/utils/utils";

export default function BookingDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [booking, setBooking] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchBooking = async () => {
      setLoading(true);
      setError(null);
      try {
        const data = await bookingService.getBookingById(id);
        setBooking(data.data);
      } catch (_) {
        setError("Gagal memuat detail booking.");
      } finally {
        setLoading(false);
      }
    };
    fetchBooking();
  }, [id]);

  if (error) {
    return (
      <div className="space-y-4">
        <Button variant="outline" onClick={() => navigate(-1)}>
          <ArrowLeft className="mr-2 h-4 w-4" /> Kembali
        </Button>
        <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-4 text-red-600">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span className="text-sm">{error}</span>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="h-10 w-1/3 animate-pulse rounded bg-muted"></div>
        <Card>
          <CardContent className="p-6 space-y-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <div
                key={i}
                className="h-6 w-full animate-pulse rounded bg-muted"
              ></div>
            ))}
          </CardContent>
        </Card>
      </div>
    );
  }

  if (!booking) return null;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="outline" size="icon" onClick={() => navigate(-1)}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <h1 className="text-2xl font-bold tracking-tight">
            {booking.booking_code}
          </h1>
        </div>
        <StatusBadge status={booking.status} />
      </div>

      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <Card className="md:col-span-2 lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-lg">Informasi Booking</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-x-6 gap-y-5">
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Kode Booking</p>
                <p className="text-sm font-medium">{booking.booking_code}</p>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Pemohon</p>
                <p className="text-sm font-medium">{booking.requester_name}</p>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Tgl Mulai</p>
                <p className="text-sm font-medium">
                  {formatDateShort(booking.start_date)}
                </p>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Tgl Selesai</p>
                <p className="text-sm font-medium">
                  {formatDateShort(booking.end_date)}
                </p>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Dibuat Oleh</p>
                <p className="text-sm font-medium">{booking.admin?.name}</p>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Dibuat Pada</p>
                <p className="text-sm font-medium">
                  {formatDate(booking.created_at)}
                </p>
              </div>
              <div className="col-span-2 space-y-1">
                <p className="text-xs text-muted-foreground">Tujuan</p>
                <p className="text-sm font-medium">{booking.destination}</p>
              </div>
              <div className="col-span-2 space-y-1">
                <p className="text-xs text-muted-foreground">Keperluan</p>
                <p className="text-sm font-medium">{booking.purpose}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Kendaraan & Driver</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-4">
              <div className="col-span-2 space-y-1">
                <p className="text-xs text-muted-foreground">Nama Kendaraan</p>
                <p className="text-sm font-medium">{booking.vehicle?.name}</p>
              </div>
              <div className="col-span-2 space-y-1">
                <p className="text-xs text-muted-foreground">Plat Nomor</p>
                <p className="text-sm font-medium">
                  {booking.vehicle?.plate_number}
                </p>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground mb-1">Tipe</p>
                <StatusBadge status={booking.vehicle?.type} />
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground mb-1">
                  Kepemilikan
                </p>
                <StatusBadge status={booking.vehicle?.ownership} />
              </div>
              <div className="col-span-2 pt-2 border-t mt-2 space-y-1">
                <p className="text-xs text-muted-foreground">Nama Driver</p>
                <p className="text-sm font-medium">{booking.driver?.name}</p>
              </div>
              <div className="col-span-2 space-y-1">
                <p className="text-xs text-muted-foreground">No. SIM</p>
                <p className="text-sm font-medium">
                  {booking.driver?.license_number}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Riwayat Approval</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-muted/50">
                  <th className="h-10 px-4 text-left font-medium text-muted-foreground">
                    Level
                  </th>
                  <th className="h-10 px-4 text-left font-medium text-muted-foreground">
                    Approver
                  </th>
                  <th className="h-10 px-4 text-left font-medium text-muted-foreground">
                    Status
                  </th>
                  <th className="h-10 px-4 text-left font-medium text-muted-foreground">
                    Catatan
                  </th>
                  <th className="h-10 px-4 text-left font-medium text-muted-foreground">
                    Ditindak Pada
                  </th>
                </tr>
              </thead>
              <tbody>
                {booking.approvals && booking.approvals.length > 0 ? (
                  booking.approvals.map((approval) => (
                    <tr key={approval.id} className="border-b last:border-0">
                      <td className="px-4 py-3">{approval.level}</td>
                      <td className="px-4 py-3">{approval.approver_name}</td>
                      <td className="px-4 py-3">
                        <StatusBadge status={approval.status} />
                      </td>
                      <td className="px-4 py-3">{approval.notes || "-"}</td>
                      <td className="px-4 py-3">
                        {approval.acted_at
                          ? formatDate(approval.acted_at)
                          : "-"}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td
                      colSpan={5}
                      className="h-24 text-center text-muted-foreground"
                    >
                      Tidak ada riwayat approval
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
