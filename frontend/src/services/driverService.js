// File: src/services/driverService.js
import api from "./api";

const driverService = {
  getDrivers: async (params = {}) => {
    const response = await api.get("/drivers", { params });
    return response.data;
  },
  createDriver: async (data) => {
    const response = await api.post("/drivers", data);
    return response.data;
  },
  updateDriver: async (id, data) => {
    const response = await api.put(`/drivers/${id}`, data);
    return response.data;
  },
  deleteDriver: async (id) => {
    const response = await api.delete(`/drivers/${id}`);
    return response.data;
  },
};

export default driverService;
