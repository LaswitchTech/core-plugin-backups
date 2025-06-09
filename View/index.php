<!--
  Core Framework - View File

  @license    MIT (https://mit-license.org/)
  @author     Louis Ouellet <louis@laswitchtech.com>
-->
<div class="col-12" id="layout"></div>
<script>
    $(document).ready(function(){
        $.ajax({
            url: '/endpoint.php/backups/index',
            type: 'GET',dataType: 'json',
            error: function(xhr, status, error) {
                let color = 'info', icon = 'question-circle', title = builder.Locale.get(xhr.statusText), content = builder.Locale.get(xhr.responseText);
                switch(xhr.status){
                    case 403: color = 'danger'; icon = 'person'; break;
                    case 404: color = 'warning'; icon = 'question-diamond'; break;
                    case 500: color = 'danger'; icon = 'bug'; break;
                }
                builder.Component("alert","#layout",{icon:icon,color:color,title:title},function(alert,component){component.content.html('<pre class="m-0 p-2">'+content+'</pre>');});
            },
            success: function(response) {

                // Set Actions
                var actions = {
                    download:{
                        label: builder.Locale.get('Download'),
                        class:{
                            button: 'text-bg-light',
                        },
                        icon:'download',
                        action:function(event, table, dt, node, row, data){
                            window.location.href = '/plugin/backups/download?name='+data.name;
                        }
                    },
                    restore:{
                        label: builder.Locale.get('Restore'),
                        class:{
                            button: 'text-bg-info',
                        },
                        icon:'arrow-counterclockwise',
                        action:function(event, table, dt, node, row, data){
                            BackupModalRestore(data.name);
                        }
                    },
                    delete:{
                        label: builder.Locale.get('Delete'),
                        class:{
                            button: 'text-bg-danger',
                        },
                        icon:'trash',
                        action:function(event, table, dt, node, row, data){
                            var uuids = [];
                            uuids.push(data);
                            BackupModalDelete(uuids, dt);
                        }
                    },
                };

                // Set Buttons
                var buttons = [
                    {
                        className : 'btn-success',
                        init: function (dt, node){
                            $(node).removeClass('btn-secondary');
                        },
                        text: '<i class="bi bi-plus-lg me-2"></i>'+builder.Locale.get('Create'),
                        action:function(e, dt, node, config){
                            BackupModalCreate(dt);
                        },
                    },
                    {
                        className : 'btn-light',
                        init: function (dt, node){
                            $(node).removeClass('btn-secondary');
                        },
                        text: '<i class="bi bi-upload me-2"></i>'+builder.Locale.get('Upload'),
                        action:function(e, dt, node, config){
                            BackupModalUpload(dt);
                        },
                    },
                    {
                        className : 'btn-blue',
                        init: function (dt, node){
                            $(node).removeClass('btn-secondary');
                        },
                        text: '<i class="bi bi-gear me-2"></i>'+builder.Locale.get('Configure'),
                        action:function(e, dt, node, config){
                            // BackupModalUpload(dt);
                        },
                    },
                    {
                        extend : 'selected',
                        className : 'btn-danger requires-selection d-none',
                        init: function (dt, node){
                            $(node).removeClass('btn-secondary');
                        },
                        text: '<i class="bi bi-trash me-2"></i>'+builder.Locale.get('Delete'),
                        action:function(e, dt, node, config){
                            var uuids = dt.rows({ selected: true }).data().toArray();
                            BackupModalDelete(uuids, dt);
                        },
                    },
                ];

                // Layout
                builder.Layout(
                    "list",
                    "#layout",
                    {
                        title: builder.Locale.get('Backups'),
                        icon: 'file-zip',
                        advancedSearch:true,
                        exportTools:true,
                        columnsVisibility:true,
                        selectTools:true,
                        showButtonsLabel: false,
                        actions:actions,
                        buttons:buttons,
                        columnDefs:[
                            { target: 0, visible: true, title: builder.Locale.get('Name'), name: 'name', data: 'name', render: function(data, type, row) {
                                var object = $(document.createElement('span'))
                                    .addClass('my-2')
                                    .text(data)
                                return object.prop('outerHTML');
                            }},
                            { target: 1, visible: false, title: builder.Locale.get('Path'), name: 'path', data: 'path', render: function(data, type, row) {
                                var object = $(document.createElement('span'))
                                    .addClass('my-2')
                                    .text(data)
                                return object.prop('outerHTML');
                            }},
                            { target: 2, visible: true, title: builder.Locale.get('Size'), name: 'size', data: 'size', render: function(data, type, row) {
                                var object = $(document.createElement('span'))
                                    .addClass('my-2')
                                    .text(data)
                                return object.prop('outerHTML');
                            }},
                            { target: 3, visible: true, title: builder.Locale.get('Date'), name: 'date', data: 'date', render: function(data, type, row) {
                                var object = $(document.createElement('span'))
                                    .addClass('my-2')
                                    .text(data)
                                return object.prop('outerHTML');
                            }},
                            { target: 4, visible: false, title: builder.Locale.get('Hash'), name: 'hash', data: 'hash', render: function(data, type, row) {
                                var object = $(document.createElement('span'))
                                    .addClass('my-2')
                                    .text(data)
                                return object.prop('outerHTML');
                            }},
                        ],
                    },
                    function(layout, component){

                        // Set container
                        var container = component.card._component.body;

                        // Lower the z-index of the table
                        component.table._component.table.addClass('z-2');

                        // Add Records to Layout
                        for(const [key, record] of Object.entries(response)){
                            layout.add(record);
                        }
                    },
                );
            },
        });
    });
</script>
