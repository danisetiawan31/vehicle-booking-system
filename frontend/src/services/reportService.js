// File: src/services/reportService.js
import api from "./api";

const reportService = {
  getReports: async (params) => {
    const response = await api.get("/reports", { params });
    return response.data;
  },
  exportReports: async (params) => {
    const response = await api.get("/reports/export", {
      params,
      responseType: "blob",
    });
    return response.data;
  },
};

export default reportService;
