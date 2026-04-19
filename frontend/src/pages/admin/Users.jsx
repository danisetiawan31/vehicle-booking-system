// File: src/pages/admin/Users.jsx
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
import userService from "@/services/userService";

export default function Users() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editTarget, setEditTarget] = useState(null);
  const [formRole, setFormRole] = useState("approver");
  const [formApprovalLevel, setFormApprovalLevel] = useState("1");

  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const [formLoading, setFormLoading] = useState(false);
  const [formErrors, setFormErrors] = useState({});

  const fetchUsers = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await userService.getUsers();
      setUsers(data.data || []);
    } catch (_) {
      setError("Gagal memuat data pengguna.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  const handleOpenAdd = () => {
    setEditTarget(null);
    setFormRole("approver");
    setFormApprovalLevel("1");
    setFormErrors({});
    setDialogOpen(true);
  };

  const handleOpenEdit = (user) => {
    setEditTarget(user);
    setFormRole(user.role || "approver");
    setFormApprovalLevel(user.approval_level ? String(user.approval_level) : "1");
    setFormErrors({});
    setDialogOpen(true);
  };

  const handleDeleteClick = (user) => {
    setDeleteTarget(user);
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
      email: formData.get("email"),
      role: formRole,
    };

    const password = formData.get("password");
    if (password) data.password = password;

    if (formRole === "approver") {
      data.approval_level = formApprovalLevel;
    }

    try {
      if (editTarget) {
        await userService.updateUser(editTarget.id, data);
      } else {
        await userService.createUser(data);
      }
      setDialogOpen(false);
      fetchUsers();
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
      await userService.deleteUser(deleteTarget.id);
      setDeleteTarget(null);
      fetchUsers();
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
    { key: "email", header: "Email" },
    {
      key: "role",
      header: "Role",
      render: (row) => <StatusBadge status={row.role} />,
    },
    {
      key: "approval_level",
      header: "Level Approval",
      render: (row) => row.approval_level ?? "-",
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
        <h1 className="text-2xl font-bold tracking-tight">Pengguna</h1>
        <Button onClick={handleOpenAdd}>
          <Plus className="mr-2 h-4 w-4" /> Tambah Pengguna
        </Button>
      </div>

      {error && (
        <div className="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-4 text-red-600">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span className="text-sm">{error}</span>
        </div>
      )}

      <Card>
        <CardContent className="p-0">
          <DataTable columns={columns} data={users} loading={loading} />
        </CardContent>
      </Card>

      <FormDialog
        open={dialogOpen}
        onOpenChange={handleDialogChange}
        title={editTarget ? "Edit Pengguna" : "Tambah Pengguna"}
      >
        <form onSubmit={handleFormSubmit} className="space-y-4">
          <InputField
            label="Nama"
            name="name"
            defaultValue={editTarget?.name}
            error={formErrors.name}
            placeholder="Misal: John Doe"
            required
          />
          <InputField
            label="Email"
            name="email"
            type="email"
            defaultValue={editTarget?.email}
            error={formErrors.email}
            placeholder="Misal: john@example.com"
            required
          />
          <div className="space-y-1">
            <InputField
              label="Password"
              name="password"
              type="password"
              error={formErrors.password}
              placeholder="Masukkan password"
              required={!editTarget}
            />
            {editTarget && (
              <p className="text-xs text-muted-foreground">
                Kosongkan jika tidak ingin mengubah password
              </p>
            )}
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Role</label>
            <Select value={formRole} onValueChange={setFormRole}>
              <SelectTrigger>
                <SelectValue placeholder="Pilih role" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="admin">Admin</SelectItem>
                <SelectItem value="approver">Approver</SelectItem>
              </SelectContent>
            </Select>
            {formErrors.role && (
              <p className="text-sm text-red-500">{formErrors.role}</p>
            )}
          </div>

          {formRole === "approver" && (
            <div className="space-y-2">
              <label className="text-sm font-medium">Level Approval</label>
              <Select
                value={formApprovalLevel}
                onValueChange={setFormApprovalLevel}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Pilih level approval" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="1">Level 1</SelectItem>
                  <SelectItem value="2">Level 2</SelectItem>
                </SelectContent>
              </Select>
              {formErrors.approval_level && (
                <p className="text-sm text-red-500">
                  {formErrors.approval_level}
                </p>
              )}
            </div>
          )}

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
        title="Hapus Pengguna"
        description={`Pengguna ${deleteTarget?.name} akan dihapus permanen.`}
        confirmText="Hapus"
        cancelText="Batal"
        onConfirm={handleConfirmDelete}
        loading={deleteLoading}
        variant="destructive"
      />
    </div>
  );
}
