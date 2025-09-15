<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    //

     public  function index(Request $request){
        $state = $request->state;
     
        if ($state) {
            $cities = City::whereStateId($state)->orderBy('name')->select(['id', 'name'])->get();
        } else {
            // Return all cities when no state is specified (for registration form)
            $cities = City::orderBy('name')->select(['id', 'name'])->get();
        }

        return $cities;
    }
    
}
