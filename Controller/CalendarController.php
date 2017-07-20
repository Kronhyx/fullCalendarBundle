<?php

namespace fadosProduccions\fullCalendarBundle\Controller;

use CoreBundle\Entity\Afectacion;
use CoreBundle\Entity\Afectado;
use CoreBundle\Entity\Usuario;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Common\Persistence\ManagerRegistry;
use fadosProduccions\fullCalendarBundle\Model\CalendarManagerEntity as baseCalendarManager;

class CalendarController extends Controller
{
    private $manager;

    function loadAction(Request $request) {

        //Get start date
        $createdAt = $request->get('start');
        $endAt = $request->get('end');
        $dataFrom = new \DateTime($createdAt);
        $dataTo = new \DateTime($endAt);

        //Get entityManager
        $manager = $this->get('fados.calendar.service');
        $events = $manager->getEvents($dataFrom,$dataTo);

        $status = empty($events) ? Response::HTTP_NO_CONTENT : Response::HTTP_OK;
        $jsonContent = $manager->serialize($events);

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent($jsonContent);
        $response->setStatusCode($status);
        return $response;
    }

    public function viewAction(Request $request){
        $id = $request->get('id');
        $repo = $this->get('fados.calendar.service')->getRepo();
        $evento = $repo->find($id);
        $afectados = $evento->getAfectados();
        $ids = '';
        foreach ($afectados as $afectado)
            $ids .= $afectado->getUsuario()->getId().',';
        $tipo = $evento->getTipo();
        $dato_evento = array('title' => $evento->getTitle(), 'type' => (isset($tipo))?$evento->getTipo()->getId():null, 'desc' => $evento->getDescripcion(), 'affected' => trim($ids,','));
        return new Response(json_encode($dato_evento));
    }

    public function updateAction(Request $request){
        $em = $this->getDoctrine()->getManager();
        $repo = $this->get('fados.calendar.service')->getRepo();
        /**
         * @var Afectacion $event
         */
        $event = $repo->find($request->get('id'));

        $title = $request->get('title');
        $desc = $request->get('desc');
        $type = $request->get('type');
        $affected = $request->get('affected');
        $tipo_afectacion = $this->getDoctrine()->getRepository('CoreBundle:Tipo_Afectacion')->find($type);
        //Va indicando los cambios que se han ido haciendo en esta actualización para poder enviarlo por correo
        $cambios = '';

        if($event->getTitle() != $title){
            $cambios .= 'El <b>Asunto:</b> <i>'.$event->getTitle().'<i> por '.$title;
            $event->setTitle($title);
        }
        if($event->getTipo()->getId() != $type){
            $cambios .= '<br />El <b>Tipo:</b> <i>'.$event->getTipo()->getNombre().'<i> por '.$tipo_afectacion->getNombre();
            $event->setTipo($tipo_afectacion);
            $event->setBgColor($tipo_afectacion->getColor());
        }
        if($event->getDescripcion() != $desc && $event->getDescription() !== null){
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
        foreach ($afectados as $el){
            $afectado = $el->getUsuario();
            if(isset($afectado))
                $bd_afectados[] = $afectado->getId();
        }
        $todos = array_merge($ids, $bd_afectados);
        $a_eliminar = array_diff($todos, $ids);
        $a_ingresar = array_diff($todos, $bd_afectados);
        $se_mantienen = array_intersect($ids, $bd_afectados);
        //Recorro el arreglo de los afectados que luego de la modificación aun se mantienen
        foreach($se_mantienen as $id_mantiene){
            /**
             * @var Usuario $se_mantiene
             */
            $se_mantiene = $this->getDoctrine()->getRepository('CoreBundle:Usuario')->find($id_mantiene);
            $this->get('soporte')->sendMail(
                $se_mantiene->getCorreo(),
                'SISTEMA DE SOPORTE::AFECTACIÓN',
                [
                    'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                    'body'  =>
                        'Se ha modificado la Afectación: <br />
                        <b>Asunto:</b> '.$event->getTitle().'<br />
                        <b>Cambios:</b><br />'.$cambios.'
                        <b>Por x persona</b>'
                ]
            );
        }
        //Recorro el listado para ir eliminando los afectados que ya no stan
        foreach ($afectados as $el){
            $afectado = $el->getUsuario();
            //Si existe el usuario del afectado y esta en la lista de los que se desea eliminar
            if(isset($afectado) && array_search($afectado->getId(), $a_eliminar) !== false)
            {
                $this->get('soporte')->sendMail(
                    $el->getCorreo(),
                    'SISTEMA DE SOPORTE::AFECTACIÓN',
                    [
                        'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                        'body'  =>
                            'Se ha cancelado la Afectación: <br />
                        <b>Asunto:</b> '.$event->getTitle().'<br />
                        <b>Tipo:</b> '.$event->getTipo()->getNombre().'<br />
                        <b>Horario:</b>'.($event->getAllDay()?" Todo el día":" Del ".($event->getStartDatetime()->format('d-m-Y H:i').' al '.$event->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por x persona</b>'
                    ]
                );
                $em->remove($el);
                $em->flush();
            }
        }
        //$creador = $auditoria->getUsuario()->getNombre();
        foreach ($a_ingresar as $id){
            /**
             * @var Usuario $usuario
             */
            $usuario = $this->getDoctrine()->getRepository('CoreBundle:Usuario')->find($id);
            $nuevo = new Afectado();
            $nuevo->setAfectacion($event);
            $nuevo->setUsuario($usuario);
            $em->persist($nuevo);
            $em->flush();
            $this->container->get('soporte')->sendMail(
                $usuario->getCorreo(),
                'SISTEMA DE SOPORTE::AFECTACIÓN',
                [
                    'title' => 'SISTEMA DE SOPORTE::AFECTACIÓN',
                    'body'  =>
                        'Usted ha sido incluido en la Afectación: <br />
                        <b>Asunto:</b> '.$event->getTitle().'<br />
                        <b>Tipo:</b> '.$event->getTipo()->getNombre().'<br />
                        <b>Horario:</b>'.($event->getAllDay()?" Todo el día":" Del ".($event->getStartDatetime()->format('d-m-Y H:i').' al '.$event->getEndDatetime()->format('d-m-Y H:i'))).'<br />
                        <b>Por x persona</b>'
                ]
            );
        }
        $em->persist($event);
        $em->flush();
        return new Response(json_encode(array('success' => true)));
    }

    function changeAction(Request $request) {

        $id = $request->get('id');
        $newStartData = $request->get('newStartData');
        $newEndData = $request->get('newEndData');
        $this->get('fados.calendar.service')->changeDate($newStartData,$newEndData,$id);

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
        $notify = $request->get('notify');
        $notify = ($notify === true || $notify === 'true') ? true : false;

        $id = $this->get('fados.calendar.service')->storeData($title, $start, $end, $allDay, $color, $affected, $type, $notify);

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