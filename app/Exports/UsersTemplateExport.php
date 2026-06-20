<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class UsersTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    public function headings(): array
    {
        return [
            'Nama',
            'Email',
            'Password',
            'Roles',
            'Divisi',
        ];
    }

    public function array(): array
    {
        return [
            ['Admin Contoh', 'admin.contoh@example.com', 'password123', 'admin', 'Operasional'],
            ['User Contoh', 'user.contoh@example.com', 'password123', 'staff,gudang', 'Gudang'],
        ];
    }

    public function title(): string
    {
        return 'Template Users';
    }
}
