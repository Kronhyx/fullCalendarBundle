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
            /*$('.fc-content').each(function(){
             //alert('aki');
             if($(this).find('.dismiss').length == 0){
             var self = $(this);
             var beforeDiv = $(this).find('div:first')
             var target = beforeDiv.length > 0 ? beforeDiv : $(this).find('span:first');
             $('<a class="pull-right dismiss">&times;</a>').insertBefore(target);
             $(this).find('a.dismiss').on('click', function(e){
             alert(actualEvent !== undefined);
             if(actualEvent && actualEvent !== undefined && confirm('¿Desea eliminar la Afectación?'))
             {
             pressedClose = true;
             $.ajax({
             url: Routing.generate('fullcalendar_remove'),
             data: { id: actualEvent.id  },
             type: 'POST',
             dataType: 'json',
             success: function(response){
             toastr.success('','Se elimino la Afectación correctamente.');
             self.parent().remove();
             setTimeout(function(){
             pressedClose = false;
             }, 200);
             },
             error: function(e){
             alert('Error processing your request: '+e.responseText);
             }
             });
             }
             else{
             pressedClose = true;
             setTimeout(function(){
             pressedClose = false;
             }, 200);
             }
             });
             }
             });*/
            $('.fc-event').popover({
                placement: 'bottom',
                content: '<div class="btn-group">' +
                '<button type="button" class="btn btn-xs red-mint">Eliminar</button>' +
                '<button type="button" class="btn btn-xs btn-default">Editar</button>' +
                '</div>',
                html: true,
                container: 'body'
            });
            $('.fc-event').on('show.bs.popover', function(){
                $('.popover').popover('hide');
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
                url:Routing.generate('fullcalendar_loadevents', { month: moment().format('MM'), year: moment().format('YYYY') }),
                color: 'blue',
                textColor:'white',
                success: function(){
                    renderCloseBtn();
                },
                error: function() {
                    alert('Error receving events');
                }
            },
        eventReceive: function(event){
            var start = event.start.format('YYYY-MM-DD HH:mm');
            var is_dayView = $('.fc-agendaDay-button').hasClass('fc-state-active');
            var end = (event.end == null && is_dayView) ? moment(start).add(2, 'hours').format('YYYY-MM-DD HH:mm') : (is_dayView) ? event.end.format('YYYY-MM-DD HH:mm'): start;
            //renderCloseBtn();
            $.ajax({
                url: Routing.generate('fullcalendar_store'),
                data: { notify: event.notify, start: start, end: end, title: event.title, allDay: (event.allDay) ? 1 : 0, color: event.color, affected: event.affected, type: event.type },
                type: 'POST',
                dataType: 'json',
                success: function(json){
                    console.log('ok');
                    //Actualizo el id del evento para poder modificarlo posteriormente
                    event.id = json.id;
                    $('#calendar-place').fullCalendar('updateEvent', event);
                    $('.panel-title:contains("'+event.title+'")').parents('.panel').remove();
                    renderCloseBtn();
                },
                error: function(){
                    alert('Error processing your request');
                }
            });
        },
        viewRender: function (view, element) {
            var month = view.calendar.getDate().format('MM');
            var year = view.calendar.getDate().format('YYYY');
            renderCloseBtn();
        },
        eventDrop: function(event,delta,revertFunc) {
            var newStartData = event.start.format('YYYY-MM-DD');
            var newEndData = (event.end == null) ? newStartData : event.end.format('YYYY-MM-DD');
            var is_dayView = $('.fc-agendaDay-button').hasClass('fc-state-active');
            var start = (!is_dayView)?newStartData:event.start.format('YYYY-MM-DD HH:mm');
            var end = (!is_dayView)?newEndData:event.end.format('YYYY-MM-DD HH:mm');
            $.ajax({
                url: Routing.generate('fullcalendar_changedate'),
                data: { id: event.id, newStartData: start,newEndData:end  },
                type: 'POST',
                dataType: 'json',
                success: function(response){
                    console.log('ok');
                    renderCloseBtn();
                },
                error: function(e){
                    revertFunc();
                    alert('Error processing your request: '+e.responseText);
                }
            });

        },
        eventResize: function(event, delta, revertFunc) {
            var is_dayView = $('.fc-agendaDay-button').hasClass('fc-state-active');
            var newData = (!is_dayView)?event.end.format('YYYY-MM-DD'):event.end.format('YYYY-MM-DD HH:mm');
            $.ajax({
                url: Routing.generate('fullcalendar_resizedate'),
                data: { id: event.id, newDate: newData },
                type: 'POST',
                dataType: 'json',
                success: function(response){
                    console.log('ok');
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
            if(moment(hoy)>moment(calEvent.start.format('YYYY-MM-DD')))
            {
                $('.popover:visible').popover('hide');
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
                        $('#form_nombre').val(json.title);
                        $('#form_tipo').val(json.type).trigger('change.select2');
                        $('#form_descripcion').val(json.desc);
                        $('#crearModal').attr('data-affected', json.affected);
                        $('#crearModal').attr('data-event', calEvent.id);
                        $('#crearModal').modal('show');
                        calEvent.color = '#000';
                    },
                    error: function () {
                        toastr.error('', 'Ha ocurrido un error');
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
                            toastr.success('', 'Se elimino la Afectación correctamente.');
                            element.remove();
                        },
                        error: function (e) {
                            alert('Error processing your request: ' + e.responseText);
                        }
                    });
                }
            });
        },
        editable: true,
        droppable: true
    });
});