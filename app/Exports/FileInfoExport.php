<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FileInfoExport implements FromArray, WithHeadings
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
       // return ['Order Number', 'Order Date', 'GST Registration No', 'Address Line 1','Address Line 2', 'Address line 3', 'Address line 4', 'State/UT Code', 'Place of supply', 'Place of delivery', 'Invoice Number', 'Invoice Details' ,'Invoice Date', 'Attachment', 'Name P1', 'Unit Price P1', 'Qty P1' ,'Name P2', 'Unit Price P2', 'Qty P2' ,'Name P3' , 'Unit Price P3', 'Qty P3' ,'Name P4' , 'Unit Price P4', 'Qty P4'];
       return ['Invoice no', 'Email timestamp', 'Bill to GST', 'Attachment'];
       //return ['Invoice no', 'Invoice timestamp', 'Bill to GST', 'Attachment'];
    }
}
