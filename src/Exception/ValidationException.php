<?php 
namespace Oktaax\Exception;
class ValidationException extends \Exception {
    public function __construct(private $data) {}
    

}
; ?>