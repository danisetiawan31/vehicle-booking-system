// File: src/components/common/StatusBadge.jsx
import { useMemo } from "react";
import { getStatusLabel } from "@/utils/utils";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import {
  Clock,
  CheckCircle,
  XCircle,
  Wrench,
  MinusCircle,
  Shield,
  Package,
  UserRound,
  HelpCircle,
  UserCheck,
} from "lucide-react";

const COLORS = {
  green:
    "bg-green-500/10 text-green-600 border-green-500/20 hover:bg-green-500/20",
  yellow:
    "bg-yellow-500/10 text-yellow-600 border-yellow-500/20 hover:bg-yellow-500/20",
  blue: "bg-blue-500/10 text-blue-600 border-blue-500/20 hover:bg-blue-500/20",
  red: "bg-red-500/10 text-red-600 border-red-500/20 hover:bg-red-500/20",
  gray: "bg-gray-500/10 text-gray-500 border-gray-500/20 hover:bg-gray-500/20",
  purple:
    "bg-purple-500/10 text-purple-600 border-purple-500/20 hover:bg-purple-500/20",
  teal: "bg-teal-500/10 text-teal-600 border-teal-500/20 hover:bg-teal-500/20",
  orange:
    "bg-orange-500/10 text-orange-600 border-orange-500/20 hover:bg-orange-500/20",
};

const STATUS_MAP = {
  // Booking status
  waiting_level_1: { icon: Clock, classNames: COLORS.yellow },
  waiting_level_2: { icon: Clock, classNames: COLORS.blue },
  approved: { icon: CheckCircle, classNames: COLORS.green },
  rejected: { icon: XCircle, classNames: COLORS.red },

  // Vehicle status
  available: { icon: CheckCircle, classNames: COLORS.green },
  maintenance: { icon: Wrench, classNames: COLORS.yellow },
  inactive: { icon: MinusCircle, classNames: COLORS.gray },

  // Driver status
  active: { icon: CheckCircle, classNames: COLORS.green },

  // Approval record status
  pending: { icon: Clock, classNames: COLORS.yellow },

  // Vehicle ownership
  own: { icon: Shield, classNames: COLORS.blue },
  rental: { icon: Package, classNames: COLORS.purple },

  // Vehicle type
  passenger: { icon: UserRound, classNames: COLORS.teal },
  cargo: { icon: Package, classNames: COLORS.orange },

  // Role
  admin: { icon: Shield, classNames: COLORS.blue, label: "Admin" },
  approver: { icon: UserCheck, classNames: COLORS.purple, label: "Approver" },
};

const FALLBACK_CONFIG = {
  icon: HelpCircle,
  classNames: COLORS.gray,
  label: "Tidak Diketahui",
};

function resolveStatus(status) {
  if (typeof status === "boolean") {
    return status ? "active" : "inactive";
  }
  if (typeof status === "string") {
    return status.toLowerCase();
  }
  return "";
}

export function StatusBadge({ status, className, hideIcon = false }) {
  const config = useMemo(() => {
    const normalized = resolveStatus(status);
    let label = getStatusLabel(normalized);

    if (label === normalized) {
      label = status ? String(status) : FALLBACK_CONFIG.label;
    }

    if (STATUS_MAP[normalized]) {
      return {
        ...STATUS_MAP[normalized],
        label,
      };
    }
    // Fallback but use the raw status string as label if it's truthy
    return {
      ...FALLBACK_CONFIG,
      label,
    };
  }, [status]);

  const Icon = config.icon;

  return (
    <Badge
      variant="outline"
      className={cn(
        "gap-1.5 whitespace-nowrap px-2.5 py-0.5 text-xs font-medium transition-colors",
        config.classNames,
        className,
      )}
    >
      {!hideIcon && <Icon className="h-3.5 w-3.5 shrink-0" />}
      {config.label}
    </Badge>
  );
}
