<?php

namespace App\Traits;

/**
 * Description of Error
 *
 */
trait Error {
    protected $errors = array(); // Array of errors, if any
    protected $has_errors = FALSE;
    protected $error_level = 0;
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
    }

    /**
     * Clears out all errors
     */
    public function resetErrors() {
        $this->errors = array();
        $this->has_errors = FALSE;
        $this->error_level = 0;
    }

    /**
     * Adds an error
     * @param string $message
     * @return bool FALSE
     */
    public function addError($message, $level = 1, $unique = TRUE) {
        if(!in_array($message, $this->errors)) {
            $this->errors[] = $message;
        }

        $this->has_errors = TRUE;
        $this->error_level = max($this->error_level, $level);
        return FALSE;
    }

    /**
     * Adds multiple errors at once
     * @param array $errors
     */
    public function addErrors($errors, $level = 1) {
        foreach($errors as $error) {
            $this->addError($error, $level);
        }
    }

}
