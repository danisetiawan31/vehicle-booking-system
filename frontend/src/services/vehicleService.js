// File: src/services/vehicleService.js
import api from "./api";

const vehicleService = {
  getVehicles: async (params = {}) => {
    const response = await api.get("/vehicles", { params });
    return response.data;
  },
  createVehicle: async (data) => {
    const response = await api.post("/vehicles", data);
    return response.data;
  },
  updateVehicle: async (id, data) => {
    const response = await api.put(`/vehicles/${id}`, data);
    return response.data;
  },
  deleteVehicle: async (id) => {
    const response = await api.delete(`/vehicles/${id}`);
    return response.data;
  },
};

export default vehicleService;
