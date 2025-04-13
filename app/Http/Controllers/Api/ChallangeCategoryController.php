<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChallangeCategory;
use Illuminate\Http\Request;

class ChallangeCategoryController extends Controller
{
    /**
     * Get all challenge categories.
     */
    public function index()
    {
        $categories = ChallangeCategory::all();

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Get a specific challenge category by UUID.
     */
    public function show($uuid)
    {
        $category = ChallangeCategory::where('uuid', $uuid)
            ->first();
        return response()->json([
            'status' => 'success',
            'data' => $category
        ]);
    }
} 