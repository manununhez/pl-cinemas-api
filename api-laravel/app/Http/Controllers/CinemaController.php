<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Cinema;
use Validator;



class CinemaController extends BaseController
{
    public function index(){        
        $cinemas = Cinema::all();

        return $this->sendResponse($cinemas->toArray(), 'cinemas retrieved successfully.');
    }

     /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $input = $request->all();


        $validator = Validator::make($input, [
            'name' => 'required|string',
            'website' => 'required|string',
            'logo_url' => 'required|string',
        ]);


        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 400);       
        }


        $cinema = Cinema::create($input);

        if(is_null($cinema))
            return $this->sendError('Cinema could not be created', 500);
        else
            return $this->sendResponse($cinema->toArray(), 'Cinema created successfully.');
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        $cinema = Cinema::find($id);

    	if(!$cinema)
    		return $this->sendError('Cinema with id = '.$id.' not found.', 400);


    	$deleted = $cinema->delete();

    	if($deleted)
    		return $this->sendResponse($cinema->toArray(), 'Cinema deleted successfully.');
    	else
    		return $this->sendError('Cinema could not be deleted', 500);
    }
}