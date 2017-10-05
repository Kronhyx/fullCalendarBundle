# FullCalendar Bundle

We want to build a distribuible bundle for Symfony 2 that allow us to show events in the fullCalendar.js (http://fullcalendar.io/) library, calendar and scheduler.

## How to use it

Install
-------

```
php composer.phar require fados-produccions/full-calendar-bundle dev-master
```

register he bundle inthe appKernel.php

```php
   new Kronhyx\fullCalendarBundle\fullCalendarBundle(),
```

This bundle has a dependency on the FOSJsRouting bundle to expose the calendar AJAX event loader route. Please ensure that the [FOSJsRouting](https://github.com/FriendsOfSymfony/FOSJsRoutingBundle) bundle is installed and configured before continuing.



Usage
-----

Configure you config.yml

```
full_calendar:
     class_manager: AppBundle\Entity\CompanyEvents
```

In the config.yml you need to put mappings by this way

```
 orm:
        auto_generate_proxy_classes: "%kernel.debug%"
        entity_managers:
              default:
                mappings:
                  fullCalendarBundle: ~
```

or

```
orm:
        auto_generate_proxy_classes: "%kernel.debug%"
        naming_strategy: doctrine.orm.naming_strategy.underscore
        auto_mapping: true
```

depends on your configuration file config.yml

The class parameter contains the Entity that stores the events, this entity must extends from BaseEvent.
Create an entity:

``` php

namespace AppBundle\Entity;
 
use Doctrine\ORM\Mapping as ORM;
use Kronhyx\fullCalendarBundle\Entity\Event as BaseEvent;
 
/**
 * @ORM\Entity
 * @ORM\Table(name="companyEvents")
 */
class CompanyEvents extends BaseEvent
{
 /**
 * @var int
 *
 * @ORM\Column(name="id", type="integer")
 * @ORM\Id
 * @ORM\GeneratedValue(strategy="AUTO")
 */
 protected $id;
}
```
Execute from command line, this will create the entity for the calendar
```
php app/console doctrine:schema:update --force
```
Register the routing in `app/config/routing.yml`:

``` yml
# app/config/routing.yml

fados_fullcalendar:
    resource: "@fullCalendarBundle/Resources/config/routing.xml"
```

Publish the assets:

    $ php app/console assets:install web
Add the required stylesheet and javascripts to your layout:

Stylesheet:    
```
<link rel="stylesheet" href="{{ asset('bundles/fullcalendar/css/fullcalendar.min.css') }}" />
<link rel="stylesheet" href="{{ asset('bundles/fullcalendar/css/fullcalendar.print.css') }}" media="print" />
```    
Javascript:
```
<script type="text/javascript" src="{{ asset('js/jquery-1.8.1.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('bundles/fullcalendar/js/moment.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('bundles/fullcalendar/js/fullcalendar.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('bundles/fullcalendar/js/init.fullCalendar.js') }}"></script>
```    
Then, in the template where you wish to display the calendar, add the following twig:

```
{{ fullCalendar() }}
```   

Page sample:

```
{% extends 'base.html.twig' %}


{% block javascripts %}
    <script type="text/javascript" src="{{ asset('js/jquery-1.8.1.min.js') }}"></script>
    <script src="{{ asset('bundles/fosjsrouting/js/router.js') }}"></script>
    <script src="{{ path('fos_js_routing_js', {'callback': 'fos.Router.setData'}) }}"></script>
    <script type="text/javascript" src="{{ asset('bundles/fullcalendar/js/moment.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('bundles/fullcalendar/js/fullcalendar.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('bundles/fullcalendar/js/init.fullCalendar.js') }}"></script>
{% endblock %}

{% block body %}
    <div id="wrapper">
        <div id="container">
            <div id="welcome">
                <h1><span>Welcome to</span> Symfony {{ constant('Symfony\\Component\\HttpKernel\\Kernel::VERSION') }}</h1>
            </div>
        </div>
    </div>

    {{ fullCalendar() }}
{% endblock %}

{% block stylesheets %}
    <link rel="stylesheet" href="{{ asset('bundles/fullcalendar/css/fullcalendar.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('bundles/fullcalendar/css/fullcalendar.print.css') }}" media="print" />
{% endblock %}
```
## Calendar Javascript
 
 The file init.fullCalendar.js in the bundles/fullcalendar/js/ contains two routes, the fullcalendar_loadevents route that is triggered when the Calendar is loaded, fullcalendar_resizedate is triggered when resize event date and the fullcalendar_changedate that is trtiggered when a event is moved.
 
``` javascript
   events:
        {
            url:Routing.generate('fullcalendar_loadevents', { month: moment().format('MM'), year: moment().format('YYYY') }),
            color: 'blue',
            textColor:'white',
            error: function() {
                alert('Error receving events');
            }
        },
        eventDrop: function(event,delta,revertFunc) {
            var newData = event.start.format('YYYY-MM-DD');
            //var end = (event.end == null) ? start : event.end.format('YYYY-MM-DD');
            $.ajax({
                url: Routing.generate('fullcalendar_changedate'),
                data: { id: event.id, newDate: newData },
                type: 'POST',
                dataType: 'json',
                success: function(response){
                    console.log('ok');
                },
                error: function(e){
                    revertFunc();
                    alert('Error processing your request: '+e.responseText);
                }
            });

        },
        eventResize: function(event, delta, revertFunc) {

            var newData = event.end.format('YYYY-MM-DD');
            $.ajax({
                url: Routing.generate('fullcalendar_resizedate'),
                data: { id: event.id, newDate: newData },
                type: 'POST',
                dataType: 'json',
                success: function(response){
                    console.log('ok');
                },
                error: function(e){
                    revertFunc();
                    alert('Error processing your request: '+e.responseText);
                }
            });

        },
        eventClick: function(calEvent, jsEvent, view) {
            console.log('Event: ' + calEvent.title);
            console.log('Event: ' + calEvent.id);
        },
```

eventClick is the event triggered when you cluck and event.

You could overwrite this init.calendar.js to fit your needs.

## How to create a FullCalendar distribuible Bundle from scratch

Create Bundle
-------------
First of all we have to create a Symfony2 project, in our case we use 2.7 version.
```
php composer.phar create-project symfony/framework-standard-edition bundleFullCalendar "2.7.*
```
Once installed, we have to create a folder inside the vendor folder where we will build our fullcalendar bundle.
We will create in the path vendor/fadosProduccions/fullcalendarbundle/

After create it, we will create the Symfony2 folder structure following [coockbook best practices](http://symfony.com/doc/current/cookbook/bundles/best_practices.html).

Inside the DependencyInjection folder we create two files, fullCalendarExtension.php with all the configuration files that we have to load.

```php
<?php

namespace Kronhyx\fullCalendarBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;

class fullCalendarExtension extends Extension
{
 //Carreguem els serveis
 public function load(array $configs, ContainerBuilder $container)
 {
 $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
 $loader->load('services.yml');
 }
}
```
and Configuration.php file

```php
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('fullCalendar');
        $rootNode->
        children()
            ->scalarNode('class_manager')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
```

In this file we configure the parameters that we will need for the fullcalendarbundle, in this case we will configure the class_manager, this parameter is mandatory because is the entity that will be showed in the calendar, this entity must be an extension of baseEvent.
This configure.php represents this configuration in the config.yml
Configure you config.yml

```
full_calendar:
     class: appBundle/Entity/CompanyEvents
```

Finally we have to create fullcalendarbundle.php used for load the bundle:

```php
<?php

namespace Kronhyx\fullCalendarBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class fullcalendarbundle extends Bundle
{

}
```

Composer.json
-------------

The composer.json file should include the following metadata

```
{
  "name": "fados-produccions/full-calendar-bundle",
  "version":"1.0.0",
  "type": "symfony-bundle",
  "description": "FullCalendar integration in Symfony2",
  "keywords": ["fullCalendar"],
  "homepage": "https://github.com/fados-produccions/fullCalendarbundle",
  "license": "MIT",
  "authors": [
    {
      "name": "Albert Juhé",
      "email": "ajuhe@fadosProduccions.com",
      "homepage": "https://github.com/fados-produccions/fullCalendarbundle"
    }
  ],
 "minimum-stability": "dev",
 "require": {
        "symfony/twig-bundle": "~2.1|~3.0",
        "symfony/framework-bundle": "~2.1|~3.0",
        "friendsofsymfony/jsrouting-bundle": "~1.1",
        "doctrine/collections": ">=1.0"
  },
 "autoload": {
    "psr-0": { "fadosProduccions\\fullCalendarBundle\\": "" }
  },
  "target-dir":"fadosProduccions/fullCalendarBundle",
  "extra": {
        "branch-alias": {
            "dev-master": "1.0.x-dev"
        }
    }
}
```

Services
--------
Now we have to create a couple of services, fados.calendar.service manage the entity and the app.fados.twig_extension that allow us to use a twig extension component {{ fullCalendar() }}

```
services:
  fados.calendar.service:
       class:  Kronhyx\fullCalendarBundle\Services\CalendarManagerRegistry
       arguments: ["@doctrine","@service_container"]
  app.fados.twig_extension:
       class:  Kronhyx\fullCalendarBundle\Twig\fullCalendarExtension
       public: false
       tags:
         - { name: twig.extension }
```

Twig extension
--------------

Now we have to create the twig extension defined previously to use it inside twig {{ fullCalendar }}
Create the folder Twig, in this folder we will crate the extesion

```php
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
```

After this you can register it in the services.yml, an use it.
If you want to try it, without normal bundle install, you have to register the bundle first with

 ```
'fadosProduccions\\fullcalendarbundle\\' => array($vendorDir . '/fadosProduccions/fullcalendarbundle'),
```

in the file vendor\composer\autoload_psr4.php

Model and Entity
----------------
He have to create the amin entity

```php
<?php

namespace Kronhyx\fullCalendarBundle\Entity;

use Kronhyx\fullCalendarBundle\Model\CalendarEvents as baseCalendarEvent;

abstract class Event extends baseCalendarEvent
{
    /**
     * Convert calendar event details to an array
     *
     * @return array $event
     */

}
```
that extends from CalendarEvents, this model contains the basic properties for the event.

```php

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
```

finally we have to map it to the orm, the next file contains the database mapping for the CalendarEvent.
This file is in the Kronhyx\fullCalendarBundle\Resources\Config\doctrine\Event.orm.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping
        xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
        http://doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <mapped-superclass name="Kronhyx\fullCalendarBundle\Entity\Event">

        <field name="title" column="title" type="string" length="255" nullable="true"/>
        <field name="url" column="url" type="string" length="255" nullable="true"/>
        <field name="bgColor" column="bgColor" type="string" length="10" nullable="true"/>
        <field name="cssClass" column="cssClass" type="string" length="10" nullable="true"/>
        <field name="startDatetime" column="startDatetime" type="datetime" nullable="false"/>
        <field name="endDatetime" column="endDatetime" type="datetime" nullable="false"/>
        <field name="allDay" column="allDay" type="boolean"/>
    </mapped-superclass>
</doctrine-mapping>
```

This allow us to create and entity that extends to baseEvent with fields mapped to the database, this mapping is very important <mapped-superclass name="Kronhyx\fullCalendarBundle\Entity\Event">, bind the entity with the database mapping.

Before execute this:

```
php app/console doctrine:schema:update --force
```
test if you have in your config.yml the auto_mapping: true

```
orm:
        auto_generate_proxy_classes: "%kernel.debug%"
        naming_strategy: doctrine.orm.naming_strategy.underscore
        auto_mapping: true
```

the entity is created in the database with the fields mapped.

Routing
-------

The routing file stores the routes called for the init.fullCalendar.js
 - Routing.generate('fullcalendar_resizedate')
 - Routing.generate('fullcalendar_changedate')
 - Routing.generate('fullcalendar_loadevents', { month: moment().format('MM'), year: moment().format('YYYY') })


```xml
<?xml version="1.0" encoding="UTF-8" ?>

<routes xmlns="http://symfony.com/schema/routing"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://symfony.com/schema/routing http://symfony.com/schema/routing/routing-1.0.xsd">

    <route id="fullcalendar_loadevents" pattern="/fullCalendar/load">
        <default key="_controller">fullcalendarbundle:Calendar:load</default>
        <option key="expose">true</option>
    </route>
    <route id="fullcalendar_changedate" pattern="/fullCalendar/change">
        <default key="_controller">fullcalendarbundle:Calendar:change</default>
        <option key="expose">true</option>
    </route>
    <route id="fullcalendar_resizedate" pattern="/fullCalendar/resize">
        <default key="_controller">fullcalendarbundle:Calendar:resize</default>
        <option key="expose">true</option>
    </route>

</routes>
```

This routing.xml must be called from config.yml to use it:
Register the routing in `app/config/routing.yml`:

``` yml
# app/config/routing.yml

fados_fullcalendar:
    resource: "@fullcalendarbundle/Resources/config/routing.xml"
```

The routes called in this file uses the CalendarController, place where we define the action for each route.

Controller
----------
The main controller is Kronhyx\fullCalendarBundle\Controller\CalendarController

This controller uses the service fados.calendar.service to manage the entity.

```
services:
  fados.calendar.service:
       class:  Kronhyx\fullCalendarBundle\Services\CalendarManagerRegistry
       arguments: ["@doctrine","@service_container"]
```

the CalendarManagerRegistry receive two parameters:
- doctrine: [ManagerRegistry](http://php-and-symfony.matthiasnoback.nl/2014/05/inject-the-manager-registry-instead-of-the-entity-manager/) (Use when you don't know wich entitymanager needs the database entity)
- service_container: Container (For getting parameters, in this case class_manager)

```php
class CalendarManagerRegistry
{
    protected $managerRegistry;
    protected $container;
    protected $recipient;
    protected $manager;

    public function __construct(ManagerRegistry $managerRegistry, Container $container)
    {
        $this->container = $container;
        $this->recipient = $this->container->getParameter( 'class_manager' );
        $this->managerRegistry = $managerRegistry;
        $this->manager = $this->managerRegistry->getManagerForClass($this->recipient);

    }
```
Manager contains the entity manager for the class class_manager get it from the config.yml parameters.
Later we will use the manager properti to find or save entity in the database, for example:

```php
 public function changeDate($newStartData,$newEndData,$id) {
        $entity = $this->manager->getRepository($this->recipient)->find($id);
        $entity->setStartDatetime(new \DateTime($newStartData));
        $entity->setEndDatetime(new \DateTime($newEndData));
        $this->save($entity);
   }
```



