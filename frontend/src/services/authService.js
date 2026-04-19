// File: src/services/authService.js
import api from "@/services/api";

async function login(email, password) {
  const response = await api.post("/auth/login", { email, password });
  return response.data;
}

async function logout() {
  const response = await api.post("/auth/logout");
  return response.data;
}

export default { login, logout };
