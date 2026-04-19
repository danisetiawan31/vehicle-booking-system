<?php
// app/Controllers/Api/ReportController.php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\AuthContext;
use CodeIgniter\Controller;
use Config\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ReportController extends Controller
{
    private function getAuthUser(): array
    {
        return AuthContext::get();
    }

    private function requireAdmin()
    {
        if ($this->getAuthUser()['role'] !== 'admin') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['status' => false, 'message' => 'Forbidden', 'data' => null]);
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Shared query builder
    // -------------------------------------------------------------------------
    private function buildQuery($db, string $startDate, string $endDate, ?string $status)
    {
        $builder = $db->table('bookings b')
            ->select('
                b.id, b.booking_code, b.requester_name,
                v.name AS vehicle_name, v.plate_number,
                d.name AS driver_name,
                b.destination, b.purpose,
                b.start_date, b.end_date, b.status,
                u_a1.name AS approver_l1_name, ba1.status AS approver_l1_status,
                u_a2.name AS approver_l2_name, ba2.status AS approver_l2_status,
                u.name AS admin_name,
                b.created_at
            ')
            ->join('vehicles v', 'v.id = b.vehicle_id')
            ->join('drivers d', 'd.id = b.driver_id')
            ->join('users u', 'u.id = b.admin_id')
            ->join('booking_approvals ba1', 'ba1.booking_id = b.id AND ba1.level = 1', 'left')
            ->join('users u_a1', 'u_a1.id = ba1.approver_id', 'left')
            ->join('booking_approvals ba2', 'ba2.booking_id = b.id AND ba2.level = 2', 'left')
            ->join('users u_a2', 'u_a2.id = ba2.approver_id', 'left')
            ->where('b.start_date >=', $startDate . ' 00:00:00')
            ->where('b.start_date <=', $endDate . ' 23:59:59')
            ->orderBy('b.created_at', 'ASC');

        if ($status !== null && $status !== '') {
            $builder->where('b.status', $status);
        }

        return $builder;
    }

    // -------------------------------------------------------------------------
    // index() — JSON report list
    // -------------------------------------------------------------------------
    public function index()
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $startDate = $this->request->getGet('start_date');
        $endDate   = $this->request->getGet('end_date');
        $status    = $this->request->getGet('status');

        $errors = [];
        if (empty($startDate)) $errors['start_date'] = 'start_date is required.';
        if (empty($endDate))   $errors['end_date']   = 'end_date is required.';

        if (!empty($errors)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => $errors,
                ]);
        }

        $db = Database::connect();
        $rows = $this->buildQuery($db, $startDate, $endDate, $status)->get()->getResultArray();

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Report data retrieved',
                'data'    => $rows,
            ]);
    }

    // -------------------------------------------------------------------------
    // export() — xlsx download
    // -------------------------------------------------------------------------
    public function export()
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $startDate = $this->request->getGet('start_date');
        $endDate   = $this->request->getGet('end_date');
        $status    = $this->request->getGet('status');

        $errors = [];
        if (empty($startDate)) $errors['start_date'] = 'start_date is required.';
        if (empty($endDate))   $errors['end_date']   = 'end_date is required.';

        if (!empty($errors)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => $errors,
                ]);
        }

        $db = Database::connect();
        $rows = $this->buildQuery($db, $startDate, $endDate, $status)->get()->getResultArray();

        // Build spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Pemesanan Kendaraan');

        $headers = [
            'No', 'Booking Code', 'Requester', 'Kendaraan', 'Plat Nomor', 'Driver',
            'Tujuan', 'Keperluan', 'Tgl Mulai', 'Tgl Selesai', 'Status',
            'Approver L1', 'Status L1', 'Approver L2', 'Status L2',
            'Dibuat Oleh', 'Dibuat Pada',
        ];

      // Header row
        foreach ($headers as $index => $header) {
            $col = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getCell($col . '1')->setValue($header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
        }

        // Data rows
        $rowNum = 2;
        $no = 1;
        foreach ($rows as $row) {
            $sheet->getCell('A' . $rowNum)->setValue($no++);
            $sheet->getCell('B' . $rowNum)->setValue($row['booking_code']);
            $sheet->getCell('C' . $rowNum)->setValue($row['requester_name']);
            $sheet->getCell('D' . $rowNum)->setValue($row['vehicle_name']);
            $sheet->getCell('E' . $rowNum)->setValue($row['plate_number']);
            $sheet->getCell('F' . $rowNum)->setValue($row['driver_name']);
            $sheet->getCell('G' . $rowNum)->setValue($row['destination']);
            $sheet->getCell('H' . $rowNum)->setValue($row['purpose']);
            $sheet->getCell('I' . $rowNum)->setValue($row['start_date']);
            $sheet->getCell('J' . $rowNum)->setValue($row['end_date']);
            $sheet->getCell('K' . $rowNum)->setValue($row['status']);
            $sheet->getCell('L' . $rowNum)->setValue($row['approver_l1_name'] ?? '');
            $sheet->getCell('M' . $rowNum)->setValue($row['approver_l1_status'] ?? '');
            $sheet->getCell('N' . $rowNum)->setValue($row['approver_l2_name'] ?? '');
            $sheet->getCell('O' . $rowNum)->setValue($row['approver_l2_status'] ?? '');
            $sheet->getCell('P' . $rowNum)->setValue($row['admin_name']);
            $sheet->getCell('Q' . $rowNum)->setValue($row['created_at']);
            $rowNum++;
        }

        // Auto-size all columns
        foreach (range(1, count($headers)) as $colIndex) {
            $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
        }

        // Stream output
        $filename = "report-bookings-{$startDate}-{$endDate}.xlsx";

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->setHeader('Cache-Control', 'max-age=0')
            ->setBody($content);
    }
}