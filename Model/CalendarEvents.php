<?php
/**
 * Created by PhpStorm.
 * User: Albert
 * Date: 27/4/2016
 * Time: 17:32
 */

namespace Kronhyx\fullCalendarBundle\Model;


class CalendarEvents
{
    /**
     * @var string Title/label of the calendar event.
     */
    protected $title;

    /**
     * @var string URL Relative to current path.
     */
    protected $url;

    /**
     * @var string HTML color code for the bg color of the event label.
     */
    protected $bgColor;

    /**
     * @var string HTML color code for the foregorund color of the event label.
     */
    protected $fgColor;

    /**
     * @var string css class for the event label
     */
    protected $cssClass;

    /**
     * @var \DateTime DateTime object of the event start date/time.
     */
    protected $startDatetime;

    /**
     * @var \DateTime DateTime object of the event end date/time.
     */
    protected $endDatetime;

    /**
     * @var boolean Is this an all day event?
     */
    protected $allDay = false;

    public function __construct()
    {

    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }
    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setBgColor($color)
    {
        $this->bgColor = $color;
    }

    public function getBgColor()
    {
        return $this->bgColor;
    }

    public function setFgColor($color)
    {
        $this->fgColor = $color;
    }

    public function getFgColor()
    {
        return $this->fgColor;
    }

    public function setCssClass($class)
    {
        $this->cssClass = $class;
    }

    public function getCssClass()
    {
        return $this->cssClass;
    }

    public function setStartDatetime(\DateTime $start)
    {
        $this->startDatetime = $start;
    }

    public function getStartDatetime()
    {
        return $this->startDatetime;
    }

    public function setEndDatetime(\DateTime $end)
    {
        $this->endDatetime = $end;
    }

    public function getEndDatetime()
    {
        return $this->endDatetime;
    }

    public function setAllDay($allDay = false)
    {
        $this->allDay = (boolean) $allDay;
    }

    public function getAllDay()
    {
        return $this->allDay;
    }

    public function toArray()
    {
        return array(
            'id'               => $this->id,
            'title'            => $this->title,
            'start'            => $this->startDatetime->format("Y-m-d\TH:i:sP"),
            'end'              => $this->endDatetime->format("Y-m-d\TH:i:sP"),
            'url'              => $this->url,
            'backgroundColor'  => $this->bgColor,
            'borderColor'      => $this->bgColor,
            'textColor'        => $this->fgColor,
            'className'        => $this->cssClass,
            'allDay'           => $this->allDay
        );
    }

}