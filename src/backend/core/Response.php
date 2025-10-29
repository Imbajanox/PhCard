<?php

namespace Core;

/**
 * JSON Response Helper
 * Standardizes API responses
 */
class Response {
    /**
     * Send success response
     */
    public static function success($data = [], $message = '') {
        $response = ['success' => true];
        
        if (!empty($message)) {
            $response['message'] = $message;
        }
        
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        
        self::send($response);
    }
    
    /**
     * Send error response
     */
    public static function error($message, $code = 400) {
        http_response_code($code);
        self::send([
            'success' => false,
            'error' => $message
        ]);
    }
    
    /**
     * Send JSON response and exit
     */
    private static function send($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
