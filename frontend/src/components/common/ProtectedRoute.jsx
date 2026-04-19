// File: src/components/common/ProtectedRoute.jsx

import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '@/context/useAuth';

/**
 * Guards a route group.
 * @prop {string[]} allowedRoles - e.g. ["admin"] or ["approver"]
 */
export default function ProtectedRoute({ allowedRoles }) {
  const { token, user } = useAuth();

  if (!token) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles && !allowedRoles.includes(user?.role)) {
    return <Navigate to="/unauthorized" replace />;
  }

  return <Outlet />;
}
