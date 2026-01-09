<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use Illuminate\Http\Request;

class FlightController extends Controller
{
    public function index(Request $request)
    {
        $query = Flight::where('is_active', true)
            ->where('departure_time', '>', now());

        if ($request->has('departure_city')) {
            $query->where('departure_city', 'like', '%' . $request->departure_city . '%');
        }

        if ($request->has('arrival_city')) {
            $query->where('arrival_city', 'like', '%' . $request->arrival_city . '%');
        }

        if ($request->has('date')) {
            $query->whereDate('departure_time', $request->date);
        }

        if ($request->has('class')) {
            $query->where('class', $request->class);
        }

        $flights = $query->orderBy('departure_time', 'asc')->paginate(12);

        return response()->json($flights);
    }

    public function show(Flight $flight)
    {
        return response()->json($flight);
    }

    public function featured()
    {
        $flights = Flight::where('is_active', true)
            ->where('departure_time', '>', now())
            ->where('seats_available', '>', 0)
            ->orderBy('price', 'asc')
            ->limit(6)
            ->get();

        return response()->json($flights);
    }

    public function search(Request $request)
    {
        $query = Flight::where('is_active', true)
            ->where('departure_time', '>', now())
            ->where('seats_available', '>', 0);

        if ($request->has('from')) {
            $query->where('departure_city', 'like', '%' . $request->from . '%');
        }

        if ($request->has('to')) {
            $query->where('arrival_city', 'like', '%' . $request->to . '%');
        }

        if ($request->has('date')) {
            $query->whereDate('departure_time', $request->date);
        }

        if ($request->has('passengers')) {
            $query->where('seats_available', '>=', $request->passengers);
        }

        return response()->json($query->paginate(12));
    }
}
