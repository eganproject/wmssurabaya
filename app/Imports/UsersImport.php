<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,array<string,mixed>> */
    public array $rows = [];

    /** @var array<int,string> */
    private array $requiredHeaders = [
        'name',
        'email',
        'password',
    ];

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File kosong',
            ]);
        }

        $first = $rows->first();
        $headersRaw = array_keys($first?->toArray() ?? []);
        $headers = array_map(fn ($h) => $this->normalizeKey((string) $h), $headersRaw);
        $missing = array_diff($this->requiredHeaders, $headers);
        if (!empty($missing)) {
            $detected = implode(', ', array_filter($headers));
            throw ValidationException::withMessages([
                'file' => 'Header wajib: Nama, Email, Password. '
                    .'Roles dan Divisi opsional. Pastikan header berada di baris pertama. '
                    .($detected !== '' ? 'Header terdeteksi: '.$detected : ''),
            ]);
        }

        $errors = [];
        $rowIndex = 1;
        $seenEmails = [];

        foreach ($rows as $row) {
            $rowIndex++;
            $rowData = $this->normalizeRow($row);
            $name = trim((string) ($rowData['name'] ?? ''));
            $email = strtolower(trim((string) ($rowData['email'] ?? '')));
            $password = (string) ($rowData['password'] ?? '');
            $roles = trim((string) ($rowData['roles'] ?? $rowData['role'] ?? ''));
            $divisi = trim((string) ($rowData['divisi'] ?? $rowData['divisi_id'] ?? ''));

            if ($name === '' || $email === '' || $password === '') {
                $errors[] = "Baris {$rowIndex}: Nama, Email, dan Password wajib diisi";
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris {$rowIndex}: Email tidak valid ({$email})";
                continue;
            }

            if (isset($seenEmails[$email])) {
                $errors[] = "Baris {$rowIndex}: Email duplikat ({$email})";
                continue;
            }
            $seenEmails[$email] = true;

            $this->rows[] = [
                'row' => $rowIndex,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'roles_raw' => $roles,
                'divisi_raw' => $divisi,
            ];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'file' => implode(' | ', array_slice($errors, 0, 5)),
            ]);
        }

        if (empty($this->rows)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }
    }

    private function normalizeRow($row): array
    {
        $data = [];
        foreach (($row instanceof Collection ? $row->toArray() : (array) $row) as $key => $value) {
            $normKey = $this->normalizeKey((string) $key);
            if ($normKey === '') {
                continue;
            }
            $data[$normKey] = $value;
        }
        return $data;
    }

    private function normalizeKey(string $key): string
    {
        $key = ltrim($key, "\xEF\xBB\xBF");
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        $key = mb_strtolower($key);
        $key = preg_replace('/[^\p{L}\p{N}]+/u', '_', $key);
        $key = trim($key, '_');

        return match ($key) {
            'nama', 'nama_user', 'user_name' => 'name',
            'role' => 'roles',
            'divisi_id' => 'divisi_id',
            'divisi' => 'divisi',
            default => $key,
        };
    }
}
