// File: src/services/bookingService.js
import api from "./api";

const bookingService = {
  getBookings: async (params = {}) => {
    const response = await api.get("/bookings", { params });
    return response.data;
  },
  getBookingById: async (id) => {
    const response = await api.get(`/bookings/${id}`);
    return response.data;
  },
  createBooking: async (data) => {
    const response = await api.post("/bookings", data);
    return response.data;
  },
  approveBooking: async (id, data = {}) => {
    const response = await api.post(`/bookings/${id}/approve`, data);
    return response.data;
  },
  rejectBooking: async (id, data) => {
    const response = await api.post(`/bookings/${id}/reject`, data);
    return response.data;
  },
};

export default bookingService;
