// File: src/App.jsx

import {
  createBrowserRouter,
  RouterProvider,
  Navigate,
  Outlet,
} from "react-router-dom";
import { AuthProvider } from "@/context/AuthProvider";
import { useAuth } from "@/context/useAuth";
import ProtectedRoute from "@/components/common/ProtectedRoute";
import Login from "@/pages/Login";
import AdminLayout from "@/components/layout/AdminLayout";
import ApproverLayout from "@/components/layout/ApproverLayout";
import AdminDashboard from "@/pages/admin/Dashboard";
import AdminBookings from "@/pages/admin/Bookings";
import AdminBookingDetail from "@/pages/admin/BookingDetail";
import AdminBookingCreate from "@/pages/admin/BookingCreate";
import AdminVehicles from "@/pages/admin/Vehicles";
import AdminDrivers from "@/pages/admin/Drivers";
import AdminUsers from "@/pages/admin/Users";
import AdminReports from "@/pages/admin/Reports";
import AdminActivityLogs from "@/pages/admin/ActivityLogs";
import ApproverDashboard from "@/pages/approver/Dashboard";
import ApproverMyApprovals from "@/pages/approver/MyApprovals";
import ApproverBookings from "@/pages/approver/Bookings";
import ApproverBookingDetail from "@/pages/approver/BookingDetail";

const Page = ({ label }) => (
  <div className="flex items-center justify-center h-screen text-xl font-semibold text-gray-600">
    {label}
  </div>
);

function AuthLayout() {
  return (
    <AuthProvider>
      <Outlet />
    </AuthProvider>
  );
}

function RootRedirect() {
  const { user } = useAuth();
  if (!user) return <Navigate to="/login" replace />;
  if (user.role === "admin") return <Navigate to="/admin/dashboard" replace />;
  if (user.role === "approver")
    return <Navigate to="/approver/dashboard" replace />;
  return <Navigate to="/unauthorized" replace />;
}

const router = createBrowserRouter([
  {
    element: <AuthLayout />,
    children: [
      { path: "/login", element: <Login /> },
      { path: "/unauthorized", element: <Page label="403 Unauthorized" /> },
      { path: "/", element: <RootRedirect /> },

      {
        element: <ProtectedRoute allowedRoles={["admin"]} />,
        children: [
          {
            element: <AdminLayout />,
            children: [
              {
                path: "/admin/dashboard",
                element: <AdminDashboard />,
              },
              { path: "/admin/bookings", element: <AdminBookings /> },
              {
                path: "/admin/bookings/create",
                element: <AdminBookingCreate />,
              },
              {
                path: "/admin/bookings/:id",
                element: <AdminBookingDetail />,
              },
              { path: "/admin/vehicles", element: <AdminVehicles /> },
              { path: "/admin/drivers", element: <AdminDrivers /> },
              { path: "/admin/users", element: <AdminUsers /> },
              { path: "/admin/reports", element: <AdminReports /> },
              { path: "/admin/activity-logs", element: <AdminActivityLogs /> },
            ],
          },
        ],
      },

      {
        element: <ProtectedRoute allowedRoles={["approver"]} />,
        children: [
          {
            element: <ApproverLayout />,
            children: [
              {
                path: "/approver/dashboard",
                element: <ApproverDashboard />,
              },
              {
                path: "/approver/approvals",
                element: <ApproverMyApprovals />,
              },
              {
                path: "/approver/bookings",
                element: <ApproverBookings />,
              },
              {
                path: "/approver/bookings/:id",
                element: <ApproverBookingDetail />,
              },
            ],
          },
        ],
      },
    ],
  },
]);

export default function App() {
  return <RouterProvider router={router} />;
}
