<?php 
namespace Oktaax\Http\Support;

use ArrayObject;

class RequestBody extends ArrayObject{

    public function __construct(array|object $body = []) {
        parent::__construct($body,ArrayObject::ARRAY_AS_PROPS);

    }
};
 ?>