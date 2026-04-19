<?php
// app/Database/Seeds/BookingSeeder.php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Services\BookingService;
use CodeIgniter\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $bookingService = new BookingService();

        // Fetch required IDs from DB — never hardcode
        $adminId     = (int)$this->db->table('users')->where('email', 'admin@vehicle.com')->get()->getRow()->id;
        $approver1Id = (int)$this->db->table('users')->where('email', 'approver1@vehicle.com')->get()->getRow()->id;
        $approver2Id = (int)$this->db->table('users')->where('email', 'approver2@vehicle.com')->get()->getRow()->id;

        // Fetch available vehicles (only available status for realistic bookings)
        $vehicles = $this->db->table('vehicles')
            ->where('status', 'available')
            ->get()->getResultArray();

        // Fetch active drivers
        $drivers = $this->db->table('drivers')
            ->where('status', 'active')
            ->get()->getResultArray();

        // Helper to cycle through vehicles/drivers by index
        $vehicleCount = count($vehicles);
        $driverCount  = count($drivers);

        /**
         * Booking definitions.
         * Each entry: [status, monthOffset, vehicleIndex, driverIndex]
         * monthOffset: 0 = current month, -1 = last month, -2 = two months ago
         */
        $bookingDefs = [
            // 3 approved — current month
            ['approved',        0,  0, 0],
            ['approved',        0,  1, 1],
            ['approved',        0,  2, 2],
            // 2 waiting_level_1 — current month
            ['waiting_level_1', 0,  3, 0],
            ['waiting_level_1', 0,  0, 1],
            // 2 waiting_level_2 — current month
            ['waiting_level_2', 0,  1, 2],
            ['waiting_level_2', 0,  2, 0],
            // 2 rejected — current month
            ['rejected',        0,  3, 1],
            ['rejected',        0,  0, 2],
            // 1 approved — different month (for chart data)
            ['approved',        -2, 1, 0],
        ];

       foreach ($bookingDefs as $i => [$status, $monthOffset, $vIdx, $dIdx]) {
            $vehicle = $vehicles[$vIdx % $vehicleCount];
            $driver  = $drivers[$dIdx % $driverCount];

            // Stagger dates within the target month so no overlaps occur
            $dayOffset   = $i * 4;
            $targetMonth = (int)date('n') + $monthOffset;
            $targetYear  = (int)date('Y');

            if ($targetMonth <= 0) {
                $targetMonth += 12;
                $targetYear  -= 1;
            }

            $baseTs    = mktime(0, 0, 0, $targetMonth, 1 + $dayOffset, $targetYear);
            $startDate = date('Y-m-d H:i:s', $baseTs + 28800);         // +8h (08:00)
            $endDate   = date('Y-m-d H:i:s', $baseTs + 28800 + 86400); // +1 day (08:00 next day)
            $createdAt = date('Y-m-d H:i:s', $baseTs);

            $bookingCode = $bookingService->generateBookingCode();

            $this->db->table('bookings')->insert([
                'booking_code'   => $bookingCode,
                'admin_id'       => $adminId,
                'vehicle_id'     => (int)$vehicle['id'],
                'driver_id'      => (int)$driver['id'],
                'requester_name' => $this->sampleRequester($i),
                'purpose'        => $this->samplePurpose($i),
                'destination'    => $this->sampleDestination($i),
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'status'         => $status,
                'created_at'     => $createdAt,
            ]);

            $bookingId = $this->db->insertID();

            $this->insertApprovals(
                $bookingId,
                $status,
                $approver1Id,
                $approver2Id,
                $createdAt
            );
        }
    }

    // -------------------------------------------------------------------------
    private function insertApprovals(
        int $bookingId,
        string $status,
        int $approver1Id,
        int $approver2Id,
        string $createdAt
    ): void {
        $actedAt = date('Y-m-d H:i:s', strtotime($createdAt) + 3600); // 1h after booking created

        switch ($status) {
            case 'approved':
                // Both levels approved
                $this->db->table('booking_approvals')->insertBatch([
                    [
                        'booking_id'  => $bookingId,
                        'approver_id' => $approver1Id,
                        'level'       => 1,
                        'status'      => 'approved',
                        'notes'       => null,
                        'acted_at'    => $actedAt,
                    ],
                    [
                        'booking_id'  => $bookingId,
                        'approver_id' => $approver2Id,
                        'level'       => 2,
                        'status'      => 'approved',
                        'notes'       => null,
                        'acted_at'    => date('Y-m-d H:i:s', strtotime($actedAt) + 3600),
                    ],
                ]);
                break;

            case 'waiting_level_1':
                // Both levels pending
                $this->db->table('booking_approvals')->insertBatch([
                    [
                        'booking_id'  => $bookingId,
                        'approver_id' => $approver1Id,
                        'level'       => 1,
                        'status'      => 'pending',
                        'notes'       => null,
                        'acted_at'    => null,
                    ],
                    [
                        'booking_id'  => $bookingId,
                        'approver_id' => $approver2Id,
                        'level'       => 2,
                        'status'      => 'pending',
                        'notes'       => null,
                        'acted_at'    => null,
                    ],
                ]);
                break;

            case 'waiting_level_2':
                // Level 1 approved, level 2 pending
                $this->db->table('booking_approvals')->insertBatch([
                    [
                        'booking_id'  => $bookingId,
                        'approver_id' => $approver1Id,
                        'level'       => 1,
                        'status'      => 'approved',
                        'notes'       => null,
                        'acted_at'    => $actedAt,
                    ],
                    [
                        'booking_id'  => $bookingId,
                        'approver_id' => $approver2Id,
                        'level'       => 2,
                        'status'      => 'pending',
                        'notes'       => null,
                        'acted_at'    => null,
                    ],
                ]);
                break;

            case 'rejected':
                // Level 1 rejected, level 2 pending
                $this->db->table('booking_approvals')->insertBatch([
                    [
                        'booking_id'  => $bookingId,
                        'approver_id' => $approver1Id,
                        'level'       => 1,
                        'status'      => 'rejected',
                        'notes'       => 'Ditolak karena kendaraan dibutuhkan unit lain.',
                        'acted_at'    => $actedAt,
                    ],
                    [
                        'booking_id'  => $bookingId,
                        'approver_id' => $approver2Id,
                        'level'       => 2,
                        'status'      => 'pending',
                        'notes'       => null,
                        'acted_at'    => null,
                    ],
                ]);
                break;
        }
    }

    // -------------------------------------------------------------------------
    private function sampleRequester(int $i): string
    {
        $names = [
            'Dani Firmansyah', 'Rizky Hidayat', 'Susi Rahayu',
            'Joko Susilo', 'Rina Wulandari', 'Ahmad Fauzi',
            'Dewi Puspita', 'Fajar Nugroho', 'Lestari Ningsih', 'Wahyu Setiawan',
        ];
        return $names[$i % count($names)];
    }

    private function samplePurpose(int $i): string
    {
        $purposes = [
            'Kunjungan lapangan area tambang blok A',
            'Pengiriman material ke gudang utama',
            'Rapat koordinasi dengan kontraktor',
            'Inspeksi peralatan berat di site',
            'Pengambilan dokumen dari kantor pusat',
            'Survey lokasi proyek baru',
            'Pengiriman logistik ke basecamp',
            'Kunjungan ke rumah sakit mitra',
            'Pelatihan teknis di luar kota',
            'Perjalanan dinas ke kantor cabang',
        ];
        return $purposes[$i % count($purposes)];
    }

    private function sampleDestination(int $i): string
    {
        $destinations = [
            'Site Tambang Blok A, Morowali',
            'Gudang Utama Kendari',
            'Kantor Kontraktor, Makassar',
            'Workshop Peralatan, Kolaka',
            'Kantor Pusat Jakarta',
            'Lokasi Proyek Baru, Konawe',
            'Basecamp Karyawan, Morowali Utara',
            'RS Umum Kendari',
            'Hotel Grand Clarion, Makassar',
            'Kantor Cabang Palu',
        ];
        return $destinations[$i % count($destinations)];
    }
}