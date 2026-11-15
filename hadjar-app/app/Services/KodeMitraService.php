<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KodeMitraService
{
    public function generateNext(): string
    {
        return DB::transaction(function (): string {
            $codes = User::query()
                ->lockForUpdate()
                ->pluck('kode_mitra');

            $maxNumeric = 1;

            foreach ($codes as $code) {
                if ( is_string($code) && preg_match('/^\d{3}$/', $code)) {
                    $value = (int) $code;
                    if ($value > $maxNumeric) {
                        $maxNumeric = $value;
                    }
                }
            }

            $next = $maxNumeric + 1;

            if ($next > 999) {
                throw new RuntimeException('Maximum kode mitra (999) has been reached.');
            }

            return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
        });
    }
}
