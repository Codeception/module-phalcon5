<?php

namespace App\Controllers;

use Phalcon\Mvc\Controller;

class DatetimeController extends Controller
{
    public function indexAction()
    {
        echo "class: " . get_class($this->getDI()->get('datetime'));
    }

    public function splAction()
    {
        echo spl_object_hash($this->getDI());
    }
}
