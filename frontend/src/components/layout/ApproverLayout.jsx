// File: src/components/layout/ApproverLayout.jsx
import { LayoutDashboard, CheckSquare, ClipboardList } from "lucide-react";
import SidebarLayout from "./SidebarLayout";

const NAV_LINKS = [
  { to: "/approver/dashboard", icon: LayoutDashboard, label: "Dashboard" },
  { to: "/approver/approvals", icon: CheckSquare, label: "Persetujuan" },
  { to: "/approver/bookings", icon: ClipboardList, label: "Semua Booking" },
];

export default function ApproverLayout() {
  return <SidebarLayout navLinks={NAV_LINKS} title="Pemesanan Kendaraan" />;
}
