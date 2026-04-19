// File: src/components/layout/SidebarLayout.jsx

import { useState } from "react";
import { NavLink, Outlet, useLocation } from "react-router-dom";
import { LogOut, Menu, PanelLeftClose, PanelLeftOpen } from "lucide-react";
import { useAuth } from "@/context/useAuth";
import { cn } from "@/lib/utils";

export default function SidebarLayout({ navLinks, title }) {
  const { user, logout } = useAuth();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(false);

  const { pathname } = useLocation();
  const activeLabel =
    navLinks.find((link) => pathname.startsWith(link.to))?.label ?? title;

  // Initials dari nama user (maks 2 karakter)
  const initials =
    user?.name
      ?.split(" ")
      .slice(0, 2)
      .map((w) => w[0])
      .join("")
      .toUpperCase() ?? "?";

  return (
    <div className="flex h-screen overflow-hidden bg-muted/40">
      {/* Mobile overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/50 md:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* ── Sidebar ──────────────────────────────────────────────────────── */}
      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 flex flex-col border-r bg-background",
          "transition-all duration-200",
          "w-64",
          sidebarOpen ? "translate-x-0" : "-translate-x-full",
          "md:relative md:translate-x-0",
          collapsed ? "md:w-16" : "md:w-64",
        )}
      >
        {/* [CHANGED] Toggle di KANAN, title di kiri */}
        <div
          className={cn(
            "flex h-14 shrink-0 items-center border-b px-4",
            collapsed ? "md:justify-center" : "justify-between",
          )}
        >
          {/* Title — hidden saat collapsed */}
          <span
            className={cn(
              "truncate text-sm font-semibold transition-all duration-200",
              collapsed ? "md:hidden" : "block",
            )}
          >
            {title}
          </span>

          {/* Toggle — desktop only, selalu ada */}
          <button
            onClick={() => setCollapsed((c) => !c)}
            className="hidden md:flex items-center justify-center rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors shrink-0"
            aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
          >
            {collapsed ? (
              <PanelLeftOpen className="h-5 w-5" />
            ) : (
              <PanelLeftClose className="h-5 w-5" />
            )}
          </button>
        </div>
        {/* Nav links */}
        <nav className="flex-1 overflow-y-auto overflow-x-hidden p-3 space-y-1">
          {navLinks.map((link) => {
            const Icon = link.icon;
            return (
              <NavLink
                key={link.to}
                to={link.to}
                title={link.label}
                onClick={() => setSidebarOpen(false)}
                className={({ isActive }) =>
                  cn(
                    "flex items-center rounded-lg px-2 py-2 text-sm transition-colors",
                    collapsed ? "md:justify-center md:px-0" : "gap-3 px-3",
                    isActive
                      ? "bg-primary text-primary-foreground font-medium"
                      : "text-muted-foreground hover:bg-muted hover:text-foreground",
                  )
                }
              >
                <Icon className="h-4 w-4 shrink-0" />
                <span
                  className={cn(
                    "overflow-hidden whitespace-nowrap transition-all duration-200",
                    collapsed ? "md:w-0 md:opacity-0" : "w-auto opacity-100",
                  )}
                >
                  {link.label}
                </span>
              </NavLink>
            );
          })}
        </nav>

        {/* ── Footer — user info + logout ── */}
        <div className="shrink-0 border-t p-3">
          {/* Expanded: avatar + nama + role stacked */}
          <div
            className={cn(
              "overflow-hidden transition-all duration-200",
              collapsed ? "md:hidden" : "block",
            )}
          >
            <div className="flex items-center gap-3 mb-3">
              {/* [CHANGE #1] Avatar initials */}
              <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                {initials}
              </div>

              {/* [CHANGE #1] Nama di atas, role di bawah — flex-col + gap */}
              <div className="min-w-0 flex flex-col gap-0.5">
                <span className="truncate text-sm font-medium leading-none">
                  {user?.name}
                </span>
                <span className="text-[11px] uppercase tracking-wide text-muted-foreground leading-none">
                  {user?.role}
                </span>
              </div>
            </div>
          </div>

          {/* Collapsed: hanya avatar */}
          <div
            className={cn(
              "hidden mb-3 justify-center",
              collapsed ? "md:flex" : "md:hidden",
            )}
          >
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
              {initials}
            </div>
          </div>

          {/* Logout */}
          <button
            onClick={logout}
            title="Logout"
            className={cn(
              "flex w-full items-center rounded-lg px-2 py-2 text-sm text-red-500 transition-colors hover:bg-red-50 dark:hover:bg-red-950/30",
              collapsed ? "md:justify-center md:px-0" : "gap-3 px-3",
            )}
          >
            <LogOut className="h-4 w-4 shrink-0" />
            <span
              className={cn(
                "overflow-hidden whitespace-nowrap transition-all duration-200",
                collapsed ? "md:w-0 md:opacity-0" : "w-auto opacity-100",
              )}
            >
              Logout
            </span>
          </button>
        </div>
      </aside>

      {/* ── Main Content ─────────────────────────────────────────────────── */}
      <main className="flex flex-1 flex-col min-w-0 overflow-hidden">
        <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-background px-4">
          <button
            onClick={() => setSidebarOpen(true)}
            className="md:hidden text-muted-foreground hover:text-foreground"
            aria-label="Open menu"
          >
            <Menu className="h-5 w-5" />
          </button>
          <span className="text-sm font-semibold truncate">{activeLabel}</span>
        </header>

        <div className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
