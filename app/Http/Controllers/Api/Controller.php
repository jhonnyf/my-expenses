<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Traits\ApiResponder;

abstract class Controller extends BaseController
{
    use ApiResponder;
}
