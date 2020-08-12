<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;

/**
 * This class should be parent class for other API controllers
 * Class AppBaseController
 */
class BaseController extends Controller
{
	/**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendResponse($result, $message, $code = 200)
    {
        $response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];


        return response()->json($response, $code);
    }

	/**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendError($error = 'Error', $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'data' => $error,
        ];


        if(!empty($errorMessages)){
            $response['message'] = $errorMessages;
        }


        return response()->json($response, $code);
    }
}
