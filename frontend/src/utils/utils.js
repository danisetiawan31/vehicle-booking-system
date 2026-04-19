// File: src/utils/utils.js

const DATE_LOCALE = 'id-ID';
const DATE_TZ = 'Asia/Jakarta';

function parseDate(dateStr) {
  if (!dateStr) return null;
  // Jika sudah ada offset/Z, parse langsung
  if (/Z|[+-]\d{2}:\d{2}$/.test(dateStr)) return new Date(dateStr);
  // Backend return WIB tanpa suffix — tambahkan +07:00
  return new Date(dateStr.replace(' ', 'T') + '+07:00');
}

/**
 * Format a date string to "DD MMM YYYY HH:mm" in WIB (Asia/Jakarta).
 */
export function formatDate(dateStr) {
  if (!dateStr) return '-';
  return new Intl.DateTimeFormat(DATE_LOCALE, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: DATE_TZ,
  }).format(parseDate(dateStr));
}

/**
 * Format a date string to "DD MMM YYYY" in WIB (Asia/Jakarta).
 */
export function formatDateShort(dateStr) {
  if (!dateStr) return '-';
  return new Intl.DateTimeFormat(DATE_LOCALE, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: DATE_TZ,
  }).format(parseDate(dateStr));
}

export function formatCurrentMonth() {
  return new Intl.DateTimeFormat(DATE_LOCALE, {
    month: 'long',
    year: 'numeric',
    timeZone: DATE_TZ,
  }).format(new Date());
}

/**
 * Returns a human-readable Indonesian label for a status.
 */
export function getStatusLabel(status) {
  const labels = {
    // Booking status
    waiting_level_1: 'Menunggu Approval L1',
    waiting_level_2: 'Menunggu Approval L2',
    approved: 'Disetujui',
    rejected: 'Ditolak',

    // Vehicle status
    available: 'Tersedia',
    maintenance: 'Perawatan',
    inactive: 'Tidak Aktif',

    // Driver status
    active: 'Aktif',

    // Approval record status
    pending: 'Menunggu',

    // Vehicle ownership
    own: 'Milik Sendiri',
    rental: 'Sewa',

    // Vehicle type
    passenger: 'Penumpang',
    cargo: 'Kargo',
  };
  return labels[status] ?? status;
}

/**
 * Formats a year/month pair into a short Indonesian month label, e.g. "Jan '25".
 */
export function formatMonthLabel(year, month) {
  const MONTHS_SHORT = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Ags","Sep","Okt","Nov","Des"];
  return `${MONTHS_SHORT[Number(month) - 1]} '${String(year).slice(2)}`;
}
