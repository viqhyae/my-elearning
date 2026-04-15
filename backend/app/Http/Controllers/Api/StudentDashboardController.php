<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DummyLmsData;
use Illuminate\Http\JsonResponse;

class StudentDashboardController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(DummyLmsData::studentDashboard());
    }
}
