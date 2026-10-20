<?php

namespace Src\Support\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class SanteController
{
    public function __invoke(): JsonResponse
    {
        DB::connection()->select('select 1');

        return response()->json(['etat' => 'ok']);
    }
}
