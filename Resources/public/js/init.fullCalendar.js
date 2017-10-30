$(function () {
    /* initialize the calendar
     -----------------------------------------------------------------*/
    //Date for the calendar events (dummy data)
    var date = new Date();
    var d = date.getDate(),
        m = date.getMonth(),
        y = date.getFullYear();

    var pressedClose = false;

    //Renderizo el boton de eliminar en cada evento
    var renderCloseBtn = function(){
        setTimeout(function(){
            $('.fc-event').popover({
                placement: 'bottom',
                content: '<div class="btn-group">' +
                '<button type="button" class="btn btn-xs red-mint normal">Eliminar</button>' +
                '<button type="button" class="btn btn-xs btn-default normal">Editar</button>' +
                '<button type="button" class="btn btn-xs btn-default different" style="display: none;">Ver</button>' +
                '</div>',
                html: true,
                container: 'body'
            });
            $('.fc-event').on('show.bs.popover', function () {
                $('.popover:visible').popover('hide');
            });
        }, 200);
    };

    var actualEvent;

    $('#calendar-place').fullCalendar({
        lang: 'es',
        header: {
            left: 'today',
            center: 'title',
            right: 'prev,next,month,agendaWeek,agendaDay'
        },
        events:
            {
                url: Routing.generate('fullcalendar_loadevents', { month: moment().format('MM'), year: moment().format('YYYY'), userId: userId, full: full }),
                color: 'blue',
                textColor:'white',
                success: function(){
                    renderCloseBtn();
                    //$('#crear_afectacion').removeClass('disabled');
                },
                error: function() {
                    //alert('Error receving events');
                    toastr.error('','Error recibiendo los eventos');
                }
            },
        eventReceive: function(event){
            if(!event.allDay && event.type == vacaciones_id){
                var toast = $('.toast-error:visible');
                toastr.error('','Esta afectación tiene que ser un evento para todo el día.');
                //alert('Esta afectación tiene que ser un evento para todo el día.');
                var event_by_hour = $('.fc-content:contains("'+event.start.format('H:mm')+'")');
                event_by_hour.each(function(){
                    if($(this).find('.fc-title:contains("'+event.title+'")').length == 1){
                        $('#calendar-place').fullCalendar('removeEvents', event._id);
                        $(this).parents('.fc-event').remove();
                    }
                });
                return;
            }
            else
            if(!event.allDay && new Date(event.start.format("YYYY-MM-DD HH:mm")) < new Date())
            {
                toastr.error('','Esta afectación no puede inciar antes de la hora actual.');
                var event_by_hour = $('.fc-content:contains("'+event.start.format('H:mm')+'")');
                event_by_hour.each(function(){
                    if($(this).find('.fc-title:contains("'+event.title+'")').length == 1){
                        $('#calendar-place').fullCalendar('removeEvents', event._id);
                        $(this).parents('.fc-event').remove();
                    }
                });
                return;
            }
            var start = event.start.format('YYYY-MM-DD HH:mm');
            var is_dayView = $('.fc-agendaDay-button').hasClass('fc-state-active');
            var end = (event.end == null && is_dayView) ? moment(start).add(2, 'hours').format('YYYY-MM-DD HH:mm') : (is_dayView) ? event.end.format('YYYY-MM-DD HH:mm'): start;
            //renderCloseBtn();
            $.ajax({
                url: Routing.generate('fullcalendar_store'),
                data: { notify: event.notify, start: start, end: end, title: event.title, allDay: (event.allDay) ? 1 : 0, color: event.color, affected: event.affected, type: event.type, desc: event.desc },
                type: 'POST',
                dataType: 'json',
                success: function(json){
                    toastr.success('','Se ha agregado la Afectación correctamente.');
                    //Actualizo el id del evento para poder modificarlo posteriormente
                    event.id = json.id;
                    $('#calendar-place').fullCalendar('updateEvent', event);
                    $('.panel-title:contains("'+event.title+'")').parents('.panel').remove();
                    renderCloseBtn();
                },
                error: function(){
                    //alert('Error processing your request');
                    toastr.error('','Error procesando su solicitud.');
                }
            });
        },
        viewRender: function (view, element) {
            var month = view.calendar.getDate().format('MM');
            var year = view.calendar.getDate().format('YYYY');
            renderCloseBtn();
            //$('#crear_afectacion').addClass('disabled');
        },
        eventDrop: function(event,delta,revertFunc) {
            //alert(event.backgroundColor);
            if(!event.allDay && event.backgroundColor === vacaciones_color){
                var toast = $('.toast-error:visible');
                toastr.error('', 'Esta afectación tiene que ser un evento para todo el día.');
                //alert('Esta afectación tiene que ser un evento para todo el día.');
                revertFunc(event);
                return;
            }
            else
            if(!event.allDay && new Date(event.start.format("YYYY-MM-DD HH:mm")) < new Date()){
                toastr.error('','Esta afectación no puede inciar antes de la hora actual.');
                revertFunc(event);
                return;
            }
            var newStartData = event.start.format('YYYY-MM-DD');
            var newEndData = (event.end == null) ? newStartData : event.end.format('YYYY-MM-DD');
            var is_dayView = (($('.fc-agendaDay-button').hasClass('fc-state-active') || $('.fc-agendaWeek-button').hasClass('fc-state-active')) && !event.allDay);
            var start = (!is_dayView)?newStartData:event.start.format('YYYY-MM-DD HH:mm');
            var end = (!is_dayView)?newEndData:(event.end !== null)?event.end.format('YYYY-MM-DD HH:mm'):moment(start).add(2,'hours').format('YYYY-MM-DD HH:mm');
            console.log('is dayView: '+is_dayView);
            console.log(start+' '+end);
            $.ajax({
                url: Routing.generate('fullcalendar_changedate'),
                data: { id: event.id, newStartData: start,newEndData:end, allDay: event.allDay},
                type: 'POST',
                dataType: 'json',
                success: function(response){
                    toastr.success('','Se ha cambiado la fecha de la Afectación correctamente.');
                    renderCloseBtn();
                },
                error: function(e){
                    revertFunc();
                    //alert('Error processing your request: '+e.responseText);
                    toastr.error('', 'Error procesando su solicitud.');
                }
            });

        },
        eventResize: function(event, delta, revertFunc) {
            var is_dayView = (($('.fc-agendaDay-button').hasClass('fc-state-active') || $('.fc-agendaWeek-button').hasClass('fc-state-active')) && !event.allDay);
            var newData = (!is_dayView)?event.end.format('YYYY-MM-DD'):(event.end !== null)?event.end.format('YYYY-MM-DD HH:mm'):moment(start).add(2,'hours').format('YYYY-MM-DD HH:mm');
            $.ajax({
                url: Routing.generate('fullcalendar_resizedate'),
                data: { id: event.id, newDate: newData },
                type: 'POST',
                dataType: 'json',
                success: function(response){
                    toastr.success('','Se ha cambiado el rango de fecha de la Afectación correctamente.');
                    renderCloseBtn();
                },
                error: function(e){
                    revertFunc();
                    alert('Error processing your request: '+e.responseText);
                }
            });

        },
        eventMouseover: function(event, jsEvent, view){
            actualEvent = event;
        },
        eventClick: function(calEvent, jsEvent, view) {
            console.log('Event: ' + calEvent.title);
            console.log('Event: ' + calEvent.id);
            var hoy = moment().format('YYYY-MM-DD');
            if(moment(hoy)>moment(calEvent.start.format('YYYY-MM-DD')) && userId === 0)
            {
                $('.popover:visible').popover('hide');
            }
            else
            if(userId !== 0){
                $('.popover').find('.normal').hide().end().find('.different').show();
            }
            //Asignacion del handler del boton de editar
            $('.popover').find('.btn-default').on('click', function() {
                $.ajax({
                    url: Routing.generate('fullcalendar_view'),
                    data: {id: calEvent.id},
                    dataType: 'json',
                    type: 'POST',
                    success: function (json) {
                        //Oculto el popover
                        $('.popover').popover('hide');
                        $('#afectacion_nombre').val(json.title);
                        $('#afectacion_tipo').val(json.type).trigger('change.select2');
                        $('#afectacion_descripcion').val(json.desc);
                        $('#crearModal').attr('data-affected', json.affected);
                        $('#crearModal').attr('data-event', calEvent.id);
                        $('#crearModal').attr('data-allDay', calEvent.allDay);
                        $('#crearModal').modal('show');
                        if(userId > 0){
                            $('.ms-selectable, #afectacion_save').hide();
                            $('#cancelar').html("Cerrar");
                            $('.ms-selection').css({'float': 'left', 'width': '100%'});
                            $('#afectacion_afectados').prop('disabled', true);
                            $('.ms-list').unbind('click');
                            $('#afectacion_tipo').prop('disabled', true);
                            $('#afectacion_descripcion, #afectacion_nombre, #afectacion_notificar').prop('disabled', true);
                        }
                        calEvent.color = '#000';
                    },
                    error: function () {
                        toastr.error('', 'Ha ocurrido un error');
                        //alert('Ha ocurrido un error');
                        $('.popover').popover('hide');
                    }
                });
            });
            //Asignacion del handler del botón de eliminar
            $('.popover').find('.red-mint').on('click', function() {
                var self = $(this).parents('.popover');
                $('.popover').popover('hide');
                var element = $('[aria-describedby="'+self.attr('id')+'"]');
                if (confirm('¿Desea eliminar la Afectación?')) {
                    $.ajax({
                        url: Routing.generate('fullcalendar_remove'),
                        data: {id: calEvent.id},
                        type: 'POST',
                        dataType: 'json',
                        success: function (response) {
                            toastr.success('', 'Se eliminó la Afectación correctamente.');
                            //alert('Se elimino la Afectación correctamente.');
                            $('#calendar-place').fullCalendar('removeEvents', calEvent._id);
                            element.remove();
                        },
                        error: function (e) {
                            //alert('Error processing your request: ' + e.responseText);
                            toastr.error('', 'Error procesando su solicitud.');
                        }
                    });
                }
            });
        },
        editable: true,
        droppable: true
    });
});