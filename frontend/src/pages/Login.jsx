// File: src/pages/Login.jsx
import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/context/useAuth";
import authService from "@/services/authService";
import Button from "@/components/common/Button";
import InputField from "@/components/common/InputField";
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
} from "@/components/ui/card";

export default function Login() {
  const { token, login } = useAuth();
  const navigate = useNavigate();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [generalError, setGeneralError] = useState("");
  const [fieldErrors, setFieldErrors] = useState({});

  // Redirect if already authenticated
  useEffect(() => {
    if (token) navigate("/", { replace: true });
  }, [token, navigate]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setGeneralError("");
    setFieldErrors({});

    try {
      const data = await authService.login(email, password);
      login(data.data.user, data.data.token);
      navigate("/");
    } catch (err) {
      const response = err.response?.data;
      if (response?.errors && typeof response.errors === "object") {
        // Field-level validation errors from the API
        setFieldErrors(response.errors);
      } else {
        // General error (wrong credentials, server error, etc.)
        setGeneralError(response?.message ?? "Terjadi kesalahan. Silakan coba lagi.");
      }
    } finally {
      setLoading(false);
    }
  };

  if (token) return null;

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
      <div className="w-full max-w-sm">
        {/* App title */}
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-bold tracking-tight">Sistem Pemesanan Kendaraan</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Masuk untuk melanjutkan
          </p>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Masuk</CardTitle>
            <CardDescription>Gunakan akun yang telah diberikan administrator.</CardDescription>
          </CardHeader>

          <CardContent>
            <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
              <InputField
                id="email"
                label="Email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="contoh@email.com"
                autoComplete="email"
                required
                error={fieldErrors.email}
              />

              <InputField
                id="password"
                label="Password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                autoComplete="current-password"
                required
                error={fieldErrors.password}
              />

              {/* General error — shown above the button */}
              {generalError && (
                <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
                  {generalError}
                </p>
              )}

              <Button type="submit" loading={loading} className="w-full">
                Masuk
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
