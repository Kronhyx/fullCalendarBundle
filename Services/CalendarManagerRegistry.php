<?php

/**
 * Created by PhpStorm.
 * User: Albert
 * Date: 23/2/2016
 * Time: 8:52
 */
namespace fadosProduccions\fullCalendarBundle\Services;

use CoreBundle\Entity\Afectacion;
use CoreBundle\Entity\Empresa;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\User\User;
use CoreBundle\Entity\Afectado;

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

    public function getManager() {
        return $this->manager;
    }

    public function getEvents($dataFrom,$dataTo) {
        $qb = $this->manager->createQuery('
            SELECT c FROM '.$this->recipient.' c            
            WHERE c.startDatetime BETWEEN :firstDate AND :lastDate
        ')
        ->setParameter('firstDate', $dataFrom)
        ->setParameter('lastDate', $dataTo);

        return $qb->getResult();
    }

    public function getRepo(){
        return $this->manager->getRepository($this->recipient);
    }

    public function changeDate($newStartData,$newEndData,$id) {
        /**
         * @var Afectacion $entity
         */
        $entity = $this->manager->getRepository($this->recipient)->find($id);
        $entity->setStartDatetime(new \DateTime($newStartData));
        $entity->setEndDatetime(new \DateTime($newEndData));
		$auditoria = $entity->getAuditoria();
        $creador = $auditoria->getUsuario()->getNombre();
        foreach ($entity->getAfectados() as $afectado){
            //Envio el correo a todos los afectados
            $this->container->get('soporte')->sendMail(
                $afectado->getUsuario()->getCorreo(),
                'SISTEMA DE SOPORTE::AFECTACIÓN',
                [
                    'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                    'body'  =>
                        'Se ha reprogramado la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getTipo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>'
                ]
            );
        }
        $this->save($entity);
   }

    public function storeData($title, $start, $end, $allDay, $color, $affected, $type, $notify) {
        $parts = preg_split('/:/', $this->recipient);
        $className = $parts[0].'\\Entity\\'.$parts[1];
        $ref = new \ReflectionClass($className);
        $entity = $ref->newInstance();
        $entity->setStartDatetime(new \DateTime($start));
        $entity->setAllDay($allDay);
        $entity->setBgColor($color);
        $entity->setEndDatetime(new \DateTime($end));
        $entity->setTipo($this->manager->getRepository('CoreBundle:Motivo')->find($type));
        $entity->setTitle($title);

        $this->save($entity);

        $auditoria = $entity->getAuditoria();
        $creador = $auditoria->getUsuario()->getNombre();

        //Separo los ids pasados en un arreglo
        $ids = preg_split('/,/', $affected);
        //Voy creando una a una cada entrada a la tabla Afectado, según la cantidad de usuarios que se vean involucrados en la misma
        foreach ($ids as $id){
            $afectado = new Afectado();
            $afectado->setAfectacion($entity);
            $usuario = $this->manager->getRepository('CoreBundle:Usuario')->find($id);
            //Envio el correo a todos los afectados
            $this->container->get('soporte')->sendMail(
                $usuario->getCorreo(),
                'SISTEMA DE SOPORTE::AFECTACIÓN',
                [
                    'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                    'body'  =>
                        'Usted ha sido incluido en la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getTipo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>'
                ]
            );
            $afectado->setUsuario($usuario);
            $this->save($afectado);
        }

        return $entity->getId();
    }

    public function removeData($id) {
        /**
         * @var Afectacion $entity
         */
        $entity = $this->manager->getRepository($this->recipient)->find($id);
        $auditoria = $entity->getAuditoria();
        $creador = $auditoria->getUsuario()->getNombre();
        /**
         * @var Afectado $afectado
         */
        foreach ($entity->getAfectados() as $afectado){
            //Envio el correo a todos los afectados
            $this->container->get('soporte')->sendMail(
                $afectado->getUsuario()->getCorreo(),
                'SISTEMA DE SOPORTE::AFECTACIÓN',
                [
                    'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                    'body'  =>
                        'Se cancela la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getTipo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>'
                ]
            );
        }
        $this->save($entity);
    }

    public function resizeEvent($newDate,$id) {
        /**
         * @var Afectacion $entity
         */
        $entity = $this->manager->getRepository($this->recipient)->find($id);
        $entity->setEndDatetime(new \DateTime($newDate));
		$auditoria = $entity->getAuditoria();
        $creador = $auditoria->getUsuario()->getNombre();
        foreach ($entity->getAfectados() as $afectado){
            //Envio el correo a todos los afectados
            $this->container->get('soporte')->sendMail(
                $afectado->getUsuario()->getCorreo(),
                'SISTEMA DE SOPORTE::AFECTACIÓN',
                [
                    'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                    'body'  =>
                        'Se h cambiado la duración de la Afectación: <br />
                        <b>Asunto:</b> '.$entity->getTitle().'<br />
                        <b>Tipo:</b> '.$entity->getTipo()->getNombre().'<br />
                        <b>Horario:</b>'.($entity->getAllDay()?" Todo el día":" Del ".($entity->getStartDatetime()->format('d-m-Y H:i').' al '.$entity->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>'
                ]
            );
        }
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
        /*{
            start: '2016-01-24',
            end: '2016-01-28',
            overlap: false,
            rendering: 'background',
            color: '#ff9f89'
        }*/
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
}