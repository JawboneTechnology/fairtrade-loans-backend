<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use League\Csv\Writer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Create an empty csv file
        $this->createEmptyCSV();
    }

    /**
     * Create an empty csv file.
     *
     * @return void
     */
    public function createEmptyCSV()
    {
        $filePath = storage_path('app/public/empty_employees.csv');

        // Ensure the directory exists
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        if (!file_exists($filePath)) {
            $csv = Writer::createFromString('');
            $csv->insertOne([
                'first_name', 'middle_name', 'last_name', 'phone_number', 'address',
                'dob', 'passport_image', 'gender', 'email', 'employee_id', 'salary'
            ]);

            file_put_contents($filePath, $csv->toString());
        }
    }
}
