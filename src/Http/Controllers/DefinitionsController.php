<?php

namespace Godrade\Trame\Http\Controllers;

use Godrade\Trame\Trame;
use Illuminate\Http\JsonResponse;

final class DefinitionsController
{
    public function __invoke(Trame $trame): JsonResponse
    {
        return response()->json($trame->definitions());
    }
}
