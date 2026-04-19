// src/services/activityLogService.js
import api from "./api";

const activityLogService = {
  getActivityLogs: async (params = {}) => {
    const response = await api.get("/activity-logs", { params });
    return response.data;
  },
};

export default activityLogService;
