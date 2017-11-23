<?php

namespace Kronhyx\fullCalendarBundle\Controller;

use AppBundle\Entity\Afectacion;
use AppBundle\Entity\Usuario;
use AppBundle\Service\MailerService;
use AppBundle\Service\SoporteService;
use Kronhyx\AuditoriaBundle\Entity\Auditoria;
use Kronhyx\fullCalendarBundle\Services\CalendarManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CalendarController extends Controller
{
    /** @var  CalendarManagerRegistry $manager */
    private $manager;

    //Dato mostrado en el titulo de los correos enviados por el sistema
    const DATO_CORREO = 'SISTEMA DE SOPORTE::AFECTACIÓN';
    const USUARIO_XDEFECTO = 'SISTEMA DE SOPORTE';

    function loadAction(Request $request) {
        //Get start date
        $createdAt = $request->get('start');
        $endAt = $request->get('end');
        $dataFrom = new \DateTime($createdAt);
        $dataTo = new \DateTime($endAt);

        //Get entityManager
        $manager = $this->get('fados.calendar.service');

        $userId = $request->get('userId', 0);
        $full = $request->get('full', false);
        $em = $this->get('doctrine.orm.entity_manager');
        if($userId != 0){
           $user = $em->find('AppBundle:Usuario', $userId);
           $events = $em->getRepository('AppBundle:Afectacion')->getAfectacionesByUsuario($user, $full);
        }
        else{
            $events = $manager->getEvents($dataFrom,$dataTo);
        }

        $status = empty($events) ? Response::HTTP_NO_CONTENT : Response::HTTP_OK;
        $jsonContent = $manager->serialize($events);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent($jsonContent);
        //Si remuevo esto y pongo $status no se va a crear la regla ke impide ke se agreguen eventos en fechas anteriores al dia actual
        $response->setStatusCode(Response::HTTP_OK);
        return $response;
    }

    public function viewAction(Request $request){
        $id = $request->get('id');
        $repo = $this->get('fados.calendar.service')->getRepo();
        /** @var Afectacion $evento */
        $evento = $repo->find($id);
        $afectados = $evento->getAfectados();
        $ids = '';
        foreach ($afectados as $afectado)
            $ids .= $afectado->getId().',';
        $tipo = $evento->getMotivo();
        $dato_evento = array(
            'title' => $evento->getTitle(),
            'type' => (isset($tipo))?$evento->getMotivo()->getId():null,
            'desc' => $evento->getDescripcion(),
            'affected' => trim($ids,','),
            'start' => $evento->getStartDatetime(),
            'end'   => $evento->getEndDatetime(),
            'allDay' => $evento->getAllDay()
        );
        return new Response(json_encode($dato_evento));
    }

    public function updateAction(Request $request){
        $this->manager = $this->container->get('fados.calendar.service');
        $em = $this->getDoctrine()->getManager();
        $repo = $this->manager->getRepo();
        /**
         * @var Afectacion $event
         */
        $event = $repo->find($request->get('id'));

        /** @var Auditoria $auditoria */
		$auditoria = $event->getAuditoria();
        $creador = (isset($auditoria))?(is_object($auditoria->getUser()))?$auditoria->getUser()->getNombre():self::USUARIO_XDEFECTO : self::USUARIO_XDEFECTO;
		
        $title = $request->get('title');
        $desc = $request->get('desc');
        $type = $request->get('type');
        $affected = $request->get('affected');
        $start = $request->get('start', null);
        $end = $request->get('end', null);
        if(isset($start, $end))
            $allDay = (strpos($start,' 00:00') !== false && strpos($end,' 00:00') !== false) ? 1 : 0;

        $motivo = $this->getDoctrine()->getRepository('AppBundle:Nomenclador')->find($type);
        if(isset($start, $end, $allDay)){
            $event->setStartDatetime(\DateTime::createFromFormat("d-m-Y H:i:s", $start));
            $event->setEndDatetime(\DateTime::createFromFormat("d-m-Y H:i:s", $end));
            $event->setAllDay($allDay);
        }

        //Va indicando los cambios que se han ido haciendo en esta actualización para poder enviarlo por correo
        $cambios = '';

        if($event->getTitle() != $title){
            $cambios .= 'El <b>Asunto:</b> <i>'.$event->getTitle().'<i> por '.$title;
            $event->setTitle($title);
        }
        if($event->getMotivo()->getId() != $type){
            $cambios .= '<br />El <b>Tipo:</b> <i>'.$event->getMotivo()->getNombre().'<i> por '.$motivo->getNombre();
            $event->setMotivo($motivo);
            $event->setBgColor($motivo->getJson('color'));
        }
        if($event->getDescripcion() != $desc/* && $event->getDescripcion() !== null*/){
            //Si no se ha asignado una descripcion
            if($event->getDescripcion() == null || trim(strtolower($event->getDescripcion())) == 0)
                $cambios .= '<br />La <b>Descripción>:</b> ha sido asignada '.$desc;
            else
                $cambios .= '<br />La <b>Descripción:</b> <i>'.$event->getDescripcion().'<i> por '.$desc;
            $event->setDescripcion($desc);
        }
        $ids = preg_split('/,/',$affected);
        //Listado de los usuarios afectados en el evento almacenado en la BD
        $afectados = $event->getAfectados();
        //Arreglo de los ids de los afectados
        $bd_afectados = array();
        //Recorro el listado para ir agregando los ids de los afectados
        foreach ($afectados as $afectado){
            if(isset($afectado))
                $bd_afectados[] = $afectado->getId();
        }
        $todos = array_merge($ids, $bd_afectados);
        $a_eliminar = array_diff($todos, $ids);
        $a_ingresar = array_diff($todos, $bd_afectados);
        $se_mantienen = array_intersect($ids, $bd_afectados);
        //Instancia del objeto destinado a enviar correos
        $mailer = $this->get(MailerService::class);
        //Recorro el arreglo de los afectados que luego de la modificación aun se mantienen
        foreach($se_mantienen as $id_mantiene){
            /**
             * @var Usuario $se_mantiene
             */
            $se_mantiene = $this->getDoctrine()->getRepository('AppBundle:Usuario')->find($id_mantiene);
            $mailer->setNombre(self::DATO_CORREO)
                ->setAsunto(self::DATO_CORREO)
                ->setDestinatario($se_mantiene->getCorreo())
                ->setCuerpo('Se ha modificado la Afectación: <br />
                        <b>Asunto:</b> '.$event->getTitle().'<br />
                        <b>Cambios:</b><br />'.$cambios.'
                        <b>Por '.$creador.'</b>')->persist();
        }
        $mailer->send();
        //Recorro el listado para ir eliminando los afectados que ya no stan
        foreach ($afectados as $afectado)
        {
            //Si existe el usuario del afectado y esta en la lista de los que se desea eliminar
            if(isset($afectado) && array_search($afectado->getId(), $a_eliminar) !== false)
            {
                $mailer->setNombre(self::DATO_CORREO)
                    ->setAsunto(self::DATO_CORREO)
                    ->setDestinatario($afectado->getCorreo())
                    ->setCuerpo('Se ha cancelado la Afectación: <br />
                        <b>Asunto:</b> '.$event->getTitle().'<br />
                        <b>Tipo:</b> '.$event->getMotivo()->getNombre().'<br />
                        <b>Horario:</b>'.($event->getAllDay()?" Todo el día":" Del ".($event->getStartDatetime()->format('d-m-Y H:i').' al '.$event->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>')->persist();
                $event->removeAfectado($afectado);
            }
        }
        $mailer->send();
        foreach ($a_ingresar as $id){
            /**
             * @var Usuario $usuario
             */
            $usuario = $this->getDoctrine()->getRepository('AppBundle:Usuario')->find($id);
            $event->addAfectado($usuario);
            $mailer->setNombre(self::DATO_CORREO)
                ->setAsunto(self::DATO_CORREO)
                ->setDestinatario($usuario->getCorreo())
                ->setCuerpo('Usted ha sido incluido en la Afectación: <br />
                        <b>Asunto:</b> '.$event->getTitle().'<br />
                        <b>Tipo:</b> '.$event->getMotivo()->getNombre().'<br />
                        <b>Horario:</b>'.($event->getAllDay()?" Todo el día":" Del ".($event->getStartDatetime()->format('d-m-Y H:i').' al '.$event->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por '.$creador.'</b>')->persist();
        }
        $mailer->send();

        $em->persist($event);
        $em->flush();
        return new Response(json_encode(array('success' => true)));
    }

    function changeAction(Request $request) {

        $id = $request->get('id');
        $newStartData = $request->get('newStartData');
        $newEndData = $request->get('newEndData');
        $allDay = $request->get('allDay');
        $this->get('fados.calendar.service')->changeDate($newStartData,$newEndData,$id,$allDay);

        return new Response($id, 201);
    }

    function storeAction(Request $request) {

        $start = $request->get('start');
        $end = $request->get('end');
        $title = $request->get('title');
        $allDay = $request->get('allDay');
        $color = $request->get('color', '#69a4e0');
        $affected = $request->get('affected');
        $type = $request->get('type');
        $desc = $request->get('desc');
        $notify = $request->get('notify');
        $notify = ($notify === true || $notify === 'true') ? true : false;
        //Busco las afectaciones (que estan en la sesión) que coincidan con la que voy a almacenar y la elimino de la sesión
        $session = $this->get('session');
        $afectaciones = $session->get('affectations', array());
        foreach($afectaciones as $afectacion){
            //Busco las que coinciden en el titulo y en el tipo
            if($afectacion['title'] == $title && $afectacion['type'] == $type)
                $to_remove = $afectacion['id'];
        }
        if(isset($to_remove)){
            unset($afectaciones[$to_remove]);
            $session->set('affectations', $afectaciones);
        }

        $id = $this->get('fados.calendar.service')->storeData($title, $start, $end, $allDay, $color, $affected, $type, $desc, $notify);

        return new Response(json_encode(array('id' => $id)));

    }

    function removeAction(Request $request){
        $this->get('fados.calendar.service')->removeData($request->get('id'));
        return new Response(1, 201);
    }

    /*
     * Change end date event
     *
     */
    function resizeAction(Request $request) {

        $id = $request->get('id');
        $newDate = $request->get('newDate');
        $this->get('fados.calendar.service')->resizeEvent($newDate,$id);

        return new Response($id, 201);

    }

}