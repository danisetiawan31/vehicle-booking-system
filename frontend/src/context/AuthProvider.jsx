// File: src/context/AuthProvider.jsx
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { AuthContext } from "./AuthContext";

export function AuthProvider({ children }) {
  const [auth, setAuth] = useState(() => {
    const storedToken = localStorage.getItem("token");
    const storedUser = localStorage.getItem("user");
    if (storedToken && storedUser) {
      try {
        const parsedUser = JSON.parse(storedUser);
        if (parsedUser && typeof parsedUser === "object") {
          return { token: storedToken, user: parsedUser };
        }
      } catch {
        // Corrupted JSON in localStorage
      }
    }
    // Desynchronized or missing state -> clear storage
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    return { token: null, user: null };
  });

  const { user, token } = auth;
  const navigate = useNavigate();

  const login = (userData, userToken) => {
    localStorage.setItem("token", userToken);
    localStorage.setItem("user", JSON.stringify(userData));
    setAuth({ token: userToken, user: userData });
  };

  const logout = () => {
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    setAuth({ token: null, user: null });
    navigate("/login");
  };

  return (
    <AuthContext.Provider value={{ user, token, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
