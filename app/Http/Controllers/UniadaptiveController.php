<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UniadaptiveController extends Controller
{
    /**
     * @param array|object|null $data
     * 
     * @return array
     */
    public function response($data = null)
    {
        $response = '';
        if ($data != null) {
            // dd($data);
            $response = ['ok' => true,  'data' => $data];
        } else {
            $response = ['ok' => true];
        }
        return $response;
    }


    /**
     * @param object|null $data
     * @param string $error
     * @param int $errorCode
     * 
     * @return array
     */
    public function errorResponse(object $data = null, $error = '', $errorCode = 0)
    {
        $response = '';
        if ($error != '') {
            if ($errorCode != 0) {
                $response = ['ok' => false,  'data' => ['error' => $error, 'error_code' => $errorCode]];
            } else {
                $response = ['ok' => false,  'data' => ['error' => $error]];
            }
        } else {
            $response = ['ok' => false,  'data' => ['error' => strtoupper($data->exception), 'error_code' => $data->errorcode], 'message' => $data->message];
        }
        return $response;
    }
}
