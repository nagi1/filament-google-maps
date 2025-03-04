<?php

namespace Cheesegrits\FilamentGoogleMaps\Commands;

use Cheesegrits\FilamentGoogleMaps\Helpers\Geocoder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

use function Laravel\Prompts\text;

class GeocodeTable extends Command
{
    protected $signature = 'filament-google-maps:geocode-table {model?} {--lat=} {--lng=} {--fields=} {--processed=} {--rate-limit=} {--verbose?}}';

    protected $description = 'Geocode a table';

    public function handle()
    {
        $verbose = $this->option('verbose');

        $prompted = false;
        $verbose  = $this->option('verbose');

        $ogModelName = $modelName = (string) Str::of($this->argument('model')
            ?? text(label: 'Model (e.g. `Location` or `Maps/Dealership`)', placeholder: 'Location', required: true))
            ->studly()
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->studly()
            ->replace('/', '\\');

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $model = new $modelName;
        } catch (Throwable $e) {
            try {
                /** @noinspection PhpUnusedLocalVariableInspection */
                $model     = new ('\\App\\Models\\' . $modelName)();
                $modelName = '\\App\\Models\\' . $modelName;
            } catch (Throwable $e) {
                echo "Can't find class $modelName or \\App\\Models\\$modelName\n";

                return static::INVALID;
            }
        }

        $fields = $this->option('fields');

        if (empty($fields)) {
            $prompted = true;

            $fields = text(
                label: 'Comma separated list of fields to concatenate for the address (e.g. `address,city,state`)',
                placeholder: 'address,city,state',
                required: true
            );
        }

        $rateLimit = (int) $this->option('rate-limit');

        while ($rateLimit > 300 || $rateLimit < 1) {
            $prompted = true;

            $rateLimit = (int) text(
                label: 'Rate limit as API calls per minute (max 300)',
                placeholder: '150',
                default: config('filament-google-maps.rate-limit', 150),
                required: true
            );
        }

        $geocoder = new Geocoder($rateLimit);

        $lat = $this->option('lat');

        if (empty($lat)) {
            $prompted = true;

            $lat = text(
                label: 'Name of latitude element on table (e.g. `latitude`)',
                placeholder: 'lat',
                required: true
            );
        }

        $lng = $this->option('lng');

        if (empty($lng)) {
            $prompted = true;

            $lng = text(
                label: 'Name of longitude element on table (e.g. `longitude`)',
                placeholder: 'lng',
                required: true
            );
        }

        $processedField = $this->option('processed');

        if (empty($processedField)) {
            $prompted = true;

            $processedField = text(
                label: 'Optional name of field to set to 1 when record is processed (e.g. `processed`)',
                placeholder: 'processed'
            );
        }

        if (empty($processedField) || $processedField === 'no-processed-field') {
            $processedField = null;
        }

        [$records, $processed, $updated] = $geocoder->geocodeBatch($modelName, $lat, $lng, $fields, $processedField, null, $verbose);

        $this->info('Results');
        $this->line('API Lookups: ' . $processed);
        $this->line('Records Updated: ' . $updated);

        if ($prompted) {
            $summary = sprintf(
                'php artisan filament-google-maps:geocode-table %s --fields=%s --lat=%s --lng=%s --processed=%s --rate-limit=%s',
                $ogModelName,
                $fields,
                $lat,
                $lng,
                $processedField ? $processedField : 'no-processed-field',
                $rateLimit
            );
            $this->newLine();
            $this->info('Command summary - you may wish to copy and save this somewhere!');
            $this->line($summary);
        }

        return static::SUCCESS;
    }
}
