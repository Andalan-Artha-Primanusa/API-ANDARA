<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class EmploymentLetterController
{
    private function generateLetter(
        Request $request,
        int $employeeId,
        string $type,
        string $title,
        string $view,
    ): JsonResponse {
        $employee = Employee::with(['user.profile', 'manager'])->findOrFail($employeeId);
        $profile = $employee->user->profile;
        $now = now();
        $filename = $type . '-' . $employee->id . '-' . $now->format('YmdHis') . '.pdf';

        $pdf = Pdf::loadView($view, [
            'employee' => $employee,
            'profile' => $profile,
            'date' => $now->toDateString(),
        ]);
        $pdfContent = $pdf->output();
        $storedPath = 'employee-documents/' . $employee->id . '/' . $filename;
        Storage::disk('public')->put($storedPath, $pdfContent);

        $existing = EmployeeDocument::where('employee_id', $employee->id)
            ->where('document_type', $type)
            ->first();
        if ($existing && $existing->file_path) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $document = EmployeeDocument::updateOrCreate(
            ['employee_id' => $employee->id, 'document_type' => $type],
            [
                'uploaded_by' => $request->user()->id,
                'title' => $title,
                'category' => 'letter',
                'status' => EmployeeDocument::STATUS_APPROVED,
                'file_name' => $filename,
                'file_path' => $storedPath,
                'file_mime' => 'application/pdf',
                'file_size' => strlen($pdfContent),
            ]
        );

        return ApiResponse::success($title . ' berhasil dibuat', $document->load(['employee.user.profile']), 201);
    }

    public function generateExperienceLetter(Request $request, int $employeeId): JsonResponse
    {
        return $this->generateLetter($request, $employeeId, 'experience_letter', 'Surat Pengalaman Kerja', 'pdf.experience-letter');
    }

    public function generateEmploymentLetter(Request $request, int $employeeId): JsonResponse
    {
        return $this->generateLetter($request, $employeeId, 'employment_letter', 'Surat Keterangan Bekerja', 'pdf.employment-letter');
    }
}

