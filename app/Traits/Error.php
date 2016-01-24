<?php

namespace App\Traits;

/**
 * Description of Error
 *
 */
trait Error {
    protected $errors = array(); // Array of errors, if any
    protected $has_errors = FALSE;
    
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
     * Clears out all errors
     */
    public function resetErrors() {
        $this->errors = array();
        $this->has_errors = FALSE;
    }
    
    /**
     * Adds an error 
     * @param string $message
     */
    protected function addError($message) {
        $this->errors[] = $message;
        $this->has_errors = TRUE;
    }
    
    /**
     * Adds multiple errors at once
     * @param array $errors
     */
    protected function addErrors($errors) {
        foreach($errors as $error) {
            $this->addError($message);
        }
    }
    
}
