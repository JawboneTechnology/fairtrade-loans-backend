<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use League\Csv\Reader;
use League\Csv\Writer;
use Illuminate\Support\Facades\Storage;

class CsvController extends Controller
{
    // Handle create empty csv
    public function handleCreateEmptyEmployeeCSV(Request $request): \Illuminate\Http\JsonResponse
    {
        // Create a CSV file with headers
        $csv = Writer::createFromString('');
        $csv->insertOne([
            'first_name', 'middle_name', 'last_name', 'phone_number', 'address',
            'dob', 'passport_image', 'gender', 'email', 'employee_id', 'salary'
        ]);

        // Save the CSV file to storage
        $filePath = storage_path('app/public/empty_employees.csv');

        // Ensure the directory exists
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents($filePath, $csv->toString());

        return response()->json([
            'message' => 'Empty CSV file created successfully.',
            'path' => $filePath
        ]);
    }

    public function downloadEmptyCSV(): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        $filePath = storage_path('app/public/empty_employees.csv');

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'Empty CSV file not found.'], 404);
        }

        return response()->download($filePath, 'empty_employees.csv');
    }

    public function uploadEmployeeCSV(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $filePath = $file->storeAs('uploads', 'uploaded_employees.csv');

        $csv = Reader::createFromPath(storage_path('app/' . $filePath), 'r');
        $csv->setHeaderOffset(0);

        foreach ($csv as $record) {
            User::create([
                'first_name' => $record['first_name'],
                'middle_name' => $record['middle_name'],
                'last_name' => $record['last_name'],
                'phone_number' => $record['phone_number'],
                'address' => $record['address'],
                'dob' => $record['dob'],
                'passport_image' => $record['passport_image'],
                'gender' => $record['gender'],
                'email' => $record['email'],
                'employee_id' => $record['employee_id'],
                'salary' => $record['salary'],
            ]);
        }

        return response()->json(['message' => 'CSV data uploaded and processed successfully.']);
    }
}
