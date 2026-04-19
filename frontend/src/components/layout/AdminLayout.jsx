// File: src/components/layout/AdminLayout.jsx
import {
  LayoutDashboard,
  ClipboardList,
  Car,
  Users,
  UserCog,
  FileBarChart,
  ScrollText,
} from "lucide-react";
import SidebarLayout from "./SidebarLayout";

const NAV_LINKS = [
  { to: "/admin/dashboard", icon: LayoutDashboard, label: "Dashboard" },
  { to: "/admin/bookings", icon: ClipboardList, label: "Pemesanan" },
  { to: "/admin/vehicles", icon: Car, label: "Kendaraan" },
  { to: "/admin/drivers", icon: Users, label: "Driver" },
  { to: "/admin/users", icon: UserCog, label: "Pengguna" },
  { to: "/admin/reports", icon: FileBarChart, label: "Laporan" },
  { to: "/admin/activity-logs", icon: ScrollText, label: "Activity Log" },
];

export default function AdminLayout() {
  return <SidebarLayout navLinks={NAV_LINKS} title="Pemesanan Kendaraan" />;
}
