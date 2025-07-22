<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MainDashBoardStatsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([]);
    }
}
