<?php

namespace App\Traits;

/**
 * Trait for Error Reporting and Handling
 *
 */
trait Error {
    protected $errors = array(); // Array of errors, if any
    protected $has_errors = FALSE;
    protected $error_level = 0;
    protected $http_status = NULL;
    protected $default_http_status = 401;

    protected static $http_status_codes = [
        100 => "Continue", 
        101 => "Switching Protocols", 
        102 => "Processing", 
        200 => "OK", 
        201 => "Created", 
        202 => "Accepted", 
        203 => "Non-Authoritative Information", 
        204 => "No Content", 
        205 => "Reset Content", 
        206 => "Partial Content", 
        207 => "Multi-Status", 
        300 => "Multiple Choices", 
        301 => "Moved Permanently", 
        302 => "Found", 
        303 => "See Other", 
        304 => "Not Modified", 
        305 => "Use Proxy", 
        306 => "(Unused)", 
        307 => "Temporary Redirect", 
        308 => "Permanent Redirect", 
        400 => "Bad Request", 
        401 => "Unauthorized", 
        402 => "Payment Required", 
        403 => "Forbidden", 
        404 => "Not Found", 
        405 => "Method Not Allowed", 
        406 => "Not Acceptable", 
        407 => "Proxy Authentication Required", 
        408 => "Request Timeout", 
        409 => "Conflict", 
        410 => "Gone", 
        411 => "Length Required", 
        412 => "Precondition Failed", 
        413 => "Request Entity Too Large", 
        414 => "Request-URI Too Long", 
        415 => "Unsupported Media Type", 
        416 => "Requested Range Not Satisfiable", 
        417 => "Expectation Failed", 
        418 => "I'm a teapot", 
        419 => "Authentication Timeout", 
        420 => "Enhance Your Calm", 
        422 => "Unprocessable Entity", 
        423 => "Locked", 
        424 => "Failed Dependency", 
        424 => "Method Failure", 
        425 => "Unordered Collection", 
        426 => "Upgrade Required", 
        428 => "Precondition Required", 
        429 => "Too Many Requests", 
        431 => "Request Header Fields Too Large", 
        444 => "No Response", 
        449 => "Retry With", 
        450 => "Blocked by Windows Parental Controls", 
        451 => "Unavailable For Legal Reasons", 
        494 => "Request Header Too Large", 
        495 => "Cert Error", 
        496 => "No Cert", 
        497 => "HTTP to HTTPS", 
        499 => "Client Closed Request", 
        500 => "Internal Server Error", 
        501 => "Not Implemented", 
        502 => "Bad Gateway", 
        503 => "Service Unavailable", 
        504 => "Gateway Timeout", 
        505 => "HTTP Version Not Supported", 
        506 => "Variant Also Negotiates", 
        507 => "Insufficient Storage", 
        508 => "Loop Detected", 
        509 => "Bandwidth Limit Exceeded", 
        510 => "Not Extended", 
        511 => "Network Authentication Required", 
        598 => "Network read timeout error", 
        599 => "Network connect timeout error",
    ];

    /**
     * Error levels
     * 0 - (HTTP 200) - No error
     * 1 - Notice - message to the user, not nessessarily even an error
     * 2 - Warning
     * 3 - Non-fatal error
     * 4 - Fatal error
     */

    /**
     * Indicates if we have any errors
     * @return bool $has_errors
     */
    public function hasErrors() {
        return $this->has_errors;
    }

    /**
     * Returns an array of all error messages
     * @return array $errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Returns the HTTP status
     * If no error, returns 200
     * @return array $errors
     */
    public function getHttpStatus() {
        return $this->hasErrors() ? $this->http_status : 200;
    }

    /**
     * Returns the error level
     * @return int error_level
     */
    public function getErrorLevel() {
        return $this->error_level;
    }

    /**
     * Force set the error level
     * @param int $level
     */
    public function setErrorLevel($level) {
        $this->error_level = intval($level);
        return ($this->error_level) ? FALSE : TRUE;
    }

    /**
     * Clears out all errors
     */
    public function resetErrors() {
        $this->errors = array();
        $this->has_errors = FALSE;
        $this->error_level = 0;
        $this->http_status = 200;
    }

    /**
     * Adds an error
     * @param string $message
     * @param int $level
     * @param int $http_status - defaults to $this->default_http_status
     * @return bool FALSE
     */
    public function addError($message, $level = 1, $http_status = NULL, $unique = TRUE) {
        if(!in_array($message, $this->errors)) {
            $this->errors[] = $message;
        }

        if(!$http_status) {
            $http_status = $this->http_status ?: $this->default_http_status;
        }

        $this->has_errors  = TRUE;
        $this->error_level = max($this->error_level, $level);
        $this->http_status = $http_status;
        return FALSE;
    }

    /**
     * Adds multiple errors at once
     * @param array $errors
     */
    public function addErrors($errors, $level = 1, $http_status = NULL) {
        foreach($errors as $error) {
            $this->addError($error, $level, $http_status);
        }
    }

    public function addErrorByHttpStatus($code, $level = NULL) {
        if(!$code || $code == 200) {
            return FALSE; // Not an error
        }

        if(!$level) {
            $level = ($code >= 200 && $code <= 299) ? 1 : 5;
        }

        $msg = static::$http_status_codes[$code] ?: 'Unknown Status Code: ' . $code;
        return $this->addError($msg, $level, $code);
    }

}
