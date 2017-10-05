<?php

namespace Kronhyx\fullCalendarBundle\Twig;


class fullCalendarExtension extends \Twig_Extension
{
    public function getName() {
        return 'fullCalendar';
    }

    public function getFunctions()
    {

        return array(
            'fullCalendar' => new \Twig_SimpleFunction(
                'fullCalendar',
                array($this, 'fullCalendar'),
                array('is_safe' => array('html'))
            ),
        );
    }

    public function fullCalendar()
    {
        return "<div id='calendar-place'></div>";
    }
}