<?php

/**
 * Created by PhpStorm.
 * User: Albert
 * Date: 23/2/2016
 * Time: 8:52
 */
namespace Kronhyx\fullCalendarBundle\Services;

use AppBundle\Entity\Afectacion;
use AppBundle\Entity\Empresa;
use AppBundle\Service\MailerService;
use Doctrine\Common\Persistence\ManagerRegistry;
use Kronhyx\fullCalendarBundle\Controller\CalendarController;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use AppBundle\Service\SoporteService;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

class CalendarManagerRegistry
{
    protected $managerRegistry;
    protected $container;
    protected $recipient;
    protected $manager;
    protected $dispatcher;

    public function __construct(ManagerRegistry $managerRegistry, Container $container, TraceableEventDispatcher $dispatcher)
    {
        $this->container = $container;
        $this->recipient = $this->container->getParameter( 'class_manager' );
        $this->managerRegistry = $managerRegistry;
        $this->manager = $this->managerRegistry->getManagerForClass($this->recipient);
        $this->dispatcher = $dispatcher;
    }

    public function getManager() {
        return $this->manager;
    }

    public function getEvents($dataFrom,$dataTo) {
        $qb = $this->manager->createQuery('
            SELECT c FROM '.$this->recipient.' c            
            WHERE c.startDatetime BETWEEN :firstDate AND :lastDate
            AND c.activo = :active
        ')
        ->setParameter('firstDate', $dataFrom)
        ->setParameter('lastDate', $dataTo)
        ->setParameter('active', true);

        return $qb->getResult();
    }


    /** Devuelve el disparador de evento del servicio
     * @return TraceableEventDispatcher
     */
    public function getDispatcher(){
        return $this->dispatcher;
    }

    public function getRepo(){
        return $this->manager->getRepository($this->recipient);
    }

    public function changeDate($newStartData,$newEndData,$id,$allDay) {
        $allDay = ($allDay === true || $allDay === 'true') ? true: false;
        /**
         * @var Afectacion $entity
         */
        $entity = $this->manager->getRepository($this->recipient)->find($id);
        $entity->setStartDatetime(new \DateTime($newStartData));
        $entity->setEndDatetime(new \DateTime($newEndData));
        if($entity->getAllDay() != $allDay)
            $entity->setAllDay($allDay);
		$auditoria = $entity->getAuditoria();
        $creador = (isset($auditoria))?$auditoria->getUsuario()->getNombre():"";
        //Instancia del objeto encargado de enviar correos.
        $mailer = $this->container->get(MailerService::class);
        foreach ($entity->getAfectados() as $afectado){
            $mailer->setNombre(CalendarController::DATO_CORREO)
                ->setAsunto(CalendarController::DATO_CORREO)
                ->setDestinatario($afectado->getCorreo())
                ->setCuerpo('Se ha reprogramado la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getMotivo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>')->persist();
            //Envio el correo a todos los afectados
            /*$this->container->get(SoporteService::class)->sendMail(
                $afectado->getCorreo(),
                'SISTEMA DE SOPORTE::AFECTACIÓN',
                [
                    'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                    'body'  =>
                        'Se ha reprogramado la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getMotivo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>'
                ]
            );*/
        }
        $mailer->send();
        $gEvent = new GenericEvent($entity);
        $this->dispatcher->dispatch('app.auditoria.instanciate', $gEvent);
        $this->save($entity);
   }

    /**
     * @param $title
     * @param $start
     * @param $end
     * @param $allDay
     * @param $color
     * @param $affected
     * @param $type
     * @param $desc
     * @param $notify
     * @return Afectacion
     */
    public function storeData($title, $start, $end, $allDay, $color, $affected, $type, $desc, $notify) {
        $parts = preg_split('/:/', $this->recipient);
        $className = $parts[0].'\\Entity\\'.$parts[1];
        $ref = new \ReflectionClass($className);
        /** @var Afectacion $entity */
        $entity = $ref->newInstance();
        $entity->setStartDatetime(new \DateTime($start));
        $entity->setAllDay($allDay);
        $entity->setBgColor($color);
        $entity->setEndDatetime(new \DateTime($end));
        $entity->setMotivo($this->manager->getRepository('AppBundle:Nomenclador')->find($type));
        $entity->setTitle($title);
        $entity->setActivo(true);
        $entity->setDescripcion($desc);


        $auditoria = $entity->getAuditoria();
        $creador = (isset($auditoria))?$auditoria->getUsuario()->getNombre():"";

        //Separo los ids pasados en un arreglo
        $ids = preg_split('/,/', $affected);
        //Instancia del objeto que se dedicara a enviar los correos electrónicos
        $mailer = $this->container->get(MailerService::class);
        //Voy creando una a una cada entrada a la tabla Afectado, según la cantidad de usuarios que se vean involucrados en la misma
        foreach ($ids as $id){
            $usuario = $this->manager->getRepository('AppBundle:Usuario')->find($id);
            $entity->addAfectado($usuario);
            $mailer->setNombre(CalendarController::DATO_CORREO)
                ->setAsunto(CalendarController::DATO_CORREO)
                ->setDestinatario($usuario->getCorreo())
                ->setCuerpo('Usted ha sido incluido en la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getMotivo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>')->persist();
            //Envio el correo a todos los afectados
            /*$this->container->get(SoporteService::class)->sendMail(
                $usuario->getCorreo(),
                'SISTEMA DE SOPORTE::AFECTACIÓN',
                [
                    'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                    'body'  =>
                        'Usted ha sido incluido en la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getMotivo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>'
                ]
            );*/
        }
        $mailer->send();
        $gEvent = new GenericEvent($entity);
        $this->dispatcher->dispatch('app.auditoria.instanciate', $gEvent);
        $this->save($entity);
        return $entity->getId();
    }

    public function removeData($id) {
        /**
         * @var Afectacion $entity
         */
        $entity = $this->manager->getRepository($this->recipient)->find($id);
        $auditoria = $entity->getAuditoria();
        $creador = (isset($auditoria))?$auditoria->getUsuario()->getNombre():"";
        /**
         * @var Afectado $afectado
         */
        foreach ($entity->getAfectados() as $afectado){
            $this->container->get(MailerService::class)
                ->setAsunto(CalendarController::DATO_CORREO)
                ->setNombre(CalendarController::DATO_CORREO)
                ->setDestinatario($afectado->getCorreo())
                ->setCuerpo('Se cancela la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getMotivo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>')->persist()->send();
            //Envio el correo a todos los afectados
            /*$this->container->get(SoporteService::class)->sendMail(
                $afectado->getCorreo(),
                'SISTEMA DE SOPORTE::AFECTACIÓN',
                [
                    'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                    'body'  =>
                        'Se cancela la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getMotivo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>'
                ]
            );*/
        }
        $entity->setActivo(false);

        $gEvent = new GenericEvent($entity, ['remove' => true]);
        $this->dispatcher->dispatch('app.auditoria.instanciate', $gEvent);
        $this->save($entity);
        //$this->remove($entity);
    }

    public function resizeEvent($newDate,$id) {
        /**
         * @var Afectacion $entity
         */
        $entity = $this->manager->getRepository($this->recipient)->find($id);
        $entity->setEndDatetime(new \DateTime($newDate));
		$auditoria = $entity->getAuditoria();
        $creador = (isset($auditoria))?$auditoria->getUsuario()->getNombre():"";
        //Instancia del objeto para enviar correos
        $mailer = $this->container->get(MailerService::class);
        foreach ($entity->getAfectados() as $afectado){
            $mailer->setNombre(CalendarController::DATO_CORREO)
                ->setAsunto(CalendarController::DATO_CORREO)
                ->setDestinatario($afectado->getCorreo())
                ->setCuerpo('Se h cambiado la duración de la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getMotivo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>')->persist();
            //Envio el correo a todos los afectados
            /*$this->container->get(SoporteService::class)->sendMail(
                $afectado->getCorreo(),
                'SISTEMA DE SOPORTE::AFECTACIÓN',
                [
                    'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                    'body'  =>
                        'Se h cambiado la duración de la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getMotivo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>'
                ]
            );*/
        }
        $mailer->send();
        $gEvent = new GenericEvent($entity);
        $this->dispatcher->dispatch('app.auditoria.instanciate', $gEvent);
        $this->save($entity);
   }

    public function serialize($elements) {
        $result = [];
        foreach ($elements as $element) {
            $event = $element->toArray();
            //Verificando que se pueda editar o no según la fecha de inicio
            $today = new \DateTime('today');
            if($today > $element->getStartDatetime())
                $event['editable'] = false;
            $result[] = $event;
        }
        //Añadiendo la zona donde no deben agregarse eventos
        $no_add = [
            'start'     => '1900-01-01',
            'end'       => (new \DateTime('today'))->format('Y-m-d'),
            'overlap'   => false,
            'rendering' => 'background',
            'color'     => '#ADB9CA'];
        $result[] = $no_add;
        return json_encode($result);
    }

    public function save($entity) {
        $this->manager->persist($entity);
        $this->manager->flush();
    }

    public function  remove($entity){
        $this->manager->remove($entity);
        $this->manager->flush();
    }
}