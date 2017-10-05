<?php
/**
 * Created by PhpStorm.
 * User: Albert
 * Date: 1/5/2016
 * Time: 19:25
 */

namespace Kronhyx\fullCalendarBundle\Model;

/*
* Creem una classe que el que volem es que es pugui instancia com a servei
* agafant com a paràmetre el parametre de nom de la classe
*/

class CalendarManagerEntity
{
    protected $class;

    function __construct($class)
    {
        $this->class = $class;
    }

    public function create() {
        return new $this->class;
    }

    public function getClass()
    {
        return $this->class;
    }
}